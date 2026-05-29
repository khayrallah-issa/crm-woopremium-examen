<?php
defined('ABSPATH') || exit;

class DealerCRM_Brands {

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $p = $wpdb->prefix . 'crm_';

        $sqls = [
            "CREATE TABLE IF NOT EXISTS {$p}brand_details (
                brand_id BIGINT UNSIGNED NOT NULL,
                contact_person VARCHAR(255) DEFAULT '',
                email VARCHAR(255) DEFAULT '',
                phone VARCHAR(100) DEFAULT '',
                last_check_date DATE DEFAULT NULL,
                feed_status VARCHAR(20) DEFAULT '',
                product_count_feed INT DEFAULT 0,
                product_count_website INT DEFAULT 0,
                counts_match VARCHAR(20) DEFAULT '',
                counts_remark TEXT,
                prices_status VARCHAR(20) DEFAULT '',
                price_type VARCHAR(50) DEFAULT '',
                images_status VARCHAR(20) DEFAULT '',
                images_per_product VARCHAR(50) DEFAULT '',
                topshot_status VARCHAR(20) DEFAULT '',
                scene_image_status VARCHAR(20) DEFAULT '',
                image_quality VARCHAR(50) DEFAULT '',
                attributes_status VARCHAR(20) DEFAULT '',
                floor_heating VARCHAR(20) DEFAULT '',
                sqm_per_pack VARCHAR(20) DEFAULT '',
                color_status VARCHAR(20) DEFAULT '',
                material_status VARCHAR(20) DEFAULT '',
                size_format_status VARCHAR(20) DEFAULT '',
                collection_name_status VARCHAR(20) DEFAULT '',
                other_attributes_note TEXT,
                score INT DEFAULT 0,
                tracker_status VARCHAR(50) DEFAULT 'Niet gestart',
                followup_remarks TEXT,
                feedback_sent VARCHAR(20) DEFAULT 'Nee',
                updated_at DATETIME DEFAULT NULL,
                PRIMARY KEY (brand_id)
            ) $charset",

            "CREATE TABLE IF NOT EXISTS {$p}brand_notes (
                id BIGINT UNSIGNED AUTO_INCREMENT,
                brand_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                content TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_brand (brand_id)
            ) $charset",

            "CREATE TABLE IF NOT EXISTS {$p}brand_followups (
                id BIGINT UNSIGNED AUTO_INCREMENT,
                brand_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                due_date DATE NOT NULL,
                status VARCHAR(20) DEFAULT 'open',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_brand (brand_id)
            ) $charset",
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sqls as $sql) {
            dbDelta($sql);
        }
    }

    public static function import_from_excel($filepath) {
        if (!file_exists($filepath)) {
            return ['success' => false, 'message' => 'Bestand niet gevonden.'];
        }

        $rows = self::read_xlsx($filepath);
        if (!$rows) {
            return ['success' => false, 'message' => 'Kon het bestand niet lezen.'];
        }

        global $wpdb;
        $p = $wpdb->prefix . 'crm_';
        $imported = 0;

        $wpdb->query('START TRANSACTION');

        // Data starts at row index 2 (0-based), skip header rows 0 and 1
        for ($r = 2; $r < count($rows); $r++) {
            $row = $rows[$r];
            $brand_name = trim($row[0] ?? '');
            if (!$brand_name) continue;

            // Get or create brand (case-insensitive)
            $brand_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$p}brands WHERE LOWER(name) = LOWER(%s)", $brand_name
            ));
            if (!$brand_id) {
                $wpdb->insert($p . 'brands', ['name' => $brand_name]);
                $brand_id = $wpdb->insert_id;
            }
            if (!$brand_id) continue;

            // Parse date
            $check_date = null;
            $raw_date = trim($row[4] ?? '');
            if ($raw_date) {
                // Try to parse various date formats
                if (is_numeric($raw_date)) {
                    // Excel serial date number
                    $unix = ($raw_date - 25569) * 86400;
                    $check_date = date('Y-m-d', $unix);
                } else {
                    $ts = strtotime($raw_date);
                    if ($ts) $check_date = date('Y-m-d', $ts);
                }
            }

            $details = [
                'brand_id'              => $brand_id,
                'contact_person'        => trim($row[1] ?? ''),
                'email'                 => trim($row[2] ?? ''),
                'phone'                 => trim($row[3] ?? ''),
                'last_check_date'       => $check_date,
                'feed_status'           => trim($row[5] ?? ''),
                'product_count_feed'    => (int) ($row[6] ?? 0),
                'product_count_website' => (int) ($row[7] ?? 0),
                'counts_match'          => trim($row[8] ?? ''),
                'counts_remark'         => trim($row[9] ?? ''),
                'prices_status'         => trim($row[10] ?? ''),
                'price_type'            => trim($row[11] ?? ''),
                'images_status'         => trim($row[12] ?? ''),
                'images_per_product'    => trim($row[13] ?? ''),
                'topshot_status'        => trim($row[14] ?? ''),
                'scene_image_status'    => trim($row[15] ?? ''),
                'image_quality'         => trim($row[16] ?? ''),
                'attributes_status'     => trim($row[17] ?? ''),
                'floor_heating'         => trim($row[18] ?? ''),
                'sqm_per_pack'          => trim($row[19] ?? ''),
                'color_status'          => trim($row[20] ?? ''),
                'material_status'       => trim($row[21] ?? ''),
                'size_format_status'    => trim($row[22] ?? ''),
                'collection_name_status'=> trim($row[23] ?? ''),
                'other_attributes_note' => trim($row[24] ?? ''),
                'score'                 => (int) ($row[25] ?? 0),
                'tracker_status'        => trim($row[26] ?? 'Niet gestart'),
                'followup_remarks'      => trim($row[27] ?? ''),
                'feedback_sent'         => trim($row[28] ?? 'Nee'),
                'updated_at'            => current_time('mysql'),
            ];

            // Insert or update
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT brand_id FROM {$p}brand_details WHERE brand_id = %d", $brand_id
            ));
            if ($exists) {
                $wpdb->update($p . 'brand_details', $details, ['brand_id' => $brand_id]);
            } else {
                $wpdb->insert($p . 'brand_details', $details);
            }

            $imported++;
        }

        $wpdb->query('COMMIT');

        return [
            'success'  => true,
            'message'  => sprintf('%d merken geïmporteerd.', $imported),
            'imported' => $imported,
        ];
    }

    private static function read_xlsx($file_path) {
        $zip = new ZipArchive();
        if ($zip->open($file_path) !== true) return false;

        $shared_strings = [];
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml) {
            $sst = new SimpleXMLElement($xml);
            foreach ($sst->si as $si) {
                $text = '';
                if ($si->t) {
                    $text = (string) $si->t;
                } elseif ($si->r) {
                    foreach ($si->r as $run) {
                        $text .= (string) $run->t;
                    }
                }
                $shared_strings[] = $text;
            }
        }

        $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheet_xml) { $zip->close(); return false; }

        $sheet = new SimpleXMLElement($sheet_xml);
        $rows = [];

        foreach ($sheet->sheetData->row as $row) {
            $row_data = [];
            foreach ($row->c as $cell) {
                $col_index = self::col_to_index((string) $cell['r']);
                while (count($row_data) < $col_index) {
                    $row_data[] = '';
                }
                $value = '';
                if (isset($cell->v)) {
                    $value = (string) $cell->v;
                    if ((string) $cell['t'] === 's') {
                        $value = $shared_strings[(int) $value] ?? '';
                    }
                } elseif (isset($cell->is)) {
                    $value = (string) $cell->is->t;
                }
                $row_data[] = $value;
            }
            $rows[] = $row_data;
        }

        $zip->close();
        return $rows;
    }

    private static function col_to_index($cell_ref) {
        preg_match('/^([A-Z]+)/', $cell_ref, $matches);
        $col = $matches[1];
        $index = 0;
        for ($i = 0; $i < strlen($col); $i++) {
            $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }

    // ── CRUD Methods ──

    public static function get_all_brands_with_details($args = []) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        $where = '1=1';
        $params = [];

        if (!empty($args['search'])) {
            $where .= ' AND b.name LIKE %s';
            $params[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }
        if (!empty($args['tracker_status']) && $args['tracker_status'] !== 'Alle') {
            $where .= ' AND COALESCE(d.tracker_status, "Niet gestart") = %s';
            $params[] = $args['tracker_status'];
        }

        $sql = "SELECT b.id, b.name, b.parent_id, d.*, COALESCE(dc.cnt, 0) AS dealer_count,
                    p.name AS parent_name
                FROM {$p}brands b
                LEFT JOIN {$p}brand_details d ON d.brand_id = b.id
                LEFT JOIN (SELECT brand_id, COUNT(*) AS cnt FROM {$p}dealer_brand GROUP BY brand_id) dc ON dc.brand_id = b.id
                LEFT JOIN {$p}brands p ON p.id = b.parent_id
                WHERE {$where}
                ORDER BY b.name ASC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        return $wpdb->get_results($sql);
    }

    public static function get_brand_with_details($brand_id) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT b.id, b.name, b.parent_id, d.*, p.name AS parent_name
            FROM {$p}brands b
            LEFT JOIN {$p}brand_details d ON d.brand_id = b.id
            LEFT JOIN {$p}brands p ON p.id = b.parent_id
            WHERE b.id = %d",
            $brand_id
        ));
    }

    public static function get_child_brands($parent_id) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.id, b.name, d.tracker_status, d.score
            FROM {$p}brands b
            LEFT JOIN {$p}brand_details d ON d.brand_id = b.id
            WHERE b.parent_id = %d
            ORDER BY b.name ASC",
            $parent_id
        ));
    }

    public static function get_all_parents() {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        // A parent is any brand that has children, or any brand without a parent that could be one
        return $wpdb->get_results(
            "SELECT DISTINCT b.id, b.name FROM {$p}brands b
             WHERE b.parent_id IS NULL
             ORDER BY b.name ASC"
        );
    }

    public static function set_parent($brand_id, $parent_id) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';
        $wpdb->update($p . 'brands', ['parent_id' => $parent_id ?: null], ['id' => $brand_id]);
    }

    public static function update_brand_details($brand_id, $data) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        $data['updated_at'] = current_time('mysql');

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT brand_id FROM {$p}brand_details WHERE brand_id = %d", $brand_id
        ));

        if ($exists) {
            $wpdb->update($p . 'brand_details', $data, ['brand_id' => $brand_id]);
        } else {
            $data['brand_id'] = $brand_id;
            $wpdb->insert($p . 'brand_details', $data);
        }
    }

    public static function update_brand_name($brand_id, $name) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';
        $wpdb->update($p . 'brands', ['name' => $name], ['id' => $brand_id]);
    }

    // ── Notes ──

    public static function get_brand_notes($brand_id) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, u.display_name AS author
            FROM {$p}brand_notes n
            LEFT JOIN {$wpdb->users} u ON u.ID = n.user_id
            WHERE n.brand_id = %d
            ORDER BY n.created_at DESC",
            $brand_id
        ));
    }

    public static function add_brand_note($brand_id, $user_id, $content) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        $wpdb->insert($p . 'brand_notes', [
            'brand_id'   => $brand_id,
            'user_id'    => $user_id,
            'content'    => $content,
            'created_at' => current_time('mysql'),
        ]);

        return $wpdb->insert_id;
    }

    public static function delete_brand_note($id) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';
        $wpdb->delete($p . 'brand_notes', ['id' => $id]);
    }

    // ── Follow-ups ──

    public static function get_brand_followups($brand_id) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT f.*, u.display_name AS assignee_name, c.display_name AS creator_name
            FROM {$p}brand_followups f
            LEFT JOIN {$wpdb->users} u ON u.ID = f.user_id
            LEFT JOIN {$wpdb->users} c ON c.ID = f.user_id
            WHERE f.brand_id = %d
            ORDER BY f.due_date ASC",
            $brand_id
        ));
    }

    public static function add_brand_followup($brand_id, $data) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        $wpdb->insert($p . 'brand_followups', [
            'brand_id'    => $brand_id,
            'user_id'     => $data['user_id'],
            'title'       => $data['title'],
            'description' => $data['description'] ?? '',
            'due_date'    => $data['due_date'],
            'status'      => 'open',
            'created_at'  => current_time('mysql'),
        ]);

        return $wpdb->insert_id;
    }

    public static function complete_brand_followup($id) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';
        $wpdb->update($p . 'brand_followups', ['status' => 'voltooid'], ['id' => $id]);
    }

    public static function delete_brand_followup($id) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';
        $wpdb->delete($p . 'brand_followups', ['id' => $id]);
    }

    // ── Dealers ──

    public static function get_brand_dealers($brand_id) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT dl.id, dl.name, dl.city, dl.status
            FROM {$p}dealers dl
            INNER JOIN {$p}dealer_brand db ON db.dealer_id = dl.id
            WHERE db.brand_id = %d
              AND dl.deleted_at IS NULL
            ORDER BY dl.name ASC",
            $brand_id
        ));
    }

    // ── Stats ──

    public static function get_brand_stats() {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        $row = $wpdb->get_row(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN d.tracker_status = 'Compleet' THEN 1 ELSE 0 END) AS compleet,
                SUM(CASE WHEN d.tracker_status = 'In behandeling' THEN 1 ELSE 0 END) AS behandeling
            FROM {$p}brands b
            LEFT JOIN {$p}brand_details d ON d.brand_id = b.id"
        );

        $total = (int) ($row->total ?? 0);
        $compleet = (int) ($row->compleet ?? 0);
        $behandeling = (int) ($row->behandeling ?? 0);

        return [
            'total'         => $total,
            'compleet'      => $compleet,
            'in_behandeling'=> $behandeling,
            'niet_gestart'  => $total - $compleet - $behandeling,
        ];
    }

    // ── Merge ──

    /**
     * Merge secondary brand into primary. Moves all relations, picks filled values.
     */
    public static function merge_brands($primary_id, $secondary_id) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        $primary = self::get_brand_with_details($primary_id);
        $secondary = self::get_brand_with_details($secondary_id);
        if (!$primary || !$secondary) return false;

        $wpdb->query('START TRANSACTION');

        try {
            // 1. Merge brand_details: take filled values from secondary where primary is empty
            $detail_fields = [
                'contact_person', 'email', 'phone', 'last_check_date',
                'feed_status', 'product_count_feed', 'product_count_website',
                'counts_match', 'counts_remark', 'prices_status', 'price_type',
                'images_status', 'images_per_product', 'topshot_status',
                'scene_image_status', 'image_quality', 'attributes_status',
                'floor_heating', 'sqm_per_pack', 'color_status', 'material_status',
                'size_format_status', 'collection_name_status', 'other_attributes_note',
                'tracker_status', 'followup_remarks', 'feedback_sent',
            ];
            $updates = [];
            foreach ($detail_fields as $f) {
                $pval = $primary->$f ?? '';
                $sval = $secondary->$f ?? '';
                if (($pval === '' || $pval === null || $pval === 'Niet gestart' || $pval === 0) && $sval !== '' && $sval !== null) {
                    $updates[$f] = $sval;
                }
            }
            if (!empty($updates)) {
                self::update_brand_details($primary_id, $updates);
            }

            // 2. Move dealer-brand links (skip duplicates)
            $existing = $wpdb->get_col($wpdb->prepare(
                "SELECT dealer_id FROM {$p}dealer_brand WHERE brand_id = %d", $primary_id
            ));
            $secondary_dealers = $wpdb->get_col($wpdb->prepare(
                "SELECT dealer_id FROM {$p}dealer_brand WHERE brand_id = %d", $secondary_id
            ));
            foreach ($secondary_dealers as $did) {
                if (!in_array($did, $existing)) {
                    $wpdb->insert($p . 'dealer_brand', ['dealer_id' => $did, 'brand_id' => $primary_id]);
                }
            }
            $wpdb->delete($p . 'dealer_brand', ['brand_id' => $secondary_id]);

            // 3. Move notes
            $wpdb->update($p . 'brand_notes', ['brand_id' => $primary_id], ['brand_id' => $secondary_id]);

            // 4. Move followups
            $wpdb->update($p . 'brand_followups', ['brand_id' => $primary_id], ['brand_id' => $secondary_id]);

            // 5. Re-parent child brands
            $wpdb->update($p . 'brands', ['parent_id' => $primary_id], ['parent_id' => $secondary_id]);

            // 6. Move campaign links (skip duplicates)
            $campaign_table = $p . 'campaign_dealer';
            // Not applicable — campaigns link to dealers, not brands

            // 7. Delete secondary brand_details and brand
            $wpdb->delete($p . 'brand_details', ['brand_id' => $secondary_id]);
            $wpdb->delete($p . 'brands', ['id' => $secondary_id]);

            // 8. Recalculate score
            self::calculate_score($primary_id);

            $wpdb->query('COMMIT');
            return true;
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            return false;
        }
    }

    /**
     * Search brands by name (for merge autocomplete).
     */
    public static function search_brands($search, $exclude_id = 0) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';
        $like = '%' . $wpdb->esc_like($search) . '%';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.id, b.name FROM {$p}brands b WHERE b.name LIKE %s AND b.id != %d ORDER BY b.name LIMIT 20",
            $like, $exclude_id
        ));
    }

    /**
     * Delete a brand and all its related data.
     */
    public static function delete_brand($brand_id) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        $brand = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}brands WHERE id = %d", $brand_id));
        if (!$brand) return false;

        $wpdb->query('START TRANSACTION');
        try {
            // Re-parent child brands to no parent
            $wpdb->update($p . 'brands', ['parent_id' => null], ['parent_id' => $brand_id]);

            // Delete dealer-brand links
            $wpdb->delete($p . 'dealer_brand', ['brand_id' => $brand_id]);

            // Delete notes
            $wpdb->delete($p . 'brand_notes', ['brand_id' => $brand_id]);

            // Delete followups
            $wpdb->delete($p . 'brand_followups', ['brand_id' => $brand_id]);

            // Delete brand_details
            $wpdb->delete($p . 'brand_details', ['brand_id' => $brand_id]);

            // Delete the brand itself
            $wpdb->delete($p . 'brands', ['id' => $brand_id]);

            $wpdb->query('COMMIT');
            return true;
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            return false;
        }
    }

    // ── Score Calculation ──

    public static function calculate_score($brand_id) {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        $brand = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$p}brand_details WHERE brand_id = %d", $brand_id
        ));

        if (!$brand) return 0;

        $score = 0;
        $fields = ['feed_status', 'prices_status', 'images_status', 'attributes_status',
                    'topshot_status', 'scene_image_status', 'floor_heating'];
        foreach ($fields as $field) {
            if (strtolower($brand->$field ?? '') === 'ja') {
                $score++;
            }
        }

        // Update score in database
        $wpdb->update($p . 'brand_details', ['score' => $score], ['brand_id' => $brand_id]);

        return $score;
    }
}
