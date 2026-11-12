<?php
require_once 'admin-helpers.php';

adminRequireAuth();

$pdo = db();
adminEnsureAdminSuiteTables($pdo);

$hasOrders = adminTableExistsCached($pdo, 'orders');
$hasClients = adminTableExistsCached($pdo, 'Client');
$hasOrderItems = adminTableExistsCached($pdo, 'order_items');

// Enhanced statistics: one aggregate query per domain instead of ~15 round trips.
$stats = [
    'total_revenue' => 0.0,
    'revenue_today' => 0.0,
    'revenue_week' => 0.0,
    'revenue_month' => 0.0,
    'revenue_last_month' => 0.0,
    'total_orders' => 0,
    'orders_today' => 0,
    'orders_week' => 0,
    'orders_last_week' => 0,
    'avg_order' => 0.0,
    'completed_orders' => 0,
    'total_customers' => 0,
    'customers_week' => 0,
    'total_products' => 0,
    'total_laptops' => 0,
    'best_seller' => 'N/A',
];

if ($hasOrders) {
    $row = adminFetchAll($pdo, "
        SELECT
            COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total ELSE 0 END), 0) AS total_revenue,
            COALESCE(SUM(CASE WHEN status != 'cancelled' AND DATE(created_at) = CURDATE() THEN total ELSE 0 END), 0) AS revenue_today,
            COALESCE(SUM(CASE WHEN status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN total ELSE 0 END), 0) AS revenue_week,
            COALESCE(SUM(CASE WHEN status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN total ELSE 0 END), 0) AS revenue_month,
            COALESCE(SUM(CASE WHEN status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN total ELSE 0 END), 0) AS revenue_last_month,
            COUNT(*) AS total_orders,
            COALESCE(SUM(DATE(created_at) = CURDATE()), 0) AS orders_today,
            COALESCE(SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)), 0) AS orders_week,
            COALESCE(SUM(created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)), 0) AS orders_last_week,
            COALESCE(AVG(CASE WHEN status != 'cancelled' THEN total END), 0) AS avg_order,
            COALESCE(SUM(status = 'delivered'), 0) AS completed_orders
        FROM orders
    ")[0] ?? [];
    foreach (['total_orders', 'orders_today', 'orders_week', 'orders_last_week', 'completed_orders'] as $key) {
        $stats[$key] = (int) ($row[$key] ?? 0);
    }
    foreach (['total_revenue', 'revenue_today', 'revenue_week', 'revenue_month', 'revenue_last_month', 'avg_order'] as $key) {
        $stats[$key] = (float) ($row[$key] ?? 0);
    }
}

if ($hasClients) {
    $row = adminFetchAll($pdo, '
        SELECT
            COUNT(*) AS total_customers,
            COALESCE(SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)), 0) AS customers_week
        FROM Client
    ')[0] ?? [];
    $stats['total_customers'] = (int) ($row['total_customers'] ?? 0);
    $stats['customers_week'] = (int) ($row['customers_week'] ?? 0);
}

$stats['total_products'] = (int) adminFetchValue($pdo, 'SELECT COUNT(*) FROM products');
$stats['total_laptops'] = adminTableExistsCached($pdo, 'laptops') ? (int) adminFetchValue($pdo, 'SELECT COUNT(*) FROM laptops') : 0;
$stats['best_seller'] = ($hasOrders && $hasOrderItems) ? adminFetchValue($pdo, '
    SELECT p.name 
    FROM order_items oi 
    JOIN products p ON p.id = oi.product_id 
    GROUP BY p.id, p.name 
    ORDER BY SUM(oi.quantity) DESC 
    LIMIT 1
') : 'N/A';

// Calculate growth rates
$stats['revenue_growth'] = 0.0;
if ($stats['revenue_last_month'] > 0) {
    $stats['revenue_growth'] = round((($stats['revenue_month'] - $stats['revenue_last_month']) / $stats['revenue_last_month']) * 100, 1);
}

$stats['order_growth'] = 0.0;
if ($stats['orders_last_week'] > 0) {
    $stats['order_growth'] = round((($stats['orders_week'] - $stats['orders_last_week']) / $stats['orders_last_week']) * 100, 1);
}

$stats['fulfillment_rate'] = 0.0;
if ($stats['total_orders'] > 0) {
    $stats['fulfillment_rate'] = round(($stats['completed_orders'] / $stats['total_orders']) * 100, 1);
}

$stats['conversion_rate'] = 0.0;
if ($stats['total_customers'] > 0) {
    $stats['conversion_rate'] = round(($stats['total_orders'] / $stats['total_customers']) * 100, 1);
}

$salesSeries = $hasOrders ? adminFetchAll($pdo, "
    SELECT DATE(created_at) AS day, COUNT(*) AS orders_count, COALESCE(SUM(total), 0) AS revenue
    FROM orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
") : [];
$salesByDay = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $salesByDay[$day] = ['day' => $day, 'orders_count' => 0, 'revenue' => 0.0];
}
foreach ($salesSeries as $row) {
    $salesByDay[(string) $row['day']] = [
        'day' => (string) $row['day'],
        'orders_count' => (int) $row['orders_count'],
        'revenue' => (float) $row['revenue'],
    ];
}
$salesChartData = array_values($salesByDay);

$categorySales = ($hasOrders && adminTableExistsCached($pdo, 'order_items'))
    ? adminFetchAll($pdo, '
        SELECT COALESCE(p.category, "Uncategorized") AS category,
               COALESCE(SUM(oi.quantity * oi.price_at_time), 0) AS revenue,
               COALESCE(SUM(oi.quantity), 0) AS units
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        LEFT JOIN products p ON p.id = oi.product_id
        GROUP BY COALESCE(p.category, "Uncategorized")
        ORDER BY revenue DESC
        LIMIT 6
    ')
    : [];
$topCategoryRevenue = (float) max(array_map(static fn($row) => (float) $row['revenue'], $categorySales ?: [['revenue' => 0]]));
$flashRevenue = ($hasOrders && adminTableExistsCached($pdo, 'order_items'))
    ? (float) adminFetchValue($pdo, '
        SELECT COALESCE(SUM(oi.quantity * oi.price_at_time), 0)
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        JOIN flash_sales fs ON fs.product_id = p.id
    ')
    : 0.0;

$hasLaptops = adminTableExistsCached($pdo, 'laptops');
$laptopTotalRevenue = ($hasOrders && $hasLaptops && adminTableExistsCached($pdo, 'order_items'))
    ? (float) adminFetchValue($pdo, '
        SELECT COALESCE(SUM(oi.quantity * oi.price_at_time), 0)
        FROM order_items oi
        JOIN laptops l ON oi.name_at_time = l.name
    ')
    : 0.0;

$laptopBrandSales = ($hasOrders && $hasLaptops && adminTableExistsCached($pdo, 'order_items'))
    ? adminFetchAll($pdo, '
        SELECT l.brand, 
               COALESCE(SUM(oi.quantity), 0) AS units, 
               COALESCE(SUM(oi.quantity * oi.price_at_time), 0) AS revenue
        FROM order_items oi
        JOIN laptops l ON oi.name_at_time = l.name
        GROUP BY l.brand
        ORDER BY revenue DESC
    ')
    : [];
$topLaptopBrandRevenue = (float) max(array_map(static fn($row) => (float) $row['revenue'], $laptopBrandSales ?: [['revenue' => 0]]));

$avgLaptopPrice = $hasLaptops ? (float) adminFetchValue($pdo, 'SELECT COALESCE(AVG(price), 0) FROM laptops') : 0.0;
$avgComponentPrice = (float) adminFetchValue($pdo, 'SELECT COALESCE(AVG(price), 0) FROM products');

$laptopStockValue = $hasLaptops ? (float) adminFetchValue($pdo, 'SELECT COALESCE(SUM(price * stock_quantity), 0) FROM laptops') : 0.0;
$componentStockValue = (float) adminFetchValue($pdo, 'SELECT COALESCE(SUM(price * stock_quantity), 0) FROM products');

$laptopStockQty = $hasLaptops ? (int) adminFetchValue($pdo, 'SELECT COALESCE(SUM(stock_quantity), 0) FROM laptops') : 0;
$componentStockQty = (int) adminFetchValue($pdo, 'SELECT COALESCE(SUM(stock_quantity), 0) FROM products');

// Advanced Chart Data
$orderStatusCounts = $hasOrders ? adminFetchAll($pdo, '
    SELECT status, COUNT(*) as count 
    FROM orders 
    GROUP BY status
') : [];

$revenueByDayOfWeek = $hasOrders ? adminFetchAll($pdo, '
    SELECT DAYNAME(created_at) as day_name, COALESCE(SUM(total), 0) as revenue 
    FROM orders 
    WHERE status != "cancelled"
    GROUP BY DAYNAME(created_at)
    ORDER BY FIELD(day_name, "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday")
') : [];

$todayRevenue = $hasOrders ? (float) adminFetchValue($pdo, 'SELECT COALESCE(SUM(total), 0) FROM orders WHERE DATE(created_at) = CURDATE()') : 0.0;
$avgOrder = $hasOrders ? (float) adminFetchValue($pdo, 'SELECT COALESCE(AVG(total), 0) FROM orders') : 0.0;

// Additional chart data
$topProducts = ($hasOrders && $hasOrderItems) ? adminFetchAll($pdo, '
    SELECT p.name, SUM(oi.quantity) as units_sold, SUM(oi.quantity * oi.price_at_time) as revenue
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    GROUP BY p.id, p.name
    ORDER BY units_sold DESC
    LIMIT 10
') : [];

$monthlyRevenue = $hasOrders ? adminFetchAll($pdo, '
    SELECT DATE_FORMAT(created_at, "%Y-%m") as month, COALESCE(SUM(total), 0) as revenue, COUNT(*) as orders
    FROM orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) AND status != "cancelled"
    GROUP BY DATE_FORMAT(created_at, "%Y-%m")
    ORDER BY month ASC
') : [];

$hourlyOrders = $hasOrders ? adminFetchAll($pdo, '
    SELECT HOUR(created_at) as hour, COUNT(*) as order_count
    FROM orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY HOUR(created_at)
    ORDER BY hour ASC
') : [];

$paymentMethods = $hasOrders ? adminFetchAll($pdo, '
    SELECT payment_method, COUNT(*) as count, SUM(total) as revenue
    FROM orders
    WHERE status != "cancelled"
    GROUP BY payment_method
') : [];

$customerOrderFrequency = ($hasOrders && $hasClients) ? adminFetchAll($pdo, '
    SELECT 
        CASE 
            WHEN order_count = 1 THEN "1 order"
            WHEN order_count = 2 THEN "2 orders"
            WHEN order_count BETWEEN 3 AND 5 THEN "3-5 orders"
            WHEN order_count BETWEEN 6 AND 10 THEN "6-10 orders"
            ELSE "10+ orders"
        END as frequency,
        COUNT(*) as customer_count
    FROM (
        SELECT client_id, COUNT(*) as order_count
        FROM orders
        GROUP BY client_id
    ) as customer_orders
    GROUP BY frequency
    ORDER BY FIELD(frequency, "1 order", "2 orders", "3-5 orders", "6-10 orders", "10+ orders")
') : [];

$categoryPerformance = ($hasOrders && $hasOrderItems) ? adminFetchAll($pdo, '
    SELECT 
        COALESCE(p.category, "Uncategorized") as category,
        COUNT(DISTINCT oi.order_id) as order_count,
        SUM(oi.quantity) as units_sold,
        SUM(oi.quantity * oi.price_at_time) as revenue
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    GROUP BY COALESCE(p.category, "Uncategorized")
    ORDER BY revenue DESC
') : [];

adminPageStart('Analytics', 'analytics');
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
window.addEventListener('error', function(e) {
    fetch('test-error-log.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'error=' + encodeURIComponent(e.message + ' at ' + e.filename + ':' + e.lineno + ':' + e.colno + '\n' + (e.error ? e.error.stack : ''))
    });
});
window.addEventListener('unhandledrejection', function(e) {
    fetch('test-error-log.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'error=' + encodeURIComponent('Unhandled rejection: ' + e.reason)
    });
});
</script>

<section class="section-heading">
    <div>
        <span class="eyebrow"><?= adminH(adminPhrase('Reports & Stats')) ?></span>
        <h1><?= adminH(adminPhrase('Analytics')) ?></h1>
        <p class="section-copy"><?= adminH(adminPhrase('Deep dive into store performance, sales trends, and customer behavior.')) ?></p>
    </div>
    <div class="heading-actions">
        <a class="button button-light" href="dashboard.php"><?= adminH(adminPhrase('Dashboard')) ?></a>
        <a class="button button-light" href="api/export-orders.php?format=csv"><i class="fas fa-file-csv"></i> <?= adminH(adminPhrase('Orders CSV')) ?></a>
        <a class="button button-light" href="api/export-customers.php?format=csv"><i class="fas fa-file-csv"></i> <?= adminH(adminPhrase('Customers CSV')) ?></a>
    </div>
</section>

<!-- Enhanced KPI Overview -->
<section class="stats-section">
    <div class="stats-section-header">
        <h2><i class="fas fa-chart-line"></i> <?= adminH(adminPhrase('Performance Overview')) ?></h2>
        <span class="stats-period"><?= adminH(adminPhrase('Real-time analytics')) ?></span>
    </div>
    
    <div class="stats-grid primary-stats">
        <!-- Total Revenue -->
        <article class="stat-card stat-card-featured" data-stat="revenue">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value"><?= adminMoney($stats['total_revenue']) ?></strong>
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

        <!-- Total Orders -->
        <article class="stat-card" data-stat="orders">
            <div class="stat-icon stat-icon-neutral">
                <i class="fas fa-shopping-bag"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value"><?= number_format($stats['total_orders']) ?></strong>
                <span class="stat-label"><?= adminH(adminPhrase('Total Orders')) ?></span>
                <div class="stat-meta">
                    <span class="stat-badge badge-info"><?= adminH(adminPhrase('{count} today', ['count' => $stats['orders_today']])) ?></span>
                    <span class="stat-subtext"><?= adminH(adminPhrase('{count} this week', ['count' => $stats['orders_week']])) ?></span>
                </div>
            </div>
        </article>

        <!-- Average Order Value -->
        <article class="stat-card" data-stat="aov">
            <div class="stat-icon stat-icon-neutral">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value"><?= adminMoney($stats['avg_order']) ?></strong>
                <span class="stat-label"><?= adminH(adminPhrase('Avg Order Value')) ?></span>
                <div class="stat-meta">
                    <span class="stat-badge badge-neutral"><?= adminH(adminPhrase('{count} completed', ['count' => $stats['completed_orders']])) ?></span>
                    <span class="stat-subtext"><?= adminH(adminPhrase('{rate}% fulfillment', ['rate' => $stats['fulfillment_rate']])) ?></span>
                </div>
            </div>
        </article>

        <!-- Total Customers -->
        <article class="stat-card" data-stat="customers">
            <div class="stat-icon stat-icon-neutral">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value"><?= number_format($stats['total_customers']) ?></strong>
                <span class="stat-label"><?= adminH(adminPhrase('Total Customers')) ?></span>
                <div class="stat-meta">
                    <span class="stat-badge badge-success"><?= adminH(adminPhrase('+{count} this week', ['count' => $stats['customers_week']])) ?></span>
                    <span class="stat-subtext"><?= adminH(adminPhrase('{rate}% conversion', ['rate' => $stats['conversion_rate']])) ?></span>
                </div>
            </div>
        </article>
    </div>
</section>

<!-- Quick Stats Grid -->
<section class="stats-section">
    <div class="stats-section-header">
        <h2><i class="fas fa-tachometer-alt"></i> <?= adminH(adminPhrase('Quick Insights')) ?></h2>
    </div>
    
    <div class="stats-grid secondary-stats">
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

        <!-- This Month Revenue -->
        <article class="stat-card stat-card-compact">
            <div class="stat-icon-small">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value"><?= adminMoney($stats['revenue_month']) ?></strong>
                <span class="stat-label"><?= adminH(adminPhrase('This Month')) ?></span>
            </div>
        </article>

        <!-- Order Growth -->
        <article class="stat-card stat-card-compact">
            <div class="stat-icon-small">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value <?= $stats['order_growth'] >= 0 ? '' : 'text-danger' ?>"><?= $stats['order_growth'] >= 0 ? '+' : '' ?><?= $stats['order_growth'] ?>%</strong>
                <span class="stat-label"><?= adminH(adminPhrase('Order Growth')) ?></span>
            </div>
        </article>

        <!-- Total Products -->
        <article class="stat-card stat-card-compact">
            <div class="stat-icon-small">
                <i class="fas fa-box"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value"><?= $stats['total_products'] + $stats['total_laptops'] ?></strong>
                <span class="stat-label"><?= adminH(adminPhrase('Total Products')) ?></span>
            </div>
        </article>

        <!-- Best Seller -->
        <article class="stat-card stat-card-compact stat-card-wide">
            <div class="stat-icon-small">
                <i class="fas fa-trophy"></i>
            </div>
            <div class="stat-content">
                <strong class="stat-value stat-value-truncate"><?= adminH($stats['best_seller']) ?></strong>
                <span class="stat-label"><?= adminH(adminPhrase('Best Selling Product')) ?></span>
            </div>
        </article>
    </div>
</section>

<section class="analytics-grid">
    <article class="table-card analytics-card">
        <div class="card-head">
            <div>
                <h2><?= adminH(adminPhrase('Sales Analytics')) ?></h2>
                <p class="card-copy"><?= adminH(adminPhrase('Revenue trend across the last 14 days.')) ?></p>
            </div>
            <strong class="analytics-kpi"><?= adminMoney($todayRevenue) ?><span><?= adminH(adminPhrase('Today')) ?></span></strong>
        </div>
        <canvas id="salesChart" class="sales-chart" height="220"></canvas>
    </article>
    <article class="table-card analytics-card">
        <div class="card-head">
            <div>
                <h2><?= adminH(adminPhrase('Category Revenue')) ?></h2>
                <p class="card-copy"><?= adminH(adminPhrase('Best-selling categories by order item revenue.')) ?></p>
            </div>
            <strong class="analytics-kpi"><?= adminMoney($avgOrder) ?><span><?= adminH(adminPhrase('Avg order')) ?></span></strong>
        </div>
        <div class="category-bars">
            <?php if ($categorySales === []): ?>
                <p class="empty-copy"><?= adminH(adminPhrase('No sales by category yet.')) ?></p>
            <?php endif; ?>
            <?php foreach ($categorySales as $row): ?>
                <?php $pct = $topCategoryRevenue > 0 ? max(3, ((float) $row['revenue'] / $topCategoryRevenue) * 100) : 0; ?>
                <div class="category-bar">
                    <span><strong><?= adminH(adminCategoryLabel($row['category'])) ?></strong><small><?= adminH(adminPhrase('{count} units', ['count' => (int) $row['units']])) ?></small></span>
                    <div><i style="width: <?= adminH((string) $pct) ?>%"></i></div>
                    <em><?= adminMoney((float) $row['revenue']) ?></em>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="flash-conversion">
            <span><?= adminH(adminPhrase('Flash sale influenced revenue')) ?></span>
            <strong><?= adminMoney($flashRevenue) ?></strong>
        </div>
    </article>
</section>

<!-- Laptop Performance & Catalog Analytics -->
<section class="analytics-grid-wide">
    <!-- Card 1: Laptop Brand Market Share -->
    <article class="table-card analytics-card">
        <div class="card-head">
            <div>
                <h2><?= adminH(adminPhrase('Laptop Brand Sales Curation')) ?></h2>
                <p class="card-copy"><?= adminH(adminPhrase('Sales volume and gross revenue split by laptop brand.')) ?></p>
            </div>
            <strong class="analytics-kpi"><?= adminMoney($laptopTotalRevenue) ?><span><?= adminH(adminPhrase('Laptop gross')) ?></span></strong>
        </div>
        <div class="category-bars">
            <?php if ($laptopBrandSales === []): ?>
                <p class="empty-copy"><?= adminH(adminPhrase('No laptop brand sales recorded yet.')) ?></p>
            <?php else: ?>
                <?php foreach ($laptopBrandSales as $row): ?>
                    <?php $pct = $topLaptopBrandRevenue > 0 ? max(3, ((float) $row['revenue'] / $topLaptopBrandRevenue) * 100) : 0; ?>
                    <div class="category-bar">
                        <span class="metric-row">
                            <strong><?= adminH($row['brand']) ?></strong>
                            <small class="metric-label"><?= adminH(adminPhrase('{count} units sold', ['count' => (int) $row['units']])) ?></small>
                        </span>
                        <div class="metric-bar-track">
                            <i class="metric-bar-segment metric-bar-segment-cyan" style="width: <?= adminH((string) $pct) ?>%;"></i>
                        </div>
                        <em class="metric-value"><?= adminMoney((float) $row['revenue']) ?></em>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </article>

    <!-- Card 2: Catalog Comparison Suite (Laptops vs Components) -->
    <article class="table-card analytics-card analytics-card-stretch">
        <div class="card-head">
            <div>
                <h2><?= adminH(adminPhrase('Catalog Curation Comparison')) ?></h2>
                <p class="card-copy"><?= adminH(adminPhrase('Visualizing stock pressure and pricing ratios.')) ?></p>
            </div>
        </div>
        
        <div class="comparison-panel">
            <!-- Comparison 1: Average List Price -->
            <div>
                <div class="metric-row">
                    <span class="metric-label"><?= adminH(adminPhrase('Average Laptop Price')) ?></span>
                    <span class="metric-value"><?= adminMoney($avgLaptopPrice) ?></span>
                </div>
                <div class="metric-row">
                    <span class="metric-label"><?= adminH(adminPhrase('Average Component Price')) ?></span>
                    <span class="metric-value"><?= adminMoney($avgComponentPrice) ?></span>
                </div>
                <?php 
                $maxAvgPrice = max(1.0, $avgLaptopPrice, $avgComponentPrice);
                $laptopPricePct = ($avgLaptopPrice / $maxAvgPrice) * 100;
                $compPricePct = ($avgComponentPrice / $maxAvgPrice) * 100;
                ?>
                <div class="metric-bar-track metric-bar-track-stacked">
                    <div class="metric-bar-segment metric-bar-segment-cyan" style="width: <?= $laptopPricePct ?>%;" title="<?= adminH(adminPhrase('Laptops')) ?>"></div>
                    <div class="metric-bar-segment metric-bar-segment-orange" style="width: <?= $compPricePct ?>%;" title="<?= adminH(adminPhrase('Components')) ?>"></div>
                </div>
            </div>

            <!-- Comparison 2: Inventory Value Investment -->
            <div>
                <div class="metric-row">
                    <span class="metric-label"><?= adminH(adminPhrase('Laptop Stock Value')) ?></span>
                    <span class="metric-value"><?= adminMoney($laptopStockValue) ?></span>
                </div>
                <div class="metric-row">
                    <span class="metric-label"><?= adminH(adminPhrase('Component Stock Value')) ?></span>
                    <span class="metric-value"><?= adminMoney($componentStockValue) ?></span>
                </div>
                <?php 
                $totalStockVal = max(1.0, $laptopStockValue + $componentStockValue);
                $laptopStockValPct = ($laptopStockValue / $totalStockVal) * 100;
                $compStockValPct = ($componentStockValue / $totalStockVal) * 100;
                ?>
                <div class="metric-bar-track metric-bar-track-stacked">
                    <div class="metric-bar-segment metric-bar-segment-cyan" style="width: <?= $laptopStockValPct ?>%;" title="<?= adminH(adminPhrase('Laptops')) ?>"></div>
                    <div class="metric-bar-segment metric-bar-segment-orange" style="width: <?= $compStockValPct ?>%;" title="<?= adminH(adminPhrase('Components')) ?>"></div>
                </div>
            </div>

            <!-- Comparison 3: Unit Stock Count Share -->
            <div>
                <div class="metric-row">
                    <span class="metric-label"><?= adminH(adminPhrase('Laptops in Stock')) ?></span>
                    <span class="metric-value"><?= adminH(adminPhrase('{count} units', ['count' => $laptopStockQty])) ?></span>
                </div>
                <div class="metric-row">
                    <span class="metric-label"><?= adminH(adminPhrase('Components in Stock')) ?></span>
                    <span class="metric-value"><?= adminH(adminPhrase('{count} units', ['count' => $componentStockQty])) ?></span>
                </div>
                <?php 
                $totalUnits = max(1, $laptopStockQty + $componentStockQty);
                $laptopUnitsPct = ($laptopStockQty / $totalUnits) * 100;
                $compUnitsPct = ($componentStockQty / $totalUnits) * 100;
                ?>
                <div class="metric-bar-track metric-bar-track-stacked">
                    <div class="metric-bar-segment metric-bar-segment-cyan" style="width: <?= $laptopUnitsPct ?>%;" title="<?= adminH(adminPhrase('Laptops')) ?>"></div>
                    <div class="metric-bar-segment metric-bar-segment-orange" style="width: <?= $compUnitsPct ?>%;" title="<?= adminH(adminPhrase('Components')) ?>"></div>
                </div>
            </div>
        </div>
        <div class="comparison-legend">
            <span><i class="fas fa-circle legend-dot legend-dot-cyan"></i> <?= adminH(adminPhrase('Laptops Curation')) ?></span>
            <span><i class="fas fa-circle legend-dot legend-dot-orange"></i> <?= adminH(adminPhrase('Components Curation')) ?></span>
        </div>
    </article>
</section>

<!-- Advanced Analytics Section -->
<section class="table-card chart-section">
    <div class="card-head">
        <div>
            <h2><?= adminH(adminPhrase('Advanced Analytics Explorer')) ?></h2>
            <p class="card-copy"><?= adminH(adminPhrase('Deeper visual insights into store performance.')) ?></p>
        </div>
        <div class="card-actions">
            <button class="button button-light button-small" onclick="exportChartAsPNG('statusChart', 'order_status_distribution.png')">
                <i class="fas fa-download"></i> <?= adminH(adminPhrase('Status')) ?>
            </button>
            <button class="button button-light button-small" onclick="exportChartAsPNG('dowChart', 'revenue_by_day.png')">
                <i class="fas fa-download"></i> <?= adminH(adminPhrase('Days')) ?>
            </button>
        </div>
    </div>
    
    <div class="chart-grid">
        <!-- Order Status Chart -->
        <div class="chart-cell">
            <h3><?= adminH(adminPhrase('Order Status Distribution')) ?></h3>
            <div class="chart-wrap">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <!-- Revenue by Day of Week -->
        <div class="chart-cell">
            <h3><?= adminH(adminPhrase('Revenue by Day of Week')) ?></h3>
            <div class="chart-wrap">
                <canvas id="dowChart"></canvas>
            </div>
        </div>
    </div>
</section>

<!-- New Charts Section: Product & Customer Analytics -->
<section class="table-card chart-section">
    <div class="card-head">
        <div>
            <h2><i class="fas fa-chart-bar"></i> <?= adminH(adminPhrase('Product & Customer Analytics')) ?></h2>
            <p class="card-copy"><?= adminH(adminPhrase('Top performers and customer behavior patterns.')) ?></p>
        </div>
        <div class="card-actions">
            <button class="button button-light button-small" onclick="exportChartAsPNG('topProductsChart', 'top_products.png')">
                <i class="fas fa-download"></i> <?= adminH(adminPhrase('Products')) ?>
            </button>
            <button class="button button-light button-small" onclick="exportChartAsPNG('customerFreqChart', 'customer_frequency.png')">
                <i class="fas fa-download"></i> <?= adminH(adminPhrase('Customers')) ?>
            </button>
        </div>
    </div>
    
    <div class="chart-grid">
        <!-- Top Products Chart -->
        <div class="chart-cell">
            <h3><?= adminH(adminPhrase('Top 10 Products by Units Sold')) ?></h3>
            <div class="chart-wrap-tall">
                <canvas id="topProductsChart"></canvas>
            </div>
        </div>

        <!-- Customer Order Frequency -->
        <div class="chart-cell">
            <h3><?= adminH(adminPhrase('Customer Order Frequency')) ?></h3>
            <div class="chart-wrap-tall">
                <canvas id="customerFreqChart"></canvas>
            </div>
        </div>
    </div>
</section>

<!-- New Charts Section: Revenue & Time Analytics -->
<section class="table-card chart-section">
    <div class="card-head">
        <div>
            <h2><i class="fas fa-clock"></i> <?= adminH(adminPhrase('Revenue & Time Analytics')) ?></h2>
            <p class="card-copy"><?= adminH(adminPhrase('Temporal patterns and revenue trends over time.')) ?></p>
        </div>
        <div class="card-actions">
            <button class="button button-light button-small" onclick="exportChartAsPNG('monthlyRevenueChart', 'monthly_revenue.png')">
                <i class="fas fa-download"></i> <?= adminH(adminPhrase('Monthly')) ?>
            </button>
            <button class="button button-light button-small" onclick="exportChartAsPNG('hourlyOrdersChart', 'hourly_orders.png')">
                <i class="fas fa-download"></i> <?= adminH(adminPhrase('Hourly')) ?>
            </button>
        </div>
    </div>
    
    <div class="chart-grid">
        <!-- Monthly Revenue Trend -->
        <div class="chart-cell">
            <h3><?= adminH(adminPhrase('Monthly Revenue Trend (12 Months)')) ?></h3>
            <div class="chart-wrap-tall">
                <canvas id="monthlyRevenueChart"></canvas>
            </div>
        </div>

        <!-- Hourly Order Distribution -->
        <div class="chart-cell">
            <h3><?= adminH(adminPhrase('Order Distribution by Hour (Last 30 Days)')) ?></h3>
            <div class="chart-wrap-tall">
                <canvas id="hourlyOrdersChart"></canvas>
            </div>
        </div>
    </div>
</section>

<!-- New Charts Section: Payment & Category Analytics -->
<section class="table-card chart-section">
    <div class="card-head">
        <div>
            <h2><i class="fas fa-credit-card"></i> <?= adminH(adminPhrase('Payment & Category Analytics')) ?></h2>
            <p class="card-copy"><?= adminH(adminPhrase('Payment preferences and category performance breakdown.')) ?></p>
        </div>
        <div class="card-actions">
            <button class="button button-light button-small" onclick="exportChartAsPNG('paymentChart', 'payment_methods.png')">
                <i class="fas fa-download"></i> <?= adminH(adminPhrase('Payment')) ?>
            </button>
            <button class="button button-light button-small" onclick="exportChartAsPNG('categoryPerfChart', 'category_performance.png')">
                <i class="fas fa-download"></i> <?= adminH(adminPhrase('Categories')) ?>
            </button>
        </div>
    </div>
    
    <div class="chart-grid">
        <!-- Payment Methods Distribution -->
        <div class="chart-cell">
            <h3><?= adminH(adminPhrase('Payment Methods Distribution')) ?></h3>
            <div class="chart-wrap-tall">
                <canvas id="paymentChart"></canvas>
            </div>
        </div>

        <!-- Category Performance -->
        <div class="chart-cell">
            <h3><?= adminH(adminPhrase('Category Performance (Revenue)')) ?></h3>
            <div class="chart-wrap-tall">
                <canvas id="categoryPerfChart"></canvas>
            </div>
        </div>
    </div>
</section>

<script>
window.adminSalesChartData = <?= i18n_script_json($salesChartData) ?>;
window.adminCharts = [];
const currencySymbol = <?= i18n_script_json(i18n_current_locale() === 'ar' ? 'د.م.' : 'DH') ?>;
const adminPhraseMap = Object.assign({}, window.__marocPcPhraseMap || {}, <?= i18n_script_json(array_reduce([
    'Revenue (DH)',
    'Units Sold',
    'Orders',
    'Revenue (Normalized)',
    'Units Sold (Normalized)',
    'Pending',
    'Processing',
    'Shipped',
    'Delivered',
    'Cancelled',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
    '1 order',
    '2 orders',
    '3-5 orders',
    '6-10 orders',
    '10+ orders',
    'Cash',
    'Card',
    'Credit',
    'Paypal',
    'Bank',
    'Apple Pay',
    'Google Pay',
    'Cash On Delivery',
    'Credit Card',
    'Cod',
    'Unknown',
], static function (array $phrases, string $source): array {
    $phrases[$source] = adminPhrase($source);
    return $phrases;
}, [])) ?>);
const adminLocale = window.__marocPcLocale || 'en';
const adminChartLocale = adminLocale === 'ar' ? 'ar-MA' : adminLocale;
const adminT = (source) => adminPhraseMap[source] || source;
const adminTitleCase = (value) => String(value || 'Unknown')
    .split(/[-_\s]+/)
    .filter(Boolean)
    .map(part => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase())
    .join(' ');
const adminCategoryLabels = <?= i18n_script_json(array_reduce(
    array_merge($categorySales, $categoryPerformance),
    static function (array $labels, array $row): array {
        $category = (string) ($row['category'] ?? '');
        if ($category !== '') {
            $labels[$category] = adminCategoryLabel($category);
        }
        return $labels;
    },
    []
)) ?>;

function getChartTheme() {
    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    return {
        isDark,
        grid: isDark ? 'rgba(255,255,255,0.10)' : 'rgba(15,23,42,0.12)',
        gridSubtle: isDark ? 'rgba(255,255,255,0.06)' : 'rgba(15,23,42,0.08)',
        text: isDark ? '#b0b8c8' : '#475569',
        textMuted: isDark ? '#9aa4b5' : '#64748B',
        tooltipBg: isDark ? 'rgba(10,11,14,0.95)' : 'rgba(255,255,255,0.98)',
        tooltipTitle: isDark ? '#00f5d4' : '#007A6E',
        tooltipBorder: isDark ? '#2d333b' : '#e2e8f0',
        cyan: isDark ? '#00f5d4' : '#007A6E',
        cyanFill: isDark ? 'rgba(0,245,212,0.35)' : 'rgba(0,122,110,0.22)',
        cyanFillSubtle: isDark ? 'rgba(0,245,212,0.2)' : 'rgba(0,122,110,0.14)',
        cyanHover: isDark ? 'rgba(0,245,212,0.45)' : 'rgba(0,122,110,0.32)',
        purple: isDark ? '#667eea' : '#4f46e5',
        purpleFill: isDark ? 'rgba(102,126,234,0.35)' : 'rgba(79,70,229,0.22)',
        purpleFillSubtle: isDark ? 'rgba(102,126,234,0.2)' : 'rgba(79,70,229,0.14)',
        purpleHover: isDark ? 'rgba(102,126,234,0.45)' : 'rgba(79,70,229,0.32)',
        orange: isDark ? '#ff6b35' : '#D95F0A',
        orangeFill: isDark ? 'rgba(255,107,53,0.35)' : 'rgba(217,95,10,0.22)',
        chartBg: isDark ? '#1e2229' : '#ffffff',
        border: isDark ? '#1e2229' : '#e2e8f0',
        white: '#ffffff'
    };
}

function renderSalesChart() {
    const t = getChartTheme();
    const canvas = document.getElementById('salesChart');
    const data = window.adminSalesChartData || [];
    if (!canvas || !data.length) return;
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = Math.max(320, rect.width) * dpr;
    canvas.height = 220 * dpr;
    ctx.scale(dpr, dpr);
    const w = canvas.width / dpr;
    const h = canvas.height / dpr;
    const pad = { left: 48, right: 18, top: 18, bottom: 34 };
    const maxRevenue = Math.max(1, ...data.map(row => Number(row.revenue || 0)));
    const x = i => pad.left + (i / Math.max(1, data.length - 1)) * (w - pad.left - pad.right);
    const y = v => pad.top + (h - pad.top - pad.bottom) - (Number(v || 0) / maxRevenue) * (h - pad.top - pad.bottom);

    ctx.clearRect(0, 0, w, h);
    ctx.strokeStyle = t.grid;
    ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
        const gy = pad.top + i * ((h - pad.top - pad.bottom) / 4);
        ctx.beginPath();
        ctx.moveTo(pad.left, gy);
        ctx.lineTo(w - pad.right, gy);
        ctx.stroke();
    }

    const gradient = ctx.createLinearGradient(0, pad.top, 0, h - pad.bottom);
    gradient.addColorStop(0, t.cyanFill);
    gradient.addColorStop(1, t.isDark ? 'rgba(0,245,212,0.02)' : 'rgba(0,122,110,0.02)');
    ctx.beginPath();
    data.forEach((row, i) => {
        const px = x(i);
        const py = y(row.revenue);
        if (i === 0) ctx.moveTo(px, py);
        else ctx.lineTo(px, py);
    });
    ctx.lineTo(x(data.length - 1), h - pad.bottom);
    ctx.lineTo(x(0), h - pad.bottom);
    ctx.closePath();
    ctx.fillStyle = gradient;
    ctx.fill();

    ctx.beginPath();
    data.forEach((row, i) => {
        const px = x(i);
        const py = y(row.revenue);
        if (i === 0) ctx.moveTo(px, py);
        else ctx.lineTo(px, py);
    });
    ctx.strokeStyle = t.cyan;
    ctx.lineWidth = 2;
    ctx.stroke();

    ctx.fillStyle = t.text;
    ctx.font = '11px JetBrains Mono, monospace';
    data.forEach((row, i) => {
        if (i % 3 !== 0 && i !== data.length - 1) return;
        ctx.fillText(String(row.day).slice(5), x(i) - 16, h - 12);
    });
}

renderSalesChart();

(function() {
    const t = getChartTheme();

    // Shared chart styling overrides
    Chart.defaults.color = t.text;
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.plugins.tooltip.backgroundColor = t.tooltipBg;
    Chart.defaults.plugins.tooltip.titleColor = t.tooltipTitle;
    Chart.defaults.plugins.tooltip.borderColor = t.tooltipBorder;
    Chart.defaults.plugins.tooltip.borderWidth = 1;

    // 1. Order Status Doughnut Chart
    const statusData = <?= i18n_script_json($orderStatusCounts) ?>;
    const statusSources = statusData.map(d => adminTitleCase(d.status));
    const labels = statusSources.map(adminT);
    const counts = statusData.map(d => d.count);

    const statusCanvas = document.getElementById('statusChart');
    if (statusData.length === 0) {
        const ctx = statusCanvas.getContext('2d');
        ctx.fillStyle = t.text;
        ctx.font = '14px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(adminT('No order data available yet.') || 'No data available', statusCanvas.width / 2, statusCanvas.height / 2);
    } else {

    const colors = {
        'Pending': '#feca57',
        'Processing': '#48dbfb',
        'Shipped': '#667eea',
        'Delivered': '#00e676',
        'Cancelled': '#ff6b6b'
    };
    const bgColors = statusSources.map(l => colors[l] || '#4fc3f7');

    window.adminCharts.push(new Chart(statusCanvas, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: bgColors,
                borderWidth: 2,
                borderColor: t.border,
                hoverOffset: 8,
                hoverBorderWidth: 3,
                hoverBorderColor: t.white
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        color: t.text,
                        padding: 15,
                        font: { size: 11 },
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    }));
    } // end else for status chart

    // 2. Revenue by Day of Week Bar Chart
    const dowData = <?= i18n_script_json($revenueByDayOfWeek) ?>;
    const dowLabels = dowData.map(d => adminT(d.day_name));
    const dowRevenues = dowData.map(d => parseFloat(d.revenue));

    const dowCanvas = document.getElementById('dowChart');
    if (dowData.length === 0) {
        const ctx = dowCanvas.getContext('2d');
        ctx.fillStyle = t.text;
        ctx.font = '14px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(adminT('No order data available yet.') || 'No data available', dowCanvas.width / 2, dowCanvas.height / 2);
    } else {

    window.adminCharts.push(new Chart(dowCanvas, {
        type: 'bar',
        data: {
            labels: dowLabels,
            datasets: [{
                label: adminT('Revenue (DH)'),
                data: dowRevenues,
                backgroundColor: t.cyanFillSubtle,
                borderColor: t.cyan,
                borderWidth: 2,
                borderRadius: 6,
                hoverBackgroundColor: t.cyanHover
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: t.gridSubtle },
                    ticks: { callback: function(val) { return val + ' ' + currencySymbol; } }
                },
                x: { grid: { display: false } }
            },
            plugins: { legend: { display: false } }
        }
    }));
    } // end else for dow chart

    // 3. Top Products Horizontal Bar Chart
    const topProductsData = <?= i18n_script_json($topProducts) ?>;
    const productLabels = topProductsData.map(d => {
        const name = d.name || 'Unknown';
        return name.length > 25 ? name.substring(0, 25) + '...' : name;
    });
    const productUnits = topProductsData.map(d => parseInt(d.units_sold || 0));

    window.adminCharts.push(new Chart(document.getElementById('topProductsChart'), {
        type: 'bar',
        data: {
            labels: productLabels,
            datasets: [{
                label: adminT('Units Sold'),
                data: productUnits,
                backgroundColor: t.purpleFillSubtle,
                borderColor: t.purple,
                borderWidth: 2,
                borderRadius: 6,
                hoverBackgroundColor: t.purpleHover
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: t.gridSubtle }
                },
                y: {
                    grid: { display: false },
                    ticks: { font: { size: 10 } }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            const fullName = topProductsData[context[0].dataIndex].name;
                            return fullName;
                        }
                    }
                }
            }
        }
    }));

    // 4. Customer Order Frequency Pie Chart
    const customerFreqData = <?= i18n_script_json($customerOrderFrequency) ?>;
    const freqLabels = customerFreqData.map(d => adminT(d.frequency));
    const freqCounts = customerFreqData.map(d => parseInt(d.customer_count));

    window.adminCharts.push(new Chart(document.getElementById('customerFreqChart'), {
        type: 'pie',
        data: {
            labels: freqLabels,
            datasets: [{
                data: freqCounts,
                backgroundColor: [
                    '#00f5d4',
                    '#667eea',
                    '#f093fb',
                    '#feca57',
                    '#48dbfb'
                ],
                borderWidth: 2,
                borderColor: t.border,
                hoverOffset: 8,
                hoverBorderWidth: 3,
                hoverBorderColor: t.white
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        padding: 15,
                        font: { size: 11 },
                        color: t.text,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                }
            }
        }
    }));

    // 5. Monthly Revenue Line Chart
    const monthlyData = <?= i18n_script_json($monthlyRevenue) ?>;
    const monthLabels = monthlyData.map(d => {
        const monthStr = d.month || '';
        if (!monthStr.includes('-')) return monthStr;
        const [year, month] = monthStr.split('-');
        const date = new Date(year, month - 1);
        return date.toLocaleDateString(adminChartLocale, { month: 'short', year: '2-digit' });
    });
    const monthRevenues = monthlyData.map(d => parseFloat(d.revenue || 0));

    window.adminCharts.push(new Chart(document.getElementById('monthlyRevenueChart'), {
        type: 'line',
        data: {
            labels: monthLabels,
            datasets: [{
                label: adminT('Revenue (DH)'),
                data: monthRevenues,
                backgroundColor: t.cyanFillSubtle,
                borderColor: t.cyan,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: t.cyan,
                pointBorderColor: t.white,
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: t.gridSubtle },
                    ticks: { callback: function(val) { return val.toLocaleString() + ' ' + currencySymbol; } }
                },
                x: {
                    grid: { display: false },
                    ticks: { maxRotation: 45, minRotation: 45 }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    }));

    // 6. Hourly Orders Area Chart
    const hourlyData = <?= i18n_script_json($hourlyOrders) ?>;
    const hourlyMap = {};
    for (let i = 0; i < 24; i++) { hourlyMap[i] = 0; }
    hourlyData.forEach(d => { hourlyMap[parseInt(d.hour)] = parseInt(d.order_count); });
    const hourLabels = Object.keys(hourlyMap).map(h => h + ':00');
    const hourCounts = Object.values(hourlyMap);

    window.adminCharts.push(new Chart(document.getElementById('hourlyOrdersChart'), {
        type: 'line',
        data: {
            labels: hourLabels,
            datasets: [{
                label: adminT('Orders'),
                data: hourCounts,
                backgroundColor: t.purpleFillSubtle,
                borderColor: t.purple,
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: t.purple,
                pointBorderColor: t.white,
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: t.gridSubtle },
                    ticks: { stepSize: 1 }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        maxRotation: 90,
                        minRotation: 90,
                        font: { size: 9 }
                    }
                }
            },
            plugins: { legend: { display: false } }
        }
    }));

    // 7. Payment Methods Doughnut Chart
    const paymentData = <?= i18n_script_json($paymentMethods) ?>;
    const paymentSources = paymentData.map(d => {
        return adminTitleCase(d.payment_method || 'Unknown');
    });
    const paymentLabels = paymentSources.map(adminT);
    const paymentCounts = paymentData.map(d => parseInt(d.count));

    const paymentColors = {
        'Cash': '#00e676',
        'Card': '#00f5d4',
        'Credit': '#667eea',
        'Paypal': '#48dbfb',
        'Bank': '#f093fb',
        'Apple Pay': '#feca57',
        'Google Pay': '#ff6b6b',
        'Cash On Delivery': '#1dd1a1',
        'Credit Card': '#5f27cd',
        'Cod': '#4fc3f7',
        'Unknown': '#636e72'
    };
    const paymentBgColors = paymentSources.map(l => paymentColors[l] || '#4fc3f7');

    const paymentCanvas = document.getElementById('paymentChart');
    if (paymentData.length === 0) {
        const ctx = paymentCanvas.getContext('2d');
        ctx.fillStyle = t.text;
        ctx.font = '14px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(adminT('No order data available yet.') || 'No data available', paymentCanvas.width / 2, paymentCanvas.height / 2);
    } else {

    window.adminCharts.push(new Chart(paymentCanvas, {
        type: 'doughnut',
        data: {
            labels: paymentLabels,
            datasets: [{
                data: paymentCounts,
                backgroundColor: paymentBgColors,
                borderWidth: 2,
                borderColor: t.border,
                hoverOffset: 8,
                hoverBorderWidth: 3,
                hoverBorderColor: t.white
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        padding: 15,
                        font: { size: 11 },
                        color: t.text,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    }));
    } // end else for payment chart

    // 8. Category Performance Radar Chart
    const categoryData = <?= i18n_script_json($categoryPerformance) ?>;
    const categoryLabels = categoryData.map(d => adminCategoryLabels[d.category] || adminT(d.category));
    const categoryRevenues = categoryData.map(d => parseFloat(d.revenue));
    const categoryUnits = categoryData.map(d => parseInt(d.units_sold));

    const maxRevenue = Math.max(...categoryRevenues, 1);
    const maxUnits = Math.max(...categoryUnits, 1);
    const normalizedRevenues = categoryRevenues.map(r => (r / maxRevenue) * 100);
    const normalizedUnits = categoryUnits.map(u => (u / maxUnits) * 100);

    const radarGrid = t.isDark ? 'rgba(255, 255, 255, 0.22)' : 'rgba(15, 23, 42, 0.18)';
    const radarLabels = t.isDark ? '#d0d8e8' : '#475569';
    const radarTicks = t.isDark ? '#b0b8c8' : '#64748B';

    window.adminCharts.push(new Chart(document.getElementById('categoryPerfChart'), {
        type: 'radar',
        data: {
            labels: categoryLabels,
            datasets: [
                {
                    label: adminT('Revenue (Normalized)'),
                    data: normalizedRevenues,
                    backgroundColor: t.cyanFill,
                    borderColor: t.cyan,
                    borderWidth: 2,
                    pointBackgroundColor: t.cyan,
                    pointBorderColor: t.white,
                    pointHoverBackgroundColor: t.white,
                    pointHoverBorderColor: t.cyan
                },
                {
                    label: adminT('Units Sold (Normalized)'),
                    data: normalizedUnits,
                    backgroundColor: t.orangeFill,
                    borderColor: t.orange,
                    borderWidth: 2,
                    pointBackgroundColor: t.orange,
                    pointBorderColor: t.white,
                    pointHoverBackgroundColor: t.white,
                    pointHoverBorderColor: t.orange
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    grid: { color: radarGrid },
                    angleLines: { color: radarGrid },
                    ticks: {
                        color: radarTicks,
                        backdropColor: 'transparent'
                    },
                    pointLabels: {
                        color: radarLabels,
                        font: { size: 11, weight: '600' },
                        callback: function(label) {
                            return label.length > 12 ? label.substring(0, 12) + '...' : label;
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { padding: 15, font: { size: 11 } }
                }
            }
        }
    }));
})();

// Re-render charts when theme changes
document.addEventListener('themeChanged', function() {
    const t = getChartTheme();
    Chart.defaults.color = t.text;
    Chart.defaults.plugins.tooltip.backgroundColor = t.tooltipBg;
    Chart.defaults.plugins.tooltip.titleColor = t.tooltipTitle;
    Chart.defaults.plugins.tooltip.borderColor = t.tooltipBorder;

    window.adminCharts.forEach(chart => {
        if (chart && chart.update) chart.update();
    });
    renderSalesChart();
});

// PNG Export function
function exportChartAsPNG(canvasId, filename) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    const t = getChartTheme();
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = canvas.width;
    tempCanvas.height = canvas.height;
    const ctx = tempCanvas.getContext('2d');

    ctx.fillStyle = t.chartBg;
    ctx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
    ctx.drawImage(canvas, 0, 0);

    const link = document.createElement('a');
    link.download = filename;
    link.href = tempCanvas.toDataURL('image/png');
    link.click();
}
</script>

<?php adminPageEnd(); ?>
