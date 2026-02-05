<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RSEO_Metabox {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_meta' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

        // Post list column
        add_filter( 'manage_posts_columns', [ $this, 'add_seo_column' ] );
        add_filter( 'manage_pages_columns', [ $this, 'add_seo_column' ] );
        add_action( 'manage_posts_custom_column', [ $this, 'render_seo_column' ], 10, 2 );
        add_action( 'manage_pages_custom_column', [ $this, 'render_seo_column' ], 10, 2 );
    }

    public function add_meta_box() {
        $post_types = get_post_types( [ 'public' => true ], 'names' );

        foreach ( $post_types as $pt ) {
            add_meta_box(
                'rseo_meta_box',
                '🔍 RendanIT SEO',
                [ $this, 'render_meta_box' ],
                $pt,
                'normal',
                'high'
            );
        }
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'rseo_save_meta', 'rseo_meta_nonce' );

        $title       = get_post_meta( $post->ID, '_rseo_title', true );
        $description = get_post_meta( $post->ID, '_rseo_description', true );
        $canonical   = get_post_meta( $post->ID, '_rseo_canonical', true );
        $noindex     = get_post_meta( $post->ID, '_rseo_noindex', true );
        $nofollow    = get_post_meta( $post->ID, '_rseo_nofollow', true );
        $og_title    = get_post_meta( $post->ID, '_rseo_og_title', true );
        $og_desc     = get_post_meta( $post->ID, '_rseo_og_description', true );
        $og_image    = get_post_meta( $post->ID, '_rseo_og_image', true );
        $focus_kw    = get_post_meta( $post->ID, '_rseo_focus_keyword', true );
        $schema_type = get_post_meta( $post->ID, '_rseo_schema_type', true );
        $schema_faq  = get_post_meta( $post->ID, '_rseo_schema_faq', true );

        $sep  = RendanIT_SEO::get_setting( 'title_separator', '|' );
        $site = RendanIT_SEO::get_setting( 'site_name', get_bloginfo( 'name' ) );
        ?>
        <div class="rseo-metabox">
            <!-- Tabs -->
            <div class="rseo-metabox-tabs">
                <button type="button" class="rseo-mtab active" data-tab="seo">🔍 SEO</button>
                <button type="button" class="rseo-mtab" data-tab="social">📱 Social</button>
                <button type="button" class="rseo-mtab" data-tab="advanced">⚙️ Haladó</button>
                <button type="button" class="rseo-mtab" data-tab="analysis">📊 Elemzés</button>
            </div>

            <!-- SEO Tab -->
            <div class="rseo-mtab-content active" id="rseo-tab-seo">
                <p>
                    <label><strong>Fókusz kulcsszó:</strong></label><br>
                    <input type="text" name="rseo_focus_keyword" value="<?php echo esc_attr( $focus_kw ); ?>" class="widefat" id="rseo_focus_keyword" placeholder="Pl.: erotikus masszázs budapest">
                </p>
                <p>
                    <label><strong>SEO Title:</strong></label><br>
                    <input type="text" name="rseo_title" value="<?php echo esc_attr( $title ); ?>" class="widefat rseo-title-input" id="rseo_title" maxlength="70" placeholder="Hagyj üresen az automata generáláshoz: <?php echo esc_attr( $post->post_title . ' ' . $sep . ' ' . $site ); ?>">
                    <span class="rseo-char-count"><span class="rseo-count">0</span>/60 karakter</span>
                </p>
                <p>
                    <label><strong>Meta Description:</strong></label><br>
                    <textarea name="rseo_description" class="widefat rseo-desc-input" id="rseo_description" rows="3" maxlength="160" placeholder="Hagyj üresen az automata generáláshoz"><?php echo esc_textarea( $description ); ?></textarea>
                    <span class="rseo-char-count"><span class="rseo-count">0</span>/155 karakter</span>
                </p>

                <!-- Google Preview -->
                <div class="rseo-google-preview">
                    <strong>Google előnézet:</strong>
                    <div class="rseo-preview">
                        <div class="rseo-preview-title" id="rseo-preview-title">
                            <?php echo esc_html( $title ?: $post->post_title . ' ' . $sep . ' ' . $site ); ?>
                        </div>
                        <div class="rseo-preview-url"><?php echo esc_html( get_permalink( $post->ID ) ); ?></div>
                        <div class="rseo-preview-desc" id="rseo-preview-desc">
                            <?php echo esc_html( $description ?: wp_trim_words( $post->post_content, 25 ) ); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Social Tab -->
            <div class="rseo-mtab-content" id="rseo-tab-social">
                <p>
                    <label><strong>OG Title (Facebook/LinkedIn):</strong></label><br>
                    <input type="text" name="rseo_og_title" value="<?php echo esc_attr( $og_title ); ?>" class="widefat" placeholder="Hagyj üresen a SEO title használatához">
                </p>
                <p>
                    <label><strong>OG Description:</strong></label><br>
                    <textarea name="rseo_og_description" class="widefat" rows="2" placeholder="Hagyj üresen a meta description használatához"><?php echo esc_textarea( $og_desc ); ?></textarea>
                </p>
                <p>
                    <label><strong>OG Kép URL:</strong></label><br>
                    <input type="url" name="rseo_og_image" value="<?php echo esc_attr( $og_image ); ?>" class="widefat" id="rseo_og_image" placeholder="Hagyj üresen a kiemelt kép használatához">
                    <button type="button" class="button rseo-upload-image" data-target="#rseo_og_image">Kép kiválasztása</button>
                </p>
            </div>

            <!-- Advanced Tab -->
            <div class="rseo-mtab-content" id="rseo-tab-advanced">
                <p>
                    <label><strong>Canonical URL:</strong></label><br>
                    <input type="url" name="rseo_canonical" value="<?php echo esc_attr( $canonical ); ?>" class="widefat" placeholder="Hagyj üresen az automatikushoz">
                </p>
                <p>
                    <label><input type="checkbox" name="rseo_noindex" value="1" <?php checked( $noindex ); ?>> <strong>noindex</strong> – Ne indexelje a Google</label>
                </p>
                <p>
                    <label><input type="checkbox" name="rseo_nofollow" value="1" <?php checked( $nofollow ); ?>> <strong>nofollow</strong> – Ne kövesse a linkeket</label>
                </p>
                <p>
                    <label><strong>Schema típus (egyedi):</strong></label><br>
                    <select name="rseo_schema_type" class="widefat">
                        <option value="">-- Alapértelmezett --</option>
                        <option value="FAQPage" <?php selected( $schema_type, 'FAQPage' ); ?>>FAQ Page</option>
                        <option value="Service" <?php selected( $schema_type, 'Service' ); ?>>Service</option>
                        <option value="Article" <?php selected( $schema_type, 'Article' ); ?>>Article</option>
                        <option value="BlogPosting" <?php selected( $schema_type, 'BlogPosting' ); ?>>Blog Posting</option>
                    </select>
                </p>
                <p>
                    <label><strong>FAQ Schema (JSON):</strong></label><br>
                    <textarea name="rseo_schema_faq" class="widefat code" rows="6" placeholder='[
  {"question": "Mik a nyitvatartási idők?", "answer": "Minden nap 11:00-22:00"},
  {"question": "Kell előre foglalni?", "answer": "Igen, előzetes időpontfoglalás szükséges."}
]'><?php echo esc_textarea( $schema_faq ); ?></textarea>
                </p>
            </div>

            <!-- Analysis Tab -->
            <div class="rseo-mtab-content" id="rseo-tab-analysis">
                <div id="rseo-analysis-results">
                    <p><em>Mentsd el a bejegyzést az elemzés megtekintéséhez, vagy kattints az "Elemzés futtatása" gombra.</em></p>
                    <button type="button" class="button" id="rseo-run-analysis">📊 Elemzés futtatása</button>
                </div>
            </div>
        </div>
        <?php
    }

    public function save_meta( $post_id, $post ) {
        if ( ! isset( $_POST['rseo_meta_nonce'] ) || ! wp_verify_nonce( $_POST['rseo_meta_nonce'], 'rseo_save_meta' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $fields = [
            'rseo_title'          => '_rseo_title',
            'rseo_description'    => '_rseo_description',
            'rseo_canonical'      => '_rseo_canonical',
            'rseo_og_title'       => '_rseo_og_title',
            'rseo_og_description' => '_rseo_og_description',
            'rseo_og_image'       => '_rseo_og_image',
            'rseo_focus_keyword'  => '_rseo_focus_keyword',
            'rseo_schema_type'    => '_rseo_schema_type',
            'rseo_schema_faq'     => '_rseo_schema_faq',
        ];

        foreach ( $fields as $post_key => $meta_key ) {
            if ( isset( $_POST[ $post_key ] ) ) {
                $value = $post_key === 'rseo_schema_faq'
                    ? sanitize_textarea_field( $_POST[ $post_key ] )
                    : sanitize_text_field( $_POST[ $post_key ] );
                update_post_meta( $post_id, $meta_key, $value );
            }
        }

        // Checkboxes
        update_post_meta( $post_id, '_rseo_noindex', isset( $_POST['rseo_noindex'] ) ? 1 : 0 );
        update_post_meta( $post_id, '_rseo_nofollow', isset( $_POST['rseo_nofollow'] ) ? 1 : 0 );
    }

    public function enqueue_scripts( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ] ) ) return;
        wp_enqueue_script( 'rseo-metabox', RSEO_PLUGIN_URL . 'admin/js/metabox.js', [ 'jquery' ], RSEO_VERSION, true );
        wp_localize_script( 'rseo-metabox', 'rseoMetabox', [
            'separator' => RendanIT_SEO::get_setting( 'title_separator', '|' ),
            'siteName'  => RendanIT_SEO::get_setting( 'site_name', get_bloginfo( 'name' ) ),
        ]);
    }

    // Admin column
    public function add_seo_column( $columns ) {
        $columns['rseo_score'] = 'SEO';
        return $columns;
    }

    public function render_seo_column( $column, $post_id ) {
        if ( $column !== 'rseo_score' ) return;

        $result = RSEO_Score::get_score( $post_id );
        $score = $result['score'];
        $grade = $result['grade'];
        $color = RSEO_Score::score_color( $score );

        printf(
            '<span class="rseo-column-score">' .
            '<span class="rseo-mini-circle" style="background:%s">%d</span>' .
            '<span class="rseo-column-grade" style="color:%s">%s</span>' .
            '</span>',
            esc_attr( $color ),
            $score,
            esc_attr( $color ),
            esc_html( $grade )
        );
    }
}

new RSEO_Metabox();
