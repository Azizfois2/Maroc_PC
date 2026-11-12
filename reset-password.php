<?php
require_once 'config.php';
require_once 'password-reset-helpers.php';
require_once __DIR__ . '/includes/i18n.php';

$token = (string) ($_GET['token'] ?? '');
$validation = validatePasswordResetToken(db(), $token);
$valid = (bool) ($validation['valid'] ?? false);
$email = $valid ? (string) $validation['email'] : '';
$error = $valid ? '' : (string) ($validation['error'] ?? 'This reset link is invalid.');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(i18n_current_locale(), ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars(i18n_direction(), ENT_QUOTES, 'UTF-8') ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= i18n_t('auth.reset_title', [], 'Set New Password - Maroc PC') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;800&family=Syne:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/signup.css">
    <link rel="stylesheet" href="assets/css/light-mode-industrial.css">
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'dark');</script>
</head>
<body>
    <a href="login.php" class="back-link"><?= i18n_t('auth.back_to_login', [], 'Back to Sign In') ?></a>

    <button class="theme-toggle" id="themeToggle" aria-label="<?= i18n_t('auth.toggle_theme', [], 'Toggle theme') ?>" style="position:absolute;top:1.5rem;right:2rem;">
        <i class="fas fa-sun icon-sun"></i>
        <i class="fas fa-moon icon-moon"></i>
    </button>
    <?= i18n_language_switcher('nav-translate', 'position:absolute;top:1.5rem;right:6rem;') ?>

    <div class="container login-container">
        <div class="hero-side">
            <img src="signup.webp" alt="<?= i18n_t('auth.reset_hero_alt', [], 'Gaming setup') ?>">
            <div class="hero-overlay">
                <h2><?= i18n_t('auth.reset_secure_title', [], 'Secure Reset') ?></h2>
                <p><?= i18n_t('auth.reset_secure_desc', [], 'Choose a strong password you have not used before.') ?></p>
            </div>
        </div>

        <div class="inscription">
            <?php if (!$valid): ?>
                <h3 id="myH3"><?= i18n_t('auth.reset_link_problem', [], 'Reset Link Problem') ?></h3>
                <p class="subtitle" style="color:var(--red);"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="form-actions" style="margin-top:24px;">
                    <a href="forgot-password.php" class="Bou" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;"><?= i18n_t('auth.reset_request_new', [], 'Request New Link') ?></a>
                </div>
            <?php else: ?>
                <form id="resetForm" method="post" action="update-password.php">
                    <h3 id="myH3"><?= i18n_t('auth.reset_new_password', [], 'New Password') ?></h3>
                    <p class="subtitle"><?= i18n_t('auth.reset_resetting_for', [], 'Resetting password for') ?> <strong><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></strong></p>
                    <div class="toast" id="resetNotice" style="position:static;transform:none;margin-bottom:20px;opacity:0;pointer-events:none;"></div>

                    <div class="form-group">
                        <label for="newpass"><?= i18n_t('auth.new_password_label', [], 'New Password') ?></label>
                        <div class="password-wrap">
                            <input type="password" name="newpass" id="newpass" class="hh" placeholder="<?= i18n_t('auth.password_placeholder', [], 'Password') ?>" required minlength="8" autocomplete="new-password">
                            <button type="button" class="toggle-pass" id="togglePass" aria-label="<?= i18n_t('auth.show_password', [], 'Show password') ?>"><?= i18n_t('auth.show_password_btn', [], 'Show') ?></button>
                        </div>
                        <span class="hint"><?= i18n_t('auth.reset_password_hint', [], 'At least 8 characters with a number and symbol.') ?></span>
                        <span class="error-msg" id="err-pass"><?= i18n_t('auth.reset_password_too_weak', [], 'Password too weak.') ?></span>
                    </div>

                    <div class="form-group">
                        <label for="confirmpass"><?= i18n_t('auth.confirm_password_label', [], 'Confirm Password') ?></label>
                        <input type="password" name="confirmpass" id="confirmpass" class="hh" placeholder="<?= i18n_t('auth.repeat_password_placeholder', [], 'Repeat password') ?>" required autocomplete="new-password">
                        <span class="error-msg" id="err-match"><?= i18n_t('auth.reset_passwords_mismatch', [], 'Passwords do not match.') ?></span>
                    </div>

                    <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                    <?= csrfField(); ?>

                    <div class="form-actions">
                        <button type="submit" class="Bou" id="submitBtn"><?= i18n_t('auth.reset_update_btn', [], 'Update Password') ?></button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($valid): ?>
        <script>
            const form = document.getElementById('resetForm');
            const notice = document.getElementById('resetNotice');
            const btn = document.getElementById('submitBtn');

            function showNotice(message, isError = false) {
                notice.className = 'toast show' + (isError ? ' error' : '');
                notice.style.opacity = '1';
                notice.style.pointerEvents = 'auto';
                notice.innerHTML = `<i class="fas ${isError ? 'fa-triangle-exclamation' : 'fa-check-circle'}"></i><span>${message}</span>`;
            }

            document.getElementById('togglePass').addEventListener('click', function () {
                const password = document.getElementById('newpass');
                password.type = password.type === 'password' ? 'text' : 'password';
                const showText = <?= json_encode(i18n_t('auth.show_password_btn', [], 'Show'), JSON_UNESCAPED_UNICODE) ?>;
                const hideText = <?= json_encode(i18n_t('auth.hide_password_btn', [], 'Hide'), JSON_UNESCAPED_UNICODE) ?>;
                this.textContent = password.type === 'password' ? showText : hideText;
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const pass = document.getElementById('newpass').value;
                const confirm = document.getElementById('confirmpass').value;
                const passGroup = document.getElementById('newpass').closest('.form-group');
                const matchGroup = document.getElementById('confirmpass').closest('.form-group');

                passGroup.classList.remove('invalid');
                matchGroup.classList.remove('invalid');

                let ok = true;
                if (!/^(?=.*[0-9])(?=.*[!@#$%^&*]).{8,}$/.test(pass)) {
                    passGroup.classList.add('invalid');
                    ok = false;
                }
                if (pass !== confirm) {
                    matchGroup.classList.add('invalid');
                    ok = false;
                }
                if (!ok) return;

                const original = btn.textContent;
                btn.textContent = <?= json_encode(i18n_t('auth.reset_updating', [], 'Updating...'), JSON_UNESCAPED_UNICODE) ?>;
                btn.disabled = true;

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        credentials: 'same-origin',
                    });
                    const data = await response.json();

                    if (data.success) {
                        showNotice(<?= json_encode(i18n_t('auth.reset_updated_redirect', [], 'Password updated. Redirecting to login...'), JSON_UNESCAPED_UNICODE) ?>);
                        setTimeout(() => { window.location.href = 'login.php'; }, 900);
                    } else {
                        showNotice(data.message || <?= json_encode(i18n_t('auth.reset_update_failed', [], 'Could not update password.'), JSON_UNESCAPED_UNICODE) ?>, true);
                        btn.textContent = original;
                        btn.disabled = false;
                    }
                } catch (error) {
                    showNotice(<?= json_encode(i18n_t('auth.network_error', [], 'Network error. Please try again.'), JSON_UNESCAPED_UNICODE) ?>, true);
                    btn.textContent = original;
                    btn.disabled = false;
                }
            });
        </script>
    <?php endif; ?>
    <?= i18n_language_switcher_assets() ?>
    <script src="assets/js/theme.js"></script>
</body>
</html>
