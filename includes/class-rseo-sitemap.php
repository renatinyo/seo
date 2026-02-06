<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RSEO_Sitemap {

    /**
     * Post types to exclude from sitemap
     */
    private $excluded_post_types = [
        'elementor_library',
        'elementor-thhf',
        'elementor_font',
        'elementor_icons',
        'elementor_snippet',
        'e-landing-page',
        'attachment',
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'wp_block',
        'wp_template',
        'wp_template_part',
        'wp_global_styles',
        'wp_navigation',
    ];

    public function __construct() {
        add_action( 'init', [ $this, 'add_rewrite_rules' ] );
        add_action( 'template_redirect', [ $this, 'serve_sitemap' ] );
        add_filter( 'query_vars', [ $this, 'add_query_vars' ] );

        // AJAX handler for generating static sitemap files
        add_action( 'wp_ajax_rseo_generate_sitemap', [ $this, 'ajax_generate_sitemap' ] );

        // Check if rewrite rules need to be flushed
        add_action( 'admin_init', [ $this, 'maybe_flush_rewrite_rules' ] );

        // Disable WP default sitemap if our custom one is active
        if ( RendanIT_SEO::get_setting( 'sitemap_enabled', 1 ) ) {
            add_filter( 'wp_sitemaps_enabled', '__return_false' );
        }
    }

    /**
     * Check if our rewrite rules exist, flush if not
     */
    public function maybe_flush_rewrite_rules() {
        if ( ! RendanIT_SEO::get_setting( 'sitemap_enabled', 1 ) ) return;

        $rules = get_option( 'rewrite_rules' );
        if ( ! isset( $rules['rseo-sitemap\.xml$'] ) ) {
            flush_rewrite_rules();
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
                if ( post_type_exists( $sitemap ) && ! in_array( $sitemap, $this->excluded_post_types, true ) ) {
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
     * AJAX handler - Generate static sitemap XML files
     */
    public function ajax_generate_sitemap() {
        check_ajax_referer( 'rseo_generate_sitemap_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Nincs jogosultságod.' ] );
        }

        $result = $this->write_sitemap_files();

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * Get allowed public post types (excluding Elementor library etc.)
     */
    private function get_allowed_post_types() {
        $post_types = get_post_types( [ 'public' => true, '_builtin' => false ], 'names' );
        return array_diff( $post_types, $this->excluded_post_types );
    }

    /**
     * Write all sitemap files to disk
     */
    public function write_sitemap_files() {
        $root_dir = ABSPATH;
        $files_written = [];
        $errors = [];
        $total_urls = 0;

        // Check if directory is writable
        if ( ! is_writable( $root_dir ) ) {
            return [
                'success' => false,
                'message' => 'A WordPress gyökérkönyvtár nem írható: ' . $root_dir,
            ];
        }

        // Collect sub-sitemaps info for the index
        $sub_sitemaps = [];

        // Pages sitemap
        $page_count = wp_count_posts( 'page' );
        if ( $page_count->publish > 0 ) {
            $result_data = $this->generate_post_type_sitemap( 'page', true );
            $file = $root_dir . 'rseo-sitemap-pages.xml';
            if ( file_put_contents( $file, $result_data['xml'] ) !== false ) {
                $files_written[] = 'rseo-sitemap-pages.xml';
                $total_urls += $result_data['count'];
                $sub_sitemaps[] = [
                    'loc'     => home_url( '/rseo-sitemap-pages.xml' ),
                    'lastmod' => $this->get_last_modified( 'page' ),
                ];
            } else {
                $errors[] = 'rseo-sitemap-pages.xml írási hiba';
            }
        }

        // Posts sitemap
        $post_count = wp_count_posts( 'post' );
        if ( $post_count->publish > 0 ) {
            $result_data = $this->generate_post_type_sitemap( 'post', true );
            $file = $root_dir . 'rseo-sitemap-posts.xml';
            if ( file_put_contents( $file, $result_data['xml'] ) !== false ) {
                $files_written[] = 'rseo-sitemap-posts.xml';
                $total_urls += $result_data['count'];
                $sub_sitemaps[] = [
                    'loc'     => home_url( '/rseo-sitemap-posts.xml' ),
                    'lastmod' => $this->get_last_modified( 'post' ),
                ];
            } else {
                $errors[] = 'rseo-sitemap-posts.xml írási hiba';
            }
        }

        // Custom post types (excluding Elementor library etc.)
        $post_types = $this->get_allowed_post_types();
        foreach ( $post_types as $pt ) {
            $count = wp_count_posts( $pt );
            if ( $count->publish > 0 ) {
                $result_data = $this->generate_post_type_sitemap( $pt, true );
                $filename = 'rseo-sitemap-' . $pt . '.xml';
                $file = $root_dir . $filename;
                if ( file_put_contents( $file, $result_data['xml'] ) !== false ) {
                    $files_written[] = $filename;
                    $total_urls += $result_data['count'];
                    $sub_sitemaps[] = [
                        'loc'     => home_url( '/' . $filename ),
                        'lastmod' => $this->get_last_modified( $pt ),
                    ];
                } else {
                    $errors[] = $filename . ' írási hiba';
                }
            }
        }

        // Generate and write the index sitemap
        $index_xml = $this->generate_index_from_data( $sub_sitemaps );
        $index_file = $root_dir . 'rseo-sitemap.xml';
        if ( file_put_contents( $index_file, $index_xml ) !== false ) {
            $files_written[] = 'rseo-sitemap.xml';
        } else {
            $errors[] = 'rseo-sitemap.xml írási hiba';
        }

        // Save generation timestamp
        update_option( 'rseo_sitemap_generated', current_time( 'mysql' ) );

        if ( ! empty( $errors ) ) {
            return [
                'success' => false,
                'message' => 'Hibák: ' . implode( ', ', $errors ),
                'files'   => $files_written,
            ];
        }

        return [
            'success'    => true,
            'message'    => count( $files_written ) . ' sitemap fájl létrehozva, ' . $total_urls . ' URL-lel.',
            'files'      => $files_written,
            'total_urls' => $total_urls,
            'sitemap_url' => home_url( '/rseo-sitemap.xml' ),
        ];
    }

    /**
     * Generate sitemap index from pre-built data (for static file generation)
     */
    private function generate_index_from_data( $sub_sitemaps ) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ( $sub_sitemaps as $sm ) {
            $xml .= '<sitemap>' . "\n";
            $xml .= '  <loc>' . esc_url( $sm['loc'] ) . '</loc>' . "\n";
            $xml .= '  <lastmod>' . esc_html( $sm['lastmod'] ) . '</lastmod>' . "\n";
            $xml .= '</sitemap>' . "\n";
        }

        $xml .= '</sitemapindex>';
        return $xml;
    }

    /**
     * Sitemap index (for dynamic serving)
     */
    private function generate_index() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Pages sitemap
        $page_count = wp_count_posts( 'page' );
        if ( $page_count->publish > 0 ) {
            $xml .= '<sitemap>';
            $xml .= '<loc>' . esc_url( home_url( '/rseo-sitemap-pages.xml' ) ) . '</loc>';
            $xml .= '<lastmod>' . esc_html( $this->get_last_modified( 'page' ) ) . '</lastmod>';
            $xml .= '</sitemap>' . "\n";
        }

        // Posts sitemap
        $post_count = wp_count_posts( 'post' );
        if ( $post_count->publish > 0 ) {
            $xml .= '<sitemap>';
            $xml .= '<loc>' . esc_url( home_url( '/rseo-sitemap-posts.xml' ) ) . '</loc>';
            $xml .= '<lastmod>' . esc_html( $this->get_last_modified( 'post' ) ) . '</lastmod>';
            $xml .= '</sitemap>' . "\n";
        }

        // Custom post types (excluding Elementor library etc.)
        $post_types = $this->get_allowed_post_types();
        foreach ( $post_types as $pt ) {
            $count = wp_count_posts( $pt );
            if ( $count->publish > 0 ) {
                $xml .= '<sitemap>';
                $xml .= '<loc>' . esc_url( home_url( '/rseo-sitemap-' . $pt . '.xml' ) ) . '</loc>';
                $xml .= '<lastmod>' . esc_html( $this->get_last_modified( $pt ) ) . '</lastmod>';
                $xml .= '</sitemap>' . "\n";
            }
        }

        $xml .= '</sitemapindex>';
        return $xml;
    }

    /**
     * Determine priority based on post type, slug, and page characteristics
     */
    private function get_priority( $post_id, $post_type ) {
        $slug = get_post_field( 'post_name', $post_id );
        $url = get_permalink( $post_id );
        $path = wp_parse_url( $url, PHP_URL_PATH );
        $path = strtolower( trim( $path, '/' ) );

        // Blog posts always 0.6
        if ( $post_type === 'post' ) {
            return '0.6';
        }

        // Pages: smart priority based on slug/path keywords
        if ( $post_type === 'page' ) {
            // High priority pages (programs, prices, services) = 0.9
            $high_priority = [ 'program', 'programok', 'arak', 'araink', 'prices', 'price', 'szolgaltatasok', 'services', 'massages', 'masszazsok', 'masszazs' ];
            foreach ( $high_priority as $kw ) {
                if ( strpos( $path, $kw ) !== false || strpos( $slug, $kw ) !== false ) {
                    return '0.9';
                }
            }

            // Medium-high priority (hotel, location, about) = 0.8
            $med_high = [ 'hotel', 'szallas', 'szalloda', 'helyszin', 'location', 'about', 'rolunk', 'bemutatkozas' ];
            foreach ( $med_high as $kw ) {
                if ( strpos( $path, $kw ) !== false || strpos( $slug, $kw ) !== false ) {
                    return '0.8';
                }
            }

            // Medium priority (profiles, team, gallery) = 0.7
            $medium = [ 'profil', 'profile', 'profiles', 'csapat', 'team', 'galeria', 'gallery', 'lanyok', 'girls', 'masszor', 'masseur' ];
            foreach ( $medium as $kw ) {
                if ( strpos( $path, $kw ) !== false || strpos( $slug, $kw ) !== false ) {
                    return '0.7';
                }
            }

            // Low priority (legal, privacy, contact forms) = 0.3
            $low = [ 'adatvede', 'privacy', 'gdpr', 'cookie', 'aszf', 'terms', 'impresszum', 'imprint', 'jogi' ];
            foreach ( $low as $kw ) {
                if ( strpos( $path, $kw ) !== false || strpos( $slug, $kw ) !== false ) {
                    return '0.3';
                }
            }

            // Contact page = 0.7
            $contact = [ 'kapcsolat', 'contact', 'elerhetoseg' ];
            foreach ( $contact as $kw ) {
                if ( strpos( $path, $kw ) !== false || strpos( $slug, $kw ) !== false ) {
                    return '0.7';
                }
            }

            // Default page priority
            return '0.8';
        }

        // Default for custom post types
        return '0.6';
    }

    /**
     * Check if a post should be excluded from sitemap
     */
    private function is_excluded( $post_id ) {
        // Check _rseo_noindex meta
        $noindex = get_post_meta( $post_id, '_rseo_noindex', true );
        if ( $noindex == '1' ) {
            return true;
        }

        // Exclude password-protected posts
        $post = get_post( $post_id );
        if ( $post && ! empty( $post->post_password ) ) {
            return true;
        }

        // Check slug patterns for common non-indexable pages
        $slug = get_post_field( 'post_name', $post_id );
        $excluded_slugs = [ 'reservation', 'foglalas', 'booking', 'cart', 'checkout', 'koszonjuk', 'thank-you', 'thankyou', 'koszonom' ];
        if ( in_array( $slug, $excluded_slugs, true ) ) {
            return true;
        }

        return false;
    }

    /**
     * Get the static front page ID (if set)
     */
    private function get_front_page_id() {
        if ( 'page' === get_option( 'show_on_front' ) ) {
            return (int) get_option( 'page_on_front' );
        }
        return 0;
    }

    /**
     * Convert Polylang language slug to proper hreflang code
     * e.g., hu -> hu, en -> en, but uses locale for regional: en_US -> en-US
     */
    private function get_hreflang_code( $lang_slug ) {
        if ( ! function_exists( 'pll_languages_list' ) ) {
            return $lang_slug;
        }

        $languages = pll_languages_list( [ 'fields' => '' ] );
        foreach ( $languages as $lang ) {
            if ( $lang->slug === $lang_slug && isset( $lang->locale ) ) {
                $parts = explode( '_', $lang->locale );
                // Simple case: hu_HU -> hu
                if ( count( $parts ) === 2 && strtolower( $parts[0] ) === strtolower( $parts[1] ) ) {
                    return strtolower( $parts[0] );
                }
                // Regional variant: en_US -> en-US
                return strtolower( $parts[0] ) . ( isset( $parts[1] ) ? '-' . strtoupper( $parts[1] ) : '' );
            }
        }

        return $lang_slug;
    }

    /**
     * Generate sitemap for a post type
     * @param string $post_type
     * @param bool $return_data If true, returns array with xml and count
     * @return string|array
     */
    private function generate_post_type_sitemap( $post_type, $return_data = false ) {
        $has_polylang = RendanIT_SEO::has_polylang();
        $url_count = 0;
        $front_page_id = $this->get_front_page_id();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';

        // Always add xhtml namespace for hreflang if Polylang is active
        if ( $has_polylang ) {
            $xml .= ' xmlns:xhtml="http://www.w3.org/1999/xhtml"';
        }
        $xml .= '>' . "\n";

        // Add home page for pages sitemap (use real lastmod, not current_time)
        if ( $post_type === 'page' ) {
            $home_alternates = $this->get_home_alternates();
            $home_lastmod = $this->get_last_modified( 'page' );
            $xml .= $this->url_entry( home_url( '/' ), $home_lastmod, 'daily', '1.0', $home_alternates );
            $url_count++;
        }

        $args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 1000,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'has_password'   => false, // Exclude password-protected posts
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
        if ( $has_polylang && function_exists( 'pll_get_post_language' ) ) {
            $args['lang'] = '';
        }

        $query = new WP_Query( $args );

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id = get_the_ID();

                // Skip the front page - already added as home_url('/')
                if ( $post_type === 'page' && $front_page_id && $post_id === $front_page_id ) {
                    continue;
                }

                // Double-check exclusion (slug-based + noindex + password)
                if ( $this->is_excluded( $post_id ) ) {
                    continue;
                }

                // Smart priority
                $priority = $this->get_priority( $post_id, $post_type );

                $changefreq = ( $post_type === 'post' ) ? 'weekly' : 'monthly';

                // Get hreflang alternates for every URL (with proper codes)
                $alternates = $this->get_post_alternates( $post_id );

                // lastmod from post_modified
                $lastmod = get_the_modified_date( 'c', $post_id );

                $xml .= $this->url_entry(
                    get_permalink( $post_id ),
                    $lastmod,
                    $changefreq,
                    $priority,
                    $alternates
                );
                $url_count++;
            }
        }

        wp_reset_postdata();

        $xml .= '</urlset>';

        if ( $return_data ) {
            return [ 'xml' => $xml, 'count' => $url_count ];
        }

        return $xml;
    }

    /**
     * Get hreflang alternates for a post (with proper hreflang codes)
     */
    private function get_post_alternates( $post_id ) {
        $alternates = [];

        if ( ! RendanIT_SEO::has_polylang() || ! function_exists( 'pll_get_post' ) || ! function_exists( 'pll_languages_list' ) ) {
            return $alternates;
        }

        $languages = pll_languages_list( [ 'fields' => 'slug' ] );
        foreach ( $languages as $lang ) {
            $translated_id = pll_get_post( $post_id, $lang );
            if ( $translated_id && get_post_status( $translated_id ) === 'publish' ) {
                $hreflang_code = $this->get_hreflang_code( $lang );
                $alternates[ $hreflang_code ] = get_permalink( $translated_id );
            }
        }

        return $alternates;
    }

    /**
     * Get hreflang alternates for the home page (with proper hreflang codes)
     */
    private function get_home_alternates() {
        $alternates = [];

        if ( ! RendanIT_SEO::has_polylang() || ! function_exists( 'pll_home_url' ) || ! function_exists( 'pll_languages_list' ) ) {
            return $alternates;
        }

        $languages = pll_languages_list( [ 'fields' => 'slug' ] );
        foreach ( $languages as $lang ) {
            $hreflang_code = $this->get_hreflang_code( $lang );
            $alternates[ $hreflang_code ] = pll_home_url( $lang );
        }

        return $alternates;
    }

    /**
     * Single URL entry with hreflang support
     */
    private function url_entry( $url, $lastmod, $changefreq, $priority, $alternates = [] ) {
        $xml = '<url>' . "\n";
        $xml .= '  <loc>' . esc_url( $url ) . '</loc>' . "\n";
        $xml .= '  <lastmod>' . esc_html( $lastmod ) . '</lastmod>' . "\n";
        $xml .= '  <changefreq>' . esc_html( $changefreq ) . '</changefreq>' . "\n";
        $xml .= '  <priority>' . esc_html( $priority ) . '</priority>' . "\n";

        // Hreflang alternates - output if there are translations (min 2 languages)
        if ( ! empty( $alternates ) && count( $alternates ) > 1 ) {
            foreach ( $alternates as $lang => $alt_url ) {
                $xml .= '  <xhtml:link rel="alternate" hreflang="' . esc_attr( $lang ) . '" href="' . esc_url( $alt_url ) . '"/>' . "\n";
            }
            // x-default pointing to the current URL
            $xml .= '  <xhtml:link rel="alternate" hreflang="x-default" href="' . esc_url( $url ) . '"/>' . "\n";
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
            'has_password'   => false,
        ] );

        if ( ! empty( $last ) ) {
            return get_the_modified_date( 'c', $last[0] );
        }

        return current_time( 'c' );
    }

    /**
     * Create database table (placeholder for interface compatibility)
     */
    public static function create_table() {
        // No database table needed for sitemap
    }
}

new RSEO_Sitemap();
