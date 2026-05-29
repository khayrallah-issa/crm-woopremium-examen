<?php
defined('ABSPATH') || exit;

class DealerCRM_Campaigns {

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $p = $wpdb->prefix . 'crm_';

        $sqls = [
            "CREATE TABLE IF NOT EXISTS {$p}campaigns (
                id BIGINT UNSIGNED AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                status VARCHAR(20) DEFAULT 'concept',
                created_by BIGINT UNSIGNED NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset",

            "CREATE TABLE IF NOT EXISTS {$p}campaign_dealer (
                campaign_id BIGINT UNSIGNED NOT NULL,
                dealer_id BIGINT UNSIGNED NOT NULL,
                added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (campaign_id, dealer_id),
                KEY idx_dealer (dealer_id)
            ) $charset",
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sqls as $sql) {
            dbDelta($sql);
        }
    }

    // ── CRUD ──

    public static function create_campaign($data) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        $wpdb->insert($p . 'campaigns', [
            'name'        => $data['name'],
            'description' => $data['description'] ?? '',
            'status'      => $data['status'] ?? 'concept',
            'created_by'  => get_current_user_id(),
            'created_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql'),
        ]);

        return $wpdb->insert_id;
    }

    public static function update_campaign($id, $data) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';
        $data['updated_at'] = current_time('mysql');
        $wpdb->update($p . 'campaigns', $data, ['id' => $id]);
    }

    public static function get_campaign($id) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, (SELECT COUNT(*) FROM {$p}campaign_dealer cd WHERE cd.campaign_id = c.id) AS dealer_count
            FROM {$p}campaigns c WHERE c.id = %d",
            $id
        ));
    }

    public static function get_all_campaigns($args = []) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        $where = '1=1';
        $params = [];

        if (!empty($args['status'])) {
            $where .= ' AND c.status = %s';
            $params[] = $args['status'];
        }

        $sql = "SELECT c.*, COALESCE(dc.cnt, 0) AS dealer_count
                FROM {$p}campaigns c
                LEFT JOIN (SELECT campaign_id, COUNT(*) AS cnt FROM {$p}campaign_dealer GROUP BY campaign_id) dc ON dc.campaign_id = c.id
                WHERE {$where}
                ORDER BY c.created_at DESC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        return $wpdb->get_results($sql);
    }

    public static function delete_campaign($id) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';
        $wpdb->delete($p . 'campaign_dealer', ['campaign_id' => $id]);
        $wpdb->delete($p . 'campaigns', ['id' => $id]);
    }

    // ── Dealer management ──

    public static function add_dealers($campaign_id, $dealer_ids) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';
        $added = 0;

        foreach ($dealer_ids as $dealer_id) {
            $dealer_id = (int) $dealer_id;
            if (!$dealer_id) continue;

            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM {$p}campaign_dealer WHERE campaign_id = %d AND dealer_id = %d",
                $campaign_id, $dealer_id
            ));
            if (!$exists) {
                $wpdb->insert($p . 'campaign_dealer', [
                    'campaign_id' => $campaign_id,
                    'dealer_id'   => $dealer_id,
                    'added_at'    => current_time('mysql'),
                ]);
                $added++;
            }
        }

        return $added;
    }

    public static function remove_dealer($campaign_id, $dealer_id) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';
        $wpdb->delete($p . 'campaign_dealer', [
            'campaign_id' => $campaign_id,
            'dealer_id'   => $dealer_id,
        ]);
    }

    public static function get_campaign_dealers($campaign_id) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT d.id, d.name, d.contact_person, d.owner, d.city, d.email, d.phone, d.status, d.street, d.postcode, d.website, d.lat, d.lng
            FROM {$p}dealers d
            INNER JOIN {$p}campaign_dealer cd ON cd.dealer_id = d.id
            WHERE cd.campaign_id = %d
              AND d.deleted_at IS NULL
            ORDER BY d.name ASC",
            $campaign_id
        ));
    }

    /**
     * Search dealers for adding to campaign, excluding already-added ones.
     */
    public static function search_dealers_for_campaign($campaign_id, $filters) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        $where = ['1=1', 'd.deleted_at IS NULL'];
        $params = [];
        $joins = '';

        // Exclude already in campaign
        $where[] = "d.id NOT IN (SELECT dealer_id FROM {$p}campaign_dealer WHERE campaign_id = %d)";
        $params[] = $campaign_id;

        if (!empty($filters['search'])) {
            $like = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where[] = "(d.name LIKE %s OR d.email LIKE %s OR d.city LIKE %s OR d.phone LIKE %s)";
            $params = array_merge($params, [$like, $like, $like, $like]);
        }
        if (!empty($filters['city'])) {
            $where[] = "d.city = %s";
            $params[] = $filters['city'];
        }
        if (!empty($filters['status'])) {
            $where[] = "d.status = %s";
            $params[] = $filters['status'];
        }
        if (!empty($filters['webshop'])) {
            if ($filters['webshop'] === 'yes') {
                $where[] = "d.webshop_status = 'detected'";
            } else {
                $where[] = "(d.webshop_status IS NULL OR d.webshop_status != 'detected')";
            }
        }
        if (!empty($filters['brand'])) {
            $joins .= " INNER JOIN {$p}dealer_brand db ON d.id = db.dealer_id
                         INNER JOIN {$p}brands b ON db.brand_id = b.id AND b.name = %s";
            $params[] = $filters['brand'];
        }
        if (!empty($filters['tag_id'])) {
            $joins .= " INNER JOIN {$p}dealer_tag dt ON d.id = dt.dealer_id AND dt.tag_id = %d";
            $params[] = (int) $filters['tag_id'];
        }

        $where_sql = implode(' AND ', $where);

        $sql = "SELECT DISTINCT d.id, d.name, d.city, d.email, d.status
                FROM {$p}dealers d {$joins}
                WHERE {$where_sql}
                ORDER BY d.name ASC
                LIMIT 200";

        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }

    // ── Stats ──

    public static function get_stats() {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        $row = $wpdb->get_row(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'actief' THEN 1 ELSE 0 END) AS actief,
                SUM(CASE WHEN status = 'afgerond' THEN 1 ELSE 0 END) AS afgerond
            FROM {$p}campaigns"
        );

        return [
            'total'    => (int) ($row->total ?? 0),
            'actief'   => (int) ($row->actief ?? 0),
            'afgerond' => (int) ($row->afgerond ?? 0),
        ];
    }

    // ── CSV Export ──

    public static function export_csv($campaign_id) {
        $campaign = self::get_campaign($campaign_id);
        if (!$campaign) return;

        $dealers = self::get_campaign_dealers($campaign_id);

        $filename = sanitize_file_name('campagne-' . $campaign->name . '-' . date('Y-m-d') . '.csv');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        // BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, ['Naam', 'Straat', 'Postcode', 'Plaats', 'Telefoon', 'E-mail', 'Website', 'Status'], ';');

        foreach ($dealers as $d) {
            fputcsv($output, [
                $d->name,
                $d->street ?? '',
                $d->postcode ?? '',
                $d->city ?? '',
                $d->phone ?? '',
                $d->email ?? '',
                $d->website ?? '',
                $d->status ?? '',
            ], ';');
        }

        fclose($output);
        exit;
    }
}
