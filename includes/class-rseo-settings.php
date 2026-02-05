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
        $sanitized = [];
        $text_fields = [
            'title_separator', 'site_name', 'home_title', 'home_description',
            'schema_type', 'schema_name', 'schema_description', 'schema_street',
            'schema_city', 'schema_zip', 'schema_country', 'schema_phone',
            'schema_email', 'schema_url', 'schema_lat', 'schema_lng',
            'schema_price_range', 'schema_opening', 'schema_image',
            'og_default_image', 'og_type', 'twitter_card',
            'robots_txt', 'gtm_id', 'ga4_id',
        ];

        foreach ( $text_fields as $field ) {
            $sanitized[ $field ] = isset( $input[ $field ] ) ? sanitize_text_field( $input[ $field ] ) : '';
        }

        $checkbox_fields = [ 'noindex_archives', 'noindex_tags', 'noindex_author', 'sitemap_enabled' ];
        foreach ( $checkbox_fields as $field ) {
            $sanitized[ $field ] = isset( $input[ $field ] ) ? 1 : 0;
        }

        // Handle multilang home titles/descriptions
        if ( RendanIT_SEO::has_polylang() && function_exists( 'pll_languages_list' ) ) {
            $languages = pll_languages_list( [ 'fields' => 'slug' ] );
            foreach ( $languages as $lang ) {
                $sanitized[ 'home_title_' . $lang ] = isset( $input[ 'home_title_' . $lang ] )
                    ? sanitize_text_field( $input[ 'home_title_' . $lang ] ) : '';
                $sanitized[ 'home_description_' . $lang ] = isset( $input[ 'home_description_' . $lang ] )
                    ? sanitize_textarea_field( $input[ 'home_description_' . $lang ] ) : '';
            }
        }

        // Schema services (JSON)
        if ( isset( $input['schema_services'] ) ) {
            $sanitized['schema_services'] = sanitize_textarea_field( $input['schema_services'] );
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
                    }
                    ?>
                </div>

                <?php submit_button( 'Mentés' ); ?>
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
                <th><label>Sitemap</label></th>
                <td>
                    <label><input type="checkbox" name="rseo_settings[sitemap_enabled]" value="1" <?php checked( $this->get( $s, 'sitemap_enabled', 1 ) ); ?>> XML Sitemap engedélyezése</label>
                    <?php if ( $this->get( $s, 'sitemap_enabled', 1 ) ) : ?>
                        <p class="description">Sitemap URL: <a href="<?php echo esc_url( home_url( '/rseo-sitemap.xml' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/rseo-sitemap.xml' ) ); ?></a></p>
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

    public function render_audit_page() {
        $audit = new RSEO_Audit();
        $audit->render_page();
    }

    private function get( $settings, $key, $default = '' ) {
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }
}

new RSEO_Settings();
