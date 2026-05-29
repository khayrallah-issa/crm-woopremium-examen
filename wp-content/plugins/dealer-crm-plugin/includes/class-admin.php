<?php
defined('ABSPATH') || exit;

class DealerCRM_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function register_menu() {
        add_menu_page('Dealer CRM', 'Dealer CRM', 'read', 'dealer-crm-dashboard', [__CLASS__, 'page_dashboard'], 'dashicons-groups', 3);
        add_submenu_page('dealer-crm-dashboard', 'Dashboard', 'Dashboard', 'read', 'dealer-crm-dashboard', [__CLASS__, 'page_dashboard']);
        add_submenu_page('dealer-crm-dashboard', 'Dealers', 'Dealers', 'read', 'dealer-crm', [__CLASS__, 'page_dealers']);
        add_submenu_page('dealer-crm-dashboard', 'Merken', 'Merken', 'read', 'dealer-crm-brands', [__CLASS__, 'page_brands']);
        add_submenu_page('dealer-crm-dashboard', 'Kaart', 'Kaart', 'read', 'dealer-crm-map', [__CLASS__, 'page_map']);
        add_submenu_page('dealer-crm-dashboard', 'Tags', 'Tags', 'manage_options', 'dealer-crm-tags', [__CLASS__, 'page_tags']);
        add_submenu_page('dealer-crm-dashboard', 'Import', 'Import', 'manage_options', 'dealer-crm-import', [__CLASS__, 'page_import']);
        add_submenu_page('dealer-crm-dashboard', 'Activiteitenlog', 'Activiteitenlog', 'read', 'dealer-crm-activity', [__CLASS__, 'page_activity_log']);
        add_submenu_page('dealer-crm-dashboard', 'Campagnes', 'Campagnes', 'read', 'dealer-crm-campaigns', [__CLASS__, 'page_campaigns']);
        add_submenu_page('dealer-crm-dashboard', 'Webshops', 'Webshops', 'read', 'dealer-crm-webshops', [__CLASS__, 'page_webshops']);
        add_submenu_page('dealer-crm-dashboard', 'Mailchimp', 'Mailchimp', 'manage_options', 'dealer-crm-mailchimp', [__CLASS__, 'page_mailchimp']);
        add_submenu_page('dealer-crm-dashboard', 'Instellingen', 'Instellingen', 'manage_options', 'dealer-crm-settings', [__CLASS__, 'page_settings']);
        $dup_count = DealerCRM_Duplicates::count_duplicates();
        $dup_badge = $dup_count > 0 ? ' <span class="awaiting-mod">' . $dup_count . '</span>' : '';
        add_submenu_page('dealer-crm-dashboard', 'Duplicaten', 'Duplicaten' . $dup_badge, 'read', 'dealer-crm-duplicates', [__CLASS__, 'page_duplicates']);
    }

    public static function enqueue_assets($hook) {
        if (strpos($hook, 'dealer-crm') === false) return;
        wp_enqueue_style('dealer-crm', DEALER_CRM_URL . 'assets/css/crm.css', [], DEALER_CRM_VERSION);
        wp_enqueue_script('dealer-crm', DEALER_CRM_URL . 'assets/js/crm.js', [], DEALER_CRM_VERSION, true);
        wp_localize_script('dealer-crm', 'dealerCRM', [
            'ajax_url'  => admin_url('admin-ajax.php'),
            'admin_url' => admin_url('admin.php'),
            'nonce'     => wp_create_nonce('dealer_crm_nonce'),
        ]);
    }

    public static function page_dashboard() {
        $user_id = get_current_user_id();
        $counts = DealerCRM_Database::count_followups_by_status($user_id);
        $filter = sanitize_text_field($_GET['followup_status'] ?? 'open');
        $followups = DealerCRM_Database::get_user_followups($user_id, $filter, 50);
        $base_url = admin_url('admin.php?page=dealer-crm-dashboard');
        $today = date('Y-m-d');
        ?>
        <div class="wrap dealer-crm-wrap">
            <h1>Dashboard</h1>

            <div class="crm-dashboard-cards">
                <div class="crm-dashboard-card crm-dashboard-card-blue">
                    <span class="crm-dashboard-card-num"><?php echo $counts['open']; ?></span>
                    <span class="crm-dashboard-card-label">Open taken</span>
                </div>
                <div class="crm-dashboard-card crm-dashboard-card-red">
                    <span class="crm-dashboard-card-num"><?php echo $counts['overdue']; ?></span>
                    <span class="crm-dashboard-card-label">Verlopen taken</span>
                </div>
                <div class="crm-dashboard-card crm-dashboard-card-green">
                    <span class="crm-dashboard-card-num"><?php echo $counts['completed_today']; ?></span>
                    <span class="crm-dashboard-card-label">Vandaag voltooid</span>
                </div>
            </div>

            <div class="crm-card">
                <div class="crm-card-header">
                    <h2>Mijn follow-ups</h2>
                    <div class="crm-followup-filters">
                        <a href="<?php echo esc_url(add_query_arg('followup_status', 'open', $base_url)); ?>" class="button <?php echo $filter === 'open' ? 'button-primary' : ''; ?>">Open</a>
                        <a href="<?php echo esc_url(add_query_arg('followup_status', 'verlopen', $base_url)); ?>" class="button <?php echo $filter === 'verlopen' ? 'button-primary' : ''; ?>">Verlopen</a>
                        <a href="<?php echo esc_url(add_query_arg('followup_status', 'voltooid', $base_url)); ?>" class="button <?php echo $filter === 'voltooid' ? 'button-primary' : ''; ?>">Voltooid</a>
                    </div>
                </div>

                <?php if (empty($followups)): ?>
                    <p class="crm-empty">Geen follow-ups gevonden.</p>
                <?php else: ?>
                    <table class="crm-table widefat striped">
                        <thead>
                            <tr>
                                <th>Dealer</th>
                                <th>Titel</th>
                                <th>Vervaldatum</th>
                                <th>Status</th>
                                <th>Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($followups as $f):
                                $is_overdue = ($f->status === 'open' && $f->due_date < $today);
                                $detail_url = admin_url('admin.php?page=dealer-crm&action=view&id=' . $f->dealer_id);
                            ?>
                                <tr class="<?php echo $is_overdue ? 'crm-followup-overdue-row' : ''; ?>">
                                    <td><a href="<?php echo esc_url($detail_url); ?>"><?php echo esc_html($f->dealer_name); ?></a></td>
                                    <td><?php echo esc_html($f->title); ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($f->due_date)); ?></td>
                                    <td>
                                        <?php if ($is_overdue): ?>
                                            <span class="crm-followup-badge crm-followup-badge-verlopen">Verlopen</span>
                                        <?php else: ?>
                                            <span class="crm-followup-badge crm-followup-badge-<?php echo esc_attr($f->status); ?>"><?php echo esc_html(ucfirst($f->status)); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($f->status === 'open'): ?>
                                            <button class="button button-small crm-complete-followup-btn" data-id="<?php echo $f->id; ?>">Voltooien</button>
                                        <?php endif; ?>
                                        <button class="button button-small crm-delete-followup-btn" data-id="<?php echo $f->id; ?>">Verwijderen</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public static function page_dealers() {
        if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
            self::render_dealer_detail((int) $_GET['id']);
            return;
        }
        if (isset($_GET['action']) && $_GET['action'] === 'merge' && isset($_GET['id'])) {
            self::render_merge_page((int) $_GET['id'], (int) ($_GET['merge_with'] ?? 0));
            return;
        }
        if (isset($_GET['action']) && $_GET['action'] === 'new') {
            self::render_new_dealer();
            return;
        }
        self::render_dealer_list();
    }

    private static function render_new_dealer() {
        $brands = DealerCRM_Database::get_all_brands();
        $tags = DealerCRM_Database::get_all_tags();
        ?>
        <div class="wrap dealer-crm-wrap">
            <h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=dealer-crm')); ?>" style="text-decoration:none;color:#666;font-size:0.8em;">&larr;</a>
                Nieuwe dealer toevoegen
            </h1>

            <div class="crm-detail-grid" style="grid-template-columns: 2fr 1fr;">
                <div class="crm-card">
                    <form id="crm-new-dealer-form">
                        <table class="crm-info-table">
                            <tr>
                                <th>Naam *</th>
                                <td><input type="text" name="name" required style="width:100%;"></td>
                            </tr>
                            <tr> 
                                <th>Contactpersoon</th>
                                <td><input type="text" name="contact_person" style="width:100%;"></td>
                            </tr>
                            <tr>
                                <th>Eigenaar</th>
                                <td><input type="text" name="owner" style="width:100%;"></td>
                            </tr>
                            <tr>
                                <th>Straat</th>
                                <td><input type="text" name="street" style="width:100%;"></td>
                            </tr>
                            <tr>
                                <th>Postcode</th>
                                <td><input type="text" name="postcode" style="width:200px;"></td>
                            </tr>
                            <tr>
                                <th>Plaats</th>
                                <td><input type="text" name="city" style="width:100%;"></td>
                            </tr>
                            <tr>
                                <th>Telefoon</th>
                                <td><input type="text" name="phone" style="width:100%;"></td>
                            </tr>
                            <tr>
                                <th>E-mail</th>
                                <td><input type="email" name="email" style="width:100%;"></td>
                            </tr>
                            <tr>
                                <th>Website</th>
                                <td><input type="url" name="website" placeholder="https://" style="width:100%;"></td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <select name="status" style="width:200px;">
                                        <option value="actief">Actief</option>
                                        <option value="inactief">Inactief</option>
                                        <option value="prospect">Prospect</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <div style="margin-top:1rem;display:flex;gap:0.5rem;">
                            <button type="submit" class="button button-primary" id="crm-save-new-dealer">Dealer opslaan</button>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=dealer-crm')); ?>" class="button">Annuleren</a>
                        </div>
                    </form>
                </div>

                <div>
                    <div class="crm-card">
                        <h3 style="margin-top:0;">Merken</h3>
                        <div id="crm-new-dealer-brands">
                            <?php foreach ($brands as $b): ?>
                                <label style="display:block;margin-bottom:0.3rem;cursor:pointer;">
                                    <input type="checkbox" name="brands[]" value="<?php echo esc_attr($b); ?>">
                                    <?php echo esc_html($b); ?>
                                </label>
                            <?php endforeach; ?>
                            <?php if (empty($brands)): ?>
                                <p class="crm-empty" style="font-size:0.85rem;">Geen merken beschikbaar.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="crm-card" style="margin-top:1rem;">
                        <h3 style="margin-top:0;">Tags</h3>
                        <div id="crm-new-dealer-tags">
                            <?php foreach ($tags as $t): ?>
                                <label style="display:block;margin-bottom:0.3rem;cursor:pointer;">
                                    <input type="checkbox" name="tags[]" value="<?php echo $t->id; ?>">
                                    <?php echo esc_html($t->name); ?>
                                </label>
                            <?php endforeach; ?>
                            <?php if (empty($tags)): ?>
                                <p class="crm-empty" style="font-size:0.85rem;">Geen tags beschikbaar.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_dealer_list() {
        $brands = DealerCRM_Database::get_all_brands();
        $cities = DealerCRM_Database::get_all_cities();
        $tags = DealerCRM_Database::get_all_tags();
        $statuses = DealerCRM_Database::get_statuses();
        $stats = DealerCRM_Database::get_stats();
        $trashed_count = DealerCRM_Database::count_trashed_dealers();

        // Trash view: shows dealers currently in the trash
        $view_trash = !empty($_GET['view']) && $_GET['view'] === 'trash';
        $can_permanent_delete = current_user_can('manage_options');

        $search = sanitize_text_field($_GET['s'] ?? '');
        $brand = sanitize_text_field($_GET['brand'] ?? '');
        $city = sanitize_text_field($_GET['city'] ?? '');
        $status = sanitize_text_field($_GET['status'] ?? '');
        $tag_id = (int) ($_GET['tag_id'] ?? 0);
        $webshop = sanitize_text_field($_GET['webshop'] ?? '');
        $paged = max(1, (int) ($_GET['paged'] ?? 1));
        $orderby = sanitize_text_field($_GET['orderby'] ?? 'name');
        $order = sanitize_text_field($_GET['order'] ?? 'ASC');

        // Postcode/radius search
        $postcode_search = sanitize_text_field($_GET['postcode'] ?? '');
        $radius_km = (int) ($_GET['radius'] ?? 0);
        $radius_active = false;
        $radius_dealers = [];
        $radius_message = '';

        if ($postcode_search && $radius_km > 0) {
            // Geocode the postcode server-side
            $query = $postcode_search . ', Netherlands';
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

            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($body[0]['lat']) && !empty($body[0]['lon'])) {
                    $geo_lat = (float) $body[0]['lat'];
                    $geo_lng = (float) $body[0]['lon'];
                    $radius_active = true;

                    $radius_dealers = DealerCRM_Database::get_dealers_by_radius($geo_lat, $geo_lng, $radius_km, [
                        'search' => $search,
                        'brand'  => $brand,
                        'status' => $status,
                        'tag_id' => $tag_id,
                    ]);

                    $radius_message = count($radius_dealers) . ' dealer' . (count($radius_dealers) !== 1 ? 's' : '') .
                        ' gevonden binnen ' . $radius_km . 'km van ' . esc_html($postcode_search);
                } else {
                    $radius_message = 'Postcode "' . esc_html($postcode_search) . '" kon niet worden gevonden.';
                }
            } else {
                $radius_message = 'Fout bij het opzoeken van de postcode. Probeer het opnieuw.';
            }
        }

        if (!$radius_active) {
            $result = DealerCRM_Database::get_dealers([
                'search'  => $search, 'brand' => $brand, 'city' => $city,
                'status'  => $status, 'tag_id' => $tag_id, 'webshop' => $webshop,
                'page'    => $paged, 'per_page' => 25,
                'orderby' => $orderby, 'order' => $order,
                'trashed' => $view_trash,
            ]);

            $dealers = $result['dealers'];
            $total = $result['total'];
            $total_pages = ceil($total / 25);
        } else {
            $dealers = $radius_dealers;
            $total = count($radius_dealers);
            $total_pages = 1;
        }

        // Batch-load brands and tags for all dealers on this page (avoids N+1)
        $dealer_ids = array_map(function ($d) { return $d->id; }, $dealers);
        $brands_map = DealerCRM_Database::get_brands_for_dealers($dealer_ids);
        $tags_map = DealerCRM_Database::get_tags_for_dealers($dealer_ids);

        $base_url = admin_url('admin.php?page=dealer-crm');
        ?>
        <div class="wrap dealer-crm-wrap">
            <h1 style="display:flex;align-items:center;gap:1rem;">
                <?php echo $view_trash ? 'Prullenbak' : 'Dealer CRM'; ?>
                <?php if (!$view_trash): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=dealer-crm&action=new')); ?>" class="button button-primary" style="font-size:13px;">+ Nieuwe dealer</a>
                <?php else: ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=dealer-crm')); ?>" class="button" style="font-size:13px;">&larr; Terug naar dealers</a>
                <?php endif; ?>
            </h1>

            <ul class="subsubsub" style="margin:0 0 0.5rem 0;">
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=dealer-crm')); ?>" class="<?php echo !$view_trash ? 'current' : ''; ?>">
                        Alle dealers <span class="count">(<?php echo number_format($stats['total_dealers']); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=dealer-crm&view=trash')); ?>" class="<?php echo $view_trash ? 'current' : ''; ?>" style="<?php echo $trashed_count > 0 && !$view_trash ? 'color:#b32d2e;' : ''; ?>">
                        Prullenbak <span class="count">(<?php echo number_format($trashed_count); ?>)</span>
                    </a>
                </li>
            </ul>

            <div class="crm-stats">
                <div class="crm-stat"><span class="crm-stat-num"><?php echo number_format($stats['total_dealers']); ?></span><span class="crm-stat-label">Dealers</span></div>
                <div class="crm-stat"><span class="crm-stat-num"><?php echo number_format($stats['total_brands']); ?></span><span class="crm-stat-label">Merken</span></div>
                <div class="crm-stat"><span class="crm-stat-num"><?php echo number_format($stats['total_contacts']); ?></span><span class="crm-stat-label">Contactmomenten</span></div>
                <div class="crm-stat"><span class="crm-stat-num"><?php echo number_format($stats['total_notes']); ?></span><span class="crm-stat-label">Notities</span></div>
            </div>

            <form method="get" class="crm-filters">
                <input type="hidden" name="page" value="dealer-crm">
                <div class="crm-filter-row">
                    <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Zoek op naam, e-mail, telefoon of plaats..." class="crm-search-input">

                    <select name="brand" class="crm-filter-select">
                        <option value="">Alle merken</option>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?php echo esc_attr($b); ?>" <?php selected($brand, $b); ?>><?php echo esc_html($b); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="city" class="crm-filter-select crm-filter-city">
                        <option value="">Alle plaatsen</option>
                        <?php foreach ($cities as $c): ?>
                            <option value="<?php echo esc_attr($c); ?>" <?php selected($city, $c); ?>><?php echo esc_html($c); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="status" class="crm-filter-select">
                        <option value="">Alle statussen</option>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?php echo esc_attr($s); ?>" <?php selected($status, $s); ?>><?php echo esc_html(ucfirst($s)); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="tag_id" class="crm-filter-select">
                        <option value="">Alle tags</option>
                        <?php foreach ($tags as $t): ?>
                            <option value="<?php echo $t->id; ?>" <?php selected($tag_id, $t->id); ?>><?php echo esc_html($t->name); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="webshop" class="crm-filter-select">
                        <option value="">Alle webshops</option>
                        <option value="yes" <?php selected($webshop, 'yes'); ?>>Met webshop</option>
                        <option value="no" <?php selected($webshop, 'no'); ?>>Zonder webshop</option>
                    </select>

                    <input type="text" name="postcode" value="<?php echo esc_attr($postcode_search); ?>" placeholder="Postcode..." class="crm-postcode-input">
                    <select name="radius" class="crm-filter-select crm-radius-select">
                        <option value="">Straal</option>
                        <?php foreach ([5, 10, 15, 20, 30, 50, 100] as $r): ?>
                            <option value="<?php echo $r; ?>" <?php selected($radius_km, $r); ?>><?php echo $r; ?> km</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button button-primary">Zoeken</button>
                    <a href="<?php echo $base_url; ?>" class="button">Reset</a>
                </div>
            </form>

            <?php
            $campaigns = DealerCRM_Campaigns::get_all_campaigns(['status' => 'actief']);
            ?>
            <div class="crm-campaign-bar" id="crm-campaign-bar" style="display:none;margin:0.5rem 0;padding:0.6rem 1rem;background:#f0f6fc;border:1px solid #c3d9ed;border-radius:4px;align-items:center;gap:0.5rem;">
                <span id="crm-selected-count">0</span> geselecteerd —
                <?php if (!$view_trash && !empty($campaigns)): ?>
                    <select id="crm-campaign-select" class="crm-filter-select">
                        <option value="">Kies campagne...</option>
                        <?php foreach ($campaigns as $camp): ?>
                            <option value="<?php echo $camp->id; ?>"><?php echo esc_html($camp->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="button button-primary button-small" id="crm-add-to-campaign-btn">Toevoegen aan campagne</button>
                    <span style="margin:0 0.25rem;color:#999;">|</span>
                <?php endif; ?>
                <?php if (!$view_trash): ?>
                    <button class="button button-small" id="crm-bulk-trash-btn" style="color:#b32d2e;">
                        <span class="dashicons dashicons-trash" style="vertical-align:middle;font-size:16px;width:16px;height:16px;"></span>
                        Naar prullenbak
                    </button>
                <?php else: ?>
                    <button class="button button-small" id="crm-bulk-restore-btn">
                        <span class="dashicons dashicons-undo" style="vertical-align:middle;font-size:16px;width:16px;height:16px;"></span>
                        Herstellen
                    </button>
                    <?php if ($can_permanent_delete): ?>
                        <button class="button button-small" id="crm-bulk-delete-permanent-btn" style="color:#b32d2e;">
                            <span class="dashicons dashicons-no" style="vertical-align:middle;font-size:16px;width:16px;height:16px;"></span>
                            Permanent verwijderen
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($radius_message): ?>
                <div class="crm-radius-message notice notice-<?php echo $radius_active ? 'info' : 'warning'; ?>" style="margin:0.5rem 0;">
                    <p><?php echo $radius_message; ?></p>
                </div>
            <?php endif; ?>

            <div class="crm-results-info" style="display:flex;align-items:center;gap:1rem;">
                <span><?php echo number_format($total); ?> dealer<?php echo $total !== 1 ? 's' : ''; ?> gevonden</span>
                <button class="button button-small" id="crm-export-dealers-btn">Exporteer resultaat</button>
            </div>

            <table class="crm-table widefat striped">
                <thead>
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="crm-select-all-dealers" title="Alles selecteren"></th>
                        <?php
                        if ($radius_active): ?>
                            <th>Afstand</th>
                        <?php endif;
                        $sort_cols = ['name' => 'Naam', 'city' => 'Plaats', 'status' => 'Status'];
                        foreach ($sort_cols as $col => $label):
                            $new_order = ($orderby === $col && $order === 'ASC') ? 'DESC' : 'ASC';
                            $arrow = $orderby === $col ? ($order === 'ASC' ? ' ▲' : ' ▼') : '';
                            $sort_url = add_query_arg(['orderby' => $col, 'order' => $new_order], $base_url);
                        ?>
                            <th><a href="<?php echo esc_url($sort_url); ?>"><?php echo $label . $arrow; ?></a></th>
                        <?php endforeach; ?>
                        <th>Telefoon</th>
                        <th>E-mail</th>
                        <th>Merken</th>
                        <th style="width:90px;text-align:right;">Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dealers)): ?>
                        <tr><td colspan="<?php echo ($radius_active ? 9 : 8); ?>" class="crm-no-results"><?php echo $view_trash ? 'Prullenbak is leeg.' : 'Geen dealers gevonden.'; ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($dealers as $d):
                        $d_brands = $brands_map[$d->id] ?? [];
                        $d_tags = $tags_map[$d->id] ?? [];
                        $detail_url = add_query_arg(['action' => 'view', 'id' => $d->id], $base_url);
                    ?>
                        <tr class="crm-dealer-row" data-href="<?php echo esc_url($detail_url); ?>" data-dealer-id="<?php echo $d->id; ?>" data-dealer-name="<?php echo esc_attr($d->name); ?>">
                            <td class="crm-checkbox-cell" style="width:30px;"><input type="checkbox" class="crm-dealer-checkbox" value="<?php echo $d->id; ?>"></td>
                            <?php if ($radius_active): ?>
                                <td class="crm-distance-cell"><?php echo esc_html($d->distance); ?> km</td>
                            <?php endif; ?>
                            <td>
                                <a href="<?php echo esc_url($detail_url); ?>" class="crm-dealer-name"><?php echo esc_html($d->name); ?></a>
                                <?php if (!empty($d->webshop_status) && $d->webshop_status === 'detected' && !empty($d->webshop_platform)): ?>
                                    <span class="crm-webshop-chip crm-webshop-<?php echo esc_attr(DealerCRM_WebshopDetector::get_platform_class($d->webshop_platform)); ?>"><?php echo esc_html(DealerCRM_WebshopDetector::get_platform_abbr($d->webshop_platform)); ?></span>
                                <?php endif; ?>
                                <?php foreach ($d_tags as $tag): ?>
                                    <span class="crm-tag-chip" style="background:<?php echo esc_attr($tag->color); ?>"><?php echo esc_html($tag->name); ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td><?php echo esc_html($d->city); ?></td>
                            <td><span class="crm-status crm-status-<?php echo esc_attr($d->status); ?>"><?php echo esc_html(ucfirst($d->status)); ?></span></td>
                            <td><?php echo esc_html($d->phone); ?></td>
                            <td><?php if ($d->email): ?><a href="mailto:<?php echo esc_attr($d->email); ?>"><?php echo esc_html($d->email); ?></a><?php endif; ?></td>
                            <td class="crm-brands-cell"><?php echo esc_html(implode(', ', array_slice($d_brands, 0, 3))); if (count($d_brands) > 3) echo ' +' . (count($d_brands) - 3); ?></td>
                            <td class="crm-actions-cell" style="text-align:right;white-space:nowrap;">
                                <?php if (!$view_trash): ?>
                                    <button type="button" class="button button-small crm-trash-dealer-btn" title="Naar prullenbak" style="color:#b32d2e;">
                                        <span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;line-height:1;vertical-align:middle;"></span>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="button button-small crm-restore-dealer-btn" title="Herstellen">
                                        <span class="dashicons dashicons-undo" style="font-size:16px;width:16px;height:16px;line-height:1;vertical-align:middle;"></span>
                                    </button>
                                    <?php if ($can_permanent_delete): ?>
                                        <button type="button" class="button button-small crm-delete-permanent-btn" title="Permanent verwijderen" style="color:#b32d2e;">
                                            <span class="dashicons dashicons-no" style="font-size:16px;width:16px;height:16px;line-height:1;vertical-align:middle;"></span>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="crm-pagination">
                    <?php
                    $pagination_args = array_filter([
                        's' => $search, 'brand' => $brand, 'city' => $city,
                        'status' => $status, 'tag_id' => $tag_id ?: null,
                        'webshop' => $webshop ?: null,
                        'orderby' => $orderby !== 'name' ? $orderby : null,
                        'order' => $order !== 'ASC' ? $order : null,
                        'view' => $view_trash ? 'trash' : null,
                    ]);
                    echo paginate_links([
                        'base'    => $base_url . '%_%',
                        'format'  => '&paged=%#%',
                        'current' => $paged,
                        'total'   => $total_pages,
                        'add_args' => $pagination_args,
                    ]);
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_dealer_detail($id) {
        $dealer = DealerCRM_Database::get_dealer($id);
        if (!$dealer) {
            echo '<div class="wrap"><h1>Dealer niet gevonden</h1><a href="' . admin_url('admin.php?page=dealer-crm') . '">← Terug</a></div>';
            return;
        }

        $brands = DealerCRM_Database::get_dealer_brands($id);
        $tags = DealerCRM_Database::get_dealer_tags($id);
        $all_tags = DealerCRM_Database::get_all_tags();
        $notes = DealerCRM_Database::get_notes($id);
        $contacts = DealerCRM_Database::get_contact_log($id);
        // Auteur: Khayrallah Issa
        // E-mails (in + uit) uit de nieuwe wp_crm_emails tabel ophalen voor de
        // "E-mail" tab. $unread_in telt de ongelezen inkomende mails, zodat we
        // er een rode badge op de tab kunnen tonen.
        $dealer_emails = DealerCRM_Database::get_dealer_emails($id);
        $unread_in = 0;
        foreach ($dealer_emails as $de) {
            if ($de->direction === 'in' && empty($de->read_at)) {
                $unread_in++;
            }
        }
        $followups = DealerCRM_Database::get_dealer_followups($id);
        $statuses = DealerCRM_Database::get_statuses();
        $wp_users = get_users(['fields' => ['ID', 'display_name']]);
        $today = date('Y-m-d');
        ?>
        <div class="wrap dealer-crm-wrap">
            <a href="<?php echo admin_url('admin.php?page=dealer-crm'); ?>" class="crm-back-link">← Terug naar overzicht</a>

            <div class="crm-detail-header">
                <h1><?php echo esc_html($dealer->name); ?></h1>
                <span class="crm-status crm-status-<?php echo esc_attr($dealer->status); ?>"><?php echo esc_html(ucfirst($dealer->status)); ?></span>
                <a href="<?php echo admin_url('admin.php?page=dealer-crm&action=merge&id=' . $id); ?>" class="button crm-merge-btn">Samenvoegen</a>
            </div>

            <div class="crm-detail-grid">
                <div class="crm-detail-main">
                    <!-- Contact Info -->
                    <div class="crm-card" id="dealer-info-card">
                        <div class="crm-card-header">
                            <h2>Contactgegevens</h2>
                            <button class="button crm-edit-toggle" data-target="dealer-info">Bewerken</button>
                        </div>
                        <div class="crm-card-body" id="dealer-info-display">
                            <table class="crm-info-table">
                                <tr><th>Adres</th><td><?php echo esc_html(trim($dealer->street . ', ' . $dealer->postcode . ' ' . $dealer->city, ', ')); ?></td></tr>
                                <tr><th>Contactpersoon</th><td><?php echo esc_html(($dealer->contact_person ?? '') ?: '—'); ?></td></tr>
                                <tr><th>Eigenaar</th><td><?php echo esc_html(($dealer->owner ?? '') ?: '—'); ?></td></tr>
                                <tr><th>Telefoon</th><td><?php if ($dealer->phone): ?><a href="tel:<?php echo esc_attr($dealer->phone); ?>"><?php echo esc_html($dealer->phone); ?></a><?php endif; ?></td></tr>
                                <tr><th>E-mail</th><td><?php if ($dealer->email): ?><a href="mailto:<?php echo esc_attr($dealer->email); ?>"><?php echo esc_html($dealer->email); ?></a><?php endif; ?></td></tr>
                                <tr><th>Website</th><td><?php if ($dealer->website): ?><a href="<?php echo esc_url($dealer->website); ?>" target="_blank"><?php echo esc_html($dealer->website); ?></a><?php endif; ?></td></tr>
                                <tr><th>Webshop</th><td id="webshop-status-cell">
                                    <?php
                                    $ws_status = $dealer->webshop_status ?? null;
                                    $ws_platform = $dealer->webshop_platform ?? null;
                                    $ws_date = $dealer->webshop_detected_at ?? null;
                                    if ($ws_status === 'detected' && $ws_platform): ?>
                                        <span class="crm-webshop-badge crm-webshop-<?php echo esc_attr(DealerCRM_WebshopDetector::get_platform_class($ws_platform)); ?>"><?php echo esc_html($ws_platform); ?></span>
                                        <br><small style="color:#999;"><?php echo date('d-m-Y H:i', strtotime($ws_date)); ?></small>
                                        <a href="#" class="crm-scan-single-btn" data-dealer-id="<?php echo $id; ?>" style="margin-left:0.5rem;font-size:0.8rem;">Opnieuw scannen</a>
                                    <?php elseif ($ws_status === 'none'): ?>
                                        <span style="color:#999;">Geen webshop gedetecteerd</span>
                                        <br><small style="color:#999;"><?php echo date('d-m-Y H:i', strtotime($ws_date)); ?></small>
                                        <a href="#" class="crm-scan-single-btn" data-dealer-id="<?php echo $id; ?>" style="margin-left:0.5rem;font-size:0.8rem;">Opnieuw scannen</a>
                                    <?php elseif ($ws_status === 'error'): ?>
                                        <span style="color:#b32d2e;">Fout bij scannen</span>
                                        <a href="#" class="crm-scan-single-btn" data-dealer-id="<?php echo $id; ?>" style="margin-left:0.5rem;font-size:0.8rem;">Opnieuw scannen</a>
                                    <?php elseif ($dealer->website): ?>
                                        <span style="color:#999;">Niet gescand</span>
                                        <button class="button button-small crm-scan-single-btn" data-dealer-id="<?php echo $id; ?>">Scannen</button>
                                    <?php else: ?>
                                        <span class="crm-empty">Geen website</span>
                                    <?php endif; ?>
                                </td></tr>
                            </table>
                        </div>
                        <div class="crm-card-body crm-edit-form" id="dealer-info-edit" style="display:none">
                            <form id="dealer-edit-form" data-dealer-id="<?php echo $id; ?>">
                                <table class="crm-info-table">
                                    <tr><th>Naam</th><td><input type="text" name="name" value="<?php echo esc_attr($dealer->name); ?>"></td></tr>
                                    <tr><th>Contactpersoon</th><td><input type="text" name="contact_person" value="<?php echo esc_attr($dealer->contact_person ?? ''); ?>"></td></tr>
                                    <tr><th>Eigenaar</th><td><input type="text" name="owner" value="<?php echo esc_attr($dealer->owner ?? ''); ?>"></td></tr>
                                    <tr><th>Straat</th><td><input type="text" name="street" value="<?php echo esc_attr($dealer->street); ?>"></td></tr>
                                    <tr><th>Postcode</th><td><input type="text" name="postcode" value="<?php echo esc_attr($dealer->postcode); ?>"></td></tr>
                                    <tr><th>Plaats</th><td><input type="text" name="city" value="<?php echo esc_attr($dealer->city); ?>"></td></tr>
                                    <tr><th>Telefoon</th><td><input type="text" name="phone" value="<?php echo esc_attr($dealer->phone); ?>"></td></tr>
                                    <tr><th>E-mail</th><td><input type="email" name="email" value="<?php echo esc_attr($dealer->email); ?>"></td></tr>
                                    <tr><th>Website</th><td><input type="url" name="website" value="<?php echo esc_attr($dealer->website); ?>"></td></tr>
                                    <tr><th>Status</th><td>
                                        <select name="status">
                                            <?php foreach ($statuses as $s): ?>
                                                <option value="<?php echo esc_attr($s); ?>" <?php selected($dealer->status, $s); ?>><?php echo esc_html(ucfirst($s)); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td></tr>
                                </table>
                                <div class="crm-form-actions">
                                    <button type="submit" class="button button-primary">Opslaan</button>
                                    <button type="button" class="button crm-edit-cancel" data-target="dealer-info">Annuleren</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tabs: Notes & Contact Log -->
                    <div class="crm-card">
                        <div class="crm-tabs">
                            <button class="crm-tab active" data-tab="notes">Notities (<?php echo count($notes); ?>)</button>
                            <button class="crm-tab" data-tab="contacts">Contacthistorie (<?php echo count($contacts); ?>)</button>
                            <button class="crm-tab" data-tab="followups">Follow-ups (<?php echo count($followups); ?>)</button>
                            <button class="crm-tab" data-tab="email">E-mail<?php
                                // Auteur: Khayrallah Issa
                                // Rode badge met het aantal ongelezen inkomende mails.
                                if ($unread_in > 0) {
                                    echo ' <span class="crm-email-unread-badge">' . (int) $unread_in . '</span>';
                                }
                                if (DealerCRM_Mailchimp::is_configured()) {
                                    $mc_activity = DealerCRM_Mailchimp::get_dealer_activity($id);
                                    if (!empty($mc_activity)) echo ' <span style="background:#ffe01b;color:#241c15;padding:1px 5px;border-radius:3px;font-size:0.7rem;">MC:' . count($mc_activity) . '</span>';
                                }
                            ?></button>
                            <button class="crm-tab" data-tab="activity">Activiteit</button>
                        </div>

                        <div class="crm-tab-content active" id="tab-notes">
                            <form id="add-note-form" data-dealer-id="<?php echo $id; ?>" class="crm-add-form">
                                <textarea name="content" placeholder="Schrijf een notitie..." rows="3" required></textarea>
                                <button type="submit" class="button button-primary">Notitie toevoegen</button>
                            </form>
                            <div id="notes-list" class="crm-timeline">
                                <?php foreach ($notes as $note): ?>
                                    <div class="crm-timeline-item" data-id="<?php echo $note->id; ?>">
                                        <div class="crm-timeline-meta">
                                            <strong><?php echo esc_html($note->author ?? 'Onbekend'); ?></strong>
                                            <time><?php echo date('d-m-Y H:i', strtotime($note->created_at)); ?></time>
                                            <button class="crm-delete-btn" data-type="note" data-id="<?php echo $note->id; ?>" title="Verwijderen">×</button>
                                        </div>
                                        <div class="crm-timeline-body"><?php echo nl2br(esc_html($note->content)); ?></div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($notes)): ?>
                                    <p class="crm-empty">Nog geen notities.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="crm-tab-content" id="tab-contacts">
                            <form id="add-contact-form" data-dealer-id="<?php echo $id; ?>" class="crm-add-form">
                                <div class="crm-form-row">
                                    <select name="type" required>
                                        <option value="email">E-mail</option>
                                        <option value="telefoon">Telefoon</option>
                                        <option value="bezoek">Bezoek</option>
                                        <option value="overig">Overig</option>
                                    </select>
                                    <input type="text" name="subject" placeholder="Onderwerp" required>
                                    <input type="datetime-local" name="contact_date" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                                </div>
                                <textarea name="content" placeholder="Beschrijving..." rows="3"></textarea>
                                <button type="submit" class="button button-primary">Contact toevoegen</button>
                            </form>

                            <!-- ============================================================ -->
                            <!-- Auteur: Khayrallah Issa                -->
                            <!-- Inbox-view binnen Contacthistorie: alleen inkomende mails    -->
                            <!-- van deze dealer. Visuele weergave als een echte inbox        -->
                            <!-- (afzender, onderwerp, preview, tijd).                        -->
                            <!-- ============================================================ -->
                            <?php
                            $inkomende_mails = array_filter($dealer_emails ?? [], function ($e) {
                                return ($e->direction ?? '') === 'in';
                            });
                            ?>
                            <div class="crm-inbox" id="crm-inbox" data-dealer-id="<?php echo $id; ?>" style="margin-top:20px; border:1px solid #d6e0ec; border-radius:4px; background:#fff; overflow:hidden;">
                                <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 14px; background:#1F4E79; color:#fff;">
                                    <strong style="font-size:14px;">
                                        <span style="display:inline-block; width:18px; height:18px; line-height:18px; text-align:center; background:#fff; color:#1F4E79; border-radius:50%; font-size:11px; margin-right:6px; font-weight:bold;">@</span>
                                        Inbox (<span id="crm-inbox-count"><?php echo count($inkomende_mails); ?></span>)
                                    </strong>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <span style="font-size:12px; opacity:.85;">Inkomende mails van <?php echo esc_html($dealer->name ?? 'deze dealer'); ?></span>
                                        <button type="button" id="crm-inbox-refresh" style="background:#fff; color:#1F4E79; border:0; padding:4px 10px; border-radius:3px; font-size:12px; cursor:pointer; font-weight:600;">
                                            <span class="refresh-icon" style="display:inline-block;">&#8635;</span> Ophalen
                                        </button>
                                    </div>
                                </div>

                                <?php if (empty($inkomende_mails)): ?>
                                    <div style="padding:30px; text-align:center; color:#888; font-size:13px;">
                                        Geen inkomende mails van deze dealer.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($inkomende_mails as $m):
                                        $is_unread = empty($m->read_at);
                                        $afzender  = $m->from_address ?? ($dealer->email ?? 'onbekend');
                                        $initials  = strtoupper(substr($dealer->name ?? 'D', 0, 1));
                                        $preview   = trim(preg_replace('/\s+/', ' ', (string)($m->body ?? '')));
                                        if (mb_strlen($preview) > 110) $preview = mb_substr($preview, 0, 110) . '...';
                                    ?>
                                        <div class="crm-inbox-row <?php echo $is_unread ? 'is-unread' : ''; ?>" data-email-id="<?php echo (int)$m->id; ?>" style="border-bottom:1px solid #eef2f7; cursor:pointer; <?php echo $is_unread ? 'background:#fffbe6;' : 'background:#fff;'; ?>">
                                            <div class="crm-inbox-summary" style="display:flex; align-items:center; gap:12px; padding:10px 14px;">
                                                <div style="flex-shrink:0; width:36px; height:36px; border-radius:50%; background:#1F4E79; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:14px;">
                                                    <?php echo esc_html($initials); ?>
                                                </div>
                                                <div style="flex:1; min-width:0;">
                                                    <div style="display:flex; align-items:baseline; justify-content:space-between; gap:10px;">
                                                        <strong class="crm-inbox-sender" style="<?php echo $is_unread ? 'color:#1F4E79;' : 'color:#444; font-weight:600;'; ?> font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                                            <?php echo esc_html($dealer->name ?? $afzender); ?>
                                                            <span style="color:#888; font-weight:normal; font-size:11px;">&lt;<?php echo esc_html($afzender); ?>&gt;</span>
                                                        </strong>
                                                        <span style="color:#888; font-size:11px; white-space:nowrap;">
                                                            <?php echo date('d-m-Y H:i', strtotime($m->sent_at ?? 'now')); ?>
                                                        </span>
                                                    </div>
                                                    <div class="crm-inbox-subject" style="<?php echo $is_unread ? 'font-weight:600; color:#222;' : 'color:#555;'; ?> font-size:13px; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                                        <span class="crm-inbox-dot" style="<?php echo $is_unread ? '' : 'display:none;'; ?> display:inline-block; width:8px; height:8px; border-radius:50%; background:#1F4E79; margin-right:6px; vertical-align:middle;"></span>
                                                        <?php echo esc_html($m->subject ?? '(geen onderwerp)'); ?>
                                                        <span style="color:#888; font-weight:normal;"> &mdash; <?php echo esc_html($preview); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="crm-inbox-full" style="display:none; padding:12px 18px 16px 62px; background:#fafcfe; border-top:1px solid #eef2f7; font-size:13px; color:#333; white-space:pre-wrap; line-height:1.5;"><?php echo esc_html($m->body ?? ''); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <div style="padding:8px 14px; background:#f7fafd; border-top:1px solid #eef2f7; text-align:right; font-size:11px; color:#888;">
                                    Auto-ophalen via IMAP (cron) elke 5 min.
                                </div>
                            </div>

                            <!-- ============================================================ -->
                            <!-- Auteur: Khayrallah Issa                -->
                            <!-- JS voor de inbox: klik op een mail om uit te klappen en      -->
                            <!-- automatisch als gelezen te markeren. Refresh-knop triggert   -->
                            <!-- de IMAP-cron en haalt de pagina opnieuw op zodra klaar.      -->
                            <!-- ============================================================ -->
                            <script>
                            (function () {
                                var inbox = document.getElementById('crm-inbox');
                                if (!inbox) return;

                                // Klik op een rij = uit/inklappen + markeren als gelezen
                                inbox.querySelectorAll('.crm-inbox-row').forEach(function (row) {
                                    row.addEventListener('click', function () {
                                        var full = row.querySelector('.crm-inbox-full');
                                        if (!full) return;
                                        var open = full.style.display !== 'none';
                                        full.style.display = open ? 'none' : 'block';
                                        if (open) return;  // alleen markeren bij openen

                                        if (row.classList.contains('is-unread')) {
                                            var emailId = row.dataset.emailId;
                                            var fd = new FormData();
                                            fd.append('action', 'crm_mark_email_read');
                                            fd.append('email_id', emailId);
                                            fd.append('nonce', dealerCRM.nonce);
                                            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                                                .then(function (r) { return r.json(); })
                                                .then(function (r) {
                                                    if (!r.success) return;
                                                    // Visueel als gelezen markeren
                                                    row.classList.remove('is-unread');
                                                    row.style.background = '#fff';
                                                    var dot  = row.querySelector('.crm-inbox-dot');
                                                    if (dot) dot.style.display = 'none';
                                                    var subj = row.querySelector('.crm-inbox-subject');
                                                    if (subj) { subj.style.fontWeight = 'normal'; subj.style.color = '#555'; }
                                                    var send = row.querySelector('.crm-inbox-sender');
                                                    if (send) { send.style.color = '#444'; }
                                                    // Tab-badge ook bijwerken
                                                    var badge = document.querySelector('.crm-email-unread-badge');
                                                    if (badge) {
                                                        var n = parseInt(badge.textContent, 10) - 1;
                                                        if (n > 0) badge.textContent = n;
                                                        else badge.remove();
                                                    }
                                                });
                                        }
                                    });
                                });

                                // Refresh-knop: roept de IMAP-fetch aan en herlaadt daarna
                                var refreshBtn = document.getElementById('crm-inbox-refresh');
                                if (refreshBtn) {
                                    refreshBtn.addEventListener('click', function (ev) {
                                        ev.stopPropagation();
                                        var original = refreshBtn.innerHTML;
                                        refreshBtn.disabled = true;
                                        refreshBtn.innerHTML = 'Bezig...';
                                        var fd = new FormData();
                                        fd.append('action', 'crm_fetch_inbox');
                                        fd.append('dealer_id', inbox.dataset.dealerId);
                                        fd.append('nonce', dealerCRM.nonce);
                                        fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                                            .then(function (r) { return r.json(); })
                                            .then(function (r) {
                                                refreshBtn.disabled = false;
                                                refreshBtn.innerHTML = original;
                                                if (!r.success) {
                                                    alert('Fout: ' + (r.data || 'onbekend'));
                                                    return;
                                                }
                                                // Herlaad de pagina zodat nieuwe mails zichtbaar worden
                                                window.location.reload();
                                            })
                                            .catch(function () {
                                                refreshBtn.disabled = false;
                                                refreshBtn.innerHTML = original;
                                                alert('Fout bij ophalen.');
                                            });
                                    });
                                }
                            })();
                            </script>

                            <div id="contacts-list" class="crm-timeline">
                                <?php foreach ($contacts as $c): ?>
                                    <div class="crm-timeline-item" data-id="<?php echo $c->id; ?>">
                                        <div class="crm-timeline-meta">
                                            <span class="crm-contact-type crm-contact-type-<?php echo esc_attr($c->type); ?>"><?php echo esc_html(ucfirst($c->type)); ?></span>
                                            <strong><?php echo esc_html($c->author ?? 'Onbekend'); ?></strong>
                                            <time><?php echo date('d-m-Y H:i', strtotime($c->contact_date)); ?></time>
                                            <button class="crm-delete-btn" data-type="contact" data-id="<?php echo $c->id; ?>" title="Verwijderen">×</button>
                                        </div>
                                        <?php if ($c->subject): ?><div class="crm-timeline-subject"><?php echo esc_html($c->subject); ?></div><?php endif; ?>
                                        <?php if ($c->content): ?><div class="crm-timeline-body"><?php echo nl2br(esc_html($c->content)); ?></div><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($contacts)): ?>
                                    <p class="crm-empty">Nog geen contactmomenten.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="crm-tab-content" id="tab-followups">
                            <form id="add-followup-form" data-dealer-id="<?php echo $id; ?>" class="crm-add-form">
                                <div class="crm-form-row">
                                    <input type="text" name="title" placeholder="Titel" required>
                                    <input type="date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                                    <select name="user_id" required>
                                        <?php foreach ($wp_users as $u): ?>
                                            <option value="<?php echo $u->ID; ?>" <?php selected($u->ID, get_current_user_id()); ?>><?php echo esc_html($u->display_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <textarea name="description" placeholder="Beschrijving..." rows="2"></textarea>
                                <button type="submit" class="button button-primary">Follow-up toevoegen</button>
                            </form>
                            <div id="followups-list" class="crm-timeline">
                                <?php foreach ($followups as $f):
                                    $is_overdue = ($f->status === 'open' && $f->due_date < $today);
                                ?>
                                    <div class="crm-timeline-item crm-followup-item <?php echo $is_overdue ? 'crm-followup-overdue' : ''; ?>" data-id="<?php echo $f->id; ?>">
                                        <div class="crm-timeline-meta">
                                            <?php if ($is_overdue): ?>
                                                <span class="crm-followup-badge crm-followup-badge-verlopen">Verlopen</span>
                                            <?php else: ?>
                                                <span class="crm-followup-badge crm-followup-badge-<?php echo esc_attr($f->status); ?>"><?php echo esc_html(ucfirst($f->status)); ?></span>
                                            <?php endif; ?>
                                            <strong><?php echo esc_html($f->assignee_name ?? 'Onbekend'); ?></strong>
                                            <time><?php echo date('d-m-Y', strtotime($f->due_date)); ?></time>
                                            <?php if ($f->status === 'open'): ?>
                                                <button class="button button-small crm-complete-followup-btn" data-id="<?php echo $f->id; ?>">Voltooien</button>
                                            <?php endif; ?>
                                            <button class="crm-delete-btn crm-delete-followup-btn" data-id="<?php echo $f->id; ?>" title="Verwijderen">&times;</button>
                                        </div>
                                        <div class="crm-timeline-subject"><?php echo esc_html($f->title); ?></div>
                                        <?php if ($f->description): ?>
                                            <div class="crm-timeline-body"><?php echo nl2br(esc_html($f->description)); ?></div>
                                        <?php endif; ?>
                                        <div class="crm-followup-creator">Aangemaakt door <?php echo esc_html($f->creator_name ?? 'Onbekend'); ?></div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($followups)): ?>
                                    <p class="crm-empty">Nog geen follow-ups.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="crm-tab-content" id="tab-email">
                            <div class="crm-add-form crm-email-form">
                                <div class="crm-email-recipient">
                                    <label><strong>Aan:</strong></label>
                                    <span class="crm-email-to-display"><?php echo esc_html($dealer->email ?: 'Geen e-mailadres beschikbaar'); ?></span>
                                </div>
                                <form id="send-email-form" data-dealer-id="<?php echo $id; ?>">
                                    <div class="crm-form-row">
                                        <input type="text" name="subject" placeholder="Onderwerp" required style="flex:1;">
                                    </div>
                                    <textarea name="message" placeholder="Typ je bericht..." rows="8" required></textarea>
                                    <button type="submit" class="button button-primary" id="send-email-btn" <?php echo !$dealer->email ? 'disabled' : ''; ?>>Versturen</button>
                                </form>
                            </div>
                            <div id="sent-emails-list" class="crm-timeline">
                                <?php
                                /*
                                 * Auteur: Khayrallah Issa
                                 *
                                 * Toont de volledige e-mailgeschiedenis van de dealer uit de
                                 * tabel wp_crm_emails (US-07). Inkomende mails van de dealer
                                 * krijgen een oranje "Inkomend" label met "Van: <adres>";
                                 * uitgaande mails een blauw "Verzonden" label met "Aan: <adres>".
                                 * Ongelezen inkomende mails worden geel gemarkeerd.
                                 */
                                foreach ($dealer_emails as $e):
                                    $is_in  = ($e->direction === 'in');
                                    $unread = ($is_in && empty($e->read_at));
                                    $row_class = $is_in ? 'crm-email-in' : 'crm-email-out';
                                    if ($unread) {
                                        $row_class .= ' crm-email-unread';
                                    }
                                ?>
                                    <div class="crm-timeline-item crm-email-item <?php echo $row_class; ?>">
                                        <div class="crm-timeline-meta">
                                            <?php if ($is_in): ?>
                                                <span class="crm-email-dir crm-email-dir-in">Inkomend</span>
                                            <?php else: ?>
                                                <span class="crm-email-dir crm-email-dir-out">Verzonden</span>
                                            <?php endif; ?>
                                            <?php if ($unread): ?><span class="crm-email-new">Ongelezen</span><?php endif; ?>
                                            <strong><?php echo esc_html($is_in ? ($dealer->name ?: 'Dealer') : ($e->author ?? 'Onbekend')); ?></strong>
                                            <time><?php echo date('d-m-Y H:i', strtotime($e->sent_at)); ?></time>
                                        </div>
                                        <?php if ($e->subject): ?><div class="crm-timeline-subject"><?php echo esc_html($e->subject); ?></div><?php endif; ?>
                                        <div class="crm-sent-email-recipient">
                                            <?php if ($is_in): ?>
                                                Van: <?php echo esc_html($e->from_address); ?>
                                            <?php else: ?>
                                                Aan: <?php echo esc_html($e->to_address); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($e->body): ?><div class="crm-timeline-body"><?php echo nl2br(esc_html($e->body)); ?></div><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($dealer_emails)): ?>
                                    <p class="crm-empty">Nog geen e-mails voor deze dealer.</p>
                                <?php endif; ?>
                            </div>

                            <?php if (DealerCRM_Mailchimp::is_configured()): ?>
                                <div style="margin-top:1.5rem;border-top:1px solid #eee;padding-top:1rem;">
                                    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem;">
                                        <span class="dashicons dashicons-email-alt" style="color:#ffe01b;font-size:20px;"></span>
                                        <h4 style="margin:0;">Mailchimp campagnes</h4>
                                        <button class="button button-small crm-mailchimp-sync-btn" data-dealer-id="<?php echo $id; ?>" style="margin-left:auto;">
                                            Synchroniseren
                                        </button>
                                    </div>
                                    <div id="crm-mailchimp-dealer-activity">
                                        <?php
                                        if (!isset($mc_activity)) {
                                            $mc_activity = DealerCRM_Mailchimp::get_dealer_activity($id);
                                        }
                                        $last_sync = DealerCRM_Mailchimp::get_last_sync($id);
                                        if (!empty($mc_activity)): ?>
                                            <?php if ($last_sync): ?>
                                                <p style="font-size:0.8rem;color:#999;margin-bottom:0.5rem;">Laatst gesynchroniseerd: <?php echo date('d-m-Y H:i', strtotime($last_sync)); ?></p>
                                            <?php endif; ?>
                                            <table class="crm-table widefat striped" style="font-size:0.85rem;">
                                                <thead>
                                                    <tr>
                                                        <th>Campagne</th>
                                                        <th>Datum</th>
                                                        <th>Status</th>
                                                        <th>Opens</th>
                                                        <th>Clicks</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($mc_activity as $mc): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo esc_html($mc->campaign_subject ?: $mc->campaign_title); ?></strong>
                                                                <?php if ($mc->campaign_title && $mc->campaign_title !== $mc->campaign_subject): ?>
                                                                    <br><span style="color:#999;font-size:0.8rem;"><?php echo esc_html($mc->campaign_title); ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td style="white-space:nowrap;"><?php echo $mc->send_time ? date('d-m-Y', strtotime($mc->send_time)) : '-'; ?></td>
                                                            <td>
                                                                <?php
                                                                $status_labels = [
                                                                    'sent'         => ['Verstuurd', '#2271b1'],
                                                                    'bounced'      => ['Bounced', '#b32d2e'],
                                                                    'unsubscribed' => ['Uitgeschreven', '#dba617'],
                                                                ];
                                                                $sl = $status_labels[$mc->status] ?? ['Verstuurd', '#2271b1'];
                                                                ?>
                                                                <span style="background:<?php echo $sl[1]; ?>;color:#fff;padding:2px 8px;border-radius:3px;font-size:0.75rem;"><?php echo $sl[0]; ?></span>
                                                            </td>
                                                            <td>
                                                                <?php if ($mc->opens > 0): ?>
                                                                    <span style="color:#46b450;font-weight:600;"><?php echo $mc->opens; ?>x</span>
                                                                <?php else: ?>
                                                                    <span style="color:#999;">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($mc->clicks > 0): ?>
                                                                    <span style="color:#2271b1;font-weight:600;"><?php echo $mc->clicks; ?>x</span>
                                                                <?php else: ?>
                                                                    <span style="color:#999;">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <p class="crm-empty" id="crm-mailchimp-empty-msg">
                                                <?php echo $last_sync
                                                    ? 'Geen Mailchimp-campagnes gevonden voor dit e-mailadres.'
                                                    : 'Nog niet gesynchroniseerd. Klik op "Synchroniseren" om campagnehistorie op te halen.'; ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="crm-tab-content" id="tab-activity">
                            <?php
                            $activities = DealerCRM_ActivityLog::get_dealer_activity($id, 30);
                            if (empty($activities)): ?>
                                <p class="crm-empty">Nog geen activiteit geregistreerd.</p>
                            <?php else: ?>
                                <div class="crm-activity-log-list">
                                    <?php foreach ($activities as $act): ?>
                                        <div class="crm-timeline-item">
                                            <div class="crm-timeline-meta">
                                                <span class="crm-activity-action crm-activity-action-<?php echo esc_attr(str_replace('_', '-', $act->action)); ?>"><?php echo esc_html(DealerCRM_ActivityLog::get_action_label($act->action)); ?></span>
                                                <strong><?php echo esc_html($act->user_name ?? 'Systeem'); ?></strong>
                                                <time><?php echo date('d-m-Y H:i', strtotime($act->created_at)); ?></time>
                                            </div>
                                            <div class="crm-timeline-body"><?php echo esc_html($act->description); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="crm-detail-sidebar">
                    <div class="crm-card">
                        <h3>Merken</h3>
                        <div class="crm-brand-list">
                            <?php foreach ($brands as $b): ?>
                                <span class="crm-brand-chip"><?php echo esc_html($b); ?></span>
                            <?php endforeach; ?>
                            <?php if (empty($brands)): ?>
                                <p class="crm-empty">Geen merken.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="crm-card">
                        <h3>Tags</h3>
                        <div class="crm-tag-list" id="dealer-tags">
                            <?php foreach ($tags as $tag): ?>
                                <span class="crm-tag-chip crm-tag-removable" style="background:<?php echo esc_attr($tag->color); ?>" data-tag-id="<?php echo $tag->id; ?>" data-dealer-id="<?php echo $id; ?>">
                                    <?php echo esc_html($tag->name); ?> <span class="crm-tag-remove">×</span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <div class="crm-tag-add">
                            <select id="add-tag-select">
                                <option value="">Tag toevoegen...</option>
                                <?php
                                $current_tag_ids = array_map(fn($t) => $t->id, $tags);
                                foreach ($all_tags as $t):
                                    if (!in_array($t->id, $current_tag_ids)):
                                ?>
                                    <option value="<?php echo $t->id; ?>" data-color="<?php echo esc_attr($t->color); ?>"><?php echo esc_html($t->name); ?></option>
                                <?php endif; endforeach; ?>
                            </select>
                            <button class="button" id="add-tag-btn" data-dealer-id="<?php echo $id; ?>">+</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_merge_page($primary_id, $merge_with_id) {
        $dealer = DealerCRM_Database::get_dealer($primary_id);
        if (!$dealer) {
            echo '<div class="wrap"><h1>Dealer niet gevonden</h1><a href="' . admin_url('admin.php?page=dealer-crm') . '">← Terug</a></div>';
            return;
        }

        $base_url = admin_url('admin.php?page=dealer-crm');
        $detail_url = add_query_arg(['action' => 'view', 'id' => $primary_id], $base_url);
        ?>
        <div class="wrap dealer-crm-wrap">
            <a href="<?php echo esc_url($detail_url); ?>" class="crm-back-link">← Terug naar <?php echo esc_html($dealer->name); ?></a>
            <h1>Dealers samenvoegen</h1>

            <?php if (!$merge_with_id): ?>
                <!-- Step 1: Search for duplicate -->
                <div class="crm-card crm-merge-search-card">
                    <h2>Zoek de dubbele dealer</h2>
                    <p>Zoek de dealer die je wilt samenvoegen met <strong><?php echo esc_html($dealer->name); ?></strong>.</p>
                    <div class="crm-merge-search-wrap">
                        <input type="text" id="merge-search-input" placeholder="Zoek op naam, e-mail, telefoon of plaats..." class="crm-search-input" autocomplete="off">
                        <div id="merge-search-results" class="crm-merge-search-results" style="display:none"></div>
                    </div>
                    <input type="hidden" id="merge-primary-id" value="<?php echo $primary_id; ?>">
                </div>

            <?php else:
                $other = DealerCRM_Database::get_dealer($merge_with_id);
                if (!$other) {
                    echo '<div class="notice notice-error"><p>Dubbele dealer niet gevonden.</p></div>';
                    return;
                }

                $dealer_brands = DealerCRM_Database::get_dealer_brands($primary_id);
                $other_brands = DealerCRM_Database::get_dealer_brands($merge_with_id);
                $dealer_tags = DealerCRM_Database::get_dealer_tags($primary_id);
                $other_tags = DealerCRM_Database::get_dealer_tags($merge_with_id);
                $dealer_notes = DealerCRM_Database::get_notes($primary_id);
                $other_notes = DealerCRM_Database::get_notes($merge_with_id);
                $dealer_contacts = DealerCRM_Database::get_contact_log($primary_id);
                $other_contacts = DealerCRM_Database::get_contact_log($merge_with_id);

                $fields = [
                    'name'     => 'Naam',
                    'street'   => 'Straat',
                    'postcode' => 'Postcode',
                    'city'     => 'Plaats',
                    'phone'    => 'Telefoon',
                    'email'    => 'E-mail',
                    'website'  => 'Website',
                    'status'   => 'Status',
                ];
                ?>
                <!-- Step 2: Side-by-side comparison -->
                <div class="crm-merge-comparison">
                    <form id="merge-execute-form" data-primary-id="<?php echo $primary_id; ?>" data-merge-with-id="<?php echo $merge_with_id; ?>">
                        <p class="crm-merge-instruction">Kies per veld welke waarde je wilt behouden. Notities, contactmomenten, tags en merken worden automatisch samengevoegd.</p>

                        <table class="crm-merge-table widefat">
                            <thead>
                                <tr>
                                    <th class="crm-merge-field-col">Veld</th>
                                    <th class="crm-merge-dealer-col">
                                        <?php echo esc_html($dealer->name); ?>
                                        <span class="crm-merge-id">#<?php echo $primary_id; ?></span>
                                    </th>
                                    <th class="crm-merge-dealer-col">
                                        <?php echo esc_html($other->name); ?>
                                        <span class="crm-merge-id">#<?php echo $merge_with_id; ?></span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fields as $key => $label):
                                    $val_a = trim($dealer->$key ?? '');
                                    $val_b = trim($other->$key ?? '');
                                    $same = ($val_a === $val_b);
                                    // Default to the side that has data; if both have data, prefer primary
                                    $prefer_primary = ($val_a !== '' || $val_b === '');
                                ?>
                                    <tr class="<?php echo $same ? '' : 'crm-merge-diff'; ?>">
                                        <td class="crm-merge-label"><?php echo esc_html($label); ?></td>
                                        <td>
                                            <label class="crm-merge-option <?php echo $same ? 'crm-merge-auto' : ''; ?>">
                                                <input type="radio" name="field_<?php echo $key; ?>" value="primary" <?php checked($prefer_primary); ?>>
                                                <span class="crm-merge-value"><?php echo esc_html($val_a ?: '(leeg)'); ?></span>
                                            </label>
                                        </td>
                                        <td>
                                            <label class="crm-merge-option <?php echo $same ? 'crm-merge-auto' : ''; ?>">
                                                <input type="radio" name="field_<?php echo $key; ?>" value="secondary" <?php checked(!$prefer_primary); ?>>
                                                <span class="crm-merge-value"><?php echo esc_html($val_b ?: '(leeg)'); ?></span>
                                            </label>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="crm-merge-related">
                            <div class="crm-merge-related-section">
                                <h3>Merken (worden samengevoegd)</h3>
                                <div class="crm-merge-chips">
                                    <?php
                                    $all_brands = array_unique(array_merge($dealer_brands, $other_brands));
                                    sort($all_brands);
                                    foreach ($all_brands as $b): ?>
                                        <span class="crm-brand-chip"><?php echo esc_html($b); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (empty($all_brands)): ?>
                                        <span class="crm-empty">Geen merken</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="crm-merge-related-section">
                                <h3>Tags (worden samengevoegd)</h3>
                                <div class="crm-merge-chips">
                                    <?php
                                    $all_tag_ids = [];
                                    $all_tags_merged = [];
                                    foreach (array_merge($dealer_tags, $other_tags) as $t) {
                                        if (!in_array($t->id, $all_tag_ids)) {
                                            $all_tag_ids[] = $t->id;
                                            $all_tags_merged[] = $t;
                                        }
                                    }
                                    foreach ($all_tags_merged as $t): ?>
                                        <span class="crm-tag-chip" style="background:<?php echo esc_attr($t->color); ?>"><?php echo esc_html($t->name); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (empty($all_tags_merged)): ?>
                                        <span class="crm-empty">Geen tags</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="crm-merge-related-section">
                                <h3>Notities (<?php echo count($dealer_notes) + count($other_notes); ?> totaal)</h3>
                                <p class="description">Alle notities van beide dealers worden behouden.</p>
                            </div>
                            <div class="crm-merge-related-section">
                                <h3>Contacthistorie (<?php echo count($dealer_contacts) + count($other_contacts); ?> totaal)</h3>
                                <p class="description">Alle contactmomenten van beide dealers worden behouden.</p>
                            </div>
                        </div>

                        <div class="crm-merge-actions">
                            <button type="submit" class="button button-primary button-large">Samenvoegen uitvoeren</button>
                            <a href="<?php echo esc_url($detail_url); ?>" class="button button-large">Annuleren</a>
                            <span class="crm-merge-warning">Let op: dealer <strong><?php echo esc_html($other->name); ?></strong> (#<?php echo $merge_with_id; ?>) wordt verwijderd na het samenvoegen.</span>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function page_brands() {
        $action = sanitize_text_field($_GET['action'] ?? '');
        $id = (int) ($_GET['id'] ?? 0);

        if ($action === 'new') {
            self::render_new_brand();
        } elseif ($action === 'view' && $id) {
            self::render_brand_detail($id);
        } else {
            self::render_brand_list();
        }
    }

    private static function render_new_brand() {
        $all_parents = DealerCRM_Brands::get_all_parents();
        $base_url = admin_url('admin.php?page=dealer-crm-brands');
        ?>
        <div class="wrap dealer-crm-wrap">
            <a href="<?php echo esc_url($base_url); ?>" class="crm-back-link">&larr; Terug naar overzicht</a>
            <h1>Nieuw merk</h1>
            <div class="crm-card" style="max-width:600px;">
                <form id="crm-new-brand-form" method="post" onsubmit="return false;">
                    <table class="crm-info-table">
                        <tr><th>Naam *</th><td><input type="text" name="name" required style="width:100%;"></td></tr>
                        <tr>
                            <th>Groothandel</th>
                            <td>
                                <select name="parent_id">
                                    <option value="">Geen (zelfstandig)</option>
                                    <?php foreach ($all_parents as $p): ?>
                                        <option value="<?php echo $p->id; ?>"><?php echo esc_html($p->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr><th>Contactpersoon</th><td><input type="text" name="contact_person" style="width:100%;"></td></tr>
                        <tr><th>E-mail</th><td><input type="email" name="email" style="width:100%;"></td></tr>
                        <tr><th>Telefoon</th><td><input type="text" name="phone" style="width:100%;"></td></tr>
                    </table>
                    <div class="crm-form-actions" style="margin-top:1rem;">
                        <button type="submit" class="button button-primary">Aanmaken</button>
                        <a href="<?php echo esc_url($base_url); ?>" class="button">Annuleren</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    private static function render_brand_list() {
        $stats = DealerCRM_Brands::get_brand_stats();
        $filter_status = sanitize_text_field($_GET['tracker_status'] ?? 'Alle');
        $search = sanitize_text_field($_GET['s'] ?? '');

        $brands = DealerCRM_Brands::get_all_brands_with_details([
            'search'         => $search,
            'tracker_status' => $filter_status,
        ]);

        $base_url = admin_url('admin.php?page=dealer-crm-brands');
        ?>
        <div class="wrap dealer-crm-wrap">
            <h1 style="display:flex;align-items:center;gap:1rem;">
                Merken
                <a href="<?php echo esc_url(add_query_arg('action', 'new', $base_url)); ?>" class="button button-primary" style="font-size:13px;">+ Nieuw merk</a>
                <?php if (current_user_can('manage_options')): ?>
                    <input type="file" id="crm-import-brands-file" accept=".xlsx" style="display:none;">
                    <button class="button" id="crm-import-brands-btn">Importeren uit Excel</button>
                <?php endif; ?>
            </h1>

            <div id="crm-brand-import-status" style="display:none;margin:0.5rem 0;">
                <span id="crm-brand-import-msg"></span>
            </div>

            <div class="crm-dashboard-cards">
                <div class="crm-dashboard-card crm-dashboard-card-blue">
                    <span class="crm-dashboard-card-num"><?php echo $stats['total']; ?></span>
                    <span class="crm-dashboard-card-label">Totaal merken</span>
                </div>
                <div class="crm-dashboard-card crm-dashboard-card-green">
                    <span class="crm-dashboard-card-num"><?php echo $stats['compleet']; ?></span>
                    <span class="crm-dashboard-card-label">Compleet</span>
                </div>
                <div class="crm-dashboard-card" style="border-top-color:#dba617;">
                    <span class="crm-dashboard-card-num"><?php echo $stats['in_behandeling']; ?></span>
                    <span class="crm-dashboard-card-label">In behandeling</span>
                </div>
                <div class="crm-dashboard-card" style="border-top-color:#6c757d;">
                    <span class="crm-dashboard-card-num"><?php echo $stats['niet_gestart']; ?></span>
                    <span class="crm-dashboard-card-label">Niet gestart</span>
                </div>
            </div>

            <form method="get" class="crm-filters">
                <input type="hidden" name="page" value="dealer-crm-brands">
                <div class="crm-filter-row">
                    <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Zoek op merknaam..." class="crm-search-input">
                    <select name="tracker_status" class="crm-filter-select">
                        <option value="Alle" <?php selected($filter_status, 'Alle'); ?>>Alle statussen</option>
                        <option value="Compleet" <?php selected($filter_status, 'Compleet'); ?>>Compleet</option>
                        <option value="In behandeling" <?php selected($filter_status, 'In behandeling'); ?>>In behandeling</option>
                        <option value="Niet gestart" <?php selected($filter_status, 'Niet gestart'); ?>>Niet gestart</option>
                    </select>
                    <button type="submit" class="button button-primary">Zoeken</button>
                    <a href="<?php echo $base_url; ?>" class="button">Reset</a>
                </div>
            </form>

            <div class="crm-results-info">
                <?php echo count($brands); ?> merk<?php echo count($brands) !== 1 ? 'en' : ''; ?> gevonden
            </div>

            <table class="crm-table widefat striped">
                <thead>
                    <tr>
                        <th>Naam</th>
                        <th>Contactpersoon</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th>Feed</th>
                        <th>Prijzen</th>
                        <th>Afbeeldingen</th>
                        <th>Attributen</th>
                        <th>Dealers</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($brands)): ?>
                        <tr><td colspan="9" class="crm-no-results">Geen merken gevonden.</td></tr>
                    <?php endif; ?>
                    <?php
                    // Group brands: parents first, then children, then standalone
                    $parents = []; // id => brand object
                    $children = []; // parent_id => [brand objects]
                    $standalone = [];
                    $child_ids = [];

                    foreach ($brands as $b) {
                        if ($b->parent_id) {
                            $children[$b->parent_id][] = $b;
                            $child_ids[$b->id] = true;
                        }
                    }
                    foreach ($brands as $b) {
                        if (isset($children[$b->id])) {
                            $parents[$b->id] = $b;
                        } elseif (!isset($child_ids[$b->id])) {
                            $standalone[] = $b;
                        }
                    }

                    // Render parents with their children
                    foreach ($parents as $parent):
                        $detail_url = add_query_arg(['action' => 'view', 'id' => $parent->id], $base_url);
                        $status = $parent->tracker_status ?: 'Niet gestart';
                        $score = (int) ($parent->score ?? 0);
                        $child_count = count($children[$parent->id] ?? []);
                    ?>
                        <tr class="crm-brand-row crm-brand-parent-row" data-href="<?php echo esc_url($detail_url); ?>">
                            <td>
                                <a href="<?php echo esc_url($detail_url); ?>" class="crm-dealer-name"><?php echo esc_html($parent->name); ?></a>
                                <span class="crm-parent-badge">Groothandel (<?php echo $child_count; ?>)</span>
                            </td>
                            <td><?php echo esc_html($parent->contact_person ?? ''); ?></td>
                            <td><?php self::render_brand_status_badge($status); ?></td>
                            <td><?php self::render_score_dots($score); ?></td>
                            <td><?php self::render_tracker_badge($parent->feed_status ?? ''); ?></td>
                            <td><?php self::render_tracker_badge($parent->prices_status ?? ''); ?></td>
                            <td><?php self::render_tracker_badge($parent->images_status ?? ''); ?></td>
                            <td><?php self::render_tracker_badge($parent->attributes_status ?? ''); ?></td>
                            <td><?php echo (int) ($parent->dealer_count ?? 0); ?></td>
                        </tr>
                        <?php foreach ($children[$parent->id] as $child):
                            $detail_url = add_query_arg(['action' => 'view', 'id' => $child->id], $base_url);
                            $status = $child->tracker_status ?: 'Niet gestart';
                            $score = (int) ($child->score ?? 0);
                        ?>
                            <tr class="crm-brand-row crm-brand-child-row" data-href="<?php echo esc_url($detail_url); ?>">
                                <td><span class="crm-child-indent">└</span> <a href="<?php echo esc_url($detail_url); ?>" class="crm-dealer-name"><?php echo esc_html($child->name); ?></a></td>
                                <td><?php echo esc_html($child->contact_person ?? ''); ?></td>
                                <td><?php self::render_brand_status_badge($status); ?></td>
                                <td><?php self::render_score_dots($score); ?></td>
                                <td><?php self::render_tracker_badge($child->feed_status ?? ''); ?></td>
                                <td><?php self::render_tracker_badge($child->prices_status ?? ''); ?></td>
                                <td><?php self::render_tracker_badge($child->images_status ?? ''); ?></td>
                                <td><?php self::render_tracker_badge($child->attributes_status ?? ''); ?></td>
                                <td><?php echo (int) ($child->dealer_count ?? 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>

                    <?php // Render standalone brands
                    foreach ($standalone as $b):
                        $detail_url = add_query_arg(['action' => 'view', 'id' => $b->id], $base_url);
                        $status = $b->tracker_status ?: 'Niet gestart';
                        $score = (int) ($b->score ?? 0);
                    ?>
                        <tr class="crm-brand-row" data-href="<?php echo esc_url($detail_url); ?>">
                            <td><a href="<?php echo esc_url($detail_url); ?>" class="crm-dealer-name"><?php echo esc_html($b->name); ?></a></td>
                            <td><?php echo esc_html($b->contact_person ?? ''); ?></td>
                            <td><?php self::render_brand_status_badge($status); ?></td>
                            <td><?php self::render_score_dots($score); ?></td>
                            <td><?php self::render_tracker_badge($b->feed_status ?? ''); ?></td>
                            <td><?php self::render_tracker_badge($b->prices_status ?? ''); ?></td>
                            <td><?php self::render_tracker_badge($b->images_status ?? ''); ?></td>
                            <td><?php self::render_tracker_badge($b->attributes_status ?? ''); ?></td>
                            <td><?php echo (int) ($b->dealer_count ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function render_brand_detail($id) {
        $brand = DealerCRM_Brands::get_brand_with_details($id);
        if (!$brand) {
            echo '<div class="wrap"><h1>Merk niet gevonden</h1><a href="' . admin_url('admin.php?page=dealer-crm-brands') . '">← Terug</a></div>';
            return;
        }

        $notes = DealerCRM_Brands::get_brand_notes($id);
        $followups = DealerCRM_Brands::get_brand_followups($id);
        $dealers = DealerCRM_Brands::get_brand_dealers($id);
        $child_brands = DealerCRM_Brands::get_child_brands($id);
        $all_parents = DealerCRM_Brands::get_all_parents();
        $wp_users = get_users(['fields' => ['ID', 'display_name']]);
        $today = date('Y-m-d');
        $score = (int) ($brand->score ?? 0);
        $status = $brand->tracker_status ?: 'Niet gestart';
        $is_parent = !empty($child_brands);
        $has_parent = !empty($brand->parent_id);
        ?>
        <div class="wrap dealer-crm-wrap">
            <a href="<?php echo admin_url('admin.php?page=dealer-crm-brands'); ?>" class="crm-back-link">← Terug naar overzicht</a>

            <div class="crm-detail-header">
                <h1><?php echo esc_html($brand->name); ?></h1>
                <?php self::render_brand_status_badge($status); ?>
                <?php if ($is_parent): ?>
                    <span class="crm-parent-badge">Groothandel</span>
                <?php endif; ?>
                <?php if ($has_parent): ?>
                    <span class="crm-child-badge">
                        Onderdeel van <a href="<?php echo esc_url(add_query_arg(['action' => 'view', 'id' => $brand->parent_id], admin_url('admin.php?page=dealer-crm-brands'))); ?>"><?php echo esc_html($brand->parent_name); ?></a>
                    </span>
                <?php endif; ?>
            </div>

            <div class="crm-detail-grid">
                <div class="crm-detail-main">
                    <!-- Tabs -->
                    <div class="crm-card">
                        <div class="crm-tabs">
                            <button class="crm-tab active" data-tab="brand-tracker">Tracker</button>
                            <button class="crm-tab" data-tab="brand-notes">Notities (<?php echo count($notes); ?>)</button>
                            <button class="crm-tab" data-tab="brand-followups">Follow-ups (<?php echo count($followups); ?>)</button>
                            <button class="crm-tab" data-tab="brand-dealers">Dealers (<?php echo count($dealers); ?>)</button>
                        </div>

                        <!-- Tracker Tab -->
                        <div class="crm-tab-content active" id="tab-brand-tracker">
                            <form id="brand-tracker-form" data-brand-id="<?php echo $id; ?>">
                                <!-- Feed Section -->
                                <div class="crm-tracker-section">
                                    <h3>Feed</h3>
                                    <div class="crm-tracker-fields">
                                        <div class="crm-tracker-field">
                                            <label>Feed aanwezig?</label>
                                            <select name="feed_status">
                                                <option value="">-</option>
                                                <option value="Ja" <?php selected($brand->feed_status ?? '', 'Ja'); ?>>Ja</option>
                                                <option value="Nee" <?php selected($brand->feed_status ?? '', 'Nee'); ?>>Nee</option>
                                                <option value="Deels" <?php selected($brand->feed_status ?? '', 'Deels'); ?>>Deels</option>
                                            </select>
                                        </div>
                                        <div class="crm-tracker-field">
                                            <label>Producten in feed</label>
                                            <input type="number" name="product_count_feed" value="<?php echo (int) ($brand->product_count_feed ?? 0); ?>">
                                        </div>
                                        <div class="crm-tracker-field">
                                            <label>Producten op website</label>
                                            <input type="number" name="product_count_website" value="<?php echo (int) ($brand->product_count_website ?? 0); ?>">
                                        </div>
                                        <div class="crm-tracker-field">
                                            <label>Aantallen overeenkomen?</label>
                                            <select name="counts_match">
                                                <option value="">-</option>
                                                <option value="Ja" <?php selected($brand->counts_match ?? '', 'Ja'); ?>>Ja</option>
                                                <option value="Nee" <?php selected($brand->counts_match ?? '', 'Nee'); ?>>Nee</option>
                                            </select>
                                        </div>
                                        <div class="crm-tracker-field crm-tracker-field-wide">
                                            <label>Verschil / opmerking</label>
                                            <textarea name="counts_remark" rows="2"><?php echo esc_textarea($brand->counts_remark ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Prijzen Section -->
                                <div class="crm-tracker-section">
                                    <h3>Prijzen</h3>
                                    <div class="crm-tracker-fields">
                                        <div class="crm-tracker-field">
                                            <label>Prijzen aanwezig?</label>
                                            <select name="prices_status">
                                                <option value="">-</option>
                                                <option value="Ja" <?php selected($brand->prices_status ?? '', 'Ja'); ?>>Ja</option>
                                                <option value="Nee" <?php selected($brand->prices_status ?? '', 'Nee'); ?>>Nee</option>
                                                <option value="Deels" <?php selected($brand->prices_status ?? '', 'Deels'); ?>>Deels</option>
                                            </select>
                                        </div>
                                        <div class="crm-tracker-field">
                                            <label>Prijstype</label>
                                            <select name="price_type">
                                                <option value="">-</option>
                                                <option value="verkoop" <?php selected($brand->price_type ?? '', 'verkoop'); ?>>Verkoop</option>
                                                <option value="inkoop" <?php selected($brand->price_type ?? '', 'inkoop'); ?>>Inkoop</option>
                                                <option value="geen" <?php selected($brand->price_type ?? '', 'geen'); ?>>Geen</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Afbeeldingen Section -->
                                <div class="crm-tracker-section">
                                    <h3>Afbeeldingen</h3>
                                    <div class="crm-tracker-fields">
                                        <div class="crm-tracker-field">
                                            <label>Afbeeldingen aanwezig?</label>
                                            <select name="images_status">
                                                <option value="">-</option>
                                                <option value="Ja" <?php selected($brand->images_status ?? '', 'Ja'); ?>>Ja</option>
                                                <option value="Nee" <?php selected($brand->images_status ?? '', 'Nee'); ?>>Nee</option>
                                                <option value="Deels" <?php selected($brand->images_status ?? '', 'Deels'); ?>>Deels</option>
                                            </select>
                                        </div>
                                        <div class="crm-tracker-field">
                                            <label>Aantal per product (gem.)</label>
                                            <input type="text" name="images_per_product" value="<?php echo esc_attr($brand->images_per_product ?? ''); ?>">
                                        </div>
                                        <div class="crm-tracker-field">
                                            <label>Top Shot aanwezig?</label>
                                            <select name="topshot_status">
                                                <option value="">-</option>
                                                <option value="Ja" <?php selected($brand->topshot_status ?? '', 'Ja'); ?>>Ja</option>
                                                <option value="Nee" <?php selected($brand->topshot_status ?? '', 'Nee'); ?>>Nee</option>
                                            </select>
                                        </div>
                                        <div class="crm-tracker-field">
                                            <label>Sfeerbeeld aanwezig?</label>
                                            <select name="scene_image_status">
                                                <option value="">-</option>
                                                <option value="Ja" <?php selected($brand->scene_image_status ?? '', 'Ja'); ?>>Ja</option>
                                                <option value="Nee" <?php selected($brand->scene_image_status ?? '', 'Nee'); ?>>Nee</option>
                                            </select>
                                        </div>
                                        <div class="crm-tracker-field">
                                            <label>Kwaliteit (px)</label>
                                            <input type="text" name="image_quality" value="<?php echo esc_attr($brand->image_quality ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Attributen Section -->
                                <div class="crm-tracker-section">
                                    <h3>Attributen</h3>
                                    <div class="crm-tracker-fields">
                                        <div class="crm-tracker-field">
                                            <label>Attributen aanwezig?</label>
                                            <select name="attributes_status">
                                                <option value="">-</option>
                                                <option value="Ja" <?php selected($brand->attributes_status ?? '', 'Ja'); ?>>Ja</option>
                                                <option value="Nee" <?php selected($brand->attributes_status ?? '', 'Nee'); ?>>Nee</option>
                                                <option value="Deels" <?php selected($brand->attributes_status ?? '', 'Deels'); ?>>Deels</option>
                                            </select>
                                        </div>
                                        <div class="crm-tracker-field">
                                            <label>Vloerverwarming?</label>
                                            <select name="floor_heating">
                                                <option value="">-</option>
                                                <option value="Ja" <?php selected($brand->floor_heating ?? '', 'Ja'); ?>>Ja</option>
                                                <option value="Nee" <?php selected($brand->floor_heating ?? '', 'Nee'); ?>>Nee</option>
                                            </select>
                                        </div>
                                        <div class="crm-tracker-field">
                                            <label>m² per pak?</label>
                                            <select name="sqm_per_pack">
                                                <option value="">-</option>
                                                <option value="Ja" <?php selected($brand->sqm_per_pack ?? '', 'Ja'); ?>>Ja</option>
                                                <option value="Nee" <?php selected($brand->sqm_per_pack ?? '', 'Nee'); ?>>Nee</option>
                                            </select>
                                        </div>
                                        <div class="crm-tracker-field">
                                            <label>Kleur?</label>
                                            <select name="color_status">
                                                <option value="">-</option>
                                                <option value="Ja" <?php selected($brand->color_status ?? '', 'Ja'); ?>>Ja</option>
                                                <option value="Nee" <?php selected($brand->color_status ?? '', 'Nee'); ?>>Nee</option>
                                            </select>
                                        </div>
                                        <div class="crm-tracker-field">
                                            <label>Materiaal?</label>
                                            <select name="material_status">
                                                <option value="">-</option>
                                                <option value="Ja" <?php selected($brand->material_status ?? '', 'Ja'); ?>>Ja</option>
                                                <option value="Nee" <?php selected($brand->material_status ?? '', 'Nee'); ?>>Nee</option>
                                            </select>
                                        </div>
                                        <div class="crm-tracker-field">
                                            <label>Afmeting / formaat?</label>
                                            <select name="size_format_status">
                                                <option value="">-</option>
                                                <option value="Ja" <?php selected($brand->size_format_status ?? '', 'Ja'); ?>>Ja</option>
                                                <option value="Nee" <?php selected($brand->size_format_status ?? '', 'Nee'); ?>>Nee</option>
                                            </select>
                                        </div>
                                        <div class="crm-tracker-field">
                                            <label>Collectie naam?</label>
                                            <select name="collection_name_status">
                                                <option value="">-</option>
                                                <option value="Ja" <?php selected($brand->collection_name_status ?? '', 'Ja'); ?>>Ja</option>
                                                <option value="Nee" <?php selected($brand->collection_name_status ?? '', 'Nee'); ?>>Nee</option>
                                            </select>
                                        </div>
                                        <div class="crm-tracker-field crm-tracker-field-wide">
                                            <label>Overige attributen</label>
                                            <textarea name="other_attributes_note" rows="2"><?php echo esc_textarea($brand->other_attributes_note ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Status Section -->
                                <div class="crm-tracker-section">
                                    <h3>Status</h3>
                                    <div class="crm-tracker-fields">
                                        <div class="crm-tracker-field">
                                            <label>Tracker status</label>
                                            <select name="tracker_status">
                                                <option value="Niet gestart" <?php selected($status, 'Niet gestart'); ?>>Niet gestart</option>
                                                <option value="In behandeling" <?php selected($status, 'In behandeling'); ?>>In behandeling</option>
                                                <option value="Compleet" <?php selected($status, 'Compleet'); ?>>Compleet</option>
                                            </select>
                                        </div>
                                        <div class="crm-tracker-field crm-tracker-field-wide">
                                            <label>Vervolgactie / opmerkingen</label>
                                            <textarea name="followup_remarks" rows="2"><?php echo esc_textarea($brand->followup_remarks ?? ''); ?></textarea>
                                        </div>
                                        <div class="crm-tracker-field">
                                            <label>Terugkoppeling naar leverancier?</label>
                                            <select name="feedback_sent">
                                                <option value="Nee" <?php selected($brand->feedback_sent ?? 'Nee', 'Nee'); ?>>Nee</option>
                                                <option value="Ja" <?php selected($brand->feedback_sent ?? '', 'Ja'); ?>>Ja</option>
                                                <option value="Gepland" <?php selected($brand->feedback_sent ?? '', 'Gepland'); ?>>Gepland</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="crm-form-actions">
                                    <button type="submit" class="button button-primary">Opslaan</button>
                                </div>
                            </form>
                        </div>

                        <!-- Notes Tab -->
                        <div class="crm-tab-content" id="tab-brand-notes">
                            <form id="add-brand-note-form" data-brand-id="<?php echo $id; ?>" class="crm-add-form">
                                <textarea name="content" placeholder="Schrijf een notitie..." rows="3" required></textarea>
                                <button type="submit" class="button button-primary">Notitie toevoegen</button>
                            </form>
                            <div id="brand-notes-list" class="crm-timeline">
                                <?php foreach ($notes as $note): ?>
                                    <div class="crm-timeline-item" data-id="<?php echo $note->id; ?>">
                                        <div class="crm-timeline-meta">
                                            <strong><?php echo esc_html($note->author ?? 'Onbekend'); ?></strong>
                                            <time><?php echo date('d-m-Y H:i', strtotime($note->created_at)); ?></time>
                                            <button class="crm-delete-btn crm-delete-brand-note-btn" data-id="<?php echo $note->id; ?>" title="Verwijderen">&times;</button>
                                        </div>
                                        <div class="crm-timeline-body"><?php echo nl2br(esc_html($note->content)); ?></div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($notes)): ?>
                                    <p class="crm-empty">Nog geen notities.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Follow-ups Tab -->
                        <div class="crm-tab-content" id="tab-brand-followups">
                            <form id="add-brand-followup-form" data-brand-id="<?php echo $id; ?>" class="crm-add-form">
                                <div class="crm-form-row">
                                    <input type="text" name="title" placeholder="Titel" required>
                                    <input type="date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                                    <select name="user_id" required>
                                        <?php foreach ($wp_users as $u): ?>
                                            <option value="<?php echo $u->ID; ?>" <?php selected($u->ID, get_current_user_id()); ?>><?php echo esc_html($u->display_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <textarea name="description" placeholder="Beschrijving..." rows="2"></textarea>
                                <button type="submit" class="button button-primary">Follow-up toevoegen</button>
                            </form>
                            <div id="brand-followups-list" class="crm-timeline">
                                <?php foreach ($followups as $f):
                                    $is_overdue = ($f->status === 'open' && $f->due_date < $today);
                                ?>
                                    <div class="crm-timeline-item crm-followup-item <?php echo $is_overdue ? 'crm-followup-overdue' : ''; ?>" data-id="<?php echo $f->id; ?>">
                                        <div class="crm-timeline-meta">
                                            <?php if ($is_overdue): ?>
                                                <span class="crm-followup-badge crm-followup-badge-verlopen">Verlopen</span>
                                            <?php else: ?>
                                                <span class="crm-followup-badge crm-followup-badge-<?php echo esc_attr($f->status); ?>"><?php echo esc_html(ucfirst($f->status)); ?></span>
                                            <?php endif; ?>
                                            <strong><?php echo esc_html($f->assignee_name ?? 'Onbekend'); ?></strong>
                                            <time><?php echo date('d-m-Y', strtotime($f->due_date)); ?></time>
                                            <?php if ($f->status === 'open'): ?>
                                                <button class="button button-small crm-complete-brand-followup-btn" data-id="<?php echo $f->id; ?>">Voltooien</button>
                                            <?php endif; ?>
                                            <button class="crm-delete-btn crm-delete-brand-followup-btn" data-id="<?php echo $f->id; ?>" title="Verwijderen">&times;</button>
                                        </div>
                                        <div class="crm-timeline-subject"><?php echo esc_html($f->title); ?></div>
                                        <?php if ($f->description): ?>
                                            <div class="crm-timeline-body"><?php echo nl2br(esc_html($f->description)); ?></div>
                                        <?php endif; ?>
                                        <div class="crm-followup-creator">Aangemaakt door <?php echo esc_html($f->creator_name ?? 'Onbekend'); ?></div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($followups)): ?>
                                    <p class="crm-empty">Nog geen follow-ups.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Dealers Tab -->
                        <div class="crm-tab-content" id="tab-brand-dealers">
                            <?php if (empty($dealers)): ?>
                                <p class="crm-empty">Geen dealers voeren dit merk.</p>
                            <?php else: ?>
                                <table class="crm-table widefat striped">
                                    <thead>
                                        <tr>
                                            <th>Naam</th>
                                            <th>Plaats</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dealers as $dl):
                                            $dealer_url = admin_url('admin.php?page=dealer-crm&action=view&id=' . $dl->id);
                                        ?>
                                            <tr class="crm-dealer-row" data-href="<?php echo esc_url($dealer_url); ?>">
                                                <td><a href="<?php echo esc_url($dealer_url); ?>" class="crm-dealer-name"><?php echo esc_html($dl->name); ?></a></td>
                                                <td><?php echo esc_html($dl->city); ?></td>
                                                <td><span class="crm-status crm-status-<?php echo esc_attr($dl->status); ?>"><?php echo esc_html(ucfirst($dl->status)); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="crm-detail-sidebar">
                    <div class="crm-card" id="brand-info-card">
                        <div class="crm-card-header">
                            <h3>Merkgegevens</h3>
                            <button class="button crm-edit-toggle" data-target="brand-info">Bewerken</button>
                        </div>
                        <div class="crm-card-body" id="brand-info-display">
                            <table class="crm-info-table">
                                <tr><th>Naam</th><td><?php echo esc_html($brand->name); ?></td></tr>
                                <tr>
                                    <th>Groothandel</th>
                                    <td>
                                        <?php if ($has_parent): ?>
                                            <a href="<?php echo esc_url(add_query_arg(['action' => 'view', 'id' => $brand->parent_id], admin_url('admin.php?page=dealer-crm-brands'))); ?>"><?php echo esc_html($brand->parent_name); ?></a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr><th>Contact</th><td><?php echo esc_html($brand->contact_person ?? ''); ?></td></tr>
                                <tr><th>E-mail</th><td><?php if ($brand->email ?? ''): ?><a href="mailto:<?php echo esc_attr($brand->email); ?>"><?php echo esc_html($brand->email); ?></a><?php endif; ?></td></tr>
                                <tr><th>Telefoon</th><td><?php if ($brand->phone ?? ''): ?><a href="tel:<?php echo esc_attr($brand->phone); ?>"><?php echo esc_html($brand->phone); ?></a><?php endif; ?></td></tr>
                                <tr><th>Laatste check</th><td><?php echo $brand->last_check_date ? date('d-m-Y', strtotime($brand->last_check_date)) : '-'; ?></td></tr>
                            </table>
                        </div>
                        <div class="crm-card-body crm-edit-form" id="brand-info-edit" style="display:none">
                            <form id="brand-info-form" data-brand-id="<?php echo $id; ?>">
                                <table class="crm-info-table">
                                    <tr><th>Naam</th><td><input type="text" name="name" value="<?php echo esc_attr($brand->name); ?>"></td></tr>
                                    <tr>
                                        <th>Groothandel</th>
                                        <td>
                                            <select name="parent_id">
                                                <option value="">Geen (zelfstandig)</option>
                                                <?php foreach ($all_parents as $p):
                                                    if ($p->id == $id) continue; // Can't be own parent
                                                ?>
                                                    <option value="<?php echo $p->id; ?>" <?php selected($brand->parent_id, $p->id); ?>><?php echo esc_html($p->name); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr><th>Contact</th><td><input type="text" name="contact_person" value="<?php echo esc_attr($brand->contact_person ?? ''); ?>"></td></tr>
                                    <tr><th>E-mail</th><td><input type="email" name="email" value="<?php echo esc_attr($brand->email ?? ''); ?>"></td></tr>
                                    <tr><th>Telefoon</th><td><input type="text" name="phone" value="<?php echo esc_attr($brand->phone ?? ''); ?>"></td></tr>
                                    <tr><th>Laatste check</th><td><input type="date" name="last_check_date" value="<?php echo esc_attr($brand->last_check_date ?? ''); ?>"></td></tr>
                                </table>
                                <div class="crm-form-actions">
                                    <button type="submit" class="button button-primary">Opslaan</button>
                                    <button type="button" class="button crm-edit-cancel" data-target="brand-info">Annuleren</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if ($is_parent): ?>
                    <div class="crm-card">
                        <h3>Merken (<?php echo count($child_brands); ?>)</h3>
                        <ul class="crm-child-brands-list">
                            <?php foreach ($child_brands as $cb):
                                $cb_url = add_query_arg(['action' => 'view', 'id' => $cb->id], admin_url('admin.php?page=dealer-crm-brands'));
                                $cb_status = $cb->tracker_status ?: 'Niet gestart';
                            ?>
                                <li>
                                    <a href="<?php echo esc_url($cb_url); ?>"><?php echo esc_html($cb->name); ?></a>
                                    <?php self::render_brand_status_badge($cb_status); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <div class="crm-card">
                        <h3>Score</h3>
                        <div style="text-align:center;padding:0.5rem 0;">
                            <span style="font-size:2.5rem;font-weight:700;color:#1d2327;"><?php echo $score; ?></span>
                            <span style="font-size:1.2rem;color:#666;">/7</span>
                        </div>
                        <div style="text-align:center;">
                            <?php self::render_score_dots($score); ?>
                        </div>
                    </div>

                    <div class="crm-card">
                        <h3>Status</h3>
                        <div style="text-align:center;padding:0.5rem 0;">
                            <?php self::render_brand_status_badge($status); ?>
                        </div>
                    </div>

                    <div class="crm-card">
                        <h3>Terugkoppeling leverancier</h3>
                        <div style="text-align:center;padding:0.5rem 0;">
                            <?php self::render_tracker_badge($brand->feedback_sent ?? 'Nee'); ?>
                        </div>
                    </div>

                    <div class="crm-card">
                        <h3>Acties</h3>
                        <div style="display:flex;flex-direction:column;gap:0.5rem;padding:0.5rem 0;">
                            <button type="button" class="button" id="crm-merge-brand-btn" data-brand-id="<?php echo $id; ?>" data-brand-name="<?php echo esc_attr($brand->name); ?>">
                                <span class="dashicons dashicons-merge" style="vertical-align:middle;margin-right:4px;"></span> Samenvoegen met ander merk
                            </button>
                            <button type="button" class="button" id="crm-delete-brand-btn" data-brand-id="<?php echo $id; ?>" data-brand-name="<?php echo esc_attr($brand->name); ?>" style="color:#b32d2e;">
                                <span class="dashicons dashicons-trash" style="vertical-align:middle;margin-right:4px;"></span> Merk verwijderen
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Merge Brand Modal -->
            <div id="crm-merge-brand-modal" style="display:none;">
                <div class="crm-modal-overlay"></div>
                <div class="crm-modal-content">
                    <div class="crm-modal-header">
                        <h2>Merk samenvoegen</h2>
                        <button class="crm-modal-close">&times;</button>
                    </div>
                    <div class="crm-modal-body">
                        <p>Zoek het merk dat je wilt samenvoegen met <strong><?php echo esc_html($brand->name); ?></strong>. Alle gegevens (dealers, notities, follow-ups) worden overgenomen. Het geselecteerde merk wordt daarna verwijderd.</p>
                        <div class="crm-merge-search-wrap">
                            <input type="text" id="crm-merge-brand-search" placeholder="Zoek merk op naam..." autocomplete="off" style="width:100%;">
                            <div id="crm-merge-brand-results" class="crm-merge-results"></div>
                        </div>
                        <div id="crm-merge-brand-selected" style="display:none;margin-top:1rem;">
                            <p>Samenvoegen met: <strong id="crm-merge-brand-selected-name"></strong></p>
                            <input type="hidden" id="crm-merge-brand-selected-id">
                        </div>
                    </div>
                    <div class="crm-modal-footer">
                        <button type="button" class="button button-primary" id="crm-merge-brand-confirm" disabled>Samenvoegen</button>
                        <button type="button" class="button crm-modal-close">Annuleren</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_brand_status_badge($status) {
        $classes = [
            'Compleet'       => 'crm-brand-status-compleet',
            'In behandeling' => 'crm-brand-status-behandeling',
            'Niet gestart'   => 'crm-brand-status-niet-gestart',
        ];
        $class = $classes[$status] ?? 'crm-brand-status-niet-gestart';
        echo '<span class="crm-brand-status-badge ' . esc_attr($class) . '">' . esc_html($status) . '</span>';
    }

    private static function render_tracker_badge($value) {
        $val = strtolower(trim($value));
        if ($val === 'ja') {
            echo '<span class="crm-tracker-badge crm-tracker-badge-ja">Ja</span>';
        } elseif ($val === 'nee') {
            echo '<span class="crm-tracker-badge crm-tracker-badge-nee">Nee</span>';
        } elseif ($val === 'deels') {
            echo '<span class="crm-tracker-badge crm-tracker-badge-deels">Deels</span>';
        } elseif ($val === 'gepland') {
            echo '<span class="crm-tracker-badge crm-tracker-badge-deels">Gepland</span>';
        } else {
            echo '<span style="color:#999;">-</span>';
        }
    }

    private static function render_score_dots($score, $max = 7) {
        echo '<span class="crm-score-dots">';
        for ($i = 0; $i < $max; $i++) {
            echo '<span class="crm-score-dot' . ($i < $score ? ' crm-score-dot-filled' : '') . '"></span>';
        }
        echo '</span>';
    }

    /**
     * Auteur: Khayrallah Issa
     *
     * De Kaart-pagina in WP-admin is vervangen door de Routeplanner
     * (US-01 t/m US-04). Wie op "Kaart" klikt ziet direct de routeplanner
     * waarop dealers geselecteerd en hun route berekend kan worden via OSRM.
     *
     * De oude losse Leaflet-kaart met filters/geocoding is uit deze pagina
     * gehaald; al die functionaliteit (cluster-icoontjes, filter op merk en
     * status, postcode-radius, opslaan-knoppen) zit nu in de routeplanner.
     */
    public static function page_map() {
        ?>
        <div class="wrap dealer-crm-wrap" style="margin:0;padding:0;">
            <iframe src="<?php echo esc_url(home_url('/crm-extensions/public/demo_route_planner.php?embed=1')); ?>"
                    title="Routeplanner"
                    style="width:100%;height:calc(100vh - 90px);border:0;display:block;"></iframe>
        </div>
        <?php
    }

    /**
     * Hulpfunctie - de oude losse-kaart pagina (niet meer gebruikt). Bewaard
     * voor het geval het ooit terug moet, maar de menu-item "Kaart" wijst nu
     * naar page_map() hierboven die de routeplanner laadt.
     * Auteur: Khayrallah Issa
     */
    public static function page_map_old_oude_versie() {
        $dealers = DealerCRM_Database::get_dealers_with_coords();
        $brands = DealerCRM_Database::get_all_brands();
        $statuses = DealerCRM_Database::get_statuses();
        $remaining = DealerCRM_Database::count_dealers_without_coords();

        // Build dealer data with brands for JSON output
        $dealer_data = [];
        foreach ($dealers as $d) {
            $d_brands = DealerCRM_Database::get_dealer_brands($d->id);
            $dealer_data[] = [
                'id'     => (int) $d->id,
                'name'   => $d->name,
                'city'   => $d->city,
                'phone'  => $d->phone,
                'email'  => $d->email,
                'status' => $d->status,
                'lat'    => (float) $d->lat,
                'lng'    => (float) $d->lng,
                'brands' => $d_brands,
            ];
        }

        $detail_base = admin_url('admin.php?page=dealer-crm&action=view&id=');
        $is_admin = current_user_can('manage_options');
        ?>
        <div class="wrap dealer-crm-wrap">
            <h1>Dealerkaart</h1>

            <!-- Leaflet CSS -->
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
            <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
            <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />

            <div class="crm-map-filters" style="display:flex;gap:0.5rem;margin:1rem 0;align-items:center;flex-wrap:wrap;">
                <select id="crm-map-filter-brand" class="crm-filter-select">
                    <option value="">Alle merken</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?php echo esc_attr($b); ?>"><?php echo esc_html($b); ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="crm-map-filter-status" class="crm-filter-select">
                    <option value="">Alle statussen</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?php echo esc_attr($s); ?>"><?php echo esc_html(ucfirst($s)); ?></option>
                    <?php endforeach; ?>
                </select>

                <span style="color:#ccc;margin:0 0.25rem;">|</span>

                <input type="text" id="crm-map-postcode" placeholder="Postcode..." class="crm-postcode-input" style="width:120px;">
                <select id="crm-map-radius" class="crm-filter-select crm-radius-select">
                    <?php foreach ([5, 10, 15, 20, 30, 50, 100] as $r): ?>
                        <option value="<?php echo $r; ?>" <?php echo $r === 10 ? 'selected' : ''; ?>><?php echo $r; ?> km</option>
                    <?php endforeach; ?>
                </select>
                <button id="crm-map-radius-btn" class="button">Zoeken</button>
                <button id="crm-map-radius-reset" class="button" style="display:none;">Reset</button>

                <span id="crm-map-count" style="color:#666;font-size:0.9rem;margin-left:0.5rem;">
                    <?php echo count($dealer_data); ?> dealers op de kaart
                </span>

                <?php /* Auteur: Khayrallah Issa
                         Knop naar de routeplanner (US-01 t/m US-04). Opent in
                         een nieuw tabblad zodat deze Kaart-pagina open blijft. */ ?>
                <a href="<?php echo esc_url(home_url('/crm-extensions/public/demo_route_planner.php')); ?>"
                   target="_blank" rel="noopener" class="button">
                    Routeplanner openen
                </a>

                <?php if ($is_admin):
                    $geo_failed = DealerCRM_Database::count_geocode_failed();
                ?>
                    <div style="margin-left:auto;display:flex;gap:0.5rem;align-items:center;">
                        <button id="crm-geocode-btn" class="button button-primary">
                            Geocoding starten
                        </button>
                        <span id="crm-geocode-status" style="color:#666;font-size:0.85rem;">
                            <?php echo $remaining; ?> dealers zonder co&ouml;rdinaten
                            <?php if ($geo_failed > 0): ?>
                                (<?php echo $geo_failed; ?> niet gevonden)
                            <?php endif; ?>
                        </span>
                        <?php if ($remaining > 0): ?>
                            <span style="color:#46b450;font-size:0.8rem;" title="Geocoding draait automatisch op de achtergrond">&#9679; Achtergrond actief</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="crm-map" style="width:100%;height:600px;border:1px solid #ddd;border-radius:8px;"></div>

            <?php /* Auteur: Khayrallah Issa
                     US-01 t/m US-04: de routeplanner ingebed op de Kaart-pagina.
                     De routeplanner draait als zelfstandige pagina in een iframe
                     (embed-modus, dus zonder dubbele kop). */ ?>
            <h2 style="margin-top:1.5rem;">Routeplanner</h2>
            <p style="color:#666;margin:0 0 0.5rem;">
                Selecteer dealers op de kaart, bereken de route via OSRM en sla 'm op.
            </p>
            <iframe src="<?php echo esc_url(home_url('/crm-extensions/public/demo_route_planner.php?embed=1')); ?>"
                    title="Routeplanner"
                    style="width:100%;height:720px;border:1px solid #ddd;border-radius:8px;"></iframe>

            <!-- Leaflet JS -->
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

            <script>
            (function() {
                var dealers = <?php echo wp_json_encode($dealer_data); ?>;
                var detailBase = <?php echo wp_json_encode($detail_base); ?>;

                // Initialiseer kaart
                var map = L.map('crm-map').setView([52.1, 5.3], 7);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                    maxZoom: 19,
                }).addTo(map);

                var markers = L.markerClusterGroup({
                    chunkedLoading: true,
                    maxClusterRadius: 50,
                });
                var allMarkers = [];

                // Maak markers aan
                dealers.forEach(function(d) {
                    var popupHtml =
                        '<strong><a href="' + detailBase + d.id + '">' + escHtml(d.name) + '</a></strong><br>' +
                        (d.city ? escHtml(d.city) + '<br>' : '') +
                        (d.phone ? 'Tel: ' + escHtml(d.phone) + '<br>' : '') +
                        (d.email ? 'E-mail: <a href="mailto:' + escHtml(d.email) + '">' + escHtml(d.email) + '</a><br>' : '') +
                        (d.brands.length ? '<em>' + escHtml(d.brands.join(', ')) + '</em>' : '');

                    var marker = L.marker([d.lat, d.lng]);
                    marker.bindPopup(popupHtml);
                    marker.dealerData = d;
                    allMarkers.push(marker);
                });

                function applyFilters() {
                    var brandFilter = document.getElementById('crm-map-filter-brand').value;
                    var statusFilter = document.getElementById('crm-map-filter-status').value;
                    markers.clearLayers();
                    var count = 0;

                    allMarkers.forEach(function(m) {
                        var d = m.dealerData;
                        var show = true;

                        if (brandFilter && d.brands.indexOf(brandFilter) === -1) {
                            show = false;
                        }
                        if (statusFilter && d.status !== statusFilter) {
                            show = false;
                        }

                        if (show) {
                            markers.addLayer(m);
                            count++;
                        }
                    });

                    document.getElementById('crm-map-count').textContent = count + ' dealers op de kaart';
                }

                // Eerste keer alle markers toevoegen
                allMarkers.forEach(function(m) { markers.addLayer(m); });
                map.addLayer(markers);

                // Filter events
                document.getElementById('crm-map-filter-brand').addEventListener('change', applyFilters);
                document.getElementById('crm-map-filter-status').addEventListener('change', applyFilters);

                // Geocoding
                var geocodeBtn = document.getElementById('crm-geocode-btn');
                var geocodeStatus = document.getElementById('crm-geocode-status');
                if (geocodeBtn) {
                    var geocoding = false;
                    geocodeBtn.addEventListener('click', function() {
                        if (geocoding) {
                            geocoding = false;
                            geocodeBtn.textContent = 'Geocoding starten';
                            return;
                        }
                        geocoding = true;
                        geocodeBtn.textContent = 'Stoppen';
                        runGeocoding();
                    });

                    function runGeocoding() {
                        if (!geocoding) return;
                        geocodeStatus.textContent = 'Bezig met geocoding...';
                        var fd = new FormData();
                        fd.append('action', 'crm_geocode_batch');
                        fd.append('nonce', dealerCRM.nonce);
                        fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                            .then(function(r) { return r.json(); })
                            .then(function(r) {
                                if (r.success) {
                                    var d = r.data;
                                    var failedInfo = d.failed > 0 ? ' (' + d.failed + ' niet gevonden)' : '';
                                    if (d.remaining > 0 && d.geocoded > 0 && geocoding) {
                                        geocodeStatus.textContent = d.remaining + ' dealers nog te verwerken... (' + d.geocoded + ' zojuist verwerkt' + failedInfo + ')';
                                        runGeocoding();
                                    } else {
                                        geocoding = false;
                                        geocodeBtn.textContent = 'Geocoding starten';
                                        geocodeStatus.textContent = d.remaining > 0
                                            ? d.remaining + ' dealers zonder co\u00f6rdinaten' + failedInfo
                                            : 'Alle dealers zijn gegeocodeerd!' + failedInfo + ' Herlaad de pagina om de kaart bij te werken.';
                                    }
                                } else {
                                    geocoding = false;
                                    geocodeBtn.textContent = 'Geocoding starten';
                                    geocodeStatus.textContent = 'Fout: ' + (r.data || 'Onbekende fout');
                                }
                            })
                            .catch(function() {
                                geocoding = false;
                                geocodeBtn.textContent = 'Geocoding starten';
                                geocodeStatus.textContent = 'Verbindingsfout. Probeer opnieuw.';
                            });
                    }
                }

                function escHtml(str) {
                    var d = document.createElement('div');
                    d.textContent = str || '';
                    return d.innerHTML;
                }

                // Postcode/radius zoeken
                var radiusCircle = null;
                var postcodeInput = document.getElementById('crm-map-postcode');
                var radiusSelect = document.getElementById('crm-map-radius');
                var radiusBtn = document.getElementById('crm-map-radius-btn');
                var radiusResetBtn = document.getElementById('crm-map-radius-reset');

                function haversineDistance(lat1, lng1, lat2, lng2) {
                    var R = 6371;
                    var dLat = (lat2 - lat1) * Math.PI / 180;
                    var dLng = (lng2 - lng1) * Math.PI / 180;
                    var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                            Math.sin(dLng/2) * Math.sin(dLng/2);
                    var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                    return R * c;
                }

                function applyRadiusFilter(lat, lng, radiusKm) {
                    // Remove existing circle
                    if (radiusCircle) {
                        map.removeLayer(radiusCircle);
                    }

                    // Draw circle
                    radiusCircle = L.circle([lat, lng], {
                        radius: radiusKm * 1000,
                        color: '#2271b1',
                        fillColor: '#2271b1',
                        fillOpacity: 0.08,
                        weight: 2,
                    }).addTo(map);

                    // Filter markers
                    var brandFilter = document.getElementById('crm-map-filter-brand').value;
                    var statusFilter = document.getElementById('crm-map-filter-status').value;
                    markers.clearLayers();
                    var count = 0;

                    allMarkers.forEach(function(m) {
                        var d = m.dealerData;
                        var show = true;
                        var dist = haversineDistance(lat, lng, d.lat, d.lng);

                        if (dist > radiusKm) show = false;
                        if (brandFilter && d.brands.indexOf(brandFilter) === -1) show = false;
                        if (statusFilter && d.status !== statusFilter) show = false;

                        if (show) {
                            markers.addLayer(m);
                            count++;
                        }
                    });

                    document.getElementById('crm-map-count').textContent = count + ' dealers binnen ' + radiusKm + ' km';

                    // Fit map to circle bounds
                    map.fitBounds(radiusCircle.getBounds());

                    radiusResetBtn.style.display = '';
                }

                function clearRadiusFilter() {
                    if (radiusCircle) {
                        map.removeLayer(radiusCircle);
                        radiusCircle = null;
                    }
                    postcodeInput.value = '';
                    radiusResetBtn.style.display = 'none';
                    applyFilters();
                }

                radiusBtn.addEventListener('click', function() {
                    var postcode = postcodeInput.value.trim();
                    if (!postcode) return;

                    radiusBtn.disabled = true;
                    radiusBtn.textContent = 'Zoeken...';

                    fetch('https://nominatim.openstreetmap.org/search?' + new URLSearchParams({
                        format: 'json',
                        q: postcode + ', Netherlands',
                        limit: 1,
                    }), {
                        headers: { 'User-Agent': 'DealerCRM-WordPress-Plugin/1.0' }
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        radiusBtn.disabled = false;
                        radiusBtn.textContent = 'Zoeken';

                        if (!data.length || !data[0].lat || !data[0].lon) {
                            alert('Postcode niet gevonden.');
                            return;
                        }

                        var lat = parseFloat(data[0].lat);
                        var lng = parseFloat(data[0].lon);
                        var radiusKm = parseInt(radiusSelect.value);
                        applyRadiusFilter(lat, lng, radiusKm);
                    })
                    .catch(function() {
                        radiusBtn.disabled = false;
                        radiusBtn.textContent = 'Zoeken';
                        alert('Fout bij het opzoeken van de postcode.');
                    });
                });

                radiusResetBtn.addEventListener('click', clearRadiusFilter);

                // Re-apply radius when brand/status filters change while radius is active
                var origApplyFilters = applyFilters;
                applyFilters = function() {
                    if (radiusCircle) {
                        var center = radiusCircle.getLatLng();
                        var radiusKm = parseInt(radiusSelect.value);
                        // Remove circle temporarily and re-apply
                        map.removeLayer(radiusCircle);
                        radiusCircle = null;
                        applyRadiusFilter(center.lat, center.lng, radiusKm);
                    } else {
                        origApplyFilters();
                    }
                };

                // Allow Enter key in postcode input
                postcodeInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        radiusBtn.click();
                    }
                });
            })();
            </script>
        </div>
        <?php
    }

    public static function page_tags() {
        if (isset($_POST['new_tag_name']) && wp_verify_nonce($_POST['_wpnonce'], 'crm_add_tag')) {
            $name = sanitize_text_field($_POST['new_tag_name']);
            $color = sanitize_hex_color($_POST['new_tag_color']) ?: '#6c757d';
            if ($name) DealerCRM_Database::create_tag($name, $color);
        }
        if (isset($_GET['delete_tag']) && wp_verify_nonce($_GET['_wpnonce'], 'crm_delete_tag')) {
            DealerCRM_Database::delete_tag((int) $_GET['delete_tag']);
        }

        $tags = DealerCRM_Database::get_all_tags();
        ?>
        <div class="wrap dealer-crm-wrap">
            <h1>Tags beheren</h1>
            <div class="crm-card" style="max-width:600px">
                <form method="post" class="crm-tag-form">
                    <?php wp_nonce_field('crm_add_tag'); ?>
                    <div class="crm-form-row">
                        <input type="text" name="new_tag_name" placeholder="Nieuwe tag..." required>
                        <input type="color" name="new_tag_color" value="#6c757d">
                        <button type="submit" class="button button-primary">Toevoegen</button>
                    </div>
                </form>
                <table class="crm-table widefat striped" style="margin-top:1rem">
                    <thead><tr><th>Tag</th><th>Kleur</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($tags as $tag):
                            $delete_url = wp_nonce_url(admin_url('admin.php?page=dealer-crm-tags&delete_tag=' . $tag->id), 'crm_delete_tag');
                        ?>
                            <tr>
                                <td><span class="crm-tag-chip" style="background:<?php echo esc_attr($tag->color); ?>"><?php echo esc_html($tag->name); ?></span></td>
                                <td><?php echo esc_html($tag->color); ?></td>
                                <td><a href="<?php echo esc_url($delete_url); ?>" class="crm-delete-link" onclick="return confirm('Tag verwijderen?')">Verwijderen</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public static function page_import() {
        $message = '';
        if (isset($_POST['crm_import']) && wp_verify_nonce($_POST['_wpnonce'], 'crm_import_dealers')) {
            if (!empty($_FILES['xlsx_file']['tmp_name'])) {
                $result = DealerCRM_Import::import_xlsx($_FILES['xlsx_file']['tmp_name']);
                $message = $result['message'];
            } else {
                $message = 'Selecteer een bestand.';
            }
        }
        if (isset($_POST['crm_import_server']) && wp_verify_nonce($_POST['_wpnonce'], 'crm_import_dealers')) {
            $server_path = sanitize_text_field($_POST['server_path'] ?? '');
            if ($server_path && file_exists($server_path)) {
                $result = DealerCRM_Import::import_xlsx($server_path);
                $message = $result['message'];
            } else {
                $message = 'Bestand niet gevonden op server.';
            }
        }
        ?>
        <div class="wrap dealer-crm-wrap">
            <h1>Dealers importeren</h1>
            <?php if ($message): ?>
                <div class="notice notice-info"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>
            <div class="crm-card" style="max-width:600px">
                <p>Upload een XLSX-bestand met kolommen: <strong>Naam, Straat, Postcode, Plaats, Telefoon, E-mail, Website, Merken</strong></p>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('crm_import_dealers'); ?>
                    <input type="file" name="xlsx_file" accept=".xlsx" required>
                    <p style="margin-top:1rem">
                        <button type="submit" name="crm_import" class="button button-primary">Importeren</button>
                    </p>
                    <p class="description">Let op: bestaande dealers worden niet gedupliceerd als je opnieuw importeert. Overweeg eerst de database te legen als je een volledige hernieuwe import wilt doen.</p>
                </form>
                <hr style="margin:1.5rem 0">
                <h3>Of importeer vanaf serverpad</h3>
                <form method="post">
                    <?php wp_nonce_field('crm_import_dealers'); ?>
                    <input type="text" name="server_path" value="<?php echo esc_attr(WP_CONTENT_DIR . '/uploads/dealers_masterbestand.xlsx'); ?>" style="width:100%" readonly>
                    <p style="margin-top:0.5rem">
                        <button type="submit" name="crm_import_server" class="button button-primary">Importeren vanaf server</button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    public static function page_activity_log() {
        $users = get_users(['fields' => ['ID', 'display_name']]);
        $action_types = DealerCRM_ActivityLog::get_action_types();

        $filter_user = (int) ($_GET['user_id'] ?? 0);
        $filter_action = sanitize_text_field($_GET['action_type'] ?? '');
        $paged = max(1, (int) ($_GET['paged'] ?? 1));
        $per_page = 30;
        $offset = ($paged - 1) * $per_page;

        $total = DealerCRM_ActivityLog::count_activity($filter_user ?: null, $filter_action ?: null);
        $activities = DealerCRM_ActivityLog::get_recent_activity($per_page, $filter_user ?: null, $filter_action ?: null, $offset);
        $total_pages = ceil($total / $per_page);
        $base_url = admin_url('admin.php?page=dealer-crm-activity');
        ?>
        <div class="wrap dealer-crm-wrap">
            <h1>Activiteitenlog</h1>

            <form method="get" class="crm-filters" style="margin-bottom:1rem;">
                <input type="hidden" name="page" value="dealer-crm-activity">
                <div class="crm-filter-row">
                    <select name="user_id" class="crm-filter-select">
                        <option value="">Alle gebruikers</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u->ID; ?>" <?php selected($filter_user, $u->ID); ?>><?php echo esc_html($u->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="action_type" class="crm-filter-select">
                        <option value="">Alle acties</option>
                        <?php foreach ($action_types as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($filter_action, $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button button-primary">Filteren</button>
                    <a href="<?php echo $base_url; ?>" class="button">Reset</a>
                </div>
            </form>

            <div class="crm-results-info"><?php echo number_format($total); ?> activiteit<?php echo $total !== 1 ? 'en' : ''; ?></div>

            <table class="crm-table widefat striped">
                <thead>
                    <tr>
                        <th>Datum/tijd</th>
                        <th>Gebruiker</th>
                        <th>Dealer</th>
                        <th>Actie</th>
                        <th>Beschrijving</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activities)): ?>
                        <tr><td colspan="5" class="crm-no-results">Geen activiteiten gevonden.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($activities as $act):
                        $dealer_url = $act->dealer_id ? admin_url('admin.php?page=dealer-crm&action=view&id=' . $act->dealer_id) : '';
                    ?>
                        <tr>
                            <td><?php echo date('d-m-Y H:i', strtotime($act->created_at)); ?></td>
                            <td><?php echo esc_html($act->user_name ?? 'Systeem'); ?></td>
                            <td>
                                <?php if ($act->dealer_id && $act->dealer_name): ?>
                                    <a href="<?php echo esc_url($dealer_url); ?>"><?php echo esc_html($act->dealer_name); ?></a>
                                <?php elseif ($act->dealer_id): ?>
                                    <span class="crm-empty">#<?php echo $act->dealer_id; ?> (verwijderd)</span>
                                <?php else: ?>
                                    <span class="crm-empty">-</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="crm-activity-action crm-activity-action-<?php echo esc_attr(str_replace('_', '-', $act->action)); ?>"><?php echo esc_html(DealerCRM_ActivityLog::get_action_label($act->action)); ?></span></td>
                            <td><?php echo esc_html($act->description); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="crm-pagination">
                    <?php
                    echo paginate_links([
                        'base'    => $base_url . '%_%',
                        'format'  => '&paged=%#%',
                        'current' => $paged,
                        'total'   => $total_pages,
                        'add_args' => array_filter([
                            'user_id' => $filter_user ?: null,
                            'action_type' => $filter_action ?: null,
                        ]),
                    ]);
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Campagnes ──

    public static function page_campaigns() {
        $action = sanitize_text_field($_GET['action'] ?? '');
        $id = (int) ($_GET['id'] ?? 0);

        if ($action === 'new') {
            self::render_campaign_form();
        } elseif ($action === 'edit' && $id) {
            self::render_campaign_form($id);
        } elseif ($action === 'view' && $id) {
            self::render_campaign_detail($id);
        } else {
            self::render_campaign_list();
        }
    }

    private static function render_campaign_list() {
        $campaigns = DealerCRM_Campaigns::get_all_campaigns();
        $base_url = admin_url('admin.php?page=dealer-crm-campaigns');
        ?>
        <div class="wrap dealer-crm-wrap">
            <h1 style="display:flex;align-items:center;gap:1rem;">
                Campagnes
                <a href="<?php echo esc_url(add_query_arg('action', 'new', $base_url)); ?>" class="button button-primary">+ Nieuwe campagne</a>
            </h1>

            <?php
            $stats = DealerCRM_Campaigns::get_stats();
            ?>
            <div class="crm-dashboard-cards">
                <div class="crm-dashboard-card crm-dashboard-card-blue">
                    <span class="crm-dashboard-card-num"><?php echo $stats['total']; ?></span>
                    <span class="crm-dashboard-card-label">Totaal</span>
                </div>
                <div class="crm-dashboard-card crm-dashboard-card-green">
                    <span class="crm-dashboard-card-num"><?php echo $stats['actief']; ?></span>
                    <span class="crm-dashboard-card-label">Actief</span>
                </div>
                <div class="crm-dashboard-card" style="border-top-color:#6c757d;">
                    <span class="crm-dashboard-card-num"><?php echo $stats['afgerond']; ?></span>
                    <span class="crm-dashboard-card-label">Afgerond</span>
                </div>
            </div>

            <table class="crm-table widefat striped">
                <thead>
                    <tr>
                        <th>Naam</th>
                        <th>Status</th>
                        <th>Dealers</th>
                        <th>Aangemaakt</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campaigns)): ?>
                        <tr><td colspan="5" class="crm-no-results">Geen campagnes gevonden.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($campaigns as $c):
                        $detail_url = add_query_arg(['action' => 'view', 'id' => $c->id], $base_url);
                        $status_class = $c->status === 'actief' ? 'crm-status-actief' : ($c->status === 'afgerond' ? 'crm-status-inactief' : 'crm-status-concept');
                    ?>
                        <tr class="crm-dealer-row" data-href="<?php echo esc_url($detail_url); ?>">
                            <td><a href="<?php echo esc_url($detail_url); ?>" class="crm-dealer-name"><?php echo esc_html($c->name); ?></a></td>
                            <td><span class="crm-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html(ucfirst($c->status)); ?></span></td>
                            <td><?php echo (int) $c->dealer_count; ?></td>
                            <td><?php echo date('d-m-Y', strtotime($c->created_at)); ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'id' => $c->id], $base_url)); ?>" class="button button-small">Bewerken</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function render_campaign_form($id = 0) {
        $campaign = null;
        if ($id) {
            $campaign = DealerCRM_Campaigns::get_campaign($id);
            if (!$campaign) {
                echo '<div class="wrap"><h1>Campagne niet gevonden</h1></div>';
                return;
            }
        }
        $base_url = admin_url('admin.php?page=dealer-crm-campaigns');
        ?>
        <div class="wrap dealer-crm-wrap">
            <a href="<?php echo esc_url($base_url); ?>" class="crm-back-link">&larr; Terug naar overzicht</a>
            <h1><?php echo $id ? 'Campagne bewerken' : 'Nieuwe campagne'; ?></h1>

            <div class="crm-card" style="max-width:600px;">
                <form id="crm-campaign-form" data-campaign-id="<?php echo $id; ?>">
                    <table class="crm-info-table">
                        <tr>
                            <th>Naam *</th>
                            <td><input type="text" name="name" value="<?php echo esc_attr($campaign->name ?? ''); ?>" required style="width:100%;"></td>
                        </tr>
                        <tr>
                            <th>Beschrijving</th>
                            <td><textarea name="description" rows="3" style="width:100%;"><?php echo esc_textarea($campaign->description ?? ''); ?></textarea></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <select name="status">
                                    <option value="concept" <?php selected($campaign->status ?? 'concept', 'concept'); ?>>Concept</option>
                                    <option value="actief" <?php selected($campaign->status ?? '', 'actief'); ?>>Actief</option>
                                    <option value="afgerond" <?php selected($campaign->status ?? '', 'afgerond'); ?>>Afgerond</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <div class="crm-form-actions" style="margin-top:1rem;">
                        <button type="submit" class="button button-primary"><?php echo $id ? 'Opslaan' : 'Aanmaken'; ?></button>
                        <a href="<?php echo esc_url($base_url); ?>" class="button">Annuleren</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    private static function render_campaign_detail($id) {
        $campaign = DealerCRM_Campaigns::get_campaign($id);
        if (!$campaign) {
            echo '<div class="wrap"><h1>Campagne niet gevonden</h1></div>';
            return;
        }

        $campaign_dealers = DealerCRM_Campaigns::get_campaign_dealers($id);
        $base_url = admin_url('admin.php?page=dealer-crm-campaigns');

        // Load filter data for "add dealers" panel
        $brands = DealerCRM_Database::get_all_brands();
        $cities = DealerCRM_Database::get_all_cities();
        $statuses = DealerCRM_Database::get_statuses();
        $tags = DealerCRM_Database::get_all_tags();
        ?>
        <div class="wrap dealer-crm-wrap">
            <a href="<?php echo esc_url($base_url); ?>" class="crm-back-link">&larr; Terug naar overzicht</a>

            <div class="crm-detail-header">
                <h1><?php echo esc_html($campaign->name); ?></h1>
                <span class="crm-status crm-status-<?php echo esc_attr($campaign->status); ?>"><?php echo esc_html(ucfirst($campaign->status)); ?></span>
                <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'id' => $id], $base_url)); ?>" class="button button-small" style="margin-left:0.5rem;">Bewerken</a>
            </div>

            <?php if ($campaign->description): ?>
                <p style="color:#666;margin:0.5rem 0 1rem;"><?php echo esc_html($campaign->description); ?></p>
            <?php endif; ?>

            <div class="crm-card">
                <div class="crm-tabs">
                    <button class="crm-tab active" data-tab="campaign-dealers">Dealers (<?php echo count($campaign_dealers); ?>)</button>
                    <button class="crm-tab" data-tab="campaign-add">Dealers toevoegen</button>
                </div>

                <!-- Dealers in campaign -->
                <div class="crm-tab-content active" id="tab-campaign-dealers">
                    <?php if (empty($campaign_dealers)): ?>
                        <p class="crm-empty">Nog geen dealers in deze campagne.</p>
                    <?php else: ?>
                        <div style="margin-bottom:0.5rem;display:flex;align-items:center;gap:0.5rem;">
                            <span><?php echo count($campaign_dealers); ?> dealer<?php echo count($campaign_dealers) !== 1 ? 's' : ''; ?></span>
                            <button class="button button-small" id="crm-export-campaign-btn" data-campaign-id="<?php echo $id; ?>">Exporteren als CSV</button>
                            <button class="button button-small" id="crm-remove-selected-campaign-dealers-btn" data-campaign-id="<?php echo $id; ?>" style="display:none;color:#b32d2e;">Selectie verwijderen</button>
                        </div>
                        <?php
                            $office = DealerCRM_Database::get_office_coords();
                            $office_set = !empty($office);
                        ?>
                        <?php if (!$office_set): ?>
                            <p style="font-size:0.85rem;color:#666;margin:0 0 0.5rem;">
                                💡 Tip: stel je <a href="<?php echo esc_url(admin_url('admin.php?page=dealer-crm-settings')); ?>">kantoor postcode</a> in om de afstand-kolom te activeren.
                            </p>
                        <?php endif; ?>
                        <table class="crm-table widefat striped crm-sortable-table" id="crm-campaign-dealers-table">
                            <thead>
                                <tr>
                                    <th style="width:30px;"><input type="checkbox" id="crm-camp-dealer-select-all" title="Alles selecteren"></th>
                                    <th class="crm-sortable" data-sort-key="name" data-sort-type="text">Naam</th>
                                    <th class="crm-sortable" data-sort-key="contact" data-sort-type="text">Contactpersoon</th>
                                    <th class="crm-sortable" data-sort-key="owner" data-sort-type="text">Eigenaar</th>
                                    <th class="crm-sortable" data-sort-key="street" data-sort-type="text">Adres</th>
                                    <th class="crm-sortable" data-sort-key="postcode" data-sort-type="text">Postcode</th>
                                    <th class="crm-sortable" data-sort-key="city" data-sort-type="text">Plaats</th>
                                    <th>Telefoon</th>
                                    <th>E-mail</th>
                                    <th>Website</th>
                                    <?php if ($office_set): ?>
                                        <th class="crm-sortable" data-sort-key="distance" data-sort-type="number" title="Afstand tot kantoor">Afstand</th>
                                    <?php endif; ?>
                                    <th>Status</th>
                                    <th style="width:40px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaign_dealers as $dl):
                                    $dealer_url = admin_url('admin.php?page=dealer-crm&action=view&id=' . $dl->id);
                                    $contact = $dl->contact_person ?? '';
                                    $owner   = $dl->owner ?? '';
                                    $phone   = $dl->phone ?? '';
                                    $email   = $dl->email ?? '';
                                    $website = $dl->website ?? '';
                                    $distance_km = null;
                                    if ($office_set && !empty($dl->lat) && !empty($dl->lng)) {
                                        $distance_km = DealerCRM_Database::haversine_km(
                                            $office['lat'], $office['lng'], $dl->lat, $dl->lng
                                        );
                                    }
                                ?>
                                    <tr
                                        data-name="<?php echo esc_attr(strtolower($dl->name)); ?>"
                                        data-contact="<?php echo esc_attr(strtolower($contact)); ?>"
                                        data-owner="<?php echo esc_attr(strtolower($owner)); ?>"
                                        data-street="<?php echo esc_attr(strtolower($dl->street ?? '')); ?>"
                                        data-postcode="<?php echo esc_attr(strtolower($dl->postcode ?? '')); ?>"
                                        data-city="<?php echo esc_attr(strtolower($dl->city ?? '')); ?>"
                                        data-distance="<?php echo $distance_km !== null ? esc_attr($distance_km) : '999999'; ?>">
                                        <td class="crm-checkbox-cell"><input type="checkbox" class="crm-camp-remove-cb" value="<?php echo $dl->id; ?>"></td>
                                        <td><a href="<?php echo esc_url($dealer_url); ?>" class="crm-dealer-name"><?php echo esc_html($dl->name); ?></a></td>
                                        <td><?php echo esc_html($contact ?: '—'); ?></td>
                                        <td><?php echo esc_html($owner ?: '—'); ?></td>
                                        <td><?php echo esc_html($dl->street ?: '—'); ?></td>
                                        <td style="font-family:monospace;white-space:nowrap;"><?php echo esc_html($dl->postcode ?: '—'); ?></td>
                                        <td><?php echo esc_html($dl->city ?: '—'); ?></td>
                                        <td>
                                            <?php if ($phone): ?>
                                                <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($email): ?>
                                                <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($website): ?>
                                                <a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener">↗</a>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <?php if ($office_set): ?>
                                            <td style="text-align:right;font-variant-numeric:tabular-nums;">
                                                <?php echo $distance_km !== null ? esc_html($distance_km) . ' km' : '<span style="color:#999;">—</span>'; ?>
                                            </td>
                                        <?php endif; ?>
                                        <td><span class="crm-status crm-status-<?php echo esc_attr($dl->status); ?>"><?php echo esc_html(ucfirst($dl->status)); ?></span></td>
                                        <td><button class="crm-delete-btn crm-remove-campaign-dealer-btn" data-campaign-id="<?php echo $id; ?>" data-dealer-id="<?php echo $dl->id; ?>" title="Verwijderen">&times;</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Add dealers tab -->
                <div class="crm-tab-content" id="tab-campaign-add">
                    <div class="crm-campaign-add-filters" style="margin-bottom:1rem;">
                        <div class="crm-filter-row">
                            <input type="text" id="camp-filter-search" placeholder="Zoek op naam, e-mail, telefoon of plaats..." class="crm-search-input">
                            <select id="camp-filter-brand" class="crm-filter-select">
                                <option value="">Alle merken</option>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?php echo esc_attr($b); ?>"><?php echo esc_html($b); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="camp-filter-city" class="crm-filter-select crm-filter-city">
                                <option value="">Alle plaatsen</option>
                                <?php foreach ($cities as $c): ?>
                                    <option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="camp-filter-status" class="crm-filter-select">
                                <option value="">Alle statussen</option>
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?php echo esc_attr($s); ?>"><?php echo esc_html(ucfirst($s)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="crm-filter-row" style="margin-top:0.5rem;">
                            <select id="camp-filter-webshop" class="crm-filter-select">
                                <option value="">Webshop</option>
                                <option value="yes">Met webshop</option>
                                <option value="no">Zonder webshop</option>
                            </select>
                            <select id="camp-filter-tag" class="crm-filter-select">
                                <option value="">Alle tags</option>
                                <?php foreach ($tags as $t): ?>
                                    <option value="<?php echo $t->id; ?>"><?php echo esc_html($t->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="button button-primary" id="crm-campaign-search-btn" data-campaign-id="<?php echo $id; ?>">Zoeken</button>
                        </div>
                    </div>
                    <div id="crm-campaign-search-results">
                        <p class="crm-empty">Gebruik de filters om dealers te zoeken en toe te voegen.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function page_webshops() {
        $stats = DealerCRM_WebshopDetector::get_scan_stats();
        $filter = sanitize_text_field($_GET['webshop_filter'] ?? 'all');
        $dealers = DealerCRM_WebshopDetector::get_dealers_by_webshop($filter);
        $base_url = admin_url('admin.php?page=dealer-crm-webshops');
        $is_admin = current_user_can('manage_options');
        ?>
        <div class="wrap dealer-crm-wrap">
            <h1>Webshop overzicht</h1>

            <div class="crm-webshop-stats">
                <div class="crm-webshop-stat crm-webshop-stat-green">
                    <span class="crm-webshop-stat-num"><?php echo $stats['detected']; ?></span>
                    <span class="crm-webshop-stat-label">Webshop gedetecteerd</span>
                </div>
                <div class="crm-webshop-stat crm-webshop-stat-gray">
                    <span class="crm-webshop-stat-num"><?php echo $stats['none']; ?></span>
                    <span class="crm-webshop-stat-label">Geen webshop</span>
                </div>
                <div class="crm-webshop-stat crm-webshop-stat-blue">
                    <span class="crm-webshop-stat-num"><?php echo $stats['unscanned']; ?></span>
                    <span class="crm-webshop-stat-label">Niet gescand</span>
                </div>
                <div class="crm-webshop-stat crm-webshop-stat-red">
                    <span class="crm-webshop-stat-num"><?php echo $stats['errors']; ?></span>
                    <span class="crm-webshop-stat-label">Fouten</span>
                </div>
            </div>

            <?php if ($is_admin): ?>
                <div class="crm-card" style="display:flex;align-items:center;gap:1rem;padding:0.75rem 1.25rem;flex-wrap:wrap;">
                    <button id="crm-webshop-scan-btn" class="button button-primary">Scan starten</button>
                    <span id="crm-webshop-scan-status" style="color:#666;font-size:0.9rem;">
                        <?php echo $stats['unscanned']; ?> dealers nog te scannen
                    </span>
                    <div id="crm-webshop-scan-progress" style="display:none;flex:1;">
                        <div class="crm-scan-progress-bar">
                            <div class="crm-scan-progress-fill" id="crm-webshop-scan-fill" style="width:0%"></div>
                        </div>
                    </div>
                    <?php
                    // Show reset buttons for platforms that have detected dealers
                    $platform_counts = self::get_webshop_platform_counts();
                    if (!empty($platform_counts)):
                    ?>
                        <div style="margin-left:auto;display:flex;align-items:center;gap:0.5rem;">
                            <span style="color:#666;font-size:0.85rem;">Reset:</span>
                            <?php foreach ($platform_counts as $platform => $count): ?>
                                <button class="button button-small crm-reset-platform-btn" data-platform="<?php echo esc_attr($platform); ?>" title="Reset <?php echo esc_attr($count); ?> dealers met <?php echo esc_attr($platform); ?> zodat ze opnieuw gescand worden">
                                    <?php echo esc_html($platform); ?> (<?php echo $count; ?>)
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div style="margin:1rem 0;display:flex;gap:0.25rem;">
                <a href="<?php echo esc_url($base_url); ?>" class="button <?php echo $filter === 'all' ? 'button-primary' : ''; ?>">Alle</a>
                <a href="<?php echo esc_url(add_query_arg('webshop_filter', 'detected', $base_url)); ?>" class="button <?php echo $filter === 'detected' ? 'button-primary' : ''; ?>">Met webshop</a>
                <a href="<?php echo esc_url(add_query_arg('webshop_filter', 'none', $base_url)); ?>" class="button <?php echo $filter === 'none' ? 'button-primary' : ''; ?>">Zonder webshop</a>
                <a href="<?php echo esc_url(add_query_arg('webshop_filter', 'unscanned', $base_url)); ?>" class="button <?php echo $filter === 'unscanned' ? 'button-primary' : ''; ?>">Niet gescand</a>
                <a href="<?php echo esc_url(add_query_arg('webshop_filter', 'error', $base_url)); ?>" class="button <?php echo $filter === 'error' ? 'button-primary' : ''; ?>">Fouten</a>
            </div>

            <div class="crm-results-info"><?php echo count($dealers); ?> dealer<?php echo count($dealers) !== 1 ? 's' : ''; ?></div>

            <table class="crm-table widefat striped">
                <thead>
                    <tr>
                        <th>Naam</th>
                        <th>Plaats</th>
                        <th>Website</th>
                        <th>Platform</th>
                        <th>Scan datum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dealers)): ?>
                        <tr><td colspan="5" class="crm-no-results">Geen dealers gevonden.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($dealers as $d):
                        $detail_url = admin_url('admin.php?page=dealer-crm&action=view&id=' . $d->id);
                    ?>
                        <tr class="crm-dealer-row" data-href="<?php echo esc_url($detail_url); ?>">
                            <td><a href="<?php echo esc_url($detail_url); ?>" class="crm-dealer-name"><?php echo esc_html($d->name); ?></a></td>
                            <td><?php echo esc_html($d->city); ?></td>
                            <td><?php if ($d->website): ?><a href="<?php echo esc_url($d->website); ?>" target="_blank" onclick="event.stopPropagation();" style="font-size:0.85rem;"><?php echo esc_html(preg_replace('#^https?://(www\.)?#', '', $d->website)); ?></a><?php endif; ?></td>
                            <td>
                                <?php if ($d->webshop_status === 'detected' && $d->webshop_platform): ?>
                                    <span class="crm-webshop-badge crm-webshop-<?php echo esc_attr(DealerCRM_WebshopDetector::get_platform_class($d->webshop_platform)); ?>"><?php echo esc_html($d->webshop_platform); ?></span>
                                <?php elseif ($d->webshop_status === 'none'): ?>
                                    <span style="color:#999;font-size:0.85rem;">Geen</span>
                                <?php elseif ($d->webshop_status === 'error'): ?>
                                    <span style="color:#b32d2e;font-size:0.85rem;">Fout</span>
                                <?php else: ?>
                                    <span style="color:#999;font-size:0.85rem;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.85rem;color:#666;">
                                <?php echo $d->webshop_detected_at ? date('d-m-Y H:i', strtotime($d->webshop_detected_at)) : '-'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function page_settings() {
        $slack_url = DealerCRM_Slack::get_webhook_url();
        $slack_configured = DealerCRM_Slack::is_configured();
        $slack_events = get_option('dealer_crm_slack_events', ['followup_due']);
        $office_postcode = get_option('dealer_crm_office_postcode', '');
        $office_lat = get_option('dealer_crm_office_lat', '');
        $office_lng = get_option('dealer_crm_office_lng', '');
        ?>
        <div class="wrap dealer-crm-wrap">
            <h1>Instellingen</h1>

            <div class="crm-detail-grid" style="grid-template-columns: 1fr;">
                <!-- Office postcode card -->
                <div class="crm-card">
                    <h3 style="margin-top:0;">
                        <span class="dashicons dashicons-location" style="color:#2271b1;"></span>
                        Kantoor locatie (voor afstand-berekening)
                    </h3>
                    <p class="description" style="margin-top:0;">Vul je kantoorpostcode in. De afstand tot dealers wordt automatisch berekend en is zichtbaar in campagne-overzichten.</p>

                    <div class="crm-form-row" style="gap:0.5rem;margin-bottom:0.5rem;align-items:center;">
                        <input type="text" id="crm-office-postcode" value="<?php echo esc_attr($office_postcode); ?>"
                               placeholder="bijv. 1234 AB" style="width:200px;font-family:monospace;">
                        <button id="crm-office-save-btn" class="button button-primary">Opslaan & geocoderen</button>
                        <span id="crm-office-status" style="font-size:0.85rem;color:#666;">
                            <?php if ($office_lat && $office_lng): ?>
                                ✓ Locatie bekend (<?php echo esc_html(round((float)$office_lat, 4)); ?>, <?php echo esc_html(round((float)$office_lng, 4)); ?>)
                            <?php elseif ($office_postcode): ?>
                                ⚠️ Postcode opgeslagen maar nog niet geocoded — klik nogmaals op Opslaan
                            <?php else: ?>
                                Nog niet ingesteld
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <!-- Slack card -->
                <div class="crm-card">
                    <h3 style="margin-top:0;">
                        <span class="dashicons dashicons-format-chat" style="color:#611f69;"></span>
                        Slack integratie
                    </h3>

                    <div id="crm-slack-status" style="margin-bottom:1rem;">
                        <?php if ($slack_configured): ?>
                            <div style="background:#edfaef;border:1px solid #46b450;border-radius:6px;padding:0.75rem 1rem;display:flex;align-items:center;gap:0.5rem;">
                                <span style="color:#46b450;font-size:1.2rem;">&#9679;</span>
                                <strong>Slack is verbonden</strong>
                            </div>
                        <?php else: ?>
                            <div style="background:#f0f0f1;border:1px solid #ddd;border-radius:6px;padding:0.75rem 1rem;">
                                <strong>Niet geconfigureerd</strong><br>
                                <span style="font-size:0.85rem;">Maak een Slack-app aan met Incoming Webhook en plak de URL hieronder.</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="crm-form-row" style="gap:0.5rem;margin-bottom:0.75rem;">
                        <input type="url" id="crm-slack-webhook-url" value="<?php echo esc_attr($slack_url); ?>"
                               placeholder="https://hooks.slack.com/services/T.../B.../xxx"
                               style="flex:1;font-family:monospace;font-size:0.85rem;">
                        <button id="crm-slack-save-btn" class="button button-primary">Opslaan</button>
                        <?php if ($slack_configured): ?>
                            <button id="crm-slack-test-btn" class="button">Test versturen</button>
                        <?php endif; ?>
                    </div>

                    <?php if ($slack_configured): ?>
                    <div style="margin-top:1rem;">
                        <h4 style="margin:0 0 0.5rem;">Notificaties</h4>
                        <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.4rem;cursor:pointer;">
                            <input type="checkbox" class="crm-slack-event" value="followup_due" <?php checked(in_array('followup_due', $slack_events)); ?>>
                            <span>Dagelijks overzicht van verlopen en aanstaande follow-ups (08:00)</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.4rem;cursor:pointer;">
                            <input type="checkbox" class="crm-slack-event" value="followup_created" <?php checked(in_array('followup_created', $slack_events)); ?>>
                            <span>Nieuwe follow-up aangemaakt</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.4rem;cursor:pointer;">
                            <input type="checkbox" class="crm-slack-event" value="followup_completed" <?php checked(in_array('followup_completed', $slack_events)); ?>>
                            <span>Follow-up voltooid</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.4rem;cursor:pointer;">
                            <input type="checkbox" class="crm-slack-event" value="email_sent" <?php checked(in_array('email_sent', $slack_events)); ?>>
                            <span>E-mail verstuurd vanuit CRM</span>
                        </label>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public static function page_mailchimp() {
        $is_configured = DealerCRM_Mailchimp::is_configured();
        $api_key = DealerCRM_Mailchimp::get_api_key();
        $connection = $is_configured ? DealerCRM_Mailchimp::test_connection() : null;
        $stats = DealerCRM_Mailchimp::get_stats();
        ?>
        <div class="wrap dealer-crm-wrap">
            <h1>Mailchimp integratie</h1>

            <div class="crm-detail-grid" style="grid-template-columns: 1fr 1fr;">
                <!-- Settings card -->
                <div class="crm-card">
                    <h3 style="margin-top:0;">Instellingen</h3>
                    <div id="crm-mailchimp-connection-status" style="margin-bottom:1rem;">
                        <?php if ($is_configured && $connection && $connection['success']): ?>
                            <div style="background:#edfaef;border:1px solid #46b450;border-radius:6px;padding:0.75rem 1rem;display:flex;align-items:center;gap:0.5rem;">
                                <span style="color:#46b450;font-size:1.2rem;">&#9679;</span>
                                <div>
                                    <strong>Verbonden</strong><br>
                                    <span style="font-size:0.85rem;color:#666;">Account: <?php echo esc_html($connection['account_name']); ?></span>
                                </div>
                            </div>
                        <?php elseif ($is_configured): ?>
                            <div style="background:#fef7f1;border:1px solid #dba617;border-radius:6px;padding:0.75rem 1rem;">
                                <strong style="color:#dba617;">&#9888; Verbinding mislukt</strong><br>
                                <span style="font-size:0.85rem;">Controleer je API-key.</span>
                            </div>
                        <?php else: ?>
                            <div style="background:#f0f0f1;border:1px solid #ddd;border-radius:6px;padding:0.75rem 1rem;">
                                <strong>Niet geconfigureerd</strong><br>
                                <span style="font-size:0.85rem;">Voer je Mailchimp API-key in om te starten.</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="crm-form-row" style="gap:0.5rem;">
                        <input type="text" id="crm-mailchimp-api-key" value="<?php echo esc_attr($api_key); ?>"
                               placeholder="Mailchimp API-key (bijv. abc123-us21)"
                               style="flex:1;font-family:monospace;font-size:0.85rem;">
                        <button id="crm-mailchimp-save-btn" class="button button-primary">Opslaan</button>
                    </div>
                    <p class="description" style="margin-top:0.5rem;font-size:0.8rem;">
                        Vind je API-key in Mailchimp &rarr; Account &rarr; Extras &rarr; API keys
                    </p>
                </div>

                <!-- Stats card -->
                <div class="crm-card">
                    <h3 style="margin-top:0;">Synchronisatie</h3>
                    <?php if ($is_configured): ?>
                        <div class="crm-dashboard-cards" style="grid-template-columns: repeat(3, 1fr);gap:0.5rem;">
                            <div class="crm-dashboard-card crm-dashboard-card-blue" style="padding:0.75rem;">
                                <span class="crm-dashboard-card-num" style="font-size:1.5rem;"><?php echo $stats['dealers_synced']; ?></span>
                                <span class="crm-dashboard-card-label" style="font-size:0.75rem;">Dealers met MC-data</span>
                                <?php if ($stats['dealers_checked'] > $stats['dealers_synced']): ?>
                                    <span style="font-size:0.7rem;color:#999;">(<?php echo $stats['dealers_checked']; ?> gecontroleerd)</span>
                                <?php endif; ?>
                            </div>
                            <div class="crm-dashboard-card crm-dashboard-card-green" style="padding:0.75rem;">
                                <span class="crm-dashboard-card-num" style="font-size:1.5rem;"><?php echo $stats['total_campaigns']; ?></span>
                                <span class="crm-dashboard-card-label" style="font-size:0.75rem;">Campagnes</span>
                            </div>
                            <div class="crm-dashboard-card" style="padding:0.75rem;background:#f8f0fc;">
                                <span class="crm-dashboard-card-num" style="font-size:1.5rem;"><?php echo $stats['total_opens']; ?></span>
                                <span class="crm-dashboard-card-label" style="font-size:0.75rem;">Opens</span>
                            </div>
                        </div>

                        <div style="margin-top:1rem;display:flex;align-items:center;gap:0.75rem;">
                            <button id="crm-mailchimp-batch-btn" class="button button-primary">Batch synchronisatie</button>
                            <span id="crm-mailchimp-batch-status" style="color:#666;font-size:0.9rem;"></span>
                        </div>
                        <div id="crm-mailchimp-batch-progress" style="display:none;margin-top:0.5rem;">
                            <div class="crm-scan-progress-bar">
                                <div class="crm-scan-progress-fill" id="crm-mailchimp-batch-fill" style="width:0%"></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="crm-empty" style="text-align:center;padding:1rem;">Configureer eerst je API-key om te synchroniseren.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private static function get_webshop_platform_counts() {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_dealers';
        $results = $wpdb->get_results(
            "SELECT webshop_platform, COUNT(*) as cnt FROM {$table}
             WHERE webshop_platform IS NOT NULL AND webshop_platform != ''
             GROUP BY webshop_platform ORDER BY cnt DESC"
        );
        $counts = [];
        foreach ($results as $row) {
            $counts[$row->webshop_platform] = (int) $row->cnt;
        }
        return $counts;
    }

    public static function page_duplicates() {
        $duplicates = DealerCRM_Duplicates::find_duplicates(50);
        $dup_count = DealerCRM_Duplicates::count_duplicates();
        $base_url = admin_url('admin.php?page=dealer-crm');
        $is_admin = current_user_can('manage_options');
        ?>
        <div class="wrap dealer-crm-wrap">
            <h1>Duplicaten detectie</h1>
            <p class="description" style="margin-bottom:1rem;">Hieronder staan mogelijke dubbele dealers op basis van overeenkomend e-mailadres, telefoonnummer of vergelijkbare naam.</p>

            <?php if (empty($duplicates)): ?>
                <div class="crm-card">
                    <p class="crm-empty" style="text-align:center;padding:2rem;">Geen mogelijke duplicaten gevonden.</p>
                </div>
            <?php else: ?>
                <?php if ($is_admin): ?>
                <div class="crm-card" style="display:flex;align-items:center;gap:1rem;padding:0.75rem 1.25rem;margin-bottom:1rem;">
                    <button id="crm-auto-merge-btn" class="button button-primary">Alles automatisch samenvoegen</button>
                    <span id="crm-auto-merge-status" style="color:#666;font-size:0.9rem;">
                        <?php echo $dup_count; ?> duplicaten gevonden
                    </span>
                    <div id="crm-auto-merge-progress" style="display:none;flex:1;">
                        <div class="crm-scan-progress-bar">
                            <div class="crm-scan-progress-fill" id="crm-auto-merge-fill" style="width:0%"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="crm-duplicates-grid">
                    <?php foreach ($duplicates as $dup):
                        $merge_url = admin_url('admin.php?page=dealer-crm&action=merge&id=' . $dup->id1 . '&merge_with=' . $dup->id2);
                        $view_url_1 = add_query_arg(['action' => 'view', 'id' => $dup->id1], $base_url);
                        $view_url_2 = add_query_arg(['action' => 'view', 'id' => $dup->id2], $base_url);
                    ?>
                        <div class="crm-card crm-duplicate-card" data-id1="<?php echo $dup->id1; ?>" data-id2="<?php echo $dup->id2; ?>">
                            <div class="crm-duplicate-reason">
                                <span class="crm-duplicate-reason-badge"><?php echo esc_html($dup->reason); ?></span>
                                <span class="crm-duplicate-match"><?php echo esc_html($dup->match); ?></span>
                            </div>
                            <div class="crm-duplicate-pair">
                                <div class="crm-duplicate-dealer">
                                    <a href="<?php echo esc_url($view_url_1); ?>" class="crm-dealer-name"><?php echo esc_html($dup->name1); ?></a>
                                    <div class="crm-duplicate-details">
                                        <?php if ($dup->city1): ?><span><?php echo esc_html($dup->city1); ?></span><?php endif; ?>
                                        <?php if ($dup->phone1): ?><span><?php echo esc_html($dup->phone1); ?></span><?php endif; ?>
                                        <?php if ($dup->email1): ?><span><?php echo esc_html($dup->email1); ?></span><?php endif; ?>
                                    </div>
                                </div>
                                <div class="crm-duplicate-separator">
                                    <span class="dashicons dashicons-leftright"></span>
                                </div>
                                <div class="crm-duplicate-dealer">
                                    <a href="<?php echo esc_url($view_url_2); ?>" class="crm-dealer-name"><?php echo esc_html($dup->name2); ?></a>
                                    <div class="crm-duplicate-details">
                                        <?php if ($dup->city2): ?><span><?php echo esc_html($dup->city2); ?></span><?php endif; ?>
                                        <?php if ($dup->phone2): ?><span><?php echo esc_html($dup->phone2); ?></span><?php endif; ?>
                                        <?php if ($dup->email2): ?><span><?php echo esc_html($dup->email2); ?></span><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="crm-duplicate-actions">
                                <a href="<?php echo esc_url($merge_url); ?>" class="button button-primary button-small">Samenvoegen</a>
                                <button class="button button-small crm-dismiss-duplicate-btn" data-id1="<?php echo $dup->id1; ?>" data-id2="<?php echo $dup->id2; ?>">Negeren</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
