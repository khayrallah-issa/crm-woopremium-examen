<?php
defined('ABSPATH') || exit;

class DealerCRM_Mailchimp {

    /**
     * Register settings and admin hooks.
     */
    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /**
     * Register Mailchimp settings.
     */
    public static function register_settings() {
        register_setting('dealer_crm_mailchimp', 'dealer_crm_mailchimp_api_key');
    }

    /**
     * Create the cache table for Mailchimp campaign activity.
     */
    public static function create_table() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'crm_mailchimp_activity';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            dealer_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(255) NOT NULL,
            campaign_id VARCHAR(50) NOT NULL,
            campaign_title VARCHAR(255),
            campaign_subject VARCHAR(255),
            send_time DATETIME,
            status VARCHAR(50) DEFAULT 'sent',
            opens INT DEFAULT 0,
            clicks INT DEFAULT 0,
            last_open DATETIME NULL,
            last_click DATETIME NULL,
            synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY idx_dealer_campaign (dealer_id, campaign_id),
            KEY idx_email (email)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Get the API key.
     */
    public static function get_api_key() {
        return get_option('dealer_crm_mailchimp_api_key', '');
    }

    /**
     * Check if Mailchimp is configured.
     */
    public static function is_configured() {
        return !empty(self::get_api_key());
    }

    /**
     * Get the data center from the API key.
     */
    private static function get_dc() {
        $key = self::get_api_key();
        $parts = explode('-', $key);
        return end($parts);
    }

    /**
     * Make an API request to Mailchimp.
     */
    private static function api_request($endpoint, $params = []) {
        $key = self::get_api_key();
        if (!$key) return null;

        $dc = self::get_dc();
        $url = "https://{$dc}.api.mailchimp.com/3.0/{$endpoint}";

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('anystring:' . $key),
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            return null;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Test the API connection.
     */
    public static function test_connection() {
        $result = self::api_request('');
        if ($result && isset($result['account_name'])) {
            return [
                'success'      => true,
                'account_name' => $result['account_name'],
                'email'        => $result['email'] ?? '',
            ];
        }
        return ['success' => false];
    }

    /**
     * Get all lists (audiences).
     */
    public static function get_lists() {
        $result = self::api_request('lists', ['count' => 100, 'fields' => 'lists.id,lists.name,lists.stats.member_count']);
        if ($result && isset($result['lists'])) {
            return $result['lists'];
        }
        return [];
    }

    /**
     * Sync campaign activity for a specific dealer email.
     * Uses the member activity endpoint to get all campaigns sent to this email.
     *
     * @param int    $dealer_id
     * @param string $email
     * @return array ['synced' => int, 'error' => string|null]
     */
    public static function sync_dealer_activity($dealer_id, $email) {
        if (!self::is_configured() || empty($email)) {
            return ['synced' => 0, 'error' => 'Niet geconfigureerd of geen e-mail.'];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'crm_mailchimp_activity';
        $email_lower = strtolower(trim($email));
        $subscriber_hash = md5($email_lower);

        // Get all lists to search across
        $lists = self::get_lists();
        if (empty($lists)) {
            return ['synced' => 0, 'error' => 'Geen Mailchimp-lijsten gevonden.'];
        }

        $synced = 0;

        foreach ($lists as $list) {
            $list_id = $list['id'];

            // Check if this email exists in this list
            $member = self::api_request("lists/{$list_id}/members/{$subscriber_hash}", [
                'fields' => 'id,email_address,status',
            ]);

            if (!$member || !isset($member['id'])) {
                continue; // Email not in this list
            }

            // Get email activity for this member
            $activity = self::api_request("lists/{$list_id}/members/{$subscriber_hash}/activity", [
                'count' => 100,
            ]);

            // Get campaigns sent to this member via the member's activity feed
            // We'll also get campaign details
            $campaign_data = [];

            if ($activity && isset($activity['activity'])) {
                foreach ($activity['activity'] as $act) {
                    $cid = $act['campaign_id'] ?? '';
                    if (empty($cid)) continue;

                    if (!isset($campaign_data[$cid])) {
                        $campaign_data[$cid] = [
                            'opens'      => 0,
                            'clicks'     => 0,
                            'last_open'  => null,
                            'last_click' => null,
                            'title'      => $act['title'] ?? '',
                        ];
                    }

                    $action = $act['action'] ?? '';
                    $timestamp = $act['timestamp'] ?? null;

                    if ($action === 'open') {
                        $campaign_data[$cid]['opens']++;
                        if ($timestamp && (!$campaign_data[$cid]['last_open'] || $timestamp > $campaign_data[$cid]['last_open'])) {
                            $campaign_data[$cid]['last_open'] = $timestamp;
                        }
                    } elseif ($action === 'click') {
                        $campaign_data[$cid]['clicks']++;
                        if ($timestamp && (!$campaign_data[$cid]['last_click'] || $timestamp > $campaign_data[$cid]['last_click'])) {
                            $campaign_data[$cid]['last_click'] = $timestamp;
                        }
                    } elseif ($action === 'bounce') {
                        $campaign_data[$cid]['status'] = 'bounced';
                    }
                }
            }

            // Also get sent campaigns via the reports endpoint to catch sends without activity
            $campaigns = self::api_request("reports", [
                'count'  => 100,
                'fields' => 'reports.id,reports.campaign_title,reports.subject_line,reports.send_time,reports.list_id',
            ]);

            if ($campaigns && isset($campaigns['reports'])) {
                foreach ($campaigns['reports'] as $camp) {
                    if ($camp['list_id'] !== $list_id) continue;
                    $cid = $camp['id'];

                    // Check if this campaign was sent to this member
                    $sent_to = self::api_request("reports/{$cid}/sent-to/{$subscriber_hash}");

                    if (!$sent_to || !isset($sent_to['email_address'])) {
                        continue; // Not sent to this member
                    }

                    if (!isset($campaign_data[$cid])) {
                        $campaign_data[$cid] = [
                            'opens'      => 0,
                            'clicks'     => 0,
                            'last_open'  => null,
                            'last_click' => null,
                        ];
                    }

                    $campaign_data[$cid]['title'] = $camp['campaign_title'] ?? '';
                    $campaign_data[$cid]['subject'] = $camp['subject_line'] ?? '';
                    $campaign_data[$cid]['send_time'] = $camp['send_time'] ?? null;
                    $campaign_data[$cid]['status'] = $campaign_data[$cid]['status'] ?? 'sent';
                }
            }

            // Store in cache table
            foreach ($campaign_data as $cid => $data) {
                $wpdb->replace($table, [
                    'dealer_id'        => $dealer_id,
                    'email'            => $email_lower,
                    'campaign_id'      => $cid,
                    'campaign_title'   => $data['title'] ?? '',
                    'campaign_subject' => $data['subject'] ?? $data['title'] ?? '',
                    'send_time'        => $data['send_time'] ?? null,
                    'status'           => $data['status'] ?? 'sent',
                    'opens'            => $data['opens'],
                    'clicks'           => $data['clicks'],
                    'last_open'        => $data['last_open'] ? date('Y-m-d H:i:s', strtotime($data['last_open'])) : null,
                    'last_click'       => $data['last_click'] ? date('Y-m-d H:i:s', strtotime($data['last_click'])) : null,
                    'synced_at'        => current_time('mysql'),
                ]);
                $synced++;
            }

            usleep(200000); // Rate limiting between lists
        }

        return ['synced' => $synced, 'error' => null];
    }

    /**
     * Faster sync: look up member activity only (without checking each campaign individually).
     * This is called for the batch sync and individual dealer sync.
     *
     * @param int    $dealer_id
     * @param string $email
     * @return array ['synced' => int, 'error' => string|null]
     */
    public static function sync_dealer_fast($dealer_id, $email) {
        if (!self::is_configured() || empty($email)) {
            return ['synced' => 0, 'error' => 'Niet geconfigureerd of geen e-mail.'];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'crm_mailchimp_activity';
        $email_lower = strtolower(trim($email));
        $subscriber_hash = md5($email_lower);

        $lists = self::get_lists();
        if (empty($lists)) {
            return ['synced' => 0, 'error' => 'Geen Mailchimp-lijsten gevonden.'];
        }

        $synced = 0;
        $found_in_list = false;

        foreach ($lists as $list) {
            $list_id = $list['id'];

            // Check if member exists
            $member = self::api_request("lists/{$list_id}/members/{$subscriber_hash}", [
                'fields' => 'id,email_address,status',
            ]);

            if (!$member || !isset($member['id'])) {
                continue;
            }

            $found_in_list = true;

            // Get member activity (includes all campaign interactions)
            $activity = self::api_request("lists/{$list_id}/members/{$subscriber_hash}/activity", [
                'count' => 500,
            ]);

            if (!$activity || !isset($activity['activity'])) {
                continue;
            }

            // Group by campaign
            $campaign_data = [];
            foreach ($activity['activity'] as $act) {
                $cid = $act['campaign_id'] ?? '';
                if (empty($cid)) continue;

                if (!isset($campaign_data[$cid])) {
                    $campaign_data[$cid] = [
                        'title'      => $act['title'] ?? '',
                        'subject'    => $act['title'] ?? '',
                        'send_time'  => null,
                        'status'     => 'sent',
                        'opens'      => 0,
                        'clicks'     => 0,
                        'last_open'  => null,
                        'last_click' => null,
                    ];
                }

                $action = $act['action'] ?? '';
                $timestamp = $act['timestamp'] ?? null;

                switch ($action) {
                    case 'open':
                        $campaign_data[$cid]['opens']++;
                        if ($timestamp && (!$campaign_data[$cid]['last_open'] || $timestamp > $campaign_data[$cid]['last_open'])) {
                            $campaign_data[$cid]['last_open'] = $timestamp;
                        }
                        // Use first open timestamp as approximate send time if we don't have one
                        if (!$campaign_data[$cid]['send_time'] && $timestamp) {
                            $campaign_data[$cid]['send_time'] = $timestamp;
                        }
                        break;
                    case 'click':
                        $campaign_data[$cid]['clicks']++;
                        if ($timestamp && (!$campaign_data[$cid]['last_click'] || $timestamp > $campaign_data[$cid]['last_click'])) {
                            $campaign_data[$cid]['last_click'] = $timestamp;
                        }
                        break;
                    case 'bounce':
                        $campaign_data[$cid]['status'] = 'bounced';
                        break;
                    case 'sent':
                        if ($timestamp) {
                            $campaign_data[$cid]['send_time'] = $timestamp;
                        }
                        break;
                    case 'unsub':
                        $campaign_data[$cid]['status'] = 'unsubscribed';
                        break;
                }
            }

            // Enrich with campaign details for the ones we found
            foreach (array_keys($campaign_data) as $cid) {
                $camp_detail = self::api_request("campaigns/{$cid}", [
                    'fields' => 'id,settings.title,settings.subject_line,send_time',
                ]);
                if ($camp_detail) {
                    $campaign_data[$cid]['title'] = $camp_detail['settings']['title'] ?? $campaign_data[$cid]['title'];
                    $campaign_data[$cid]['subject'] = $camp_detail['settings']['subject_line'] ?? $campaign_data[$cid]['subject'];
                    if (!empty($camp_detail['send_time'])) {
                        $campaign_data[$cid]['send_time'] = $camp_detail['send_time'];
                    }
                }
                usleep(200000); // Rate limit
            }

            // Store in cache
            foreach ($campaign_data as $cid => $data) {
                $send_time_formatted = null;
                if ($data['send_time']) {
                    $send_time_formatted = date('Y-m-d H:i:s', strtotime($data['send_time']));
                }

                $wpdb->replace($table, [
                    'dealer_id'        => $dealer_id,
                    'email'            => $email_lower,
                    'campaign_id'      => $cid,
                    'campaign_title'   => $data['title'],
                    'campaign_subject' => $data['subject'],
                    'send_time'        => $send_time_formatted,
                    'status'           => $data['status'],
                    'opens'            => $data['opens'],
                    'clicks'           => $data['clicks'],
                    'last_open'        => $data['last_open'] ? date('Y-m-d H:i:s', strtotime($data['last_open'])) : null,
                    'last_click'       => $data['last_click'] ? date('Y-m-d H:i:s', strtotime($data['last_click'])) : null,
                    'synced_at'        => current_time('mysql'),
                ]);
                $synced++;
            }
        }

        // If dealer not found in any list, store a placeholder so batch doesn't retry
        if (!$found_in_list || $synced === 0) {
            $wpdb->replace($table, [
                'dealer_id'        => $dealer_id,
                'email'            => $email_lower,
                'campaign_id'      => '_no_data',
                'campaign_title'   => '',
                'campaign_subject' => '',
                'send_time'        => null,
                'status'           => 'not_in_list',
                'opens'            => 0,
                'clicks'           => 0,
                'last_open'        => null,
                'last_click'       => null,
                'synced_at'        => current_time('mysql'),
            ]);
        }

        return ['synced' => $synced, 'error' => null];
    }

    /**
     * Get cached activity for a dealer.
     *
     * @param int $dealer_id
     * @return array
     */
    public static function get_dealer_activity($dealer_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_mailchimp_activity';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE dealer_id = %d AND campaign_id != '_no_data' ORDER BY send_time DESC",
            $dealer_id
        )) ?: [];
    }

    /**
     * Get last sync time for a dealer.
     */
    public static function get_last_sync($dealer_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_mailchimp_activity';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(synced_at) FROM {$table} WHERE dealer_id = %d",
            $dealer_id
        ));
    }

    /**
     * Get global Mailchimp stats.
     */
    public static function get_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_mailchimp_activity';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
        if (!$table_exists) {
            return [
                'total_campaigns' => 0,
                'dealers_synced'  => 0,
                'total_sends'     => 0,
                'total_opens'     => 0,
                'total_clicks'    => 0,
            ];
        }

        return [
            'total_campaigns' => (int) $wpdb->get_var("SELECT COUNT(DISTINCT campaign_id) FROM {$table} WHERE campaign_id != '_no_data'"),
            'dealers_synced'  => (int) $wpdb->get_var("SELECT COUNT(DISTINCT dealer_id) FROM {$table} WHERE campaign_id != '_no_data'"),
            'dealers_checked' => (int) $wpdb->get_var("SELECT COUNT(DISTINCT dealer_id) FROM {$table}"),
            'total_sends'     => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE campaign_id != '_no_data'"),
            'total_opens'     => (int) $wpdb->get_var("SELECT SUM(opens) FROM {$table} WHERE campaign_id != '_no_data'"),
            'total_clicks'    => (int) $wpdb->get_var("SELECT SUM(clicks) FROM {$table} WHERE campaign_id != '_no_data'"),
        ];
    }
}
