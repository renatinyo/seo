<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Elementor Integration
 *
 * Extracts content, headings, and SEO data from Elementor pages.
 */
class RSEO_Elementor {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Only load if Elementor is active
        if ( ! $this->is_elementor_active() ) {
            return;
        }

        // Filter to enhance content extraction for score calculation
        add_filter( 'rseo_get_post_content', [ $this, 'get_elementor_content' ], 10, 2 );

        // Filter to extract H1 from Elementor
        add_filter( 'rseo_get_post_h1', [ $this, 'get_elementor_h1' ], 10, 2 );

        // Filter to extract images from Elementor
        add_filter( 'rseo_get_post_images', [ $this, 'get_elementor_images' ], 10, 2 );

        // Filter to extract links from Elementor
        add_filter( 'rseo_get_post_links', [ $this, 'get_elementor_links' ], 10, 2 );

        // Add Elementor-specific meta fields sync
        add_action( 'elementor/editor/after_save', [ $this, 'sync_elementor_seo' ], 10, 2 );

        // Invalidate cache when Elementor saves
        add_action( 'elementor/editor/after_save', [ $this, 'invalidate_score_cache' ], 10, 2 );
    }

    /**
     * Check if Elementor is active
     */
    public function is_elementor_active() {
        return defined( 'ELEMENTOR_VERSION' ) || class_exists( 'Elementor\Plugin' );
    }

    /**
     * Check if a post was built with Elementor
     */
    public function is_built_with_elementor( $post_id ) {
        return get_post_meta( $post_id, '_elementor_edit_mode', true ) === 'builder';
    }

    /**
     * Get rendered Elementor content
     */
    public function get_elementor_content( $content, $post_id ) {
        if ( ! $this->is_built_with_elementor( $post_id ) ) {
            return $content;
        }

        // Try to get Elementor data
        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );

        if ( empty( $elementor_data ) ) {
            return $content;
        }

        // Parse Elementor JSON data
        if ( is_string( $elementor_data ) ) {
            $elementor_data = json_decode( $elementor_data, true );
        }

        if ( ! is_array( $elementor_data ) ) {
            return $content;
        }

        // Extract text content from Elementor widgets
        $extracted_content = $this->extract_text_from_elements( $elementor_data );

        return $extracted_content ?: $content;
    }

    /**
     * Recursively extract text from Elementor elements
     */
    private function extract_text_from_elements( $elements ) {
        $text = '';

        if ( ! is_array( $elements ) ) {
            return $text;
        }

        foreach ( $elements as $element ) {
            // Extract from widget settings
            if ( isset( $element['settings'] ) ) {
                $text .= $this->extract_text_from_settings( $element['settings'], $element['widgetType'] ?? '' );
            }

            // Recursively process child elements
            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $text .= $this->extract_text_from_elements( $element['elements'] );
            }
        }

        return $text;
    }

    /**
     * Extract text from widget settings
     */
    private function extract_text_from_settings( $settings, $widget_type = '' ) {
        $text = '';

        // Text widgets
        $text_fields = [
            'title',
            'editor',              // Text Editor widget
            'text',
            'description',
            'content',
            'heading_title',       // Heading widget
            'testimonial_content', // Testimonial widget
            'alert_title',
            'alert_description',
            'accordion_tab_title',
            'tab_title',
            'tab_content',
            'item_description',
            'price_list_title',
            'price_list_description',
            'blockquote_content',
            'author_name',
            'inner_text',
            'button_text',
            'cta_text',
            'title_text',
            'description_text',
        ];

        foreach ( $text_fields as $field ) {
            if ( isset( $settings[ $field ] ) && is_string( $settings[ $field ] ) ) {
                $text .= ' ' . $settings[ $field ];
            }
        }

        // Handle repeater fields (like accordion, tabs, etc.)
        $repeater_fields = [ 'tabs', 'accordion', 'slides', 'price_list', 'icon_list', 'items' ];
        foreach ( $repeater_fields as $repeater ) {
            if ( isset( $settings[ $repeater ] ) && is_array( $settings[ $repeater ] ) ) {
                foreach ( $settings[ $repeater ] as $item ) {
                    if ( is_array( $item ) ) {
                        $text .= $this->extract_text_from_settings( $item, '' );
                    }
                }
            }
        }

        return $text;
    }

    /**
     * Get H1 heading from Elementor content
     */
    public function get_elementor_h1( $h1, $post_id ) {
        if ( ! $this->is_built_with_elementor( $post_id ) ) {
            return $h1;
        }

        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );

        if ( empty( $elementor_data ) ) {
            return $h1;
        }

        if ( is_string( $elementor_data ) ) {
            $elementor_data = json_decode( $elementor_data, true );
        }

        if ( ! is_array( $elementor_data ) ) {
            return $h1;
        }

        // Find H1 heading
        $found_h1 = $this->find_h1_in_elements( $elementor_data );

        return $found_h1 ?: $h1;
    }

    /**
     * Recursively find H1 heading in Elementor elements
     */
    private function find_h1_in_elements( $elements ) {
        if ( ! is_array( $elements ) ) {
            return '';
        }

        foreach ( $elements as $element ) {
            // Check heading widgets
            if ( isset( $element['widgetType'] ) && $element['widgetType'] === 'heading' ) {
                $settings = $element['settings'] ?? [];
                $tag = $settings['header_size'] ?? 'h2';

                if ( $tag === 'h1' && isset( $settings['title'] ) ) {
                    return wp_strip_all_tags( $settings['title'] );
                }
            }

            // Check theme-style heading
            if ( isset( $element['widgetType'] ) && $element['widgetType'] === 'theme-post-title' ) {
                $settings = $element['settings'] ?? [];
                $tag = $settings['header_size'] ?? 'h1';

                if ( $tag === 'h1' ) {
                    // Return post title as H1
                    return get_the_title( get_the_ID() );
                }
            }

            // Recursively search in child elements
            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $found = $this->find_h1_in_elements( $element['elements'] );
                if ( $found ) {
                    return $found;
                }
            }
        }

        return '';
    }

    /**
     * Get images from Elementor content
     */
    public function get_elementor_images( $images, $post_id ) {
        if ( ! $this->is_built_with_elementor( $post_id ) ) {
            return $images;
        }

        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );

        if ( empty( $elementor_data ) ) {
            return $images;
        }

        if ( is_string( $elementor_data ) ) {
            $elementor_data = json_decode( $elementor_data, true );
        }

        if ( ! is_array( $elementor_data ) ) {
            return $images;
        }

        // Extract images from Elementor
        $elementor_images = $this->find_images_in_elements( $elementor_data );

        return array_merge( $images, $elementor_images );
    }

    /**
     * Recursively find images in Elementor elements
     */
    private function find_images_in_elements( $elements ) {
        $images = [];

        if ( ! is_array( $elements ) ) {
            return $images;
        }

        foreach ( $elements as $element ) {
            $widget_type = $element['widgetType'] ?? '';
            $settings = $element['settings'] ?? [];

            // Image widget
            if ( $widget_type === 'image' && isset( $settings['image']['url'] ) ) {
                $images[] = [
                    'url' => $settings['image']['url'],
                    'alt' => $settings['image']['alt'] ?? '',
                    'id'  => $settings['image']['id'] ?? 0,
                ];
            }

            // Image Box widget
            if ( $widget_type === 'image-box' && isset( $settings['image']['url'] ) ) {
                $images[] = [
                    'url' => $settings['image']['url'],
                    'alt' => $settings['image']['alt'] ?? '',
                    'id'  => $settings['image']['id'] ?? 0,
                ];
            }

            // Gallery widget
            if ( $widget_type === 'image-gallery' && isset( $settings['gallery'] ) && is_array( $settings['gallery'] ) ) {
                foreach ( $settings['gallery'] as $img ) {
                    if ( isset( $img['url'] ) ) {
                        $images[] = [
                            'url' => $img['url'],
                            'alt' => $img['alt'] ?? '',
                            'id'  => $img['id'] ?? 0,
                        ];
                    }
                }
            }

            // Image Carousel widget
            if ( $widget_type === 'image-carousel' && isset( $settings['carousel'] ) && is_array( $settings['carousel'] ) ) {
                foreach ( $settings['carousel'] as $img ) {
                    if ( isset( $img['url'] ) ) {
                        $images[] = [
                            'url' => $img['url'],
                            'alt' => $img['alt'] ?? '',
                            'id'  => $img['id'] ?? 0,
                        ];
                    }
                }
            }

            // Background images in sections/columns
            if ( isset( $settings['background_image']['url'] ) && $settings['background_image']['url'] ) {
                $images[] = [
                    'url' => $settings['background_image']['url'],
                    'alt' => $settings['background_image']['alt'] ?? '',
                    'id'  => $settings['background_image']['id'] ?? 0,
                ];
            }

            // Recursively search in child elements
            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $child_images = $this->find_images_in_elements( $element['elements'] );
                $images = array_merge( $images, $child_images );
            }
        }

        return $images;
    }

    /**
     * Get links from Elementor content
     */
    public function get_elementor_links( $links, $post_id ) {
        if ( ! $this->is_built_with_elementor( $post_id ) ) {
            return $links;
        }

        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );

        if ( empty( $elementor_data ) ) {
            return $links;
        }

        if ( is_string( $elementor_data ) ) {
            $elementor_data = json_decode( $elementor_data, true );
        }

        if ( ! is_array( $elementor_data ) ) {
            return $links;
        }

        // Extract links from Elementor
        $elementor_links = $this->find_links_in_elements( $elementor_data );

        return array_merge( $links, $elementor_links );
    }

    /**
     * Recursively find links in Elementor elements
     */
    private function find_links_in_elements( $elements ) {
        $links = [];

        if ( ! is_array( $elements ) ) {
            return $links;
        }

        foreach ( $elements as $element ) {
            $widget_type = $element['widgetType'] ?? '';
            $settings = $element['settings'] ?? [];

            // Button widget
            if ( $widget_type === 'button' && isset( $settings['link']['url'] ) && $settings['link']['url'] ) {
                $links[] = $settings['link']['url'];
            }

            // Image widget link
            if ( $widget_type === 'image' && isset( $settings['link']['url'] ) && $settings['link']['url'] ) {
                $links[] = $settings['link']['url'];
            }

            // Call to Action widget
            if ( $widget_type === 'call-to-action' && isset( $settings['link']['url'] ) && $settings['link']['url'] ) {
                $links[] = $settings['link']['url'];
            }

            // Icon Box widget
            if ( $widget_type === 'icon-box' && isset( $settings['link']['url'] ) && $settings['link']['url'] ) {
                $links[] = $settings['link']['url'];
            }

            // Check for links in text content (editor widget)
            if ( isset( $settings['editor'] ) && is_string( $settings['editor'] ) ) {
                preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $settings['editor'], $matches );
                if ( ! empty( $matches[1] ) ) {
                    $links = array_merge( $links, $matches[1] );
                }
            }

            // Recursively search in child elements
            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $child_links = $this->find_links_in_elements( $element['elements'] );
                $links = array_merge( $links, $child_links );
            }
        }

        return $links;
    }

    /**
     * Sync Elementor SEO settings to RendanIT SEO
     */
    public function sync_elementor_seo( $post_id, $editor_data ) {
        // Check if Elementor Pro SEO is set
        $elementor_meta = get_post_meta( $post_id, '_elementor_page_settings', true );

        if ( ! is_array( $elementor_meta ) ) {
            return;
        }

        // Sync meta title if set in Elementor and not in RendanIT SEO
        if ( ! empty( $elementor_meta['meta_title'] ) && empty( get_post_meta( $post_id, '_rseo_title', true ) ) ) {
            update_post_meta( $post_id, '_rseo_title', sanitize_text_field( $elementor_meta['meta_title'] ) );
        }

        // Sync meta description
        if ( ! empty( $elementor_meta['meta_description'] ) && empty( get_post_meta( $post_id, '_rseo_description', true ) ) ) {
            update_post_meta( $post_id, '_rseo_description', sanitize_textarea_field( $elementor_meta['meta_description'] ) );
        }
    }

    /**
     * Invalidate score cache when Elementor saves
     */
    public function invalidate_score_cache( $post_id, $editor_data ) {
        if ( class_exists( 'RSEO_Score' ) ) {
            RSEO_Score::invalidate_cache( $post_id );
        }
    }

    /**
     * Get all text content from an Elementor page (static helper)
     */
    public static function get_page_content( $post_id ) {
        $instance = self::instance();

        if ( ! $instance->is_built_with_elementor( $post_id ) ) {
            return '';
        }

        return $instance->get_elementor_content( '', $post_id );
    }

    /**
     * Get page H1 (static helper)
     */
    public static function get_page_h1( $post_id ) {
        $instance = self::instance();

        if ( ! $instance->is_built_with_elementor( $post_id ) ) {
            return '';
        }

        return $instance->get_elementor_h1( '', $post_id );
    }
}

// Initialize
RSEO_Elementor::instance();
