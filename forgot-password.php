<?php
require_once 'config.php';
require_once __DIR__ . '/includes/i18n.php';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(i18n_current_locale(), ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars(i18n_direction(), ENT_QUOTES, 'UTF-8') ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= i18n_t('auth.forgot_title', [], 'Reset Password - Maroc PC') ?></title>
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
            <img src="signup.webp" alt="<?= i18n_t('auth.forgot_hero_alt', [], 'Gaming setup') ?>">
            <div class="hero-overlay">
                <h2><?= i18n_t('auth.forgot_locked_out', [], 'Locked Out?') ?></h2>
                <p><?= i18n_t('auth.forgot_hero_desc', [], 'No worries. We will send a secure reset link to your email.') ?></p>
            </div>
        </div>

        <div class="inscription">
            <form id="forgotForm" method="post" action="send-reset.php">
                <h3 id="myH3"><?= i18n_t('auth.forgot_title_small', [], 'Reset Password') ?></h3>
                <p class="subtitle"><?= i18n_t('auth.forgot_subtitle', [], 'Enter your account email and we will send you a reset link.') ?></p>

                <?php if (!empty($_GET['sent'])): ?>
                    <div class="toast show" style="position:static;transform:none;margin-bottom:20px;opacity:1;pointer-events:auto;">
                        <i class="fas fa-envelope"></i>
                        <span><?= i18n_t('auth.forgot_sent_notice', [], 'If an account exists, a reset link has been sent.') ?></span>
                    </div>
                <?php endif; ?>

                <div class="toast" id="resetNotice" style="position:static;transform:none;margin-bottom:20px;opacity:0;pointer-events:none;"></div>

                <div class="form-group">
                    <label for="email"><?= i18n_t('auth.email_label', [], 'Email Address') ?></label>
                    <input type="email" name="email" id="email" class="hh" placeholder="<?= i18n_t('auth.email_placeholder', [], 'john@example.com') ?>" required autocomplete="email">
                    <span class="error-msg" id="err-email"><?= i18n_t('auth.forgot_email_error', [], 'Please enter a valid email.') ?></span>
                </div>

                <?= csrfField(); ?>

                <div class="form-actions">
                    <button type="submit" class="Bou" id="submitBtn"><?= i18n_t('auth.forgot_send_btn', [], 'Send Reset Link') ?></button>
                </div>

                <p class="login-link"><?= i18n_t('auth.forgot_remember', [], 'Remember your password?') ?> <a href="login.php"><?= i18n_t('auth.sign_in', [], 'Sign in') ?></a></p>
            </form>
        </div>
    </div>

    <script>
        const form = document.getElementById('forgotForm');
        const notice = document.getElementById('resetNotice');
        const btn = document.getElementById('submitBtn');

        function showNotice(message, isError = false) {
            notice.className = 'toast show' + (isError ? ' error' : '');
            notice.style.opacity = '1';
            notice.style.pointerEvents = 'auto';
            notice.innerHTML = `<i class="fas ${isError ? 'fa-triangle-exclamation' : 'fa-envelope'}"></i><span>${message}</span>`;
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const original = btn.textContent;
            btn.textContent = <?= json_encode(i18n_t('auth.forgot_sending', [], 'Sending...'), JSON_UNESCAPED_UNICODE) ?>;
            btn.disabled = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                });
                const data = await response.json();

                showNotice(data.message || <?= json_encode(i18n_t('auth.forgot_sent_notice', [], 'If an account exists, a reset link has been sent.'), JSON_UNESCAPED_UNICODE) ?>, !data.success);
                if (data.dev_link) {
                    console.info('Password reset dev link:', data.dev_link);
                }
            } catch (error) {
                showNotice(<?= json_encode(i18n_t('auth.network_error', [], 'Network error. Please try again.'), JSON_UNESCAPED_UNICODE) ?>, true);
            } finally {
                btn.textContent = original;
                btn.disabled = false;
            }
        });
    </script>
    <?= i18n_language_switcher_assets() ?>
    <script src="assets/js/theme.js"></script>
</body>
</html>
