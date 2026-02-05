<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RSEO_Sitemap {

    public function __construct() {
        add_action( 'init', [ $this, 'add_rewrite_rules' ] );
        add_action( 'template_redirect', [ $this, 'serve_sitemap' ] );
        add_filter( 'query_vars', [ $this, 'add_query_vars' ] );

        // Disable WP default sitemap if our custom one is active
        if ( RendanIT_SEO::get_setting( 'sitemap_enabled', 1 ) ) {
            add_filter( 'wp_sitemaps_enabled', '__return_false' );
        }
    }

    public function add_rewrite_rules() {
        if ( ! RendanIT_SEO::get_setting( 'sitemap_enabled', 1 ) ) return;

        add_rewrite_rule( 'rseo-sitemap\.xml$', 'index.php?rseo_sitemap=index', 'top' );
        add_rewrite_rule( 'rseo-sitemap-([a-z]+)\.xml$', 'index.php?rseo_sitemap=$matches[1]', 'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'rseo_sitemap';
        return $vars;
    }

    public function serve_sitemap() {
        $sitemap = get_query_var( 'rseo_sitemap' );
        if ( ! $sitemap ) return;

        header( 'Content-Type: application/xml; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex, follow' );

        switch ( $sitemap ) {
            case 'index':
                echo $this->generate_index();
                break;
            case 'pages':
                echo $this->generate_post_type_sitemap( 'page' );
                break;
            case 'posts':
                echo $this->generate_post_type_sitemap( 'post' );
                break;
            default:
                // Custom post type
                if ( post_type_exists( $sitemap ) ) {
                    echo $this->generate_post_type_sitemap( $sitemap );
                } else {
                    status_header( 404 );
                    echo '<?xml version="1.0" encoding="UTF-8"?><error>Sitemap not found</error>';
                }
                break;
        }

        exit;
    }

    /**
     * Sitemap index
     */
    private function generate_index() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . RSEO_PLUGIN_URL . 'admin/sitemap-style.xsl"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Pages sitemap
        $page_count = wp_count_posts( 'page' );
        if ( $page_count->publish > 0 ) {
            $xml .= '<sitemap>';
            $xml .= '<loc>' . esc_url( home_url( '/rseo-sitemap-pages.xml' ) ) . '</loc>';
            $xml .= '<lastmod>' . $this->get_last_modified( 'page' ) . '</lastmod>';
            $xml .= '</sitemap>' . "\n";
        }

        // Posts sitemap
        $post_count = wp_count_posts( 'post' );
        if ( $post_count->publish > 0 ) {
            $xml .= '<sitemap>';
            $xml .= '<loc>' . esc_url( home_url( '/rseo-sitemap-posts.xml' ) ) . '</loc>';
            $xml .= '<lastmod>' . $this->get_last_modified( 'post' ) . '</lastmod>';
            $xml .= '</sitemap>' . "\n";
        }

        // Custom post types
        $post_types = get_post_types( [ 'public' => true, '_builtin' => false ], 'names' );
        foreach ( $post_types as $pt ) {
            $count = wp_count_posts( $pt );
            if ( $count->publish > 0 ) {
                $xml .= '<sitemap>';
                $xml .= '<loc>' . esc_url( home_url( '/rseo-sitemap-' . $pt . '.xml' ) ) . '</loc>';
                $xml .= '<lastmod>' . $this->get_last_modified( $pt ) . '</lastmod>';
                $xml .= '</sitemap>' . "\n";
            }
        }

        $xml .= '</sitemapindex>';
        return $xml;
    }

    /**
     * Generate sitemap for a post type
     */
    private function generate_post_type_sitemap( $post_type ) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';

        // Add xhtml namespace for hreflang
        if ( RendanIT_SEO::has_polylang() ) {
            $xml .= ' xmlns:xhtml="http://www.w3.org/1999/xhtml"';
        }
        $xml .= '>' . "\n";

        // Add home page for pages sitemap
        if ( $post_type === 'page' ) {
            $xml .= $this->url_entry( home_url( '/' ), current_time( 'c' ), 'daily', '1.0' );
        }

        $args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 1000,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_rseo_noindex',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => '_rseo_noindex',
                    'value'   => '1',
                    'compare' => '!=',
                ],
            ],
        ];

        // If Polylang, get all languages
        if ( RendanIT_SEO::has_polylang() && function_exists( 'pll_get_post_language' ) ) {
            $args['lang'] = ''; // Get all languages
        }

        $query = new WP_Query( $args );

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id = get_the_ID();

                $priority = '0.6';
                if ( $post_type === 'page' ) $priority = '0.8';

                $changefreq = 'weekly';

                // Get hreflang alternates
                $alternates = [];
                if ( RendanIT_SEO::has_polylang() && function_exists( 'pll_get_post' ) && function_exists( 'pll_languages_list' ) ) {
                    $languages = pll_languages_list( [ 'fields' => 'slug' ] );
                    foreach ( $languages as $lang ) {
                        $translated_id = pll_get_post( $post_id, $lang );
                        if ( $translated_id && get_post_status( $translated_id ) === 'publish' ) {
                            $alternates[ $lang ] = get_permalink( $translated_id );
                        }
                    }
                }

                $xml .= $this->url_entry(
                    get_permalink( $post_id ),
                    get_the_modified_date( 'c' ),
                    $changefreq,
                    $priority,
                    $alternates
                );
            }
        }

        wp_reset_postdata();

        $xml .= '</urlset>';
        return $xml;
    }

    /**
     * Single URL entry
     */
    private function url_entry( $url, $lastmod, $changefreq, $priority, $alternates = [] ) {
        $xml = '<url>' . "\n";
        $xml .= '  <loc>' . esc_url( $url ) . '</loc>' . "\n";
        $xml .= '  <lastmod>' . $lastmod . '</lastmod>' . "\n";
        $xml .= '  <changefreq>' . $changefreq . '</changefreq>' . "\n";
        $xml .= '  <priority>' . $priority . '</priority>' . "\n";

        // Hreflang alternates
        if ( ! empty( $alternates ) && count( $alternates ) > 1 ) {
            foreach ( $alternates as $lang => $alt_url ) {
                $xml .= '  <xhtml:link rel="alternate" hreflang="' . esc_attr( $lang ) . '" href="' . esc_url( $alt_url ) . '"/>' . "\n";
            }
        }

        $xml .= '</url>' . "\n";
        return $xml;
    }

    /**
     * Get last modified date for post type
     */
    private function get_last_modified( $post_type ) {
        $last = get_posts( [
            'post_type'      => $post_type,
            'posts_per_page' => 1,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ] );

        if ( ! empty( $last ) ) {
            return get_the_modified_date( 'c', $last[0] );
        }

        return current_time( 'c' );
    }
}

new RSEO_Sitemap();
