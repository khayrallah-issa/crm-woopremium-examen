<?php
defined('ABSPATH') || exit;

class DealerCRM_ActivityLog {

    public static function create_table() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'crm_activity_log';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            dealer_id BIGINT UNSIGNED NULL,
            action VARCHAR(50) NOT NULL,
            description TEXT NOT NULL,
            meta JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_dealer (dealer_id),
            KEY idx_user (user_id),
            KEY idx_created (created_at)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Log an activity.
     */
    public static function log($action, $description, $dealer_id = null, $meta = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_activity_log';

        $data = [
            'user_id'     => get_current_user_id(),
            'dealer_id'   => $dealer_id,
            'action'      => $action,
            'description' => $description,
        ];

        if ($meta !== null) {
            $data['meta'] = wp_json_encode($meta);
        }

        $wpdb->insert($table, $data);
    }

    /**
     * Get activity for a specific dealer.
     */
    public static function get_dealer_activity($dealer_id, $limit = 50) {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_activity_log';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, u.display_name as user_name
             FROM {$table} a
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE a.dealer_id = %d
             ORDER BY a.created_at DESC
             LIMIT %d",
            $dealer_id,
            $limit
        ));
    }

    /**
     * Get recent activity across all dealers (with optional filters and pagination).
     */
    public static function get_recent_activity($limit = 50, $user_id = null, $action_filter = null, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_activity_log';
        $dealers_table = $wpdb->prefix . 'crm_dealers';

        $where = ['1=1'];
        $params = [];

        if ($user_id) {
            $where[] = 'a.user_id = %d';
            $params[] = $user_id;
        }
        if ($action_filter) {
            $where[] = 'a.action = %s';
            $params[] = $action_filter;
        }

        $where_sql = implode(' AND ', $where);

        $query = "SELECT a.*, u.display_name as user_name, d.name as dealer_name
                  FROM {$table} a
                  LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                  LEFT JOIN {$dealers_table} d ON a.dealer_id = d.id
                  WHERE {$where_sql}
                  ORDER BY a.created_at DESC
                  LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($query, ...$params));
    }

    /**
     * Count total activities (for pagination).
     */
    public static function count_activity($user_id = null, $action_filter = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_activity_log';

        $where = ['1=1'];
        $params = [];

        if ($user_id) {
            $where[] = 'user_id = %d';
            $params[] = $user_id;
        }
        if ($action_filter) {
            $where[] = 'action = %s';
            $params[] = $action_filter;
        }

        $where_sql = implode(' AND ', $where);
        $query = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

        if ($params) {
            return (int) $wpdb->get_var($wpdb->prepare($query, ...$params));
        }
        return (int) $wpdb->get_var($query);
    }

    /**
     * Get all action types for filter dropdown.
     */
    public static function get_action_types() {
        return [
            'dealer_updated'    => 'Dealer bijgewerkt',
            'note_added'        => 'Notitie toegevoegd',
            'note_deleted'      => 'Notitie verwijderd',
            'contact_added'     => 'Contact toegevoegd',
            'contact_deleted'   => 'Contact verwijderd',
            'tag_added'         => 'Tag toegevoegd',
            'tag_removed'       => 'Tag verwijderd',
            'dealer_merged'     => 'Dealers samengevoegd',
            'followup_added'    => 'Follow-up toegevoegd',
            'followup_completed'=> 'Follow-up afgerond',
            'dealer_imported'   => 'Dealers geimporteerd',
            'email_sent'        => 'E-mail verstuurd',
        ];
    }

    /**
     * Get human-readable label for an action.
     */
    public static function get_action_label($action) {
        $types = self::get_action_types();
        return $types[$action] ?? $action;
    }
}

/**
 * Global helper function for logging activities.
 */
function crm_log_activity($action, $description, $dealer_id = null, $meta = null) {
    DealerCRM_ActivityLog::log($action, $description, $dealer_id, $meta);
}
