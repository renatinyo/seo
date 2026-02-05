<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RSEO_Hreflang {

    public function __construct() {
        // Output hreflang tags - high priority to override Polylang's default if needed
        add_action( 'wp_head', [ $this, 'output_hreflang' ], 1 );

        // Optionally remove Polylang's hreflang to avoid duplicates
        add_action( 'wp', [ $this, 'maybe_remove_polylang_hreflang' ] );
    }

    /**
     * Remove Polylang's built-in hreflang if we're handling it
     */
    public function maybe_remove_polylang_hreflang() {
        if ( ! RendanIT_SEO::has_polylang() ) return;

        // Polylang uses its own hreflang output - we can let it handle it
        // or remove and do our own. We'll enhance rather than replace.
        // Only remove if Polylang's output is insufficient.
    }

    /**
     * Output hreflang tags
     */
    public function output_hreflang() {
        if ( ! RendanIT_SEO::has_polylang() ) return;
        if ( ! function_exists( 'pll_languages_list' ) ) return;

        // Check if Polylang already outputs hreflang
        // We'll output our own enhanced version
        $languages = pll_languages_list( [ 'fields' => '' ] );
        if ( empty( $languages ) ) return;

        echo "\n<!-- RendanIT SEO - Hreflang Tags -->\n";

        $has_translations = false;

        foreach ( $languages as $lang ) {
            $url = $this->get_url_for_language( $lang->slug );
            if ( ! $url ) continue;

            $has_translations = true;

            // Full locale for hreflang
            $hreflang = $this->get_hreflang_code( $lang );

            echo '<link rel="alternate" hreflang="' . esc_attr( $hreflang ) . '" href="' . esc_url( $url ) . '">' . "\n";
        }

        // x-default (usually English or the default language)
        if ( $has_translations ) {
            $default_lang = pll_default_language( 'slug' );
            $default_url  = $this->get_url_for_language( $default_lang );
            if ( ! $default_url ) {
                $default_url = home_url( '/' );
            }
            echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $default_url ) . '">' . "\n";
        }

        echo "<!-- /RendanIT SEO Hreflang -->\n\n";
    }

    /**
     * Get URL for a specific language
     */
    private function get_url_for_language( $lang_slug ) {
        if ( ! function_exists( 'pll_get_post' ) ) return false;

        // Front page
        if ( is_front_page() || is_home() ) {
            return pll_home_url( $lang_slug );
        }

        // Single post/page
        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            $translated_id = pll_get_post( $post_id, $lang_slug );

            if ( $translated_id && get_post_status( $translated_id ) === 'publish' ) {
                return get_permalink( $translated_id );
            }
            return false;
        }

        // Taxonomy
        if ( ( is_category() || is_tag() || is_tax() ) && function_exists( 'pll_get_term' ) ) {
            $term = get_queried_object();
            if ( $term ) {
                $translated_term_id = pll_get_term( $term->term_id, $lang_slug );
                if ( $translated_term_id ) {
                    $link = get_term_link( $translated_term_id );
                    if ( ! is_wp_error( $link ) ) {
                        return $link;
                    }
                }
            }
            return false;
        }

        // Archive pages - return language home
        return pll_home_url( $lang_slug );
    }

    /**
     * Get proper hreflang code from Polylang language object
     */
    private function get_hreflang_code( $lang ) {
        // Polylang stores locale like hu_HU or en_US
        // Hreflang expects hu or en-US
        if ( isset( $lang->locale ) ) {
            $locale = $lang->locale;
            // For simple cases (hu_HU -> hu)
            $parts = explode( '_', $locale );
            if ( count( $parts ) === 2 && strtolower( $parts[0] ) === strtolower( $parts[1] ) ) {
                return strtolower( $parts[0] );
            }
            // For regional variants (en_US -> en-US, pt_BR -> pt-BR)
            return strtolower( $parts[0] ) . ( isset( $parts[1] ) ? '-' . strtoupper( $parts[1] ) : '' );
        }

        return $lang->slug;
    }
}

new RSEO_Hreflang();
