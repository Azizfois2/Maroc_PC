<?php
require_once 'config.php';
require_once 'mailer.php';
require_once 'src/Services/two-factor-helpers.php';
require_once __DIR__ . '/includes/i18n.php';
i18n_start_page_translation();

$signupLocale = i18n_current_locale();
$signupPhraseMap = i18n_page_phrase_map($signupLocale);
$signupAttr = static fn(string $text): string => htmlspecialchars($signupPhraseMap[$text] ?? $text, ENT_QUOTES, 'UTF-8');

$requestMethod = $_SERVER["REQUEST_METHOD"] ?? "GET";

$errors = [];
$success = false;
$fullname = $email = $dob = $adresse = $telephone = "";
$verify_method = "email";
$terms_agree = false;
$newsletter_opt_in = false;

if ($requestMethod !== "POST") {
    $oauthError = $_GET['error'] ?? '';
    $providerLabels = [
        'facebook' => 'Facebook',
        'steam' => 'Steam',
        'apple' => 'Apple',
    ];

    if ($oauthError === 'google_auth_failed') {
        $errors["general"] = i18n_t('auth.signup_google_failed', [], 'Google signup failed. Please try again or use the form.');
    } elseif ($oauthError === 'provider_unavailable') {
        $provider = strtolower((string) ($_GET['provider'] ?? ''));
        $providerName = $providerLabels[$provider] ?? i18n_t('auth.this_provider', [], 'This provider');
        $errors["general"] = $providerName . ' ' . i18n_t('auth.not_configured_signup', [], 'is not configured for signup yet.');
    }
}

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

    // ── Sanitize ──────────────────────────────────────────
    $fullname = trim($_POST["fullname"] ?? "");
    $fullname = strip_tags($fullname);
    $fullname = preg_replace('/[^\p{L}\p{N}\p{Z}\p{Pd}\p{Pc}]/u', '', $fullname);
    $fullname = trim($fullname);

    $email = trim($_POST["email"] ?? "");
    $pass_raw = $_POST["pass"] ?? "";
    $dob = trim($_POST["dob"] ?? "");
    $adresse = trim($_POST["adresse"] ?? "");
    $telephone = trim($_POST["telephone"] ?? "");
    $verify_method = $_POST["verify_method"] ?? "email";
    $terms_agree = isset($_POST["terms_agree"]);
    $newsletter_opt_in = isset($_POST["newsletter_opt_in"]);

    // ── Validate ──────────────────────────────────────────
    if (empty($errors) && mb_strlen($fullname) < 2) {
        $errors["fullname"] = i18n_t('auth.signup_name_required', [], 'Please enter your full name.');
    }

    if (empty($errors) && mb_strlen($adresse) < 5) {
        $errors["adresse"] = i18n_t('auth.signup_address_required', [], 'Address must be at least 5 characters.');
    }

    if (empty($errors) && (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        $errors["email"] = i18n_t('auth.invalid_email', [], 'Invalid email address.');
    }

    if (
        empty($errors) && (strlen($pass_raw) < 8 ||
            !preg_match('/[0-9]/', $pass_raw) ||
            !preg_match('/[^a-zA-Z0-9]/', $pass_raw))
    ) {
        $errors["pass"] = i18n_t('auth.signup_password_requirements', [], 'Min 8 chars, 1 number, and 1 symbol.');
    }

    if (empty($errors) && (empty($dob) || $dob >= date('Y-m-d'))) {
        $errors["dob"] = i18n_t('auth.signup_dob_required', [], 'Birth date is required and must be in the past.');
    }

    if (empty($errors) && in_array($verify_method, ['whatsapp', 'sms'])) {
        $telephone = twoFactorNormalizePhone($telephone);
        if ($telephone === '') {
            $errors["telephone"] = i18n_t('auth.signup_invalid_phone', [], 'Invalid phone number.');
        }
    }

    if (empty($errors) && !$terms_agree) {
        $errors["terms_agree"] = i18n_t('auth.signup_terms_required', [], 'You need to accept the Terms of Service to create an account.');
    }

    // ── Check duplicate email ─────────────────────────────
    if (empty($errors)) {
        $stmt = db()->prepare("SELECT id_client FROM Client WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors["email"] = i18n_t('auth.signup_email_taken', [], 'This email is already in use.');
        }
    }

    // ── Check duplicate phone ─────────────────────────────
    if (empty($errors) && !empty($telephone)) {
        $stmt = db()->prepare("SELECT id_client FROM Client WHERE telephone = ?");
        $stmt->execute([$telephone]);
        if ($stmt->fetch()) {
            $errors["telephone"] = i18n_t('auth.signup_phone_taken', [], 'This phone number is already in use.');
        }
    }

    // ── Insert + send verification email ──────────────────
    if (empty($errors)) {
        try {
            $pdo = db();

            // Insert user with email_verified = 0
            $stmt = $pdo->prepare(
                "INSERT INTO Client (nom, email, mot_de_passe, date_naissance, moyen_paiement, adresse, telephone, email_verified)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0)"
            );
            $stmt->execute([
                $fullname,
                $email,
                password_hash($pass_raw, PASSWORD_DEFAULT),
                $dob,
                'not_set',
                $adresse,
                $telephone
            ]);

            if ($newsletter_opt_in) {
                ensureNewsletterSubscribersTable($pdo);
                $stmt = $pdo->prepare("INSERT IGNORE INTO newsletter_subscribers (email) VALUES (?)");
                $stmt->execute([$email]);
            }

            if ($verify_method === 'whatsapp' || $verify_method === 'sms') {
                $code = (string) random_int(100000, 999999);
                $tokenHash = password_hash($code, PASSWORD_DEFAULT);
                $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                
                $stmt = $pdo->prepare("INSERT INTO email_verifications (email, token_hash, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $tokenHash, $expiresAt]);
                
                if ($verify_method === 'whatsapp') {
                    sendTwoFactorCodeWhatsApp($telephone, $fullname, $code);
                } else {
                    sendTwoFactorCodeSMS($telephone, $fullname, $code);
                }
                
                $_SESSION['verify_account_email'] = $email;
                $_SESSION['verify_account_method'] = $verify_method;
                header("Location: verify-account-code.php");
                exit();
            } else {
                // Generate verification token for Email
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

                $stmt = $pdo->prepare("INSERT INTO email_verifications (email, token_hash, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $tokenHash, $expiresAt]);

                // Send verification email
                sendVerificationEmail($email, $fullname, $token);

                $success = true;
                $fullname = $email = $dob = $adresse = $telephone = "";
                $terms_agree = false;
                $newsletter_opt_in = false;
            }
        } catch (PDOException $e) {
            $errors["general"] = DEV_MODE
                ? "DB Error: " . $e->getMessage()
                : i18n_t('auth.signup_registration_error', [], 'Error during registration. Please try again.');
        }
    }
}

function grp(string $field, array $errors): string
{
    return isset($errors[$field]) ? 'form-group invalid' : 'form-group';
}

function ensureNewsletterSubscribersTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(i18n_current_locale(), ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars(i18n_direction(), ENT_QUOTES, 'UTF-8') ?>" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $signupAttr('Create an account — Maroc PC') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;800&family=Syne:wght@400;600;700&family=JetBrains+Mono:wght@400;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/signup.css?v=<?= urlencode((string) filemtime(__DIR__ . '/assets/css/signup.css')) ?>">
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'dark');</script>
    <?php if (defined('TURNSTILE_SITE_KEY') && TURNSTILE_SITE_KEY !== ''): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" defer></script>
    <?php endif; ?>

    <style>
        /* ── Styles manquants dans signup.css : alertes + surcharge invalid ── */

        /* Force l'affichage du .error-msg quand PHP ajoute .invalid */
        .form-group.invalid .error-msg {
            display: block;
        }

        /* Alertes globales */
        .alert-success,
        .alert-error {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 24px;
        }

        .alert-success {
            background: rgba(0, 230, 118, 0.08);
            border: 1px solid var(--green);
            color: var(--green);
        }

        .alert-success a {
            color: var(--green);
            font-weight: 800;
            text-decoration: underline;
            margin-left: auto;
        }

        .alert-error {
            background: rgba(255, 61, 90, 0.08);
            border: 1px solid var(--red);
            color: var(--red);
        }
    </style>
    <link rel="stylesheet" href="assets/css/light-mode-industrial.css">
</head>

<body class="auth-page auth-balanced auth-signup">

    <!-- ── Confirmation Modal ──────────────────────────────────────── -->
    <div class="confirm-overlay" id="confirmOverlay"></div>
    <div class="confirm-modal" id="confirmModal">
        <div class="confirm-header">
            <h4><?= $signupAttr('Confirm your details') ?></h4>
            <button type="button" class="confirm-close" id="confirmClose">✕</button>
        </div>
        <div class="confirm-body">
            <p class="confirm-subtitle"><?= $signupAttr('Please verify your information before creating your account.') ?></p>
            <ul class="confirm-list">
                <li>
                    <span class="confirm-label"><?= $signupAttr('Full Name') ?></span>
                    <span class="confirm-value" id="cf-name">—</span>
                </li>
                <li>
                    <span class="confirm-label"><?= $signupAttr('Email') ?></span>
                    <span class="confirm-value" id="cf-email">—</span>
                </li>
                <li>
                    <span class="confirm-label"><?= $signupAttr('Password') ?></span>
                    <span class="confirm-value" id="cf-pass">—</span>
                </li>
                <li>
                    <span class="confirm-label"><?= $signupAttr('Date of Birth') ?></span>
                    <span class="confirm-value" id="cf-dob">—</span>
                </li>
                <li>
                    <span class="confirm-label"><?= $signupAttr('Phone') ?></span>
                    <span class="confirm-value" id="cf-telephone">—</span>
                </li>
                <li>
                    <span class="confirm-label"><?= $signupAttr('Address') ?></span>
                    <span class="confirm-value" id="cf-adresse">—</span>
                </li>
            </ul>
        </div>
        <div class="confirm-actions">
            <button type="button" class="btn-secondary" id="confirmEdit">← <?= $signupAttr('Edit') ?></button>
            <button type="button" class="Bou" id="confirmSubmit"><?= $signupAttr('Create Account') ?> →</button>
        </div>
    </div>

    <a href="<?= htmlspecialchars(i18n_url('index.php'), ENT_QUOTES, 'UTF-8') ?>" class="back-link">&larr; <?php i18n_e('auth.back_to_store', [], 'Back to Store'); ?></a>

    <button class="theme-toggle" id="themeToggle" aria-label="<?= $signupAttr('Toggle theme') ?>"
        style="position: absolute; top: 1.5rem; right: 2rem;">
        <i class="fas fa-sun icon-sun"></i>
        <i class="fas fa-moon icon-moon"></i>
    </button>
    <?= i18n_language_switcher('nav-translate', 'position: absolute; top: 1.5rem; right: 6rem;') ?>


    <div class="container">

        <!-- ── Côté image ─────────────────────────────────────── -->
        <div class="hero-side">
            <img src="signup.webp" alt="<?= $signupAttr('Gaming setup workspace') ?>">
            <div class="hero-overlay">
                <span class="hero-kicker"><?= $signupAttr('New account') ?></span>
                <h2><?= $signupAttr('Build smarter with Maroc PC') ?></h2>
                <p><?= $signupAttr('Save parts, verify securely, and keep every order connected to your account.') ?></p>
                <div class="hero-trust-list" aria-label="<?= $signupAttr('Account benefits') ?>">
                    <span><i class="fas fa-tags"></i> <?= $signupAttr('Member offers') ?></span>
                    <span><i class="fas fa-truck-fast"></i> <?= $signupAttr('Tracking') ?></span>
                    <span><i class="fas fa-lock"></i> <?= $signupAttr('Secure access') ?></span>
                </div>
            </div>
        </div>

        <!-- ── Côté formulaire ────────────────────────────────── -->
        <div class="inscription">

            <?php if ($success): ?>
                <div class="alert-success" style="flex-direction:column;align-items:flex-start;">
                    <span>✅ <?= $signupAttr('Account successfully created!') ?></span>
                    <span style="font-size:0.85rem;font-weight:400;margin-top:6px;color:var(--muted,#b0b8c8);">
                        📧 <?= $signupAttr('A verification email has been sent. Check your inbox (and spam) to activate your account.') ?>
                    </span>
                    <a href="login.php" style="margin-top:12px;"><?= $signupAttr('Sign In') ?> →</a>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors["general"])): ?>
                <div class="alert-error">
                    ❌ <?= htmlspecialchars($errors["general"]) ?>
                </div>
            <?php endif; ?>

            <form name="signup" method="post" action="signup.php" novalidate>
                <?= csrfField() ?>
                <span class="auth-kicker"><?= $signupAttr('Maroc PC account') ?></span>
                <h3 id="myH3"><?= $signupAttr('Create your account') ?></h3>
                <p class="subtitle"><?= $signupAttr('Set up faster checkout, order tracking, and saved build lists.') ?></p>

                <!-- ── Nom complet ────────────────────────────── -->
                <div class="<?= grp('fullname', $errors) ?>">
                    <label for="fullname"><?= $signupAttr('Full Name') ?></label>
                    <input type="text" name="fullname" id="fullname" class="hh" placeholder="<?= $signupAttr('John Doe') ?>"
                        value="<?= htmlspecialchars($fullname) ?>" required>
                    <span class="error-msg" id="err-name">
                        <?= isset($errors["fullname"])
                            ? htmlspecialchars($errors["fullname"])
                            : $signupAttr('Please enter your full name') ?>
                    </span>
                </div>

                <!-- ── Email ──────────────────────────────────── -->
                <div class="<?= grp('email', $errors) ?>">
                    <label for="email"><?= $signupAttr('Email Address') ?></label>
                    <input type="email" name="email" id="email" class="hh" placeholder="<?= $signupAttr('john@example.com') ?>"
                        value="<?= htmlspecialchars($email) ?>" required>
                    <span class="error-msg" id="err-email">
                        <?= isset($errors["email"])
                            ? htmlspecialchars($errors["email"])
                            : $signupAttr('Please enter a valid email') ?>
                    </span>
                </div>

                <!-- ── Mot de passe ───────────────────────────── -->
                <div class="<?= grp('pass', $errors) ?>">
                    <label for="pass"><?= $signupAttr('Password') ?></label>
                    <div class="password-wrap">
                        <input type="password" name="pass" id="pass" class="hh" placeholder="<?= $signupAttr('Password') ?>" required
                            minlength="8">
                        <button type="button" class="toggle-pass" id="togglePass"
                            aria-label="<?= $signupAttr('Show password') ?>"><i class="fas fa-eye"></i></button>
                    </div>
                    
                    <!-- Password Requirements Checklist -->
                    <div class="password-requirements" id="passwordRequirements">
                        <div class="requirement" id="req-length">
                            <span class="req-icon">○</span>
                            <span class="req-text"><?= $signupAttr('At least 8 characters') ?></span>
                        </div>
                        <div class="requirement" id="req-upper">
                            <span class="req-icon">○</span>
                            <span class="req-text"><?= $signupAttr('Uppercase letter improves strength') ?></span>
                        </div>
                        <div class="requirement" id="req-number">
                            <span class="req-icon">○</span>
                            <span class="req-text"><?= $signupAttr('Contains a number') ?></span>
                        </div>
                        <div class="requirement" id="req-symbol">
                            <span class="req-icon">○</span>
                            <span class="req-text"><?= $signupAttr('Contains a symbol (!@#$%^&*)') ?></span>
                        </div>
                    </div>
                    
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <span class="strength-text" id="strengthText"><?= $signupAttr('Enter password') ?></span>
                    </div>
                    
                    <span class="error-msg" id="err-pass">
                        <?= isset($errors["pass"])
                            ? htmlspecialchars($errors["pass"])
                            : $signupAttr('Password is too weak') ?>
                    </span>
                </div>

                <!-- ── Date de naissance ──────────────────────── -->
                <div class="<?= grp('dob', $errors) ?>">
                    <label for="dob"><?= $signupAttr('Date of Birth') ?></label>
                    <input type="date" name="dob" id="dob" class="hh" value="<?= htmlspecialchars($dob) ?>" required>
                    <span class="error-msg" id="err-dob">
                        <?= isset($errors["dob"])
                            ? htmlspecialchars($errors["dob"])
                            : $signupAttr('Required') ?>
                    </span>
                </div>

                <!-- ── Adresse ──────────────────────────────────────── -->
                <div class="<?= grp('adresse', $errors) ?>">
                    <label for="adresse"><?= $signupAttr('Shipping Address') ?></label>
                    <input type="text" name="adresse" id="adresse" class="hh"
                        placeholder="<?= $signupAttr('123 Example Street, City') ?>" value="<?= htmlspecialchars($adresse) ?>" required>
                    <span class="error-msg" id="err-adresse">
                        <?= isset($errors["adresse"])
                            ? htmlspecialchars($errors["adresse"])
                            : $signupAttr('Address is required') ?>
                    </span>
                </div>

                <!-- ── Téléphone ──────────────────────────────────────── -->
                <div class="<?= grp('telephone', $errors) ?> phone-group">
                    <label for="telephone"><?= $signupAttr('Phone Number') ?></label>
                    <input type="tel" name="telephone" id="telephone" class="hh"
                        placeholder="+212600000000" value="<?= htmlspecialchars($telephone) ?>">
                    <span class="hint"><?= $signupAttr('Required only for WhatsApp or SMS verification.') ?></span>
                    <span class="error-msg" id="err-telephone">
                        <?= isset($errors["telephone"])
                            ? htmlspecialchars($errors["telephone"])
                            : $signupAttr('Phone number is required for this verification method') ?>
                    </span>
                </div>

                <!-- ── Méthode de vérification ──────────────────────────────────────── -->
                <div class="form-group">
                    <label><?= $signupAttr('How would you like to verify your account?') ?></label>
                    <div class="radio-group auth-verification-options">
                        <label class="radio-card">
                            <input type="radio" name="verify_method" value="email" <?= $verify_method === 'email' ? 'checked' : '' ?>>
                            <span class="radio-check"></span>
                            <i class="fas fa-envelope"></i>
                            <span class="radio-label"><?= $signupAttr('Email') ?></span>
                            <small><?= $signupAttr('Recommended') ?></small>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="verify_method" value="whatsapp" <?= $verify_method === 'whatsapp' ? 'checked' : '' ?>>
                            <span class="radio-check"></span>
                            <i class="fab fa-whatsapp"></i>
                            <span class="radio-label"><?= $signupAttr('WhatsApp') ?></span>
                            <small><?= $signupAttr('Phone code') ?></small>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="verify_method" value="sms" <?= $verify_method === 'sms' ? 'checked' : '' ?>>
                            <span class="radio-check"></span>
                            <i class="fas fa-comment-sms"></i>
                            <span class="radio-label"><?= $signupAttr('SMS') ?></span>
                            <small><?= $signupAttr('Text code') ?></small>
                        </label>
                    </div>
                </div>

                <div class="auth-consent-list">
                    <div class="<?= grp('terms_agree', $errors) ?> consent-group">
                        <label class="remember-label consent-label">
                            <input type="checkbox" name="terms_agree" id="termsAgree" required
                                <?= $terms_agree ? "checked" : "" ?>>
                            <span class="check-box"></span>
                            <span class="remember-copy">
                                <span class="remember-text"><?= $signupAttr('I agree to the Terms of Service') ?></span>
                                <small>
                                    <?= $signupAttr('Please review the') ?>
                                    <a href="terms-of-service.php" target="_blank" rel="noopener"><?= $signupAttr('Terms of Service') ?></a>
                                    <?= $signupAttr('and') ?>
                                    <a href="privacy-policy.php" target="_blank" rel="noopener"><?= $signupAttr('Privacy Policy') ?></a>.
                                </small>
                            </span>
                        </label>
                        <span class="error-msg" id="err-terms-agree">
                            <?= isset($errors["terms_agree"])
                                ? htmlspecialchars($errors["terms_agree"])
                                : $signupAttr('You need to accept the Terms of Service') ?>
                        </span>
                    </div>

                    <div class="form-group consent-group optional-consent">
                        <label class="remember-label consent-label">
                            <input type="checkbox" name="newsletter_opt_in" id="newsletterOptIn"
                                <?= $newsletter_opt_in ? "checked" : "" ?>>
                            <span class="check-box"></span>
                            <span class="remember-copy">
                                <span class="remember-text"><?= $signupAttr('Send me Maroc PC deals and build tips') ?></span>
                                <small><?= $signupAttr('Occasional offers, restock alerts, and setup inspiration. Unsubscribe anytime.') ?></small>
                            </span>
                        </label>
                    </div>
                </div>

                <?php if (defined('TURNSTILE_SITE_KEY') && TURNSTILE_SITE_KEY !== ''): ?>
                    <div style="display: flex; justify-content: center; margin-bottom: 20px;">
                        <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars(TURNSTILE_SITE_KEY) ?>"></div>
                    </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="Bou"><?= $signupAttr('Create Account') ?></button>
                </div>

                <div class="social-login">
                    <p><?= $signupAttr('Or continue with') ?></p>
                    <div class="social-provider-grid">
                        <a href="<?= htmlspecialchars(i18n_url('google-callback.php?next=index.php'), ENT_QUOTES, 'UTF-8') ?>" class="social-provider provider-google">
                            <i class="fab fa-google"></i>
                            <span><?= $signupAttr('Google') ?></span>
                        </a>
                        <a href="facebook-login.php" class="social-provider provider-facebook">
                            <i class="fab fa-facebook-f"></i>
                            <span><?= $signupAttr('Facebook') ?></span>
                        </a>
                        <a href="discord-login.php" class="social-provider provider-discord">
                            <i class="fab fa-discord"></i>
                            <span><?= $signupAttr('Discord') ?></span>
                        </a>
                        <a href="steam-login.php" class="social-provider provider-steam">
                            <i class="fab fa-steam"></i>
                            <span><?= $signupAttr('Steam') ?></span>
                        </a>

                    </div>
                </div>

                <p class="login-link" style="margin-top: 1.5rem;">
                    <?= $signupAttr('Already have an account?') ?> <a href="login.php" data-auth-transition="login"><?= $signupAttr('Sign In') ?></a>
                </p>

            </form>
        </div>
    </div>

    <?= i18n_language_switcher_assets() ?>
    <script src="assets/js/form.js?v=<?= urlencode((string) filemtime(__DIR__ . '/assets/js/form.js')) ?>" defer></script>
    <script src="assets/js/theme.js" defer></script>
    <script src="assets/js/auth-premium.js?v=<?= urlencode((string) filemtime(__DIR__ . '/assets/js/auth-premium.js')) ?>" defer></script>
</body>

</html>
