<?php
require_once 'admin-helpers.php';
require_once __DIR__ . '/includes/i18n.php';
i18n_start_page_translation();

$requestMethod = $_SERVER["REQUEST_METHOD"] ?? "GET";
// ADMIN_LOGIN_FAILED_MESSAGE and ADMIN_LOGIN_LOCK_MESSAGE are now resolved via i18n_t() directly at usage points
const ADMIN_MAX_FAILED_ATTEMPTS = 3;
const ADMIN_LOCK_MINUTES = 15;

$adminSessionExpired = isset($_GET['session_expired']) && $_GET['session_expired'] === '1';
if ($adminSessionExpired && isset($_COOKIE['has_active_admin_session'])) {
    setcookie('has_active_admin_session', '', appCookieOptions(time() - 3600));
}

// ─── Si déjà connecté ────────────────────────────────
if (isset($_SESSION["admin_id"])) {
    header("Location: dashboard.php");
    exit();
}

$errors   = [];
$success  = false;
$email    = "";

if ($adminSessionExpired) {
    $errors["general"] = i18n_t('auth.session_expired', [], 'Your session has expired. Please sign in again.');
}

$pdo = db();
ensureAdminUsersTable($pdo);
$adminSetupNeeded = adminCountAdmins($pdo) === 0;

if ($requestMethod === "POST") {
    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? null)) {
        $errors["general"] = i18n_t('auth.admin_invalid_session', [], 'Invalid session, please try again.');
    }

    if (empty($errors) && defined('TURNSTILE_SITE_KEY') && TURNSTILE_SITE_KEY !== '') {
        $cfToken = $_POST['cf-turnstile-response'] ?? '';
        if (!verifyTurnstile($cfToken)) {
            $errors["general"] = i18n_t('auth.admin_captcha_failed', [], 'CAPTCHA verification failed. Please try again.');
        }
    }

    $email    = trim($_POST["email"] ?? "");
    $pass_raw = trim($_POST["pass"]  ?? "");

    if (empty($errors) && (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        $errors["email"] = i18n_t('auth.admin_invalid_email', [], 'Invalid email address.');
    }

    if (empty($errors) && empty($pass_raw)) {
        $errors["pass"] = i18n_t('auth.admin_password_required', [], 'Password is required.');
    }

    if (empty($errors)) {
        ensureAdminLoginAttemptsTable($pdo);
        $attemptKey = adminLoginAttemptKey($email);
        $lockedUntil = adminLoginLockedUntil($pdo, $attemptKey);
        if ($lockedUntil !== null && $lockedUntil <= new DateTime()) {
            clearAdminLoginAttempts($pdo, $attemptKey);
            $lockedUntil = null;
        }
        if ($lockedUntil !== null && $lockedUntil > new DateTime()) {
            $errors["general"] = i18n_t('auth.too_many_admin_attempts', [], 'Too many administrator attempts. Try again in a few minutes.');
        } elseif (adminCountAdmins($pdo) === 0) {
            $errors["general"] = i18n_t('auth.cli_admin_create', [], 'No admin account found. Create one via CLI:') . ' <code>php create-admin.php admin@example.com password "Display Name"</code>';
        } else {
            $stmt = $pdo->prepare('SELECT id, password_hash, name FROM admin_users WHERE email = ? LIMIT 1');
            $stmt->execute([strtolower(trim($email))]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            $hash = is_array($admin) ? (string) ($admin['password_hash'] ?? '') : '';
            if (!is_array($admin) || $hash === '' || !password_verify($pass_raw, $hash)) {
                registerAdminFailedLogin($pdo, $attemptKey, $email);
                $errors["general"] = i18n_t('auth.invalid_admin_credentials', [], 'Invalid administrator credentials. Check your information and try again.');
            } else {
                clearAdminLoginAttempts($pdo, $attemptKey);
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int) $admin['id'];
                $_SESSION['admin_nom'] = (string) ($admin['name'] ?: 'Administrator');
                $_SESSION['admin_email'] = strtolower(trim($email));
                applyAdminSessionLifetime();

                $success = true;
                header('Location: dashboard.php');
                exit();
            }
        }
    }
}

function grp(string $field, array $errors): string {
    return isset($errors[$field]) ? 'form-group invalid' : 'form-group';
}

function adminClientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function adminLoginAttemptKey(string $email): string
{
    return hash('sha256', strtolower(trim($email)) . '|' . adminClientIp());
}

function ensureAdminLoginAttemptsTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_login_attempts (
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

function adminLoginLockedUntil(PDO $pdo, string $attemptKey): ?DateTime
{
    $stmt = $pdo->prepare("SELECT locked_until FROM admin_login_attempts WHERE attempt_key = ?");
    $stmt->execute([$attemptKey]);
    $lockedUntil = $stmt->fetchColumn();

    return $lockedUntil ? new DateTime((string) $lockedUntil) : null;
}

function registerAdminFailedLogin(PDO $pdo, string $attemptKey, string $email): void
{
    $stmt = $pdo->prepare("SELECT failed_attempts FROM admin_login_attempts WHERE attempt_key = ?");
    $stmt->execute([$attemptKey]);
    $attempts = $stmt->fetchColumn();

    if ($attempts === false) {
        $stmt = $pdo->prepare(
            "INSERT INTO admin_login_attempts (attempt_key, email, ip_address, failed_attempts, last_failed_at)
             VALUES (?, ?, ?, 1, NOW())"
        );
        $stmt->execute([$attemptKey, strtolower(trim($email)), adminClientIp()]);
        return;
    }

    $nextAttempts = (int) $attempts + 1;
    $lockedUntil = null;
    if ($nextAttempts >= ADMIN_MAX_FAILED_ATTEMPTS) {
        $lockedUntil = (new DateTime())->modify('+' . ADMIN_LOCK_MINUTES . ' minutes')->format('Y-m-d H:i:s');
    }

    $stmt = $pdo->prepare(
        "UPDATE admin_login_attempts
         SET failed_attempts = ?, last_failed_at = NOW(), locked_until = ?
         WHERE attempt_key = ?"
    );
    $stmt->execute([$nextAttempts, $lockedUntil, $attemptKey]);
}

function clearAdminLoginAttempts(PDO $pdo, string $attemptKey): void
{
    $stmt = $pdo->prepare("DELETE FROM admin_login_attempts WHERE attempt_key = ?");
    $stmt->execute([$attemptKey]);
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(i18n_current_locale(), ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars(i18n_direction(), ENT_QUOTES, 'UTF-8') ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(i18n_t('auth.admin_portal_title', [], 'Admin Portal — Maroc PC'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;800&family=Syne:wght@400;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/signup.css">
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'dark');</script>
    <?php if (defined('TURNSTILE_SITE_KEY') && TURNSTILE_SITE_KEY !== ''): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" defer></script>
    <?php endif; ?>

    <style>
        .form-group.invalid .error-msg { display: block; }
        .alert-error {
            display: flex; align-items: center; gap: 10px;
            padding: 14px 18px; border-radius: 12px; font-size: 0.9rem; font-weight: 600;
            margin-bottom: 24px; background: rgba(255, 61, 90, 0.08); border: 1px solid var(--red); color: var(--red);
        }
        .hero-overlay h2 {
            color: var(--cyan);
        }

        /* ── Glitch ACCESS DENIED overlay ──────────────────── */
        .glitch-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            background: rgba(0, 0, 0, 0.92);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.15s;
        }
        .glitch-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .glitch-overlay .scanlines {
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(
                0deg,
                transparent,
                transparent 2px,
                rgba(255, 59, 92, 0.04) 2px,
                rgba(255, 59, 92, 0.04) 4px
            );
            pointer-events: none;
        }
        .glitch-text {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(2.5rem, 8vw, 5rem);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 6px;
            color: #ff3b5c;
            position: relative;
            text-shadow: 0 0 40px rgba(255, 59, 92, 0.5);
            animation: glitchFlicker 0.15s linear infinite;
        }
        .glitch-text::before,
        .glitch-text::after {
            content: attr(data-text);
            position: absolute;
            left: 0; top: 0;
            width: 100%; height: 100%;
        }
        .glitch-text::before {
            color: #00f5d4;
            animation: glitchClipTop 0.4s steps(2) infinite;
            clip-path: polygon(0 0, 100% 0, 100% 40%, 0 40%);
        }
        .glitch-text::after {
            color: #ff6b35;
            animation: glitchClipBottom 0.4s steps(2) infinite;
            clip-path: polygon(0 60%, 100% 60%, 100% 100%, 0 100%);
        }
        .glitch-sub {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            color: #ff3b5c;
            letter-spacing: 4px;
            text-transform: uppercase;
            margin-top: 16px;
            opacity: 0.7;
        }
        .glitch-shield {
            font-size: 3rem;
            margin-bottom: 16px;
            animation: shieldPulse 0.6s ease-in-out infinite alternate;
        }

        @keyframes glitchFlicker {
            0% { opacity: 1; transform: translate(0); }
            20% { opacity: 0.8; transform: translate(-2px, 1px); }
            40% { opacity: 1; transform: translate(1px, -1px); }
            60% { opacity: 0.9; transform: translate(-1px, 2px); }
            80% { opacity: 1; transform: translate(2px, -1px); }
            100% { opacity: 0.85; transform: translate(0); }
        }
        @keyframes glitchClipTop {
            0% { transform: translate(-3px, -1px); }
            50% { transform: translate(3px, 1px); }
            100% { transform: translate(-2px, 0); }
        }
        @keyframes glitchClipBottom {
            0% { transform: translate(3px, 1px); }
            50% { transform: translate(-3px, -1px); }
            100% { transform: translate(2px, 0); }
        }
        @keyframes shieldPulse {
            from { transform: scale(1); filter: drop-shadow(0 0 15px rgba(255,59,92,0.4)); }
            to { transform: scale(1.15); filter: drop-shadow(0 0 30px rgba(255,59,92,0.7)); }
        }

        /* Screen shake on the container */
        @keyframes screenShake {
            0%, 100% { transform: translate(0); }
            10% { transform: translate(-6px, 3px); }
            20% { transform: translate(5px, -4px); }
            30% { transform: translate(-3px, 2px); }
            40% { transform: translate(4px, -2px); }
            50% { transform: translate(-2px, 4px); }
            60% { transform: translate(3px, -3px); }
            70% { transform: translate(-4px, 1px); }
            80% { transform: translate(2px, -2px); }
            90% { transform: translate(-1px, 3px); }
        }
        .shake { animation: screenShake 0.5s ease-in-out; }
    </style>
	<link rel="stylesheet" href="assets/css/light-mode-industrial.css">
</head>
<body class="auth-page auth-balanced auth-login">

    <!-- ACCESS DENIED glitch overlay -->
    <div class="glitch-overlay" id="glitchOverlay">
        <div class="scanlines"></div>
        <div class="glitch-shield">🛡️</div>
        <div class="glitch-text" data-text="<?= htmlspecialchars(i18n_t('auth.access_denied', [], 'ACCESS DENIED'), ENT_QUOTES, 'UTF-8') ?>"><?php i18n_e('auth.access_denied', [], 'ACCESS DENIED'); ?></div>
        <p class="glitch-sub"><?php i18n_e('auth.unauthorized_credentials', [], '// unauthorized credentials'); ?></p>
    </div>

    <a href="<?= htmlspecialchars(i18n_url('index.php'), ENT_QUOTES, 'UTF-8') ?>" class="back-link">← <?php i18n_e('auth.back_to_store', [], 'Back to Store'); ?></a>

    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme" style="position: absolute; top: 1.5rem; right: 2rem;">
        <i class="fas fa-sun icon-sun"></i>
        <i class="fas fa-moon icon-moon"></i>
    </button>
    <?= i18n_language_switcher('nav-translate', 'position: absolute; top: 1.5rem; right: 6rem;') ?>


    <div class="container login-container" id="loginContainer">
        <!-- ── Côté image ─────────────────────────────────────── -->
        <div class="hero-side">
            <img src="admin_bg.webp" alt="Admin Dashboard Background">
            <div class="hero-overlay" style="background: rgba(0,0,0,0.8); z-index: 10;">
                <h2 style="color: var(--cyan);"><?php i18n_e('auth.admin_portal', [], 'Admin Portal'); ?></h2>
                <p><?php i18n_e('auth.admin_restricted_hint', [], 'Restricted access. System control, analytics, and catalog management.'); ?></p>
            </div>
        </div>

        <!-- ── Côté formulaire ────────────────────────────────── -->
        <div class="inscription">
            <?php if ($adminSetupNeeded): ?>
                <div class="alert-error" role="alert" style="margin-bottom:16px;">
                    <span aria-hidden="true">⚠</span>
                    <span><?php i18n_e('auth.cli_admin_create', [], 'No admin account found. Create one via CLI:'); ?> <code style="font-size:0.85em;">php create-admin.php email@example.com password "Name"</code></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors["general"])): ?>
                <div class="alert-error" role="alert" aria-live="polite" <?= $adminSessionExpired ? 'data-admin-session-expired="1"' : '' ?>>
                    <span aria-hidden="true">❌</span>
                    <span><?= htmlspecialchars($errors["general"]) ?></span>
                </div>
            <?php endif; ?>

            <form name="adminlogin" method="post" action="adminlogin.php" novalidate <?= $success ? 'style="display:none"' : '' ?>>
                <?= csrfField() ?>
                <h3 id="myH3"><?php i18n_e('auth.administrator_mode', [], 'Administrator Mode'); ?></h3>
                <p class="subtitle"><?php i18n_e('auth.admin_login_subtitle', [], 'Highly secure connection required.'); ?></p>

                <!-- ── Email ──────────────────────────────────── -->
                <div class="<?= grp('email', $errors) ?>">
                    <label for="login-email"><?php i18n_e('auth.admin_email_label', [], 'Admin Email'); ?></label>
                    <input type="email" name="email" id="login-email" class="hh"
                           placeholder="admin@marocpc.com" value="<?= htmlspecialchars($email) ?>" required>
                    <span class="error-msg" id="err-login-email"><?= $errors["email"] ?? i18n_t('auth.please_enter_valid_email', [], 'Please enter a valid email') ?></span>
                </div>

                <!-- ── Mot de passe ───────────────────────────── -->
                <div class="<?= grp('pass', $errors) ?>">
                    <label for="login-pass"><?php i18n_e('auth.master_password_label', [], 'Master Password'); ?></label>
                    <div class="password-wrap">
                        <input type="password" name="pass" id="login-pass" class="hh" placeholder="••••••••" required>
                        <button type="button" class="toggle-pass" id="loginTogglePass" aria-label="<?php i18n_e('auth.show_password', [], 'Show password'); ?>">👁</button>
                    </div>
                    <span class="error-msg" id="err-login-pass"><?= $errors["pass"] ?? i18n_t('auth.admin_password_required_hint', [], 'Password is required') ?></span>
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
                    <button type="submit" class="Bou" id="loginBtn" style="background: var(--cyan); color: #000;"><?php i18n_e('auth.system_access_btn', [], 'System Access'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Required Scripts -->
    <script src="assets/js/login.js" defer></script>
    <?= i18n_language_switcher_assets() ?>
    <script src="assets/js/theme.js"></script>

    <script>
    (function() {
        const isGetExpired = <?= $adminSessionExpired ? 'true' : 'false' ?>;
        let hadAdminSession = false;
        try {
            hadAdminSession = localStorage.getItem('has_active_admin_session') === '1';
            if (hadAdminSession || isGetExpired) {
                localStorage.removeItem('has_active_admin_session');
            }
        } catch (_) {}

        if (!isGetExpired && hadAdminSession && !document.querySelector('[data-admin-session-expired]')) {
            const form = document.querySelector('form[name="adminlogin"]');
            if (!form || !form.parentElement) return;
            const alert = document.createElement('div');
            alert.className = 'alert-error';
            alert.setAttribute('role', 'alert');
            alert.setAttribute('aria-live', 'polite');
            alert.setAttribute('data-admin-session-expired', '1');
            alert.innerHTML = '<span aria-hidden="true">!</span><span><?= htmlspecialchars(i18n_t('auth.session_expired', [], 'Your session has expired. Please sign in again.'), ENT_QUOTES, 'UTF-8') ?></span>';
            form.parentElement.insertBefore(alert, form);
        }
    })();
    </script>

    <?php if (!empty($errors["general"]) && !$adminSessionExpired): ?>
    <script>
    (function() {
        const overlay = document.getElementById('glitchOverlay');
        const container = document.getElementById('loginContainer');

        // Show glitch overlay immediately
        overlay.classList.add('active');

        // Add screen shake to form
        container.classList.add('shake');

        // Dismiss after 2 seconds
        setTimeout(function() {
            overlay.classList.remove('active');
        }, 2000);

        // Remove shake class after animation ends
        container.addEventListener('animationend', function() {
            container.classList.remove('shake');
        });
    })();
    </script>
    <?php endif; ?>
    
    <?php if (defined('TURNSTILE_SITE_KEY') && TURNSTILE_SITE_KEY !== ''): ?>
    <script>
        // Turnstile Callbacks with comprehensive error handling for Admin Login
        let turnstileToken = null;
        
        function onTurnstileSuccess(token) {
            turnstileToken = token;
            console.log('✅ Turnstile: Admin token received successfully');
            
            const errorDiv = document.getElementById('turnstileError');
            if (errorDiv) errorDiv.style.display = 'none';
            
            const submitBtn = document.getElementById('loginBtn');
            if (submitBtn) submitBtn.disabled = false;
        }
        
        function onTurnstileError(error) {
            console.error('❌ Turnstile: Admin verification error', error);
            turnstileToken = null;
            
            const errorDiv = document.getElementById('turnstileError');
            if (errorDiv) {
                errorDiv.textContent = '<?= htmlspecialchars(i18n_t('auth.admin_captcha_failed_msg', [], '🛡️ Security verification failed. Refresh and try again.'), ENT_QUOTES, 'UTF-8') ?>';
                errorDiv.style.display = 'block';
            }
            
            const submitBtn = document.getElementById('loginBtn');
            if (submitBtn) submitBtn.disabled = true;
            
            // Enhanced admin logging
            console.log('🔒 Admin Security Check Failed');
            console.log('1. Verify site key matches Cloudflare dashboard');
            console.log('2. Check domain whitelist in Cloudflare settings');
            console.log('3. Inspect Network tab for blocked requests');
            console.log('4. Test with different browser/incognito mode');
        }
        
        function onTurnstileExpired() {
            console.warn('⏰ Turnstile: Admin token expired');
            turnstileToken = null;
            
            const errorDiv = document.getElementById('turnstileError');
            if (errorDiv) {                    errorDiv.textContent = '<?= htmlspecialchars(i18n_t('auth.admin_captcha_expired', [], '⏰ Security check expired. Widget will refresh automatically.'), ENT_QUOTES, 'UTF-8') ?>';
                errorDiv.style.display = 'block';
                setTimeout(() => errorDiv.style.display = 'none', 3000);
            }
        }
        
        // Prevent admin form submission without token
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[name="adminlogin"]');
            const submitBtn = document.getElementById('loginBtn');
            
            if (form && submitBtn) {
                form.addEventListener('submit', function(e) {
                    const tokenInput = document.querySelector('input[name="cf-turnstile-response"]');
                    const token = tokenInput ? tokenInput.value : '';
                    
                    if (!token) {
                        e.preventDefault();
                        console.error('❌ Admin login blocked: Missing Turnstile token');
                        
                        const errorDiv = document.getElementById('turnstileError');
                        if (errorDiv) {
                            errorDiv.textContent = '<?= htmlspecialchars(i18n_t('auth.admin_captcha_missing', [], '🛡️ Complete security verification to access admin portal.'), ENT_QUOTES, 'UTF-8') ?>';
                            errorDiv.style.display = 'block';
                        }
                        
                        submitBtn.style.animation = 'shake 0.5s';
                        setTimeout(() => submitBtn.style.animation = '', 500);
                        
                        return false;
                    }
                    
                    console.log('✅ Admin form submission: Turnstile token verified');
                });
            }
        });
        
        console.log('🔒 Admin Turnstile: Initialized');
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
