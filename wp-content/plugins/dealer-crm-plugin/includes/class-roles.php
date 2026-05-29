<?php
defined('ABSPATH') || exit;

class DealerCRM_Roles {

    /**
     * Custom capability for CRM access.
     */
    const CAP = 'use_dealer_crm';

    /**
     * Register hooks.
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'cleanup_menu'], 999);
        add_action('admin_init', [__CLASS__, 'redirect_non_crm_pages']);
        add_action('admin_bar_menu', [__CLASS__, 'cleanup_admin_bar'], 999);
        add_filter('login_redirect', [__CLASS__, 'login_redirect'], 10, 3);

        // Prevent WordPress from hanging on new user email notification
        add_action('register_new_user', [__CLASS__, 'suppress_new_user_email'], 1);
        add_action('edit_user_created_user', [__CLASS__, 'suppress_new_user_email'], 1);
        add_action('user_register', [__CLASS__, 'suppress_new_user_email'], 1);
    }

    /**
     * Disable new user email notifications to prevent hanging
     * when no mail server is configured (common in local dev).
     */
    public static function suppress_new_user_email($user_id) {
        // Remove the default WP notification that tries to send email
        remove_action('register_new_user', 'wp_send_new_user_notifications');
        remove_action('edit_user_created_user', 'wp_send_new_user_notifications');
    }

    /**
     * Create the CRM Medewerker role on activation.
     */
    public static function activate() {
        // Remove old role first to update capabilities
        remove_role('crm_medewerker');

        add_role('crm_medewerker', 'CRM Medewerker', [
            'read'             => true,   // Required for WP admin access
            self::CAP          => true,   // Custom CRM capability
            'upload_files'     => false,
            'edit_posts'       => false,
            'edit_pages'       => false,
            'publish_posts'    => false,
            'manage_options'   => false,
        ]);

        // Also grant the capability to administrators
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap(self::CAP);
        }
    }

    /**
     * Remove the custom role on deactivation.
     */
    public static function deactivate() {
        // Don't remove the role — users might still be assigned to it
        // Just remove the custom cap from admin
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->remove_cap(self::CAP);
        }
    }

    /**
     * Check if the current user is a CRM-only user (not admin).
     */
    private static function is_crm_only_user() {
        if (!is_user_logged_in()) return false;
        $user = wp_get_current_user();
        return in_array('crm_medewerker', (array) $user->roles);
    }

    /**
     * Remove all non-CRM menu items for CRM-only users.
     */
    public static function cleanup_menu() {
        if (!self::is_crm_only_user()) return;

        global $menu, $submenu;

        // List of menu slugs to KEEP
        $keep = [
            'dealer-crm-dashboard',
            'profile.php', // Let users manage their own profile
        ];

        // Remove all top-level menu items except the ones we want to keep
        if (is_array($menu)) {
            foreach ($menu as $key => $item) {
                $slug = $item[2] ?? '';
                if (!in_array($slug, $keep) && $slug !== '') {
                    remove_menu_page($slug);
                }
            }
        }

        // Also clean up any remaining separators
        if (is_array($menu)) {
            foreach ($menu as $key => $item) {
                if (strpos($item[2] ?? '', 'separator') !== false) {
                    unset($menu[$key]);
                }
            }
        }
    }

    /**
     * Redirect CRM-only users away from non-CRM admin pages.
     */
    public static function redirect_non_crm_pages() {
        if (!self::is_crm_only_user()) return;
        if (defined('DOING_AJAX') && DOING_AJAX) return;
        if (defined('DOING_CRON') && DOING_CRON) return;
        if (wp_doing_ajax()) return;

        // Don't redirect on POST requests (form submissions)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') return;

        $page = $_GET['page'] ?? '';
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');

        // Allow CRM pages
        if (strpos($page, 'dealer-crm') !== false) return;

        // Allow profile page
        if ($script === 'profile.php') return;

        // Allow admin-ajax, admin-post and other utility scripts
        $allowed_scripts = ['admin-ajax.php', 'admin-post.php', 'async-upload.php', 'update.php'];
        if (in_array($script, $allowed_scripts)) return;

        // Redirect everything else to CRM dashboard
        wp_safe_redirect(admin_url('admin.php?page=dealer-crm-dashboard'));
        exit;
    }

    /**
     * Simplify the admin bar for CRM users.
     */
    public static function cleanup_admin_bar($wp_admin_bar) {
        if (!self::is_crm_only_user()) return;

        // Remove unnecessary admin bar items
        $remove_nodes = [
            'wp-logo',
            'comments',
            'new-content',
            'updates',
            'customize',
            'edit',
            'site-name',
        ];

        foreach ($remove_nodes as $node) {
            $wp_admin_bar->remove_node($node);
        }

        // Add a CRM home link
        $wp_admin_bar->add_node([
            'id'    => 'dealer-crm-home',
            'title' => 'Dealer CRM',
            'href'  => admin_url('admin.php?page=dealer-crm-dashboard'),
        ]);
    }

    /**
     * Redirect CRM users to CRM dashboard after login.
     */
    public static function login_redirect($redirect_to, $requested_redirect_to, $user) {
        if (!is_a($user, 'WP_User')) return $redirect_to;

        if (in_array('crm_medewerker', (array) $user->roles)) {
            return admin_url('admin.php?page=dealer-crm-dashboard');
        }

        return $redirect_to;
    }
}
