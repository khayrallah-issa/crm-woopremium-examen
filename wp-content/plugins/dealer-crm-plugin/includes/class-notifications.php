<?php
defined('ABSPATH') || exit;

class DealerCRM_Notifications {

    public static function init() {
        if (!wp_next_scheduled('dealer_crm_daily_digest')) {
            wp_schedule_event(strtotime('tomorrow 08:00:00'), 'daily', 'dealer_crm_daily_digest');
        }
        add_action('dealer_crm_daily_digest', [__CLASS__, 'send_daily_digest']);
    }

    public static function deactivate() {
        $timestamp = wp_next_scheduled('dealer_crm_daily_digest');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'dealer_crm_daily_digest');
        }
    }

    /**
     * Send daily digest email to each user with open/overdue follow-ups.
     */
    public static function send_daily_digest() {
        global $wpdb;
        $p = $wpdb->prefix . 'crm_';
        $today = date('Y-m-d');
        $week_ahead = date('Y-m-d', strtotime('+7 days'));

        // Get all users that have open follow-ups (overdue or upcoming within 7 days)
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$p}followups
             WHERE status = 'open' AND due_date <= %s",
            $week_ahead
        ));

        if (empty($user_ids)) {
            return;
        }

        $dashboard_url = admin_url('admin.php?page=dealer-crm-dashboard');

        foreach ($user_ids as $user_id) {
            $user = get_user_by('ID', $user_id);
            if (!$user || !$user->user_email) {
                continue;
            }

            // Get overdue follow-ups
            $overdue = $wpdb->get_results($wpdb->prepare(
                "SELECT f.*, d.name as dealer_name, d.id as dealer_id
                 FROM {$p}followups f
                 LEFT JOIN {$p}dealers d ON f.dealer_id = d.id
                 WHERE f.user_id = %d AND f.status = 'open' AND f.due_date < %s
                 ORDER BY f.due_date ASC",
                $user_id, $today
            ));

            // Get upcoming follow-ups (due today through 7 days ahead)
            $upcoming = $wpdb->get_results($wpdb->prepare(
                "SELECT f.*, d.name as dealer_name, d.id as dealer_id
                 FROM {$p}followups f
                 LEFT JOIN {$p}dealers d ON f.dealer_id = d.id
                 WHERE f.user_id = %d AND f.status = 'open' AND f.due_date >= %s AND f.due_date <= %s
                 ORDER BY f.due_date ASC",
                $user_id, $today, $week_ahead
            ));

            $total = count($overdue) + count($upcoming);
            if ($total === 0) {
                continue;
            }

            $subject = 'Dealer CRM: Je hebt ' . $total . ' openstaande taken';

            $body = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;background:#f5f5f5;padding:20px;">';
            $body .= '<div style="background:#fff;border-radius:8px;padding:24px;border:1px solid #ddd;">';
            $body .= '<h2 style="margin:0 0 16px;color:#1d2327;">Dealer CRM - Dagelijks overzicht</h2>';
            $body .= '<p style="color:#666;margin:0 0 20px;">Hallo ' . esc_html($user->display_name) . ', hier is je taakenoverzicht.</p>';

            // Overdue section
            if (!empty($overdue)) {
                $body .= '<h3 style="color:#b32d2e;margin:0 0 12px;border-bottom:2px solid #f8d7da;padding-bottom:8px;">Verlopen taken (' . count($overdue) . ')</h3>';
                $body .= '<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">';
                foreach ($overdue as $f) {
                    $dealer_url = admin_url('admin.php?page=dealer-crm&action=view&id=' . $f->dealer_id);
                    $body .= '<tr style="border-bottom:1px solid #f0f0f1;">';
                    $body .= '<td style="padding:8px 4px;color:#b32d2e;font-weight:600;"><a href="' . esc_url($dealer_url) . '" style="color:#b32d2e;text-decoration:none;">' . esc_html($f->dealer_name) . '</a></td>';
                    $body .= '<td style="padding:8px 4px;color:#b32d2e;">' . esc_html($f->title) . '</td>';
                    $body .= '<td style="padding:8px 4px;color:#b32d2e;white-space:nowrap;">' . date('d-m-Y', strtotime($f->due_date)) . '</td>';
                    $body .= '</tr>';
                }
                $body .= '</table>';
            }

            // Upcoming section
            if (!empty($upcoming)) {
                $body .= '<h3 style="color:#004085;margin:0 0 12px;border-bottom:2px solid #cce5ff;padding-bottom:8px;">Komende taken (' . count($upcoming) . ')</h3>';
                $body .= '<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">';
                foreach ($upcoming as $f) {
                    $dealer_url = admin_url('admin.php?page=dealer-crm&action=view&id=' . $f->dealer_id);
                    $body .= '<tr style="border-bottom:1px solid #f0f0f1;">';
                    $body .= '<td style="padding:8px 4px;"><a href="' . esc_url($dealer_url) . '" style="color:#2271b1;text-decoration:none;">' . esc_html($f->dealer_name) . '</a></td>';
                    $body .= '<td style="padding:8px 4px;color:#444;">' . esc_html($f->title) . '</td>';
                    $body .= '<td style="padding:8px 4px;color:#666;white-space:nowrap;">' . date('d-m-Y', strtotime($f->due_date)) . '</td>';
                    $body .= '</tr>';
                }
                $body .= '</table>';
            }

            $body .= '<p style="margin:20px 0 0;"><a href="' . esc_url($dashboard_url) . '" style="display:inline-block;background:#2271b1;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;font-weight:500;">Naar dashboard</a></p>';
            $body .= '</div>';
            $body .= '<p style="text-align:center;color:#999;font-size:12px;margin:12px 0 0;">Verstuurd via Dealer CRM</p>';
            $body .= '</div>';

            add_filter('wp_mail_content_type', [__CLASS__, 'html_content_type']);
            wp_mail($user->user_email, $subject, $body);
            remove_filter('wp_mail_content_type', [__CLASS__, 'html_content_type']);
        }
    }

    /**
     * Send notification when a follow-up is assigned to someone else.
     */
    public static function notify_assignment($followup_id, $dealer_id, $assigned_to, $title, $due_date) {
        $current_user_id = get_current_user_id();

        // Only send if assigner is different from assignee
        if ((int) $assigned_to === $current_user_id) {
            return;
        }

        $assignee = get_user_by('ID', $assigned_to);
        $assigner = wp_get_current_user();
        if (!$assignee || !$assignee->user_email) {
            return;
        }

        $dealer = DealerCRM_Database::get_dealer($dealer_id);
        $dealer_name = $dealer ? $dealer->name : 'Onbekend';
        $dealer_url = admin_url('admin.php?page=dealer-crm&action=view&id=' . $dealer_id);

        $subject = 'Dealer CRM: Nieuwe taak toegewezen';

        $body = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;background:#f5f5f5;padding:20px;">';
        $body .= '<div style="background:#fff;border-radius:8px;padding:24px;border:1px solid #ddd;">';
        $body .= '<h2 style="margin:0 0 16px;color:#1d2327;">Nieuwe taak toegewezen</h2>';
        $body .= '<p style="color:#666;margin:0 0 16px;">Hallo ' . esc_html($assignee->display_name) . ',</p>';
        $body .= '<p style="color:#444;margin:0 0 16px;"><strong>' . esc_html($assigner->display_name) . '</strong> heeft een taak aan je toegewezen:</p>';
        $body .= '<table style="width:100%;border-collapse:collapse;margin-bottom:20px;background:#f9f9f9;border-radius:6px;">';
        $body .= '<tr><td style="padding:10px 14px;color:#666;font-weight:500;width:100px;">Dealer</td><td style="padding:10px 14px;"><a href="' . esc_url($dealer_url) . '" style="color:#2271b1;text-decoration:none;font-weight:600;">' . esc_html($dealer_name) . '</a></td></tr>';
        $body .= '<tr><td style="padding:10px 14px;color:#666;font-weight:500;">Taak</td><td style="padding:10px 14px;color:#1d2327;">' . esc_html($title) . '</td></tr>';
        $body .= '<tr><td style="padding:10px 14px;color:#666;font-weight:500;">Vervaldatum</td><td style="padding:10px 14px;color:#1d2327;">' . date('d-m-Y', strtotime($due_date)) . '</td></tr>';
        $body .= '</table>';
        $body .= '<p style="margin:0;"><a href="' . esc_url(admin_url('admin.php?page=dealer-crm-dashboard')) . '" style="display:inline-block;background:#2271b1;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;font-weight:500;">Naar dashboard</a></p>';
        $body .= '</div>';
        $body .= '<p style="text-align:center;color:#999;font-size:12px;margin:12px 0 0;">Verstuurd via Dealer CRM</p>';
        $body .= '</div>';

        add_filter('wp_mail_content_type', [__CLASS__, 'html_content_type']);
        wp_mail($assignee->user_email, $subject, $body);
        remove_filter('wp_mail_content_type', [__CLASS__, 'html_content_type']);
    }

    /**
     * Get the HTML email wrapper for outgoing emails.
     */
    public static function wrap_email($message) {
        $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;background:#f5f5f5;padding:20px;">';
        $html .= '<div style="background:#fff;border-radius:8px;padding:24px;border:1px solid #ddd;">';
        $html .= '<div style="color:#1d2327;line-height:1.6;">' . nl2br(esc_html($message)) . '</div>';
        $html .= '</div>';
        $html .= '<p style="text-align:center;color:#999;font-size:12px;margin:12px 0 0;">Verstuurd via Dealer CRM</p>';
        $html .= '</div>';
        return $html;
    }

    public static function html_content_type() {
        return 'text/html';
    }
}
