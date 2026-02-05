<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RSEO_Frontend {

    public function __construct() {
        // Remove default WP title and replace
        add_filter( 'pre_get_document_title', [ $this, 'custom_title' ], 999 );
        add_filter( 'document_title_parts', [ $this, 'title_parts' ], 999 );

        // Head output
        add_action( 'wp_head', [ $this, 'output_meta_tags' ], 1 );
        add_action( 'wp_head', [ $this, 'output_og_tags' ], 2 );
        add_action( 'wp_head', [ $this, 'output_canonical' ], 3 );
        add_action( 'wp_head', [ $this, 'output_robots_meta' ], 4 );

        // Tracking codes
        add_action( 'wp_head', [ $this, 'output_gtm_head' ], 0 );
        add_action( 'wp_body_open', [ $this, 'output_gtm_body' ], 0 );
        add_action( 'wp_head', [ $this, 'output_ga4' ], 0 );

        // Robots.txt
        add_filter( 'robots_txt', [ $this, 'custom_robots_txt' ], 10, 2 );

        // Remove unwanted meta
        remove_action( 'wp_head', 'wp_generator' );
        remove_action( 'wp_head', 'wlwmanifest_link' );
        remove_action( 'wp_head', 'rsd_link' );
    }

    /**
     * Custom title tag
     */
    public function custom_title( $title ) {
        if ( is_admin() ) return $title;

        $sep  = RendanIT_SEO::get_setting( 'title_separator', '|' );
        $site = RendanIT_SEO::get_setting( 'site_name', get_bloginfo( 'name' ) );

        // Home page
        if ( is_front_page() || is_home() ) {
            $lang = RendanIT_SEO::get_current_lang();
            $home_title = RendanIT_SEO::get_setting( 'home_title_' . $lang );
            if ( ! $home_title ) {
                $home_title = RendanIT_SEO::get_setting( 'home_title' );
            }
            if ( $home_title ) {
                return $home_title;
            }
        }

        // Single post/page
        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            $custom_title = get_post_meta( $post_id, '_rseo_title', true );
            if ( $custom_title ) {
                return $custom_title;
            }
            return get_the_title( $post_id ) . ' ' . $sep . ' ' . $site;
        }

        // Taxonomy
        if ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            if ( $term ) {
                return $term->name . ' ' . $sep . ' ' . $site;
            }
        }

        return $title;
    }

    /**
     * Override title parts (backup method)
     */
    public function title_parts( $parts ) {
        $site = RendanIT_SEO::get_setting( 'site_name', get_bloginfo( 'name' ) );
        if ( $site && isset( $parts['site'] ) ) {
            $parts['site'] = $site;
        }
        return $parts;
    }

    /**
     * Output meta description
     */
    public function output_meta_tags() {
        $description = $this->get_meta_description();

        if ( $description ) {
            echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
        }

        // Language
        $lang = RendanIT_SEO::get_current_lang();
        echo '<meta http-equiv="content-language" content="' . esc_attr( $lang ) . '">' . "\n";
    }

    /**
     * Get meta description for current page
     */
    private function get_meta_description() {
        // Home
        if ( is_front_page() || is_home() ) {
            $lang = RendanIT_SEO::get_current_lang();
            $desc = RendanIT_SEO::get_setting( 'home_description_' . $lang );
            if ( ! $desc ) {
                $desc = RendanIT_SEO::get_setting( 'home_description' );
            }
            return $desc;
        }

        // Single
        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            $desc = get_post_meta( $post_id, '_rseo_description', true );
            if ( $desc ) return $desc;

            // Auto-generate from content
            $post = get_post( $post_id );
            if ( $post ) {
                $content = wp_strip_all_tags( $post->post_content );
                $content = str_replace( [ "\n", "\r", "\t" ], ' ', $content );
                $content = preg_replace( '/\s+/', ' ', $content );
                return mb_substr( trim( $content ), 0, 155 );
            }
        }

        // Taxonomy
        if ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            if ( $term && $term->description ) {
                return mb_substr( wp_strip_all_tags( $term->description ), 0, 155 );
            }
        }

        return '';
    }

    /**
     * Open Graph tags
     */
    public function output_og_tags() {
        $title       = '';
        $description = '';
        $url         = '';
        $image       = RendanIT_SEO::get_setting( 'og_default_image' );
        $type        = RendanIT_SEO::get_setting( 'og_type', 'website' );
        $site_name   = RendanIT_SEO::get_setting( 'site_name', get_bloginfo( 'name' ) );
        $lang        = RendanIT_SEO::get_current_lang();

        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            $title   = get_post_meta( $post_id, '_rseo_og_title', true ) ?: get_post_meta( $post_id, '_rseo_title', true ) ?: get_the_title( $post_id );
            $description = get_post_meta( $post_id, '_rseo_og_description', true ) ?: get_post_meta( $post_id, '_rseo_description', true ) ?: $this->get_meta_description();
            $url     = get_permalink( $post_id );
            $type    = 'article';

            $og_img = get_post_meta( $post_id, '_rseo_og_image', true );
            if ( $og_img ) {
                $image = $og_img;
            } elseif ( has_post_thumbnail( $post_id ) ) {
                $image = get_the_post_thumbnail_url( $post_id, 'large' );
            }
        } elseif ( is_front_page() || is_home() ) {
            $title = RendanIT_SEO::get_setting( 'home_title_' . $lang ) ?: RendanIT_SEO::get_setting( 'home_title' ) ?: get_bloginfo( 'name' );
            $description = $this->get_meta_description();
            $url = home_url( '/' );
        } else {
            $title = wp_get_document_title();
            $description = $this->get_meta_description();
            $url = home_url( add_query_arg( [] ) );
        }

        $locale_map = [
            'hu' => 'hu_HU',
            'en' => 'en_US',
            'de' => 'de_DE',
        ];
        $og_locale = isset( $locale_map[ $lang ] ) ? $locale_map[ $lang ] : get_locale();

        echo "\n<!-- RendanIT SEO - Open Graph -->\n";

        if ( $title )       echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
        if ( $description ) echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
        if ( $url )         echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
        if ( $image )       echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
        if ( $type )        echo '<meta property="og:type" content="' . esc_attr( $type ) . '">' . "\n";
        if ( $site_name )   echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '">' . "\n";
        echo '<meta property="og:locale" content="' . esc_attr( $og_locale ) . '">' . "\n";

        // Twitter Card
        $twitter_card = RendanIT_SEO::get_setting( 'twitter_card', 'summary_large_image' );
        echo '<meta name="twitter:card" content="' . esc_attr( $twitter_card ) . '">' . "\n";
        if ( $title )       echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
        if ( $description ) echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";
        if ( $image )       echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";

        echo "<!-- /RendanIT SEO -->\n\n";
    }

    /**
     * Canonical URL
     */
    public function output_canonical() {
        // Remove default WordPress canonical
        remove_action( 'wp_head', 'rel_canonical' );

        $canonical = '';

        if ( is_singular() ) {
            $post_id   = get_queried_object_id();
            $canonical = get_post_meta( $post_id, '_rseo_canonical', true );
            if ( ! $canonical ) {
                $canonical = get_permalink( $post_id );
            }
        } elseif ( is_front_page() || is_home() ) {
            $canonical = home_url( '/' );
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $canonical = get_term_link( get_queried_object() );
        }

        if ( $canonical && ! is_wp_error( $canonical ) ) {
            echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
        }
    }

    /**
     * Robots meta tag
     */
    public function output_robots_meta() {
        $robots = [];

        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            if ( get_post_meta( $post_id, '_rseo_noindex', true ) ) {
                $robots[] = 'noindex';
            }
            if ( get_post_meta( $post_id, '_rseo_nofollow', true ) ) {
                $robots[] = 'nofollow';
            }
        }

        // Archive noindex
        if ( is_date() && RendanIT_SEO::get_setting( 'noindex_archives' ) ) {
            $robots[] = 'noindex';
            $robots[] = 'follow';
        }
        if ( is_tag() && RendanIT_SEO::get_setting( 'noindex_tags' ) ) {
            $robots[] = 'noindex';
            $robots[] = 'follow';
        }
        if ( is_author() && RendanIT_SEO::get_setting( 'noindex_author' ) ) {
            $robots[] = 'noindex';
            $robots[] = 'follow';
        }

        // Paginated
        if ( is_paged() ) {
            $robots[] = 'noindex';
            $robots[] = 'follow';
        }

        if ( ! empty( $robots ) ) {
            echo '<meta name="robots" content="' . esc_attr( implode( ', ', array_unique( $robots ) ) ) . '">' . "\n";
        }
    }

    /**
     * GTM Head
     */
    public function output_gtm_head() {
        $gtm_id = RendanIT_SEO::get_setting( 'gtm_id' );
        if ( ! $gtm_id ) return;
        ?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?php echo esc_js( $gtm_id ); ?>');</script>
<!-- End Google Tag Manager -->
        <?php
    }

    /**
     * GTM Body
     */
    public function output_gtm_body() {
        $gtm_id = RendanIT_SEO::get_setting( 'gtm_id' );
        if ( ! $gtm_id ) return;
        ?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr( $gtm_id ); ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
        <?php
    }

    /**
     * GA4
     */
    public function output_ga4() {
        $ga4_id = RendanIT_SEO::get_setting( 'ga4_id' );
        $gtm_id = RendanIT_SEO::get_setting( 'gtm_id' );
        if ( ! $ga4_id || $gtm_id ) return; // Skip if GTM handles it
        ?>
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $ga4_id ); ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '<?php echo esc_js( $ga4_id ); ?>');
</script>
<!-- End GA4 -->
        <?php
    }

    /**
     * Custom robots.txt
     */
    public function custom_robots_txt( $output, $public ) {
        $custom = RendanIT_SEO::get_setting( 'robots_txt' );

        // Add sitemap
        if ( RendanIT_SEO::get_setting( 'sitemap_enabled' ) ) {
            $output .= "\nSitemap: " . home_url( '/rseo-sitemap.xml' ) . "\n";
        }

        if ( $custom ) {
            $output .= "\n" . $custom . "\n";
        }

        return $output;
    }
}

new RSEO_Frontend();
