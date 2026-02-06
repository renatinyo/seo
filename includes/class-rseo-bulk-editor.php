<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Bulk Editor
 *
 * Mass edit SEO titles, descriptions, and focus keywords.
 */
class RSEO_Bulk_Editor {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

        // AJAX handlers
        add_action( 'wp_ajax_rseo_bulk_save', [ $this, 'ajax_bulk_save' ] );
        add_action( 'wp_ajax_rseo_bulk_load_posts', [ $this, 'ajax_load_posts' ] );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'rendanit-seo',
            'Tömeges szerkesztő',
            '📝 Tömeges szerkesztő',
            'edit_posts',
            'rseo-bulk-editor',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( $hook !== 'rendanit-seo_page_rseo-bulk-editor' ) return;

        wp_enqueue_style( 'rseo-bulk-editor', RSEO_PLUGIN_URL . 'admin/css/bulk-editor.css', [], RSEO_VERSION );
        wp_enqueue_script( 'rseo-bulk-editor', RSEO_PLUGIN_URL . 'admin/js/bulk-editor.js', [ 'jquery' ], RSEO_VERSION, true );
        wp_localize_script( 'rseo-bulk-editor', 'rseoBulk', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'rseo_bulk_nonce' ),
            'strings' => [
                'saving'  => 'Mentés...',
                'saved'   => 'Mentve!',
                'error'   => 'Hiba történt',
                'confirm' => 'Biztosan el szeretnéd menteni a változtatásokat?',
            ],
        ]);
    }

    /**
     * Get posts for bulk editing
     */
    private function get_posts( $args = [] ) {
        $defaults = [
            'post_type'      => 'post',
            'posts_per_page' => 50,
            'paged'          => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post_status'    => 'publish',
            's'              => '',
            'category'       => '',
            'score_filter'   => '',
        ];

        $args = wp_parse_args( $args, $defaults );

        $query_args = [
            'post_type'      => $args['post_type'],
            'posts_per_page' => $args['posts_per_page'],
            'paged'          => $args['paged'],
            'orderby'        => $args['orderby'],
            'order'          => $args['order'],
            'post_status'    => $args['post_status'],
        ];

        if ( $args['s'] ) {
            $query_args['s'] = $args['s'];
        }

        if ( $args['category'] && $args['post_type'] === 'post' ) {
            $query_args['cat'] = $args['category'];
        }

        $query = new WP_Query( $query_args );
        $posts = [];

        foreach ( $query->posts as $post ) {
            $score_data = class_exists( 'RSEO_Score' ) ? RSEO_Score::get_score( $post->ID ) : [ 'score' => 0 ];

            // Apply score filter
            if ( $args['score_filter'] ) {
                $score = $score_data['score'];
                $matches = false;

                switch ( $args['score_filter'] ) {
                    case 'excellent': $matches = $score >= 80; break;
                    case 'good':      $matches = $score >= 60 && $score < 80; break;
                    case 'fair':      $matches = $score >= 40 && $score < 60; break;
                    case 'poor':      $matches = $score < 40; break;
                }

                if ( ! $matches ) continue;
            }

            $posts[] = [
                'ID'           => $post->ID,
                'post_title'   => $post->post_title,
                'post_type'    => $post->post_type,
                'post_date'    => $post->post_date,
                'edit_link'    => get_edit_post_link( $post->ID, 'raw' ),
                'permalink'    => get_permalink( $post->ID ),
                'seo_title'    => get_post_meta( $post->ID, '_rseo_title', true ),
                'seo_desc'     => get_post_meta( $post->ID, '_rseo_description', true ),
                'focus_kw'     => get_post_meta( $post->ID, '_rseo_focus_keyword', true ),
                'seo_score'    => $score_data['score'],
                'seo_grade'    => $score_data['grade'] ?? 'N/A',
            ];
        }

        return [
            'posts'       => $posts,
            'total'       => $query->found_posts,
            'total_pages' => $query->max_num_pages,
            'current'     => $args['paged'],
        ];
    }

    /**
     * AJAX: Load posts
     */
    public function ajax_load_posts() {
        check_ajax_referer( 'rseo_bulk_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Nincs jogosultságod.' );
        }

        $result = $this->get_posts( [
            'post_type'    => sanitize_text_field( $_POST['post_type'] ?? 'post' ),
            'paged'        => intval( $_POST['page'] ?? 1 ),
            's'            => sanitize_text_field( $_POST['search'] ?? '' ),
            'category'     => intval( $_POST['category'] ?? 0 ),
            'score_filter' => sanitize_text_field( $_POST['score_filter'] ?? '' ),
        ] );

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Bulk save
     */
    public function ajax_bulk_save() {
        check_ajax_referer( 'rseo_bulk_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Nincs jogosultságod.' );
        }

        $changes = json_decode( stripslashes( $_POST['changes'] ), true );

        if ( ! is_array( $changes ) || empty( $changes ) ) {
            wp_send_json_error( 'Nincs mentendő változtatás.' );
        }

        $saved = 0;

        foreach ( $changes as $post_id => $data ) {
            $post_id = intval( $post_id );

            if ( ! current_user_can( 'edit_post', $post_id ) ) continue;

            if ( isset( $data['seo_title'] ) ) {
                update_post_meta( $post_id, '_rseo_title', sanitize_text_field( $data['seo_title'] ) );
            }

            if ( isset( $data['seo_desc'] ) ) {
                update_post_meta( $post_id, '_rseo_description', sanitize_textarea_field( $data['seo_desc'] ) );
            }

            if ( isset( $data['focus_kw'] ) ) {
                update_post_meta( $post_id, '_rseo_focus_keyword', sanitize_text_field( $data['focus_kw'] ) );
            }

            // Invalidate score cache
            if ( class_exists( 'RSEO_Score' ) ) {
                RSEO_Score::invalidate_cache( $post_id );
            }

            $saved++;
        }

        wp_send_json_success( [
            'message' => "{$saved} bejegyzés frissítve!",
            'count'   => $saved,
        ] );
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        $categories = get_categories( [ 'hide_empty' => false ] );

        ?>
        <div class="wrap rseo-bulk-wrap">
            <h1>📝 Tömeges SEO szerkesztő</h1>
            <p class="description">Szerkeszd gyorsan a SEO címeket, leírásokat és kulcsszavakat egy helyen.</p>

            <!-- Filters -->
            <div class="rseo-bulk-filters">
                <div class="rseo-filter-row">
                    <div class="rseo-filter-item">
                        <label>Tartalom típus:</label>
                        <select id="rseo-filter-post-type">
                            <?php foreach ( $post_types as $pt ) : ?>
                                <?php if ( $pt->name === 'attachment' ) continue; ?>
                                <option value="<?php echo esc_attr( $pt->name ); ?>">
                                    <?php echo esc_html( $pt->labels->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="rseo-filter-item rseo-filter-category">
                        <label>Kategória:</label>
                        <select id="rseo-filter-category">
                            <option value="">Mind</option>
                            <?php foreach ( $categories as $cat ) : ?>
                                <option value="<?php echo esc_attr( $cat->term_id ); ?>">
                                    <?php echo esc_html( $cat->name ); ?> (<?php echo $cat->count; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="rseo-filter-item">
                        <label>SEO pontszám:</label>
                        <select id="rseo-filter-score">
                            <option value="">Mind</option>
                            <option value="excellent">Kiváló (80+)</option>
                            <option value="good">Jó (60-79)</option>
                            <option value="fair">Közepes (40-59)</option>
                            <option value="poor">Gyenge (&lt;40)</option>
                        </select>
                    </div>

                    <div class="rseo-filter-item rseo-filter-search">
                        <label>Keresés:</label>
                        <input type="text" id="rseo-filter-search" placeholder="Cím keresése...">
                    </div>

                    <div class="rseo-filter-item rseo-filter-buttons">
                        <button type="button" class="button" id="rseo-apply-filters">Szűrés</button>
                        <button type="button" class="button" id="rseo-reset-filters">Visszaállítás</button>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div class="rseo-bulk-actions">
                <button type="button" class="button button-primary" id="rseo-save-all" disabled>
                    💾 Változtatások mentése (<span class="rseo-change-count">0</span>)
                </button>
                <span class="rseo-bulk-status"></span>
            </div>

            <!-- Table -->
            <div class="rseo-bulk-table-wrap">
                <table class="wp-list-table widefat fixed striped rseo-bulk-table">
                    <thead>
                        <tr>
                            <th class="column-title">Cím</th>
                            <th class="column-seo-title">SEO Title <span class="rseo-char-hint">(60)</span></th>
                            <th class="column-seo-desc">Meta Description <span class="rseo-char-hint">(155)</span></th>
                            <th class="column-focus-kw">Fókusz kulcsszó</th>
                            <th class="column-score">SEO</th>
                        </tr>
                    </thead>
                    <tbody id="rseo-bulk-tbody">
                        <tr class="rseo-loading-row">
                            <td colspan="5">Betöltés...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="rseo-bulk-pagination">
                <button type="button" class="button" id="rseo-prev-page" disabled>« Előző</button>
                <span class="rseo-page-info">Oldal <span id="rseo-current-page">1</span> / <span id="rseo-total-pages">1</span></span>
                <button type="button" class="button" id="rseo-next-page">Következő »</button>
            </div>

            <!-- Export -->
            <div class="rseo-bulk-export">
                <h3>Export</h3>
                <p>
                    <button type="button" class="button" id="rseo-export-csv">📥 CSV Export</button>
                    <span class="description">A jelenlegi szűrési feltételek szerinti bejegyzések exportálása.</span>
                </p>
            </div>
        </div>
        <?php
    }
}

// Initialize
RSEO_Bulk_Editor::instance();
