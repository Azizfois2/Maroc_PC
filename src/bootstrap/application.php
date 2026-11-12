<?php
declare(strict_types=1);

define('REMEMBER_ME_SECONDS', 30 * 24 * 3600);

// Bump this when the on-demand schema helpers change so the one-time
// migration runs again after a deployment.
define('APP_SCHEMA_VERSION', '2026-11-12.1');

function secureCookieActive(): bool
{
    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function appCookieOptions(int $expires = 0): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => secureCookieActive(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => secureCookieActive(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// ── Database ──────────────────────────────────────────────
define('DB_HOST', envString('DB_HOST', 'localhost'));
define('DB_NAME', envString('DB_NAME', 'maroc_pc'));
define('DB_USER', envString('DB_USER', 'root'));
define('DB_PASS', envString('DB_PASS', ''));
define('DB_CHARSET', envString('DB_CHARSET', 'utf8mb4'));

// ── Security ──────────────────────────────────────────────
define('CSRF_TOKEN_NAME', envString('CSRF_TOKEN_NAME', 'marocpc_csrf'));

$appUrl = envString('APP_URL', 'http://localhost/Test/');
if ($appUrl !== '' && !str_ends_with($appUrl, '/')) {
    $appUrl .= '/';
}
define('APP_URL', $appUrl);

define('MAIL_FROM', envString('MAIL_FROM', 'noreply@marocpc.ma'));
define('DEV_MODE', envBool('DEV_MODE', true));
define('TRUSTED_PROXY_IPS', envString('TRUSTED_PROXY_IPS', ''));

// ── SMTP (PHPMailer) — set in .env (see env.example) ────────
define('SMTP_HOST', envString('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int) envString('SMTP_PORT', '465'));
define('SMTP_USER', envString('SMTP_USER', ''));
define('SMTP_PASS', envString('SMTP_PASS', ''));
define('SMTP_ENCRYPTION', envString('SMTP_ENCRYPTION', 'ssl'));
define('SMTP_FROM', envString('SMTP_FROM', envString('SMTP_USER', '')));
define('SMTP_FROM_NAME', envString('SMTP_FROM_NAME', 'Maroc PC'));

// Evolution API (optional). In DEV_MODE without these values, 2FA codes are logged locally.
define('EVOLUTION_API_URL', envString('EVOLUTION_API_URL', 'http://localhost:8080'));
define('EVOLUTION_API_KEY', envString('EVOLUTION_API_KEY', ''));
define('EVOLUTION_INSTANCE_NAME', envString('EVOLUTION_INSTANCE_NAME', 'Maroc PC'));

// Textbee SMS API (optional). In DEV_MODE without these values, SMS codes are logged locally.
define('TEXTBEE_API_KEY', envString('TEXTBEE_API_KEY', ''));
define('TEXTBEE_DEVICE_ID', envString('TEXTBEE_DEVICE_ID', ''));

// Cloudflare Turnstile CAPTCHA keys
define('TURNSTILE_SITE_KEY', envString('TURNSTILE_SITE_KEY', ''));
define('TURNSTILE_SECRET_KEY', envString('TURNSTILE_SECRET_KEY', ''));

// ── Google OAuth ──────────────────────────────────────────────
define('GOOGLE_CLIENT_ID', envString('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', envString('GOOGLE_CLIENT_SECRET', ''));
define('GOOGLE_REDIRECT_URI', APP_URL . 'google-callback.php');

// ── Facebook OAuth ────────────────────────────────────────────
define('FB_APP_ID', envString('FB_APP_ID', ''));
define('FB_APP_SECRET', envString('FB_APP_SECRET', ''));
define('FB_REDIRECT_URI', APP_URL . 'facebook-callback.php');

// ── Discord OAuth ─────────────────────────────────────────────
define('DISCORD_CLIENT_ID', envString('DISCORD_CLIENT_ID', ''));
define('DISCORD_CLIENT_SECRET', envString('DISCORD_CLIENT_SECRET', ''));
define('DISCORD_REDIRECT_URI', APP_URL . 'discord-callback.php');

// ── Xbox (Microsoft) OAuth ───────────────────────────────────
define('XBOX_CLIENT_ID', envString('XBOX_CLIENT_ID', ''));
define('XBOX_CLIENT_SECRET', envString('XBOX_CLIENT_SECRET', ''));
define('XBOX_REDIRECT_URI', APP_URL . 'xbox-callback.php');

// ── Stripe Keys ──────────────────────────────────────────────
define('STRIPE_PUBLISHABLE_KEY', envString('STRIPE_PUBLISHABLE_KEY', ''));
define('STRIPE_SECRET_KEY', envString('STRIPE_SECRET_KEY', ''));

// ── Error display (DEV_MODE enables verbose errors) ────────────
if (DEV_MODE) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

// ── Database connection ───────────────────────────────────
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            $requestPath = $_SERVER['REQUEST_URI'] ?? '';
            if (str_contains($requestPath, '/api/')) {
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=UTF-8');
                }
                echo json_encode([
                    'success' => false,
                    'error' => 'Database connection failed. Please try again later.',
                ]);
                exit;
            }
            exit('Database connection failed.');
        }
    }
    return $pdo;
}

// ── CSRF helpers ──────────────────────────────────────────
function csrfToken(): string
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrfField(): string
{
    $token = htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . $token . '">';
}

function verifyCsrf(?string $token): bool
{
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], (string) $token);
}

// ── Auth helpers ──────────────────────────────────────────
function applyLoginSessionLifetime(bool $remember): void
{
    $_SESSION['remember_me'] = $remember;
    
    // Set a temporary cookie so the frontend JS (theme.js) can plant the localStorage footprint
    $opts = appCookieOptions(time() + 60);
    $opts['httponly'] = false; // Must be readable by JS
    setcookie('plant_footprint', '1', $opts);

    if ($remember) {
        $expires = time() + REMEMBER_ME_SECONDS;
        setcookie(session_name(), session_id(), appCookieOptions($expires));
        setcookie('remember_token', bin2hex(random_bytes(32)), appCookieOptions($expires));
        return;
    }

    setcookie(session_name(), session_id(), appCookieOptions(0));
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', appCookieOptions(time() - 3600));
    }
}

function applyAdminSessionLifetime(): void
{
    $_SESSION['admin_session_started'] = time();

    setcookie(session_name(), session_id(), appCookieOptions(0));
    setcookie('has_active_admin_session', '1', appCookieOptions(0));

    $opts = appCookieOptions(time() + 60);
    $opts['httponly'] = false;
    setcookie('plant_admin_footprint', '1', $opts);
}

function destroyAdminSession(): void
{
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_nom'],
        $_SESSION['admin_email'],
        $_SESSION['admin_session_started']
    );

    if (isset($_COOKIE['has_active_admin_session'])) {
        setcookie('has_active_admin_session', '', appCookieOptions(time() - 3600));
    }

    if (isset($_COOKIE['plant_admin_footprint'])) {
        setcookie('plant_admin_footprint', '', appCookieOptions(time() - 3600));
    }
}

function destroyAppSession(): void
{
    $_SESSION = [];

    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', appCookieOptions(time() - 3600));
    }

    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', appCookieOptions(time() - 3600));
    }

    if (isset($_COOKIE['has_active_session'])) {
        setcookie('has_active_session', '', appCookieOptions(time() - 3600));
    }

    if (isset($_COOKIE['has_active_admin_session'])) {
        setcookie('has_active_admin_session', '', appCookieOptions(time() - 3600));
    }

    if (isset($_COOKIE['plant_admin_footprint'])) {
        setcookie('plant_admin_footprint', '', appCookieOptions(time() - 3600));
    }

    session_destroy();
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['client_id']);
}

function jsonResponse(bool $success, string $message, array $data = []): never
{
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// ── Mail helper (legacy — kept for backward compatibility) ─
function sendResetEmail(string $to, string $resetLink): bool
{
    if (!function_exists('sendPasswordResetEmail')) {
        require_once SRC_PATH . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'mailer.php';
    }

    if (function_exists('sendPasswordResetEmail')) {
        return sendPasswordResetEmail($to, $resetLink);
    }

    error_log('[MAIL ERROR] Password reset mailer is not available.');
    return false;
}

// ── Auto-Suppress Suspended Accounts (After 15 days) ──────
// Done probabilistically (1 in 10 requests) to avoid overhead on every request
if (rand(1, 10) === 1) {
    try {
        $db = db();
        // Check for accounts suspended > 15 days ago that are not yet deleted
        $db->exec("UPDATE Client SET deleted_at = NOW() WHERE is_suspended = 1 AND suspended_at <= DATE_SUB(NOW(), INTERVAL 15 DAY) AND deleted_at IS NULL");
    } catch (Exception $e) {
        // Silently ignore DB errors here to not break the app
    }
}

/**
 * Verify Cloudflare Turnstile CAPTCHA token
 * Uses file_get_contents — no cURL extension required.
 */
function verifyTurnstile(string $token): bool
{
    if (!defined('TURNSTILE_SECRET_KEY') || TURNSTILE_SECRET_KEY === '') {
        return true; // dev/testing — skip verification
    }

    if (empty($token)) {
        return false;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                'secret'   => TURNSTILE_SECRET_KEY,
                'response' => $token,
                'remoteip' => $ip,
            ]),
            'timeout' => 10,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $result = @file_get_contents(
        'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        false,
        $context
    );

    if ($result === false) {
        error_log('Turnstile: request failed');
        return false;
    }

    $response = json_decode($result, true);
    if (!is_array($response) || !isset($response['success'])) {
        error_log('Turnstile: bad response — ' . substr($result, 0, 300));
        return false;
    }

    return $response['success'] === true;
}
