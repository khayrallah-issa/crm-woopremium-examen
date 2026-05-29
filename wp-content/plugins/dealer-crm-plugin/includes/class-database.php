<?php
defined('ABSPATH') || exit;

class DealerCRM_Database {

    public static function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'crm_';

        $sql = "
        CREATE TABLE {$prefix}dealers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            street VARCHAR(255),
            postcode VARCHAR(20),
            city VARCHAR(255),
            phone VARCHAR(100),
            email VARCHAR(255),
            website VARCHAR(500),
            status VARCHAR(50) DEFAULT 'actief',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset;

        CREATE TABLE {$prefix}brands (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            parent_id BIGINT UNSIGNED DEFAULT NULL
        ) $charset;

        CREATE TABLE {$prefix}dealer_brand (
            dealer_id BIGINT UNSIGNED NOT NULL,
            brand_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (dealer_id, brand_id)
        ) $charset;

        CREATE TABLE {$prefix}tags (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            color VARCHAR(7) DEFAULT '#6c757d'
        ) $charset;

        CREATE TABLE {$prefix}dealer_tag (
            dealer_id BIGINT UNSIGNED NOT NULL,
            tag_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (dealer_id, tag_id)
        ) $charset;

        CREATE TABLE {$prefix}notes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            dealer_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_dealer (dealer_id)
        ) $charset;

        CREATE TABLE {$prefix}contact_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            dealer_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(50) NOT NULL,
            subject VARCHAR(255),
            content TEXT,
            contact_date DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_dealer (dealer_id)
        ) $charset;

        CREATE TABLE {$prefix}followups (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            dealer_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            due_date DATE NOT NULL,
            status VARCHAR(50) DEFAULT 'open',
            completed_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_dealer (dealer_id),
            KEY idx_user_due (user_id, due_date)
        ) $charset;
        ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option('dealer_crm_db_version', DEALER_CRM_VERSION);
    }

    private static function prefix() {
        global $wpdb;
        return $wpdb->prefix . 'crm_';
    }

    public static function get_dealers($args = []) {
        global $wpdb;
        $p = self::prefix();

        $defaults = [
            'search'   => '',
            'brand'    => '',
            'city'     => '',
            'status'   => '',
            'tag_id'   => 0,
            'webshop'  => '',
            'page'     => 1,
            'per_page' => 25,
            'orderby'  => 'name',
            'order'    => 'ASC',
            'trashed'  => false, // true = only show trashed dealers, false = only show active
        ];
        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $params = [];

        // Trash filter: by default exclude trashed dealers; if trashed=true show only trashed
        if ($args['trashed']) {
            $where[] = "d.deleted_at IS NOT NULL";
        } else {
            $where[] = "d.deleted_at IS NULL";
        }

        if ($args['search']) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = "(d.name LIKE %s OR d.email LIKE %s OR d.city LIKE %s OR d.phone LIKE %s)";
            $params = array_merge($params, [$like, $like, $like, $like]);
        }
        if ($args['city']) {
            $where[] = "d.city = %s";
            $params[] = $args['city'];
        }
        if ($args['status']) {
            $where[] = "d.status = %s";
            $params[] = $args['status'];
        }
        if ($args['webshop'] === 'yes') {
            $where[] = "d.webshop_status = 'detected'";
        } elseif ($args['webshop'] === 'no') {
            $where[] = "d.webshop_status = 'none'";
        }

        $joins = '';
        if ($args['brand']) {
            $joins .= " INNER JOIN {$p}dealer_brand db ON d.id = db.dealer_id
                         INNER JOIN {$p}brands b ON db.brand_id = b.id AND b.name = %s";
            $params[] = $args['brand'];
        }
        if ($args['tag_id']) {
            $joins .= " INNER JOIN {$p}dealer_tag dt ON d.id = dt.dealer_id AND dt.tag_id = %d";
            $params[] = $args['tag_id'];
        }

        $where_sql = implode(' AND ', $where);

        $allowed_order = ['name', 'city', 'status', 'created_at', 'updated_at'];
        $orderby = in_array($args['orderby'], $allowed_order) ? $args['orderby'] : 'name';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $count_sql = "SELECT COUNT(DISTINCT d.id) FROM {$p}dealers d $joins WHERE $where_sql";
        if ($params) {
            $total = $wpdb->get_var($wpdb->prepare($count_sql, ...$params));
        } else {
            $total = $wpdb->get_var($count_sql);
        }

        $offset = ($args['page'] - 1) * $args['per_page'];
        $query = "SELECT DISTINCT d.* FROM {$p}dealers d $joins WHERE $where_sql ORDER BY d.{$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $args['per_page'];
        $params[] = $offset;

        $dealers = $wpdb->get_results($wpdb->prepare($query, ...$params));

        return ['dealers' => $dealers, 'total' => (int) $total];
    }

    public static function get_dealer($id) {
        global $wpdb;
        $p = self::prefix();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}dealers WHERE id = %d", $id));
    }

    public static function create_dealer($data) {
        global $wpdb;
        $wpdb->insert(self::prefix() . 'dealers', $data);
        return $wpdb->insert_id;
    }

    public static function update_dealer($id, $data) {
        global $wpdb;
        $wpdb->update(self::prefix() . 'dealers', $data, ['id' => $id]);
    }

    public static function get_or_create_brand($name) {
        global $wpdb;
        $p = self::prefix();
        $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}brands WHERE name = %s", $name));
        if ($id) return (int) $id;
        $wpdb->insert($p . 'brands', ['name' => $name]);
        return $wpdb->insert_id;
    }

    public static function add_dealer_brand($dealer_id, $brand_id) {
        global $wpdb;
        $p = self::prefix();
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}dealer_brand WHERE dealer_id = %d AND brand_id = %d",
            $dealer_id, $brand_id
        ));
        if (!$exists) {
            $wpdb->insert($p . 'dealer_brand', ['dealer_id' => $dealer_id, 'brand_id' => $brand_id]);
        }
    }

    public static function get_dealer_brands($dealer_id) {
        global $wpdb;
        $p = self::prefix();
        return $wpdb->get_col($wpdb->prepare(
            "SELECT b.name FROM {$p}brands b
             INNER JOIN {$p}dealer_brand db ON b.id = db.brand_id
             WHERE db.dealer_id = %d ORDER BY b.name",
            $dealer_id
        ));
    }

    /**
     * Batch-load brands for multiple dealers at once (avoids N+1).
     */
    public static function get_brands_for_dealers($dealer_ids) {
        global $wpdb;
        if (empty($dealer_ids)) return [];
        $p = self::prefix();
        $ids = implode(',', array_map('intval', $dealer_ids));
        $rows = $wpdb->get_results(
            "SELECT db.dealer_id, b.name FROM {$p}brands b
             INNER JOIN {$p}dealer_brand db ON b.id = db.brand_id
             WHERE db.dealer_id IN ({$ids})
             ORDER BY b.name"
        );
        $map = [];
        foreach ($rows as $row) {
            $map[$row->dealer_id][] = $row->name;
        }
        return $map;
    }

    /**
     * Batch-load tags for multiple dealers at once (avoids N+1).
     */
    public static function get_tags_for_dealers($dealer_ids) {
        global $wpdb;
        if (empty($dealer_ids)) return [];
        $p = self::prefix();
        $ids = implode(',', array_map('intval', $dealer_ids));
        $rows = $wpdb->get_results(
            "SELECT dt.dealer_id, t.id, t.name, t.color FROM {$p}tags t
             INNER JOIN {$p}dealer_tag dt ON t.id = dt.tag_id
             WHERE dt.dealer_id IN ({$ids})
             ORDER BY t.name"
        );
        $map = [];
        foreach ($rows as $row) {
            $map[$row->dealer_id][] = $row;
        }
        return $map;
    }

    public static function get_all_brands() {
        $cached = get_transient('dealer_crm_all_brands');
        if ($cached !== false) return $cached;
        global $wpdb;
        $result = $wpdb->get_col("SELECT name FROM " . self::prefix() . "brands ORDER BY name");
        set_transient('dealer_crm_all_brands', $result, HOUR_IN_SECONDS);
        return $result;
    }

    public static function get_all_cities() {
        $cached = get_transient('dealer_crm_all_cities');
        if ($cached !== false) return $cached;
        global $wpdb;
        $result = $wpdb->get_col("SELECT DISTINCT city FROM " . self::prefix() . "dealers WHERE city IS NOT NULL AND city != '' AND deleted_at IS NULL ORDER BY city");
        set_transient('dealer_crm_all_cities', $result, HOUR_IN_SECONDS);
        return $result;
    }

    public static function get_all_tags() {
        $cached = get_transient('dealer_crm_all_tags');
        if ($cached !== false) return $cached;
        global $wpdb;
        $result = $wpdb->get_results("SELECT * FROM " . self::prefix() . "tags ORDER BY name");
        set_transient('dealer_crm_all_tags', $result, HOUR_IN_SECONDS);
        return $result;
    }

    public static function create_tag($name, $color = '#6c757d') {
        global $wpdb;
        $wpdb->insert(self::prefix() . 'tags', ['name' => $name, 'color' => $color]);
        return $wpdb->insert_id;
    }

    public static function delete_tag($id) {
        global $wpdb;
        $p = self::prefix();
        $wpdb->delete($p . 'dealer_tag', ['tag_id' => $id]);
        $wpdb->delete($p . 'tags', ['id' => $id]);
    }

    public static function get_dealer_tags($dealer_id) {
        global $wpdb;
        $p = self::prefix();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.* FROM {$p}tags t
             INNER JOIN {$p}dealer_tag dt ON t.id = dt.tag_id
             WHERE dt.dealer_id = %d ORDER BY t.name",
            $dealer_id
        ));
    }

    public static function add_dealer_tag($dealer_id, $tag_id) {
        global $wpdb;
        $wpdb->replace(self::prefix() . 'dealer_tag', [
            'dealer_id' => $dealer_id,
            'tag_id' => $tag_id,
        ]);
    }

    public static function remove_dealer_tag($dealer_id, $tag_id) {
        global $wpdb;
        $wpdb->delete(self::prefix() . 'dealer_tag', [
            'dealer_id' => $dealer_id,
            'tag_id' => $tag_id,
        ]);
    }

    public static function add_note($dealer_id, $user_id, $content) {
        global $wpdb;
        $wpdb->insert(self::prefix() . 'notes', [
            'dealer_id' => $dealer_id,
            'user_id' => $user_id,
            'content' => $content,
        ]);
        return $wpdb->insert_id;
    }

    public static function get_notes($dealer_id) {
        global $wpdb;
        $p = self::prefix();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, u.display_name as author
             FROM {$p}notes n
             LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
             WHERE n.dealer_id = %d ORDER BY n.created_at DESC",
            $dealer_id
        ));
    }

    public static function delete_note($id) {
        global $wpdb;
        $wpdb->delete(self::prefix() . 'notes', ['id' => $id]);
    }

    public static function add_contact_log($dealer_id, $user_id, $data) {
        global $wpdb;
        $wpdb->insert(self::prefix() . 'contact_log', array_merge($data, [
            'dealer_id' => $dealer_id,
            'user_id' => $user_id,
        ]));
        return $wpdb->insert_id;
    }

    public static function get_contact_log($dealer_id) {
        global $wpdb;
        $p = self::prefix();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT cl.*, u.display_name as author
             FROM {$p}contact_log cl
             LEFT JOIN {$wpdb->users} u ON cl.user_id = u.ID
             WHERE cl.dealer_id = %d ORDER BY cl.contact_date DESC",
            $dealer_id
        ));
    }

    public static function delete_contact_log($id) {
        global $wpdb;
        $wpdb->delete(self::prefix() . 'contact_log', ['id' => $id]);
    }

    /**
     * Auteur: Khayrallah Issa
     *
     * Haalt alle e-mails (in- en uitgaand) van een dealer op uit de nieuwe
     * tabel wp_crm_emails. Wordt gebruikt door de "E-mail" tab op de
     * dealerpagina, zodat naast verzonden mails ook de inkomende mails van
     * dealers zichtbaar worden. Resultaat is gesorteerd op verzenddatum
     * (nieuwste bovenaan).
     *
     * Als de migratie nog niet gedraaid is bestaat de tabel niet; in dat
     * geval geven we een lege lijst terug zodat de pagina niet crasht.
     */
    public static function get_dealer_emails($dealer_id) {
        global $wpdb;
        $table = self::prefix() . 'emails';
        $exists = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))
        );
        if ($exists !== $table) {
            return [];
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, u.display_name AS author
             FROM {$table} e
             LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
             WHERE e.dealer_id = %d
             ORDER BY e.sent_at DESC",
            $dealer_id
        ));
    }

    /**
     * Auteur: Khayrallah Issa
     *
     * Markeert een inkomende mail als gelezen door read_at op nu te zetten.
     * Wordt aangeroepen vanuit de inbox in de Contacthistorie-tab zodra de
     * gebruiker een mail openklapt.
     */
    public static function mark_email_read($email_id) {
        global $wpdb;
        $table = self::prefix() . 'emails';
        $exists = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))
        );
        if ($exists !== $table) return false;
        return $wpdb->update(
            $table,
            ['read_at' => current_time('mysql')],
            ['id' => (int) $email_id, 'direction' => 'in']
        );
    }

    /**
     * Auteur: Khayrallah Issa
     *
     * Haalt een enkele mail op (voor "openklappen" in de inbox).
     */
    public static function get_email($email_id) {
        global $wpdb;
        $table = self::prefix() . 'emails';
        $exists = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))
        );
        if ($exists !== $table) return null;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
            (int) $email_id
        ));
    }

    public static function add_followup($dealer_id, $data) {
        global $wpdb;
        $wpdb->insert(self::prefix() . 'followups', array_merge($data, [
            'dealer_id'  => $dealer_id,
            'created_by' => get_current_user_id(),
        ]));
        return $wpdb->insert_id;
    }

    public static function get_dealer_followups($dealer_id) {
        global $wpdb;
        $p = self::prefix();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT f.*, u.display_name as assignee_name, c.display_name as creator_name
             FROM {$p}followups f
             LEFT JOIN {$wpdb->users} u ON f.user_id = u.ID
             LEFT JOIN {$wpdb->users} c ON f.created_by = c.ID
             WHERE f.dealer_id = %d ORDER BY f.due_date ASC",
            $dealer_id
        ));
    }

    public static function get_user_followups($user_id, $status = 'open', $limit = 20) {
        global $wpdb;
        $p = self::prefix();
        $where = "f.user_id = %d";
        $params = [$user_id];

        if ($status === 'verlopen') {
            $where .= " AND f.status = 'open' AND f.due_date < CURDATE()";
        } elseif ($status === 'open') {
            $where .= " AND f.status = 'open' AND f.due_date >= CURDATE()";
        } elseif ($status) {
            $where .= " AND f.status = %s";
            $params[] = $status;
        }

        $params[] = $limit;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT f.*, d.name as dealer_name, d.id as dealer_id
             FROM {$p}followups f
             LEFT JOIN {$p}dealers d ON f.dealer_id = d.id
             WHERE $where
             ORDER BY f.due_date ASC
             LIMIT %d",
            ...$params
        ));
    }

    public static function get_all_followups($args = []) {
        global $wpdb;
        $p = self::prefix();

        $defaults = [
            'status'  => '',
            'user_id' => 0,
            'overdue' => false,
            'limit'   => 50,
        ];
        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $params = [];

        if ($args['status']) {
            $where[] = "f.status = %s";
            $params[] = $args['status'];
        }
        if ($args['user_id']) {
            $where[] = "f.user_id = %d";
            $params[] = $args['user_id'];
        }
        if ($args['overdue']) {
            $where[] = "f.status = 'open' AND f.due_date < CURDATE()";
        }

        $where_sql = implode(' AND ', $where);
        $params[] = $args['limit'];

        $sql = "SELECT f.*, d.name as dealer_name, u.display_name as assignee_name
                FROM {$p}followups f
                LEFT JOIN {$p}dealers d ON f.dealer_id = d.id
                LEFT JOIN {$wpdb->users} u ON f.user_id = u.ID
                WHERE $where_sql
                ORDER BY f.due_date ASC
                LIMIT %d";

        if (count($params) > 1) {
            return $wpdb->get_results($wpdb->prepare($sql, ...$params));
        }
        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }

    public static function update_followup_status($id, $status) {
        global $wpdb;
        $data = ['status' => $status];
        if ($status === 'voltooid') {
            $data['completed_at'] = current_time('mysql');
        }
        $wpdb->update(self::prefix() . 'followups', $data, ['id' => $id]);
    }

    public static function delete_followup($id) {
        global $wpdb;
        $wpdb->delete(self::prefix() . 'followups', ['id' => $id]);
    }

    public static function count_followups_by_status($user_id) {
        global $wpdb;
        $p = self::prefix();
        $open = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}followups WHERE user_id = %d AND status = 'open' AND due_date >= CURDATE()", $user_id
        ));
        $overdue = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}followups WHERE user_id = %d AND status = 'open' AND due_date < CURDATE()", $user_id
        ));
        $completed_today = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}followups WHERE user_id = %d AND status = 'voltooid' AND DATE(completed_at) = CURDATE()", $user_id
        ));
        return [
            'open'            => $open,
            'overdue'         => $overdue,
            'completed_today' => $completed_today,
        ];
    }

    public static function get_statuses() {
        global $wpdb;
        $p = self::prefix();
        $db_statuses = $wpdb->get_col(
            "SELECT DISTINCT status FROM {$p}dealers WHERE status IS NOT NULL AND status != '' AND deleted_at IS NULL ORDER BY status"
        );
        return !empty($db_statuses) ? $db_statuses : ['actief', 'inactief', 'prospect', 'geblokkeerd'];
    }

    public static function merge_dealers($primary_id, $merge_with_id, $field_choices) {
        global $wpdb;
        $p = self::prefix();

        $primary = self::get_dealer($primary_id);
        $secondary = self::get_dealer($merge_with_id);
        if (!$primary || !$secondary) return false;

        $wpdb->query('START TRANSACTION');

        try {
            // Build merged field data
            $data = [];
            foreach ($field_choices as $key => $source) {
                $data[$key] = ($source === 'secondary') ? ($secondary->$key ?? '') : ($primary->$key ?? '');
            }
            $wpdb->update($p . 'dealers', $data, ['id' => $primary_id]);

            // Move notes from secondary to primary
            $wpdb->update($p . 'notes', ['dealer_id' => $primary_id], ['dealer_id' => $merge_with_id]);

            // Move contact_log from secondary to primary
            $wpdb->update($p . 'contact_log', ['dealer_id' => $primary_id], ['dealer_id' => $merge_with_id]);

            // Move followups from secondary to primary
            $wpdb->update($p . 'followups', ['dealer_id' => $primary_id], ['dealer_id' => $merge_with_id]);

            // Merge brands: copy brand links that don't already exist on primary
            $existing_brands = $wpdb->get_col($wpdb->prepare(
                "SELECT brand_id FROM {$p}dealer_brand WHERE dealer_id = %d", $primary_id
            ));
            $secondary_brands = $wpdb->get_col($wpdb->prepare(
                "SELECT brand_id FROM {$p}dealer_brand WHERE dealer_id = %d", $merge_with_id
            ));
            foreach ($secondary_brands as $brand_id) {
                if (!in_array($brand_id, $existing_brands)) {
                    $wpdb->insert($p . 'dealer_brand', [
                        'dealer_id' => $primary_id,
                        'brand_id' => $brand_id,
                    ]);
                }
            }
            $wpdb->delete($p . 'dealer_brand', ['dealer_id' => $merge_with_id]);

            // Merge tags: copy tag links that don't already exist on primary
            $existing_tags = $wpdb->get_col($wpdb->prepare(
                "SELECT tag_id FROM {$p}dealer_tag WHERE dealer_id = %d", $primary_id
            ));
            $secondary_tags = $wpdb->get_col($wpdb->prepare(
                "SELECT tag_id FROM {$p}dealer_tag WHERE dealer_id = %d", $merge_with_id
            ));
            foreach ($secondary_tags as $tag_id) {
                if (!in_array($tag_id, $existing_tags)) {
                    $wpdb->insert($p . 'dealer_tag', [
                        'dealer_id' => $primary_id,
                        'tag_id' => $tag_id,
                    ]);
                }
            }
            $wpdb->delete($p . 'dealer_tag', ['dealer_id' => $merge_with_id]);

            // Delete the secondary dealer
            $wpdb->delete($p . 'dealers', ['id' => $merge_with_id]);

            $wpdb->query('COMMIT');
            return true;
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            return false;
        }
    }

    public static function get_stats() {
        global $wpdb;
        $p = self::prefix();
        $row = $wpdb->get_row(
            "SELECT
                (SELECT COUNT(*) FROM {$p}dealers WHERE deleted_at IS NULL) as total_dealers,
                (SELECT COUNT(*) FROM {$p}brands) as total_brands,
                (SELECT COUNT(*) FROM {$p}notes n INNER JOIN {$p}dealers d ON n.dealer_id = d.id WHERE d.deleted_at IS NULL) as total_notes,
                (SELECT COUNT(*) FROM {$p}contact_log c INNER JOIN {$p}dealers d ON c.dealer_id = d.id WHERE d.deleted_at IS NULL) as total_contacts"
        );
        return [
            'total_dealers'  => (int) ($row->total_dealers ?? 0),
            'total_brands'   => (int) ($row->total_brands ?? 0),
            'total_notes'    => (int) ($row->total_notes ?? 0),
            'total_contacts' => (int) ($row->total_contacts ?? 0),
        ];
    }

    /**
     * v1.6.0 — add deleted_at column to dealers table for soft-delete (trash) support.
     * NULL = active, DATETIME = moved to trash at that moment.
     */
    public static function ensure_trash_column() {
        global $wpdb;
        $table = self::prefix() . 'dealers';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (!in_array('deleted_at', $columns)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
            $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_deleted_at (deleted_at)");
        }
    }

    /**
     * v1.7.0 — add contact_person + owner columns to dealers table.
     * Both are simple VARCHAR text fields for the company's contact person and owner name.
     */
    public static function ensure_contact_owner_columns() {
        global $wpdb;
        $table = self::prefix() . 'dealers';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (!in_array('contact_person', $columns)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN contact_person VARCHAR(255) NULL AFTER name");
        }
        // Re-fetch in case contact_person was just added
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (!in_array('owner', $columns)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN `owner` VARCHAR(255) NULL AFTER contact_person");
        }
    }

    /**
     * v1.7.0 — seed default lead-qualification tags. Idempotent: skips tags that already exist (case-insensitive).
     */
    public static function seed_default_tags() {
        global $wpdb;
        $table = self::prefix() . 'tags';
        $defaults = [
            ['name' => 'Match',                  'color' => '#16a34a'], // groen
            ['name' => 'Kans',                   'color' => '#2563eb'], // blauw
            ['name' => 'Te Moeilijk',            'color' => '#f59e0b'], // oranje
            ['name' => 'Valt buiten de doelgroep','color' => '#6b7280'], // grijs
        ];
        foreach ($defaults as $tag) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE LOWER(name) = LOWER(%s) LIMIT 1",
                $tag['name']
            ));
            if (!$exists) {
                $wpdb->insert($table, $tag);
            }
        }
        // Bust the cached tag list so it refreshes immediately
        delete_transient('dealer_crm_all_tags');
    }

    /**
     * Get the configured office coordinates, or null if not set.
     * Used for the distance-to-dealer column in campaign tables.
     */
    public static function get_office_coords() {
        $lat = get_option('dealer_crm_office_lat', '');
        $lng = get_option('dealer_crm_office_lng', '');
        if ($lat === '' || $lng === '') return null;
        return ['lat' => (float) $lat, 'lng' => (float) $lng];
    }

    /**
     * Haversine distance in kilometers between two lat/lng points.
     * Returns null if any coord is missing.
     */
    public static function haversine_km($lat1, $lng1, $lat2, $lng2) {
        if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) return null;
        if ($lat1 === '' || $lng1 === '' || $lat2 === '' || $lng2 === '') return null;
        $earth = 6371.0; // km
        $dLat = deg2rad((float)$lat2 - (float)$lat1);
        $dLng = deg2rad((float)$lng2 - (float)$lng1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad((float)$lat1)) * cos(deg2rad((float)$lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earth * $c, 1);
    }

    /**
     * Move a single dealer to the trash (soft delete).
     */
    public static function trash_dealer($id) {
        global $wpdb;
        $id = (int) $id;
        if (!$id) return false;
        return (bool) $wpdb->update(
            self::prefix() . 'dealers',
            ['deleted_at' => current_time('mysql')],
            ['id' => $id]
        );
    }

    /**
     * Move multiple dealers to the trash in one query.
     * Returns the number of rows affected.
     */
    public static function trash_dealers_bulk($ids) {
        global $wpdb;
        $ids = array_filter(array_map('intval', (array) $ids));
        if (empty($ids)) return 0;
        $p = self::prefix();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $now = current_time('mysql');
        $sql = "UPDATE {$p}dealers SET deleted_at = %s WHERE id IN ($placeholders) AND deleted_at IS NULL";
        return (int) $wpdb->query($wpdb->prepare($sql, array_merge([$now], $ids)));
    }

    /**
     * Restore a dealer from the trash.
     */
    public static function restore_dealer($id) {
        global $wpdb;
        $id = (int) $id;
        if (!$id) return false;
        return (bool) $wpdb->update(
            self::prefix() . 'dealers',
            ['deleted_at' => null],
            ['id' => $id]
        );
    }

    /**
     * Permanently delete a dealer and all related data.
     * Uses a transaction to keep related tables consistent.
     */
    public static function delete_dealer_permanent($id) {
        global $wpdb;
        $id = (int) $id;
        if (!$id) return false;
        $p = self::prefix();

        $wpdb->query('START TRANSACTION');
        try {
            $wpdb->delete($p . 'dealer_brand', ['dealer_id' => $id]);
            $wpdb->delete($p . 'dealer_tag', ['dealer_id' => $id]);
            $wpdb->delete($p . 'notes', ['dealer_id' => $id]);
            $wpdb->delete($p . 'contact_log', ['dealer_id' => $id]);
            $wpdb->delete($p . 'followups', ['dealer_id' => $id]);
            $wpdb->delete($p . 'dealers', ['id' => $id]);
            $wpdb->query('COMMIT');
            return true;
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            return false;
        }
    }

    /**
     * Count how many dealers are currently in the trash.
     */
    public static function count_trashed_dealers() {
        global $wpdb;
        $p = self::prefix();
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}dealers WHERE deleted_at IS NOT NULL");
    }

    public static function ensure_brand_parent_column() {
        global $wpdb;
        $table = self::prefix() . 'brands';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (!in_array('parent_id', $columns)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN parent_id BIGINT UNSIGNED DEFAULT NULL");
        }
    }

    public static function ensure_geo_columns() {
        global $wpdb;
        $table = self::prefix() . 'dealers';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (!in_array('lat', $columns)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN lat DECIMAL(10,7) NULL");
        }
        if (!in_array('lng', $columns)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN lng DECIMAL(10,7) NULL");
        }
        if (!in_array('geocode_status', $columns)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN geocode_status VARCHAR(20) NULL");
        }
    }

    /**
     * Ensure performance indexes exist on the dealers table.
     */
    public static function ensure_indexes() {
        global $wpdb;
        $table = self::prefix() . 'dealers';

        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table}", ARRAY_A);
        $existing = array_column($indexes, 'Key_name');

        $needed = [
            'idx_email'    => 'email',
            'idx_phone'    => 'phone',
            'idx_city'     => 'city',
            'idx_name'     => 'name',
            'idx_status'   => 'status',
            'idx_postcode' => 'postcode',
        ];

        foreach ($needed as $idx_name => $column) {
            if (!in_array($idx_name, $existing)) {
                $wpdb->query("ALTER TABLE {$table} ADD INDEX {$idx_name} ({$column})");
            }
        }
    }

    /**
     * Normalize city names: proper case, expand abbreviations, merge duplicates.
     */
    public static function normalize_cities() {
        global $wpdb;
        $table = self::prefix() . 'dealers';

        // Get all distinct city values with counts
        $cities = $wpdb->get_results(
            "SELECT city, COUNT(*) as cnt FROM {$table} WHERE city IS NOT NULL AND city != '' GROUP BY city"
        );

        // Step 1: Build normalized key for each city
        $groups = []; // normalized_key => [ ['city' => original, 'cnt' => count], ... ]
        foreach ($cities as $row) {
            $key = self::city_normalize_key(trim($row->city));
            $groups[$key][] = ['city' => trim($row->city), 'cnt' => (int) $row->cnt];
        }

        // Step 2: Merge groups where one key is a prefix of another (truncated names)
        $keys = array_keys($groups);
        sort($keys);
        $merged = [];
        foreach ($keys as $key) {
            $found = false;
            foreach ($merged as $canonical => &$entries) {
                // Check if this key is a truncated version of an existing canonical (or vice versa)
                $shorter = min(mb_strlen($key), mb_strlen($canonical));
                if ($shorter >= 8) { // only merge if enough chars to be confident
                    if (mb_substr($canonical, 0, $shorter) === mb_substr($key, 0, $shorter)) {
                        // Merge into the longer key
                        if (mb_strlen($key) > mb_strlen($canonical)) {
                            // New key is longer, re-key
                            $entries = array_merge($entries, $groups[$key]);
                            $merged[$key] = $entries;
                            unset($merged[$canonical]);
                        } else {
                            $entries = array_merge($entries, $groups[$key]);
                        }
                        $found = true;
                        break;
                    }
                }
            }
            unset($entries);
            if (!$found) {
                $merged[$key] = $groups[$key];
            }
        }

        // Step 3: For each group, determine the canonical name and update
        foreach ($merged as $key => $variants) {
            // Pick the best variant: prefer longest name with most occurrences
            usort($variants, function ($a, $b) {
                $len_diff = mb_strlen($b['city']) - mb_strlen($a['city']);
                if ($len_diff !== 0) return $len_diff;
                return $b['cnt'] - $a['cnt'];
            });

            $best = $variants[0]['city'];
            $proper = self::city_proper_case($best);

            // Update all variants
            foreach ($variants as $v) {
                if ($v['city'] !== $proper) {
                    $wpdb->update($table, ['city' => $proper], ['city' => $v['city']]);
                }
            }
        }

        // Clear the cities cache
        delete_transient('dealer_crm_all_cities');
    }

    /**
     * Create a normalized key for grouping city names.
     * Expands abbreviations, lowercases, strips punctuation.
     */
    private static function city_normalize_key($city) {
        $s = self::city_fix_encoding($city);
        $s = mb_strtolower(trim($s), 'UTF-8');

        // Strip everything in parentheses: "(Bezoek op Afspraak)" etc.
        $s = preg_replace('/\s*\([^)]*\)/', '', $s);

        // Expand common Dutch abbreviations
        $s = preg_replace('/\ba\s*\/\s*d\b/', 'aan den', $s);
        $s = preg_replace('/\bad\b/', 'aan den', $s);
        $s = preg_replace('/\ba\s*\/\s*h\b/', 'aan het', $s);
        $s = preg_replace('/\bah\b/', 'aan het', $s);
        $s = preg_replace('/\bo\s*\/\s*d\b/', 'op den', $s);
        $s = preg_replace('/\bod\b/', 'op den', $s);
        $s = preg_replace('/\bb\s*\/\s*d\b/', 'bij den', $s);
        $s = preg_replace('/\bbd\b/', 'bij den', $s);
        $s = preg_replace('/\brt\b/', 'rotterdam', $s);
        $s = preg_replace('/\bn\.?b\.?\b/', 'noord-brabant', $s);
        $s = preg_replace('/\bn\.?h\.?\b/', 'noord-holland', $s);
        $s = preg_replace('/\bz\.?h\.?\b/', 'zuid-holland', $s);
        $s = preg_replace('/\bgld\.?\b/', 'gelderland', $s);
        $s = preg_replace('/\bfr\.?\b/', 'friesland', $s);
        $s = preg_replace('/\blb\.?\b/', 'limburg', $s);
        $s = preg_replace('/\but\.?\b/', 'utrecht', $s);

        // Normalize "aan de" vs "aan den" — group together
        $s = str_replace('aan de ', 'aan den ', $s);

        // Normalize & to en
        $s = str_replace('&', 'en', $s);

        // Normalize hyphens to spaces for matching
        $s = str_replace('-', ' ', $s);

        // Strip punctuation and extra spaces
        $s = preg_replace('/[\/\.\,\(\)]/', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        $s = trim($s);

        return $s;
    }

    /**
     * Convert city name to proper Dutch title case.
     */
    private static function city_proper_case($city) {
        $s = self::city_fix_encoding(trim($city));

        // Strip everything in parentheses
        $s = preg_replace('/\s*\([^)]*\)/', '', $s);
        $s = trim($s);

        // Expand abbreviations to full form
        $s = preg_replace('/\bA\s*\/\s*D\b/i', 'aan den', $s);
        $s = preg_replace('/\bAd\b/', 'aan den', $s);
        $s = preg_replace('/\bAD\b/', 'aan den', $s);
        $s = preg_replace('/\bA\s*\/\s*H\b/i', 'aan het', $s);
        $s = preg_replace('/\bO\s*\/\s*D\b/i', 'op den', $s);
        $s = preg_replace('/\bRt\b/i', 'Rotterdam', $s);

        // Normalize & to en
        $s = str_replace('&', 'en', $s);

        // Title case
        $s = mb_convert_case(mb_strtolower($s, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');

        // Dutch prepositions lowercase (but not at start)
        $lower_words = ['aan', 'an', 'bij', 'de', 'den', 'der', 'het', 'in', 'op', 'te', 'ten', 'ter', 'van', 'voor', 'en'];
        foreach ($lower_words as $w) {
            $s = preg_replace('/(?<=\s)' . ucfirst($w) . '(?=\s|$)/', $w, $s);
        }

        // First character always uppercase
        $s = mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');

        // Fix IJ digraph
        $s = preg_replace('/\bIj/', 'IJ', $s);

        // Capitalize after hyphen
        $s = preg_replace_callback('/-(\w)/u', function ($m) {
            return '-' . mb_strtoupper($m[1], 'UTF-8');
        }, $s);

        return $s;
    }

    /**
     * Fix broken unicode escape sequences like \U00Eb -> ë
     */
    private static function city_fix_encoding($city) {
        // Fix \U00XX patterns (e.g. \U00Eb = ë, \U00E9 = é)
        $s = preg_replace_callback('/\\\\U([0-9A-Fa-f]{4})/', function ($m) {
            return mb_chr(hexdec($m[1]), 'UTF-8');
        }, $city);

        // Also handle \u00XX variant
        $s = preg_replace_callback('/\\\\u([0-9A-Fa-f]{4})/', function ($m) {
            return mb_chr(hexdec($m[1]), 'UTF-8');
        }, $s);

        return $s;
    }

    public static function get_dealers_with_coords() {
        global $wpdb;
        $p = self::prefix();
        return $wpdb->get_results(
            "SELECT d.id, d.name, d.city, d.phone, d.email, d.status, d.lat, d.lng
             FROM {$p}dealers d
             WHERE d.lat IS NOT NULL AND d.lng IS NOT NULL
               AND d.deleted_at IS NULL"
        );
    }

    public static function update_dealer_coords($id, $lat, $lng) {
        global $wpdb;
        $wpdb->update(self::prefix() . 'dealers', [
            'lat' => $lat,
            'lng' => $lng,
        ], ['id' => $id]);
    }

    public static function get_dealers_without_coords($limit = 5) {
        global $wpdb;
        $p = self::prefix();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, street, postcode, city
             FROM {$p}dealers
             WHERE (lat IS NULL OR lng IS NULL)
               AND (geocode_status IS NULL OR geocode_status = '')
               AND city IS NOT NULL AND city != ''
               AND deleted_at IS NULL
             LIMIT %d",
            $limit
        ));
    }

    public static function count_dealers_without_coords() {
        global $wpdb;
        $p = self::prefix();
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$p}dealers
             WHERE (lat IS NULL OR lng IS NULL)
               AND (geocode_status IS NULL OR geocode_status = '')
               AND city IS NOT NULL AND city != ''
               AND deleted_at IS NULL"
        );
    }

    public static function mark_geocode_failed($id) {
        global $wpdb;
        $wpdb->update(self::prefix() . 'dealers', [
            'geocode_status' => 'failed',
        ], ['id' => $id]);
    }

    public static function count_geocode_failed() {
        global $wpdb;
        $p = self::prefix();
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$p}dealers WHERE geocode_status = 'failed' AND deleted_at IS NULL"
        );
    }

    public static function reset_geocode_failed() {
        global $wpdb;
        $wpdb->update(self::prefix() . 'dealers', [
            'geocode_status' => null,
        ], ['geocode_status' => 'failed']);
    }

    /**
     * Get dealers within a radius from a given lat/lng using the Haversine formula.
     * Returns dealers sorted by distance ASC with a `distance` field (in km).
     */
    public static function get_dealers_by_radius($lat, $lng, $radius_km, $args = []) {
        global $wpdb;
        $p = self::prefix();

        $defaults = [
            'search'   => '',
            'brand'    => '',
            'status'   => '',
            'tag_id'   => 0,
        ];
        $args = wp_parse_args($args, $defaults);

        $where = [
            'd.lat IS NOT NULL',
            'd.lng IS NOT NULL',
            'd.deleted_at IS NULL',
        ];
        $params = [];

        // Haversine distance formula
        $haversine = "(6371 * acos(
            LEAST(1, cos(radians(%f)) * cos(radians(d.lat)) * cos(radians(d.lng) - radians(%f))
            + sin(radians(%f)) * sin(radians(d.lat)))
        ))";

        $where[] = "$haversine <= %f";
        $params[] = $lat;
        $params[] = $lng;
        $params[] = $lat;
        $params[] = $radius_km;

        if ($args['search']) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = "(d.name LIKE %s OR d.email LIKE %s OR d.city LIKE %s OR d.phone LIKE %s)";
            $params = array_merge($params, [$like, $like, $like, $like]);
        }
        if ($args['status']) {
            $where[] = "d.status = %s";
            $params[] = $args['status'];
        }

        $joins = '';
        if ($args['brand']) {
            $joins .= " INNER JOIN {$p}dealer_brand db ON d.id = db.dealer_id
                         INNER JOIN {$p}brands b ON db.brand_id = b.id AND b.name = %s";
            $params[] = $args['brand'];
        }
        if ($args['tag_id']) {
            $joins .= " INNER JOIN {$p}dealer_tag dt ON d.id = dt.dealer_id AND dt.tag_id = %d";
            $params[] = $args['tag_id'];
        }

        $where_sql = implode(' AND ', $where);

        // Select with distance, repeat haversine params for SELECT
        $select_haversine = str_replace(['%f'], ['%f'], $haversine);
        $select_params = [$lat, $lng, $lat];

        $all_params = array_merge($select_params, $params);

        $query = "SELECT DISTINCT d.*, $select_haversine AS distance
                  FROM {$p}dealers d $joins
                  WHERE $where_sql
                  ORDER BY distance ASC";

        $dealers = $wpdb->get_results($wpdb->prepare($query, ...$all_params));

        // Round distance
        foreach ($dealers as &$dealer) {
            $dealer->distance = round((float) $dealer->distance, 1);
        }

        return $dealers;
    }
}
