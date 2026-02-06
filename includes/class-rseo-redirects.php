<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Redirect Manager
 *
 * Handles 301/302 redirects with automatic slug change detection.
 * Prevents 404 errors when URLs change.
 */
class RSEO_Redirects {

    const TABLE_NAME = 'rseo_redirects';
    const DB_VERSION = '1.0';

    private static $instance = null;
    private $table_name;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . self::TABLE_NAME;

        // Admin hooks
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'check_db_version' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

        // Slug change detection
        add_action( 'post_updated', [ $this, 'detect_slug_change' ], 10, 3 );

        // Frontend redirect execution
        add_action( 'template_redirect', [ $this, 'maybe_redirect' ], 1 );

        // AJAX handlers
        add_action( 'wp_ajax_rseo_add_redirect', [ $this, 'ajax_add_redirect' ] );
        add_action( 'wp_ajax_rseo_delete_redirect', [ $this, 'ajax_delete_redirect' ] );
        add_action( 'wp_ajax_rseo_toggle_redirect', [ $this, 'ajax_toggle_redirect' ] );
        add_action( 'wp_ajax_rseo_dismiss_slug_notice', [ $this, 'ajax_dismiss_slug_notice' ] );
        add_action( 'wp_ajax_rseo_create_redirect_from_notice', [ $this, 'ajax_create_redirect_from_notice' ] );

        // Admin notice for pending slug changes
        add_action( 'admin_notices', [ $this, 'show_slug_change_notice' ] );
    }

    /**
     * Create database table on activation
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            source_url VARCHAR(500) NOT NULL,
            target_url VARCHAR(500) NOT NULL,
            redirect_type INT(3) NOT NULL DEFAULT 301,
            is_regex TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            hits INT(11) NOT NULL DEFAULT 0,
            last_hit DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT(20) UNSIGNED DEFAULT NULL,
            notes VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY source_url (source_url(191)),
            KEY is_active (is_active)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        update_option( 'rseo_redirects_db_version', self::DB_VERSION );
    }

    /**
     * Check and update DB if needed
     */
    public function check_db_version() {
        if ( get_option( 'rseo_redirects_db_version' ) !== self::DB_VERSION ) {
            self::create_table();
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'rendanit-seo',
            'Átirányítások',
            '↪️ Átirányítások',
            'manage_options',
            'rseo-redirects',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( $hook !== 'rendanit-seo_page_rseo-redirects' && $hook !== 'post.php' && $hook !== 'post-new.php' ) {
            return;
        }

        wp_enqueue_style( 'rseo-redirects', RSEO_PLUGIN_URL . 'admin/css/redirects.css', [], RSEO_VERSION );
        wp_enqueue_script( 'rseo-redirects', RSEO_PLUGIN_URL . 'admin/js/redirects.js', [ 'jquery' ], RSEO_VERSION, true );
        wp_localize_script( 'rseo-redirects', 'rseoRedirects', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'rseo_redirects_nonce' ),
            'strings' => [
                'confirmDelete' => 'Biztosan törölni szeretnéd ezt az átirányítást?',
                'added'         => 'Átirányítás hozzáadva!',
                'deleted'       => 'Átirányítás törölve!',
                'error'         => 'Hiba történt. Próbáld újra!',
            ],
        ]);
    }

    /**
     * Detect slug change on post update
     */
    public function detect_slug_change( $post_id, $post_after, $post_before ) {
        // Skip autosaves and revisions
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( $post_after->post_status !== 'publish' ) return;
        if ( $post_before->post_status !== 'publish' ) return;

        $old_slug = $post_before->post_name;
        $new_slug = $post_after->post_name;

        // No change
        if ( $old_slug === $new_slug ) return;

        // Build old and new URLs
        $old_url = $this->get_post_url_with_slug( $post_id, $old_slug, $post_after->post_type );
        $new_url = get_permalink( $post_id );

        // Store pending redirect for user confirmation
        $pending = get_option( 'rseo_pending_redirects', [] );
        $pending[ $post_id ] = [
            'old_url'    => $old_url,
            'new_url'    => $new_url,
            'old_slug'   => $old_slug,
            'new_slug'   => $new_slug,
            'post_title' => $post_after->post_title,
            'detected'   => time(),
        ];
        update_option( 'rseo_pending_redirects', $pending );
    }

    /**
     * Build URL with specific slug
     */
    private function get_post_url_with_slug( $post_id, $slug, $post_type ) {
        $post = get_post( $post_id );

        // Temporarily change slug to build old URL
        $current_slug = $post->post_name;

        // Get permalink structure
        $permalink = get_permalink( $post_id );

        // Replace new slug with old slug in URL
        $old_url = str_replace( '/' . $current_slug . '/', '/' . $slug . '/', $permalink );
        $old_url = str_replace( '/' . $current_slug, '/' . $slug, $old_url );

        return $old_url;
    }

    /**
     * Show admin notice for pending slug changes
     */
    public function show_slug_change_notice() {
        $pending = get_option( 'rseo_pending_redirects', [] );

        if ( empty( $pending ) ) return;

        // Only show on relevant pages
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->base, [ 'post', 'edit', 'dashboard' ] ) ) return;

        foreach ( $pending as $post_id => $data ) {
            // Skip if older than 24 hours
            if ( time() - $data['detected'] > 86400 ) {
                unset( $pending[ $post_id ] );
                update_option( 'rseo_pending_redirects', $pending );
                continue;
            }
            ?>
            <div class="notice notice-warning rseo-slug-notice" data-post-id="<?php echo esc_attr( $post_id ); ?>">
                <p>
                    <strong>🔄 URL változás észlelve!</strong><br>
                    A „<?php echo esc_html( $data['post_title'] ); ?>" bejegyzés URL-je megváltozott.<br>
                    <code><?php echo esc_html( $data['old_url'] ); ?></code> → <code><?php echo esc_html( $data['new_url'] ); ?></code>
                </p>
                <p>
                    <button type="button" class="button button-primary rseo-create-redirect"
                            data-post-id="<?php echo esc_attr( $post_id ); ?>"
                            data-old="<?php echo esc_attr( $data['old_url'] ); ?>"
                            data-new="<?php echo esc_attr( $data['new_url'] ); ?>">
                        ✅ 301 átirányítás létrehozása
                    </button>
                    <button type="button" class="button rseo-dismiss-notice" data-post-id="<?php echo esc_attr( $post_id ); ?>">
                        ❌ Elutasítás
                    </button>
                </p>
            </div>
            <?php
        }
    }

    /**
     * AJAX: Create redirect from notice
     */
    public function ajax_create_redirect_from_notice() {
        check_ajax_referer( 'rseo_redirects_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Nincs jogosultságod.' );
        }

        $post_id = intval( $_POST['post_id'] );
        $old_url = esc_url_raw( $_POST['old_url'] );
        $new_url = esc_url_raw( $_POST['new_url'] );

        // Add redirect
        $result = $this->add_redirect( $old_url, $new_url, 301, false, 'Auto: slug változás' );

        if ( $result ) {
            // Remove from pending
            $pending = get_option( 'rseo_pending_redirects', [] );
            unset( $pending[ $post_id ] );
            update_option( 'rseo_pending_redirects', $pending );

            wp_send_json_success( 'Átirányítás létrehozva!' );
        } else {
            wp_send_json_error( 'Hiba az átirányítás létrehozásakor.' );
        }
    }

    /**
     * AJAX: Dismiss slug notice
     */
    public function ajax_dismiss_slug_notice() {
        check_ajax_referer( 'rseo_redirects_nonce', 'nonce' );

        $post_id = intval( $_POST['post_id'] );

        $pending = get_option( 'rseo_pending_redirects', [] );
        unset( $pending[ $post_id ] );
        update_option( 'rseo_pending_redirects', $pending );

        wp_send_json_success();
    }

    /**
     * Execute redirect on frontend
     */
    public function maybe_redirect() {
        if ( is_admin() ) return;

        global $wpdb;

        $current_url = $this->get_current_url();
        $current_path = wp_parse_url( $current_url, PHP_URL_PATH );

        // First try exact match
        $redirect = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE is_active = 1
             AND is_regex = 0
             AND (source_url = %s OR source_url = %s)
             LIMIT 1",
            $current_url,
            $current_path
        ) );

        // If no exact match, try regex
        if ( ! $redirect ) {
            $regex_redirects = $wpdb->get_results(
                "SELECT * FROM {$this->table_name} WHERE is_active = 1 AND is_regex = 1"
            );

            foreach ( $regex_redirects as $r ) {
                $pattern = '~' . $r->source_url . '~i';
                if ( @preg_match( $pattern, $current_path ) ) {
                    $redirect = $r;
                    // Apply regex replacement
                    $redirect->target_url = preg_replace( $pattern, $r->target_url, $current_path );
                    break;
                }
            }
        }

        if ( $redirect ) {
            // Update hit count
            $wpdb->update(
                $this->table_name,
                [
                    'hits' => $redirect->hits + 1,
                    'last_hit' => current_time( 'mysql' ),
                ],
                [ 'id' => $redirect->id ],
                [ '%d', '%s' ],
                [ '%d' ]
            );

            // Perform redirect
            $target = $redirect->target_url;

            // Make absolute URL if relative
            if ( strpos( $target, 'http' ) !== 0 ) {
                $target = home_url( $target );
            }

            wp_redirect( $target, $redirect->redirect_type );
            exit;
        }
    }

    /**
     * Get current URL
     */
    private function get_current_url() {
        $protocol = is_ssl() ? 'https://' : 'http://';
        return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    /**
     * Add a redirect
     */
    public function add_redirect( $source, $target, $type = 301, $is_regex = false, $notes = '' ) {
        global $wpdb;

        // Normalize source URL
        $source = $this->normalize_url( $source );

        // Check if exists
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE source_url = %s",
            $source
        ) );

        if ( $exists ) {
            // Update existing
            return $wpdb->update(
                $this->table_name,
                [
                    'target_url'    => $target,
                    'redirect_type' => $type,
                    'is_regex'      => $is_regex ? 1 : 0,
                    'is_active'     => 1,
                    'notes'         => $notes,
                ],
                [ 'id' => $exists ],
                [ '%s', '%d', '%d', '%d', '%s' ],
                [ '%d' ]
            );
        }

        return $wpdb->insert(
            $this->table_name,
            [
                'source_url'    => $source,
                'target_url'    => $target,
                'redirect_type' => $type,
                'is_regex'      => $is_regex ? 1 : 0,
                'is_active'     => 1,
                'created_by'    => get_current_user_id(),
                'notes'         => $notes,
            ],
            [ '%s', '%s', '%d', '%d', '%d', '%d', '%s' ]
        );
    }

    /**
     * Normalize URL to path only
     */
    private function normalize_url( $url ) {
        // If full URL, extract path
        if ( strpos( $url, 'http' ) === 0 ) {
            $parsed = wp_parse_url( $url );
            $url = isset( $parsed['path'] ) ? $parsed['path'] : '/';
            if ( isset( $parsed['query'] ) ) {
                $url .= '?' . $parsed['query'];
            }
        }

        // Ensure leading slash
        if ( strpos( $url, '/' ) !== 0 ) {
            $url = '/' . $url;
        }

        // Remove trailing slash (except for root)
        if ( $url !== '/' ) {
            $url = rtrim( $url, '/' );
        }

        return $url;
    }

    /**
     * Delete a redirect
     */
    public function delete_redirect( $id ) {
        global $wpdb;
        return $wpdb->delete( $this->table_name, [ 'id' => $id ], [ '%d' ] );
    }

    /**
     * Toggle redirect active state
     */
    public function toggle_redirect( $id ) {
        global $wpdb;

        $current = $wpdb->get_var( $wpdb->prepare(
            "SELECT is_active FROM {$this->table_name} WHERE id = %d",
            $id
        ) );

        return $wpdb->update(
            $this->table_name,
            [ 'is_active' => $current ? 0 : 1 ],
            [ 'id' => $id ],
            [ '%d' ],
            [ '%d' ]
        );
    }

    /**
     * Get all redirects
     */
    public function get_redirects( $args = [] ) {
        global $wpdb;

        $defaults = [
            'per_page' => 50,
            'page'     => 1,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
            'search'   => '',
        ];

        $args = wp_parse_args( $args, $defaults );
        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        $where = '1=1';
        if ( $args['search'] ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where .= $wpdb->prepare( " AND (source_url LIKE %s OR target_url LIKE %s)", $search, $search );
        }

        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );

        return $wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY {$orderby} LIMIT {$args['per_page']} OFFSET {$offset}"
        );
    }

    /**
     * Get total redirect count
     */
    public function get_total_count( $search = '' ) {
        global $wpdb;

        $where = '1=1';
        if ( $search ) {
            $search = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= $wpdb->prepare( " AND (source_url LIKE %s OR target_url LIKE %s)", $search, $search );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}" );
    }

    /**
     * AJAX: Add redirect
     */
    public function ajax_add_redirect() {
        check_ajax_referer( 'rseo_redirects_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Nincs jogosultságod.' );
        }

        $source   = sanitize_text_field( $_POST['source'] );
        $target   = sanitize_text_field( $_POST['target'] );
        $type     = intval( $_POST['type'] );
        $is_regex = isset( $_POST['is_regex'] ) && $_POST['is_regex'] === '1';
        $notes    = sanitize_text_field( $_POST['notes'] ?? '' );

        if ( empty( $source ) || empty( $target ) ) {
            wp_send_json_error( 'Forrás és cél URL megadása kötelező.' );
        }

        if ( ! in_array( $type, [ 301, 302, 307 ] ) ) {
            $type = 301;
        }

        $result = $this->add_redirect( $source, $target, $type, $is_regex, $notes );

        if ( $result ) {
            wp_send_json_success( [ 'message' => 'Átirányítás hozzáadva!' ] );
        } else {
            wp_send_json_error( 'Hiba az átirányítás létrehozásakor.' );
        }
    }

    /**
     * AJAX: Delete redirect
     */
    public function ajax_delete_redirect() {
        check_ajax_referer( 'rseo_redirects_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Nincs jogosultságod.' );
        }

        $id = intval( $_POST['id'] );
        $result = $this->delete_redirect( $id );

        if ( $result ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( 'Hiba a törléskor.' );
        }
    }

    /**
     * AJAX: Toggle redirect
     */
    public function ajax_toggle_redirect() {
        check_ajax_referer( 'rseo_redirects_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Nincs jogosultságod.' );
        }

        $id = intval( $_POST['id'] );
        $result = $this->toggle_redirect( $id );

        if ( $result !== false ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( 'Hiba a váltáskor.' );
        }
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $per_page = 50;

        $redirects = $this->get_redirects( [
            'search'   => $search,
            'page'     => $page,
            'per_page' => $per_page,
        ] );

        $total = $this->get_total_count( $search );
        $total_pages = ceil( $total / $per_page );

        ?>
        <div class="wrap rseo-redirects-wrap">
            <h1>↪️ Átirányítások</h1>
            <p class="description">301/302 átirányítások kezelése. A slug változáskor automatikusan felajánljuk az átirányítás létrehozását.</p>

            <!-- Add New Form -->
            <div class="rseo-redirect-form-wrap">
                <h2>Új átirányítás</h2>
                <form id="rseo-add-redirect-form" class="rseo-redirect-form">
                    <div class="rseo-form-row">
                        <div class="rseo-form-field">
                            <label>Forrás URL</label>
                            <input type="text" name="source" placeholder="/regi-url" required>
                            <span class="description">A régi URL útvonala (pl. /regi-oldal)</span>
                        </div>
                        <div class="rseo-form-field rseo-arrow">→</div>
                        <div class="rseo-form-field">
                            <label>Cél URL</label>
                            <input type="text" name="target" placeholder="/uj-url" required>
                            <span class="description">Az új URL útvonala vagy teljes URL</span>
                        </div>
                        <div class="rseo-form-field rseo-type">
                            <label>Típus</label>
                            <select name="type">
                                <option value="301">301 (Végleges)</option>
                                <option value="302">302 (Ideiglenes)</option>
                                <option value="307">307 (Temp. POST)</option>
                            </select>
                        </div>
                        <div class="rseo-form-field rseo-regex">
                            <label>
                                <input type="checkbox" name="is_regex" value="1">
                                Regex
                            </label>
                        </div>
                        <div class="rseo-form-field rseo-notes">
                            <label>Megjegyzés</label>
                            <input type="text" name="notes" placeholder="opcionális">
                        </div>
                        <div class="rseo-form-field rseo-submit">
                            <button type="submit" class="button button-primary">➕ Hozzáadás</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Search -->
            <form method="get" class="rseo-search-form">
                <input type="hidden" name="page" value="rseo-redirects">
                <p class="search-box">
                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Keresés URL-ben...">
                    <button type="submit" class="button">Keresés</button>
                    <?php if ( $search ) : ?>
                        <a href="<?php echo admin_url( 'admin.php?page=rseo-redirects' ); ?>" class="button">Szűrés törlése</a>
                    <?php endif; ?>
                </p>
            </form>

            <!-- Stats -->
            <div class="rseo-redirect-stats">
                <span class="rseo-stat">
                    <strong><?php echo $total; ?></strong> átirányítás összesen
                </span>
                <?php
                global $wpdb;
                $active_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE is_active = 1" );
                $total_hits = $wpdb->get_var( "SELECT SUM(hits) FROM {$this->table_name}" );
                ?>
                <span class="rseo-stat">
                    <strong><?php echo $active_count; ?></strong> aktív
                </span>
                <span class="rseo-stat">
                    <strong><?php echo number_format( $total_hits ?: 0 ); ?></strong> összes találat
                </span>
            </div>

            <!-- Table -->
            <table class="wp-list-table widefat fixed striped rseo-redirects-table">
                <thead>
                    <tr>
                        <th class="column-source">Forrás URL</th>
                        <th class="column-target">Cél URL</th>
                        <th class="column-type">Típus</th>
                        <th class="column-hits">Találatok</th>
                        <th class="column-status">Állapot</th>
                        <th class="column-date">Létrehozva</th>
                        <th class="column-actions">Műveletek</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $redirects ) ) : ?>
                        <tr>
                            <td colspan="7" class="rseo-no-redirects">
                                Nincs még átirányítás. Hozz létre egyet a fenti űrlappal!
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $redirects as $redirect ) : ?>
                            <tr class="<?php echo $redirect->is_active ? '' : 'rseo-inactive'; ?>" data-id="<?php echo $redirect->id; ?>">
                                <td class="column-source">
                                    <code><?php echo esc_html( $redirect->source_url ); ?></code>
                                    <?php if ( $redirect->is_regex ) : ?>
                                        <span class="rseo-badge rseo-badge-regex">regex</span>
                                    <?php endif; ?>
                                    <?php if ( $redirect->notes ) : ?>
                                        <br><small class="rseo-notes"><?php echo esc_html( $redirect->notes ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="column-target">
                                    <code><?php echo esc_html( $redirect->target_url ); ?></code>
                                    <a href="<?php echo esc_url( $redirect->target_url ); ?>" target="_blank" class="rseo-external-link">↗</a>
                                </td>
                                <td class="column-type">
                                    <span class="rseo-badge rseo-badge-<?php echo $redirect->redirect_type; ?>">
                                        <?php echo $redirect->redirect_type; ?>
                                    </span>
                                </td>
                                <td class="column-hits">
                                    <?php echo number_format( $redirect->hits ); ?>
                                    <?php if ( $redirect->last_hit ) : ?>
                                        <br><small><?php echo human_time_diff( strtotime( $redirect->last_hit ) ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="column-status">
                                    <button type="button" class="rseo-toggle-redirect button-link" title="Kattints az állapot váltásához">
                                        <?php echo $redirect->is_active ? '✅ Aktív' : '⏸️ Inaktív'; ?>
                                    </button>
                                </td>
                                <td class="column-date">
                                    <?php echo date_i18n( 'Y.m.d', strtotime( $redirect->created_at ) ); ?>
                                </td>
                                <td class="column-actions">
                                    <button type="button" class="button button-small rseo-delete-redirect" title="Törlés">
                                        🗑️
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo $total; ?> elem</span>
                        <span class="pagination-links">
                            <?php
                            $pagination_args = [
                                'base'      => add_query_arg( 'paged', '%#%' ),
                                'format'    => '',
                                'current'   => $page,
                                'total'     => $total_pages,
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                            ];
                            echo paginate_links( $pagination_args );
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Import/Export section -->
            <div class="rseo-redirect-import-export">
                <h3>Import / Export</h3>
                <p>
                    <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=rseo-redirects&action=export' ), 'rseo_export_redirects' ); ?>" class="button">
                        📥 CSV Export
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
}

// Initialize
RSEO_Redirects::instance();
