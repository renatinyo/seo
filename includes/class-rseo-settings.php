<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RSEO_Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    public function add_menu() {
        add_menu_page(
            'RendanIT SEO',
            'RendanIT SEO',
            'manage_options',
            'rendanit-seo',
            [ $this, 'render_settings_page' ],
            'dashicons-search',
            80
        );

        add_submenu_page(
            'rendanit-seo',
            'SEO Beállítások',
            'Beállítások',
            'manage_options',
            'rendanit-seo',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            'rendanit-seo',
            'SEO Audit',
            'SEO Audit',
            'manage_options',
            'rendanit-seo-audit',
            [ $this, 'render_audit_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'rseo_settings_group', 'rseo_settings', [
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
        ]);
    }

    public function sanitize_settings( $input ) {
        // IMPORTANT: Merge with existing settings so other tabs' data is preserved
        $existing = get_option( 'rseo_settings', [] );
        $sanitized = is_array( $existing ) ? $existing : [];

        $text_fields = [
            'title_separator', 'site_name', 'home_title', 'home_description',
            'schema_type', 'schema_name', 'schema_street',
            'schema_city', 'schema_zip', 'schema_country', 'schema_phone',
            'schema_email', 'schema_url', 'schema_lat', 'schema_lng',
            'schema_price_range', 'schema_image',
            'og_default_image', 'og_type', 'twitter_card',
            'gtm_id', 'ga4_id',
        ];

        foreach ( $text_fields as $field ) {
            if ( isset( $input[ $field ] ) ) {
                $sanitized[ $field ] = sanitize_text_field( $input[ $field ] );
            }
        }

        // Textarea fields (preserve newlines)
        $textarea_fields = [ 'schema_opening', 'robots_txt', 'schema_description' ];
        foreach ( $textarea_fields as $field ) {
            if ( isset( $input[ $field ] ) ) {
                $sanitized[ $field ] = sanitize_textarea_field( $input[ $field ] );
            }
        }

        // Checkboxes - only update if the relevant tab was submitted
        $checkbox_fields = [ 'noindex_archives', 'noindex_tags', 'noindex_author', 'sitemap_enabled' ];
        // Detect which tab is being saved by checking for tab-specific fields
        $is_indexing_tab = isset( $input['robots_txt'] ) || isset( $input['noindex_archives'] ) || isset( $input['sitemap_enabled'] );
        if ( $is_indexing_tab ) {
            foreach ( $checkbox_fields as $field ) {
                $sanitized[ $field ] = isset( $input[ $field ] ) ? 1 : 0;
            }
        }

        // Handle multilang home titles/descriptions
        if ( RendanIT_SEO::has_polylang() && function_exists( 'pll_languages_list' ) ) {
            $languages = pll_languages_list( [ 'fields' => 'slug' ] );
            foreach ( $languages as $lang ) {
                if ( isset( $input[ 'home_title_' . $lang ] ) ) {
                    $sanitized[ 'home_title_' . $lang ] = sanitize_text_field( $input[ 'home_title_' . $lang ] );
                }
                if ( isset( $input[ 'home_description_' . $lang ] ) ) {
                    $sanitized[ 'home_description_' . $lang ] = sanitize_textarea_field( $input[ 'home_description_' . $lang ] );
                }
            }
        }

        // Schema services (JSON)
        if ( isset( $input['schema_services'] ) ) {
            $sanitized['schema_services'] = sanitize_textarea_field( $input['schema_services'] );
        }

        // LLMS.txt content
        if ( isset( $input['llms_content'] ) ) {
            $sanitized['llms_content'] = sanitize_textarea_field( $input['llms_content'] );
        }

        return $sanitized;
    }

    public function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'rendanit-seo' ) === false ) return;

        wp_enqueue_style( 'rseo-admin', RSEO_PLUGIN_URL . 'admin/css/admin.css', [], RSEO_VERSION );
        wp_enqueue_script( 'rseo-admin', RSEO_PLUGIN_URL . 'admin/js/admin.js', [ 'jquery' ], RSEO_VERSION, true );
        wp_enqueue_media();
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
        $settings = get_option( 'rseo_settings', [] );
        ?>
        <div class="wrap rseo-wrap">
            <h1><span class="dashicons dashicons-search"></span> RendanIT SEO</h1>

            <nav class="nav-tab-wrapper rseo-tabs">
                <a href="?page=rendanit-seo&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    🏠 Általános
                </a>
                <a href="?page=rendanit-seo&tab=homepage" class="nav-tab <?php echo $active_tab === 'homepage' ? 'nav-tab-active' : ''; ?>">
                    📄 Főoldal SEO
                </a>
                <a href="?page=rendanit-seo&tab=schema" class="nav-tab <?php echo $active_tab === 'schema' ? 'nav-tab-active' : ''; ?>">
                    🏢 Schema / Structured Data
                </a>
                <a href="?page=rendanit-seo&tab=social" class="nav-tab <?php echo $active_tab === 'social' ? 'nav-tab-active' : ''; ?>">
                    📱 Social / Open Graph
                </a>
                <a href="?page=rendanit-seo&tab=indexing" class="nav-tab <?php echo $active_tab === 'indexing' ? 'nav-tab-active' : ''; ?>">
                    🤖 Indexelés
                </a>
                <a href="?page=rendanit-seo&tab=tracking" class="nav-tab <?php echo $active_tab === 'tracking' ? 'nav-tab-active' : ''; ?>">
                    📊 Tracking
                </a>
                <a href="?page=rendanit-seo&tab=llms" class="nav-tab <?php echo $active_tab === 'llms' ? 'nav-tab-active' : ''; ?>">
                    🤖 AI (llms.txt)
                </a>
                <a href="?page=rendanit-seo&tab=tools" class="nav-tab <?php echo $active_tab === 'tools' ? 'nav-tab-active' : ''; ?>">
                    🧪 Eszközök
                </a>
            </nav>

            <form method="post" action="options.php">
                <?php settings_fields( 'rseo_settings_group' ); ?>

                <div class="rseo-tab-content">
                    <?php
                    switch ( $active_tab ) {
                        case 'general':
                            $this->tab_general( $settings );
                            break;
                        case 'homepage':
                            $this->tab_homepage( $settings );
                            break;
                        case 'schema':
                            $this->tab_schema( $settings );
                            break;
                        case 'social':
                            $this->tab_social( $settings );
                            break;
                        case 'indexing':
                            $this->tab_indexing( $settings );
                            break;
                        case 'tracking':
                            $this->tab_tracking( $settings );
                            break;
                        case 'llms':
                            $this->tab_llms( $settings );
                            break;
                        case 'tools':
                            $this->tab_tools( $settings );
                            break;
                    }
                    ?>
                </div>

                <?php if ( $active_tab !== 'tools' ) submit_button( 'Mentés' ); ?>
            </form>
        </div>
        <?php
    }

    private function tab_general( $s ) {
        ?>
        <h2>Általános SEO Beállítások</h2>
        <table class="form-table">
            <tr>
                <th><label for="title_separator">Title elválasztó</label></th>
                <td>
                    <select name="rseo_settings[title_separator]" id="title_separator">
                        <?php
                        $separators = [ '|', '–', '-', '·', '»', '/', '•' ];
                        foreach ( $separators as $sep ) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr( $sep ),
                                selected( $this->get( $s, 'title_separator', '|' ), $sep, false ),
                                esc_html( $sep )
                            );
                        }
                        ?>
                    </select>
                    <p class="description">Pl.: "Oldal címe | Weboldal neve"</p>
                </td>
            </tr>
            <tr>
                <th><label for="site_name">Weboldal neve</label></th>
                <td>
                    <input type="text" name="rseo_settings[site_name]" id="site_name"
                           value="<?php echo esc_attr( $this->get( $s, 'site_name', get_bloginfo('name') ) ); ?>"
                           class="regular-text">
                    <p class="description">Ez jelenik meg a title tag végén. Pl.: "Allure Massage Budapest"</p>
                </td>
            </tr>
        </table>
        <?php
    }

    private function tab_homepage( $s ) {
        ?>
        <h2>Főoldal SEO Beállítások</h2>

        <?php if ( RendanIT_SEO::has_polylang() && function_exists( 'pll_languages_list' ) ) : ?>
            <?php
            $languages = pll_languages_list( [ 'fields' => '' ] );
            foreach ( $languages as $lang ) :
            ?>
                <div class="rseo-lang-section">
                    <h3>🌐 <?php echo esc_html( strtoupper( $lang->slug ) ); ?> – <?php echo esc_html( $lang->name ); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th><label>Title tag</label></th>
                            <td>
                                <input type="text" name="rseo_settings[home_title_<?php echo esc_attr( $lang->slug ); ?>]"
                                       value="<?php echo esc_attr( $this->get( $s, 'home_title_' . $lang->slug ) ); ?>"
                                       class="large-text rseo-title-input" maxlength="70">
                                <div class="rseo-char-count">
                                    <span class="rseo-count">0</span>/60 karakter
                                    <span class="rseo-indicator"></span>
                                </div>
                                <div class="rseo-preview">
                                    <div class="rseo-preview-title"></div>
                                    <div class="rseo-preview-url"><?php echo esc_html( home_url( '/' . $lang->slug . '/' ) ); ?></div>
                                    <div class="rseo-preview-desc"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Meta description</label></th>
                            <td>
                                <textarea name="rseo_settings[home_description_<?php echo esc_attr( $lang->slug ); ?>]"
                                          rows="3" class="large-text rseo-desc-input"
                                          maxlength="160"><?php echo esc_textarea( $this->get( $s, 'home_description_' . $lang->slug ) ); ?></textarea>
                                <div class="rseo-char-count">
                                    <span class="rseo-count">0</span>/155 karakter
                                    <span class="rseo-indicator"></span>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <table class="form-table">
                <tr>
                    <th><label for="home_title">Főoldal Title</label></th>
                    <td>
                        <input type="text" name="rseo_settings[home_title]" id="home_title"
                               value="<?php echo esc_attr( $this->get( $s, 'home_title' ) ); ?>"
                               class="large-text rseo-title-input" maxlength="70">
                        <div class="rseo-char-count"><span class="rseo-count">0</span>/60 karakter</div>
                    </td>
                </tr>
                <tr>
                    <th><label for="home_description">Főoldal Meta Description</label></th>
                    <td>
                        <textarea name="rseo_settings[home_description]" id="home_description"
                                  rows="3" class="large-text rseo-desc-input"
                                  maxlength="160"><?php echo esc_textarea( $this->get( $s, 'home_description' ) ); ?></textarea>
                        <div class="rseo-char-count"><span class="rseo-count">0</span>/155 karakter</div>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
        <?php
    }

    private function tab_schema( $s ) {
        ?>
        <h2>Schema / Structured Data Beállítások</h2>
        <p class="description">Ez a Google Rich Snippetekhez szükséges. A LocalBusiness schema segíti a helyi keresési megjelenést.</p>

        <table class="form-table">
            <tr>
                <th><label for="schema_type">Vállalkozás típusa</label></th>
                <td>
                    <select name="rseo_settings[schema_type]" id="schema_type">
                        <?php
                        $types = [
                            'LocalBusiness'            => 'Local Business (általános)',
                            'HealthAndBeautyBusiness'  => 'Health & Beauty Business',
                            'DaySpa'                   => 'Day Spa',
                            'BeautySalon'              => 'Beauty Salon',
                        ];
                        foreach ( $types as $val => $label ) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr( $val ),
                                selected( $this->get( $s, 'schema_type', 'LocalBusiness' ), $val, false ),
                                esc_html( $label )
                            );
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="schema_name">Vállalkozás neve</label></th>
                <td><input type="text" name="rseo_settings[schema_name]" id="schema_name" value="<?php echo esc_attr( $this->get( $s, 'schema_name' ) ); ?>" class="regular-text" placeholder="Allure Massage Budapest"></td>
            </tr>
            <tr>
                <th><label for="schema_description">Leírás</label></th>
                <td><textarea name="rseo_settings[schema_description]" id="schema_description" rows="3" class="large-text" placeholder="Premium erotic massage salon in Budapest..."><?php echo esc_textarea( $this->get( $s, 'schema_description' ) ); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="schema_street">Utca, házszám</label></th>
                <td><input type="text" name="rseo_settings[schema_street]" id="schema_street" value="<?php echo esc_attr( $this->get( $s, 'schema_street' ) ); ?>" class="regular-text" placeholder="Dózsa György út 54"></td>
            </tr>
            <tr>
                <th><label for="schema_city">Város</label></th>
                <td><input type="text" name="rseo_settings[schema_city]" id="schema_city" value="<?php echo esc_attr( $this->get( $s, 'schema_city', 'Budapest' ) ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="schema_zip">Irányítószám</label></th>
                <td><input type="text" name="rseo_settings[schema_zip]" id="schema_zip" value="<?php echo esc_attr( $this->get( $s, 'schema_zip' ) ); ?>" class="regular-text" placeholder="1071"></td>
            </tr>
            <tr>
                <th><label for="schema_country">Ország kód</label></th>
                <td><input type="text" name="rseo_settings[schema_country]" id="schema_country" value="<?php echo esc_attr( $this->get( $s, 'schema_country', 'HU' ) ); ?>" class="small-text"></td>
            </tr>
            <tr>
                <th><label for="schema_phone">Telefonszám</label></th>
                <td><input type="text" name="rseo_settings[schema_phone]" id="schema_phone" value="<?php echo esc_attr( $this->get( $s, 'schema_phone' ) ); ?>" class="regular-text" placeholder="+36702062141"></td>
            </tr>
            <tr>
                <th><label for="schema_email">Email</label></th>
                <td><input type="email" name="rseo_settings[schema_email]" id="schema_email" value="<?php echo esc_attr( $this->get( $s, 'schema_email' ) ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="schema_url">Weboldal URL</label></th>
                <td><input type="url" name="rseo_settings[schema_url]" id="schema_url" value="<?php echo esc_attr( $this->get( $s, 'schema_url', home_url() ) ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="schema_lat">GPS Latitude</label></th>
                <td><input type="text" name="rseo_settings[schema_lat]" id="schema_lat" value="<?php echo esc_attr( $this->get( $s, 'schema_lat' ) ); ?>" class="regular-text" placeholder="47.5095"></td>
            </tr>
            <tr>
                <th><label for="schema_lng">GPS Longitude</label></th>
                <td><input type="text" name="rseo_settings[schema_lng]" id="schema_lng" value="<?php echo esc_attr( $this->get( $s, 'schema_lng' ) ); ?>" class="regular-text" placeholder="19.0750"></td>
            </tr>
            <tr>
                <th><label for="schema_price_range">Ár kategória</label></th>
                <td>
                    <select name="rseo_settings[schema_price_range]" id="schema_price_range">
                        <?php foreach ( ['$', '$$', '$$$', '$$$$'] as $pr ) : ?>
                            <option value="<?php echo esc_attr( $pr ); ?>" <?php selected( $this->get( $s, 'schema_price_range', '$$' ), $pr ); ?>><?php echo esc_html( $pr ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="schema_opening">Nyitvatartás</label></th>
                <td>
                    <textarea name="rseo_settings[schema_opening]" id="schema_opening" rows="4" class="large-text" placeholder="Mo-Sa 11:00-22:00&#10;Su 11:00-22:00"><?php echo esc_textarea( $this->get( $s, 'schema_opening' ) ); ?></textarea>
                    <p class="description">Soronként egy sor. Formátum: <code>Mo-Sa 11:00-22:00</code> vagy <code>Mo 11:00-22:00</code></p>
                </td>
            </tr>
            <tr>
                <th><label for="schema_image">Vállalkozás kép URL</label></th>
                <td>
                    <input type="url" name="rseo_settings[schema_image]" id="schema_image" value="<?php echo esc_attr( $this->get( $s, 'schema_image' ) ); ?>" class="regular-text">
                    <button type="button" class="button rseo-upload-image" data-target="#schema_image">Kép kiválasztása</button>
                </td>
            </tr>
            <tr>
                <th><label for="schema_services">Szolgáltatások (JSON)</label></th>
                <td>
                    <textarea name="rseo_settings[schema_services]" id="schema_services" rows="8" class="large-text code" placeholder='[
  {"name": "Lingam Massage", "description": "Professional lingam massage", "price": "30000", "currency": "HUF"},
  {"name": "Tantra Massage", "description": "Tantric massage experience", "price": "35000", "currency": "HUF"}
]'><?php echo esc_textarea( $this->get( $s, 'schema_services' ) ); ?></textarea>
                    <p class="description">JSON tömb formátum. Minden szolgáltatás: name, description, price, currency</p>
                </td>
            </tr>
        </table>
        <?php
    }

    private function tab_social( $s ) {
        ?>
        <h2>Open Graph & Social Beállítások</h2>
        <table class="form-table">
            <tr>
                <th><label for="og_default_image">Alapértelmezett OG kép</label></th>
                <td>
                    <input type="url" name="rseo_settings[og_default_image]" id="og_default_image" value="<?php echo esc_attr( $this->get( $s, 'og_default_image' ) ); ?>" class="regular-text">
                    <button type="button" class="button rseo-upload-image" data-target="#og_default_image">Kép kiválasztása</button>
                    <p class="description">Ajánlott méret: 1200x630px. Ez jelenik meg alapértelmezetten Facebook/LinkedIn megosztásnál.</p>
                </td>
            </tr>
            <tr>
                <th><label for="og_type">OG típus</label></th>
                <td>
                    <select name="rseo_settings[og_type]" id="og_type">
                        <option value="website" <?php selected( $this->get( $s, 'og_type', 'website' ), 'website' ); ?>>website</option>
                        <option value="business.business" <?php selected( $this->get( $s, 'og_type' ), 'business.business' ); ?>>business.business</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="twitter_card">Twitter Card típus</label></th>
                <td>
                    <select name="rseo_settings[twitter_card]" id="twitter_card">
                        <option value="summary_large_image" <?php selected( $this->get( $s, 'twitter_card', 'summary_large_image' ), 'summary_large_image' ); ?>>Summary Large Image</option>
                        <option value="summary" <?php selected( $this->get( $s, 'twitter_card' ), 'summary' ); ?>>Summary</option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    private function tab_indexing( $s ) {
        $sitemap_url = home_url( '/rseo-sitemap.xml' );
        $sitemap_generated = get_option( 'rseo_sitemap_generated', '' );
        $sitemap_file_exists = file_exists( ABSPATH . 'rseo-sitemap.xml' );
        ?>
        <h2>Indexelés Beállítások</h2>
        <table class="form-table">
            <tr>
                <th>Noindex beállítások</th>
                <td>
                    <label><input type="checkbox" name="rseo_settings[noindex_archives]" value="1" <?php checked( $this->get( $s, 'noindex_archives' ) ); ?>> Archívum oldalak (dátum archívum)</label><br>
                    <label><input type="checkbox" name="rseo_settings[noindex_tags]" value="1" <?php checked( $this->get( $s, 'noindex_tags' ) ); ?>> Címke oldalak</label><br>
                    <label><input type="checkbox" name="rseo_settings[noindex_author]" value="1" <?php checked( $this->get( $s, 'noindex_author' ) ); ?>> Szerző oldalak</label><br>
                </td>
            </tr>
            <tr>
                <th><label>XML Sitemap</label></th>
                <td>
                    <label><input type="checkbox" name="rseo_settings[sitemap_enabled]" value="1" <?php checked( $this->get( $s, 'sitemap_enabled', 1 ) ); ?>> XML Sitemap engedélyezése</label>

                    <?php if ( $this->get( $s, 'sitemap_enabled', 1 ) ) : ?>
                        <div style="margin-top:15px;padding:15px;background:#f9f9f9;border:1px solid #ddd;border-radius:8px;">
                            <p style="margin-top:0;">
                                <button type="button" id="rseo-generate-sitemap" class="button button-primary" style="font-size:14px;padding:5px 20px;">
                                    🗺️ Sitemap Generálása
                                </button>
                            </p>
                            <div id="rseo-sitemap-status" style="margin-top:10px;">
                                <?php if ( $sitemap_file_exists && $sitemap_generated ) : ?>
                                    <p style="color:#00a32a;">
                                        ✅ Sitemap létezik — Utoljára generálva: <strong><?php echo esc_html( $sitemap_generated ); ?></strong>
                                    </p>
                                    <p>
                                        <a href="<?php echo esc_url( $sitemap_url ); ?>" target="_blank"><?php echo esc_html( $sitemap_url ); ?></a>
                                    </p>
                                <?php elseif ( $sitemap_file_exists ) : ?>
                                    <p style="color:#00a32a;">✅ Sitemap fájl létezik.</p>
                                    <p>
                                        <a href="<?php echo esc_url( $sitemap_url ); ?>" target="_blank"><?php echo esc_html( $sitemap_url ); ?></a>
                                    </p>
                                <?php else : ?>
                                    <p style="color:#d63638;">❌ Sitemap még nincs generálva. Kattints a gombra a létrehozáshoz!</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="robots_txt">Egyedi robots.txt kiegészítés</label></th>
                <td>
                    <textarea name="rseo_settings[robots_txt]" id="robots_txt" rows="6" class="large-text code"><?php echo esc_textarea( $this->get( $s, 'robots_txt' ) ); ?></textarea>
                    <p class="description">Extra sorok a robots.txt fájlhoz (az alapértelmezett WordPress robots.txt után).</p>
                </td>
            </tr>
        </table>

        <?php if ( $this->get( $s, 'sitemap_enabled', 1 ) ) : ?>
        <script>
        jQuery(function($) {
            $('#rseo-generate-sitemap').on('click', function() {
                var $btn = $(this);
                var $status = $('#rseo-sitemap-status');

                $btn.prop('disabled', true).text('⏳ Generálás...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rseo_generate_sitemap',
                        nonce: '<?php echo wp_create_nonce( 'rseo_generate_sitemap_nonce' ); ?>'
                    },
                    success: function(response) {
                        $btn.prop('disabled', false).html('🗺️ Sitemap Generálása');
                        if (response.success) {
                            var d = response.data;
                            $status.html(
                                '<p style="color:#00a32a;">✅ <strong>' + d.message + '</strong></p>' +
                                '<p>Fájlok: ' + d.files.join(', ') + '</p>' +
                                '<p><a href="' + d.sitemap_url + '" target="_blank">' + d.sitemap_url + '</a></p>'
                            );
                        } else {
                            var msg = response.data && response.data.message ? response.data.message : 'Ismeretlen hiba';
                            $status.html('<p style="color:#d63638;">❌ Hiba: ' + msg + '</p>');
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).html('🗺️ Sitemap Generálása');
                        $status.html('<p style="color:#d63638;">❌ AJAX hiba történt.</p>');
                    }
                });
            });
        });
        </script>
        <?php endif; ?>
        <?php
    }

    private function tab_tracking( $s ) {
        ?>
        <h2>Tracking Beállítások</h2>
        <table class="form-table">
            <tr>
                <th><label for="gtm_id">Google Tag Manager ID</label></th>
                <td>
                    <input type="text" name="rseo_settings[gtm_id]" id="gtm_id" value="<?php echo esc_attr( $this->get( $s, 'gtm_id' ) ); ?>" class="regular-text" placeholder="GTM-XXXXXXX">
                </td>
            </tr>
            <tr>
                <th><label for="ga4_id">Google Analytics 4 ID</label></th>
                <td>
                    <input type="text" name="rseo_settings[ga4_id]" id="ga4_id" value="<?php echo esc_attr( $this->get( $s, 'ga4_id' ) ); ?>" class="regular-text" placeholder="G-XXXXXXXXXX">
                    <p class="description">Ha GTM-et használsz, inkább azon keresztül konfiguráld a GA4-et.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    private function tab_llms( $s ) {
        $llms_url = home_url( '/llms.txt' );
        ?>
        <h2>🤖 AI Felismerés (llms.txt)</h2>

        <p class="description" style="font-size:14px;max-width:800px;">
            Az <code>llms.txt</code> egy új szabvány ami segít az AI rendszereknek (ChatGPT, Claude, Gemini)
            megérteni a weboldalad tartalmát. Ha valaki megkérdezi az AI-t a cégedről, az AI tudni fog róla.
        </p>

        <div style="background:#e7f5ff;padding:15px;border-radius:8px;margin:15px 0;border-left:4px solid #2271b1;">
            <strong>📍 Az llms.txt elérhető itt:</strong><br>
            <a href="<?php echo esc_url( $llms_url ); ?>" target="_blank" style="font-size:16px;"><?php echo esc_html( $llms_url ); ?></a>
        </div>

        <h3>Automatikus generálás</h3>
        <p>Ha üresen hagyod az alábbi mezőt, a plugin automatikusan generálja a tartalmat a Schema beállításokból és az oldalakból.</p>

        <h3>Egyedi tartalom (opcionális)</h3>
        <table class="form-table">
            <tr>
                <th><label for="llms_content">llms.txt tartalma</label></th>
                <td>
                    <textarea name="rseo_settings[llms_content]" id="llms_content" rows="15" class="large-text code"
                        placeholder="Hagyd üresen az automatikus generáláshoz..."><?php echo esc_textarea( $this->get( $s, 'llms_content' ) ); ?></textarea>
                    <p class="description">Markdown formátumban írd. Ha üresen hagyod, automatikusan generálódik a Schema beállításokból.</p>
                </td>
            </tr>
        </table>

        <h3>Példa formátum</h3>
        <pre style="background:#f5f5f5;padding:15px;border-radius:4px;overflow-x:auto;max-width:600px;"># Cégnév

> Rövid leírás a cégről.

## Szolgáltatások

- Szolgáltatás 1
- Szolgáltatás 2
- Szolgáltatás 3

## Kapcsolat

- Telefon: +36 1 234 5678
- Email: info@example.com

## Cím

Budapest, Példa utca 123.

## Nyitvatartás

Hétfő-Péntek: 9:00-18:00</pre>

        <h3>Hogyan segít ez?</h3>
        <ul style="list-style:none;padding:0;">
            <li>✅ Az AI rendszerek automatikusan olvassák az llms.txt fájlt</li>
            <li>✅ Ha valaki megkérdezi pl. "Mi az [cégnév] telefonszáma?", az AI tudni fogja</li>
            <li>✅ A Google és más keresők is értelmezik</li>
            <li>✅ Javítja az AI-alapú keresési találatokat</li>
        </ul>
        <?php
    }

    private function tab_tools( $s ) {
        $site_url = home_url();
        $encoded_url = urlencode( $site_url );
        $sitemap_url = home_url( '/rseo-sitemap.xml' );
        $encoded_sitemap = urlencode( $sitemap_url );
        $llms_url = home_url( '/llms.txt' );
        ?>
        <h2>🧪 SEO Eszközök & Tesztelők</h2>
        <p class="description" style="font-size:14px;">Egy kattintással teszteld az oldalad a legfontosabb Google és SEO eszközökkel. Minden link az oldalad URL-jét tartalmazza.</p>

        <style>
            .rseo-tools-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px; margin-top: 20px; }
            .rseo-tool-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; }
            .rseo-tool-card h3 { margin-top: 0; font-size: 16px; }
            .rseo-tool-card p { color: #666; font-size: 13px; margin-bottom: 12px; }
            .rseo-tool-card .button { margin-right: 5px; margin-bottom: 5px; }
            .rseo-tool-section { margin-top: 30px; }
            .rseo-tool-section h2 { border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        </style>

        <!-- Google Eszközök -->
        <div class="rseo-tool-section">
            <h2>🔍 Google Eszközök</h2>
            <div class="rseo-tools-grid">

                <div class="rseo-tool-card">
                    <h3>📊 PageSpeed Insights</h3>
                    <p>Oldal sebesség és Core Web Vitals mérés. Mobil és asztali eredmények javítási javaslatokkal.</p>
                    <a href="https://pagespeed.web.dev/analysis?url=<?php echo $encoded_url; ?>" target="_blank" class="button button-primary">Teszt indítása</a>
                </div>

                <div class="rseo-tool-card">
                    <h3>📱 Mobilbarát teszt</h3>
                    <p>Ellenőrizd, hogy az oldalad mobilbarát-e. A Google előnyben részesíti a mobilra optimalizált oldalakat.</p>
                    <a href="https://search.google.com/test/mobile-friendly?url=<?php echo $encoded_url; ?>" target="_blank" class="button button-primary">Teszt indítása</a>
                </div>

                <div class="rseo-tool-card">
                    <h3>🏗️ Rich Results (Schema) Teszt</h3>
                    <p>Ellenőrizd a Schema markup-ot. Megmutatja a hibákat és figyelmeztetéseket a strukturált adatokban.</p>
                    <a href="https://search.google.com/test/rich-results?url=<?php echo $encoded_url; ?>" target="_blank" class="button button-primary">Teszt indítása</a>
                </div>

                <div class="rseo-tool-card">
                    <h3>🔎 Google Search Console</h3>
                    <p>Keresési teljesítmény, indexelés, hibák. URL vizsgálat és sitemap beküldés.</p>
                    <a href="https://search.google.com/search-console" target="_blank" class="button button-primary">Megnyitás</a>
                    <a href="https://search.google.com/search-console/inspect?resource_id=<?php echo $encoded_url; ?>" target="_blank" class="button">URL vizsgálat</a>
                </div>

                <div class="rseo-tool-card">
                    <h3>📋 Google Cache</h3>
                    <p>Nézd meg, hogyan látja a Google az oldalad. Ha nincs cache, az oldal még nem indexelt.</p>
                    <a href="https://webcache.googleusercontent.com/search?q=cache:<?php echo $encoded_url; ?>" target="_blank" class="button button-primary">Cache megtekintése</a>
                    <a href="https://www.google.com/search?q=site:<?php echo urlencode( parse_url( $site_url, PHP_URL_HOST ) ); ?>" target="_blank" class="button">Indexelt oldalak</a>
                </div>

                <div class="rseo-tool-card">
                    <h3>🗺️ XML Sitemap</h3>
                    <p>Az oldalad sitemap-ja. Küldd be a Google Search Console-ba az indexelés gyorsításához.</p>
                    <a href="<?php echo esc_url( $sitemap_url ); ?>" target="_blank" class="button button-primary">Sitemap megnyitása</a>
                    <a href="https://www.google.com/ping?sitemap=<?php echo $encoded_sitemap; ?>" target="_blank" class="button">Ping Google</a>
                </div>

            </div>
        </div>

        <!-- SEO Elemzők -->
        <div class="rseo-tool-section">
            <h2>📈 SEO Elemzők</h2>
            <div class="rseo-tools-grid">

                <div class="rseo-tool-card">
                    <h3>🔗 Meta Tag Ellenőrző</h3>
                    <p>Nézd meg milyen meta tageket lát a Google és a social média az oldaladon.</p>
                    <a href="https://metatags.io/?url=<?php echo $encoded_url; ?>" target="_blank" class="button button-primary">Ellenőrzés</a>
                </div>

                <div class="rseo-tool-card">
                    <h3>📊 GTmetrix</h3>
                    <p>Részletes oldal sebesség elemzés, waterfall diagram, és optimalizálási javaslatok.</p>
                    <a href="https://gtmetrix.com/?url=<?php echo $encoded_url; ?>" target="_blank" class="button button-primary">Teszt indítása</a>
                </div>

                <div class="rseo-tool-card">
                    <h3>🔒 SSL Ellenőrző</h3>
                    <p>SSL tanúsítvány ellenőrzés. A HTTPS fontos rangsorolási faktor.</p>
                    <a href="https://www.ssllabs.com/ssltest/analyze.html?d=<?php echo urlencode( parse_url( $site_url, PHP_URL_HOST ) ); ?>" target="_blank" class="button button-primary">SSL Teszt</a>
                </div>

                <div class="rseo-tool-card">
                    <h3>📐 W3C Validator</h3>
                    <p>HTML validáció. A helyes HTML segíti a keresőmotorok munkáját.</p>
                    <a href="https://validator.w3.org/nu/?doc=<?php echo $encoded_url; ?>" target="_blank" class="button button-primary">HTML Validáció</a>
                </div>

                <div class="rseo-tool-card">
                    <h3>♿ Accessibility (WAVE)</h3>
                    <p>Akadálymentesítés ellenőrzés. A Google figyelembe veszi az accessibility-t.</p>
                    <a href="https://wave.webaim.org/report#/<?php echo $encoded_url; ?>" target="_blank" class="button button-primary">Ellenőrzés</a>
                </div>

                <div class="rseo-tool-card">
                    <h3>🌐 Headers Ellenőrző</h3>
                    <p>HTTP headers vizsgálat (redirect, cache, security headers).</p>
                    <a href="https://securityheaders.com/?q=<?php echo $encoded_url; ?>&followRedirects=on" target="_blank" class="button button-primary">Headers Teszt</a>
                </div>

            </div>
        </div>

        <!-- Social Media -->
        <div class="rseo-tool-section">
            <h2>📱 Social Media Tesztelők</h2>
            <div class="rseo-tools-grid">

                <div class="rseo-tool-card">
                    <h3>📘 Facebook Debugger</h3>
                    <p>Open Graph tagek ellenőrzése. Itt tudod frissíteni a Facebook cache-t is megosztás után.</p>
                    <a href="https://developers.facebook.com/tools/debug/?q=<?php echo $encoded_url; ?>" target="_blank" class="button button-primary">Debug</a>
                </div>

                <div class="rseo-tool-card">
                    <h3>🐦 Twitter Card Validator</h3>
                    <p>Twitter Card előnézet ellenőrzés. Nézd meg hogyan jelenik meg a tweet-ben az oldalad.</p>
                    <a href="https://cards-dev.twitter.com/validator" target="_blank" class="button button-primary">Validator megnyitása</a>
                </div>

                <div class="rseo-tool-card">
                    <h3>💼 LinkedIn Inspector</h3>
                    <p>LinkedIn post előnézet. Frissítsd a LinkedIn cache-t ha módosítottad az OG tageket.</p>
                    <a href="https://www.linkedin.com/post-inspector/inspect/<?php echo $encoded_url; ?>" target="_blank" class="button button-primary">Inspect</a>
                </div>

            </div>
        </div>

        <!-- Saját Oldal -->
        <div class="rseo-tool-section">
            <h2>🏠 Saját Oldal Linkek</h2>
            <div class="rseo-tools-grid">

                <div class="rseo-tool-card">
                    <h3>🗺️ Sitemap</h3>
                    <p>Az oldalad XML sitemap fájlja.</p>
                    <a href="<?php echo esc_url( $sitemap_url ); ?>" target="_blank" class="button button-primary">rseo-sitemap.xml</a>
                </div>

                <div class="rseo-tool-card">
                    <h3>🤖 robots.txt</h3>
                    <p>A keresőrobotoknak szóló utasítások.</p>
                    <a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" class="button button-primary">robots.txt</a>
                </div>

                <div class="rseo-tool-card">
                    <h3>🤖 llms.txt</h3>
                    <p>AI rendszereknek szóló oldal leírás.</p>
                    <a href="<?php echo esc_url( $llms_url ); ?>" target="_blank" class="button button-primary">llms.txt</a>
                </div>

            </div>
        </div>

        <?php
    }

    public function render_audit_page() {
        $audit = new RSEO_Audit();
        $audit->render_page();
    }

    private function get( $settings, $key, $default = '' ) {
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }
}

new RSEO_Settings();
