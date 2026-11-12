<?php
/**
 * Visitor Tracker Middleware
 *
 * Lightweight include that runs on every storefront page:
 *  - Extracts client IP (proxy-aware)
 *  - Parses User-Agent for browser, OS, device
 *  - Checks IP / country block lists (redirects to 403 if blocked)
 *  - Inserts a row into visitor_logs
 *
 * Usage: require_once from includes/store-head.php (inside storeHead()).
 */

if (!function_exists('visitorTrackerBoot')) {

    /**
     * Resolve the real client IP, respecting common proxy headers.
     */
    function visitorGetClientIp(): string
    {
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $trustedProxyIps = array_filter(array_map('trim', explode(',', defined('TRUSTED_PROXY_IPS') ? (string) TRUSTED_PROXY_IPS : '')));
        $remoteIsLocal = filter_var($remoteIp, FILTER_VALIDATE_IP)
            && !filter_var($remoteIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        $remoteIsTrustedProxy = $remoteIsLocal || in_array($remoteIp, $trustedProxyIps, true);

        if ($remoteIsTrustedProxy) {
            $headers = [
                'HTTP_CF_CONNECTING_IP',   // Cloudflare, when your proxy is trusted
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_REAL_IP',
                'HTTP_CLIENT_IP',
            ];
            foreach ($headers as $h) {
                if (!empty($_SERVER[$h])) {
                    $ip = trim(explode(',', (string) $_SERVER[$h])[0]);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }

        return $remoteIp;
    }

    /**
     * Parse User-Agent into browser, OS, device type.
     */
    function visitorParseUserAgent(string $ua): array
    {
        $browser = 'Unknown';
        $browserVersion = '';
        $os = 'Unknown';
        $osVersion = '';
        $deviceType = 'desktop';
        $isBot = false;

        // Bot detection
        $botPatterns = [
            'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
            'googlebot', 'bingbot', 'baiduspider', 'yandex', 'duckduckbot',
            'facebookexternalhit', 'twitterbot', 'linkedinbot', 'semrushbot',
            'ahrefsbot', 'mj12bot', 'dotbot', 'petalbot',
        ];
        $uaLower = strtolower($ua);
        foreach ($botPatterns as $pattern) {
            if (str_contains($uaLower, $pattern)) {
                $isBot = true;
                $browser = 'Bot';
                break;
            }
        }

        if (!$isBot) {
            // Browser detection
            if (preg_match('/Edg\/([\d.]+)/i', $ua, $m)) {
                $browser = 'Edge';
                $browserVersion = $m[1];
            } elseif (preg_match('/OPR\/([\d.]+)/i', $ua, $m)) {
                $browser = 'Opera';
                $browserVersion = $m[1];
            } elseif (preg_match('/Chrome\/([\d.]+)/i', $ua, $m) && !str_contains($uaLower, 'edg')) {
                $browser = 'Chrome';
                $browserVersion = $m[1];
            } elseif (preg_match('/Firefox\/([\d.]+)/i', $ua, $m)) {
                $browser = 'Firefox';
                $browserVersion = $m[1];
            } elseif (preg_match('/Version\/([\d.]+).*Safari/i', $ua, $m)) {
                $browser = 'Safari';
                $browserVersion = $m[1];
            } elseif (preg_match('/Safari\/([\d.]+)/i', $ua, $m)) {
                $browser = 'Safari';
                $browserVersion = $m[1];
            } elseif (preg_match('/MSIE|Trident.*rv:([\d.]+)/i', $ua, $m)) {
                $browser = 'IE';
                $browserVersion = $m[1] ?? '';
            }

            // OS detection
            if (preg_match('/Windows NT ([\d.]+)/i', $ua, $m)) {
                $os = 'Windows';
                $ntVersions = ['10.0' => '10/11', '6.3' => '8.1', '6.2' => '8', '6.1' => '7'];
                $osVersion = $ntVersions[$m[1]] ?? $m[1];
            } elseif (preg_match('/Mac OS X ([\d_.]+)/i', $ua, $m)) {
                $os = 'macOS';
                $osVersion = str_replace('_', '.', $m[1]);
            } elseif (preg_match('/Android ([\d.]+)/i', $ua, $m)) {
                $os = 'Android';
                $osVersion = $m[1];
            } elseif (preg_match('/iPhone OS ([\d_]+)/i', $ua, $m)) {
                $os = 'iOS';
                $osVersion = str_replace('_', '.', $m[1]);
            } elseif (preg_match('/iPad.*OS ([\d_]+)/i', $ua, $m)) {
                $os = 'iPadOS';
                $osVersion = str_replace('_', '.', $m[1]);
            } elseif (preg_match('/Linux/i', $ua)) {
                $os = 'Linux';
            } elseif (preg_match('/CrOS/i', $ua)) {
                $os = 'Chrome OS';
            }

            // Device type
            if (preg_match('/Mobile|Android.*Mobile|iPhone|iPod/i', $ua)) {
                $deviceType = 'mobile';
            } elseif (preg_match('/Tablet|iPad|Android(?!.*Mobile)/i', $ua)) {
                $deviceType = 'tablet';
            }
        }

        return [
            'browser' => substr($browser, 0, 64),
            'browser_version' => substr($browserVersion, 0, 32),
            'os' => substr($os, 0, 64),
            'os_version' => substr($osVersion, 0, 32),
            'device_type' => $deviceType,
            'is_bot' => $isBot ? 1 : 0,
        ];
    }

    /**
     * Check if an IP or its country is blocked.
     * Returns null if allowed, or a reason string if blocked.
     */
    function visitorIpMatchesCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bits = max(0, min((int) $bits, strlen($ipBin) * 8));
        $fullBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        return (ord($ipBin[$fullBytes]) & $mask) === (ord($subnetBin[$fullBytes]) & $mask);
    }

    function visitorNetworkBlockTypes(PDO $pdo, string $ip): array
    {
        $matches = [];
        try {
            $rows = $pdo->query("SELECT block_type, cidr FROM visitor_network_blocks WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                if (visitorIpMatchesCidr($ip, (string) $row['cidr'])) {
                    $matches[] = (string) $row['block_type'];
                }
            }
        } catch (Throwable $e) {
            // Non-blocking
        }
        return array_values(array_unique($matches));
    }

    function visitorNetworkProfile(PDO $pdo, string $ip, array $geo): array
    {
        $flags = [];
        $isp = strtolower((string) ($geo['isp'] ?? ''));
        $matchedTypes = visitorNetworkBlockTypes($pdo, $ip);

        if (!empty($geo['is_proxy'])) {
            $flags[] = 'proxy';
        }
        if (!empty($geo['is_hosting'])) {
            $flags[] = 'hosting';
        }
        foreach ($matchedTypes as $type) {
            $flags[] = $type;
        }

        $vpnHints = ['vpn', 'nordvpn', 'expressvpn', 'surfshark', 'proton', 'mullvad', 'private internet access', 'cyberghost', 'windscribe'];
        foreach ($vpnHints as $hint) {
            if ($hint !== '' && str_contains($isp, $hint)) {
                $flags[] = 'vpn';
                break;
            }
        }

        $proxyHints = ['proxy', 'anonymizer', 'hide my', 'privacy'];
        foreach ($proxyHints as $hint) {
            if ($hint !== '' && str_contains($isp, $hint)) {
                $flags[] = 'proxy';
                break;
            }
        }

        $hostingHints = ['hosting', 'datacenter', 'data center', 'cloud', 'server', 'digitalocean', 'ovh', 'hetzner', 'linode', 'vultr', 'aws', 'amazon', 'google cloud', 'azure'];
        foreach ($hostingHints as $hint) {
            if ($hint !== '' && str_contains($isp, $hint)) {
                $flags[] = 'hosting';
                break;
            }
        }

        if (str_contains($isp, 'tor') || in_array('tor', $matchedTypes, true)) {
            $flags[] = 'tor';
        }

        $flags = array_values(array_unique($flags));

        return [
            'is_proxy' => in_array('proxy', $flags, true) ? 1 : 0,
            'is_vpn' => in_array('vpn', $flags, true) ? 1 : 0,
            'is_tor' => in_array('tor', $flags, true) ? 1 : 0,
            'is_hosting' => in_array('hosting', $flags, true) ? 1 : 0,
            'network_flags' => $flags !== [] ? implode(',', $flags) : null,
        ];
    }

    function visitorSettingEnabled(PDO $pdo, string $key): bool
    {
        try {
            if (function_exists('adminSetting')) {
                return (string) adminSetting($pdo, $key, '0') === '1';
            }
            $stmt = $pdo->prepare('SELECT setting_value FROM admin_settings WHERE setting_key = ?');
            $stmt->execute([$key]);
            return (string) $stmt->fetchColumn() === '1';
        } catch (Throwable $e) {
            return false;
        }
    }

    function visitorCheckBlocked(PDO $pdo, string $ip, ?string $countryCode, ?array $networkProfile = null): ?string
    {
        // Check IP block list
        try {
            $stmt = $pdo->prepare('SELECT reason, expires_at FROM ip_blocks WHERE ip_address = ?');
            $stmt->execute([$ip]);
            $block = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($block) {
                // Check if expired
                if ($block['expires_at'] !== null && strtotime($block['expires_at']) < time()) {
                    // Expired — remove it
                    $pdo->prepare('DELETE FROM ip_blocks WHERE ip_address = ?')->execute([$ip]);
                } else {
                    return $block['reason'] ?: 'IP address is blocked.';
                }
            }
        } catch (Throwable $e) {
            // Non-blocking
        }

        // Check country block list
        if ($countryCode !== null && $countryCode !== '') {
            try {
                $stmt = $pdo->prepare('SELECT reason, country_name FROM country_blocks WHERE country_code = ?');
                $stmt->execute([$countryCode]);
                $cBlock = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($cBlock) {
                    return ($cBlock['reason'] ?: 'Access from ' . $cBlock['country_name'] . ' is not available.');
                }
            } catch (Throwable $e) {
                // Non-blocking
            }
        }

        if ($networkProfile !== null) {
            $networkRules = [
                'visitor_block_tor' => ['field' => 'is_tor', 'label' => 'Tor network access is blocked.'],
                'visitor_block_vpn' => ['field' => 'is_vpn', 'label' => 'VPN network access is blocked.'],
                'visitor_block_proxy' => ['field' => 'is_proxy', 'label' => 'Proxy network access is blocked.'],
                'visitor_block_hosting' => ['field' => 'is_hosting', 'label' => 'Hosting/datacenter network access is blocked.'],
            ];
            foreach ($networkRules as $setting => $rule) {
                if (!empty($networkProfile[$rule['field']]) && visitorSettingEnabled($pdo, $setting)) {
                    return $rule['label'];
                }
            }
        }

        return null;
    }

    /**
     * Look up GeoIP from cache or ip-api.com.
     */
    function visitorGeoLookup(PDO $pdo, string $ip): array
    {
        // Check cache first (valid for 7 days)
        try {
            $stmt = $pdo->prepare('SELECT * FROM geoip_cache WHERE ip_address = ? AND fetched_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
            $stmt->execute([$ip]);
            $cached = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cached) {
                return [
                'country' => $cached['country'],
                'country_code' => $cached['country_code'],
                'city' => $cached['city'],
                'isp' => $cached['isp'],
                'latitude' => $cached['latitude'],
                'longitude' => $cached['longitude'],
                'is_proxy' => (int) ($cached['is_proxy'] ?? 0),
                'is_hosting' => (int) ($cached['is_hosting'] ?? 0),
                'is_mobile' => (int) ($cached['is_mobile'] ?? 0),
            ];
            }
        } catch (Throwable $e) {
            // Table might not exist yet
        }

        // Skip private/local IPs
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return ['country' => null, 'country_code' => null, 'city' => null, 'isp' => null, 'latitude' => null, 'longitude' => null, 'is_proxy' => 0, 'is_hosting' => 0, 'is_mobile' => 0];
        }

        // Call ip-api.com (free tier: 45 req/min)
        $geo = ['country' => null, 'country_code' => null, 'city' => null, 'isp' => null, 'latitude' => null, 'longitude' => null, 'is_proxy' => 0, 'is_hosting' => 0, 'is_mobile' => 0];
        try {
            $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,message,country,countryCode,city,isp,lat,lon,proxy,hosting,mobile';
            $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
            $response = @file_get_contents($url, false, $ctx);
            if ($response !== false) {
                $data = json_decode($response, true);
                if (isset($data['status']) && $data['status'] === 'success') {
                    $geo = [
                        'country' => substr((string) ($data['country'] ?? ''), 0, 64),
                        'country_code' => substr((string) ($data['countryCode'] ?? ''), 0, 3),
                        'city' => substr((string) ($data['city'] ?? ''), 0, 128),
                        'isp' => substr((string) ($data['isp'] ?? ''), 0, 128),
                        'latitude' => $data['lat'] ?? null,
                        'longitude' => $data['lon'] ?? null,
                        'is_proxy' => !empty($data['proxy']) ? 1 : 0,
                        'is_hosting' => !empty($data['hosting']) ? 1 : 0,
                        'is_mobile' => !empty($data['mobile']) ? 1 : 0,
                    ];
                }
            }
        } catch (Throwable $e) {
            // Silently fail
        }

        // Cache the result
        try {
            $pdo->prepare('
                INSERT INTO geoip_cache (ip_address, country, country_code, city, isp, latitude, longitude, is_proxy, is_hosting, is_mobile, fetched_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE country=VALUES(country), country_code=VALUES(country_code),
                    city=VALUES(city), isp=VALUES(isp), latitude=VALUES(latitude), longitude=VALUES(longitude),
                    is_proxy=VALUES(is_proxy), is_hosting=VALUES(is_hosting), is_mobile=VALUES(is_mobile), fetched_at=NOW()
            ')->execute([
                $ip, $geo['country'], $geo['country_code'], $geo['city'],
                $geo['isp'], $geo['latitude'], $geo['longitude'],
                $geo['is_proxy'], $geo['is_hosting'], $geo['is_mobile'],
            ]);
        } catch (Throwable $e) {
            // Non-blocking
        }

        return $geo;
    }

    /**
     * Main boot — call once per page load.
     */
    function visitorTrackerBoot(): void
    {
        // Avoid tracking admin pages or API calls
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (str_contains($script, '/admin') || str_contains($script, '/api/') || str_contains($script, '/scratch/')) {
            return;
        }

        try {
            $pdo = db();

            // Ensure tables exist (runs only CREATE IF NOT EXISTS — very fast)
            if (function_exists('adminEnsureAdminSuiteTables')) {
                adminEnsureAdminSuiteTables($pdo);
            }
            if (function_exists('adminVisitorArchiveOldLogs')) {
                // Archiving is maintenance work, not page rendering: check at most
                // once every five minutes per session instead of on every view.
                $lastArchiveCheck = (int) ($_SESSION['visitor_archive_checked_at'] ?? 0);
                if ($lastArchiveCheck < time() - 300) {
                    adminVisitorArchiveOldLogs($pdo);
                    $_SESSION['visitor_archive_checked_at'] = time();
                }
            }

            $ip = visitorGetClientIp();
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
            $parsed = visitorParseUserAgent($ua);

            // Get GeoIP (also used for country-block check)
            $geo = visitorGeoLookup($pdo, $ip);
            $network = visitorNetworkProfile($pdo, $ip, $geo);

            // Check if blocked
            $blockReason = visitorCheckBlocked($pdo, $ip, $geo['country_code'], $network);
            if ($blockReason !== null) {
                // Log the blocked visit attempt
                try {
                    $pdo->prepare('
                        INSERT INTO visitor_logs (ip_address, user_agent, browser, browser_version, os, os_version,
                            device_type, language, referrer, page_url, country, country_code, city, isp,
                            latitude, longitude, is_bot, is_proxy, is_vpn, is_tor, is_hosting, network_flags, visited_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ')->execute([
                        $ip, $ua, $parsed['browser'], $parsed['browser_version'],
                        $parsed['os'], $parsed['os_version'], $parsed['device_type'],
                        substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 16),
                        substr($_SERVER['HTTP_REFERER'] ?? '', 0, 512),
                        substr($_SERVER['REQUEST_URI'] ?? '', 0, 512),
                        $geo['country'], $geo['country_code'], $geo['city'], $geo['isp'],
                        $geo['latitude'], $geo['longitude'], $parsed['is_bot'],
                        $network['is_proxy'], $network['is_vpn'], $network['is_tor'],
                        $network['is_hosting'], $network['network_flags'],
                    ]);
                } catch (Throwable $e) {}

                http_response_code(403);
                // Store reason for the 403 page
                $GLOBALS['__visitor_block_reason'] = $blockReason;
                if (!headers_sent()) {
                    header('Location: 403.php?blocked=1');
                }
                exit;
            }

            // Skip logging bot traffic to keep analytics clean (optional: set to false to log bots)
            if ($parsed['is_bot']) {
                return;
            }

            // Store visitor log entry (fingerprint will be filled in later via JS)
            $pageUrl = substr(($_SERVER['REQUEST_URI'] ?? '/'), 0, 512);
            $referrer = substr(($_SERVER['HTTP_REFERER'] ?? ''), 0, 512);
            $language = substr(($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 16);
            $sessionId = session_id() ?: bin2hex(random_bytes(16));

            $stmt = $pdo->prepare('
                INSERT INTO visitor_logs (ip_address, session_id, user_agent, browser, browser_version,
                    os, os_version, device_type, language, referrer, page_url,
                    country, country_code, city, isp, latitude, longitude, is_bot,
                    is_proxy, is_vpn, is_tor, is_hosting, network_flags, visited_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $ip, $sessionId, $ua, $parsed['browser'], $parsed['browser_version'],
                $parsed['os'], $parsed['os_version'], $parsed['device_type'],
                $language, $referrer, $pageUrl,
                $geo['country'], $geo['country_code'], $geo['city'], $geo['isp'],
                $geo['latitude'], $geo['longitude'], 0,
                $network['is_proxy'], $network['is_vpn'], $network['is_tor'],
                $network['is_hosting'], $network['network_flags'],
            ]);

            // Store the log ID in session so the fingerprint JS can update it
            $_SESSION['__visitor_log_id'] = (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            // Visitor tracking must never break the page
            if (defined('DEV_MODE') && DEV_MODE) {
                error_log('[VisitorTracker] ' . $e->getMessage());
            }
        }
    }
}
