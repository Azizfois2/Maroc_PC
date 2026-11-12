<?php
/**
 * Admin Auto-Optimizer
 * Automatically optimizes ALL admin pages with:
 * - Query result caching
 * - Pagination
 * - Lazy loading
 * - Performance monitoring
 * 
 * Include this file in admin-helpers.php to apply optimizations globally
 */

// ============================================
// AUTOMATIC PAGINATION FOR ADMIN QUERIES
// ============================================

function adminPaginatedQuery(PDO $pdo, string $sql, array $params = [], int $perPage = 50): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;
    
    // Get total count
    $countSql = preg_replace('/SELECT .+ FROM/i', 'SELECT COUNT(*) as total FROM', $sql);
    $countSql = preg_replace('/ORDER BY .+$/i', '', $countSql);
    $countSql = preg_replace('/LIMIT .+$/i', '', $countSql);
    
    $totalStmt = $pdo->prepare($countSql);
    $totalStmt->execute($params);
    $total = (int) $totalStmt->fetchColumn();
    
    // Get paginated results
    $paginatedSql = $sql . " LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($paginatedSql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    return [
        'data' => $results,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage),
            'has_prev' => $page > 1,
            'has_next' => $page < ceil($total / $perPage),
        ]
    ];
}

// ============================================
// RENDER PAGINATION HTML
// ============================================

function adminRenderPagination(array $pagination, string $baseUrl = ''): string
{
    if ($pagination['total_pages'] <= 1) {
        return '';
    }
    
    $current = $pagination['current_page'];
    $total = $pagination['total_pages'];
    
    // Parse existing query string
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    parse_str($queryString, $queryParams);
    unset($queryParams['page']); // Remove existing page param
    
    $baseUrl = $baseUrl ?: $_SERVER['PHP_SELF'];
    $queryBase = http_build_query($queryParams);
    $connector = $queryBase ? '&' : '';
    
    $html = '<div class="pagination">';
    
    // Previous button
    if ($pagination['has_prev']) {
        $prevPage = $current - 1;
        $html .= "<a href=\"{$baseUrl}?{$queryBase}{$connector}page={$prevPage}\" class=\"button button-light button-small\">
                    <i class=\"fas fa-chevron-left\"></i> " . adminPhrase('Previous') . "
                  </a>";
    }
    
    // Page numbers
    $start = max(1, $current - 2);
    $end = min($total, $current + 2);
    
    if ($start > 1) {
        $html .= "<a href=\"{$baseUrl}?{$queryBase}{$connector}page=1\">1</a>";
        if ($start > 2) {
            $html .= "<span>...</span>";
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $current ? ' active' : '';
        $html .= "<a href=\"{$baseUrl}?{$queryBase}{$connector}page={$i}\" class=\"{$active}\">{$i}</a>";
    }
    
    if ($end < $total) {
        if ($end < $total - 1) {
            $html .= "<span>...</span>";
        }
        $html .= "<a href=\"{$baseUrl}?{$queryBase}{$connector}page={$total}\">{$total}</a>";
    }
    
    // Next button
    if ($pagination['has_next']) {
        $nextPage = $current + 1;
        $html .= "<a href=\"{$baseUrl}?{$queryBase}{$connector}page={$nextPage}\" class=\"button button-light button-small\">
                    " . adminPhrase('Next') . " <i class=\"fas fa-chevron-right\"></i>
                  </a>";
    }
    
    $html .= '</div>';
    
    // Add pagination info
    $showing = min($pagination['per_page'] * $current, $pagination['total']);
    $start = ($current - 1) * $pagination['per_page'] + 1;
    $html .= "<div class=\"pagination-info\">" . 
             adminPhrase('Showing {start} to {showing} of {total} entries', [
                 'start' => $start,
                 'showing' => $showing,
                 'total' => $pagination['total']
             ]) . 
             "</div>";
    
    return $html;
}

// ============================================
// BATCH QUERY OPTIMIZER
// ============================================

function adminBatchFetch(PDO $pdo, array $queries): array
{
    $results = [];
    
    foreach ($queries as $key => $query) {
        $cacheKey = 'batch_' . md5($query['sql'] . json_encode($query['params'] ?? []));
        
        $results[$key] = adminCachedFetch($pdo, $cacheKey, function() use ($pdo, $query) {
            if (stripos($query['sql'], 'SELECT COUNT') !== false || 
                stripos($query['sql'], 'SELECT COALESCE') !== false ||
                stripos($query['sql'], 'SELECT SUM') !== false ||
                stripos($query['sql'], 'SELECT AVG') !== false) {
                return adminFetchValue($pdo, $query['sql'], $query['params'] ?? []);
            }
            return adminFetchAll($pdo, $query['sql'], $query['params'] ?? []);
        }, $query['ttl'] ?? 300);
    }
    
    return $results;
}

// ============================================
// OPTIMIZED STATS CALCULATOR
// ============================================

function adminGetOptimizedStats(PDO $pdo, string $cacheKey, array $statsQueries, int $ttl = 300): array
{
    return adminCachedFetch($pdo, $cacheKey, function() use ($pdo, $statsQueries) {
        $stats = [];
        
        foreach ($statsQueries as $key => $query) {
            try {
                if (is_callable($query)) {
                    $stats[$key] = $query();
                } else {
                    $stmt = $pdo->query($query);
                    $stats[$key] = $stmt->fetchColumn();
                }
            } catch (Throwable $e) {
                $stats[$key] = 0;
            }
        }
        
        return $stats;
    }, $ttl);
}

// ============================================
// LAZY TABLE DATA ATTRIBUTE
// ============================================

function adminLazyTable(string $tableId = '', int $batchSize = 20): string
{
    return "data-lazy-table=\"true\" data-batch-size=\"{$batchSize}\" id=\"{$tableId}\"";
}

// ============================================
// CACHE INVALIDATION HELPERS
// ============================================

function adminInvalidateProductCache(): void
{
    adminClearCache('dashboard_stats');
    adminClearCache('all_products');
    adminClearCache('flash_sales');
    adminClearCache('recent_products');
    adminClearCache('low_stock');
    adminClearCache('admin_sidebar_counts');
    adminClearCache('batch_');
}

function adminInvalidateOrderCache(): void
{
    adminClearCache('dashboard_stats');
    adminClearCache('recent_orders');
    adminClearCache('order_stats');
    adminClearCache('admin_sidebar_counts');
    adminClearCache('batch_');
}

function adminInvalidateCustomerCache(): void
{
    adminClearCache('dashboard_stats');
    adminClearCache('customer_stats');
    adminClearCache('recent_customers');
    adminClearCache('admin_sidebar_counts');
    adminClearCache('batch_');
}

function adminInvalidateStockCache(): void
{
    adminClearCache('dashboard_stats');
    adminClearCache('stock_stats');
    adminClearCache('reorder_products');
    adminClearCache('admin_sidebar_counts');
    adminClearCache('batch_');
}

// ============================================
// AUTOMATIC INDEX OPTIMIZATION
// ============================================

function adminEnsureOptimalIndexes(PDO $pdo): void
{
    static $optimized = false;
    if ($optimized) return;

    // `CREATE INDEX IF NOT EXISTS` is MariaDB-only and silently fails on MySQL,
    // so inspect information_schema first and create only what is missing.
    $wanted = [
        'products' => [
            'idx_products_stock' => 'stock_quantity, reorder_level',
            'idx_products_category' => 'category',
            'idx_products_created' => 'created_at',
        ],
        'orders' => [
            'idx_orders_status' => 'status',
            'idx_orders_created' => 'created_at',
            'idx_orders_client' => 'client_id',
            'idx_orders_created_status' => 'created_at, status',
        ],
        'Client' => [
            'idx_client_email' => 'email',
            'idx_client_created' => 'created_at',
        ],
        'laptops' => [
            'idx_laptops_stock' => 'stock_quantity, reorder_level',
            'idx_laptops_created' => 'created_at',
        ],
        'scheduled_emails' => [
            'idx_scheduled_status' => 'status',
        ],
        'restock_notifications' => [
            'idx_restock_notified' => 'notified',
        ],
        'support_requests' => [
            'idx_support_status' => 'status',
        ],
        'supplier_orders' => [
            'idx_supplier_orders_status' => 'status',
        ],
    ];

    try {
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $tables = array_map('strval', $tables);

        $existing = [];
        foreach ($pdo->query('SELECT TABLE_NAME, INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA = DATABASE()')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existing[$row['TABLE_NAME'] . '.' . $row['INDEX_NAME']] = true;
        }

        foreach ($wanted as $table => $indexes) {
            if (!in_array($table, $tables, true)) {
                continue;
            }
            foreach ($indexes as $name => $columns) {
                if (isset($existing[$table . '.' . $name])) {
                    continue;
                }
                try {
                    $pdo->exec("CREATE INDEX `{$name}` ON `{$table}` ({$columns})");
                } catch (Throwable $e) {
                    // Another request may have created it, or the user lacks ALTER.
                }
            }
        }

        $optimized = true;
    } catch (Throwable $e) {
        // Silently fail - indexes might already exist
    }
}

// ============================================
// AUTO-APPLY OPTIMIZATIONS
// ============================================

// Index creation is intentionally NOT run on include. It used to fire ten
// CREATE INDEX statements on every request (admin and storefront alike), which
// is pure overhead once the indexes exist. It now runs as part of the one-time
// schema bootstrap in adminEnsureAdminSuiteTables().
