<?php
defined('ABSPATH') || exit;

class DealerCRM_Geocoder {

    /**
     * Register custom cron interval and schedule background geocoding.
     */
    public static function init() {
        add_filter('cron_schedules', [__CLASS__, 'add_cron_interval']);

        if (!wp_next_scheduled('dealer_crm_geocode_batch')) {
            wp_schedule_event(time(), 'every_two_minutes', 'dealer_crm_geocode_batch');
        }
        add_action('dealer_crm_geocode_batch', [__CLASS__, 'run_background_batch']);
    }

    /**
     * Add a custom 2-minute interval for WP-Cron.
     */
    public static function add_cron_interval($schedules) {
        $schedules['every_two_minutes'] = [
            'interval' => 120,
            'display'  => 'Elke 2 minuten',
        ];
        return $schedules;
    }

    /**
     * Deactivate: remove scheduled event.
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled('dealer_crm_geocode_batch');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'dealer_crm_geocode_batch');
        }
    }

    /**
     * Background geocoding via WP-Cron.
     * Runs a batch of 10 dealers every 2 minutes.
     * Stops automatically when no more dealers to geocode.
     */
    public static function run_background_batch() {
        $remaining = DealerCRM_Database::count_dealers_without_coords();
        if ($remaining === 0) {
            return; // Nothing to do
        }

        // Use the shared geocode logic from Ajax class
        DealerCRM_Ajax::run_geocode_batch(10);
    }
}
