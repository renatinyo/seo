<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Internal Link Suggestions
 *
 * Suggests relevant internal links based on content analysis.
 */
class RSEO_Link_Suggestions {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Add link suggestions to metabox
        add_action( 'add_meta_boxes', [ $this, 'add_link_suggestions_metabox' ] );

        // AJAX handlers
        add_action( 'wp_ajax_rseo_get_link_suggestions', [ $this, 'ajax_get_suggestions' ] );
        add_action( 'wp_ajax_rseo_search_posts_for_link', [ $this, 'ajax_search_posts' ] );

        // Enqueue scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    /**
     * Add metabox for link suggestions
     */
    public function add_link_suggestions_metabox() {
        $post_types = get_post_types( [ 'public' => true ], 'names' );

        foreach ( $post_types as $pt ) {
            if ( $pt === 'attachment' ) continue;

            add_meta_box(
                'rseo_link_suggestions',
                '🔗 Belső link javaslatok',
                [ $this, 'render_metabox' ],
                $pt,
                'side',
                'default'
            );
        }
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ] ) ) return;

        wp_enqueue_style( 'rseo-link-suggestions', RSEO_PLUGIN_URL . 'admin/css/link-suggestions.css', [], RSEO_VERSION );
        wp_enqueue_script( 'rseo-link-suggestions', RSEO_PLUGIN_URL . 'admin/js/link-suggestions.js', [ 'jquery' ], RSEO_VERSION, true );

        global $post;
        wp_localize_script( 'rseo-link-suggestions', 'rseoLinks', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'rseo_links_nonce' ),
            'postId'  => $post ? $post->ID : 0,
            'strings' => [
                'loading'     => 'Javaslatok keresése...',
                'noResults'   => 'Nincs releváns javaslat',
                'copied'      => 'Link kimásolva!',
                'searchPlaceholder' => 'Keresés a bejegyzésekben...',
            ],
        ]);
    }

    /**
     * Render metabox
     */
    public function render_metabox( $post ) {
        ?>
        <div class="rseo-link-suggestions-wrap">
            <p class="rseo-link-description">
                Releváns belső oldalak, amiket érdemes belinkelned:
            </p>

            <div id="rseo-link-suggestions-list" class="rseo-link-list">
                <p class="rseo-loading">Javaslatok keresése...</p>
            </div>

            <div class="rseo-link-search-wrap">
                <input type="text" id="rseo-link-search" placeholder="Keresés a bejegyzésekben...">
                <div id="rseo-link-search-results" class="rseo-link-list" style="display:none;"></div>
            </div>

            <button type="button" class="button" id="rseo-refresh-suggestions">
                🔄 Frissítés
            </button>
        </div>
        <?php
    }

    /**
     * Get link suggestions for a post
     */
    public function get_suggestions( $post_id, $limit = 5 ) {
        $post = get_post( $post_id );
        if ( ! $post ) return [];

        // Get current post keywords
        $focus_keyword = get_post_meta( $post_id, '_rseo_focus_keyword', true );
        $title = $post->post_title;
        $content = wp_strip_all_tags( $post->post_content );

        // Build search terms from title and focus keyword
        $search_terms = [];

        if ( $focus_keyword ) {
            $search_terms[] = $focus_keyword;
        }

        // Extract important words from title (exclude stopwords)
        $title_words = $this->extract_keywords( $title );
        $search_terms = array_merge( $search_terms, array_slice( $title_words, 0, 3 ) );

        // Get already linked posts
        $already_linked = $this->get_linked_post_ids( $post->post_content, $post_id );

        // Search for relevant posts
        $suggestions = [];
        $seen_ids = [ $post_id ];

        foreach ( $search_terms as $term ) {
            if ( empty( trim( $term ) ) ) continue;

            $args = [
                'post_type'      => [ 'post', 'page' ],
                'post_status'    => 'publish',
                'posts_per_page' => 10,
                's'              => $term,
                'post__not_in'   => $seen_ids,
            ];

            $query = new WP_Query( $args );

            foreach ( $query->posts as $result ) {
                if ( in_array( $result->ID, $seen_ids ) ) continue;
                if ( count( $suggestions ) >= $limit ) break 2;

                $seen_ids[] = $result->ID;

                $suggestions[] = [
                    'id'         => $result->ID,
                    'title'      => $result->post_title,
                    'url'        => get_permalink( $result->ID ),
                    'post_type'  => $result->post_type,
                    'match_term' => $term,
                    'is_linked'  => in_array( $result->ID, $already_linked ),
                ];
            }
        }

        // If not enough suggestions, add recent posts
        if ( count( $suggestions ) < $limit ) {
            $recent = get_posts( [
                'post_type'      => [ 'post', 'page' ],
                'post_status'    => 'publish',
                'posts_per_page' => $limit - count( $suggestions ),
                'post__not_in'   => $seen_ids,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ] );

            foreach ( $recent as $result ) {
                $suggestions[] = [
                    'id'         => $result->ID,
                    'title'      => $result->post_title,
                    'url'        => get_permalink( $result->ID ),
                    'post_type'  => $result->post_type,
                    'match_term' => '',
                    'is_linked'  => in_array( $result->ID, $already_linked ),
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Extract keywords from text
     */
    private function extract_keywords( $text ) {
        $text = mb_strtolower( $text );
        $words = preg_split( '/\s+/', $text );

        // Hungarian stopwords
        $stopwords = [
            'a', 'az', 'és', 'vagy', 'de', 'hogy', 'nem', 'is', 'már', 'még',
            'csak', 'meg', 'el', 'ki', 'be', 'fel', 'le', 'át', 'rá', 'ide',
            'egy', 'ez', 'azt', 'ami', 'aki', 'amely', 'ők', 'mi', 'ti', 'én',
            'te', 'ő', 'van', 'volt', 'lesz', 'lett', 'lenne', 'legyen', 'nincs',
            'mint', 'után', 'előtt', 'között', 'alatt', 'felett', 'mellett',
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
            'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been',
            'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
            'could', 'should', 'may', 'might', 'must', 'shall', 'can', 'need',
        ];

        $keywords = [];
        foreach ( $words as $word ) {
            $word = trim( $word, '.,!?;:"\'' );
            if ( mb_strlen( $word ) >= 3 && ! in_array( $word, $stopwords ) ) {
                $keywords[] = $word;
            }
        }

        return array_unique( $keywords );
    }

    /**
     * Get IDs of posts already linked in content
     */
    private function get_linked_post_ids( $content, $current_post_id ) {
        $ids = [];
        $home_url = home_url();

        preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );

        foreach ( $matches[1] as $url ) {
            // Check if internal link
            if ( strpos( $url, $home_url ) === 0 || strpos( $url, '/' ) === 0 ) {
                $post_id = url_to_postid( $url );
                if ( $post_id && $post_id !== $current_post_id ) {
                    $ids[] = $post_id;
                }
            }
        }

        return array_unique( $ids );
    }

    /**
     * AJAX: Get suggestions
     */
    public function ajax_get_suggestions() {
        check_ajax_referer( 'rseo_links_nonce', 'nonce' );

        $post_id = intval( $_POST['post_id'] );

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( 'Érvénytelen kérés.' );
        }

        $suggestions = $this->get_suggestions( $post_id );
        wp_send_json_success( $suggestions );
    }

    /**
     * AJAX: Search posts for linking
     */
    public function ajax_search_posts() {
        check_ajax_referer( 'rseo_links_nonce', 'nonce' );

        $search = sanitize_text_field( $_POST['search'] );
        $current_post_id = intval( $_POST['post_id'] );

        if ( mb_strlen( $search ) < 2 ) {
            wp_send_json_success( [] );
        }

        $args = [
            'post_type'      => [ 'post', 'page' ],
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            's'              => $search,
            'post__not_in'   => [ $current_post_id ],
        ];

        $query = new WP_Query( $args );
        $results = [];

        foreach ( $query->posts as $post ) {
            $results[] = [
                'id'        => $post->ID,
                'title'     => $post->post_title,
                'url'       => get_permalink( $post->ID ),
                'post_type' => $post->post_type,
            ];
        }

        wp_send_json_success( $results );
    }
}

// Initialize
RSEO_Link_Suggestions::instance();
