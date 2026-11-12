
<?php
require_once 'admin-helpers.php';
require_once __DIR__ . '/includes/i18n.php';
i18n_start_page_translation();

adminRequireAuth();

$pdo = db();
adminEnsureAdminSuiteTables($pdo);

$adminName = trim((string) ($_SESSION['admin_nom'] ?? 'Administrator'));
$adminEmail = trim((string) ($_SESSION['admin_email'] ?? ''));
$adminDisplayName = $adminName === 'Administrator' || $adminName === 'System Administrator'
    ? adminPhrase($adminName)
    : $adminName;
$hasOrders = adminTableExistsCached($pdo, 'orders');
$hasClients = adminTableExistsCached($pdo, 'Client');

// Enhanced statistics with trends and comparisons.
// Three aggregate queries replace the previous ~18 individual round trips.
$stats = [
    'products' => 0,
    'laptops' => 0,
    'stock' => 0,
    'stock_value' => 0.0,
    'alerts' => 0,
    'out_of_stock' => 0,
    'customers' => 0,
    'customers_week' => 0,
    'customers_month' => 0,
    'orders' => 0,
    'orders_today' => 0,
    'orders_week' => 0,
    'orders_month' => 0,
    'pending' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'revenue' => 0.0,
    'revenue_today' => 0.0,
    'revenue_week' => 0.0,
    'revenue_month' => 0.0,
    'revenue_last_month' => 0.0,
    'avg_order' => 0.0,
    'conversion_rate' => 0.0,
    'fulfillment_rate' => 0.0,
    'revenue_growth' => 0.0,
];

$inventoryRow = adminFetchAll($pdo, '
    SELECT
        (SELECT COUNT(*) FROM products) AS products,
        (SELECT COUNT(*) FROM laptops) AS laptops,
        (SELECT COALESCE(SUM(stock_quantity), 0) FROM products)
            + (SELECT COALESCE(SUM(stock_quantity), 0) FROM laptops) AS stock,
        (SELECT COALESCE(SUM(stock_quantity * price), 0) FROM products)
            + (SELECT COALESCE(SUM(stock_quantity * price), 0) FROM laptops) AS stock_value,
        (SELECT COUNT(*) FROM products WHERE stock_quantity <= reorder_level)
            + (SELECT COUNT(*) FROM laptops WHERE stock_quantity <= reorder_level) AS alerts,
        (SELECT COUNT(*) FROM products WHERE stock_quantity <= 0 OR in_stock = 0)
            + (SELECT COUNT(*) FROM laptops WHERE stock_quantity <= 0) AS out_of_stock
');
if ($inventoryRow) {
    $row = $inventoryRow[0];
    $stats['products'] = (int) $row['products'];
    $stats['laptops'] = (int) $row['laptops'];
    $stats['stock'] = (int) $row['stock'];
    $stats['stock_value'] = (float) $row['stock_value'];
    $stats['alerts'] = (int) $row['alerts'];
    $stats['out_of_stock'] = (int) $row['out_of_stock'];
}

if ($hasClients) {
    $row = adminFetchAll($pdo, '
        SELECT
            COUNT(*) AS customers,
            COALESCE(SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)), 0) AS customers_week,
            COALESCE(SUM(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS customers_month
        FROM Client
    ')[0] ?? [];
    $stats['customers'] = (int) ($row['customers'] ?? 0);
    $stats['customers_week'] = (int) ($row['customers_week'] ?? 0);
    $stats['customers_month'] = (int) ($row['customers_month'] ?? 0);
}

if ($hasOrders) {
    $row = adminFetchAll($pdo, "
        SELECT
            COUNT(*) AS orders,
            COALESCE(SUM(DATE(created_at) = CURDATE()), 0) AS orders_today,
            COALESCE(SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)), 0) AS orders_week,
            COALESCE(SUM(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS orders_month,
            COALESCE(SUM(status IN ('pending', 'processing')), 0) AS pending,
            COALESCE(SUM(status = 'delivered'), 0) AS completed,
            COALESCE(SUM(status = 'cancelled'), 0) AS cancelled,
            COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total ELSE 0 END), 0) AS revenue,
            COALESCE(SUM(CASE WHEN status != 'cancelled' AND DATE(created_at) = CURDATE() THEN total ELSE 0 END), 0) AS revenue_today,
            COALESCE(SUM(CASE WHEN status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN total ELSE 0 END), 0) AS revenue_week,
            COALESCE(SUM(CASE WHEN status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN total ELSE 0 END), 0) AS revenue_month,
            COALESCE(SUM(CASE WHEN status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN total ELSE 0 END), 0) AS revenue_last_month,
            COALESCE(AVG(CASE WHEN status != 'cancelled' THEN total END), 0) AS avg_order
        FROM orders
    ")[0] ?? [];
    foreach (['orders', 'orders_today', 'orders_week', 'orders_month', 'pending', 'completed', 'cancelled'] as $key) {
        $stats[$key] = (int) ($row[$key] ?? 0);
    }
    foreach (['revenue', 'revenue_today', 'revenue_week', 'revenue_month', 'revenue_last_month', 'avg_order'] as $key) {
        $stats[$key] = (float) ($row[$key] ?? 0);
    }
}

// Calculate conversion rate (orders / customers * 100)
if ($stats['customers'] > 0) {
    $stats['conversion_rate'] = round(($stats['orders'] / $stats['customers']) * 100, 1);
}

// Calculate fulfillment rate (completed / total orders * 100)
if ($stats['orders'] > 0) {
    $stats['fulfillment_rate'] = round(($stats['completed'] / $stats['orders']) * 100, 1);
}

// Calculate revenue growth (month over month)
$stats['revenue_growth'] = 0.0;
if ($stats['revenue_last_month'] > 0) {
    $stats['revenue_growth'] = round((($stats['revenue_month'] - $stats['revenue_last_month']) / $stats['revenue_last_month']) * 100, 1);
}

// Flash sales data
$hasFlashSales = adminTableExistsCached($pdo, 'flash_sales');
$flashSales = [];
$activeFlashCount = 0;
if ($hasFlashSales) {
    $flashSales = adminFetchAll($pdo, "
        SELECT fs.*, p.name AS product_name, p.price AS current_price, p.image AS product_image
        FROM flash_sales fs
        JOIN products p ON p.id = fs.product_id
        ORDER BY fs.ends_at DESC
    ");
    $activeFlashCount = 0;
    $now = time();
    foreach ($flashSales as $sale) {
        $starts = !empty($sale['starts_at']) ? strtotime((string) $sale['starts_at']) : null;
        $ends = !empty($sale['ends_at']) ? strtotime((string) $sale['ends_at']) : null;
        if (($starts === null || $starts <= $now) && ($ends === null || $ends > $now)) {
            $activeFlashCount++;
        }
    }
}

$allProducts = adminFetchAll($pdo, 'SELECT id, name, price, brand FROM products ORDER BY name ASC');

$recentProducts = adminFetchAll($pdo, '
    SELECT id, name, brand, category, price, stock_quantity, reorder_level, in_stock, created_at
    FROM products
    ORDER BY created_at DESC, id DESC
    LIMIT 6
');

$lowStockProducts = adminFetchAll($pdo, '
    SELECT id, name, category, stock_quantity, reorder_level
    FROM products
    WHERE stock_quantity <= reorder_level
    ORDER BY stock_quantity ASC, name ASC
    LIMIT 8
');

$mostWantedRestocks = adminFetchAll($pdo, '
    SELECT p.id, p.name, p.category, p.stock_quantity, COUNT(rn.id) as subscriber_count
    FROM restock_notifications rn
    JOIN products p ON p.id = rn.product_id
    WHERE rn.notified = 0 AND p.stock_quantity <= 0
    GROUP BY p.id, p.name, p.category, p.stock_quantity
    ORDER BY subscriber_count DESC
    LIMIT 5
');

$recentOrders = ($hasOrders && $hasClients)
    ? adminFetchAll($pdo, '
        SELECT o.id, o.status, o.total, o.payment_status, o.created_at, c.nom AS client_name
        FROM orders o
        LEFT JOIN Client c ON c.id_client = o.client_id
        ORDER BY o.created_at DESC, o.id DESC
        LIMIT 8
    ')
    : [];

$notifications = adminDashboardNotifications($pdo);
$recentActivity = adminFetchAll($pdo, '
    SELECT actor_email, action, entity_type, entity_id, summary, created_at
    FROM admin_activity
    ORDER BY created_at DESC, id DESC
    LIMIT 8
');



adminPageStart('Admin Dashboard', 'dashboard');
?>
<section class="section-heading">
    <div>
        <span class="eyebrow"><?= adminH(adminPhrase('Administration')) ?></span>
        <h1><?= adminH(adminPhrase('Admin Dashboard')) ?></h1>
        <p class="section-copy"><?= adminH(adminPhrase('Welcome back, {name}. Manage catalog data, watch stock pressure, and follow customer orders from one focused workspace.', ['name' => $adminDisplayName])) ?></p>
    </div>
    <div class="heading-actions">
        <a class="button button-light" href="admin-orders.php"><i class="fas fa-receipt"></i> <?= adminH(adminPhrase('Orders')) ?></a>
        <a class="button button-light" href="admin-stock.php"><i class="fas fa-chart-simple"></i> <?= adminH(adminPhrase('Stock')) ?></a>
        <a class="button button-primary" href="admin-product-form.php"><i class="fas fa-plus"></i> <?= adminH(adminPhrase('Add Product')) ?></a>
    </div>
</section>

<section class="section-heading admin-ops-banner">
    <div class="admin-ops-banner-copy">
        <span class="eyebrow"><?= adminH(i18n_t('admin.system_maintenance', [], adminPhrase('System Maintenance'))) ?></span>
        <h2><?= adminH(i18n_t('admin.manual_background_ops', [], adminPhrase('Manual control for background operations'))) ?></h2>
        <p class="section-copy"><?= adminH(i18n_t('admin.manual_background_ops_desc', [], adminPhrase('Use these actions when you need a supervised checkpoint for price history capture or loyalty tier synchronization.'))) ?></p>
    </div>
    <div class="heading-actions admin-ops-actions">
        <button onclick="triggerSnapshot()" id="snapshotBtn" class="button button-light button-small">
            <i class="fas fa-camera"></i> <?= adminH(adminPhrase('Capture Price History')) ?>
        </button>
        <button onclick="recalculateTiers()" id="tiersBtn" class="button button-light button-small">
            <i class="fas fa-sync"></i> <?= adminH(adminPhrase('Sync Loyalty Tiers')) ?>
        </button>
    </div>
</section>

<script>
async function triggerSnapshot() {
    const btn = document.getElementById('snapshotBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (window.adminI18n?.general?.processing || 'Processing...');
    
    try {
        const res = await fetch('api/admin-maintenance.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'snapshot_price_history' })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || data.error || 'Request failed');
        alert(data.success ? data.message : (data.error || (window.adminI18n?.general?.failed_snapshot || 'Failed to capture snapshot')));
    } catch (e) {
        alert((e.message || (window.adminI18n?.general?.network_error || 'Network error')) + ' ' + (window.adminI18n?.general?.err_capturing_snapshot || 'while capturing snapshot'));
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

async function recalculateTiers() {
    const btn = document.getElementById('tiersBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (window.adminI18n?.general?.syncing || 'Syncing...');
    
    try {
        const res = await fetch('api/admin-maintenance.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'sync_loyalty_tiers' })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || data.error || 'Request failed');
        alert(data.success ? data.message : (data.error || (window.adminI18n?.general?.failed_sync || 'Failed to sync tiers')));
    } catch (e) {
        alert((e.message || (window.adminI18n?.general?.network_error || 'Network error')) + ' ' + (window.adminI18n?.general?.err_syncing_tiers || 'while syncing tiers'));
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}
</script>

<div class="admin-profile-bar admin-identity-card">
    <div class="profile-mark admin-identity-mark">
        <span><?= adminH(substr($adminName, 0, 1)) ?></span>
    </div>
    <div class="admin-identity-copy">
        <span class="eyebrow"><?= adminH(i18n_t('admin.control_session', [], adminPhrase('Control Session'))) ?></span>
        <strong><?= adminH($adminDisplayName) ?></strong>
        <small><?= adminH($adminEmail ?: adminPhrase('Administrator session active')) ?></small>
    </div>
    <div class="admin-identity-meta">
        <span><?= adminH(i18n_t('admin.role', [], adminPhrase('Role'))) ?></span>
        <strong><?= adminH(i18n_t('admin.system_administrator', [], adminPhrase('System Administrator'))) ?></strong>
    </div>
</div>

<?php if ($notifications !== []): ?>
<section class="notification-grid">
    <?php foreach ($notifications as $note): ?>
        <a class="notification-card <?= adminH($note['tone']) ?>" href="<?= adminH($note['href']) ?>">
            <i class="fas <?= adminH($note['icon']) ?>"></i>
            <span>
                <strong><?= adminH($note['title']) ?></strong>
                <small><?= adminH($note['text']) ?></small>
            </span>
        </a>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- Primary Statistics Grid -->
<section class="stats-section">
    <div class="stats-section-header">
        <h2><i class="fas fa-chart-line"></i> <?= adminH(adminPhrase('Key Performance Indicators')) ?></h2>
        <span class="stats-period"><?= adminH(adminPhrase('Real-time metrics')) ?></span>
    </div>
    
    <div class="stats-grid primary-stats">
        <!-- Revenue Card -->
        <article class="stat-card stat-card-featured" data-stat="revenue">
            <div class="stat-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value"><?= adminMoney($stats['revenue']) ?></strong>
                <span class="stat-label"><?= adminH(adminPhrase('Total Revenue')) ?></span>
                <div class="stat-meta">
                    <span class="stat-trend <?= $stats['revenue_growth'] >= 0 ? 'trend-up' : 'trend-down' ?>">
                        <i class="fas fa-<?= $stats['revenue_growth'] >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                        <?= abs($stats['revenue_growth']) ?>%
                    </span>
                    <span class="stat-subtext"><?= adminH(adminPhrase('vs last month')) ?></span>
                </div>
            </div>
        </article>

        <!-- Orders Card -->
        <article class="stat-card" data-stat="orders">
            <div class="stat-icon stat-icon-neutral">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value"><?= number_format($stats['orders']) ?></strong>
                <span class="stat-label"><?= adminH(adminPhrase('Total Orders')) ?></span>
                <div class="stat-meta">
                    <span class="stat-badge badge-info"><?= adminH(adminPhrase('{count} today', ['count' => $stats['orders_today']])) ?></span>
                    <span class="stat-subtext"><?= adminH(adminPhrase('{count} this week', ['count' => $stats['orders_week']])) ?></span>
                </div>
            </div>
        </article>

        <!-- Customers Card -->
        <article class="stat-card" data-stat="customers">
            <div class="stat-icon stat-icon-neutral">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value"><?= number_format($stats['customers']) ?></strong>
                <span class="stat-label"><?= adminH(adminPhrase('Total Customers')) ?></span>
                <div class="stat-meta">
                    <span class="stat-badge badge-success"><?= adminH(adminPhrase('+{count} this week', ['count' => $stats['customers_week']])) ?></span>
                    <span class="stat-subtext"><?= adminH(adminPhrase('{count} this month', ['count' => $stats['customers_month']])) ?></span>
                </div>
            </div>
        </article>

        <!-- Stock Value Card -->
        <article class="stat-card" data-stat="stock-value">
            <div class="stat-icon stat-icon-neutral">
                <i class="fas fa-warehouse"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value"><?= adminMoney($stats['stock_value']) ?></strong>
                <span class="stat-label"><?= adminH(adminPhrase('Inventory Value')) ?></span>
                <div class="stat-meta">
                    <span class="stat-badge badge-neutral"><?= adminH(adminPhrase('{count} units', ['count' => number_format($stats['stock'])])) ?></span>
                    <span class="stat-subtext"><?= adminH(adminPhrase('{count} products', ['count' => $stats['products'] + $stats['laptops']])) ?></span>
                </div>
            </div>
        </article>
    </div>
</section>

<!-- Secondary Statistics Grid -->
<section class="stats-section">
    <div class="stats-section-header">
        <h2><i class="fas fa-tachometer-alt"></i> <?= adminH(adminPhrase('Operations Overview')) ?></h2>
    </div>
    
    <div class="stats-grid secondary-stats">
        <!-- Average Order Value -->
        <article class="stat-card stat-card-compact">
            <div class="stat-icon-small">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value"><?= adminMoney($stats['avg_order']) ?></strong>
                <span class="stat-label"><?= adminH(adminPhrase('Avg Order Value')) ?></span>
            </div>
        </article>

        <!-- Pending Orders -->
        <article class="stat-card stat-card-compact <?= $stats['pending'] > 0 ? 'stat-card-warning' : '' ?>">
            <div class="stat-icon-small">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value"><?= $stats['pending'] ?></strong>
                <span class="stat-label"><?= adminH(adminPhrase('Pending Orders')) ?></span>
            </div>
        </article>

        <!-- Fulfillment Rate -->
        <article class="stat-card stat-card-compact">
            <div class="stat-icon-small">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value"><?= $stats['fulfillment_rate'] ?>%</strong>
                <span class="stat-label"><?= adminH(adminPhrase('Fulfillment Rate')) ?></span>
            </div>
        </article>

        <!-- Conversion Rate -->
        <article class="stat-card stat-card-compact">
            <div class="stat-icon-small">
                <i class="fas fa-chart-pie"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value"><?= $stats['conversion_rate'] ?>%</strong>
                <span class="stat-label"><?= adminH(adminPhrase('Conversion Rate')) ?></span>
            </div>
        </article>

        <!-- Reorder Alerts -->
        <article class="stat-card stat-card-compact <?= $stats['alerts'] > 0 ? 'stat-card-danger' : '' ?>">
            <div class="stat-icon-small">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value"><?= $stats['alerts'] ?></strong>
                <span class="stat-label"><?= adminH(adminPhrase('Reorder Alerts')) ?></span>
            </div>
        </article>

        <!-- Out of Stock -->
        <article class="stat-card stat-card-compact <?= $stats['out_of_stock'] > 0 ? 'stat-card-danger' : '' ?>">
            <div class="stat-icon-small">
                <i class="fas fa-box-open"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value"><?= $stats['out_of_stock'] ?></strong>
                <span class="stat-label"><?= adminH(adminPhrase('Out of Stock')) ?></span>
            </div>
        </article>

        <!-- Today's Revenue -->
        <article class="stat-card stat-card-compact stat-card-success">
            <div class="stat-icon-small">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value"><?= adminMoney($stats['revenue_today']) ?></strong>
                <span class="stat-label"><?= adminH(adminPhrase("Today's Revenue")) ?></span>
            </div>
        </article>

        <!-- This Week Revenue -->
        <article class="stat-card stat-card-compact">
            <div class="stat-icon-small">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value"><?= adminMoney($stats['revenue_week']) ?></strong>
                <span class="stat-label"><?= adminH(adminPhrase('This Week')) ?></span>
            </div>
        </article>
    </div>
</section>


<section class="table-card activity-feed-card">
    <div class="card-head">
        <h2><?= adminH(i18n_t('admin.dashboard_activity_feed', [], adminPhrase('Dashboard Activity Feed'))) ?></h2>
    </div>
    <?php if ($recentActivity === []): ?>
        <p class="empty-copy"><?= adminH(adminPhrase('No admin activity logged yet.')) ?></p>
    <?php else: ?>
        <div class="activity-feed">
            <?php foreach ($recentActivity as $event): ?>
                <div class="activity-item">
                    <strong><?= adminH(adminActivitySummaryLabel($event['summary'])) ?></strong>
                    <span><?= adminH(i18n_t('admin.actor_date_compact', [
                        'actor' => $event['actor_email'] ?: i18n_t('admin.system_actor', [], adminPhrase('System')),
                        'date' => adminFormatDate($event['created_at'], 'datetime_short'),
                    ], adminPhrase('{actor} · {date}', [
                        'actor' => $event['actor_email'] ?: i18n_t('admin.system_actor', [], adminPhrase('System')),
                        'date' => adminFormatDate($event['created_at'], 'datetime_short'),
                    ]))) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>


<div class="dashboard-grid">
    <section class="table-card">
        <div class="card-head">
            <h2><?= adminH(adminPhrase('Latest Components')) ?></h2>
            <a class="button button-light button-small" href="admin-products.php"><?= adminH(adminPhrase('Manage')) ?></a>
        </div>
        <table>
            <thead>
                <tr>
                    <th><?= adminH(adminPhrase('Product')) ?></th>
                    <th><?= adminH(adminPhrase('Brand')) ?></th>
                    <th><?= adminH(adminPhrase('Category')) ?></th>
                    <th><?= adminH(adminPhrase('Price')) ?></th>
                    <th><?= adminH(adminPhrase('Stock')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentProducts === []): ?>
                    <tr><td colspan="5"><?= adminH(adminPhrase('No products yet. Add your first catalog item.')) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($recentProducts as $product): ?>
                    <tr>
                        <td>
                            <strong><?= adminH($product['name']) ?></strong>
                            <small>#<?= (int) $product['id'] ?></small>
                        </td>
                        <td><?= adminH($product['brand']) ?></td>
                        <td><?= adminH(adminCategoryLabel($product['category'])) ?></td>
                        <td><?= adminMoney((float) $product['price']) ?></td>
                        <td>
                            <span class="status-badge <?= adminStockBadgeClass((int) $product['stock_quantity'], (int) $product['reorder_level']) ?>">
                                <?= (int) $product['stock_quantity'] ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <aside class="table-card">
        <div class="card-head">
            <h2><?= adminH(adminPhrase('Reorder List')) ?></h2>
            <a class="button button-light button-small" href="admin-stock.php"><?= adminH(adminPhrase('Stock')) ?></a>
        </div>
        <?php if ($lowStockProducts === []): ?>
            <p class="empty-copy"><?= adminH(adminPhrase('No product is below its reorder level.')) ?></p>
        <?php else: ?>
            <ul class="alert-list">
                <?php foreach ($lowStockProducts as $product): ?>
                    <li>
                        <strong><?= adminH($product['name']) ?></strong>
                        <span><?= adminH(adminPhrase('{category} - Stock {stock} / reorder at {reorder}', [
                            'category' => adminCategoryLabel($product['category']),
                            'stock' => (int) $product['stock_quantity'],
                            'reorder' => (int) $product['reorder_level'],
                        ])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </aside>

    <aside class="table-card">
        <div class="card-head">
            <h2><?= adminH(adminPhrase('Most Wanted Restocks')) ?></h2>
        </div>
        <?php if ($mostWantedRestocks === []): ?>
            <p class="empty-copy"><?= adminH(adminPhrase('No out-of-stock products have restock requests.')) ?></p>
        <?php else: ?>
            <ul class="alert-list">
                <?php foreach ($mostWantedRestocks as $product): ?>
                    <li>
                        <strong><?= adminH($product['name']) ?></strong>
                        <span style="color: var(--cyan); font-weight: bold;"><i class="fas fa-bell"></i> <?= adminH(adminPhrase('{count} subscribers', ['count' => (int) $product['subscriber_count']])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </aside>
</div>

<section class="table-card">
    <div class="card-head">
        <h2><?= adminH(adminPhrase('Recent Customer Orders')) ?></h2>
        <a class="button button-light button-small" href="admin-orders.php"><?= adminH(adminPhrase('Track orders')) ?></a>
    </div>
    <table>
        <thead>
            <tr>
                <th><?= adminH(adminPhrase('Order')) ?></th>
                <th><?= adminH(adminPhrase('Customer')) ?></th>
                <th><?= adminH(adminPhrase('Date')) ?></th>
                <th><?= adminH(adminPhrase('Status')) ?></th>
                <th><?= adminH(adminPhrase('Payment')) ?></th>
                <th><?= adminH(adminPhrase('Total')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($recentOrders === []): ?>
                <tr><td colspan="6"><?= adminH(adminPhrase('No order data available yet.')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($recentOrders as $order): ?>
                <?php $status = (string) $order['status']; ?>
                <tr>
                    <td>#<?= (int) $order['id'] ?></td>
                    <td><?= adminH($order['client_name'] ?: adminPhrase('Unknown customer')) ?></td>
                    <td><?= adminH(date('Y-m-d', strtotime((string) $order['created_at']))) ?></td>
                    <td>
                        <span class="status-badge <?= in_array($status, ['delivered', 'shipped'], true) ? 'is-good' : ($status === 'cancelled' ? 'is-danger' : 'is-warn') ?>">
                            <?= adminH(adminStatusLabel($status)) ?>
                        </span>
                    </td>
                    <td><?= adminH(adminPaymentStatusLabel($order['payment_status'])) ?></td>
                    <td><?= adminMoney((float) $order['total']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<!-- Flash Sales Management -->
<section class="table-card" id="flash-sales-section">
    <div class="card-head">
        <h2>⚡ <?= adminH(adminPhrase('Flash Sales ({count} active)', ['count' => $activeFlashCount])) ?></h2>
    </div>

    <div style="padding: 20px; background: var(--input-bg); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 20px;">
        <h3 style="margin: 0 0 16px; font-size: 1rem; color: var(--text);"><?= adminH(adminPhrase('Create Flash Sale')) ?></h3>
        <form id="flashSaleForm" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
            <div>
                <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:4px;"><?= adminH(adminPhrase('Product')) ?></label>
                <select id="fsProduct" required style="width:100%; padding:10px; background:var(--card-bg); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:0.85rem;">
                    <option value=""><?= adminH(adminPhrase('Select product...')) ?></option>
                    <?php foreach ($allProducts as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" data-price="<?= (float) $p['price'] ?>">
                            <?= adminH($p['name']) ?> — <?= adminMoney((float) $p['price']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:4px;"><?= adminH(adminPhrase('Sale Price (DH)')) ?></label>
                <input type="number" id="fsSalePrice" step="0.01" min="1" required placeholder="<?= adminH(adminPhrase('e.g. 4999.90')) ?>" style="width:100%; padding:10px; background:var(--card-bg); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:0.85rem;">
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:4px;"><?= adminH(adminPhrase('Starts At')) ?></label>
                <input type="datetime-local" id="fsStartsAt" required style="width:100%; padding:10px; background:var(--card-bg); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:0.85rem;">
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:4px;"><?= adminH(adminPhrase('Ends At')) ?></label>
                <input type="datetime-local" id="fsEndsAt" required style="width:100%; padding:10px; background:var(--card-bg); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:0.85rem;">
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:4px;"><?= adminH(adminPhrase('Max Quantity (optional)')) ?></label>
                <input type="number" id="fsMaxQty" min="1" placeholder="<?= adminH(adminPhrase('Unlimited')) ?>" style="width:100%; padding:10px; background:var(--card-bg); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:0.85rem;">
            </div>
            <div>
                <button type="submit" class="button button-primary" style="width:100%; padding:10px;">⚡ <?= adminH(adminPhrase('Create Sale')) ?></button>
            </div>
        </form>
        <div id="fsMessage" style="margin-top: 10px; font-size: 0.85rem;"></div>
    </div>

    <table>
        <thead>
            <tr>
                <th><?= adminH(adminPhrase('Product')) ?></th>
                <th><?= adminH(adminPhrase('Original')) ?></th>
                <th><?= adminH(adminPhrase('Sale Price')) ?></th>
                <th><?= adminH(adminPhrase('Discount')) ?></th>
                <th><?= adminH(adminPhrase('Stock')) ?></th>
                <th><?= adminH(adminPhrase('Period')) ?></th>
                <th><?= adminH(adminPhrase('Status')) ?></th>
                <th><?= adminH(adminPhrase('Action')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($flashSales)): ?>
                <tr><td colspan="8"><?= adminH(adminPhrase('No flash sales yet. Create one above.')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($flashSales as $sale): ?>
                <?php
                    $nowDt = new DateTime();
                    $starts = new DateTime($sale['starts_at']);
                    $ends = new DateTime($sale['ends_at']);
                    $isActive = $nowDt >= $starts && $nowDt < $ends;
                    $isUpcoming = $nowDt < $starts;
                    $discount = round((1 - (float)$sale['sale_price'] / (float)$sale['original_price']) * 100);
                    $statusClass = $isActive ? 'is-good' : ($isUpcoming ? 'is-warn' : 'is-danger');
                    $statusText = $isActive ? adminPhrase('Active') : ($isUpcoming ? adminPhrase('Upcoming') : adminPhrase('Expired'));
                    $soldInfo = $sale['max_quantity']
                        ? ((int)$sale['sold_count'] . '/' . (int)$sale['max_quantity'])
                        : adminPhrase('Unlimited');
                ?>
                <tr data-sale-id="<?= (int) $sale['id'] ?>">
                    <td>
                        <strong><?= adminH($sale['product_name']) ?></strong>
                        <small>#<?= (int) $sale['product_id'] ?></small>
                    </td>
                    <td><?= adminMoney((float) $sale['original_price']) ?></td>
                    <td style="color: #ff3d5a; font-weight: 700;"><?= adminMoney((float) $sale['sale_price']) ?></td>
                    <td><span style="color: #00e676; font-weight: 700;">-<?= $discount ?>%</span></td>
                    <td><?= $soldInfo ?></td>
                    <td>
                        <small><?= adminH(adminFormatDate($starts->format('Y-m-d H:i:s'), 'datetime_short')) ?></small><br>
                        <small>→ <?= adminH(adminFormatDate($ends->format('Y-m-d H:i:s'), 'datetime_short')) ?></small>
                    </td>
                    <td><span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                    <td>
                        <button class="button button-light button-small fs-delete-btn" data-id="<?= (int) $sale['id'] ?>"><?= adminH(adminPhrase('Delete')) ?></button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<script>
(function() {
    // Auto-fill dates
    var now = new Date();
    var later = new Date(now.getTime() + 3 * 86400000);
    function toLocal(d) {
        var pad = function(n) { return String(n).padStart(2, '0'); };
        return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }
    var startInput = document.getElementById('fsStartsAt');
    var endInput = document.getElementById('fsEndsAt');
    if (startInput && !startInput.value) startInput.value = toLocal(now);
    if (endInput && !endInput.value) endInput.value = toLocal(later);

    // Auto-suggest sale price when product changes
    var prodSelect = document.getElementById('fsProduct');
    if (prodSelect) {
        prodSelect.addEventListener('change', function() {
            var opt = this.options[this.selectedIndex];
            var price = parseFloat(opt.getAttribute('data-price') || 0);
            if (price > 0) {
                document.getElementById('fsSalePrice').value = (price * 0.80).toFixed(2);
            }
        });
    }

    // Create flash sale
    var form = document.getElementById('flashSaleForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var msgEl = document.getElementById('fsMessage');
            var body = {
                action: 'create',
                product_id: parseInt(document.getElementById('fsProduct').value),
                sale_price: parseFloat(document.getElementById('fsSalePrice').value),
                starts_at: document.getElementById('fsStartsAt').value.replace('T', ' ') + ':00',
                ends_at: document.getElementById('fsEndsAt').value.replace('T', ' ') + ':00',
                max_quantity: document.getElementById('fsMaxQty').value ? parseInt(document.getElementById('fsMaxQty').value) : null
            };
            fetch('api/admin-flash-sales.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                msgEl.style.color = data.success ? '#00e676' : '#ff3d5a';
                msgEl.textContent = data.message || data.error;
                if (data.success) setTimeout(function() { location.reload(); }, 1000);
            })
            .catch(function(err) {
                msgEl.style.color = '#ff3d5a';
                msgEl.textContent = (window.adminI18n?.general?.network_error || 'Network error') + ': ' + err.message;
            });
        });
    }

    // Delete flash sale
    document.querySelectorAll('.fs-delete-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm(window.adminI18n?.general?.confirm_delete_flash_sale || 'Delete this flash sale?')) return;
            var id = parseInt(this.getAttribute('data-id'));
            var row = this.closest('tr');
            fetch('api/admin-flash-sales.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id: id })
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success && row) row.remove();
                else if (!data.success) alert(data.error);
            })
            .catch(function(err) { alert((window.adminI18n?.general?.error || 'Error') + ': ' + err.message); });
        });
    });
})();
</script>



<?php adminPageEnd(); ?>
