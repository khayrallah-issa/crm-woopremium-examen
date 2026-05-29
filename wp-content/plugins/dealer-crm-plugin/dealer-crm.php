<?php
/**
 * Plugin Name: Dealer CRM
 * Description: Intern CRM-systeem voor dealerbeheer
 * Version: 1.0.0
 * Author: Vincent van Lettow
 * Text Domain: dealer-crm
 */

defined('ABSPATH') || exit;

define('DEALER_CRM_VERSION', '1.7.0');
define('DEALER_CRM_PATH', plugin_dir_path(__FILE__));
define('DEALER_CRM_URL', plugin_dir_url(__FILE__));

require_once DEALER_CRM_PATH . 'includes/class-database.php';
require_once DEALER_CRM_PATH . 'includes/class-admin.php';
require_once DEALER_CRM_PATH . 'includes/class-ajax.php';
require_once DEALER_CRM_PATH . 'includes/class-import.php';
require_once DEALER_CRM_PATH . 'includes/class-brands.php';
require_once DEALER_CRM_PATH . 'includes/class-campaigns.php';
require_once DEALER_CRM_PATH . 'includes/class-activity-log.php';
require_once DEALER_CRM_PATH . 'includes/class-duplicates.php';
require_once DEALER_CRM_PATH . 'includes/class-notifications.php';
require_once DEALER_CRM_PATH . 'includes/class-webshop-detector.php';
require_once DEALER_CRM_PATH . 'includes/class-geocoder.php';
require_once DEALER_CRM_PATH . 'includes/class-mailchimp.php';
require_once DEALER_CRM_PATH . 'includes/class-roles.php';
require_once DEALER_CRM_PATH . 'includes/class-slack.php';

register_activation_hook(__FILE__, function () {
    DealerCRM_Database::activate();
    DealerCRM_ActivityLog::create_table();
    DealerCRM_Duplicates::create_table();
    DealerCRM_Mailchimp::create_table();
    DealerCRM_Roles::activate();
    DealerCRM_Brands::create_tables();
    DealerCRM_Campaigns::create_tables();
});

register_deactivation_hook(__FILE__, function () {
    DealerCRM_Notifications::deactivate();
    DealerCRM_Geocoder::deactivate();
    DealerCRM_Roles::deactivate();
});

add_action('init', function () {
    DealerCRM_Admin::init();
    DealerCRM_Ajax::init();
    DealerCRM_Notifications::init();
    DealerCRM_Geocoder::init();
    DealerCRM_Mailchimp::init();
    DealerCRM_Roles::init();
    DealerCRM_Slack::init();
});

add_action('admin_init', function () {
    $db_version = get_option('dealer_crm_db_version', '0');
    if (version_compare($db_version, DEALER_CRM_VERSION, '<')) {
        DealerCRM_Database::ensure_geo_columns();
        DealerCRM_Database::ensure_indexes();
        DealerCRM_ActivityLog::create_table();
        DealerCRM_Duplicates::create_table();
        DealerCRM_WebshopDetector::ensure_columns();
        DealerCRM_Mailchimp::create_table();
        DealerCRM_Brands::create_tables();
        DealerCRM_Campaigns::create_tables();
        if (!get_role('crm_medewerker')) {
            DealerCRM_Roles::activate();
        }
        // v1.4.0: normalize city names (abbreviations, encoding, dedup)
        if (version_compare($db_version, '1.4.0', '<')) {
            DealerCRM_Database::normalize_cities();
        }
        // v1.5.0: add parent_id to brands for wholesaler hierarchy
        if (version_compare($db_version, '1.5.0', '<')) {
            DealerCRM_Database::ensure_brand_parent_column();
        }
        // v1.6.0: add deleted_at to dealers for trash/restore support
        if (version_compare($db_version, '1.6.0', '<')) {
            DealerCRM_Database::ensure_trash_column();
        }
        // v1.7.0: add contact_person + owner to dealers + seed default tags
        if (version_compare($db_version, '1.7.0', '<')) {
            DealerCRM_Database::ensure_contact_owner_columns();
            DealerCRM_Database::seed_default_tags();
        }

        update_option('dealer_crm_db_version', DEALER_CRM_VERSION);
    }
});
