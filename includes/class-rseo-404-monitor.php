<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 404 Monitor
 *
 * Logs 404 errors and provides quick redirect creation.
 */
class RSEO_404_Monitor {

    const TABLE_NAME = 'rseo_404_log';
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

        // Log 404 errors
        add_action( 'template_redirect', [ $this, 'log_404' ], 99 );

        // Admin hooks
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'check_db_version' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

        // AJAX handlers
        add_action( 'wp_ajax_rseo_delete_404', [ $this, 'ajax_delete_404' ] );
        add_action( 'wp_ajax_rseo_delete_all_404', [ $this, 'ajax_delete_all_404' ] );
        add_action( 'wp_ajax_rseo_create_redirect_from_404', [ $this, 'ajax_create_redirect' ] );

        // Cleanup cron
        add_action( 'rseo_cleanup_404_log', [ $this, 'cleanup_old_entries' ] );
        if ( ! wp_next_scheduled( 'rseo_cleanup_404_log' ) ) {
            wp_schedule_event( time(), 'daily', 'rseo_cleanup_404_log' );
        }
    }

    /**
     * Create database table
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            url VARCHAR(500) NOT NULL,
            referrer VARCHAR(500) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            ip_hash VARCHAR(64) DEFAULT NULL,
            hit_count INT(11) NOT NULL DEFAULT 1,
            first_hit DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_hit DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_resolved TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY url (url(191)),
            KEY hit_count (hit_count),
            KEY last_hit (last_hit)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        update_option( 'rseo_404_db_version', self::DB_VERSION );
    }

    /**
     * Check and update DB if needed
     */
    public function check_db_version() {
        if ( get_option( 'rseo_404_db_version' ) !== self::DB_VERSION ) {
            self::create_table();
        }
    }

    /**
     * Log 404 error
     */
    public function log_404() {
        if ( ! is_404() ) return;
        if ( is_admin() ) return;

        // Skip bots and crawlers for common asset extensions
        $url = $_SERVER['REQUEST_URI'];
        $skip_extensions = [ '.css', '.js', '.jpg', '.jpeg', '.png', '.gif', '.ico', '.svg', '.woff', '.woff2', '.ttf', '.map' ];
        foreach ( $skip_extensions as $ext ) {
            if ( stripos( $url, $ext ) !== false ) return;
        }

        global $wpdb;

        $url = esc_url_raw( $url );
        $referrer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : null;
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( substr( $_SERVER['HTTP_USER_AGENT'], 0, 500 ) ) : null;
        $ip_hash = hash( 'sha256', $_SERVER['REMOTE_ADDR'] . wp_salt() );

        // Check if URL already logged
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, hit_count FROM {$this->table_name} WHERE url = %s",
            $url
        ) );

        if ( $existing ) {
            // Update hit count
            $wpdb->update(
                $this->table_name,
                [
                    'hit_count' => $existing->hit_count + 1,
                    'last_hit'  => current_time( 'mysql' ),
                    'referrer'  => $referrer ?: $wpdb->get_var( $wpdb->prepare( "SELECT referrer FROM {$this->table_name} WHERE id = %d", $existing->id ) ),
                ],
                [ 'id' => $existing->id ],
                [ '%d', '%s', '%s' ],
                [ '%d' ]
            );
        } else {
            // Insert new
            $wpdb->insert(
                $this->table_name,
                [
                    'url'        => $url,
                    'referrer'   => $referrer,
                    'user_agent' => $user_agent,
                    'ip_hash'    => $ip_hash,
                    'first_hit'  => current_time( 'mysql' ),
                    'last_hit'   => current_time( 'mysql' ),
                ],
                [ '%s', '%s', '%s', '%s', '%s', '%s' ]
            );
        }
    }

    /**
     * Cleanup old entries (older than 30 days)
     */
    public function cleanup_old_entries() {
        global $wpdb;

        $days = apply_filters( 'rseo_404_retention_days', 30 );

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE last_hit < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'rendanit-seo',
            '404 Monitor',
            '🚫 404 Monitor',
            'manage_options',
            'rseo-404-monitor',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( $hook !== 'rendanit-seo_page_rseo-404-monitor' ) return;

        wp_enqueue_style( 'rseo-404-monitor', RSEO_PLUGIN_URL . 'admin/css/404-monitor.css', [], RSEO_VERSION );
        wp_enqueue_script( 'rseo-404-monitor', RSEO_PLUGIN_URL . 'admin/js/404-monitor.js', [ 'jquery' ], RSEO_VERSION, true );
        wp_localize_script( 'rseo-404-monitor', 'rseo404', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'rseo_404_nonce' ),
            'strings' => [
                'confirmDelete'    => 'Biztosan törölni szeretnéd ezt a bejegyzést?',
                'confirmDeleteAll' => 'Biztosan törölni szeretnéd az ÖSSZES 404 bejegyzést?',
            ],
        ]);
    }

    /**
     * Get 404 logs
     */
    public function get_logs( $args = [] ) {
        global $wpdb;

        $defaults = [
            'per_page' => 50,
            'page'     => 1,
            'orderby'  => 'hit_count',
            'order'    => 'DESC',
            'search'   => '',
            'resolved' => null,
        ];

        $args = wp_parse_args( $args, $defaults );
        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        $where = '1=1';
        if ( $args['search'] ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where .= $wpdb->prepare( " AND (url LIKE %s OR referrer LIKE %s)", $search, $search );
        }
        if ( $args['resolved'] !== null ) {
            $where .= $wpdb->prepare( " AND is_resolved = %d", $args['resolved'] );
        }

        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
        if ( ! $orderby ) $orderby = 'hit_count DESC';

        return $wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY {$orderby} LIMIT {$args['per_page']} OFFSET {$offset}"
        );
    }

    /**
     * Get total count
     */
    public function get_total_count( $search = '', $resolved = null ) {
        global $wpdb;

        $where = '1=1';
        if ( $search ) {
            $search = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= $wpdb->prepare( " AND (url LIKE %s OR referrer LIKE %s)", $search, $search );
        }
        if ( $resolved !== null ) {
            $where .= $wpdb->prepare( " AND is_resolved = %d", $resolved );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}" );
    }

    /**
     * AJAX: Delete single 404
     */
    public function ajax_delete_404() {
        check_ajax_referer( 'rseo_404_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Nincs jogosultságod.' );
        }

        global $wpdb;
        $id = intval( $_POST['id'] );

        $result = $wpdb->delete( $this->table_name, [ 'id' => $id ], [ '%d' ] );

        if ( $result ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( 'Hiba a törléskor.' );
        }
    }

    /**
     * AJAX: Delete all 404s
     */
    public function ajax_delete_all_404() {
        check_ajax_referer( 'rseo_404_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Nincs jogosultságod.' );
        }

        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );

        wp_send_json_success();
    }

    /**
     * AJAX: Create redirect from 404
     */
    public function ajax_create_redirect() {
        check_ajax_referer( 'rseo_404_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Nincs jogosultságod.' );
        }

        $id = intval( $_POST['id'] );
        $source = sanitize_text_field( $_POST['source'] );
        $target = sanitize_text_field( $_POST['target'] );

        if ( empty( $source ) || empty( $target ) ) {
            wp_send_json_error( 'Forrás és cél URL megadása kötelező.' );
        }

        // Create redirect using RSEO_Redirects
        if ( class_exists( 'RSEO_Redirects' ) ) {
            $redirects = RSEO_Redirects::instance();
            $result = $redirects->add_redirect( $source, $target, 301, false, 'Auto: 404 monitor' );

            if ( $result ) {
                // Mark as resolved
                global $wpdb;
                $wpdb->update(
                    $this->table_name,
                    [ 'is_resolved' => 1 ],
                    [ 'id' => $id ],
                    [ '%d' ],
                    [ '%d' ]
                );

                wp_send_json_success( [ 'message' => 'Átirányítás létrehozva!' ] );
            }
        }

        wp_send_json_error( 'Hiba az átirányítás létrehozásakor.' );
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        global $wpdb;

        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $per_page = 50;
        $filter = isset( $_GET['filter'] ) ? sanitize_text_field( $_GET['filter'] ) : '';

        $resolved = null;
        if ( $filter === 'resolved' ) $resolved = 1;
        if ( $filter === 'unresolved' ) $resolved = 0;

        $logs = $this->get_logs( [
            'search'   => $search,
            'page'     => $page,
            'per_page' => $per_page,
            'resolved' => $resolved,
        ] );

        $total = $this->get_total_count( $search, $resolved );
        $total_pages = ceil( $total / $per_page );

        // Stats
        $total_all = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
        $total_hits = $wpdb->get_var( "SELECT SUM(hit_count) FROM {$this->table_name}" );
        $total_unresolved = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE is_resolved = 0" );
        $top_404 = $wpdb->get_row( "SELECT url, hit_count FROM {$this->table_name} ORDER BY hit_count DESC LIMIT 1" );

        ?>
        <div class="wrap rseo-404-wrap">
            <h1>🚫 404 Monitor</h1>
            <p class="description">Nem található oldalak naplózása. Hozz létre átirányítást egy kattintással!</p>

            <!-- Stats -->
            <div class="rseo-404-stats">
                <div class="rseo-stat-card">
                    <span class="rseo-stat-number"><?php echo number_format( $total_all ?: 0 ); ?></span>
                    <span class="rseo-stat-label">Egyedi 404 URL</span>
                </div>
                <div class="rseo-stat-card">
                    <span class="rseo-stat-number"><?php echo number_format( $total_hits ?: 0 ); ?></span>
                    <span class="rseo-stat-label">Összes találat</span>
                </div>
                <div class="rseo-stat-card rseo-stat-warning">
                    <span class="rseo-stat-number"><?php echo number_format( $total_unresolved ?: 0 ); ?></span>
                    <span class="rseo-stat-label">Megoldatlan</span>
                </div>
                <?php if ( $top_404 ) : ?>
                <div class="rseo-stat-card rseo-stat-danger">
                    <span class="rseo-stat-number"><?php echo number_format( $top_404->hit_count ); ?>x</span>
                    <span class="rseo-stat-label" title="<?php echo esc_attr( $top_404->url ); ?>">Leggyakoribb</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Filters & Search -->
            <div class="rseo-404-toolbar">
                <div class="rseo-filter-buttons">
                    <a href="<?php echo admin_url( 'admin.php?page=rseo-404-monitor' ); ?>"
                       class="button <?php echo ! $filter ? 'button-primary' : ''; ?>">Mind</a>
                    <a href="<?php echo admin_url( 'admin.php?page=rseo-404-monitor&filter=unresolved' ); ?>"
                       class="button <?php echo $filter === 'unresolved' ? 'button-primary' : ''; ?>">Megoldatlan</a>
                    <a href="<?php echo admin_url( 'admin.php?page=rseo-404-monitor&filter=resolved' ); ?>"
                       class="button <?php echo $filter === 'resolved' ? 'button-primary' : ''; ?>">Megoldott</a>
                </div>

                <form method="get" class="rseo-search-form">
                    <input type="hidden" name="page" value="rseo-404-monitor">
                    <?php if ( $filter ) : ?>
                        <input type="hidden" name="filter" value="<?php echo esc_attr( $filter ); ?>">
                    <?php endif; ?>
                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Keresés...">
                    <button type="submit" class="button">Keresés</button>
                </form>

                <div class="rseo-bulk-actions">
                    <button type="button" class="button rseo-delete-all-404">🗑️ Összes törlése</button>
                </div>
            </div>

            <!-- Table -->
            <table class="wp-list-table widefat fixed striped rseo-404-table">
                <thead>
                    <tr>
                        <th class="column-url">404 URL</th>
                        <th class="column-referrer">Hivatkozó</th>
                        <th class="column-hits">Találatok</th>
                        <th class="column-last">Utolsó</th>
                        <th class="column-status">Állapot</th>
                        <th class="column-actions">Műveletek</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $logs ) ) : ?>
                        <tr>
                            <td colspan="6" class="rseo-no-data">
                                <?php if ( $search || $filter ) : ?>
                                    Nincs találat a szűrési feltételeknek megfelelően.
                                <?php else : ?>
                                    Még nincs 404 hiba naplózva. Ez jó hír!
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $logs as $log ) : ?>
                            <tr class="<?php echo $log->is_resolved ? 'rseo-resolved' : ''; ?>" data-id="<?php echo $log->id; ?>">
                                <td class="column-url">
                                    <code><?php echo esc_html( $log->url ); ?></code>
                                    <a href="<?php echo esc_url( home_url( $log->url ) ); ?>" target="_blank" class="rseo-test-link" title="Tesztelés">↗</a>
                                </td>
                                <td class="column-referrer">
                                    <?php if ( $log->referrer ) : ?>
                                        <a href="<?php echo esc_url( $log->referrer ); ?>" target="_blank" title="<?php echo esc_attr( $log->referrer ); ?>">
                                            <?php echo esc_html( wp_parse_url( $log->referrer, PHP_URL_HOST ) ?: $log->referrer ); ?>
                                        </a>
                                    <?php else : ?>
                                        <span class="rseo-no-referrer">Közvetlen</span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-hits">
                                    <span class="rseo-hit-count <?php echo $log->hit_count >= 10 ? 'rseo-high' : ''; ?>">
                                        <?php echo number_format( $log->hit_count ); ?>
                                    </span>
                                </td>
                                <td class="column-last">
                                    <?php echo human_time_diff( strtotime( $log->last_hit ) ); ?>
                                    <br><small><?php echo date_i18n( 'Y.m.d H:i', strtotime( $log->last_hit ) ); ?></small>
                                </td>
                                <td class="column-status">
                                    <?php if ( $log->is_resolved ) : ?>
                                        <span class="rseo-status rseo-status-resolved">✅ Megoldva</span>
                                    <?php else : ?>
                                        <span class="rseo-status rseo-status-pending">⚠️ Várakozik</span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-actions">
                                    <?php if ( ! $log->is_resolved ) : ?>
                                        <button type="button" class="button button-small rseo-create-redirect-404"
                                                data-url="<?php echo esc_attr( $log->url ); ?>"
                                                title="Átirányítás létrehozása">
                                            ↪️
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="button button-small rseo-delete-404" title="Törlés">
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
                            echo paginate_links( [
                                'base'      => add_query_arg( 'paged', '%#%' ),
                                'format'    => '',
                                'current'   => $page,
                                'total'     => $total_pages,
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                            ] );
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Redirect Modal -->
            <div id="rseo-redirect-modal" class="rseo-modal" style="display:none;">
                <div class="rseo-modal-content">
                    <h3>↪️ Átirányítás létrehozása</h3>
                    <form id="rseo-404-redirect-form">
                        <input type="hidden" name="id" value="">
                        <p>
                            <label>Forrás URL (404):</label>
                            <input type="text" name="source" readonly class="widefat">
                        </p>
                        <p>
                            <label>Cél URL:</label>
                            <input type="text" name="target" class="widefat" placeholder="/uj-oldal vagy https://...">
                        </p>
                        <p class="rseo-modal-buttons">
                            <button type="submit" class="button button-primary">Létrehozás</button>
                            <button type="button" class="button rseo-modal-close">Mégse</button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialize
RSEO_404_Monitor::instance();
