<?php
/**
 * Plugin Name: RendanIT SEO
 * Plugin URI: https://rendanit.com
 * Description: Testreszabott SEO plugin Polylang integrációval. Meta címek, leírások, Schema markup, hreflang, Open Graph, sitemap és SEO audit.
 * Version: 1.5.0
 * Author: RendanIT
 * Author URI: https://rendanit.com
 * Text Domain: rendanit-seo
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RSEO_VERSION', '1.5.0' );
define( 'RSEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RSEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RSEO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Plugin Class
 */
final class RendanIT_SEO {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        require_once RSEO_PLUGIN_DIR . 'includes/class-rseo-settings.php';
        require_once RSEO_PLUGIN_DIR . 'includes/class-rseo-metabox.php';
        require_once RSEO_PLUGIN_DIR . 'includes/class-rseo-frontend.php';
        require_once RSEO_PLUGIN_DIR . 'includes/class-rseo-schema.php';
        require_once RSEO_PLUGIN_DIR . 'includes/class-rseo-hreflang.php';
        require_once RSEO_PLUGIN_DIR . 'includes/class-rseo-sitemap.php';
        require_once RSEO_PLUGIN_DIR . 'includes/class-rseo-audit.php';
        require_once RSEO_PLUGIN_DIR . 'includes/class-rseo-score.php';
        require_once RSEO_PLUGIN_DIR . 'includes/class-rseo-admin-bar.php';
        require_once RSEO_PLUGIN_DIR . 'includes/class-rseo-redirects.php';
        require_once RSEO_PLUGIN_DIR . 'includes/class-rseo-404-monitor.php';
        require_once RSEO_PLUGIN_DIR . 'includes/class-rseo-readability.php';
        require_once RSEO_PLUGIN_DIR . 'includes/class-rseo-bulk-editor.php';
        require_once RSEO_PLUGIN_DIR . 'includes/class-rseo-social-preview.php';
        require_once RSEO_PLUGIN_DIR . 'includes/class-rseo-link-suggestions.php';
        require_once RSEO_PLUGIN_DIR . 'includes/class-rseo-elementor.php';
        require_once RSEO_PLUGIN_DIR . 'includes/class-rseo-llms.php';
    }

    private function init_hooks() {
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );

        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_filter( 'plugin_action_links_' . RSEO_PLUGIN_BASENAME, [ $this, 'plugin_action_links' ] );
    }

    public function activate() {
        $defaults = [
            'title_separator'    => '|',
            'site_name'          => get_bloginfo( 'name' ),
            'home_title'         => '',
            'home_description'   => '',
            'schema_type'        => 'LocalBusiness',
            'schema_name'        => '',
            'schema_description' => '',
            'schema_street'      => '',
            'schema_city'        => 'Budapest',
            'schema_zip'         => '',
            'schema_country'     => 'HU',
            'schema_phone'       => '',
            'schema_email'       => '',
            'schema_url'         => home_url(),
            'schema_lat'         => '',
            'schema_lng'         => '',
            'schema_price_range' => '$$',
            'schema_opening'     => '',
            'schema_image'       => '',
            'og_default_image'   => '',
            'og_type'            => 'website',
            'twitter_card'       => 'summary_large_image',
            'noindex_archives'   => 1,
            'noindex_tags'       => 1,
            'noindex_author'     => 1,
            'sitemap_enabled'    => 1,
            'robots_txt'         => '',
            'gtm_id'             => '',
            'ga4_id'             => '',
        ];

        if ( ! get_option( 'rseo_settings' ) ) {
            update_option( 'rseo_settings', $defaults );
        }

        // Create database tables
        RSEO_Redirects::create_table();
        RSEO_404_Monitor::create_table();

        // Flush rewrite rules for sitemap
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'rendanit-seo', false, dirname( RSEO_PLUGIN_BASENAME ) . '/languages' );
    }

    public function plugin_action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=rendanit-seo' ) . '">' . __( 'Beállítások', 'rendanit-seo' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Get a setting value
     */
    public static function get_setting( $key, $default = '' ) {
        $settings = get_option( 'rseo_settings', [] );
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Check if Polylang is active
     */
    public static function has_polylang() {
        return function_exists( 'pll_current_language' );
    }

    /**
     * Get current language
     */
    public static function get_current_lang() {
        if ( self::has_polylang() ) {
            return pll_current_language( 'slug' );
        }
        return substr( get_locale(), 0, 2 );
    }
}

/**
 * Initialize
 */
function rendanit_seo() {
    return RendanIT_SEO::instance();
}

add_action( 'plugins_loaded', 'rendanit_seo' );
