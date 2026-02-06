<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RSEO_Schema {

    public function __construct() {
        add_action( 'wp_head', [ $this, 'output_schema' ], 5 );
    }

    public function output_schema() {
        echo "\n<!-- RendanIT SEO - Schema.org Structured Data -->\n";

        // Always output Organization/LocalBusiness on all pages
        $this->output_local_business();

        // Website schema
        $this->output_website();

        // Page-specific schemas
        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            $schema_type = get_post_meta( $post_id, '_rseo_schema_type', true );

            if ( $schema_type === 'FAQPage' || get_post_meta( $post_id, '_rseo_schema_faq', true ) ) {
                $this->output_faq( $post_id );
            }

            if ( $schema_type === 'Service' ) {
                $this->output_service_page( $post_id );
            }

            if ( is_single() ) {
                $this->output_article( $post_id );
            }

            // Breadcrumb
            $this->output_breadcrumb( $post_id );
        }

        // Services from settings
        $this->output_services();

        echo "<!-- /RendanIT SEO Schema -->\n\n";
    }

    /**
     * LocalBusiness schema
     */
    private function output_local_business() {
        $name        = RendanIT_SEO::get_setting( 'schema_name' );
        $type        = RendanIT_SEO::get_setting( 'schema_type', 'LocalBusiness' );

        if ( ! $name ) return;

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => $type,
            'name'        => $name,
            'url'         => RendanIT_SEO::get_setting( 'schema_url', home_url() ),
        ];

        $description = RendanIT_SEO::get_setting( 'schema_description' );
        if ( $description ) $schema['description'] = $description;

        $image = RendanIT_SEO::get_setting( 'schema_image' );
        if ( $image ) $schema['image'] = $image;

        $phone = RendanIT_SEO::get_setting( 'schema_phone' );
        if ( $phone ) $schema['telephone'] = $phone;

        $email = RendanIT_SEO::get_setting( 'schema_email' );
        if ( $email ) $schema['email'] = $email;

        $price_range = RendanIT_SEO::get_setting( 'schema_price_range' );
        if ( $price_range ) $schema['priceRange'] = $price_range;

        // Address
        $street  = RendanIT_SEO::get_setting( 'schema_street' );
        $city    = RendanIT_SEO::get_setting( 'schema_city' );
        $zip     = RendanIT_SEO::get_setting( 'schema_zip' );
        $country = RendanIT_SEO::get_setting( 'schema_country' );

        if ( $street || $city ) {
            $schema['address'] = [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $street,
                'addressLocality' => $city,
                'postalCode'      => $zip,
                'addressCountry'  => $country,
            ];
        }

        // Geo
        $lat = RendanIT_SEO::get_setting( 'schema_lat' );
        $lng = RendanIT_SEO::get_setting( 'schema_lng' );
        if ( $lat && $lng ) {
            $schema['geo'] = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => (float) $lat,
                'longitude' => (float) $lng,
            ];
        }

        // Opening hours
        $opening = RendanIT_SEO::get_setting( 'schema_opening' );
        if ( $opening ) {
            $hours = $this->parse_opening_hours( $opening );
            if ( $hours ) {
                $schema['openingHoursSpecification'] = $hours;
            }
        }

        // Available languages (Polylang)
        if ( RendanIT_SEO::has_polylang() && function_exists( 'pll_languages_list' ) ) {
            $languages = pll_languages_list( [ 'fields' => '' ] );
            if ( count( $languages ) > 1 ) {
                $available = [];
                foreach ( $languages as $lang ) {
                    $locale = isset( $lang->locale ) ? $lang->locale : $lang->slug;
                    $available[] = str_replace( '_', '-', $locale );
                }
                $schema['availableLanguage'] = $available;
            }
        }

        $this->print_json_ld( $schema );
    }

    /**
     * WebSite schema (for sitelinks search)
     */
    private function output_website() {
        if ( ! is_front_page() && ! is_home() ) return;

        $current_lang = RendanIT_SEO::get_current_lang();

        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'WebSite',
            'name'       => RendanIT_SEO::get_setting( 'site_name', get_bloginfo( 'name' ) ),
            'url'        => home_url( '/' ),
            'inLanguage' => $current_lang,
        ];

        // Add alternate languages
        if ( RendanIT_SEO::has_polylang() && function_exists( 'pll_languages_list' ) ) {
            $languages = pll_languages_list( [ 'fields' => 'slug' ] );
            if ( count( $languages ) > 1 ) {
                $schema['availableLanguage'] = $languages;
            }
        }

        $this->print_json_ld( $schema );
    }

    /**
     * FAQ schema
     */
    private function output_faq( $post_id ) {
        $faq_json = get_post_meta( $post_id, '_rseo_schema_faq', true );
        if ( ! $faq_json ) return;

        $faqs = json_decode( $faq_json, true );
        if ( ! is_array( $faqs ) || empty( $faqs ) ) return;

        $items = [];
        foreach ( $faqs as $faq ) {
            if ( isset( $faq['question'] ) && isset( $faq['answer'] ) ) {
                $items[] = [
                    '@type'          => 'Question',
                    'name'           => $faq['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => $faq['answer'],
                    ],
                ];
            }
        }

        if ( ! empty( $items ) ) {
            $schema = [
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                'mainEntity' => $items,
            ];
            $this->print_json_ld( $schema );
        }
    }

    /**
     * Article / BlogPosting schema
     */
    private function output_article( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) return;

        $schema_type = get_post_meta( $post_id, '_rseo_schema_type', true );
        $type = ( $schema_type === 'Article' ) ? 'Article' : 'BlogPosting';

        $business_name = RendanIT_SEO::get_setting( 'schema_name', get_bloginfo( 'name' ) );
        $business_logo = RendanIT_SEO::get_setting( 'schema_image' );

        $publisher = [
            '@type' => 'Organization',
            'name'  => $business_name,
        ];
        if ( $business_logo ) {
            $publisher['logo'] = [
                '@type' => 'ImageObject',
                'url'   => $business_logo,
            ];
        }

        $schema = [
            '@context'         => 'https://schema.org',
            '@type'            => $type,
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => get_permalink( $post_id ),
            ],
            'headline'         => get_the_title( $post_id ),
            'url'              => get_permalink( $post_id ),
            'datePublished'    => get_the_date( 'c', $post_id ),
            'dateModified'     => get_the_modified_date( 'c', $post_id ),
            'author'           => [
                '@type' => 'Organization',
                'name'  => $business_name,
            ],
            'publisher'        => $publisher,
        ];

        if ( has_post_thumbnail( $post_id ) ) {
            $schema['image'] = get_the_post_thumbnail_url( $post_id, 'large' );
        }

        $desc = get_post_meta( $post_id, '_rseo_description', true );
        if ( ! $desc ) {
            $content = wp_strip_all_tags( $post->post_content );
            $content = preg_replace( '/\s+/', ' ', $content );
            $desc = mb_substr( trim( $content ), 0, 155 );
        }
        if ( $desc ) {
            $schema['description'] = $desc;
        }

        $lang = RendanIT_SEO::get_current_lang();
        if ( $lang ) {
            $schema['inLanguage'] = $lang;
        }

        $this->print_json_ld( $schema );
    }

    /**
     * Breadcrumb schema
     */
    private function output_breadcrumb( $post_id ) {
        $items = [];
        $pos   = 1;

        $site_name = RendanIT_SEO::get_setting( 'site_name', get_bloginfo( 'name' ) );
        if ( ! $site_name ) $site_name = get_bloginfo( 'name' );

        // Home - use current language home URL
        $home_url = home_url( '/' );
        if ( RendanIT_SEO::has_polylang() && function_exists( 'pll_home_url' ) ) {
            $home_url = pll_home_url();
        }

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => $site_name,
            'item'     => $home_url,
        ];

        // Post parent pages (for hierarchical)
        $post = get_post( $post_id );
        if ( $post && $post->post_parent ) {
            $ancestors = array_reverse( get_post_ancestors( $post_id ) );
            foreach ( $ancestors as $ancestor_id ) {
                $ancestor_title = get_the_title( $ancestor_id );
                if ( $ancestor_title ) {
                    $items[] = [
                        '@type'    => 'ListItem',
                        'position' => $pos++,
                        'name'     => $ancestor_title,
                        'item'     => get_permalink( $ancestor_id ),
                    ];
                }
            }
        }

        // Current page - last item should NOT have 'item' per Google spec
        $current_title = get_the_title( $post_id );
        if ( ! $current_title ) $current_title = 'Oldal';

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos,
            'name'     => $current_title,
        ];

        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];

        $this->print_json_ld( $schema );
    }

    /**
     * Service page schema (for individual pages with Service schema type)
     */
    private function output_service_page( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) return;

        $title = get_the_title( $post_id );
        if ( ! $title ) return;

        $business_name = RendanIT_SEO::get_setting( 'schema_name', get_bloginfo( 'name' ) );

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Service',
            'name'        => $title,
            'url'         => get_permalink( $post_id ),
            'provider'    => [
                '@type' => RendanIT_SEO::get_setting( 'schema_type', 'LocalBusiness' ),
                'name'  => $business_name,
            ],
        ];

        $desc = get_post_meta( $post_id, '_rseo_description', true );
        if ( $desc ) {
            $schema['description'] = $desc;
        }

        if ( has_post_thumbnail( $post_id ) ) {
            $schema['image'] = get_the_post_thumbnail_url( $post_id, 'large' );
        }

        $lang = RendanIT_SEO::get_current_lang();
        if ( $lang ) {
            $schema['inLanguage'] = $lang;
        }

        $this->print_json_ld( $schema );
    }

    /**
     * Services from global settings
     */
    private function output_services() {
        if ( ! is_front_page() && ! is_home() ) return;

        $services_json = RendanIT_SEO::get_setting( 'schema_services' );
        if ( ! $services_json ) return;

        $services = json_decode( $services_json, true );
        if ( ! is_array( $services ) || empty( $services ) ) return;

        $business_name = RendanIT_SEO::get_setting( 'schema_name' );

        foreach ( $services as $service ) {
            if ( ! isset( $service['name'] ) ) continue;

            $s = [
                '@context' => 'https://schema.org',
                '@type'    => 'Service',
                'name'     => $service['name'],
                'provider' => [
                    '@type' => RendanIT_SEO::get_setting( 'schema_type', 'LocalBusiness' ),
                    'name'  => $business_name,
                ],
            ];

            if ( isset( $service['description'] ) ) {
                $s['description'] = $service['description'];
            }

            if ( isset( $service['price'] ) && isset( $service['currency'] ) ) {
                $s['offers'] = [
                    '@type'         => 'Offer',
                    'price'         => $service['price'],
                    'priceCurrency' => $service['currency'],
                ];
            }

            $this->print_json_ld( $s );
        }
    }

    /**
     * Parse opening hours string
     */
    private function parse_opening_hours( $text ) {
        $hours = [];
        $lines = explode( "\n", $text );
        $day_map = [
            'Mo' => 'Monday', 'Tu' => 'Tuesday', 'We' => 'Wednesday',
            'Th' => 'Thursday', 'Fr' => 'Friday', 'Sa' => 'Saturday', 'Su' => 'Sunday',
        ];

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( ! $line ) continue;

            // Format: Mo-Sa 11:00-22:00
            if ( preg_match( '/^(\w{2})(?:-(\w{2}))?\s+(\d{1,2}:\d{2})-(\d{1,2}:\d{2})$/', $line, $m ) ) {
                $start_day = $m[1];
                $end_day   = $m[2] ?: $m[1];
                $opens     = $m[3];
                $closes    = $m[4];

                $days = [];
                $found = false;
                foreach ( $day_map as $abbr => $full ) {
                    if ( $abbr === $start_day ) $found = true;
                    if ( $found ) $days[] = $full;
                    if ( $abbr === $end_day ) break;
                }

                $hours[] = [
                    '@type'     => 'OpeningHoursSpecification',
                    'dayOfWeek' => $days,
                    'opens'     => $opens,
                    'closes'    => $closes,
                ];
            }
        }

        return $hours;
    }

    /**
     * Print JSON-LD script tag
     */
    private function print_json_ld( $data ) {
        echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n";
    }
}

new RSEO_Schema();
