<?php
defined('ABSPATH') || exit;

class DealerCRM_WebshopDetector {

    /**
     * Ensure webshop columns exist on the dealers table.
     */
    public static function ensure_columns() {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_dealers';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);

        if (!in_array('webshop_platform', $columns)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN webshop_platform VARCHAR(100) NULL");
        }
        if (!in_array('webshop_detected_at', $columns)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN webshop_detected_at DATETIME NULL");
        }
        if (!in_array('webshop_status', $columns)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN webshop_status VARCHAR(50) NULL");
        }
    }

    /**
     * Detect webshop platform from a URL.
     *
     * @param string $url
     * @return array ['platform' => string|null, 'status' => string, 'details' => string]
     */
    public static function detect($url) {
        if (empty($url)) {
            return ['platform' => null, 'status' => 'no_website', 'details' => 'Geen website opgegeven.'];
        }

        // Ensure URL has a scheme
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['platform' => null, 'status' => 'error', 'details' => 'Ongeldige URL.'];
        }

        $response = wp_remote_get($url, [
            'timeout'    => 10,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'sslverify'  => false,
        ]);

        if (is_wp_error($response)) {
            return ['platform' => null, 'status' => 'error', 'details' => 'Fout: ' . $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            return ['platform' => null, 'status' => 'error', 'details' => 'HTTP-fout: ' . $code];
        }

        $body = wp_remote_retrieve_body($response);
        $headers_raw = wp_remote_retrieve_headers($response);
        $headers_str = '';
        if (is_object($headers_raw) || is_array($headers_raw)) {
            foreach ($headers_raw as $key => $value) {
                $headers_str .= $key . ': ' . (is_array($value) ? implode(', ', $value) : $value) . "\n";
            }
        }

        $combined = strtolower($body . "\n" . $headers_str);

        // Platform signatures — ordered by specificity (named platforms first)
        // Each signature must be specific enough to avoid false positives
        $platforms = [
            'WooCommerce' => ['wp-content/plugins/woocommerce', 'woocommerce-page', 'is-woocommerce', 'wc-add-to-cart', 'class="woocommerce'],
            'Shopify'     => ['cdn.shopify.com', 'shopify.theme', 'myshopify.com', 'shopify-section', 'shopify.css'],
            'Magento'     => ['x-magento-', 'mage-error', 'magento/framework', '/static/version', 'catalog/product/view'],
            'PrestaShop'  => ['content="prestashop"', 'prestashop.js', '/modules/prestashop', 'prestashop-page'],
            'Lightspeed'  => ['lightspeedhq', 'seoshop.net', 'webshopapp.com', 'lightspeed-checkout'],
            'BigCommerce' => ['cdn.bigcommerce.com', 'bigcommerce.com/cart'],
            'OpenCart'     => ['route=product/', 'route=checkout/', 'catalog/view/theme'],
            'CCV Shop'    => ['ccvshop', 'ccv.eu/shop'],
            'Mijnwebwinkel' => ['mijnwebwinkel.nl', 'mijndomein.nl/shop'],
            'WP E-commerce' => ['easy-digital-downloads', 'wp-content/plugins/ecwid'],
        ];

        // Generic indicators — require at least 2 matches to reduce false positives
        $generic_indicators = [
            'add-to-cart', 'add_to_cart', 'winkelwagen', 'winkelmand',
            'shopping-cart', 'cart-items', 'data-product-id', 'product-price',
            'btn-cart', 'cart-count', 'mini-cart',
        ];

        // Check named platforms — for Magento require 2+ matches due to generic terms
        foreach ($platforms as $name => $signatures) {
            $matches = [];
            foreach ($signatures as $sig) {
                if (strpos($combined, strtolower($sig)) !== false) {
                    $matches[] = $sig;
                }
            }
            if ($name === 'Magento' && count($matches) < 2) continue;
            if (count($matches) > 0) {
                return [
                    'platform' => $name,
                    'status'   => 'detected',
                    'details'  => 'Platform gedetecteerd op basis van: ' . implode(', ', $matches),
                ];
            }
        }

        // Check generic webshop indicators — require at least 2 matches
        $generic_matches = [];
        foreach ($generic_indicators as $indicator) {
            if (strpos($combined, $indicator) !== false) {
                $generic_matches[] = $indicator;
            }
        }
        if (count($generic_matches) >= 2) {
            return [
                'platform' => 'Webshop (onbekend platform)',
                'status'   => 'detected',
                'details'  => 'Webshop-indicatoren gevonden: ' . implode(', ', $generic_matches),
            ];
        }

        return ['platform' => null, 'status' => 'none', 'details' => 'Geen webshop gedetecteerd.'];
    }

    /**
     * Scan a batch of dealers that haven't been scanned yet.
     *
     * @param int $limit
     * @return array ['scanned' => int, 'remaining' => int]
     */
    public static function scan_batch($limit = 5) {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_dealers';

        $dealers = $wpdb->get_results($wpdb->prepare(
            "SELECT id, website FROM {$table}
             WHERE webshop_detected_at IS NULL
               AND website IS NOT NULL AND website != ''
               AND deleted_at IS NULL
             LIMIT %d",
            $limit
        ));

        $scanned = 0;
        foreach ($dealers as $dealer) {
            $result = self::detect($dealer->website);

            $wpdb->update($table, [
                'webshop_platform'    => $result['platform'],
                'webshop_status'      => $result['status'],
                'webshop_detected_at' => current_time('mysql'),
            ], ['id' => $dealer->id]);

            $scanned++;

            // Be polite: 1 second between requests
            if ($scanned < count($dealers)) {
                sleep(1);
            }
        }

        $remaining = self::count_unscanned();

        return ['scanned' => $scanned, 'remaining' => $remaining];
    }

    /**
     * Get scan statistics.
     *
     * @return array
     */
    public static function get_scan_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_dealers';

        $total_with_website = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE website IS NOT NULL AND website != '' AND deleted_at IS NULL"
        );
        $scanned = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE webshop_detected_at IS NOT NULL AND deleted_at IS NULL"
        );
        $detected = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE webshop_status = 'detected' AND deleted_at IS NULL"
        );
        $none = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE webshop_status = 'none' AND deleted_at IS NULL"
        );
        $errors = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE webshop_status = 'error' AND deleted_at IS NULL"
        );
        $unscanned = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table}
             WHERE webshop_detected_at IS NULL
               AND website IS NOT NULL AND website != ''
               AND deleted_at IS NULL"
        );

        return [
            'total_with_website' => $total_with_website,
            'scanned'            => $scanned,
            'detected'           => $detected,
            'none'               => $none,
            'errors'             => $errors,
            'unscanned'          => $unscanned,
        ];
    }

    /**
     * Count dealers with a website that haven't been scanned yet.
     *
     * @return int
     */
    public static function count_unscanned() {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_dealers';
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table}
             WHERE webshop_detected_at IS NULL
               AND website IS NOT NULL AND website != ''
               AND deleted_at IS NULL"
        );
    }

    /**
     * Get dealers filtered by webshop status.
     *
     * @param string $filter 'all', 'detected', 'none', 'unscanned', 'error'
     * @return array
     */
    public static function get_dealers_by_webshop($filter = 'all') {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_dealers';

        $where = '1=1';
        switch ($filter) {
            case 'detected':
                $where = "webshop_status = 'detected'";
                break;
            case 'none':
                $where = "webshop_status = 'none'";
                break;
            case 'unscanned':
                $where = "webshop_detected_at IS NULL AND website IS NOT NULL AND website != ''";
                break;
            case 'error':
                $where = "webshop_status = 'error'";
                break;
            default:
                $where = "website IS NOT NULL AND website != ''";
                break;
        }

        return $wpdb->get_results(
            "SELECT id, name, city, website, webshop_platform, webshop_status, webshop_detected_at
             FROM {$table}
             WHERE {$where}
               AND deleted_at IS NULL
             ORDER BY name ASC"
        );
    }

    /**
     * Get short platform abbreviation for badges.
     */
    public static function get_platform_abbr($platform) {
        if (!$platform) return '';
        $map = [
            'WooCommerce'    => 'Woo',
            'Shopify'        => 'Shopify',
            'Magento'        => 'Magento',
            'PrestaShop'     => 'Presta',
            'Lightspeed'     => 'LS',
            'BigCommerce'    => 'BC',
            'OpenCart'        => 'OC',
            'CCV Shop'       => 'CCV',
            'Mijnwebwinkel'  => 'MWW',
            'WP E-commerce'  => 'WP-EC',
        ];
        return $map[$platform] ?? 'Shop';
    }

    /**
     * Get CSS class suffix for platform badge color.
     */
    public static function get_platform_class($platform) {
        if (!$platform) return '';
        $map = [
            'WooCommerce'    => 'woocommerce',
            'Shopify'        => 'shopify',
            'Magento'        => 'magento',
            'PrestaShop'     => 'prestashop',
            'Lightspeed'     => 'lightspeed',
            'BigCommerce'    => 'bigcommerce',
            'OpenCart'        => 'opencart',
            'CCV Shop'       => 'ccvshop',
            'Mijnwebwinkel'  => 'mijnwebwinkel',
            'WP E-commerce'  => 'wp-ecommerce',
        ];
        return $map[$platform] ?? 'generic';
    }
}
