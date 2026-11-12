<?php
require_once 'config.php';
require_once 'mailer.php';
require_once 'two-factor-helpers.php';
require_once __DIR__ . '/includes/i18n.php';
i18n_start_page_translation();

$requestMethod = $_SERVER["REQUEST_METHOD"] ?? "GET";
const LOGIN_MAX_FAILED_ATTEMPTS = 3;
const LOGIN_LOCK_MINUTES = 15;

// ── Validate redirect target (prevent open redirect) ─────────────
$next = $_GET['next'] ?? ($_POST['next'] ?? 'index.php');
// Only allow relative paths — block absolute URLs, protocol-relative, and javascript:
if (preg_match('#^(https?://|//|javascript:)#i', $next) || strpos($next, '..') !== false) {
    $next = 'index.php';
}

// ─── Si déjà connecté → rediriger ────────────────────────────────
if (isset($_SESSION["client_id"])) {
    header("Location: $next");
    exit();
}

// ─── Variables ───────────────────────────────────────────────────
$errors   = [];
$success  = false;
$email    = "";

if ($requestMethod !== "POST") {
    $oauthError = $_GET['error'] ?? '';
    $providerLabels = [
        'facebook' => 'Facebook',
        'steam' => 'Steam',
        'apple' => 'Apple',
    ];

    if ($oauthError === 'google_auth_failed') {
        $detail = $_GET['detail'] ?? '';
        $errors["general"] = i18n_t('auth.google_login_failed', [], 'Google login failed. Please try again or use your email.')
            . ($detail !== '' ? " [Debug: " . htmlspecialchars($detail) . "]" : "");
    } elseif ($oauthError === 'provider_unavailable') {
        $provider = strtolower((string) ($_GET['provider'] ?? ''));
        $providerName = $providerLabels[$provider] ?? i18n_t('auth.this_provider', [], 'This provider');
        $errors["general"] = $providerName . " " . i18n_t('auth.not_configured', [], 'is not configured for login yet.');
    }
}

// ─── Traitement du formulaire ─────────────────────────────────────
if ($requestMethod === "POST") {

    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? null)) {
        $errors["general"] = i18n_t('auth.invalid_session', [], 'Invalid session, please try again.');
    }

    if (empty($errors) && defined('TURNSTILE_SITE_KEY') && TURNSTILE_SITE_KEY !== '') {
        $cfToken = $_POST['cf-turnstile-response'] ?? '';
        if (!verifyTurnstile($cfToken)) {
            $errors["general"] = i18n_t('auth.captcha_failed', [], 'CAPTCHA verification failed. Please try again.');
        }
    }

    $email    = trim($_POST["email"] ?? "");
    $pass_raw = trim($_POST["pass"]  ?? "");
    $remember = isset($_POST["remember"]);

    // ── Validation basique ────────────────────────────────────────
    if (empty($errors) && (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        $errors["email"] = i18n_t('auth.invalid_email', [], 'Invalid email address.');
    }

    if (empty($errors) && empty($pass_raw)) {
        $errors["pass"] = i18n_t('auth.password_required', [], 'Password is required.');
    }

    // ── Vérification en base ──────────────────────────────────────
    if (empty($errors)) {
        $pdo = db();
        ensureLoginLockoutColumns($pdo);
        ensureLoginAttemptsTable($pdo);
        twoFactorEnsureColumns($pdo);

        $attemptKey = loginAttemptKey($email);
        $globalLockedUntil = loginLockedUntil($pdo, $attemptKey);
        
        if ($globalLockedUntil !== null && $globalLockedUntil <= new DateTime()) {
            clearGlobalLoginAttempts($pdo, $attemptKey);
            $globalLockedUntil = null;
        }

        if ($globalLockedUntil !== null && $globalLockedUntil > new DateTime()) {
            $errors["general"] = i18n_t('auth.too_many_attempts', ['time' => $globalLockedUntil->format('H:i')], 'Too many attempts. Try again after ' . $globalLockedUntil->format('H:i') . '.');
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id_client, nom, email, telephone, mot_de_passe, email_verified, deleted_at, failed_login_attempts, locked_until, two_factor_enabled, two_factor_method, two_factor_totp_secret, is_suspended, suspension_reason FROM Client WHERE email = ?");
        $stmt->execute([$email]);
        $client = $stmt->fetch();

        if ($client && !empty($client["is_suspended"])) {
            $errors["general"] = i18n_t('auth.account_suspended', ['reason' => ($client["suspension_reason"] ?: i18n_t('auth.not_specified', [], 'Not specified'))], 'Your account has been suspended.') . " " . ($client["suspension_reason"] ?: i18n_t('auth.not_specified', [], 'Not specified'));
        }

        if (empty($errors)) {
            $lockedUntil = ($client && !empty($client["locked_until"])) ? new DateTime($client["locked_until"]) : null;
        $now = new DateTime();

        if ($client && $lockedUntil && $lockedUntil <= $now) {
            resetFailedLogins($pdo, (int) $client["id_client"]);
            $client["failed_login_attempts"] = 0;
            $client["locked_until"] = null;
            $lockedUntil = null;
        }

        if ($client && $lockedUntil && $lockedUntil > $now) {
            $errors["general"] = i18n_t('auth.too_many_attempts', ['time' => $lockedUntil->format('H:i')], 'Too many attempts. Try again after ' . $lockedUntil->format('H:i') . '.');
        } elseif (!$client || !password_verify($pass_raw, $client["mot_de_passe"])) {
            registerGlobalFailedLogin($pdo, $attemptKey, $email);
            if ($client) {
                $isNowLocked = registerFailedLogin($pdo, (int) $client["id_client"], (int) $client["failed_login_attempts"]);
                if ($isNowLocked) {
                    $lockUntil = (new DateTime())->modify('+' . LOGIN_LOCK_MINUTES . ' minutes');
                    $errors["general"] = i18n_t('auth.too_many_attempts', ['time' => $lockUntil->format('H:i')], 'Too many attempts. Try again after ' . $lockUntil->format('H:i') . '.');
                }
            }
            // Check global lock after registering failed attempt
            $newGlobalLockedUntil = loginLockedUntil($pdo, $attemptKey);
            if ($newGlobalLockedUntil !== null && $newGlobalLockedUntil > new DateTime()) {
                $errors["general"] = i18n_t('auth.too_many_attempts', ['time' => $newGlobalLockedUntil->format('H:i')], 'Too many attempts. Try again after ' . $newGlobalLockedUntil->format('H:i') . '.');
            }
            if (empty($errors["general"])) {
                $errors["general"] = i18n_t('auth.wrong_credentials', [], 'Incorrect email or password.');
            }
        } elseif (empty($client["email_verified"])) {
            resetFailedLogins($pdo, (int) $client["id_client"]);
            $errors["general"] = i18n_t('auth.email_not_verified', [], 'Your email is not verified yet. Check your inbox.');
            $showResendLink = true;
        } elseif (!empty($client["deleted_at"])) {
            resetFailedLogins($pdo, (int) $client["id_client"]);
            $deletedAt = new DateTime($client["deleted_at"]);
            $deadline = (clone $deletedAt)->modify('+5 days');
            if ($now > $deadline) {
                $errors["general"] = i18n_t('auth.account_deleted', [], 'This account has been deleted. Contact support for assistance.');
            } else {
                // Still in grace period — allow login, redirect to account
                if (!empty($client['two_factor_enabled'])) {
                    startTwoFactorLogin($client, 'account.php?tab=security', false, $_POST['two_factor_method'] ?? null);
                }
                session_regenerate_id(true);
                $_SESSION["client_id"]  = $client["id_client"];
                $_SESSION["client_nom"] = $client["nom"];
                $_SESSION["client_email"] = $email;
                applyLoginSessionLifetime(false);
                $success = true;
                header("Location: account.php?tab=security");
                exit();
            }
        } else {
            resetFailedLogins($pdo, (int) $client["id_client"]);
            clearGlobalLoginAttempts($pdo, $attemptKey);
            // ── Connexion réussie ─────────────────────────────────
            if (!empty($client['two_factor_enabled'])) {
                startTwoFactorLogin($client, $next, $remember, $_POST['two_factor_method'] ?? null);
            }
            session_regenerate_id(true);
            $_SESSION["client_id"]  = $client["id_client"];
            $_SESSION["client_nom"] = $client["nom"];
            $_SESSION["client_email"] = $email;

            // ── Record device fingerprint ──
            try {
                $fpIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $fpUa = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $fpHash = hash('sha256', $fpIp . '|' . $fpUa);
                $fpStmt = $pdo->prepare("SELECT id FROM login_device_fingerprints WHERE client_id = ? AND device_hash = ?");
                $fpStmt->execute([(int) $client["id_client"], $fpHash]);
                if ($fpStmt->fetch()) {
                    $pdo->prepare("UPDATE login_device_fingerprints SET last_login_at = NOW() WHERE client_id = ? AND device_hash = ?")->execute([(int) $client["id_client"], $fpHash]);
                } else {
                    $pdo->prepare("INSERT INTO login_device_fingerprints (client_id, ip_address, user_agent, device_hash) VALUES (?, ?, ?, ?)")->execute([(int) $client["id_client"], $fpIp, $fpUa, $fpHash]);
                }
            } catch (Throwable $e) { /* Never block the login */ }

            // Remember me → cookie 30 jours
            applyLoginSessionLifetime($remember);

            $success = true;

            header("Location: $next");
            exit();
            }
        }
    }
}
}

// ─── Helper CSS ───────────────────────────────────────────────────
function grp(string $field, array $errors): string {
    return isset($errors[$field]) ? 'form-group invalid' : 'form-group';
}

function ensureLoginLockoutColumns(PDO $pdo): void
{
    try {
        $pdo->exec("ALTER TABLE Client ADD COLUMN failed_login_attempts INT NOT NULL DEFAULT 0");
    } catch (PDOException $e) {
        // Column already exists.
    }

    try {
        $pdo->exec("ALTER TABLE Client ADD COLUMN locked_until DATETIME DEFAULT NULL");
    } catch (PDOException $e) {
        // Column already exists.
    }

    try {
        $pdo->exec("ALTER TABLE Client ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0");
    } catch (PDOException $e) {
        // Column already exists.
    }
}

function startTwoFactorLogin(array $client, string $next, bool $remember, ?string $preferredMethod): void
{
    twoFactorStartLoginChallenge($client, $next, $remember, $preferredMethod);
}

function registerFailedLogin(PDO $pdo, int $clientId, int $currentAttempts): bool
{
    $nextAttempts = $currentAttempts + 1;

    if ($nextAttempts >= LOGIN_MAX_FAILED_ATTEMPTS) {
        $lockedUntil = (new DateTime())->modify('+' . LOGIN_LOCK_MINUTES . ' minutes')->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "UPDATE Client
             SET failed_login_attempts = ?,
                 locked_until = ?
             WHERE id_client = ?"
        );
        $stmt->execute([$nextAttempts, $lockedUntil, $clientId]);
        return true;
    }

    $stmt = $pdo->prepare(
        "UPDATE Client
         SET failed_login_attempts = ?, locked_until = NULL
         WHERE id_client = ?"
    );
    $stmt->execute([$nextAttempts, $clientId]);
    return false;
}

function resetFailedLogins(PDO $pdo, int $clientId): void
{
    $stmt = $pdo->prepare(
        "UPDATE Client
         SET failed_login_attempts = 0, locked_until = NULL
         WHERE id_client = ?"
    );
    $stmt->execute([$clientId]);
}

function loginClientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function loginAttemptKey(string $email): string
{
    return hash('sha256', strtolower(trim($email)) . '|' . loginClientIp());
}

function ensureLoginAttemptsTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS client_login_attempts (
            attempt_key VARCHAR(64) PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            failed_attempts INT NOT NULL DEFAULT 0,
            locked_until DATETIME DEFAULT NULL,
            last_failed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_locked_until (locked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function loginLockedUntil(PDO $pdo, string $attemptKey): ?DateTime
{
    $stmt = $pdo->prepare("SELECT locked_until FROM client_login_attempts WHERE attempt_key = ?");
    $stmt->execute([$attemptKey]);
    $lockedUntil = $stmt->fetchColumn();
    return $lockedUntil ? new DateTime((string) $lockedUntil) : null;
}

function registerGlobalFailedLogin(PDO $pdo, string $attemptKey, string $email): void
{
    $stmt = $pdo->prepare("SELECT failed_attempts FROM client_login_attempts WHERE attempt_key = ?");
    $stmt->execute([$attemptKey]);
    $attempts = $stmt->fetchColumn();

    if ($attempts === false) {
        $stmt = $pdo->prepare(
            "INSERT INTO client_login_attempts (attempt_key, email, ip_address, failed_attempts, last_failed_at)
             VALUES (?, ?, ?, 1, NOW())"
        );
        $stmt->execute([$attemptKey, strtolower(trim($email)), loginClientIp()]);
        return;
    }

    $nextAttempts = (int) $attempts + 1;
    $lockedUntil = null;
    if ($nextAttempts >= LOGIN_MAX_FAILED_ATTEMPTS) {
        $lockedUntil = (new DateTime())->modify('+' . LOGIN_LOCK_MINUTES . ' minutes')->format('Y-m-d H:i:s');
    }

    $stmt = $pdo->prepare(
        "UPDATE client_login_attempts
         SET failed_attempts = ?, last_failed_at = NOW(), locked_until = ?
         WHERE attempt_key = ?"
    );
    $stmt->execute([$nextAttempts, $lockedUntil, $attemptKey]);
}

function clearGlobalLoginAttempts(PDO $pdo, string $attemptKey): void
{
    $stmt = $pdo->prepare("DELETE FROM client_login_attempts WHERE attempt_key = ?");
    $stmt->execute([$attemptKey]);
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(i18n_current_locale(), ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars(i18n_direction(), ENT_QUOTES, 'UTF-8') ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Maroc PC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;800&family=Syne:wght@400;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/signup.css?v=<?= urlencode((string) filemtime(__DIR__ . '/assets/css/signup.css')) ?>">
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'dark');</script>
    <?php if (defined('TURNSTILE_SITE_KEY') && TURNSTILE_SITE_KEY !== ''): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" defer></script>
    <?php endif; ?>

    <style>
        /* ── Surcharge : affiche .error-msg quand PHP ajoute .invalid ── */
        .form-group.invalid .error-msg {
            display: block;
        }

        /* ── Alerte générale erreur ─────────────────────────────────── */
        .alert-error {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 24px;
            background: rgba(255, 61, 90, 0.08);
            border: 1px solid var(--red);
            color: var(--red);
        }

        /* ── Toast succès ───────────────────────────────────────────── */
        .alert-success-toast {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 24px;
            background: rgba(0, 230, 118, 0.08);
            border: 1px solid var(--green);
            color: var(--green);
        }
    </style>
	<link rel="stylesheet" href="assets/css/light-mode-industrial.css">
</head>
<body class="auth-page auth-balanced auth-login">

    <a href="<?= htmlspecialchars(i18n_url('index.php'), ENT_QUOTES, 'UTF-8') ?>" class="back-link">&larr; <?php i18n_e('auth.back_to_store', [], 'Back to Store'); ?></a>

    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme" style="position: absolute; top: 1.5rem; right: 2rem;">
        <i class="fas fa-sun icon-sun"></i>
        <i class="fas fa-moon icon-moon"></i>
    </button>
    <?= i18n_language_switcher('nav-translate', 'position: absolute; top: 1.5rem; right: 6rem;') ?>


    <div class="container login-container">

        <!-- ── Côté image ─────────────────────────────────────── -->
        <div class="hero-side">
            <img src="signup.webp" alt="Gaming setup workspace">
            <div class="hero-overlay">
                <span class="hero-kicker"><?= i18n_t('auth.account_access', [], 'Account access') ?></span>
                <h2><?= i18n_t('auth.your_setup_ready', [], 'Your setup is waiting') ?></h2>
                <p><?= i18n_t('auth.hero_description', [], 'Access saved builds, order history, and faster checkout from one secure place.') ?></p>
                <div class="hero-trust-list" aria-label="Account benefits">
                    <span><i class="fas fa-box-open"></i> <?= i18n_t('auth.hero_orders', [], 'Orders') ?></span>
                    <span><i class="fas fa-bookmark"></i> <?= i18n_t('auth.hero_saved_parts', [], 'Saved parts') ?></span>
                    <span><i class="fas fa-shield-halved"></i> <?= i18n_t('auth.hero_2fa', [], '2FA ready') ?></span>
                </div>
            </div>
        </div>

        <!-- ── Côté formulaire ────────────────────────────────── -->
        <div class="inscription">

            <?php if ($success): ?>
                <div class="alert-success-toast">
                    ✅ <?= i18n_t('auth.login_successful', [], 'Login successful! Redirecting...') ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors["general"])): ?>
                <div class="alert-error">
                    ❌ <?= htmlspecialchars($errors["general"]) ?>
                    <?php if (!empty($showResendLink)): ?>
                        <br><a href="resend-verification.php?email=<?= urlencode($email) ?>" style="color:#00f5d4;text-decoration:underline;font-size:0.85rem;"><?= i18n_t('auth.resend_verification', [], 'Resend verification email →') ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form name="login" method="post" action="login.php?next=<?= urlencode($next) ?>" novalidate
                  <?= $success ? 'style="display:none"' : '' ?>>
                <?= csrfField() ?>
                <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">

                <span class="auth-kicker"><?= i18n_t('auth.account_kicker', [], 'Maroc PC account') ?></span>
                <h3 id="myH3"><?= i18n_t('auth.welcome_back', [], 'Welcome back') ?></h3>
                <p class="subtitle"><?= i18n_t('auth.login_subtitle', [], 'Sign in to manage orders, saved builds, and account security.') ?></p>

                <!-- ── Email ──────────────────────────────────── -->
                <div class="<?= grp('email', $errors) ?>">
                    <label for="login-email"><?= i18n_t('auth.email_label', [], 'Email Address') ?></label>
                    <input type="email" name="email" id="login-email" class="hh"
                           placeholder="<?= i18n_t('auth.email_placeholder', [], 'john@example.com') ?>"
                           value="<?= htmlspecialchars($email) ?>" required>
                    <span class="error-msg" id="err-login-email">
                        <?= isset($errors["email"])
                            ? htmlspecialchars($errors["email"])
                            : i18n_t('auth.valid_email_hint', [], 'Please enter a valid email') ?>
                    </span>
                </div>

                <!-- ── Mot de passe ───────────────────────────── -->
                <div class="<?= grp('pass', $errors) ?>">
                    <label for="login-pass"><?= i18n_t('auth.password_label', [], 'Password') ?></label>
                    <div class="password-wrap">
                        <input type="password" name="pass" id="login-pass" class="hh"
                               placeholder="<?= i18n_t('auth.password_placeholder', [], '••••••••') ?>" required>
                        <button type="button" class="toggle-pass" id="loginTogglePass"
                                aria-label="<?= i18n_t('auth.show_password', [], 'Show password') ?>"><i class="fas fa-eye"></i></button>
                    </div>
                    <span class="error-msg" id="err-login-pass">
                        <?= isset($errors["pass"])
                            ? htmlspecialchars($errors["pass"])
                            : i18n_t('auth.password_required_hint', [], 'Password is required') ?>
                    </span>
                </div>


                <div class="form-options">
                    <label class="remember-label">
                        <input type="checkbox" name="remember" id="remember"
                               <?= isset($_POST["remember"]) ? "checked" : "" ?>>
                        <span class="check-box"></span>
                        <span class="remember-copy">
                            <span class="remember-text"><?= i18n_t('auth.remember_label', [], 'Remember this device') ?></span>
                            <small><?= i18n_t('auth.remember_sublabel', [], 'Keep me signed in for 30 days.') ?></small>
                        </span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link"><?= i18n_t('auth.forgot_password', [], 'Forgot password?') ?></a>
                </div>

                <?php if (defined('TURNSTILE_SITE_KEY') && TURNSTILE_SITE_KEY !== ''): ?>
                    <div style="display: flex; justify-content: center; margin-bottom: 20px;">
                        <div class="cf-turnstile" 
                             data-sitekey="<?= htmlspecialchars(TURNSTILE_SITE_KEY) ?>"
                             data-callback="onTurnstileSuccess"
                             data-error-callback="onTurnstileError"
                             data-expired-callback="onTurnstileExpired"></div>
                    </div>
                    <div id="turnstileError" style="display: none; text-align: center; color: var(--danger); font-size: 0.9rem; margin-bottom: 16px;"></div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="Bou" id="loginBtn"><?= i18n_t('auth.sign_in', [], 'Sign In') ?></button>
                </div>

                <div class="social-login">
                    <p><?= i18n_t('auth.or_continue_with', [], 'Or continue with') ?></p>
                    <div class="social-provider-grid">
                        <a href="google-callback.php?next=<?= urlencode($next) ?>" class="social-provider provider-google">
                            <i class="fab fa-google"></i>
                            <span><?= i18n_t('auth.provider_google', [], 'Google') ?></span>
                        </a>
                        <a href="facebook-login.php?next=<?= urlencode($next) ?>" class="social-provider provider-facebook">
                            <i class="fab fa-facebook-f"></i>
                            <span><?= i18n_t('auth.provider_facebook', [], 'Facebook') ?></span>
                        </a>
                        <a href="discord-login.php?next=<?= urlencode($next) ?>" class="social-provider provider-discord">
                            <i class="fab fa-discord"></i>
                            <span><?= i18n_t('auth.provider_discord', [], 'Discord') ?></span>
                        </a>
                        <a href="steam-login.php?next=<?= urlencode($next) ?>" class="social-provider provider-steam">
                            <i class="fab fa-steam"></i>
                            <span><?= i18n_t('auth.provider_steam', [], 'Steam') ?></span>
                        </a>

                    </div>
                </div>

                <p class="login-link" style="margin-top: 1.5rem;">
                    <?= i18n_t('auth.new_here', [], 'New here?') ?> <a href="signup.php" data-auth-transition="signup"><?= i18n_t('auth.create_account', [], 'Create an account') ?></a>
                </p>

            </form>
        </div>
    </div>

    <!-- ── Toast Notification ─────────────────────────────────── -->
    <div class="toast <?= $success ? 'show' : '' ?>" id="loginToast">
        <i>⚡</i>
        <span id="loginToastMsg">
            <?php if ($success): ?>
                <?= i18n_t('auth.welcome_toast', ['name' => htmlspecialchars($_SESSION["client_nom"])], 'Welcome, ' . htmlspecialchars($_SESSION["client_nom"]) . '!') ?>
            <?php else: ?>
                <?= i18n_t('auth.welcome_guest', [], 'Welcome!') ?>
            <?php endif; ?>
        </span>
    </div>

    <?= i18n_language_switcher_assets() ?>
    <script src="assets/js/login.js?v=<?= urlencode((string) filemtime(__DIR__ . '/assets/js/login.js')) ?>" defer></script>
    <script src="assets/js/theme.js" defer></script>
    
    <script>
        (function() {
            // 1. If PHP logged us in successfully, plant the footprint
            <?php if ($success): ?>
                localStorage.setItem('has_active_session', '1');
            <?php endif; ?>

            // 2. If we are on the login page, and we have a footprint, it means we got disconnected!
            <?php if (!$success): ?>
                // Also check if PHP explicitly told us session expired via GET parameter
                const isGetExpired = <?= (isset($_GET['session_expired']) && $_GET['session_expired'] == 1) ? 'true' : 'false' ?>;
                
                if (localStorage.getItem('has_active_session') === '1' || isGetExpired) {
                    // Remove footprint so we don't show toast again on refresh
                    localStorage.removeItem('has_active_session');
                    // Empty the shopping cart because session expired
                    localStorage.removeItem('cart');
                    
                    const displayToast = () => {
                        setTimeout(() => {
                            const toast = document.getElementById('loginToast');
                            const toastMsg = document.getElementById('loginToastMsg');
                            if (toast && toastMsg) {
                                toastMsg.textContent = <?= json_encode(i18n_t('auth.session_expired'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
                                toast.style.borderColor = 'var(--red)';
                                const icon = toast.querySelector('i');
                                if (icon) {
                                    icon.textContent = '✕';
                                    icon.style.color = 'var(--red)';
                                }
                                toast.classList.add('show');
                                setTimeout(() => toast.classList.remove('show'), 5000);
                            }
                        }, 100);
                    };
                    
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', displayToast);
                    } else {
                        displayToast();
                    }
                }
            <?php endif; ?>
        })();
    </script>
    <script src="assets/js/auth-premium.js?v=<?= urlencode((string) filemtime(__DIR__ . '/assets/js/auth-premium.js')) ?>" defer></script>
    
    <?php if (defined('TURNSTILE_SITE_KEY') && TURNSTILE_SITE_KEY !== ''): ?>
    <script>
        // Turnstile Callbacks with comprehensive error handling
        let turnstileToken = null;
        
        function onTurnstileSuccess(token) {
            turnstileToken = token;
            console.log('✅ Turnstile: Token received successfully');
            
            // Hide error message if visible
            const errorDiv = document.getElementById('turnstileError');
            if (errorDiv) {
                errorDiv.style.display = 'none';
            }
            
            // Enable submit button
            const submitBtn = document.getElementById('loginBtn');
            if (submitBtn) {
                submitBtn.disabled = false;
            }
        }
        
        function onTurnstileError(error) {
            console.error('❌ Turnstile: Error occurred', error);
            turnstileToken = null;
            
            // Show user-friendly error message
            const errorDiv = document.getElementById('turnstileError');
            if (errorDiv) {
                errorDiv.textContent = <?= json_encode(i18n_t('auth.turnstile_error', [], '⚠️ Security check failed. Please refresh the page and try again.'), JSON_UNESCAPED_UNICODE) ?>;
                errorDiv.style.display = 'block';
            }
            
            // Disable submit button
            const submitBtn = document.getElementById('loginBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            
            // Log to help debugging
            console.log('💡 Troubleshooting tips:');
            console.log('1. Check if site key matches Cloudflare dashboard');
            console.log('2. Verify domain is whitelisted in Cloudflare');
            console.log('3. Disable ad blockers/privacy extensions');
            console.log('4. Check browser console for blocked requests');
        }
        
        function onTurnstileExpired() {
            console.warn('⏰ Turnstile: Token expired (tokens last 5 minutes)');
            turnstileToken = null;
            
            // Show expiration message
            const errorDiv = document.getElementById('turnstileError');
            if (errorDiv) {
                errorDiv.textContent = <?= json_encode(i18n_t('auth.turnstile_expired', [], '⏰ Security check expired. The widget will refresh automatically.'), JSON_UNESCAPED_UNICODE) ?>;
                errorDiv.style.display = 'block';
                
                // Auto-hide after 3 seconds as widget auto-refreshes
                setTimeout(() => {
                    errorDiv.style.display = 'none';
                }, 3000);
            }
        }
        
        // Prevent form submission if token missing or expired
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[name="login"]');
            const submitBtn = document.getElementById('loginBtn');
            
            if (form && submitBtn) {
                form.addEventListener('submit', function(e) {
                    const tokenInput = document.querySelector('input[name="cf-turnstile-response"]');
                    const token = tokenInput ? tokenInput.value : '';
                    
                    if (!token) {
                        e.preventDefault();
                        console.error('❌ Form submission blocked: No Turnstile token');
                        
                        const errorDiv = document.getElementById('turnstileError');
                        if (errorDiv) {
                            errorDiv.textContent = <?= json_encode(i18n_t('auth.turnstile_missing', [], '⚠️ Please complete the security check before signing in.'), JSON_UNESCAPED_UNICODE) ?>;
                            errorDiv.style.display = 'block';
                        }
                        
                        // Shake effect for visual feedback
                        submitBtn.style.animation = 'shake 0.5s';
                        setTimeout(() => {
                            submitBtn.style.animation = '';
                        }, 500);
                        
                        return false;
                    }
                    
                    console.log('✅ Form submission: Turnstile token present');
                });
            }
        });
        
        // Debug mode: Log widget load
        console.log('🔒 Turnstile: Widget initialized');
    </script>
    <style>
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
    </style>
    <?php endif; ?>
</body>
</html>
