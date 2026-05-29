<?php
defined('ABSPATH') || exit;

class DealerCRM_Slack {

    /**
     * Register hooks.
     */
    public static function init() {
        // Daily check for due follow-ups (runs alongside the existing daily digest)
        add_action('dealer_crm_daily_digest', [__CLASS__, 'notify_due_followups']);
    }

    /**
     * Get the webhook URL.
     */
    public static function get_webhook_url() {
        return get_option('dealer_crm_slack_webhook_url', '');
    }

    /**
     * Check if Slack is configured.
     */
    public static function is_configured() {
        return !empty(self::get_webhook_url());
    }

    /**
     * Send a message to Slack via webhook.
     *
     * @param string $text    Plain text fallback
     * @param array  $blocks  Slack Block Kit blocks (optional)
     * @return bool
     */
    public static function send($text, $blocks = []) {
        $url = self::get_webhook_url();
        if (empty($url)) return false;

        $payload = ['text' => $text];
        if (!empty($blocks)) {
            $payload['blocks'] = $blocks;
        }

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($payload),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        return wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Test the webhook connection.
     */
    public static function test_connection() {
        return self::send('Dealer CRM is verbonden met Slack!');
    }

    /**
     * Notify about follow-ups that are due today or overdue.
     * Triggered by the daily digest cron (same schedule, 08:00).
     */
    public static function notify_due_followups() {
        if (!self::is_configured()) return;

        global $wpdb;
        $p = $wpdb->prefix . 'crm_';
        $today = date('Y-m-d');

        // Get overdue follow-ups
        $overdue = $wpdb->get_results($wpdb->prepare(
            "SELECT f.*, d.name as dealer_name, u.display_name as assignee_name
             FROM {$p}followups f
             LEFT JOIN {$p}dealers d ON d.id = f.dealer_id
             LEFT JOIN {$wpdb->users} u ON u.ID = f.user_id
             WHERE f.status = 'open' AND f.due_date < %s
             ORDER BY f.due_date ASC",
            $today
        ));

        // Get follow-ups due today
        $due_today = $wpdb->get_results($wpdb->prepare(
            "SELECT f.*, d.name as dealer_name, u.display_name as assignee_name
             FROM {$p}followups f
             LEFT JOIN {$p}dealers d ON d.id = f.dealer_id
             LEFT JOIN {$wpdb->users} u ON u.ID = f.user_id
             WHERE f.status = 'open' AND f.due_date = %s
             ORDER BY f.due_date ASC",
            $today
        ));

        if (empty($overdue) && empty($due_today)) return;

        $blocks = [];

        // Header
        $blocks[] = [
            'type' => 'header',
            'text' => [
                'type' => 'plain_text',
                'text' => 'Dealer CRM - Follow-up overzicht',
                'emoji' => true,
            ],
        ];

        // Overdue section
        if (!empty($overdue)) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*:warning: " . count($overdue) . " verlopen follow-up" . (count($overdue) !== 1 ? 's' : '') . ":*",
                ],
            ];

            foreach (array_slice($overdue, 0, 10) as $f) {
                $days_overdue = (int) ((strtotime($today) - strtotime($f->due_date)) / 86400);
                $dealer_url = admin_url('admin.php?page=dealer-crm&action=view&id=' . $f->dealer_id);
                $blocks[] = [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => ":red_circle: *<{$dealer_url}|" . self::escape($f->dealer_name) . ">* - " . self::escape($f->title) . "\n"
                            . "_" . $days_overdue . " dag" . ($days_overdue !== 1 ? 'en' : '') . " verlopen_ | Toegewezen aan: " . self::escape($f->assignee_name ?? 'Onbekend'),
                    ],
                ];
            }

            if (count($overdue) > 10) {
                $blocks[] = [
                    'type' => 'context',
                    'elements' => [[
                        'type' => 'mrkdwn',
                        'text' => '_...en ' . (count($overdue) - 10) . ' meer_',
                    ]],
                ];
            }

            $blocks[] = ['type' => 'divider'];
        }

        // Due today section
        if (!empty($due_today)) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*:bell: " . count($due_today) . " follow-up" . (count($due_today) !== 1 ? 's' : '') . " voor vandaag:*",
                ],
            ];

            foreach (array_slice($due_today, 0, 10) as $f) {
                $dealer_url = admin_url('admin.php?page=dealer-crm&action=view&id=' . $f->dealer_id);
                $blocks[] = [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => ":large_blue_circle: *<{$dealer_url}|" . self::escape($f->dealer_name) . ">* - " . self::escape($f->title) . "\n"
                            . "Toegewezen aan: " . self::escape($f->assignee_name ?? 'Onbekend'),
                    ],
                ];
            }
        }

        // Footer
        $dashboard_url = admin_url('admin.php?page=dealer-crm-dashboard');
        $blocks[] = [
            'type' => 'context',
            'elements' => [[
                'type' => 'mrkdwn',
                'text' => "<{$dashboard_url}|Bekijk alle follow-ups in het CRM>",
            ]],
        ];

        $total = count($overdue) + count($due_today);
        $text = "Dealer CRM: {$total} follow-up(s) vereisen aandacht";

        self::send($text, $blocks);
    }

    /**
     * Send a notification for a specific CRM event.
     *
     * @param string $event  Event type
     * @param array  $data   Event data
     */
    public static function notify_event($event, $data = []) {
        if (!self::is_configured()) return;

        // Check which events are enabled
        $enabled_events = get_option('dealer_crm_slack_events', ['followup_due']);
        if (!in_array($event, $enabled_events)) return;

        $text = '';
        $blocks = [];

        switch ($event) {
            case 'followup_created':
                $dealer_url = admin_url('admin.php?page=dealer-crm&action=view&id=' . ($data['dealer_id'] ?? 0));
                $text = 'Nieuwe follow-up aangemaakt voor ' . ($data['dealer_name'] ?? 'onbekend');
                $blocks = [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => ":clipboard: *Nieuwe follow-up*\n"
                                . "*Dealer:* <{$dealer_url}|" . self::escape($data['dealer_name'] ?? '') . ">\n"
                                . "*Taak:* " . self::escape($data['title'] ?? '') . "\n"
                                . "*Deadline:* " . ($data['due_date'] ?? '') . "\n"
                                . "*Toegewezen aan:* " . self::escape($data['assignee'] ?? ''),
                        ],
                    ],
                ];
                break;

            case 'followup_completed':
                $dealer_url = admin_url('admin.php?page=dealer-crm&action=view&id=' . ($data['dealer_id'] ?? 0));
                $text = 'Follow-up voltooid voor ' . ($data['dealer_name'] ?? 'onbekend');
                $blocks = [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => ":white_check_mark: *Follow-up voltooid*\n"
                                . "*Dealer:* <{$dealer_url}|" . self::escape($data['dealer_name'] ?? '') . ">\n"
                                . "*Taak:* " . self::escape($data['title'] ?? '') . "\n"
                                . "*Voltooid door:* " . self::escape($data['completed_by'] ?? ''),
                        ],
                    ],
                ];
                break;

            case 'email_sent':
                $dealer_url = admin_url('admin.php?page=dealer-crm&action=view&id=' . ($data['dealer_id'] ?? 0));
                $text = 'E-mail verstuurd naar ' . ($data['dealer_name'] ?? 'onbekend');
                $blocks = [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => ":email: *E-mail verstuurd*\n"
                                . "*Dealer:* <{$dealer_url}|" . self::escape($data['dealer_name'] ?? '') . ">\n"
                                . "*Onderwerp:* " . self::escape($data['subject'] ?? '') . "\n"
                                . "*Door:* " . self::escape($data['sent_by'] ?? ''),
                        ],
                    ],
                ];
                break;
        }

        if (!empty($text)) {
            self::send($text, $blocks);
        }
    }

    /**
     * Escape text for Slack mrkdwn format.
     */
    private static function escape($text) {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text ?? '');
    }
}
