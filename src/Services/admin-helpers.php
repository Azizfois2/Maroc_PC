<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';

function adminRequireAuth(): void
{
    if (empty($_SESSION['admin_id'])) {
        $target = 'adminlogin.php';
        if (isset($_COOKIE['has_active_admin_session'])) {
            $target .= '?session_expired=1';
        }
        header('Location: ' . $target);
        exit();
    }
}

function adminRequireJsonAuth(): void
{
    if (!empty($_SESSION['admin_id'])) {
        return;
    }

    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'sessionExpired' => isset($_COOKIE['has_active_admin_session']),
    ]);
    exit;
}

function adminH($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function adminMoney(float $value): string
{
    // Get current locale
    if (!function_exists('i18n_current_locale')) {
        $i18nPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'i18n.php';
        if (is_file($i18nPath)) {
            require_once $i18nPath;
        }
    }
    
    $locale = function_exists('i18n_current_locale') ? i18n_current_locale() : 'en';
    
    // Currency symbols based on locale
    $currencies = [
        'ar' => 'د.م.',    // Arabic: Moroccan Dirham in Arabic
        'fr' => 'DH',      // French: DH
        'es' => 'DH',      // Spanish: DH
        'en' => 'DH'       // English: DH
    ];
    
    $currency = $currencies[$locale] ?? 'DH';
    $formatted = number_format($value, 2, '.', ',');
    
    return $formatted . ' ' . $currency;
}

/**
 * Fix common corrupted Arabic text that may enter the system through
 * external integrations (n8n workflows, automated imports, etc.).
 */
function adminFixCorruptedArabic(string $text): string
{
    $fixes = [
        // Common character corruptions
        'التظَّام' => 'النظام',
        'تعيث' => 'تعيين',
        'يونيُو' => 'يونيو',
        // More potential corruptions
        'إعاد' => 'إعادة',
        'مسخدم' => 'مستخدم',
        'الطل' => 'الطلب',
    ];

    foreach ($fixes as $wrong => $correct) {
        $text = str_replace($wrong, $correct, $text);
    }

    return $text;
}

function adminReportMissingTranslation(string $source, string $locale): void
{
    static $reported = [];

    if ($locale === '' || $locale === 'en') {
        return;
    }

    $key = $locale . "\0" . $source;
    if (isset($reported[$key])) {
        return;
    }
    $reported[$key] = true;

    $message = "[i18n] Missing admin phrase: '{$source}' locale: '{$locale}'";
    error_log($message);

    // Keep missing translations out of rendered HTML/JS. The log entry above is
    // enough for audits; visible warnings can break inline scripts.
}

function adminPhrase(string $source, array $params = []): string
{
    $translated = $source;
    $locale = 'en';
    if (!function_exists('i18n_current_locale') || !function_exists('i18n_page_phrase_map')) {
        $i18nPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'i18n.php';
        if (is_file($i18nPath)) {
            require_once $i18nPath;
        }
    }
    if (function_exists('i18n_current_locale') && function_exists('i18n_page_phrase_map')) {
        $locale = i18n_current_locale();
        $map = i18n_page_phrase_map($locale);
        if (array_key_exists($source, $map)) {
            $translated = (string) $map[$source];
        } else {
            if ($translated === $source) {
                adminReportMissingTranslation($source, $locale);
            }
        }
    }

    foreach ($params as $name => $replacement) {
        $translated = str_replace('{' . $name . '}', (string) $replacement, $translated);
    }

    return $translated;
}

function adminLabelFromValue(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return adminPhrase('N/A');
    }

    $source = ucwords(str_replace(['_', '-'], ' ', $value));
    return adminPhrase($source);
}

function adminStatusLabel(?string $value): string
{
    return adminLabelFromValue($value);
}

function adminPaymentStatusLabel(?string $value): string
{
    return adminLabelFromValue($value);
}

function adminBooleanLabel($value): string
{
    return adminPhrase(!empty($value) ? 'Yes' : 'No');
}

function adminCategoryLabel(?string $category): string
{
    $category = trim((string) $category);
    if ($category === '') {
        return '';
    }

    $labels = [
        'cpu' => 'CPU',
        'gpu' => 'GPU',
        'motherboard' => 'Motherboard',
        'ram' => 'RAM',
        'storage' => 'Storage',
        'psu' => 'Power Supply',
        'case' => 'Case',
        'cooling' => 'Cooling',
        'monitor' => 'Monitor',
        'accessories' => 'Accessories',
        'keyboard' => 'Keyboard',
        'mouse' => 'Mouse',
        'router' => 'Router',
        'vr' => 'VR',
        'laptop' => 'Laptop',
        'laptops' => 'Laptops',
    ];

    $source = $labels[strtolower($category)] ?? ucwords(str_replace(['_', '-'], ' ', $category));
    return adminPhrase($source);
}

function adminActivitySummaryLabel(?string $summary): string
{
    $summary = trim((string) $summary);
    if ($summary === '') {
        return '';
    }

    // Pattern: "Order #123 changed from pending to shipped"
    if (preg_match('/^Order #(\d+) changed from (\w+) to (\w+)$/i', $summary, $matches)) {
        return i18n_t('admin.activity_order_status_changed', [
            'id' => $matches[1],
            'old' => adminStatusLabel($matches[2]),
            'new' => adminStatusLabel($matches[3]),
        ], adminPhrase('Order #{id} changed from {old} to {new}', [
            'id' => $matches[1],
            'old' => adminStatusLabel($matches[2]),
            'new' => adminStatusLabel($matches[3]),
        ]));
    }

    // Pattern: "Order #123 assembly status changed from X to Y"
    if (preg_match('/^Order #(\d+) assembly status changed from (\w+) to (\w+)$/i', $summary, $matches)) {
        return i18n_t('admin.activity_assembly_status_changed', [
            'id' => $matches[1],
            'old' => adminStatusLabel($matches[2]),
            'new' => adminStatusLabel($matches[3]),
        ], adminPhrase('Order #{id} assembly status changed from {old} to {new}', [
            'id' => $matches[1],
            'old' => adminStatusLabel($matches[2]),
            'new' => adminStatusLabel($matches[3]),
        ]));
    }

    // Pattern: "Deleted order #123"
    if (preg_match('/^Deleted order #(\d+)$/i', $summary, $matches)) {
        return i18n_t('admin.activity_deleted_order', ['id' => $matches[1]], adminPhrase('Deleted order #{id}', ['id' => $matches[1]]));
    }

    // Pattern: "Added note to order #123"
    if (preg_match('/^Added note to order #(\d+)$/i', $summary, $matches)) {
        return i18n_t('admin.activity_added_note', ['id' => $matches[1]], adminPhrase('Added note to order #{id}', ['id' => $matches[1]]));
    }

    // Pattern: "Promo CODE123 used on order #456"
    if (preg_match('/^Promo (\w+) used on order #(\d+)$/i', $summary, $matches)) {
        return adminPhrase('Promo {code} used on order #{id}', [
            'code' => $matches[1],
            'id' => $matches[2],
        ]);
    }

    // Pattern: "Set trade_in_requests #1 to declined"
    if (preg_match('/^Set ([a-z_]+) #(\d+) to (\w+)$/i', $summary, $matches)) {
        return adminPhrase('Set {table} #{id} to {status}', [
            'table' => str_replace('_', ' ', $matches[1]),
            'id' => $matches[2],
            'status' => adminStatusLabel($matches[3]),
        ]);
    }

    // Pattern: "Updated restock plan for product/laptop #123"
    if (preg_match('/^Updated restock plan for (product|laptop) #(\d+)$/i', $summary, $matches)) {
        $entity = strtolower($matches[1]) === 'laptop' ? adminPhrase('laptop') : adminPhrase('product');
        return adminPhrase('Updated restock plan for {entity} #{id}', [
            'entity' => $entity,
            'id' => $matches[2],
        ]);
    }

    // Pattern: "Updated homepage gamer review for Name"
    if (preg_match('/^Updated homepage gamer review for (.+)$/i', $summary, $matches)) {
        return adminPhrase('Updated homepage gamer review for {name}', ['name' => $matches[1]]);
    }

    // Pattern: "Added homepage gamer review for Name"
    if (preg_match('/^Added homepage gamer review for (.+)$/i', $summary, $matches)) {
        return adminPhrase('Added homepage gamer review for {name}', ['name' => $matches[1]]);
    }

    // Pattern: "Deleted homepage gamer review #123"
    if (preg_match('/^Deleted homepage gamer review #(\d+)$/i', $summary, $matches)) {
        return adminPhrase('Deleted homepage gamer review #{id}', ['id' => $matches[1]]);
    }

    // Pattern: "Declared special event deal for Product"
    if (preg_match('/^Declared special event deal for (.+)$/i', $summary, $matches)) {
        return adminPhrase('Declared special event deal for {product}', ['product' => $matches[1]]);
    }

    // Pattern: "Deleted flash sale #123"
    if (preg_match('/^Deleted flash sale #(\d+)$/i', $summary, $matches)) {
        return adminPhrase('Deleted flash sale #{id}', ['id' => $matches[1]]);
    }

    // Pattern: "Deleted scheduled email #123"
    if (preg_match('/^Deleted scheduled email #(\d+)$/i', $summary, $matches)) {
        return adminPhrase('Deleted scheduled email #{id}', ['id' => $matches[1]]);
    }

    // Pattern: "Sent marketing test email: Subject"
    if (preg_match('/^Sent marketing test email: (.+)$/i', $summary, $matches)) {
        return adminPhrase('Sent marketing test email: {subject}', ['subject' => $matches[1]]);
    }

    // Pattern: "Scheduled campaign 'Subject' to type"
    if (preg_match("/^Scheduled campaign '(.+)' to (.+)$/i", $summary, $matches)) {
        return adminPhrase("Scheduled campaign '{subject}' to {type}", ['subject' => $matches[1], 'type' => $matches[2]]);
    }

    // Pattern: "Blocked IP: 1.2.3.4"
    if (preg_match('/^Blocked IP: (.+)$/i', $summary, $matches)) {
        return adminPhrase('Blocked IP: {ip}', ['ip' => $matches[1]]);
    }

    // Pattern: "Unblocked IP: 1.2.3.4"
    if (preg_match('/^Unblocked IP: (.+)$/i', $summary, $matches)) {
        return adminPhrase('Unblocked IP: {ip}', ['ip' => $matches[1]]);
    }

    // Pattern: "Blocked country: Name (CC)"
    if (preg_match('/^Blocked country: (.+) \(([A-Z]{2})\)/i', $summary, $matches)) {
        return adminPhrase('Blocked country: {name} ({code})', ['name' => $matches[1], 'code' => $matches[2]]);
    }

    // Pattern: "Unblocked country: Name (CC)"
    if (preg_match('/^Unblocked country: (.+) \(([A-Z]{2})\)/i', $summary, $matches)) {
        return adminPhrase('Unblocked country: {name} ({code})', ['name' => $matches[1], 'code' => $matches[2]]);
    }

    // Pattern: "Price history already captured for 2026-06-10"
    if (preg_match('/^Price history already captured for (.+)$/i', $summary, $matches)) {
        return adminPhrase('Price history already captured for {date}', ['date' => $matches[1]]);
    }

    // Pattern: "Already snapshotted for 2026-06-10"
    if (preg_match('/^Already snapshotted for (.+)$/i', $summary, $matches)) {
        return adminPhrase('Already snapshotted for {date}', ['date' => $matches[1]]);
    }

    // Pattern: "Synced 5 customer loyalty record(s)" (also catches typos like "Synced o ...")
    if (preg_match('/^Synced (.+?) customer loyalty record\(s\)$/i', $summary, $matches)) {
        $count = $matches[1];
        // If the captured count is not purely numeric (e.g. "o" typo from n8n), try to recover it
        if (!is_numeric($count)) {
            $count = ctype_digit($count) ? $count : '0';
        }
        return adminPhrase('Synced {count} customer loyalty record(s)', ['count' => $count]);
    }

    // Pattern: "Archived 10 visit row(s)"
    if (preg_match('/^Archived (\d+) visit row\(s\)\.?$/i', $summary, $matches)) {
        return adminPhrase('Archived {count} visit row(s).', ['count' => $matches[1]]);
    }

    // Pattern: "Sent 3 emails for campaign #5"
    if (preg_match('/^Sent (\d+) emails for campaign #(\d+)\.?$/i', $summary, $matches)) {
        return adminPhrase('Sent {count} emails for campaign #{id}.', ['count' => $matches[1], 'id' => $matches[2]]);
    }

    // Pattern: "Test email sent to user@example.com"
    if (preg_match('/^Test email sent to (.+)\.?$/i', $summary, $matches)) {
        return adminPhrase('Test email sent to {email}.', ['email' => $matches[1]]);
    }

    // Pattern: "Customer approved feedback for Trusted by Gamers"
    if (preg_match('/^Customer approved feedback for Trusted by Gamers$/i', $summary)) {
        return adminPhrase('Customer approved feedback for Trusted by Gamers');
    }

    // Pattern: "Requested customer approval for Trusted by Gamers publication"
    if (preg_match('/^Requested customer approval for Trusted by Gamers publication$/i', $summary)) {
        return adminPhrase('Requested customer approval for Trusted by Gamers publication');
    }

    // Pattern: "{count} order(s) updated to {status}."
    if (preg_match('/^(\d+) order\(s\) updated to (\w+)\.?$/i', $summary, $matches)) {
        return adminPhrase('{count} order(s) updated to {status}.', ['count' => $matches[1], 'status' => adminStatusLabel($matches[2])]);
    }

    // Pattern: "Permanently delete {count} order(s)? This cannot be undone."
    if (preg_match('/^Permanently delete (\d+) order\(s\)\? This cannot be undone\.$/i', $summary, $matches)) {
        return adminPhrase('Permanently delete {count} order(s)? This cannot be undone.', ['count' => $matches[1]]);
    }

    // Pattern: "Captured 5 product prices for 2026-06-14"
    if (preg_match('/^Captured (\d+|\w+) product prices? for (.+)$/i', $summary, $matches)) {
        return adminPhrase('Captured {count} product prices for {date}', ['count' => $matches[1], 'date' => $matches[2]]);
    }

    return adminFixCorruptedArabic(adminPhrase($summary));
}

function adminFormatDate($value, string $style = 'date'): string
{
    $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
    if (!$timestamp) {
        return '';
    }

    $locale = function_exists('i18n_current_locale') ? i18n_current_locale() : 'en';
    if ($locale !== 'ar') {
        return match ($style) {
            'date_short' => date('M j', $timestamp),
            'datetime_short' => date('M j, H:i', $timestamp),
            'datetime_full' => date('M j, Y H:i', $timestamp),
            default => date('M j, Y', $timestamp),
        };
    }

    $months = [
        1 => 'يناير',
        2 => 'فبراير',
        3 => 'مارس',
        4 => 'أبريل',
        5 => 'مايو',
        6 => 'يونيو',
        7 => 'يوليو',
        8 => 'أغسطس',
        9 => 'سبتمبر',
        10 => 'أكتوبر',
        11 => 'نوفمبر',
        12 => 'ديسمبر',
    ];
    $day = date('j', $timestamp);
    $month = $months[(int) date('n', $timestamp)] ?? date('M', $timestamp);
    $year = date('Y', $timestamp);
    $time = date('H:i', $timestamp);

    return match ($style) {
        'date_short' => $day . ' ' . $month,
        'datetime_short' => $day . ' ' . $month . '، ' . $time,
        'datetime_full' => $day . ' ' . $month . ' ' . $year . '، ' . $time,
        default => $day . ' ' . $month . ' ' . $year,
    };
}

function adminRedirect(string $path): never
{
    header('Location: ' . $path);
    exit();
}

function adminTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
    return (bool) $stmt->fetch(PDO::FETCH_NUM);
}

/**
 * Request-scoped existence check for read-only screens. Unlike adminTableExists
 * it is not invalidated after CREATE TABLE, so only use it where the code does
 * not create the table in the same request.
 */
function adminTableExistsCached(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (!array_key_exists($table, $cache)) {
        $cache[$table] = adminTableExists($pdo, $table);
    }
    return $cache[$table];
}

function adminColumnExists(PDO $pdo, string $table, string $column): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return false;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function ensureAdminUsersTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL DEFAULT 'Administrator',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function adminCountAdmins(PDO $pdo): int
{
    if (!adminTableExists($pdo, 'admin_users')) {
        return 0;
    }
    return (int) adminFetchValue($pdo, 'SELECT COUNT(*) FROM admin_users');
}

function adminFetchValue(PDO $pdo, string $sql, array $params = [], $fallback = 0)
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value !== false && $value !== null ? $value : $fallback;
    } catch (Throwable $e) {
        return $fallback;
    }
}

function adminFetchAll(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

// Simple query result caching for admin dashboard
function adminCachedFetch(PDO $pdo, string $cacheKey, callable $fetchCallback, int $ttlSeconds = 300): mixed
{
    static $cache = [];
    
    if (!isset($_SESSION['admin_query_cache'])) {
        $_SESSION['admin_query_cache'] = [];
    }
    
    $now = time();
    
    // Check session cache first (survives across requests)
    if (isset($_SESSION['admin_query_cache'][$cacheKey])) {
        $cached = $_SESSION['admin_query_cache'][$cacheKey];
        if ($cached['expires'] > $now) {
            return $cached['data'];
        }
        unset($_SESSION['admin_query_cache'][$cacheKey]);
    }
    
    // Check static cache (for same request)
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    
    // Fetch fresh data
    $data = $fetchCallback();
    
    // Store in both caches
    $cache[$cacheKey] = $data;
    $_SESSION['admin_query_cache'][$cacheKey] = [
        'data' => $data,
        'expires' => $now + $ttlSeconds
    ];
    
    // Clean old cache entries (keep last 50)
    if (count($_SESSION['admin_query_cache']) > 50) {
        $sorted = $_SESSION['admin_query_cache'];
        uasort($sorted, fn($a, $b) => $b['expires'] <=> $a['expires']);
        $_SESSION['admin_query_cache'] = array_slice($sorted, 0, 50, true);
    }
    
    return $data;
}

// Clear admin cache
function adminClearCache(string $pattern = null): void
{
    if ($pattern === null) {
        $_SESSION['admin_query_cache'] = [];
    } else {
        foreach ($_SESSION['admin_query_cache'] ?? [] as $key => $value) {
            if (str_contains($key, $pattern)) {
                unset($_SESSION['admin_query_cache'][$key]);
            }
        }
    }
}

function adminEnsureSettingsTable(PDO $pdo): void
{
    if (!empty($_SESSION['admin_ensured_settings'])) return;
    if (appSchemaIsCurrent()) { $_SESSION['admin_ensured_settings'] = true; return; }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_settings (
            setting_key VARCHAR(120) PRIMARY KEY,
            setting_value TEXT DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $_SESSION['admin_ensured_settings'] = true;
}

function &adminSettingCache(): array
{
    static $cache = [];
    return $cache;
}

function adminSetting(PDO $pdo, string $key, $default = null)
{
    $cache = &adminSettingCache();
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    adminEnsureSettingsTable($pdo);
    $cache[$key] = adminFetchValue($pdo, 'SELECT setting_value FROM admin_settings WHERE setting_key = ?', [$key], $default);
    return $cache[$key];
}

function adminSetSetting(PDO $pdo, string $key, $value): void
{
    adminEnsureSettingsTable($pdo);
    $stmt = $pdo->prepare("
        INSERT INTO admin_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([$key, (string) $value]);
    $cache = &adminSettingCache();
    $cache[$key] = (string) $value;
}

function adminEnsureProductAdminColumns(PDO $pdo): void
{
    if (!empty($_SESSION['admin_ensured_products'])) return;
    if (appSchemaIsCurrent()) { $_SESSION['admin_ensured_products'] = true; return; }
    if (!adminTableExists($pdo, 'products')) {
        $pdo->exec("
            CREATE TABLE products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                brand VARCHAR(100) NOT NULL,
                category VARCHAR(50) NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                old_price DECIMAL(10,2) DEFAULT NULL,
                badge VARCHAR(50) DEFAULT NULL,
                rating DECIMAL(2,1) DEFAULT NULL,
                reviews INT DEFAULT 0,
                image VARCHAR(255) DEFAULT NULL,
                featured TINYINT(1) DEFAULT 0,
                in_stock TINYINT(1) DEFAULT 1,
                specs JSON DEFAULT NULL,
                stock_quantity INT NOT NULL DEFAULT 0,
                reorder_level INT NOT NULL DEFAULT 5,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    if (!adminTableExists($pdo, 'products')) {
        return;
    }

    if (!adminColumnExists($pdo, 'products', 'stock_quantity')) {
        $pdo->exec('ALTER TABLE products ADD stock_quantity INT NOT NULL DEFAULT 0');
        $pdo->exec('UPDATE products SET stock_quantity = CASE WHEN in_stock = 1 THEN 10 ELSE 0 END');
    }

    if (!adminColumnExists($pdo, 'products', 'reorder_level')) {
        $pdo->exec('ALTER TABLE products ADD reorder_level INT NOT NULL DEFAULT 5');
    }

    if ((int) adminFetchValue($pdo, 'SELECT COUNT(*) FROM products') === 0) {
        adminImportProductsFromDataJs($pdo);
    }
    $_SESSION['admin_ensured_products'] = true;
}

function adminEnsureClientMarketingColumns(PDO $pdo): void
{
    if (!empty($_SESSION['admin_ensured_client_marketing'])) return;
    if (appSchemaIsCurrent()) { $_SESSION['admin_ensured_client_marketing'] = true; return; }
    if (!adminTableExists($pdo, 'Client')) {
        return;
    }

    if (!adminColumnExists($pdo, 'Client', 'deleted_at')) {
        try {
            $pdo->exec('ALTER TABLE Client ADD COLUMN deleted_at DATETIME DEFAULT NULL');
        } catch (Throwable $e) {
            // Another request/setup may have added it already.
        }
    }
    $_SESSION['admin_ensured_client_marketing'] = true;
}

function adminEnsureMarketingTables(PDO $pdo): void
{
    if (!empty($_SESSION['admin_ensured_marketing'])) return;
    if (appSchemaIsCurrent()) { $_SESSION['admin_ensured_marketing'] = true; return; }
    adminEnsureClientMarketingColumns($pdo);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    if (!adminColumnExists($pdo, 'newsletter_subscribers', 'subscribed_at')) {
        $pdo->exec('ALTER TABLE newsletter_subscribers ADD subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS scheduled_emails (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            scheduled_at DATETIME NOT NULL,
            recipients_type ENUM('all','subscribers','everyone') DEFAULT 'all',
            status ENUM('pending','sending','sent','failed') DEFAULT 'pending',
            total_recipients INT DEFAULT 0,
            sent_count INT DEFAULT 0,
            sent_at DATETIME DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $scheduledColumns = [
        'recipients_type' => "ALTER TABLE scheduled_emails ADD recipients_type ENUM('all','subscribers','everyone') DEFAULT 'all'",
        'status' => "ALTER TABLE scheduled_emails ADD status ENUM('pending','sending','sent','failed') DEFAULT 'pending'",
        'total_recipients' => 'ALTER TABLE scheduled_emails ADD total_recipients INT DEFAULT 0',
        'sent_count' => 'ALTER TABLE scheduled_emails ADD sent_count INT DEFAULT 0',
        'sent_at' => 'ALTER TABLE scheduled_emails ADD sent_at DATETIME DEFAULT NULL',
        'error_message' => 'ALTER TABLE scheduled_emails ADD error_message TEXT DEFAULT NULL',
        'created_at' => 'ALTER TABLE scheduled_emails ADD created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ];
    foreach ($scheduledColumns as $column => $sql) {
        if (!adminColumnExists($pdo, 'scheduled_emails', $column)) {
            $pdo->exec($sql);
        }
    }
    try {
        $pdo->exec("ALTER TABLE scheduled_emails MODIFY recipients_type VARCHAR(50) NOT NULL DEFAULT 'all'");
    } catch (Throwable $e) {
        // Older MySQL variants may already be compatible enough for the core targets.
    }

    $pdo->exec("
        UPDATE scheduled_emails
        SET recipients_type = 'all'
        WHERE recipients_type IS NULL
           OR recipients_type NOT IN ('all', 'subscribers', 'everyone', 'high_spenders', 'inactive_30', 'gpu_buyers', 'newsletter_only')
    ");
    $_SESSION['admin_ensured_marketing'] = true;
}

function adminEnsureFlashSalesTable(PDO $pdo): void
{
    if (!empty($_SESSION['admin_ensured_flash_sales'])) return;
    if (appSchemaIsCurrent()) { $_SESSION['admin_ensured_flash_sales'] = true; return; }
    adminEnsureProductAdminColumns($pdo);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS flash_sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            sale_price DECIMAL(10,2) NOT NULL,
            original_price DECIMAL(10,2) NOT NULL,
            max_quantity INT DEFAULT NULL,
            sold_count INT DEFAULT 0,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $flashColumns = [
        'max_quantity' => 'ALTER TABLE flash_sales ADD max_quantity INT DEFAULT NULL',
        'sold_count' => 'ALTER TABLE flash_sales ADD sold_count INT DEFAULT 0',
        'event_name' => 'ALTER TABLE flash_sales ADD event_name VARCHAR(120) DEFAULT NULL',
        'event_badge' => 'ALTER TABLE flash_sales ADD event_badge VARCHAR(80) DEFAULT NULL',
        'event_note' => 'ALTER TABLE flash_sales ADD event_note VARCHAR(255) DEFAULT NULL',
        'created_at' => 'ALTER TABLE flash_sales ADD created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ];
    foreach ($flashColumns as $column => $sql) {
        if (!adminColumnExists($pdo, 'flash_sales', $column)) {
            $pdo->exec($sql);
        }
    }
    $_SESSION['admin_ensured_flash_sales'] = true;
}

function adminEnsureHomepageGamerReviewsTable(PDO $pdo): void
{
    if (!empty($_SESSION['admin_ensured_homepage_reviews'])) return;
    if (appSchemaIsCurrent()) { $_SESSION['admin_ensured_homepage_reviews'] = true; return; }
    adminEnsureSettingsTable($pdo);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS homepage_gamer_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reviewer_name VARCHAR(120) NOT NULL,
            reviewer_role VARCHAR(120) NOT NULL DEFAULT 'Verified gamer',
            quote TEXT NOT NULL,
            reviewer_name_fr VARCHAR(120) DEFAULT NULL,
            reviewer_role_fr VARCHAR(120) DEFAULT NULL,
            quote_fr TEXT DEFAULT NULL,
            reviewer_name_ar VARCHAR(120) DEFAULT NULL,
            reviewer_role_ar VARCHAR(120) DEFAULT NULL,
            quote_ar TEXT DEFAULT NULL,
            reviewer_name_es VARCHAR(120) DEFAULT NULL,
            reviewer_role_es VARCHAR(120) DEFAULT NULL,
            quote_es TEXT DEFAULT NULL,
            avatar_initials VARCHAR(8) DEFAULT NULL,
            rating DECIMAL(2,1) NOT NULL DEFAULT 5.0,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_homepage_gamer_reviews_active (is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $reviewTranslationColumns = [
        'reviewer_name_fr' => 'ALTER TABLE homepage_gamer_reviews ADD reviewer_name_fr VARCHAR(120) DEFAULT NULL',
        'reviewer_role_fr' => 'ALTER TABLE homepage_gamer_reviews ADD reviewer_role_fr VARCHAR(120) DEFAULT NULL',
        'quote_fr' => 'ALTER TABLE homepage_gamer_reviews ADD quote_fr TEXT DEFAULT NULL',
        'reviewer_name_ar' => 'ALTER TABLE homepage_gamer_reviews ADD reviewer_name_ar VARCHAR(120) DEFAULT NULL',
        'reviewer_role_ar' => 'ALTER TABLE homepage_gamer_reviews ADD reviewer_role_ar VARCHAR(120) DEFAULT NULL',
        'quote_ar' => 'ALTER TABLE homepage_gamer_reviews ADD quote_ar TEXT DEFAULT NULL',
        'reviewer_name_es' => 'ALTER TABLE homepage_gamer_reviews ADD reviewer_name_es VARCHAR(120) DEFAULT NULL',
        'reviewer_role_es' => 'ALTER TABLE homepage_gamer_reviews ADD reviewer_role_es VARCHAR(120) DEFAULT NULL',
        'quote_es' => 'ALTER TABLE homepage_gamer_reviews ADD quote_es TEXT DEFAULT NULL',
    ];
    foreach ($reviewTranslationColumns as $column => $sql) {
        if (!adminColumnExists($pdo, 'homepage_gamer_reviews', $column)) {
            $pdo->exec($sql);
        }
    }

    $i18nPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'i18n.php';
    if (is_file($i18nPath)) {
        require_once $i18nPath;
    }
    if (function_exists('i18n_supported_locales')) {
        $defaultLocale = defined('I18N_DEFAULT_LOCALE') ? I18N_DEFAULT_LOCALE : 'en';
        foreach (i18n_supported_locales() as $locale) {
            if ($locale === $defaultLocale || !preg_match('/^[a-z]{2}$/', (string) $locale)) {
                continue;
            }
            $dynamicColumns = [
                "reviewer_name_{$locale}" => "ALTER TABLE homepage_gamer_reviews ADD reviewer_name_{$locale} VARCHAR(120) DEFAULT NULL",
                "reviewer_role_{$locale}" => "ALTER TABLE homepage_gamer_reviews ADD reviewer_role_{$locale} VARCHAR(120) DEFAULT NULL",
                "quote_{$locale}" => "ALTER TABLE homepage_gamer_reviews ADD quote_{$locale} TEXT DEFAULT NULL",
            ];
            foreach ($dynamicColumns as $column => $sql) {
                if (!adminColumnExists($pdo, 'homepage_gamer_reviews', $column)) {
                    $pdo->exec($sql);
                }
            }
        }
    }

    $seeded = (string) adminFetchValue(
        $pdo,
        "SELECT setting_value FROM admin_settings WHERE setting_key = 'homepage_gamer_reviews_seeded'",
        [],
        ''
    );
    $reviewCount = (int) adminFetchValue($pdo, 'SELECT COUNT(*) FROM homepage_gamer_reviews');

    if ($seeded !== '1' && $reviewCount === 0) {
        $defaults = [
            ['Abdelkrim El Cherifi', 'Verified Customer', 'The AI PC Builder is a game changer. It perfectly balanced my Ryzen 7 and RTX 4070 build without bottlenecking. Delivered to Marrakech in 2 days!', 'AE', 5.0, 1],
            ['Sami L.', 'Content Creator', "Setup nadi bezaf! Les prix homa hadok w l'emballage ja m9ad. Service client jawboni f blassa mli swlthom 3la l'alimentation. Top!", 'SL', 5.0, 2],
            ['Yassine B.', '3D Artist', "Montage tres propre et cable management impeccable. J'ai commande une workstation pour le rendu 3D et les temperatures sont excellentes.", 'YB', 4.5, 3],
            ['Lina R.', 'Tech Enthusiast', 'Finally, a Moroccan hardware store that updates stock in real-time. Got my hands on the new OLED monitor before it sold out everywhere else.', 'LR', 5.0, 4],
            ['Ali Z.', 'Software Engineer', 'Tawsil srie3 w les composants kolhom originaux b garantie. Le setup kamel wsalni l Tanger f a9al mn 48h. Chokran Maroc PC!', 'AZ', 5.0, 5],
            ['Sofia T.', 'E-sports Pro', 'Best gaming store in Morocco, hands down. Le service apres-vente est incredible, they helped me troubleshoot a RAM issue via WhatsApp instantly.', 'ST', 5.0, 6],
        ];

        $stmt = $pdo->prepare("
            INSERT INTO homepage_gamer_reviews (reviewer_name, reviewer_role, quote, avatar_initials, rating, sort_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        foreach ($defaults as $review) {
            $stmt->execute($review);
        }
    }

    adminSetSetting($pdo, 'homepage_gamer_reviews_seeded', '1');
    $_SESSION['admin_ensured_homepage_reviews'] = true;
}

function adminEnsureFeedbackTestimonialApprovalsTable(PDO $pdo): void
{
    if (!empty($_SESSION['admin_ensured_feedback_approvals'])) return;
    if (appSchemaIsCurrent()) { $_SESSION['admin_ensured_feedback_approvals'] = true; return; }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS feedback_testimonial_approvals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            feedback_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            status ENUM('pending','accepted','expired','cancelled') NOT NULL DEFAULT 'pending',
            homepage_review_id INT DEFAULT NULL,
            requested_by VARCHAR(255) DEFAULT NULL,
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            accepted_at DATETIME DEFAULT NULL,
            expires_at DATETIME NOT NULL,
            INDEX idx_feedback_approval_feedback_status (feedback_id, status),
            INDEX idx_feedback_approval_status_expiry (status, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $columns = [
        'homepage_review_id' => 'ALTER TABLE feedback_testimonial_approvals ADD homepage_review_id INT DEFAULT NULL',
        'requested_by' => 'ALTER TABLE feedback_testimonial_approvals ADD requested_by VARCHAR(255) DEFAULT NULL',
        'accepted_at' => 'ALTER TABLE feedback_testimonial_approvals ADD accepted_at DATETIME DEFAULT NULL',
        'expires_at' => 'ALTER TABLE feedback_testimonial_approvals ADD expires_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ];
    foreach ($columns as $column => $sql) {
        if (!adminColumnExists($pdo, 'feedback_testimonial_approvals', $column)) {
            $pdo->exec($sql);
        }
    }
    $_SESSION['admin_ensured_feedback_approvals'] = true;
}

function adminFeedbackApprovalPublicUrl(string $token): string
{
    return APP_URL . 'feedback-testimonial-approval.php?token=' . urlencode($token);
}

function adminFeedbackReviewerInitials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($parts === []) {
        return 'MP';
    }

    $first = strtoupper(substr((string) $parts[0], 0, 1));
    $last = count($parts) > 1 ? strtoupper(substr((string) $parts[count($parts) - 1], 0, 1)) : '';
    $initials = preg_replace('/[^A-Z0-9]/', '', $first . $last);

    return $initials !== '' ? substr($initials, 0, 2) : 'MP';
}

function adminFeedbackApprovalRequester(): ?string
{
    $candidates = [
        $_SESSION['admin_email'] ?? null,
        $_SESSION['admin']['email'] ?? null,
        $_SESSION['admin_user']['email'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }
    }

    return null;
}

function adminCreateFeedbackApprovalRequest(PDO $pdo, int $feedbackId): array
{
    adminEnsureHomepageGamerReviewsTable($pdo);
    adminEnsureFeedbackTestimonialApprovalsTable($pdo);

    $feedback = $pdo->query('SELECT * FROM customer_feedback WHERE id = ' . (int) $feedbackId . ' LIMIT 1')
        ->fetch(PDO::FETCH_ASSOC);
    if (!$feedback) {
        return ['success' => false, 'message' => 'Feedback was not found.'];
    }

    $email = trim((string) ($feedback['email'] ?? ''));
    $name = trim((string) ($feedback['name'] ?? ''));
    $message = trim((string) ($feedback['message'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'This feedback does not have a valid customer email.'];
    }
    if ($name === '' || $message === '') {
        return ['success' => false, 'message' => 'This feedback is missing a publishable name or message.'];
    }

    $pdo->prepare("UPDATE feedback_testimonial_approvals SET status = 'cancelled' WHERE feedback_id = ? AND status = 'pending'")
        ->execute([$feedbackId]);

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = (new DateTimeImmutable('+14 days'))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        INSERT INTO feedback_testimonial_approvals (feedback_id, token_hash, requested_by, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$feedbackId, $tokenHash, adminFeedbackApprovalRequester(), $expiresAt]);
    $approvalId = (int) $pdo->lastInsertId();

    require_once dirname(__DIR__, 2) . '/mailer.php';

    $approvalUrl = adminFeedbackApprovalPublicUrl($token);
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeQuote = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $safeLink = htmlspecialchars($approvalUrl, ENT_QUOTES, 'UTF-8');
    $body = emailTemplate('Approve Sharing Your Feedback', '
        <p>Hi <strong>' . $safeName . '</strong>,</p>
        <p>Thank you for sending feedback to Maroc PC. We would like to feature your comment in our public Trusted by Gamers section.</p>
        <div class="highlight">
            <p>"' . $safeQuote . '"</p>
        </div>
        <p>We will only publish it if you approve. Your email address will not be shown.</p>
        <div class="btn-wrap">
            <a href="' . $safeLink . '" class="btn">Approve Publication</a>
        </div>
        <p class="small">Or copy this link:<br><span class="link">' . $safeLink . '</span></p>
        <p class="small">This approval link expires in 14 days. If you do not want this comment published, you can ignore this email.</p>
    ');

    if (!sendEmail($email, 'Approve sharing your Maroc PC feedback', $body)) {
        $pdo->prepare("UPDATE feedback_testimonial_approvals SET status = 'cancelled' WHERE id = ?")->execute([$approvalId]);
        $error = function_exists('lastMailError') ? (lastMailError() ?: 'Email could not be sent.') : 'Email could not be sent.';
        return ['success' => false, 'message' => $error];
    }

    adminLogActivity($pdo, 'request_approval', 'customer_feedback', $feedbackId, 'Requested customer approval for Trusted by Gamers publication');

    return ['success' => true, 'message' => 'Approval email sent to the customer.'];
}

function adminAcceptFeedbackApproval(PDO $pdo, string $token): array
{
    adminEnsureHomepageGamerReviewsTable($pdo);
    adminEnsureFeedbackTestimonialApprovalsTable($pdo);

    $token = trim($token);
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return ['success' => false, 'message' => 'This approval link is invalid.'];
    }

    $tokenHash = hash('sha256', $token);
    $approval = $pdo->query("
        SELECT a.*, f.name, f.email, f.rating, f.message
        FROM feedback_testimonial_approvals a
        INNER JOIN customer_feedback f ON f.id = a.feedback_id
        WHERE a.token_hash = " . $pdo->quote($tokenHash) . "
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    if (!$approval) {
        return ['success' => false, 'message' => 'This approval link was not found.'];
    }

    if ($approval['status'] === 'accepted') {
        return ['success' => true, 'message' => 'Thank you. This feedback has already been approved for publication.'];
    }
    if ($approval['status'] !== 'pending') {
        return ['success' => false, 'message' => 'This approval link is no longer active.'];
    }
    if (strtotime((string) $approval['expires_at']) < time()) {
        $pdo->prepare("UPDATE feedback_testimonial_approvals SET status = 'expired' WHERE id = ?")->execute([(int) $approval['id']]);
        return ['success' => false, 'message' => 'This approval link has expired. Please ask Maroc PC to send a new one.'];
    }

    $name = trim((string) $approval['name']);
    $message = trim((string) $approval['message']);
    $rating = max(1.0, min(5.0, (float) $approval['rating']));
    $sortOrder = (int) adminFetchValue($pdo, 'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM homepage_gamer_reviews', [], 1);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO homepage_gamer_reviews (reviewer_name, reviewer_role, quote, avatar_initials, rating, sort_order, is_active)
            VALUES (?, 'Verified Customer', ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $name,
            $message,
            adminFeedbackReviewerInitials($name),
            $rating,
            $sortOrder,
        ]);
        $reviewId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            UPDATE feedback_testimonial_approvals
            SET status = 'accepted', accepted_at = NOW(), homepage_review_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$reviewId, (int) $approval['id']]);

        $pdo->exec("UPDATE customer_feedback SET status = 'reviewed' WHERE id = " . (int) $approval['feedback_id'] . " AND status = 'new'");

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'We could not publish this feedback right now.'];
    }

    adminLogActivity($pdo, 'publish', 'homepage_gamer_reviews', $reviewId, 'Customer approved feedback for Trusted by Gamers');

    return ['success' => true, 'message' => 'Thank you. Your feedback is now approved for the Trusted by Gamers section.'];
}

/**
 * One-time schema marker.
 *
 * The admin "ensure*" helpers create tables and columns on demand. Running them
 * on every request (or every new session) costs dozens of DDL/metadata round
 * trips, so the work is done once per deployment and recorded in this marker.
 * When the marker directory is not writable we fall back to the session flag.
 */
function appSchemaMarkerFile(): string
{
    $dir = ROOT_PATH . DIRECTORY_SEPARATOR . 'var';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $version = defined('APP_SCHEMA_VERSION') ? APP_SCHEMA_VERSION : '1';
    $database = defined('DB_NAME') ? (string) DB_NAME : '';
    $suffix = $database !== '' ? '-' . substr(md5($database), 0, 8) : '';
    return $dir . DIRECTORY_SEPARATOR . 'schema-' . preg_replace('/[^0-9A-Za-z._-]/', '-', $version) . $suffix . '.ok';
}

function appSchemaIsCurrent(): bool
{
    return is_file(appSchemaMarkerFile());
}

function appSchemaMarkCurrent(): void
{
    $marker = appSchemaMarkerFile();
    if (!is_file($marker)) {
        @file_put_contents($marker, date('c'));
    }
}

function adminEnsureAdminSuiteTables(PDO $pdo): void
{
    if (!empty($_SESSION['admin_ensured_suite_tables'])) return;

    // Already migrated by an earlier request: skip every DDL statement below.
    if (appSchemaIsCurrent()) {
        $_SESSION['admin_ensured_suite_tables'] = true;
        return;
    }

    adminEnsureSettingsTable($pdo);
    adminEnsureProductAdminColumns($pdo);
    adminEnsureMarketingTables($pdo);
    adminEnsureFlashSalesTable($pdo);
    adminEnsureHomepageGamerReviewsTable($pdo);
    adminEnsureFeedbackTestimonialApprovalsTable($pdo);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_activity (
            id INT AUTO_INCREMENT PRIMARY KEY,
            actor_email VARCHAR(255) DEFAULT NULL,
            action VARCHAR(80) NOT NULL,
            entity_type VARCHAR(80) NOT NULL,
            entity_id INT DEFAULT NULL,
            summary VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_activity_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS coupon_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(40) NOT NULL UNIQUE,
            discount_type ENUM('percent','fixed','shipping') NOT NULL DEFAULT 'percent',
            discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
            min_cart DECIMAL(10,2) NOT NULL DEFAULT 0,
            usage_limit INT DEFAULT NULL,
            used_count INT NOT NULL DEFAULT 0,
            starts_at DATETIME DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stock_restock_plans (
            product_id INT PRIMARY KEY,
            status ENUM('needed','ordered','received') NOT NULL DEFAULT 'needed',
            expected_at DATE DEFAULT NULL,
            notify_waiting TINYINT(1) NOT NULL DEFAULT 0,
            note VARCHAR(255) DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS restock_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            client_id INT DEFAULT NULL,
            notified TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    if (!adminColumnExists($pdo, 'restock_notifications', 'notified_at')) {
        $pdo->exec('ALTER TABLE restock_notifications ADD notified_at DATETIME DEFAULT NULL');
    }

    // ── Visitor Analytics Tables ────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS visitor_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            fingerprint_hash VARCHAR(64) DEFAULT NULL,
            session_id VARCHAR(64) DEFAULT NULL,
            user_agent VARCHAR(512) DEFAULT NULL,
            browser VARCHAR(64) DEFAULT NULL,
            browser_version VARCHAR(32) DEFAULT NULL,
            os VARCHAR(64) DEFAULT NULL,
            os_version VARCHAR(32) DEFAULT NULL,
            device_type VARCHAR(20) DEFAULT 'desktop',
            screen_resolution VARCHAR(20) DEFAULT NULL,
            language VARCHAR(16) DEFAULT NULL,
            referrer VARCHAR(512) DEFAULT NULL,
            page_url VARCHAR(512) DEFAULT NULL,
            country VARCHAR(64) DEFAULT NULL,
            country_code VARCHAR(3) DEFAULT NULL,
            city VARCHAR(128) DEFAULT NULL,
            isp VARCHAR(128) DEFAULT NULL,
            latitude DECIMAL(10,6) DEFAULT NULL,
            longitude DECIMAL(10,6) DEFAULT NULL,
            is_bot TINYINT(1) NOT NULL DEFAULT 0,
            is_proxy TINYINT(1) NOT NULL DEFAULT 0,
            is_vpn TINYINT(1) NOT NULL DEFAULT 0,
            is_tor TINYINT(1) NOT NULL DEFAULT 0,
            is_hosting TINYINT(1) NOT NULL DEFAULT 0,
            network_flags VARCHAR(255) DEFAULT NULL,
            visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_visitor_ip (ip_address),
            INDEX idx_visitor_visited (visited_at),
            INDEX idx_visitor_country (country_code),
            INDEX idx_visitor_session (session_id),
            INDEX idx_visitor_network (is_proxy, is_vpn, is_tor, is_hosting)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $visitorColumns = [
        'is_proxy' => 'ALTER TABLE visitor_logs ADD is_proxy TINYINT(1) NOT NULL DEFAULT 0',
        'is_vpn' => 'ALTER TABLE visitor_logs ADD is_vpn TINYINT(1) NOT NULL DEFAULT 0',
        'is_tor' => 'ALTER TABLE visitor_logs ADD is_tor TINYINT(1) NOT NULL DEFAULT 0',
        'is_hosting' => 'ALTER TABLE visitor_logs ADD is_hosting TINYINT(1) NOT NULL DEFAULT 0',
        'network_flags' => 'ALTER TABLE visitor_logs ADD network_flags VARCHAR(255) DEFAULT NULL',
    ];
    foreach ($visitorColumns as $column => $sql) {
        if (!adminColumnExists($pdo, 'visitor_logs', $column)) {
            $pdo->exec($sql);
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS visitor_log_archive (
            id INT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            fingerprint_hash VARCHAR(64) DEFAULT NULL,
            session_id VARCHAR(64) DEFAULT NULL,
            user_agent VARCHAR(512) DEFAULT NULL,
            browser VARCHAR(64) DEFAULT NULL,
            browser_version VARCHAR(32) DEFAULT NULL,
            os VARCHAR(64) DEFAULT NULL,
            os_version VARCHAR(32) DEFAULT NULL,
            device_type VARCHAR(20) DEFAULT 'desktop',
            screen_resolution VARCHAR(20) DEFAULT NULL,
            language VARCHAR(16) DEFAULT NULL,
            referrer VARCHAR(512) DEFAULT NULL,
            page_url VARCHAR(512) DEFAULT NULL,
            country VARCHAR(64) DEFAULT NULL,
            country_code VARCHAR(3) DEFAULT NULL,
            city VARCHAR(128) DEFAULT NULL,
            isp VARCHAR(128) DEFAULT NULL,
            latitude DECIMAL(10,6) DEFAULT NULL,
            longitude DECIMAL(10,6) DEFAULT NULL,
            is_bot TINYINT(1) NOT NULL DEFAULT 0,
            is_proxy TINYINT(1) NOT NULL DEFAULT 0,
            is_vpn TINYINT(1) NOT NULL DEFAULT 0,
            is_tor TINYINT(1) NOT NULL DEFAULT 0,
            is_hosting TINYINT(1) NOT NULL DEFAULT 0,
            network_flags VARCHAR(255) DEFAULT NULL,
            visited_at TIMESTAMP NULL DEFAULT NULL,
            archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_archive_visited (visited_at),
            INDEX idx_archive_country (country_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS visitor_network_blocks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            block_type ENUM('vpn','proxy','tor','hosting') NOT NULL,
            cidr VARCHAR(64) NOT NULL,
            label VARCHAR(120) DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_network_block (block_type, cidr),
            INDEX idx_network_block_type (block_type, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ip_blocks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL UNIQUE,
            reason VARCHAR(255) DEFAULT NULL,
            blocked_by VARCHAR(255) DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_block_addr (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS country_blocks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            country_code VARCHAR(3) NOT NULL UNIQUE,
            country_name VARCHAR(128) NOT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            blocked_by VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_country_block_code (country_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // GeoIP cache to avoid hitting ip-api rate limits
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS geoip_cache (
            ip_address VARCHAR(45) NOT NULL PRIMARY KEY,
            country VARCHAR(64) DEFAULT NULL,
            country_code VARCHAR(3) DEFAULT NULL,
            city VARCHAR(128) DEFAULT NULL,
            isp VARCHAR(128) DEFAULT NULL,
            latitude DECIMAL(10,6) DEFAULT NULL,
            longitude DECIMAL(10,6) DEFAULT NULL,
            is_proxy TINYINT(1) NOT NULL DEFAULT 0,
            is_hosting TINYINT(1) NOT NULL DEFAULT 0,
            is_mobile TINYINT(1) NOT NULL DEFAULT 0,
            fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $geoColumns = [
        'is_proxy' => 'ALTER TABLE geoip_cache ADD is_proxy TINYINT(1) NOT NULL DEFAULT 0',
        'is_hosting' => 'ALTER TABLE geoip_cache ADD is_hosting TINYINT(1) NOT NULL DEFAULT 0',
        'is_mobile' => 'ALTER TABLE geoip_cache ADD is_mobile TINYINT(1) NOT NULL DEFAULT 0',
    ];
    foreach ($geoColumns as $column => $sql) {
        if (!adminColumnExists($pdo, 'geoip_cache', $column)) {
            $pdo->exec($sql);
        }
    }

    $visitorDefaults = [
        'visitor_archive_enabled' => '1',
        'visitor_archive_after_days' => '30',
        'visitor_archive_prune_days' => '365',
        'visitor_archive_interval_minutes' => '60',
        'visitor_live_max_rows' => '50000',
        'visitor_block_vpn' => '0',
        'visitor_block_proxy' => '0',
        'visitor_block_tor' => '0',
        'visitor_block_hosting' => '0',
    ];
    foreach ($visitorDefaults as $key => $value) {
        if (adminSetting($pdo, $key, null) === null) {
            adminSetSetting($pdo, $key, $value);
        }
    }

    // Laptop AI specs (NPU, Copilot+, AI tier)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS laptop_ai_specs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            laptop_id INT NOT NULL,
            npu_model VARCHAR(100),
            npu_tops DECIMAL(5,1) DEFAULT 0,
            npu_vendor ENUM('Intel','AMD','Qualcomm','Apple','None') DEFAULT 'None',
            is_copilot_plus BOOLEAN DEFAULT FALSE,
            has_windows_studio_effects BOOLEAN DEFAULT FALSE,
            has_live_captions BOOLEAN DEFAULT FALSE,
            has_recall BOOLEAN DEFAULT FALSE,
            has_paint_cocreator BOOLEAN DEFAULT FALSE,
            has_copilot_key BOOLEAN DEFAULT FALSE,
            ai_tier ENUM('none','basic','copilot','workstation') DEFAULT 'none',
            ai_marketing_badge VARCHAR(50),
            ai_feature_highlights TEXT,
            FOREIGN KEY (laptop_id) REFERENCES laptops(id) ON DELETE CASCADE,
            INDEX idx_npu_tops (npu_tops),
            INDEX idx_copilot (is_copilot_plus),
            INDEX idx_ai_tier (ai_tier)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Laptop price history for laptop finder deal alerts.
    // Keep this separate from product_price_history, which is used by product admin pricing.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS laptop_price_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            laptop_id INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            event_type ENUM('regular','sale','flash','release') DEFAULT 'regular',
            FOREIGN KEY (laptop_id) REFERENCES laptops(id) ON DELETE CASCADE,
            INDEX idx_laptop_date (laptop_id, recorded_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Add AI-related columns to laptops table if they don't exist
    $laptopCols = $pdo->query("SHOW COLUMNS FROM laptops")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('category', $laptopCols)) {
        $pdo->exec("ALTER TABLE laptops ADD COLUMN category ENUM('laptop','mini_pc','workstation') DEFAULT 'laptop'");
    }
    if (!in_array('form_factor', $laptopCols)) {
        $pdo->exec("ALTER TABLE laptops ADD COLUMN form_factor VARCHAR(50)");
    }
    if (!in_array('dimensions', $laptopCols)) {
        $pdo->exec("ALTER TABLE laptops ADD COLUMN dimensions VARCHAR(100)");
    }
    if (!in_array('cooling_type', $laptopCols)) {
        $pdo->exec("ALTER TABLE laptops ADD COLUMN cooling_type VARCHAR(50)");
    }
    if (!in_array('max_displays', $laptopCols)) {
        $pdo->exec("ALTER TABLE laptops ADD COLUMN max_displays INT DEFAULT 1");
    }

    if (function_exists('adminEnsureOptimalIndexes')) {
        adminEnsureOptimalIndexes($pdo);
    }

    appSchemaMarkCurrent();
    $_SESSION['admin_ensured_suite_tables'] = true;
}

function adminVisitorArchiveOldLogs(PDO $pdo, bool $force = false): array
{
    adminEnsureSettingsTable($pdo);

    $enabled = (string) adminSetting($pdo, 'visitor_archive_enabled', '1') === '1';
    if (!$enabled && !$force) {
        return ['ran' => false, 'archived' => 0, 'deleted' => 0, 'pruned' => 0, 'message' => adminPhrase('Visitor archival is disabled.')];
    }

    $interval = max(5, (int) adminSetting($pdo, 'visitor_archive_interval_minutes', '60'));
    $lastRun = (string) adminSetting($pdo, 'visitor_archive_last_run', '');
    if (!$force && $lastRun !== '' && strtotime($lastRun) > time() - ($interval * 60)) {
        return ['ran' => false, 'archived' => 0, 'deleted' => 0, 'pruned' => 0, 'message' => adminPhrase('Visitor archival recently ran.')];
    }

    $archiveAfterDays = max(1, min(365, (int) adminSetting($pdo, 'visitor_archive_after_days', '30')));
    $pruneAfterDays = max($archiveAfterDays + 1, min(3650, (int) adminSetting($pdo, 'visitor_archive_prune_days', '365')));
    $maxLiveRows = max(1000, min(1000000, (int) adminSetting($pdo, 'visitor_live_max_rows', '50000')));
    $batchSize = 5000;

    adminSetSetting($pdo, 'visitor_archive_last_run', date('Y-m-d H:i:s'));

    $cutoff = date('Y-m-d H:i:s', strtotime("-{$archiveAfterDays} days"));
    $archiveSql = "
        INSERT IGNORE INTO visitor_log_archive (
            id, ip_address, fingerprint_hash, session_id, user_agent, browser, browser_version,
            os, os_version, device_type, screen_resolution, language, referrer, page_url,
            country, country_code, city, isp, latitude, longitude, is_bot, is_proxy, is_vpn,
            is_tor, is_hosting, network_flags, visited_at
        )
        SELECT
            id, ip_address, fingerprint_hash, session_id, user_agent, browser, browser_version,
            os, os_version, device_type, screen_resolution, language, referrer, page_url,
            country, country_code, city, isp, latitude, longitude, is_bot, is_proxy, is_vpn,
            is_tor, is_hosting, network_flags, visited_at
        FROM visitor_logs
        WHERE visited_at < ?
        ORDER BY visited_at ASC
        LIMIT {$batchSize}
    ";
    $stmt = $pdo->prepare($archiveSql);
    $stmt->execute([$cutoff]);
    $archived = $stmt->rowCount();

    $deleteSql = "DELETE FROM visitor_logs WHERE visited_at < ? ORDER BY visited_at ASC LIMIT {$batchSize}";
    $stmt = $pdo->prepare($deleteSql);
    $stmt->execute([$cutoff]);
    $deleted = $stmt->rowCount();

    $overflowArchived = 0;
    $overflowDeleted = 0;
    $liveRows = (int) adminFetchValue($pdo, 'SELECT COUNT(*) FROM visitor_logs');
    if ($liveRows > $maxLiveRows) {
        $overflowBatch = min($batchSize, $liveRows - $maxLiveRows);
        $overflowSql = "
            INSERT IGNORE INTO visitor_log_archive (
                id, ip_address, fingerprint_hash, session_id, user_agent, browser, browser_version,
                os, os_version, device_type, screen_resolution, language, referrer, page_url,
                country, country_code, city, isp, latitude, longitude, is_bot, is_proxy, is_vpn,
                is_tor, is_hosting, network_flags, visited_at
            )
            SELECT
                id, ip_address, fingerprint_hash, session_id, user_agent, browser, browser_version,
                os, os_version, device_type, screen_resolution, language, referrer, page_url,
                country, country_code, city, isp, latitude, longitude, is_bot, is_proxy, is_vpn,
                is_tor, is_hosting, network_flags, visited_at
            FROM visitor_logs
            ORDER BY visited_at ASC
            LIMIT {$overflowBatch}
        ";
        $stmt = $pdo->prepare($overflowSql);
        $stmt->execute();
        $overflowArchived = $stmt->rowCount();

        $stmt = $pdo->prepare("DELETE FROM visitor_logs ORDER BY visited_at ASC LIMIT {$overflowBatch}");
        $stmt->execute();
        $overflowDeleted = $stmt->rowCount();
    }

    $archiveCutoff = date('Y-m-d H:i:s', strtotime("-{$pruneAfterDays} days"));
    $stmt = $pdo->prepare("DELETE FROM visitor_log_archive WHERE visited_at < ? LIMIT {$batchSize}");
    $stmt->execute([$archiveCutoff]);
    $pruned = $stmt->rowCount();

    return [
        'ran' => true,
        'archived' => (int) ($archived + $overflowArchived),
        'deleted' => (int) ($deleted + $overflowDeleted),
        'pruned' => (int) $pruned,
        'message' => adminPhrase('Archived {count} visit row(s).', ['count' => (int) ($archived + $overflowArchived)]),
    ];
}

function adminLogActivity(PDO $pdo, string $action, string $entityType, ?int $entityId, string $summary): void
{
    try {
        adminEnsureAdminSuiteTables($pdo);
        $actor = trim((string) ($_SESSION['admin_email'] ?? ''));
        $stmt = $pdo->prepare('
            INSERT INTO admin_activity (actor_email, action, entity_type, entity_id, summary)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$actor !== '' ? $actor : null, $action, $entityType, $entityId, substr($summary, 0, 255)]);
    } catch (Throwable $e) {
        // Activity should never block the admin action itself.
    }

    // ── Also log into security_audit_logs for enterprise-grade audit trail ──
    try {
        $adminId = (int) ($_SESSION['admin_id'] ?? 0);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $pdo->prepare('
            INSERT INTO security_audit_logs (admin_id, action, target_table, target_id, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ')->execute([$adminId, $action, $entityType, $entityId ?? 0, $ip, $ua]);
    } catch (Throwable $e) {
        // Never block the admin action.
    }
}

function adminCouponDiscount(PDO $pdo, string $code, float $subtotal): array
{
    adminEnsureAdminSuiteTables($pdo);
    $code = strtoupper(trim($code));
    if ($code === '') {
        return ['valid' => false, 'error' => 'Enter a promo code.'];
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM coupon_codes
        WHERE code = ?
          AND active = 1
          AND (starts_at IS NULL OR starts_at <= NOW())
          AND (expires_at IS NULL OR expires_at >= NOW())
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$coupon) {
        return ['valid' => false, 'error' => 'Invalid or inactive promo code.'];
    }

    if ((float) $coupon['min_cart'] > $subtotal) {
        return ['valid' => false, 'error' => 'Cart total is below the minimum for this code.'];
    }
    if ($coupon['usage_limit'] !== null && (int) $coupon['used_count'] >= (int) $coupon['usage_limit']) {
        return ['valid' => false, 'error' => 'This promo code has reached its usage limit.'];
    }

    $type = (string) $coupon['discount_type'];
    $value = (float) $coupon['discount_value'];
    $discount = 0.0;
    if ($type === 'percent') {
        $discount = round($subtotal * min(100, max(0, $value)) / 100, 2);
    } elseif ($type === 'fixed') {
        $discount = min($subtotal, round(max(0, $value), 2));
    } elseif ($type === 'shipping') {
        $discount = 0.0;
    }

    return [
        'valid' => true,
        'id' => (int) $coupon['id'],
        'code' => $code,
        'type' => $type,
        'value' => $value,
        'discount' => $discount,
        'label' => $type === 'shipping' ? 'Free shipping' : ($type === 'percent' ? rtrim(rtrim((string) $value, '0'), '.') . '% off' : adminMoney($discount) . ' off'),
    ];
}

function adminIncrementCouponUse(PDO $pdo, int $couponId): void
{
    if ($couponId <= 0) {
        return;
    }
    $stmt = $pdo->prepare('UPDATE coupon_codes SET used_count = used_count + 1 WHERE id = ?');
    $stmt->execute([$couponId]);
}

function adminNormalizeRecipientsType(?string $type): string
{
    $type = trim((string) $type);
    $allowed = ['all', 'subscribers', 'everyone', 'high_spenders', 'inactive_30', 'gpu_buyers', 'newsletter_only'];
    return in_array($type, $allowed, true) ? $type : 'all';
}

/**
 * @return list<string>
 */
function adminMarketingRecipientEmails(PDO $pdo, ?string $type): array
{
    adminEnsureMarketingTables($pdo);

    $type = adminNormalizeRecipientsType($type);
    $clientEmails = [];
    $subscriberEmails = [];

    if (($type === 'all' || $type === 'everyone') && adminTableExists($pdo, 'Client')) {
        $stmt = $pdo->query("SELECT email FROM Client WHERE deleted_at IS NULL AND email IS NOT NULL AND TRIM(email) <> ''");
        $clientEmails = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    if (($type === 'subscribers' || $type === 'everyone' || $type === 'newsletter_only') && adminTableExists($pdo, 'newsletter_subscribers')) {
        $stmt = $pdo->query("SELECT email FROM newsletter_subscribers WHERE email IS NOT NULL AND TRIM(email) <> ''");
        $subscriberEmails = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    if ($type === 'high_spenders' && adminTableExists($pdo, 'Client') && adminTableExists($pdo, 'orders')) {
        $clientEmails = adminFetchAll($pdo, "
            SELECT c.email
            FROM Client c
            JOIN orders o ON o.client_id = c.id_client
            WHERE c.deleted_at IS NULL AND c.email IS NOT NULL AND TRIM(c.email) <> ''
            GROUP BY c.id_client, c.email
            HAVING COALESCE(SUM(o.total), 0) >= 10000
        ");
        $clientEmails = array_column($clientEmails, 'email');
    }

    if ($type === 'inactive_30' && adminTableExists($pdo, 'Client')) {
        $clientEmails = adminFetchAll($pdo, "
            SELECT c.email
            FROM Client c
            WHERE c.deleted_at IS NULL
              AND c.email IS NOT NULL
              AND TRIM(c.email) <> ''
              AND NOT EXISTS (
                  SELECT 1 FROM orders o
                  WHERE o.client_id = c.id_client
                    AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              )
        ");
        $clientEmails = array_column($clientEmails, 'email');
    }

    if ($type === 'gpu_buyers' && adminTableExists($pdo, 'Client') && adminTableExists($pdo, 'orders') && adminTableExists($pdo, 'order_items')) {
        $clientEmails = adminFetchAll($pdo, "
            SELECT DISTINCT c.email
            FROM Client c
            JOIN orders o ON o.client_id = c.id_client
            JOIN order_items oi ON oi.order_id = o.id
            LEFT JOIN products p ON p.id = oi.product_id
            WHERE c.deleted_at IS NULL
              AND c.email IS NOT NULL
              AND TRIM(c.email) <> ''
              AND (
                  LOWER(COALESCE(p.category, '')) LIKE '%gpu%'
                  OR LOWER(COALESCE(oi.name_at_time, '')) LIKE '%rtx%'
                  OR LOWER(COALESCE(oi.name_at_time, '')) LIKE '%radeon%'
                  OR LOWER(COALESCE(oi.name_at_time, '')) LIKE '%gpu%'
              )
        ");
        $clientEmails = array_column($clientEmails, 'email');
    }

    if ($type === 'newsletter_only' && adminTableExists($pdo, 'Client')) {
        $clientLookup = adminFetchAll($pdo, "SELECT LOWER(TRIM(email)) AS email FROM Client WHERE email IS NOT NULL AND TRIM(email) <> ''");
        $clientSet = array_flip(array_column($clientLookup, 'email'));
        $subscriberEmails = array_values(array_filter(
            $subscriberEmails,
            static fn($email): bool => !isset($clientSet[strtolower(trim((string) $email))])
        ));
    }

    $emails = array_map(
        static fn($email): string => strtolower(trim((string) $email)),
        array_merge($clientEmails, $subscriberEmails)
    );

    return array_values(array_unique(array_filter($emails)));
}

function adminCustomerSegmentCounts(PDO $pdo): array
{
    $labels = [
        'all' => 'Registered clients',
        'subscribers' => 'Newsletter subscribers',
        'everyone' => 'Everyone unique',
        'high_spenders' => 'High spenders',
        'inactive_30' => 'Inactive 30 days',
        'gpu_buyers' => 'GPU buyers',
        'newsletter_only' => 'Newsletter only',
    ];
    $counts = [];
    foreach ($labels as $key => $label) {
        $counts[$key] = [
            'label' => adminPhrase($label),
            'count' => adminMarketingRecipientCount($pdo, $key),
        ];
    }
    return $counts;
}

function adminMarketingRecipientCount(PDO $pdo, ?string $type): int
{
    return count(adminMarketingRecipientEmails($pdo, $type));
}

function adminDashboardNotifications(PDO $pdo): array
{
    adminEnsureAdminSuiteTables($pdo);
    $items = [];

    $failedEmails = (int) adminFetchValue($pdo, "SELECT COUNT(*) FROM scheduled_emails WHERE status = 'failed'");
    if ($failedEmails > 0) {
        $items[] = ['tone' => 'danger', 'icon' => 'fa-envelope-circle-check', 'title' => adminPhrase('Failed email campaigns'), 'text' => adminPhrase('{count} campaign(s) need review.', ['count' => $failedEmails]), 'href' => 'admin-marketing.php'];
    }

    $out = (int) adminFetchValue($pdo, 'SELECT COUNT(*) FROM products WHERE stock_quantity <= 0 OR in_stock = 0');
    if ($out > 0) {
        $items[] = ['tone' => 'danger', 'icon' => 'fa-box-open', 'title' => adminPhrase('Products out of stock'), 'text' => adminPhrase('{count} catalog item(s) cannot be sold.', ['count' => $out]), 'href' => 'admin-stock.php'];
    }

    $low = (int) adminFetchValue($pdo, 'SELECT COUNT(*) FROM products WHERE stock_quantity > 0 AND stock_quantity <= reorder_level');
    if ($low > 0) {
        $items[] = ['tone' => 'warn', 'icon' => 'fa-triangle-exclamation', 'title' => adminPhrase('Low-stock alerts'), 'text' => adminPhrase('{count} item(s) are at or below reorder level.', ['count' => $low]), 'href' => 'admin-stock.php'];
    }

    $restockRequests = adminTableExists($pdo, 'restock_notifications')
        ? (int) adminFetchValue($pdo, 'SELECT COUNT(*) FROM restock_notifications WHERE notified = 0')
        : 0;
    if ($restockRequests > 0) {
        $items[] = ['tone' => 'info', 'icon' => 'fa-bell', 'title' => adminPhrase('Restock requests'), 'text' => adminPhrase('{count} customer waitlist request(s).', ['count' => $restockRequests]), 'href' => 'admin-stock.php'];
    }

    $newOrders = adminTableExists($pdo, 'orders')
        ? (int) adminFetchValue($pdo, "SELECT COUNT(*) FROM orders WHERE status IN ('pending','processing')")
        : 0;
    if ($newOrders > 0) {
        $items[] = ['tone' => 'info', 'icon' => 'fa-receipt', 'title' => adminPhrase('Orders in progress'), 'text' => adminPhrase('{count} order(s) still need handling.', ['count' => $newOrders]), 'href' => 'admin-orders.php'];
    }

    return $items;
}

function adminSendRestockNotifications(PDO $pdo, int $productId): int
{
    if (!adminTableExists($pdo, 'restock_notifications')) {
        return 0;
    }
    $product = adminFetchAll($pdo, 'SELECT name FROM products WHERE id = ? LIMIT 1', [$productId])[0] ?? null;
    if (!$product) {
        return 0;
    }

    $rows = adminFetchAll($pdo, 'SELECT id, email FROM restock_notifications WHERE product_id = ? AND notified = 0', [$productId]);
    if ($rows === []) {
        return 0;
    }

    require_once dirname(__DIR__, 2) . '/mailer.php';
    $sent = 0;
    foreach ($rows as $row) {
        $body = emailTemplate('Back in stock', '<p><strong>' . adminH($product['name']) . '</strong> is available again at Maroc PC.</p><div class="btn-wrap"><a href="' . APP_URL . 'products.php" class="btn">Shop now</a></div>');
        if (sendEmail((string) $row['email'], 'Back in stock: ' . $product['name'], $body)) {
            $sent++;
            $pdo->prepare('UPDATE restock_notifications SET notified = 1, notified_at = NOW() WHERE id = ?')->execute([(int) $row['id']]);
        }
    }
    return $sent;
}

function adminDataJsPath(): string
{
    return dirname(__DIR__, 2) . '/assets/js/data.js';
}

function adminParseDataJs(): array
{
    $filePath = adminDataJsPath();
    if (!is_file($filePath)) {
        return [];
    }

    $js = (string) file_get_contents($filePath);
    $js = preg_replace('/\/\/[^\n]*/', '', $js);
    $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);

    $start = strpos($js, '[');
    $end = strrpos($js, ']');
    if ($start === false || $end === false || $end <= $start) {
        return [];
    }

    $jsonLike = substr($js, $start, $end - $start + 1);
    $jsonLike = preg_replace('/,\s*([\]}])/', '$1', $jsonLike);
    $jsonLike = preg_replace('/([{,]\s*)([a-zA-Z_][a-zA-Z0-9_ ]*)(\s*:)/', '$1"$2"$3', $jsonLike);
    $jsonLike = str_replace("'", '"', $jsonLike);

    $products = json_decode($jsonLike, true);
    return is_array($products) ? $products : [];
}

function adminImportProductsFromDataJs(PDO $pdo): void
{
    $products = adminParseDataJs();
    if ($products === []) {
        return;
    }

    $stmt = $pdo->prepare('
        INSERT INTO products
            (id, name, brand, category, price, old_price, badge, rating, reviews, image, featured, in_stock, specs, stock_quantity, reorder_level)
        VALUES
            (:id, :name, :brand, :category, :price, :old_price, :badge, :rating, :reviews, :image, :featured, :in_stock, :specs, :stock_quantity, :reorder_level)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            brand = VALUES(brand),
            category = VALUES(category),
            price = VALUES(price),
            old_price = VALUES(old_price),
            badge = VALUES(badge),
            rating = VALUES(rating),
            reviews = VALUES(reviews),
            image = VALUES(image),
            featured = VALUES(featured),
            in_stock = VALUES(in_stock),
            specs = VALUES(specs),
            stock_quantity = VALUES(stock_quantity),
            reorder_level = VALUES(reorder_level)
    ');

    foreach ($products as $product) {
        if (empty($product['id']) || empty($product['name'])) {
            continue;
        }

        $inStock = !empty($product['inStock']);
        $stmt->execute([
            'id' => (int) $product['id'],
            'name' => (string) $product['name'],
            'brand' => (string) ($product['brand'] ?? ''),
            'category' => (string) ($product['category'] ?? ''),
            'price' => (float) ($product['price'] ?? 0),
            'old_price' => isset($product['oldPrice']) ? (float) $product['oldPrice'] : null,
            'badge' => $product['badge'] ?? null,
            'rating' => isset($product['rating']) ? (float) $product['rating'] : null,
            'reviews' => (int) ($product['reviews'] ?? 0),
            'image' => $product['image'] ?? null,
            'featured' => !empty($product['featured']) ? 1 : 0,
            'in_stock' => $inStock ? 1 : 0,
            'specs' => json_encode($product['specs'] ?? [], JSON_UNESCAPED_SLASHES),
            'stock_quantity' => $inStock ? 10 : 0,
            'reorder_level' => 5,
        ]);
    }
}

function adminSyncMissingStockFromAvailability(PDO $pdo, int $defaultQuantity = 10): int
{
    adminEnsureProductAdminColumns($pdo);

    $stmt = $pdo->prepare('
        UPDATE products
        SET stock_quantity = :default_quantity
        WHERE in_stock = 1 AND stock_quantity <= 0
    ');
    $stmt->execute(['default_quantity' => max(1, $defaultQuantity)]);

    return $stmt->rowCount();
}

function adminExportProductsToDataJs(PDO $pdo): void
{
    $stmt = $pdo->query('
        SELECT id, name, brand, category, price, old_price, badge, rating, reviews, image, featured, in_stock, specs, stock_quantity
        FROM products
        ORDER BY id ASC
    ');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $products = [];
    foreach ($rows as $row) {
        $specs = [];
        if (!empty($row['specs'])) {
            $decodedSpecs = json_decode((string) $row['specs'], true);
            $specs = is_array($decodedSpecs) ? $decodedSpecs : [];
        }

        $product = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'brand' => (string) $row['brand'],
            'category' => (string) $row['category'],
            'price' => (float) $row['price'],
        ];

        if ($row['old_price'] !== null && (float) $row['old_price'] > 0) {
            $product['oldPrice'] = (float) $row['old_price'];
        }

        if (!empty($row['badge'])) {
            $product['badge'] = (string) $row['badge'];
        }

        $product['rating'] = $row['rating'] !== null ? (float) $row['rating'] : 0;
        $product['reviews'] = (int) $row['reviews'];
        $product['image'] = (string) ($row['image'] ?? '');
        $product['featured'] = !empty($row['featured']);
        $product['inStock'] = !empty($row['in_stock']) && (int) $row['stock_quantity'] > 0;
        $product['specs'] = $specs;

        $products[] = $product;
    }

    $json = json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Unable to encode products for data.js.');
    }

    $content = "/**\n";
    $content .= " * data.js - Single source of truth for all product data.\n";
    $content .= " * This file is updated by the admin dashboard product tools.\n";
    $content .= " */\n";
    $content .= "const products = " . $json . ";\n";

    if (file_put_contents(adminDataJsPath(), $content, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write assets/js/data.js.');
    }
}

function adminStockBadgeClass(int $stock, int $reorderLevel): string
{
    if ($stock <= 0) {
        return 'is-danger';
    }

    if ($stock <= $reorderLevel) {
        return 'is-warn';
    }

    return 'is-good';
}

function adminPageStart(string $title, string $active): void
{
    require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'i18n.php';
    i18n_start_page_translation();
    $pageTitle = adminPhrase($title);

    $pendingOrderCount = 0;
    $sidebarCounts = [
        'products' => 0,
        'laptops' => 0,
        'stock' => 0,
        'procurement' => 0,
        'orders' => 0,
        'diagnostics' => 0,
    ];
    try {
        $pdo = db();
        // Sidebar badges are identical on every admin page, so compute them once
        // per session instead of on each navigation.
        $loadSidebarCounts = function () use ($pdo): array {
            return [
                'pending' => (int) adminFetchValue($pdo, "SELECT COUNT(*) FROM `Order` WHERE status = 'pending'"),
                'products' => (int) adminFetchValue($pdo, 'SELECT COUNT(*) FROM products'),
                'laptops' => (int) adminFetchValue($pdo, 'SELECT COUNT(*) FROM laptops'),
                'stock' => (int) adminFetchValue($pdo, 'SELECT COUNT(*) FROM products WHERE stock_quantity <= reorder_level OR stock_quantity <= 0 OR in_stock = 0'),
                'procurement' => (int) adminFetchValue($pdo, 'SELECT COUNT(*) FROM supplier_orders WHERE status IN ("pending","ordered","partial")'),
                'orders' => (int) adminFetchValue($pdo, "SELECT COUNT(*) FROM `Order` WHERE status IN ('pending','processing')"),
                'diagnostics' => (int) adminFetchValue($pdo, 'SELECT COUNT(*) FROM support_requests WHERE status IN ("new","open","pending")'),
            ];
        };
        $sidebarData = function_exists('adminCachedFetch')
            ? adminCachedFetch($pdo, 'admin_sidebar_counts', $loadSidebarCounts, 300)
            : $loadSidebarCounts();

        $pendingOrderCount = (int) ($sidebarData['pending'] ?? 0);
        $sidebarCounts['products'] = (int) ($sidebarData['products'] ?? 0);
        $sidebarCounts['laptops'] = (int) ($sidebarData['laptops'] ?? 0);
        $sidebarCounts['stock'] = (int) ($sidebarData['stock'] ?? 0);
        $sidebarCounts['procurement'] = (int) ($sidebarData['procurement'] ?? 0);
        $sidebarCounts['orders'] = (int) ($sidebarData['orders'] ?? 0);
        $sidebarCounts['diagnostics'] = (int) ($sidebarData['diagnostics'] ?? 0);
    } catch (Exception $e) {
        $pendingOrderCount = 0;
    }

    $links = [
        'dashboard' => ['dashboard.php', 'fa-shield-halved', i18n_t('admin.dashboard', [], 'Dashboard')],
        'products' => ['admin-products.php', 'fa-box', i18n_t('nav.components')],
        'laptops' => ['admin-laptops.php', 'fa-laptop', adminPhrase('Laptops')],
        'stock' => ['admin-stock.php', 'fa-chart-simple', i18n_t('admin.stock', [], 'Stock')],
        'procurement' => ['admin-procurement.php', 'fa-truck-ramp-box', i18n_t('admin.procurement', [], 'Procurement')],
        'orders' => ['admin-orders.php', 'fa-receipt', i18n_t('account.orders', [], 'Orders')],
        'diagnostics' => ['admin-diagnostics.php', 'fa-screwdriver-wrench', i18n_t('admin.diagnostics', [], 'Diagnostics')],
        'customers' => ['admin-customers.php', 'fa-users', i18n_t('admin.customers', [], 'Customers')],
        'feedback' => ['admin-feedback.php', 'fa-comment-dots', i18n_t('admin.feedback', [], 'Feedback')],
        'analytics' => ['admin-analytics.php', 'fa-chart-pie', i18n_t('admin.analytics', [], 'Analytics')],
        'marketing' => ['admin-marketing.php', 'fa-bullhorn', i18n_t('admin.marketing', [], 'Marketing')],
        'coupons' => ['admin-coupons.php', 'fa-ticket', i18n_t('admin.coupons', [], 'Coupons')],
        'reviews' => ['admin-reviews.php', 'fa-star', i18n_t('admin.reviews', [], 'Reviews')],
        'requests' => ['admin-requests.php', 'fa-inbox', i18n_t('admin.requests', [], 'Requests')],
        'chatbot' => ['admin-chatbot-feedback.php', 'fa-robot', adminPhrase('Chatbot Logs')],
        'visitors' => ['admin-visitors.php', 'fa-eye', i18n_t('admin.visitors', [], 'Visitors')],
        'store' => [i18n_url('index.php'), 'fa-store', adminPhrase('Storefront')],
        'logout' => ['logout.php', 'fa-sign-out-alt', i18n_t('auth.logout')],
    ];
    ?>
<!DOCTYPE html>
<html lang="<?= adminH(i18n_current_locale()) ?>" dir="<?= adminH(i18n_direction()) ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= adminH($pageTitle) ?> - Maroc PC</title>
    
    <!-- Optimized Font Loading -->
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;800&family=Syne:wght@400;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    
    <!-- Critical CSS Inline for Fast First Paint -->
    <style>
        :root{--page-bg:#0a0e17;--page-bg-2:#0f1419;--card-bg:#1a1f2e;--text:#e0e6ed;--muted:#8b92a0;--border:rgba(255,255,255,.08);--cyan:#00f5d4;--red:#ff3d5a;--input-bg:#13181f;--white:#ffffff}
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:var(--page-bg);color:var(--text);font-family:system-ui,-apple-system,sans-serif;line-height:1.6;min-height:100vh}
        .admin-body{display:flex;flex-direction:column}
        .header{background:var(--card-bg);border-bottom:1px solid var(--border);padding:1rem;position:sticky;top:0;z-index:100}
        .nav-container{display:flex;align-items:center;gap:2rem;max-width:1400px;margin:0 auto}
        .table-card{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:24px}
        .button{padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:600;transition:.3s}
        .button-primary{background:var(--cyan);color:#000}
        .loading-skeleton{background:linear-gradient(90deg,var(--card-bg) 25%,rgba(255,255,255,.05) 50%,var(--card-bg) 75%);background-size:200% 100%;animation:loading 1.5s infinite}
        @keyframes loading{0%{background-position:200% 0}100%{background-position:-200% 0}}
    </style>
    
    <!-- Non-Critical CSS Deferred -->
    <link rel="stylesheet" href="assets/css/admin-optimized.css">
    <link rel="stylesheet" href="assets/css/admin-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/admin-visitors-enhanced.css">
    <link rel="stylesheet" href="assets/css/admin-loading.css">
    <link rel="stylesheet" href="assets/css/account.css" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="assets/css/dashboard.css" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="assets/css/dashboard-premium.css" media="print" onload="this.media='all'">
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'dark');</script>
    <link rel="stylesheet" href="assets/css/light-mode-industrial.css" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="assets/css/admin-mobile-2026.css">

    <script src="assets/js/admin-performance.js" defer></script>
    <script src="assets/js/admin-infinite-scroll.js" defer></script>
    <script src="assets/js/admin-common.js?v=i18n-fix-4" defer></script>
    
    <!-- Performance Monitoring (No UI) -->
    <script>
    (function() {
        var start = performance.now();
        window.addEventListener('load', function() {
            var loadTime = Math.round(performance.now() - start);
            console.log('Admin Page Load: ' + loadTime + 'ms');
            // Send to analytics if needed
            if (typeof window.gtag === 'function') {
                window.gtag('event', 'timing_complete', {
                    name: 'admin_load',
                    value: loadTime
                });
            }
        });
    })();
    </script>
    <script>
    window.adminI18n = {
        shortcuts: {
            open_command_palette: <?= json_encode(adminPhrase('Open command palette'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            show_keyboard_shortcuts: <?= json_encode(adminPhrase('Show keyboard shortcuts'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            keyboard_shortcuts: <?= json_encode(adminPhrase('Keyboard Shortcuts'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            go_to_dashboard: <?= json_encode(adminPhrase('Go to Dashboard'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            go_to_orders: <?= json_encode(adminPhrase('Go to Orders'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            go_to_stock: <?= json_encode(adminPhrase('Go to Stock'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            go_to_customers: <?= json_encode(adminPhrase('Go to Customers'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            go_to_analytics: <?= json_encode(adminPhrase('Go to Analytics'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            toggle_dark_light: <?= json_encode(adminPhrase('Toggle dark / light mode'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            close: <?= json_encode(adminPhrase('Close'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        },
        general: {
            network_error: <?= json_encode(adminPhrase('Network error'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            error: <?= json_encode(adminPhrase('Error'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            confirm_delete_flash_sale: <?= json_encode(adminPhrase('Delete this flash sale?'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            processing: <?= json_encode(adminPhrase('Processing...'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            syncing: <?= json_encode(adminPhrase('Syncing...'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            failed_snapshot: <?= json_encode(adminPhrase('Failed to capture snapshot'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            failed_sync: <?= json_encode(adminPhrase('Failed to sync tiers'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            err_capturing_snapshot: <?= json_encode(adminPhrase('while capturing snapshot'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            err_syncing_tiers: <?= json_encode(adminPhrase('while syncing tiers'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        },
        infiniteScroll: {
            loading: <?= json_encode(adminPhrase('Loading more...'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            allLoaded: <?= json_encode(adminPhrase('All items loaded'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            loadFailed: <?= json_encode(adminPhrase('Failed to load more.'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            refreshPage: <?= json_encode(adminPhrase('Refresh page'), JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        }
    };
    </script>
</head>
    <body class="admin-body">
    <div class="admin-app-shell">
    <header class="header admin-topbar">
        <div class="nav-container admin-topbar-inner">
            <div class="admin-topbar-branding">
                <a href="<?= adminH(i18n_url('index.php')) ?>" class="logo admin-logo-lockup">
                    <img src="logo.png" alt="Maroc PC Logo" class="nav-logo">
                    <span class="admin-brand-copy">
                        <strong><?= adminH(i18n_t('admin.brand_control', [], adminPhrase('Maroc PC Control'))) ?></strong>
                        <small><?= adminH(i18n_t('admin.premium_console', [], adminPhrase('Premium operations console'))) ?></small>
                    </span>
                </a>
                <nav class="nav admin-utility-nav">
                    <a href="dashboard.php" class="nav-link <?= $active === 'dashboard' ? 'active' : '' ?>"><?= adminH(i18n_t('admin.admin', [], 'Admin')) ?></a>
                    <a href="products.php" class="nav-link"><?= adminH(i18n_t('admin.store_products', [], 'Store Products')) ?></a>
                </nav>
            </div>
            <div class="nav-spacer"></div>
            <div class="admin-topbar-actions">
                <div class="admin-topbar-badge">
                    <span class="admin-topbar-badge-label"><?= adminH(i18n_t('admin.pending_orders', [], adminPhrase('Pending orders'))) ?></span>
                    <strong><?= $pendingOrderCount ?></strong>
                </div>
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                    <i class="fas fa-sun icon-sun"></i>
                    <i class="fas fa-moon icon-moon"></i>
                </button>
                <?= i18n_language_switcher('nav-translate') ?>
            </div>

        </div>
    </header>

    <!-- Mobile Quick-Nav Strip (visible only on ≤1080px, sits right after topbar) -->
    <nav class="admin-mobile-strip" aria-label="<?= adminH(i18n_t('admin.admin_navigation', [], adminPhrase('Admin navigation'))) ?>">
        <?php foreach ($links as $key => [$href, $icon, $label]): ?>
            <?php
                $count = $sidebarCounts[$key] ?? null;
                $isAlert = in_array($key, ['stock','orders','diagnostics'], true);
            ?>
            <?php if ($key === 'store' || $key === 'logout'): ?>
                <div class="admin-mobile-strip-divider"></div>
            <?php endif; ?>
            <a href="<?= adminH($href) ?>"
               class="admin-mobile-strip-item<?= $active === $key ? ' active' : '' ?>"
               title="<?= adminH($label) ?>">
                <i class="fas <?= adminH($icon) ?>"></i>
                <span class="strip-label"><?= adminH($label) ?></span>
                <?php if ($count !== null && $count > 0): ?>
                    <span class="admin-mobile-strip-badge<?= $isAlert ? ' strip-badge-alert' : '' ?>"><?= $count ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <section class="account-page dashboard-page admin-workspace-page">
        <div class="container admin-workspace-container">
            <div class="account-layout admin-workspace-layout">
                <aside class="account-sidebar admin-sidebar-premium">
                    <div class="admin-sidebar-intro">
                        <span class="eyebrow"><?= adminH(adminPhrase('Operations')) ?></span>
                        <h2><?= adminH($pageTitle) ?></h2>
                        <p><?= adminH(i18n_t('admin.workspace_intro', [], adminPhrase('Move between commerce, catalog, logistics, and customer workflows from one premium control surface.'))) ?></p>
                    </div>
                    <nav class="admin-sidebar-nav" aria-label="<?= adminH(i18n_t('admin.admin_navigation', [], adminPhrase('Admin navigation'))) ?>">
                    <?php foreach ($links as $key => [$href, $icon, $label]): ?>
                        <?php $count = $sidebarCounts[$key] ?? null; ?>
                        <a href="<?= adminH($href) ?>" class="<?= $active === $key ? 'active' : '' ?> <?= $count !== null && $count > 0 ? 'has-badge' : '' ?>" title="<?= adminH($label) ?>">
                            <i class="fas <?= adminH($icon) ?>"></i>
                            <span><?= adminH($label) ?></span>
                            <?php if ($count !== null && $count > 0): ?>
                                <span class="sidebar-badge <?= in_array($key, ['stock', 'orders', 'diagnostics'], true) ? 'sidebar-badge-alert' : '' ?>"><?= $count ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                    </nav>
                </aside>
                <div class="account-content dashboard-shell admin-content-premium">
                    <div class="admin-shortcuts-bar admin-command-strip">
                        <span><kbd>Ctrl</kbd> + <kbd>K</kbd> <?= adminH(adminPhrase('Command Palette')) ?></span>
                        <span><kbd>Shift</kbd> + <kbd>?</kbd> <?= adminH(adminPhrase('Shortcuts')) ?></span>
                        <span><kbd>Ctrl</kbd> + <kbd>1&ndash;5</kbd> <?= adminH(adminPhrase('Quick Nav')) ?></span>
                        <span><kbd>Esc</kbd> <?= adminH(adminPhrase('Close')) ?></span>
                    </div>
                    <main class="admin-page-stack">

    <?php
}

function adminPageEnd(): void
{
    ?>
                    </main>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container" style="text-align:center;">
            <p style="color: var(--muted); font-size: 0.82rem;">&copy; 2026 Maroc PC &mdash; <?= adminH(adminPhrase('All rights reserved.')) ?></p>
        </div>
    </footer>

    <?= i18n_language_switcher_assets() ?>
    <script src="assets/js/theme.js"></script>
</body>
</html>
    <?php
}
