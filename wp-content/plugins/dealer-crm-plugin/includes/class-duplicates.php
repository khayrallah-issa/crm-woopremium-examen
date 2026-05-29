<?php
defined('ABSPATH') || exit;

class DealerCRM_Duplicates {

    public static function create_table() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'crm_dismissed_duplicates';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            dealer_id_1 BIGINT UNSIGNED NOT NULL,
            dealer_id_2 BIGINT UNSIGNED NOT NULL,
            dismissed_by BIGINT UNSIGNED NOT NULL,
            dismissed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY idx_pair (dealer_id_1, dealer_id_2)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Find potential duplicate dealers.
     */
    public static function find_duplicates($limit = 50) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';
        $dismissed = $wpdb->prefix . 'crm_dismissed_duplicates';

        $duplicates = [];
        $seen_pairs = [];

        // 1. Exact email match
        $email_dupes = $wpdb->get_results($wpdb->prepare(
            "SELECT d1.id as id1, d1.name as name1, d1.email as email1, d1.city as city1, d1.phone as phone1,
                    d2.id as id2, d2.name as name2, d2.email as email2, d2.city as city2, d2.phone as phone2
             FROM {$p}dealers d1
             INNER JOIN {$p}dealers d2 ON d1.email = d2.email AND d1.id < d2.id
             WHERE d1.email IS NOT NULL AND d1.email != ''
               AND d1.deleted_at IS NULL AND d2.deleted_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM {$dismissed} dd
                   WHERE (dd.dealer_id_1 = d1.id AND dd.dealer_id_2 = d2.id)
                      OR (dd.dealer_id_1 = d2.id AND dd.dealer_id_2 = d1.id)
               )
             LIMIT %d",
            $limit
        ));

        foreach (($email_dupes ?: []) as $row) {
            $key = min($row->id1, $row->id2) . '-' . max($row->id1, $row->id2);
            if (!isset($seen_pairs[$key])) {
                $seen_pairs[$key] = true;
                $duplicates[] = (object) [
                    'id1'    => $row->id1,
                    'name1'  => $row->name1,
                    'city1'  => $row->city1,
                    'email1' => $row->email1,
                    'phone1' => $row->phone1,
                    'id2'    => $row->id2,
                    'name2'  => $row->name2,
                    'city2'  => $row->city2,
                    'email2' => $row->email2,
                    'phone2' => $row->phone2,
                    'reason' => 'E-mail',
                    'match'  => $row->email1,
                ];
            }
        }

        // 2. Exact phone match (normalized: strip spaces, dashes, parentheses)
        $remaining = $limit - count($duplicates);
        if ($remaining > 0) {
            $phone_dupes = $wpdb->get_results($wpdb->prepare(
                "SELECT d1.id as id1, d1.name as name1, d1.email as email1, d1.city as city1, d1.phone as phone1,
                        d2.id as id2, d2.name as name2, d2.email as email2, d2.city as city2, d2.phone as phone2
                 FROM {$p}dealers d1
                 INNER JOIN {$p}dealers d2 ON
                     REPLACE(REPLACE(REPLACE(REPLACE(d1.phone, ' ', ''), '-', ''), '(', ''), ')', '') =
                     REPLACE(REPLACE(REPLACE(REPLACE(d2.phone, ' ', ''), '-', ''), '(', ''), ')', '')
                     AND d1.id < d2.id
                 WHERE d1.phone IS NOT NULL AND d1.phone != ''
                   AND d1.deleted_at IS NULL AND d2.deleted_at IS NULL
                   AND NOT EXISTS (
                       SELECT 1 FROM {$dismissed} dd
                       WHERE (dd.dealer_id_1 = d1.id AND dd.dealer_id_2 = d2.id)
                          OR (dd.dealer_id_1 = d2.id AND dd.dealer_id_2 = d1.id)
                   )
                 LIMIT %d",
                $remaining
            ));

            foreach (($phone_dupes ?: []) as $row) {
                $key = min($row->id1, $row->id2) . '-' . max($row->id1, $row->id2);
                if (!isset($seen_pairs[$key])) {
                    $seen_pairs[$key] = true;
                    $duplicates[] = (object) [
                        'id1'    => $row->id1,
                        'name1'  => $row->name1,
                        'city1'  => $row->city1,
                        'email1' => $row->email1,
                        'phone1' => $row->phone1,
                        'id2'    => $row->id2,
                        'name2'  => $row->name2,
                        'city2'  => $row->city2,
                        'email2' => $row->email2,
                        'phone2' => $row->phone2,
                        'reason' => 'Telefoon',
                        'match'  => $row->phone1,
                    ];
                }
            }
        }

        // 3. Similar name: normalized exact match (strips spaces, punctuation, case)
        // This catches "Berg & Berg" vs "Berg&Berg", "BEMA wonen" vs "Bema Wonen" etc.
        $remaining = $limit - count($duplicates);
        if ($remaining > 0) {
            $name_dupes = $wpdb->get_results($wpdb->prepare(
                "SELECT d1.id as id1, d1.name as name1, d1.email as email1, d1.city as city1, d1.phone as phone1,
                        d2.id as id2, d2.name as name2, d2.email as email2, d2.city as city2, d2.phone as phone2
                 FROM {$p}dealers d1
                 INNER JOIN {$p}dealers d2 ON
                     LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(d1.name, ' ', ''), '-', ''), '&', ''), '.', ''), ',', '')) =
                     LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(d2.name, ' ', ''), '-', ''), '&', ''), '.', ''), ',', ''))
                     AND d1.id < d2.id
                 WHERE d1.name IS NOT NULL AND d1.name != ''
                   AND d1.name != d2.name
                   AND d1.deleted_at IS NULL AND d2.deleted_at IS NULL
                   AND NOT EXISTS (
                       SELECT 1 FROM {$dismissed} dd
                       WHERE (dd.dealer_id_1 = d1.id AND dd.dealer_id_2 = d2.id)
                          OR (dd.dealer_id_1 = d2.id AND dd.dealer_id_2 = d1.id)
                   )
                 LIMIT %d",
                $remaining
            ));

            foreach (($name_dupes ?: []) as $row) {
                $key = min($row->id1, $row->id2) . '-' . max($row->id1, $row->id2);
                if (!isset($seen_pairs[$key])) {
                    $seen_pairs[$key] = true;
                    $duplicates[] = (object) [
                        'id1'    => $row->id1,
                        'name1'  => $row->name1,
                        'city1'  => $row->city1,
                        'email1' => $row->email1,
                        'phone1' => $row->phone1,
                        'id2'    => $row->id2,
                        'name2'  => $row->name2,
                        'city2'  => $row->city2,
                        'email2' => $row->email2,
                        'phone2' => $row->phone2,
                        'reason' => 'Naam',
                        'match'  => $row->name1 . ' / ' . $row->name2,
                    ];
                }
            }
        }

        return $duplicates;
    }

    /**
     * Dismiss a duplicate pair.
     */
    public static function dismiss_duplicate($dealer_id_1, $dealer_id_2) {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_dismissed_duplicates';

        // Always store with smaller ID first
        $id1 = min($dealer_id_1, $dealer_id_2);
        $id2 = max($dealer_id_1, $dealer_id_2);

        $wpdb->replace($table, [
            'dealer_id_1'  => $id1,
            'dealer_id_2'  => $id2,
            'dismissed_by' => get_current_user_id(),
        ]);
    }

    /**
     * Count potential duplicates (for menu badge).
     */
    public static function count_duplicates() {
        // Cache for 10 minutes to avoid running heavy self-join queries on every page load
        $cached = get_transient('dealer_crm_dup_count');
        if ($cached !== false) {
            return (int) $cached;
        }

        global $wpdb;
        $p = $wpdb->prefix . 'crm_';
        $dismissed = $wpdb->prefix . 'crm_dismissed_duplicates';

        // Count email duplicates
        $email_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT d1.id
                FROM {$p}dealers d1
                INNER JOIN {$p}dealers d2 ON d1.email = d2.email AND d1.id < d2.id
                WHERE d1.email IS NOT NULL AND d1.email != ''
                  AND d1.deleted_at IS NULL AND d2.deleted_at IS NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM {$dismissed} dd
                      WHERE (dd.dealer_id_1 = d1.id AND dd.dealer_id_2 = d2.id)
                         OR (dd.dealer_id_1 = d2.id AND dd.dealer_id_2 = d1.id)
                  )
                LIMIT 100
            ) as t"
        );

        // Count phone duplicates
        $phone_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT d1.id
                FROM {$p}dealers d1
                INNER JOIN {$p}dealers d2 ON
                    REPLACE(REPLACE(REPLACE(REPLACE(d1.phone, ' ', ''), '-', ''), '(', ''), ')', '') =
                    REPLACE(REPLACE(REPLACE(REPLACE(d2.phone, ' ', ''), '-', ''), '(', ''), ')', '')
                    AND d1.id < d2.id
                WHERE d1.phone IS NOT NULL AND d1.phone != ''
                  AND d1.deleted_at IS NULL AND d2.deleted_at IS NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM {$dismissed} dd
                      WHERE (dd.dealer_id_1 = d1.id AND dd.dealer_id_2 = d2.id)
                         OR (dd.dealer_id_1 = d2.id AND dd.dealer_id_2 = d1.id)
                  )
                LIMIT 100
            ) as t"
        );

        $count = $email_count + $phone_count;
        set_transient('dealer_crm_dup_count', $count, HOUR_IN_SECONDS);
        return $count;
    }

    /**
     * Clear the cached duplicate count (call after merge/dismiss).
     */
    public static function clear_count_cache() {
        delete_transient('dealer_crm_dup_count');
    }
}
