<?php
defined('ABSPATH') || exit;

class DealerCRM_Ajax {

    public static function init() {
        $actions = ['create_dealer', 'update_dealer', 'add_note', 'delete_note', 'add_contact', 'delete_contact', 'add_tag', 'remove_tag', 'search_dealers', 'merge_dealers', 'geocode_batch', 'dismiss_duplicate', 'add_followup', 'complete_followup', 'delete_followup', 'geocode_postcode', 'send_email', 'scan_webshops', 'scan_single_dealer', 'reset_webshop_platform', 'auto_merge_duplicates', 'mailchimp_save_key', 'mailchimp_sync_dealer', 'mailchimp_sync_batch', 'slack_save_settings', 'slack_test', 'create_brand', 'update_brand', 'add_brand_note', 'delete_brand_note', 'add_brand_followup', 'complete_brand_followup', 'delete_brand_followup', 'import_brands', 'merge_brands', 'search_brands', 'delete_brand', 'save_campaign', 'add_dealers_to_campaign', 'remove_campaign_dealer', 'remove_campaign_dealers_bulk', 'search_campaign_dealers', 'export_campaign_csv', 'export_dealers_csv', 'trash_dealer', 'trash_dealers_bulk', 'restore_dealer', 'delete_dealer_permanent', 'save_office_postcode', 'mark_email_read', 'fetch_inbox'];
        foreach ($actions as $action) {
            add_action('wp_ajax_crm_' . $action, [__CLASS__, $action]);
        }
    }

    private static function verify() {
        if (!check_ajax_referer('dealer_crm_nonce', 'nonce', false)) {
            wp_send_json_error('Ongeldige beveiligingstoken.');
        }
    }

    public static function create_dealer() {
        self::verify();
        $name = sanitize_text_field($_POST['name'] ?? '');
        if (empty($name)) wp_send_json_error('Naam is verplicht.');

        $data = ['name' => $name];
        $fields = ['contact_person','owner','street', 'postcode', 'city', 'phone', 'email', 'website', 'status'];
        foreach ($fields as $f) {
            if (isset($_POST[$f]) && $_POST[$f] !== '') {
                $data[$f] = sanitize_text_field($_POST[$f]);
            }
        }
        if (!empty($data['email'])) {
            $data['email'] = sanitize_email($data['email']);
        }
        if (!empty($data['website'])) {
            $data['website'] = esc_url_raw($data['website']);
        }

        $dealer_id = DealerCRM_Database::create_dealer($data);
        if (!$dealer_id) wp_send_json_error('Fout bij aanmaken dealer.');

        // Add brands
        $brands = $_POST['brands'] ?? [];
        if (!empty($brands) && is_array($brands)) {
            foreach ($brands as $brand_name) {
                $brand_name = sanitize_text_field($brand_name);
                if (empty($brand_name)) continue;
                $brand_id = DealerCRM_Database::get_or_create_brand($brand_name);
                if ($brand_id) {
                    DealerCRM_Database::add_dealer_brand($dealer_id, $brand_id);
                }
            }
        }

        // Add tags
        $tags = $_POST['tags'] ?? [];
        if (!empty($tags) && is_array($tags)) {
            foreach ($tags as $tag_id) {
                $tag_id = (int) $tag_id;
                if ($tag_id > 0) {
                    DealerCRM_Database::add_dealer_tag($dealer_id, $tag_id);
                }
            }
        }

        DealerCRM_ActivityLog::log('dealer_created', 'Nieuwe dealer aangemaakt: ' . $name, $dealer_id);

        wp_send_json_success([
            'message'  => 'Dealer succesvol aangemaakt.',
            'redirect' => admin_url('admin.php?page=dealer-crm&action=view&id=' . $dealer_id),
        ]);
    }

    public static function update_dealer() {
        self::verify();
        $id = (int) ($_POST['dealer_id'] ?? 0);
        if (!$id) wp_send_json_error('Geen dealer ID.');

        $fields = ['name', 'contact_person', 'owner', 'street', 'postcode', 'city', 'phone', 'email', 'website', 'status'];
        $data = [];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) {
                $data[$f] = sanitize_text_field($_POST[$f]);
            }
        }
        if (!empty($data['email'])) {
            $data['email'] = sanitize_email($data['email']);
        }
        if (!empty($data['website'])) {
            $data['website'] = esc_url_raw($data['website']);
        }

        DealerCRM_Database::update_dealer($id, $data);
        DealerCRM_ActivityLog::log('dealer_updated', 'Dealer bijgewerkt.', $id, $data);
        wp_send_json_success(['message' => 'Dealer bijgewerkt.']);
    }

    public static function add_note() {
        self::verify();
        $dealer_id = (int) ($_POST['dealer_id'] ?? 0);
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        if (!$dealer_id || !$content) wp_send_json_error('Vul alle velden in.');

        $note_id = DealerCRM_Database::add_note($dealer_id, get_current_user_id(), $content);
        $user = wp_get_current_user();

        DealerCRM_ActivityLog::log('note_added', 'Notitie toegevoegd.', $dealer_id);
        wp_send_json_success([
            'id' => $note_id,
            'author' => $user->display_name,
            'content' => $content,
            'date' => date('d-m-Y H:i'),
        ]);
    }

    public static function delete_note() {
        self::verify();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error('Geen ID.');
        DealerCRM_Database::delete_note($id);
        DealerCRM_ActivityLog::log('note_deleted', 'Notitie verwijderd.', null, ['note_id' => $id]);
        wp_send_json_success();
    }

    public static function add_contact() {
        self::verify();
        $dealer_id = (int) ($_POST['dealer_id'] ?? 0);
        $type = sanitize_text_field($_POST['type'] ?? '');
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $contact_date = sanitize_text_field($_POST['contact_date'] ?? '');

        if (!$dealer_id || !$type || !$subject || !$contact_date) {
            wp_send_json_error('Vul alle verplichte velden in.');
        }

        $log_id = DealerCRM_Database::add_contact_log($dealer_id, get_current_user_id(), [
            'type' => $type,
            'subject' => $subject,
            'content' => $content,
            'contact_date' => $contact_date,
        ]);
        $user = wp_get_current_user();

        DealerCRM_ActivityLog::log('contact_added', 'Contact toegevoegd: ' . $subject, $dealer_id);
        wp_send_json_success([
            'id' => $log_id,
            'type' => $type,
            'subject' => $subject,
            'content' => $content,
            'author' => $user->display_name,
            'date' => date('d-m-Y H:i', strtotime($contact_date)),
        ]);
    }

    public static function delete_contact() {
        self::verify();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error('Geen ID.');
        DealerCRM_Database::delete_contact_log($id);
        DealerCRM_ActivityLog::log('contact_deleted', 'Contactmoment verwijderd.', null, ['contact_id' => $id]);
        wp_send_json_success();
    }

    public static function add_tag() {
        self::verify();
        $dealer_id = (int) ($_POST['dealer_id'] ?? 0);
        $tag_id = (int) ($_POST['tag_id'] ?? 0);
        if (!$dealer_id || !$tag_id) wp_send_json_error('Ongeldige gegevens.');
        DealerCRM_Database::add_dealer_tag($dealer_id, $tag_id);
        DealerCRM_ActivityLog::log('tag_added', 'Tag toegevoegd.', $dealer_id, ['tag_id' => $tag_id]);
        wp_send_json_success();
    }

    public static function remove_tag() {
        self::verify();
        $dealer_id = (int) ($_POST['dealer_id'] ?? 0);
        $tag_id = (int) ($_POST['tag_id'] ?? 0);
        if (!$dealer_id || !$tag_id) wp_send_json_error('Ongeldige gegevens.');
        DealerCRM_Database::remove_dealer_tag($dealer_id, $tag_id);
        DealerCRM_ActivityLog::log('tag_removed', 'Tag verwijderd.', $dealer_id, ['tag_id' => $tag_id]);
        wp_send_json_success();
    }

    public static function search_dealers() {
        self::verify();
        $search = sanitize_text_field($_POST['search'] ?? '');
        $exclude = (int) ($_POST['exclude'] ?? 0);
        if (strlen($search) < 2) wp_send_json_error('Minimaal 2 tekens.');

        $result = DealerCRM_Database::get_dealers([
            'search'   => $search,
            'per_page' => 10,
            'page'     => 1,
        ]);

        $dealers = [];
        foreach ($result['dealers'] as $d) {
            if ($d->id == $exclude) continue;
            $dealers[] = [
                'id'   => $d->id,
                'name' => $d->name,
                'city' => $d->city,
                'email' => $d->email,
                'phone' => $d->phone,
            ];
        }

        wp_send_json_success($dealers);
    }

    public static function merge_dealers() {
        self::verify();
        $primary_id = (int) ($_POST['primary_id'] ?? 0);
        $merge_with_id = (int) ($_POST['merge_with_id'] ?? 0);
        if (!$primary_id || !$merge_with_id) wp_send_json_error('Ongeldige dealer IDs.');
        if ($primary_id === $merge_with_id) wp_send_json_error('Kan een dealer niet met zichzelf samenvoegen.');

        $fields = [];
        $field_keys = ['name', 'contact_person', 'owner', 'street', 'postcode', 'city', 'phone', 'email', 'website', 'status'];
        foreach ($field_keys as $key) {
            $source = sanitize_text_field($_POST['field_' . $key] ?? 'primary');
            $fields[$key] = ($source === 'secondary') ? 'secondary' : 'primary';
        }

        $result = DealerCRM_Database::merge_dealers($primary_id, $merge_with_id, $fields);
        if ($result) {
            DealerCRM_Duplicates::clear_count_cache();
            DealerCRM_ActivityLog::log('dealer_merged', 'Dealers samengevoegd.', $primary_id, ['merged_with' => $merge_with_id]);
            wp_send_json_success([
                'message' => 'Dealers succesvol samengevoegd.',
                'redirect' => admin_url('admin.php?page=dealer-crm&action=view&id=' . $primary_id),
            ]);
        } else {
            wp_send_json_error('Er is een fout opgetreden bij het samenvoegen.');
        }
    }

    public static function geocode_batch() {
        self::verify();
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Onvoldoende rechten.');
        }

        $result = self::run_geocode_batch(5);

        wp_send_json_success($result);
    }

    /**
     * Shared geocoding logic used by both AJAX and WP-Cron.
     */
    public static function run_geocode_batch($batch_size = 5) {
        $dealers = DealerCRM_Database::get_dealers_without_coords($batch_size);
        $geocoded = 0;
        $failed = 0;

        foreach ($dealers as $dealer) {
            // Try full address first, then fallback to just postcode+city, then just city
            $attempts = [];

            $full_parts = array_filter([$dealer->street, $dealer->postcode, $dealer->city]);
            if (!empty($full_parts)) {
                $attempts[] = implode(', ', $full_parts) . ', Netherlands';
            }

            $short_parts = array_filter([$dealer->postcode, $dealer->city]);
            if (!empty($short_parts) && count($full_parts) > count($short_parts)) {
                $attempts[] = implode(', ', $short_parts) . ', Netherlands';
            }

            if (!empty($dealer->city)) {
                $city_only = $dealer->city . ', Netherlands';
                if (!in_array($city_only, $attempts)) {
                    $attempts[] = $city_only;
                }
            }

            $found = false;
            foreach ($attempts as $query) {
                $response = wp_remote_get(
                    'https://nominatim.openstreetmap.org/search?' . http_build_query([
                        'format' => 'json',
                        'q'      => $query,
                        'limit'  => 1,
                    ]),
                    [
                        'headers' => [
                            'User-Agent' => 'DealerCRM-WordPress-Plugin/1.0 (dealer-crm)',
                        ],
                        'timeout' => 15,
                    ]
                );

                if (!is_wp_error($response)) {
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    if (!empty($body[0]['lat']) && !empty($body[0]['lon'])) {
                        DealerCRM_Database::update_dealer_coords(
                            $dealer->id,
                            (float) $body[0]['lat'],
                            (float) $body[0]['lon']
                        );
                        $geocoded++;
                        $found = true;
                        break; // Success, skip other attempts
                    }
                }

                // Rate limit between attempts
                usleep(1100000);
            }

            if (!$found) {
                DealerCRM_Database::mark_geocode_failed($dealer->id);
                $failed++;
            }

            // Rate limit between dealers
            usleep(1100000);
        }

        $remaining = DealerCRM_Database::count_dealers_without_coords();

        return [
            'geocoded'  => $geocoded,
            'failed'    => $failed,
            'remaining' => $remaining,
        ];
    }

    public static function dismiss_duplicate() {
        self::verify();
        $dealer_id_1 = (int) ($_POST['dealer_id_1'] ?? 0);
        $dealer_id_2 = (int) ($_POST['dealer_id_2'] ?? 0);
        if (!$dealer_id_1 || !$dealer_id_2) wp_send_json_error('Ongeldige dealer IDs.');

        DealerCRM_Duplicates::dismiss_duplicate($dealer_id_1, $dealer_id_2);
        DealerCRM_Duplicates::clear_count_cache();
        wp_send_json_success(['message' => 'Duplicaat genegeerd.']);
    }

    public static function add_followup() {
        self::verify();
        $dealer_id = (int) ($_POST['dealer_id'] ?? 0);
        $title = sanitize_text_field($_POST['title'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $due_date = sanitize_text_field($_POST['due_date'] ?? '');
        $user_id = (int) ($_POST['user_id'] ?? get_current_user_id());

        if (!$dealer_id || !$title || !$due_date) {
            wp_send_json_error('Vul alle verplichte velden in.');
        }

        $followup_id = DealerCRM_Database::add_followup($dealer_id, [
            'user_id'     => $user_id,
            'title'       => $title,
            'description' => $description,
            'due_date'    => $due_date,
        ]);

        $assignee = get_user_by('ID', $user_id);
        $creator = wp_get_current_user();

        // Notify assigned user if different from creator
        DealerCRM_Notifications::notify_assignment($followup_id, $dealer_id, $user_id, $title, $due_date);

        // Slack notification
        $dealer = DealerCRM_Database::get_dealer($dealer_id);
        DealerCRM_Slack::notify_event('followup_created', [
            'dealer_id'   => $dealer_id,
            'dealer_name' => $dealer ? $dealer->name : 'Onbekend',
            'title'       => $title,
            'due_date'    => $due_date,
            'assignee'    => $assignee ? $assignee->display_name : 'Onbekend',
        ]);

        wp_send_json_success([
            'id'            => $followup_id,
            'title'         => $title,
            'description'   => $description,
            'due_date'      => $due_date,
            'status'        => 'open',
            'assignee_name' => $assignee ? $assignee->display_name : 'Onbekend',
            'creator_name'  => $creator->display_name,
        ]);
    }

    public static function complete_followup() {
        self::verify();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error('Geen ID.');

        // Get followup details before completing for Slack notification
        global $wpdb;
        $followup = $wpdb->get_row($wpdb->prepare(
            "SELECT f.*, d.name as dealer_name FROM {$wpdb->prefix}crm_followups f
             LEFT JOIN {$wpdb->prefix}crm_dealers d ON d.id = f.dealer_id
             WHERE f.id = %d", $id
        ));

        DealerCRM_Database::update_followup_status($id, 'voltooid');

        if ($followup) {
            $user = wp_get_current_user();
            DealerCRM_Slack::notify_event('followup_completed', [
                'dealer_id'    => $followup->dealer_id,
                'dealer_name'  => $followup->dealer_name ?? 'Onbekend',
                'title'        => $followup->title,
                'completed_by' => $user->display_name,
            ]);
        }

        wp_send_json_success(['message' => 'Follow-up voltooid.']);
    }

    public static function delete_followup() {
        self::verify();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error('Geen ID.');
        DealerCRM_Database::delete_followup($id);
        wp_send_json_success(['message' => 'Follow-up verwijderd.']);
    }

    public static function geocode_postcode() {
        self::verify();
        $postcode = sanitize_text_field($_POST['postcode'] ?? '');
        if (!$postcode) wp_send_json_error('Geen postcode opgegeven.');

        $query = $postcode . ', Netherlands';

        $response = wp_remote_get(
            'https://nominatim.openstreetmap.org/search?' . http_build_query([
                'format' => 'json',
                'q'      => $query,
                'limit'  => 1,
            ]),
            [
                'headers' => [
                    'User-Agent' => 'DealerCRM-WordPress-Plugin/1.0 (dealer-crm)',
                ],
                'timeout' => 10,
            ]
        );

        if (is_wp_error($response)) {
            wp_send_json_error('Geocoding mislukt: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body[0]['lat']) || empty($body[0]['lon'])) {
            wp_send_json_error('Postcode niet gevonden.');
        }

        wp_send_json_success([
            'lat'     => (float) $body[0]['lat'],
            'lng'     => (float) $body[0]['lon'],
            'address' => $body[0]['display_name'] ?? $postcode,
        ]);
    }

    public static function scan_webshops() {
        self::verify();
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Onvoldoende rechten.');
        }

        $result = DealerCRM_WebshopDetector::scan_batch(5);

        wp_send_json_success([
            'scanned'   => $result['scanned'],
            'remaining' => $result['remaining'],
        ]);
    }

    public static function scan_single_dealer() {
        self::verify();
        $dealer_id = (int) ($_POST['dealer_id'] ?? 0);
        if (!$dealer_id) wp_send_json_error('Geen dealer ID.');

        $dealer = DealerCRM_Database::get_dealer($dealer_id);
        if (!$dealer) wp_send_json_error('Dealer niet gevonden.');

        if (empty($dealer->website)) {
            wp_send_json_error('Dealer heeft geen website.');
        }

        $result = DealerCRM_WebshopDetector::detect($dealer->website);

        global $wpdb;
        $wpdb->update($wpdb->prefix . 'crm_dealers', [
            'webshop_platform'    => $result['platform'],
            'webshop_status'      => $result['status'],
            'webshop_detected_at' => current_time('mysql'),
        ], ['id' => $dealer_id]);

        wp_send_json_success([
            'platform' => $result['platform'],
            'status'   => $result['status'],
            'details'  => $result['details'],
        ]);
    }

    public static function auto_merge_duplicates() {
        self::verify();
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Onvoldoende rechten.');
        }

        $batch_size = (int) ($_POST['batch_size'] ?? 10);
        $batch_size = min($batch_size, 25);

        $duplicates = DealerCRM_Duplicates::find_duplicates($batch_size);
        $merged = 0;
        $errors = 0;
        $field_keys = ['name', 'contact_person', 'owner', 'street', 'postcode', 'city', 'phone', 'email', 'website', 'status'];

        foreach ($duplicates as $dup) {
            // Get both dealers fresh (may have been deleted by previous merge in this batch)
            $primary = DealerCRM_Database::get_dealer($dup->id1);
            $secondary = DealerCRM_Database::get_dealer($dup->id2);
            if (!$primary || !$secondary) {
                continue;
            }

            // Smart field choices: prefer filled values, fall back to primary
            $field_choices = [];
            foreach ($field_keys as $key) {
                $val_primary = trim($primary->$key ?? '');
                $val_secondary = trim($secondary->$key ?? '');

                if ($val_primary !== '' || $val_secondary === '') {
                    $field_choices[$key] = 'primary';
                } else {
                    $field_choices[$key] = 'secondary';
                }
            }

            $result = DealerCRM_Database::merge_dealers($dup->id1, $dup->id2, $field_choices);
            if ($result) {
                $merged++;
                DealerCRM_ActivityLog::log('dealer_merged', 'Automatisch samengevoegd: ' . ($secondary->name ?? '?'), $dup->id1, [
                    'merged_with' => $dup->id2,
                    'auto' => true,
                ]);
            } else {
                $errors++;
            }
        }

        DealerCRM_Duplicates::clear_count_cache();
        $remaining = DealerCRM_Duplicates::count_duplicates();

        wp_send_json_success([
            'merged'    => $merged,
            'errors'    => $errors,
            'remaining' => $remaining,
        ]);
    }

    public static function reset_webshop_platform() {
        self::verify();
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Onvoldoende rechten.');
        }

        $platform = sanitize_text_field($_POST['platform'] ?? '');
        if (!$platform) wp_send_json_error('Geen platform opgegeven.');

        global $wpdb;
        $table = $wpdb->prefix . 'crm_dealers';

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE webshop_platform = %s",
            $platform
        ));

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET webshop_platform = NULL, webshop_status = NULL, webshop_detected_at = NULL WHERE webshop_platform = %s",
            $platform
        ));

        DealerCRM_ActivityLog::log('webshop_reset', "Webshop-data gereset voor {$count} dealers met platform: {$platform}.");

        wp_send_json_success([
            'reset_count' => $count,
            'message'     => "{$count} dealers met platform '{$platform}' zijn gereset en worden opnieuw gescand.",
        ]);
    }

    public static function slack_save_settings() {
        self::verify();
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Onvoldoende rechten.');
        }

        $webhook_url = esc_url_raw($_POST['webhook_url'] ?? '');
        update_option('dealer_crm_slack_webhook_url', $webhook_url);

        // Save enabled events
        $events = [];
        $raw_events = $_POST['events'] ?? '';
        if (!empty($raw_events)) {
            $events = array_map('sanitize_text_field', explode(',', $raw_events));
        }
        update_option('dealer_crm_slack_events', $events);

        wp_send_json_success(['message' => 'Slack-instellingen opgeslagen.']);
    }

    public static function slack_test() {
        self::verify();
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Onvoldoende rechten.');
        }

        if (!DealerCRM_Slack::is_configured()) {
            wp_send_json_error('Slack webhook is niet geconfigureerd.');
        }

        $result = DealerCRM_Slack::test_connection();
        if ($result) {
            wp_send_json_success(['message' => 'Testbericht verzonden naar Slack!']);
        } else {
            wp_send_json_error('Kon geen bericht verzenden. Controleer de webhook-URL.');
        }
    }

    public static function mailchimp_save_key() {
        self::verify();
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Onvoldoende rechten.');
        }

        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        update_option('dealer_crm_mailchimp_api_key', $api_key);

        if (empty($api_key)) {
            wp_send_json_success(['message' => 'API-key verwijderd.', 'connected' => false]);
        }

        $test = DealerCRM_Mailchimp::test_connection();
        if ($test['success']) {
            wp_send_json_success([
                'message'      => 'Verbonden met Mailchimp account: ' . $test['account_name'],
                'connected'    => true,
                'account_name' => $test['account_name'],
            ]);
        } else {
            delete_option('dealer_crm_mailchimp_api_key');
            wp_send_json_error('Ongeldige API-key. Controleer de sleutel en probeer opnieuw.');
        }
    }

    public static function mailchimp_sync_dealer() {
        self::verify();
        $dealer_id = (int) ($_POST['dealer_id'] ?? 0);
        if (!$dealer_id) wp_send_json_error('Geen dealer ID.');

        $dealer = DealerCRM_Database::get_dealer($dealer_id);
        if (!$dealer || empty($dealer->email)) {
            wp_send_json_error('Dealer heeft geen e-mailadres.');
        }

        if (!DealerCRM_Mailchimp::is_configured()) {
            wp_send_json_error('Mailchimp is niet geconfigureerd.');
        }

        $result = DealerCRM_Mailchimp::sync_dealer_fast($dealer_id, $dealer->email);

        if ($result['error']) {
            wp_send_json_error($result['error']);
        }

        $activity = DealerCRM_Mailchimp::get_dealer_activity($dealer_id);

        wp_send_json_success([
            'synced'   => $result['synced'],
            'activity' => $activity,
            'message'  => $result['synced'] > 0
                ? $result['synced'] . ' campagne(s) gesynchroniseerd.'
                : 'Geen Mailchimp-campagnes gevonden voor dit e-mailadres.',
        ]);
    }

    public static function mailchimp_sync_batch() {
        self::verify();
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Onvoldoende rechten.');
        }

        if (!DealerCRM_Mailchimp::is_configured()) {
            wp_send_json_error('Mailchimp is niet geconfigureerd.');
        }

        global $wpdb;
        $p = $wpdb->prefix . 'crm_';
        $cache_table = $wpdb->prefix . 'crm_mailchimp_activity';

        // Get dealers with email that haven't been synced yet (or synced more than 24h ago)
        $dealers = $wpdb->get_results($wpdb->prepare(
            "SELECT d.id, d.email FROM {$p}dealers d
             WHERE d.email IS NOT NULL AND d.email != ''
               AND d.deleted_at IS NULL
               AND d.id NOT IN (
                   SELECT DISTINCT dealer_id FROM {$cache_table}
                   WHERE synced_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
               )
             LIMIT %d",
            5
        ));

        $synced_dealers = 0;
        $total_campaigns = 0;

        foreach (($dealers ?: []) as $dealer) {
            $result = DealerCRM_Mailchimp::sync_dealer_fast($dealer->id, $dealer->email);
            $synced_dealers++;
            $total_campaigns += $result['synced'];
            usleep(500000); // Rate limit between dealers
        }

        // Count remaining
        $remaining = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$p}dealers d
             WHERE d.email IS NOT NULL AND d.email != ''
               AND d.deleted_at IS NULL
               AND d.id NOT IN (
                   SELECT DISTINCT dealer_id FROM {$cache_table}
                   WHERE synced_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
               )"
        );

        wp_send_json_success([
            'synced_dealers'  => $synced_dealers,
            'total_campaigns' => $total_campaigns,
            'remaining'       => $remaining,
        ]);
    }

    public static function send_email() {
        self::verify();
        $dealer_id = (int) ($_POST['dealer_id'] ?? 0);
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');

        if (!$dealer_id || !$subject || !$message) {
            wp_send_json_error('Vul alle velden in.');
        }

        $dealer = DealerCRM_Database::get_dealer($dealer_id);
        if (!$dealer || !$dealer->email) {
            wp_send_json_error('Dealer heeft geen e-mailadres.');
        }

        $html_body = DealerCRM_Notifications::wrap_email($message);

        add_filter('wp_mail_content_type', [DealerCRM_Notifications::class, 'html_content_type']);
        $sent = wp_mail($dealer->email, $subject, $html_body);
        remove_filter('wp_mail_content_type', [DealerCRM_Notifications::class, 'html_content_type']);

        if (!$sent) {
            wp_send_json_error('E-mail kon niet worden verzonden.');
        }

        // Log as contact_log entry
        $log_id = DealerCRM_Database::add_contact_log($dealer_id, get_current_user_id(), [
            'type'         => 'email',
            'subject'      => $subject,
            'content'      => $message,
            'contact_date' => current_time('mysql'),
        ]);

        // Log in activity log
        DealerCRM_ActivityLog::log('email_sent', 'E-mail verstuurd: ' . $subject, $dealer_id);

        $user = wp_get_current_user();

        // Slack notification
        DealerCRM_Slack::notify_event('email_sent', [
            'dealer_id'   => $dealer_id,
            'dealer_name' => $dealer->name,
            'subject'     => $subject,
            'sent_by'     => $user->display_name,
        ]);
        wp_send_json_success([
            'id'        => $log_id,
            'subject'   => $subject,
            'message'   => $message,
            'recipient' => $dealer->email,
            'author'    => $user->display_name,
            'date'      => date('d-m-Y H:i'),
        ]);
    }

    /**
     * Auteur: Khayrallah Issa
     *
     * Markeert een inkomende mail als gelezen (US-07 inbox). Wordt aangeroepen
     * vanuit de inbox in de Contacthistorie-tab als de gebruiker een mail
     * openklapt.
     */
    public static function mark_email_read() {
        self::verify();
        $email_id = (int) ($_POST['email_id'] ?? 0);
        if (!$email_id) {
            wp_send_json_error('Geen email_id meegegeven.');
        }
        DealerCRM_Database::mark_email_read($email_id);
        $email = DealerCRM_Database::get_email($email_id);
        wp_send_json_success([
            'id'      => $email_id,
            'body'    => $email->body ?? '',
            'subject' => $email->subject ?? '',
            'from'    => $email->from_address ?? '',
            'sent_at' => $email->sent_at ?? '',
        ]);
    }

    /**
     * Auteur: Khayrallah Issa
     *
     * Triggert de IMAP-fetch (US-06). Wij roepen Local's eigen PHP CLI aan
     * met de imap-extensie tijdelijk ingeladen via -d extension=php_imap.dll.
     * Dat is nodig omdat Local de runtime php.ini bij elke start regenereert
     * en de imap-extensie daar standaard niet in zet; via -d omzeilen we dit
     * zonder iets in php.ini te hoeven aanpassen.
     *
     * In productie staat php-imap gewoon aan en draait dit als gewone cron;
     * dan is de -d niet nodig.
     */
    public static function fetch_inbox() {
        self::verify();
        $dealer_id = (int) ($_POST['dealer_id'] ?? 0);

        // Pad naar Local's PHP-binary en het cron-script.
        $php_bin = 'C:\\Users\\khayr\\AppData\\Roaming\\Local\\lightning-services\\php-8.2.29+0\\bin\\win64\\php.exe';
        $ext_dir = 'C:\\Users\\khayr\\AppData\\Roaming\\Local\\lightning-services\\php-8.2.29+0\\bin\\win64\\ext';
        $script  = ABSPATH . 'crm-extensions/cron/fetch_emails.php';

        if (!file_exists($php_bin)) {
            wp_send_json_error('Local PHP binary niet gevonden op: ' . $php_bin);
        }
        if (!file_exists($script)) {
            wp_send_json_error('Cron-script niet gevonden op: ' . $script);
        }

        // Bron-ini opzoeken via Local's PHP zelf, kopieer naar tmp, voeg
        // imap-extensie toe. Zo zijn pdo_mysql, mbstring, openssl EN imap
        // allemaal geladen wanneer fetch_emails.php draait.
        $iniInfo = shell_exec(sprintf('"%s" --ini 2>&1', $php_bin));
        if (preg_match('/Loaded Configuration File:\s*(.+)/', (string)$iniInfo, $m)) {
            $srcIni = trim($m[1]);
        } else {
            wp_send_json_error('Kon Local PHP --ini niet uitlezen: ' . substr((string)$iniInfo, 0, 200));
        }

        $tmpIni = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'crm_cli_php_' . wp_generate_password(8, false) . '.ini';
        $okCopy = @copy($srcIni, $tmpIni);
        if (!$okCopy) {
            wp_send_json_error('Kon php.ini niet kopieren van ' . $srcIni);
        }
        file_put_contents($tmpIni,
            "\n; --- Toegevoegd door fetch_inbox AJAX-handler ---\n"
            . 'extension_dir="' . $ext_dir . "\"\n"
            . "extension=php_imap.dll\n",
            FILE_APPEND
        );

        // CLI uitvoeren met onze custom ini.
        $cmd = sprintf('"%s" -c "%s" "%s" 2>&1', $php_bin, $tmpIni, $script);
        $output = shell_exec($cmd);
        @unlink($tmpIni);

        // Aantal nieuwe mails voor deze dealer tellen na de fetch.
        $new_count = 0;
        if ($dealer_id) {
            $emails = DealerCRM_Database::get_dealer_emails($dealer_id);
            foreach ($emails as $e) {
                if ($e->direction === 'in' && empty($e->read_at)) $new_count++;
            }
        }

        wp_send_json_success([
            'log'        => trim((string) $output),
            'unread_now' => $new_count,
        ]);
    }

    // ── Brand AJAX handlers ──

    public static function create_brand() {
        self::verify();
        $name = sanitize_text_field($_POST['name'] ?? '');
        if (empty($name)) wp_send_json_error('Naam is verplicht.');

        $brand_id = DealerCRM_Database::get_or_create_brand($name);
        if (!$brand_id) wp_send_json_error('Fout bij aanmaken merk.');

        // Set parent if provided
        $parent_id = (int) ($_POST['parent_id'] ?? 0);
        if ($parent_id) {
            DealerCRM_Brands::set_parent($brand_id, $parent_id);
        }

        // Save contact details if provided
        $data = [];
        foreach (['contact_person', 'email', 'phone'] as $f) {
            if (!empty($_POST[$f])) {
                $data[$f] = sanitize_text_field($_POST[$f]);
            }
        }
        if (!empty($data['email'])) {
            $data['email'] = sanitize_email($data['email']);
        }
        if (!empty($data)) {
            DealerCRM_Brands::update_brand_details($brand_id, $data);
        }

        wp_send_json_success(['id' => $brand_id, 'message' => 'Merk aangemaakt.']);
    }

    public static function update_brand() {
        self::verify();
        $brand_id = (int) ($_POST['brand_id'] ?? 0);
        if (!$brand_id) wp_send_json_error('Geen merk ID.');

        // Update brand name if provided
        $name = sanitize_text_field($_POST['name'] ?? '');
        if ($name) {
            DealerCRM_Brands::update_brand_name($brand_id, $name);
        }

        // Update parent (groothandel) if provided
        if (isset($_POST['parent_id'])) {
            $parent_id = (int) $_POST['parent_id'];
            DealerCRM_Brands::set_parent($brand_id, $parent_id ?: null);
        }

        // Collect tracker fields
        $tracker_fields = [
            'contact_person', 'email', 'phone', 'last_check_date',
            'feed_status', 'counts_match', 'counts_remark',
            'prices_status', 'price_type',
            'images_status', 'images_per_product', 'topshot_status',
            'scene_image_status', 'image_quality',
            'attributes_status', 'floor_heating', 'sqm_per_pack',
            'color_status', 'material_status', 'size_format_status',
            'collection_name_status', 'other_attributes_note',
            'tracker_status', 'followup_remarks', 'feedback_sent',
        ];
        $int_fields = ['product_count_feed', 'product_count_website'];

        $data = [];
        foreach ($tracker_fields as $f) {
            if (isset($_POST[$f])) {
                $data[$f] = sanitize_text_field($_POST[$f]);
            }
        }
        foreach ($int_fields as $f) {
            if (isset($_POST[$f])) {
                $data[$f] = (int) $_POST[$f];
            }
        }

        if (!empty($data['email'])) {
            $data['email'] = sanitize_email($data['email']);
        }
        if (isset($data['last_check_date']) && $data['last_check_date'] === '') {
            $data['last_check_date'] = null;
        }

        if (!empty($data)) {
            DealerCRM_Brands::update_brand_details($brand_id, $data);
        }

        // Auto-calculate score
        DealerCRM_Brands::calculate_score($brand_id);

        wp_send_json_success(['message' => 'Merk bijgewerkt.']);
    }

    public static function add_brand_note() {
        self::verify();
        $brand_id = (int) ($_POST['brand_id'] ?? 0);
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        if (!$brand_id || !$content) wp_send_json_error('Vul alle velden in.');

        $note_id = DealerCRM_Brands::add_brand_note($brand_id, get_current_user_id(), $content);
        $user = wp_get_current_user();

        wp_send_json_success([
            'id'      => $note_id,
            'author'  => $user->display_name,
            'content' => $content,
            'date'    => date('d-m-Y H:i'),
        ]);
    }

    public static function delete_brand_note() {
        self::verify();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error('Geen ID.');
        DealerCRM_Brands::delete_brand_note($id);
        wp_send_json_success();
    }

    public static function add_brand_followup() {
        self::verify();
        $brand_id = (int) ($_POST['brand_id'] ?? 0);
        $title = sanitize_text_field($_POST['title'] ?? '');
        $due_date = sanitize_text_field($_POST['due_date'] ?? '');
        $user_id = (int) ($_POST['user_id'] ?? get_current_user_id());
        $description = sanitize_textarea_field($_POST['description'] ?? '');

        if (!$brand_id || !$title || !$due_date) {
            wp_send_json_error('Vul alle verplichte velden in.');
        }

        $followup_id = DealerCRM_Brands::add_brand_followup($brand_id, [
            'user_id'     => $user_id,
            'title'       => $title,
            'description' => $description,
            'due_date'    => $due_date,
        ]);

        $assignee = get_userdata($user_id);
        $creator = wp_get_current_user();

        wp_send_json_success([
            'id'            => $followup_id,
            'title'         => $title,
            'description'   => $description,
            'due_date'      => $due_date,
            'status'        => 'open',
            'assignee_name' => $assignee ? $assignee->display_name : 'Onbekend',
            'creator_name'  => $creator->display_name,
        ]);
    }

    public static function complete_brand_followup() {
        self::verify();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error('Geen ID.');
        DealerCRM_Brands::complete_brand_followup($id);
        wp_send_json_success();
    }

    public static function delete_brand_followup() {
        self::verify();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error('Geen ID.');
        DealerCRM_Brands::delete_brand_followup($id);
        wp_send_json_success();
    }

    public static function import_brands() {
        self::verify();
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Onvoldoende rechten.');
        }

        if (empty($_FILES['file']['tmp_name'])) {
            wp_send_json_error('Geen bestand geselecteerd.');
        }

        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            wp_send_json_error('Alleen .xlsx bestanden zijn toegestaan.');
        }

        $result = DealerCRM_Brands::import_from_excel($file['tmp_name']);
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    public static function merge_brands() {
        self::verify();
        $primary_id = (int) ($_POST['primary_id'] ?? 0);
        $secondary_id = (int) ($_POST['secondary_id'] ?? 0);
        if (!$primary_id || !$secondary_id) wp_send_json_error('Ongeldige merk IDs.');
        if ($primary_id === $secondary_id) wp_send_json_error('Kan een merk niet met zichzelf samenvoegen.');

        $secondary = DealerCRM_Brands::get_brand_with_details($secondary_id);
        $result = DealerCRM_Brands::merge_brands($primary_id, $secondary_id);
        if ($result) {
            DealerCRM_ActivityLog::log('brand_merged', 'Merk samengevoegd: ' . ($secondary->name ?? '?'), null, [
                'primary_id' => $primary_id,
                'secondary_id' => $secondary_id,
            ]);
            wp_send_json_success([
                'message'  => 'Merken succesvol samengevoegd.',
                'redirect' => admin_url('admin.php?page=dealer-crm-brands&action=view&id=' . $primary_id),
            ]);
        } else {
            wp_send_json_error('Er is een fout opgetreden bij het samenvoegen.');
        }
    }

    public static function search_brands() {
        self::verify();
        $search = sanitize_text_field($_POST['search'] ?? '');
        $exclude_id = (int) ($_POST['exclude_id'] ?? 0);
        if (strlen($search) < 2) wp_send_json_error('Minimaal 2 tekens.');

        $brands = DealerCRM_Brands::search_brands($search, $exclude_id);
        $result = [];
        foreach ($brands as $b) {
            $result[] = ['id' => $b->id, 'name' => $b->name];
        }
        wp_send_json_success($result);
    }

    public static function delete_brand() {
        self::verify();
        $brand_id = (int) ($_POST['brand_id'] ?? 0);
        if (!$brand_id) wp_send_json_error('Geen merk ID.');

        $brand = DealerCRM_Brands::get_brand_with_details($brand_id);
        if (!$brand) wp_send_json_error('Merk niet gevonden.');

        $result = DealerCRM_Brands::delete_brand($brand_id);
        if ($result) {
            DealerCRM_ActivityLog::log('brand_deleted', 'Merk verwijderd: ' . $brand->name);
            wp_send_json_success([
                'message'  => 'Merk "' . $brand->name . '" is verwijderd.',
                'redirect' => admin_url('admin.php?page=dealer-crm-brands'),
            ]);
        } else {
            wp_send_json_error('Er is een fout opgetreden bij het verwijderen.');
        }
    }

    // ── Campaigns ──

    public static function save_campaign() {
        self::verify();
        $id = (int) ($_POST['campaign_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        if (empty($name)) wp_send_json_error('Naam is verplicht.');

        $data = [
            'name'        => $name,
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'status'      => sanitize_text_field($_POST['status'] ?? 'concept'),
        ];

        if ($id) {
            DealerCRM_Campaigns::update_campaign($id, $data);
            wp_send_json_success(['id' => $id, 'message' => 'Campagne opgeslagen.']);
        } else {
            $new_id = DealerCRM_Campaigns::create_campaign($data);
            if (!$new_id) wp_send_json_error('Fout bij aanmaken campagne.');
            wp_send_json_success(['id' => $new_id, 'message' => 'Campagne aangemaakt.']);
        }
    }

    public static function add_dealers_to_campaign() {
        self::verify();
        $campaign_id = (int) ($_POST['campaign_id'] ?? 0);
        $dealer_ids = array_map('intval', $_POST['dealer_ids'] ?? []);

        if (!$campaign_id || empty($dealer_ids)) {
            wp_send_json_error('Geen campagne of dealers geselecteerd.');
        }

        $added = DealerCRM_Campaigns::add_dealers($campaign_id, $dealer_ids);
        wp_send_json_success([
            'added'   => $added,
            'message' => sprintf('%d dealer%s toegevoegd aan campagne.', $added, $added !== 1 ? 's' : ''),
        ]);
    }

    public static function remove_campaign_dealer() {
        self::verify();
        $campaign_id = (int) ($_POST['campaign_id'] ?? 0);
        $dealer_id = (int) ($_POST['dealer_id'] ?? 0);

        if (!$campaign_id || !$dealer_id) {
            wp_send_json_error('Ongeldige parameters.');
        }

        DealerCRM_Campaigns::remove_dealer($campaign_id, $dealer_id);
        wp_send_json_success(['message' => 'Dealer verwijderd uit campagne.']);
    }

    public static function remove_campaign_dealers_bulk() {
        self::verify();
        $campaign_id = (int) ($_POST['campaign_id'] ?? 0);
        $dealer_ids = array_map('intval', $_POST['dealer_ids'] ?? []);

        if (!$campaign_id || empty($dealer_ids)) {
            wp_send_json_error('Geen campagne of dealers geselecteerd.');
        }

        $removed = 0;
        foreach ($dealer_ids as $dealer_id) {
            DealerCRM_Campaigns::remove_dealer($campaign_id, $dealer_id);
            $removed++;
        }

        wp_send_json_success([
            'removed' => $removed,
            'message' => sprintf('%d dealer%s verwijderd uit campagne.', $removed, $removed !== 1 ? 's' : ''),
        ]);
    }

    public static function search_campaign_dealers() {
        self::verify();
        $campaign_id = (int) ($_POST['campaign_id'] ?? 0);
        if (!$campaign_id) wp_send_json_error('Geen campagne geselecteerd.');

        $filters = [
            'search'  => sanitize_text_field($_POST['search'] ?? ''),
            'brand'   => sanitize_text_field($_POST['brand'] ?? ''),
            'city'    => sanitize_text_field($_POST['city'] ?? ''),
            'status'  => sanitize_text_field($_POST['status'] ?? ''),
            'webshop' => sanitize_text_field($_POST['webshop'] ?? ''),
            'tag_id'  => (int) ($_POST['tag_id'] ?? 0),
        ];

        $dealers = DealerCRM_Campaigns::search_dealers_for_campaign($campaign_id, $filters);
        wp_send_json_success(['dealers' => $dealers, 'count' => count($dealers)]);
    }

    public static function export_campaign_csv() {
        self::verify();
        $campaign_id = (int) ($_POST['campaign_id'] ?? 0);
        if (!$campaign_id) wp_send_json_error('Geen campagne geselecteerd.');

        // Build CSV data as string and return via JSON (avoids header issues with AJAX)
        $campaign = DealerCRM_Campaigns::get_campaign($campaign_id);
        if (!$campaign) wp_send_json_error('Campagne niet gevonden.');

        $dealers = DealerCRM_Campaigns::get_campaign_dealers($campaign_id);

        $csv = chr(0xEF) . chr(0xBB) . chr(0xBF); // BOM
        $csv .= "Naam;Contactpersoon;Eigenaar;Straat;Postcode;Plaats;Telefoon;E-mail;Website;Status\n";

        foreach ($dealers as $d) {
            $row = [
                $d->name,
                $d->contact_person ?? '',
                $d->owner ?? '',
                $d->street ?? '', $d->postcode ?? '', $d->city ?? '',
                $d->phone ?? '', $d->email ?? '', $d->website ?? '', $d->status ?? '',
            ];
            $csv .= implode(';', array_map(function ($v) {
                return '"' . str_replace('"', '""', $v) . '"';
            }, $row)) . "\n";
        }

        wp_send_json_success([
            'csv'      => $csv,
            'filename' => sanitize_file_name('campagne-' . $campaign->name . '-' . date('Y-m-d') . '.csv'),
        ]);
    }

    public static function export_dealers_csv() {
        self::verify();

        $filters = [
            'search'   => sanitize_text_field($_POST['search'] ?? ''),
            'brand'    => sanitize_text_field($_POST['brand'] ?? ''),
            'city'     => sanitize_text_field($_POST['city'] ?? ''),
            'status'   => sanitize_text_field($_POST['status'] ?? ''),
            'tag_id'   => (int) ($_POST['tag_id'] ?? 0),
            'webshop'  => sanitize_text_field($_POST['webshop'] ?? ''),
            'page'     => 1,
            'per_page' => 10000,
            'orderby'  => sanitize_text_field($_POST['orderby'] ?? 'name'),
            'order'    => sanitize_text_field($_POST['order'] ?? 'ASC'),
        ];

        $result = DealerCRM_Database::get_dealers($filters);
        $dealers = $result['dealers'];

        $csv = chr(0xEF) . chr(0xBB) . chr(0xBF); // BOM
        $csv .= "Naam;Contactpersoon;Eigenaar;Straat;Postcode;Plaats;Telefoon;E-mail;Website;Status\n";

        foreach ($dealers as $d) {
            $row = [
                $d->name ?? '',
                $d->contact_person ?? '',
                $d->owner ?? '',
                $d->street ?? '', $d->postcode ?? '', $d->city ?? '',
                $d->phone ?? '', $d->email ?? '', $d->website ?? '', $d->status ?? '',
            ];
            $csv .= implode(';', array_map(function ($v) {
                return '"' . str_replace('"', '""', $v) . '"';
            }, $row)) . "\n";
        }

        $filename = 'dealers-export-' . date('Y-m-d');
        $active_filters = array_filter([
            $filters['city'], $filters['brand'], $filters['status'],
        ]);
        if (!empty($active_filters)) {
            $filename .= '-' . implode('-', $active_filters);
        }

        wp_send_json_success([
            'csv'      => $csv,
            'filename' => sanitize_file_name($filename . '.csv'),
            'count'    => count($dealers),
        ]);
    }

    // ── Trash / Restore / Permanent delete ──

    public static function trash_dealer() {
        self::verify();
        if (!current_user_can('read')) wp_send_json_error('Geen toegang.');

        $id = (int) ($_POST['dealer_id'] ?? 0);
        if (!$id) wp_send_json_error('Geen dealer ID.');

        $dealer = DealerCRM_Database::get_dealer($id);
        if (!$dealer) wp_send_json_error('Dealer niet gevonden.');

        $result = DealerCRM_Database::trash_dealer($id);
        if ($result) {
            DealerCRM_ActivityLog::log('dealer_trashed', 'Dealer naar prullenbak: ' . $dealer->name, $id);
            wp_send_json_success([
                'message' => 'Dealer "' . $dealer->name . '" is naar de prullenbak verplaatst.',
                'id'      => $id,
            ]);
        }
        wp_send_json_error('Fout bij verplaatsen naar prullenbak.');
    }

    public static function trash_dealers_bulk() {
        self::verify();
        if (!current_user_can('read')) wp_send_json_error('Geen toegang.');

        $ids = array_filter(array_map('intval', (array) ($_POST['dealer_ids'] ?? [])));
        if (empty($ids)) wp_send_json_error('Geen dealers geselecteerd.');

        $count = DealerCRM_Database::trash_dealers_bulk($ids);
        if ($count > 0) {
            DealerCRM_ActivityLog::log('dealers_trashed_bulk', $count . ' dealer(s) naar prullenbak verplaatst');
        }
        wp_send_json_success([
            'count'   => $count,
            'message' => sprintf('%d dealer%s naar de prullenbak verplaatst.', $count, $count !== 1 ? 's' : ''),
        ]);
    }

    public static function restore_dealer() {
        self::verify();
        if (!current_user_can('read')) wp_send_json_error('Geen toegang.');

        $id = (int) ($_POST['dealer_id'] ?? 0);
        if (!$id) wp_send_json_error('Geen dealer ID.');

        $dealer = DealerCRM_Database::get_dealer($id);
        if (!$dealer) wp_send_json_error('Dealer niet gevonden.');

        $result = DealerCRM_Database::restore_dealer($id);
        if ($result) {
            DealerCRM_ActivityLog::log('dealer_restored', 'Dealer hersteld uit prullenbak: ' . $dealer->name, $id);
            wp_send_json_success([
                'message' => 'Dealer "' . $dealer->name . '" is hersteld.',
                'id'      => $id,
            ]);
        }
        wp_send_json_error('Fout bij herstellen.');
    }

    public static function delete_dealer_permanent() {
        self::verify();
        // Extra check: only admins can permanently delete
        if (!current_user_can('manage_options')) wp_send_json_error('Alleen beheerders mogen permanent verwijderen.');

        $id = (int) ($_POST['dealer_id'] ?? 0);
        if (!$id) wp_send_json_error('Geen dealer ID.');

        $dealer = DealerCRM_Database::get_dealer($id);
        if (!$dealer) wp_send_json_error('Dealer niet gevonden.');

        $name = $dealer->name;
        $result = DealerCRM_Database::delete_dealer_permanent($id);
        if ($result) {
            DealerCRM_ActivityLog::log('dealer_deleted_permanent', 'Dealer permanent verwijderd: ' . $name);
            wp_send_json_success([
                'message' => 'Dealer "' . $name . '" is permanent verwijderd.',
                'id'      => $id,
            ]);
        }
        wp_send_json_error('Fout bij permanent verwijderen.');
    }

    /**
     * Save kantoor postcode + geocode it via Nominatim.
     * Used in Settings > Kantoor locatie card.
     */
    public static function save_office_postcode() {
        self::verify();
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Alleen beheerders mogen instellingen wijzigen.');
        }

        $raw = (string) ($_POST['postcode'] ?? '');
        // Hard length cap before sanitization to limit attack surface
        if (strlen($raw) > 20) {
            wp_send_json_error('Postcode is te lang (max 20 tekens).');
        }
        $postcode = sanitize_text_field($raw);

        if ($postcode === '') {
            // Empty: clear the setting
            delete_option('dealer_crm_office_postcode');
            delete_option('dealer_crm_office_lat');
            delete_option('dealer_crm_office_lng');
            wp_send_json_success(['message' => 'Kantoor postcode gewist.', 'lat' => null, 'lng' => null]);
        }

        // Strict NL postcode validation: 4 cijfers + optionele spatie + 2 letters
        if (!preg_match('/^[1-9][0-9]{3}\s?[A-Za-z]{2}$/', $postcode)) {
            wp_send_json_error('Ongeldig postcode formaat. Verwacht: 1234 AB of 1234AB.');
        }
        // Normalize: uppercase letters, single space between digits and letters
        $postcode = strtoupper(preg_replace('/\s+/', '', $postcode));
        $postcode = substr($postcode, 0, 4) . ' ' . substr($postcode, 4, 2);

        update_option('dealer_crm_office_postcode', $postcode);

        // Geocode via Nominatim (same pattern as geocode_postcode)
        $query = $postcode . ', Netherlands';
        $response = wp_remote_get(
            'https://nominatim.openstreetmap.org/search?' . http_build_query([
                'format' => 'json',
                'q'      => $query,
                'limit'  => 1,
            ]),
            [
                'headers' => ['User-Agent' => 'DealerCRM-WordPress-Plugin/1.0 (dealer-crm)'],
                'timeout' => 10,
            ]
        );

        if (is_wp_error($response)) {
            wp_send_json_error('Geocoding mislukt: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body[0]['lat']) || empty($body[0]['lon'])) {
            wp_send_json_error('Postcode "' . esc_html($postcode) . '" niet gevonden door geocoder. Postcode wel opgeslagen.');
        }

        $lat = (float) $body[0]['lat'];
        $lng = (float) $body[0]['lon'];
        update_option('dealer_crm_office_lat', $lat);
        update_option('dealer_crm_office_lng', $lng);

        wp_send_json_success([
            'message' => 'Kantoor locatie opgeslagen en gegeocodeerd.',
            'lat'     => $lat,
            'lng'     => $lng,
        ]);
    }
}
