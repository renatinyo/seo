<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Social Preview
 *
 * Facebook and Twitter preview in metabox.
 * Separate image upload for social platforms.
 */
class RSEO_Social_Preview {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Add social preview to metabox
        add_action( 'rseo_metabox_social_tab', [ $this, 'render_social_preview' ], 10, 1 );

        // Enqueue preview scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

        // Save additional social fields
        add_action( 'save_post', [ $this, 'save_social_meta' ], 10, 2 );

        // AJAX preview update
        add_action( 'wp_ajax_rseo_get_social_preview', [ $this, 'ajax_get_preview' ] );
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ] ) ) return;

        wp_enqueue_media();
        wp_enqueue_style( 'rseo-social-preview', RSEO_PLUGIN_URL . 'admin/css/social-preview.css', [], RSEO_VERSION );
        wp_enqueue_script( 'rseo-social-preview', RSEO_PLUGIN_URL . 'admin/js/social-preview.js', [ 'jquery' ], RSEO_VERSION, true );

        global $post;
        wp_localize_script( 'rseo-social-preview', 'rseoSocial', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'rseo_social_nonce' ),
            'postId'    => $post ? $post->ID : 0,
            'siteUrl'   => wp_parse_url( home_url(), PHP_URL_HOST ),
            'siteName'  => get_bloginfo( 'name' ),
            'defaultOg' => RendanIT_SEO::get_setting( 'og_default_image', '' ),
        ]);
    }

    /**
     * Render social preview section
     */
    public function render_social_preview( $post ) {
        $og_title   = get_post_meta( $post->ID, '_rseo_og_title', true );
        $og_desc    = get_post_meta( $post->ID, '_rseo_og_description', true );
        $og_image   = get_post_meta( $post->ID, '_rseo_og_image', true );
        $tw_image   = get_post_meta( $post->ID, '_rseo_twitter_image', true );
        $tw_card    = get_post_meta( $post->ID, '_rseo_twitter_card', true ) ?: 'summary_large_image';

        $seo_title  = get_post_meta( $post->ID, '_rseo_title', true ) ?: $post->post_title;
        $seo_desc   = get_post_meta( $post->ID, '_rseo_description', true ) ?: wp_trim_words( $post->post_content, 25, '...' );

        $preview_title = $og_title ?: $seo_title;
        $preview_desc  = $og_desc ?: $seo_desc;
        $preview_image = $og_image ?: get_the_post_thumbnail_url( $post->ID, 'large' ) ?: RendanIT_SEO::get_setting( 'og_default_image' );
        $preview_url   = wp_parse_url( get_permalink( $post->ID ), PHP_URL_HOST );

        ?>
        <div class="rseo-social-preview-wrap">

            <!-- Facebook Preview -->
            <div class="rseo-social-section">
                <h4>📘 Facebook előnézet</h4>
                <div class="rseo-fb-preview" id="rseo-fb-preview">
                    <div class="rseo-fb-card">
                        <div class="rseo-fb-image" id="rseo-fb-image" style="<?php echo $preview_image ? "background-image: url('{$preview_image}');" : ''; ?>">
                            <?php if ( ! $preview_image ) : ?>
                                <span class="rseo-no-image">Nincs kép</span>
                            <?php endif; ?>
                        </div>
                        <div class="rseo-fb-content">
                            <div class="rseo-fb-url" id="rseo-fb-url"><?php echo esc_html( $preview_url ); ?></div>
                            <div class="rseo-fb-title" id="rseo-fb-title"><?php echo esc_html( $preview_title ); ?></div>
                            <div class="rseo-fb-desc" id="rseo-fb-desc"><?php echo esc_html( mb_substr( $preview_desc, 0, 150 ) ); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Twitter Preview -->
            <div class="rseo-social-section">
                <h4>🐦 Twitter előnézet</h4>
                <div class="rseo-tw-preview" id="rseo-tw-preview">
                    <div class="rseo-tw-card rseo-tw-<?php echo esc_attr( $tw_card ); ?>">
                        <div class="rseo-tw-image" id="rseo-tw-image" style="<?php echo $preview_image ? "background-image: url('{$preview_image}');" : ''; ?>">
                            <?php if ( ! $preview_image ) : ?>
                                <span class="rseo-no-image">Nincs kép</span>
                            <?php endif; ?>
                        </div>
                        <div class="rseo-tw-content">
                            <div class="rseo-tw-title" id="rseo-tw-title"><?php echo esc_html( $preview_title ); ?></div>
                            <div class="rseo-tw-desc" id="rseo-tw-desc"><?php echo esc_html( mb_substr( $preview_desc, 0, 120 ) ); ?></div>
                            <div class="rseo-tw-url" id="rseo-tw-url"><?php echo esc_html( $preview_url ); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Social-specific settings -->
            <div class="rseo-social-section rseo-social-settings">
                <h4>⚙️ Platform-specifikus beállítások</h4>

                <p>
                    <label><strong>Twitter kártya típus:</strong></label><br>
                    <select name="rseo_twitter_card" id="rseo_twitter_card" class="widefat">
                        <option value="summary_large_image" <?php selected( $tw_card, 'summary_large_image' ); ?>>Nagy kép (summary_large_image)</option>
                        <option value="summary" <?php selected( $tw_card, 'summary' ); ?>>Kis kép (summary)</option>
                    </select>
                </p>

                <p>
                    <label><strong>Twitter kép (opcionális):</strong></label><br>
                    <input type="url" name="rseo_twitter_image" id="rseo_twitter_image" value="<?php echo esc_attr( $tw_image ); ?>" class="widefat" placeholder="Egyedi kép Twitter-hez (2:1 arány ajánlott)">
                    <button type="button" class="button rseo-upload-image" data-target="#rseo_twitter_image">Kép kiválasztása</button>
                    <span class="description">Ha üres, az OG kép vagy kiemelt kép lesz használva.</span>
                </p>

                <div class="rseo-social-tips">
                    <h5>💡 Képméret ajánlások:</h5>
                    <ul>
                        <li><strong>Facebook:</strong> 1200 × 630 px (1.91:1 arány)</li>
                        <li><strong>Twitter nagy kép:</strong> 1200 × 600 px (2:1 arány)</li>
                        <li><strong>Twitter kis kép:</strong> 144 × 144 px (1:1 arány)</li>
                        <li><strong>LinkedIn:</strong> 1200 × 627 px</li>
                    </ul>
                </div>
            </div>

        </div>
        <?php
    }

    /**
     * Save additional social meta
     */
    public function save_social_meta( $post_id, $post ) {
        if ( ! isset( $_POST['rseo_meta_nonce'] ) || ! wp_verify_nonce( $_POST['rseo_meta_nonce'], 'rseo_save_meta' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Twitter card type
        if ( isset( $_POST['rseo_twitter_card'] ) ) {
            update_post_meta( $post_id, '_rseo_twitter_card', sanitize_text_field( $_POST['rseo_twitter_card'] ) );
        }

        // Twitter image
        if ( isset( $_POST['rseo_twitter_image'] ) ) {
            update_post_meta( $post_id, '_rseo_twitter_image', esc_url_raw( $_POST['rseo_twitter_image'] ) );
        }
    }

    /**
     * AJAX: Get preview data
     */
    public function ajax_get_preview() {
        check_ajax_referer( 'rseo_social_nonce', 'nonce' );

        $post_id = intval( $_POST['post_id'] );
        $post = get_post( $post_id );

        if ( ! $post ) {
            wp_send_json_error( 'Bejegyzés nem található.' );
        }

        $og_title = $_POST['og_title'] ?? get_post_meta( $post_id, '_rseo_og_title', true );
        $og_desc  = $_POST['og_desc'] ?? get_post_meta( $post_id, '_rseo_og_description', true );
        $og_image = $_POST['og_image'] ?? get_post_meta( $post_id, '_rseo_og_image', true );

        $seo_title = $_POST['seo_title'] ?? get_post_meta( $post_id, '_rseo_title', true );
        $seo_desc  = $_POST['seo_desc'] ?? get_post_meta( $post_id, '_rseo_description', true );

        $preview_title = $og_title ?: $seo_title ?: $post->post_title;
        $preview_desc  = $og_desc ?: $seo_desc ?: wp_trim_words( $post->post_content, 25, '...' );
        $preview_image = $og_image ?: get_the_post_thumbnail_url( $post_id, 'large' ) ?: RendanIT_SEO::get_setting( 'og_default_image' );

        wp_send_json_success( [
            'title' => $preview_title,
            'desc'  => $preview_desc,
            'image' => $preview_image,
            'url'   => wp_parse_url( get_permalink( $post_id ), PHP_URL_HOST ),
        ] );
    }
}

// Initialize
RSEO_Social_Preview::instance();
