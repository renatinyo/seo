<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SEO Score Calculator
 * 
 * Calculates a 0-100 SEO score per page/post with detailed
 * issue reporting. Results are cached in transients for speed.
 * 
 * Reusable across any WordPress site - no hardcoded values.
 */
class RSEO_Score {

    /**
     * All check definitions with weights
     * Total weight = 100
     */
    private static $checks = [
        // Critical (high weight)
        'title_exists'         => [ 'weight' => 12, 'category' => 'meta',     'severity' => 'critical' ],
        'title_length'         => [ 'weight' => 6,  'category' => 'meta',     'severity' => 'warning'  ],
        'description_exists'   => [ 'weight' => 12, 'category' => 'meta',     'severity' => 'critical' ],
        'description_length'   => [ 'weight' => 5,  'category' => 'meta',     'severity' => 'warning'  ],
        'h1_exists'            => [ 'weight' => 8,  'category' => 'content',  'severity' => 'critical' ],
        'content_length'       => [ 'weight' => 8,  'category' => 'content',  'severity' => 'warning'  ],

        // Important
        'focus_keyword_set'    => [ 'weight' => 5,  'category' => 'keyword',  'severity' => 'warning'  ],
        'keyword_in_title'     => [ 'weight' => 7,  'category' => 'keyword',  'severity' => 'warning'  ],
        'keyword_in_desc'      => [ 'weight' => 4,  'category' => 'keyword',  'severity' => 'info'     ],
        'keyword_in_content'   => [ 'weight' => 5,  'category' => 'keyword',  'severity' => 'warning'  ],
        'keyword_in_url'       => [ 'weight' => 4,  'category' => 'keyword',  'severity' => 'info'     ],
        'keyword_density'      => [ 'weight' => 3,  'category' => 'keyword',  'severity' => 'info'     ],

        // Technical
        'has_featured_image'   => [ 'weight' => 4,  'category' => 'media',    'severity' => 'warning'  ],
        'images_have_alt'      => [ 'weight' => 4,  'category' => 'media',    'severity' => 'warning'  ],
        'has_internal_links'   => [ 'weight' => 3,  'category' => 'links',    'severity' => 'info'     ],
        'has_external_links'   => [ 'weight' => 2,  'category' => 'links',    'severity' => 'info'     ],
        'url_length'           => [ 'weight' => 3,  'category' => 'technical','severity' => 'info'     ],
        'has_schema'           => [ 'weight' => 3,  'category' => 'technical','severity' => 'info'     ],
        'has_og_image'         => [ 'weight' => 2,  'category' => 'social',   'severity' => 'info'     ],
    ];

    /**
     * Get score for a post (cached)
     */
    public static function get_score( $post_id ) {
        $cache_key = 'rseo_score_' . $post_id;
        $cached = get_transient( $cache_key );

        if ( $cached !== false ) {
            return $cached;
        }

        $result = self::calculate( $post_id );

        // Cache for 12 hours or until post is updated
        set_transient( $cache_key, $result, 12 * HOUR_IN_SECONDS );

        return $result;
    }

    /**
     * Invalidate cache when post is saved
     */
    public static function invalidate_cache( $post_id ) {
        delete_transient( 'rseo_score_' . $post_id );
    }

    /**
     * Calculate full score
     * Returns: [ 'score' => int, 'grade' => string, 'checks' => array, 'summary' => array ]
     */
    public static function calculate( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return [ 'score' => 0, 'grade' => 'F', 'checks' => [], 'summary' => [] ];
        }

        // Gather all data once
        $data = self::gather_data( $post );

        $total_score = 0;
        $checks_result = [];

        foreach ( self::$checks as $check_id => $check_meta ) {
            $method = 'check_' . $check_id;
            if ( ! method_exists( __CLASS__, $method ) ) continue;

            $result = self::$method( $data );
            // $result = [ 'pass' => bool|float(0-1), 'message' => string, 'fix' => string ]

            $pass_value = is_bool( $result['pass'] ) ? ( $result['pass'] ? 1.0 : 0.0 ) : floatval( $result['pass'] );
            $points = round( $check_meta['weight'] * $pass_value );
            $total_score += $points;

            $checks_result[] = [
                'id'        => $check_id,
                'category'  => $check_meta['category'],
                'severity'  => $result['pass'] === true || $pass_value >= 0.8 ? 'good' : $check_meta['severity'],
                'weight'    => $check_meta['weight'],
                'points'    => $points,
                'pass'      => $pass_value,
                'message'   => $result['message'],
                'fix'       => $result['fix'] ?? '',
            ];
        }

        // Sort: failures first
        usort( $checks_result, function( $a, $b ) {
            $order = [ 'critical' => 0, 'warning' => 1, 'info' => 2, 'good' => 3 ];
            return ( $order[ $a['severity'] ] ?? 4 ) - ( $order[ $b['severity'] ] ?? 4 );
        });

        // Category summary
        $categories = [];
        foreach ( $checks_result as $c ) {
            $cat = $c['category'];
            if ( ! isset( $categories[ $cat ] ) ) {
                $categories[ $cat ] = [ 'earned' => 0, 'total' => 0, 'issues' => 0 ];
            }
            $categories[ $cat ]['earned'] += $c['points'];
            $categories[ $cat ]['total']  += $c['weight'];
            if ( $c['severity'] !== 'good' ) {
                $categories[ $cat ]['issues']++;
            }
        }

        return [
            'score'      => min( 100, max( 0, $total_score ) ),
            'grade'      => self::score_to_grade( $total_score ),
            'checks'     => $checks_result,
            'categories' => $categories,
            'post_id'    => $post_id,
            'timestamp'  => time(),
        ];
    }

    /**
     * Gather all needed data in one pass (performance)
     */
    private static function gather_data( $post ) {
        $content_raw  = $post->post_content;
        $content_text = wp_strip_all_tags( $content_raw );
        $content_text = str_replace( [ "\n", "\r", "\t" ], ' ', $content_text );
        $content_text = preg_replace( '/\s+/', ' ', $content_text );
        $content_text = trim( $content_text );

        $sep  = RendanIT_SEO::get_setting( 'title_separator', '|' );
        $site = RendanIT_SEO::get_setting( 'site_name', get_bloginfo( 'name' ) );

        $seo_title = get_post_meta( $post->ID, '_rseo_title', true );
        $effective_title = $seo_title ?: ( $post->post_title . ' ' . $sep . ' ' . $site );

        $seo_desc  = get_post_meta( $post->ID, '_rseo_description', true );
        $focus_kw  = get_post_meta( $post->ID, '_rseo_focus_keyword', true );
        $permalink = get_permalink( $post->ID );
        $slug      = basename( untrailingslashit( parse_url( $permalink, PHP_URL_PATH ) ) );

        // Count images & alt texts
        preg_match_all( '/<img[^>]+>/i', $content_raw, $img_matches );
        $images = $img_matches[0] ?? [];
        $images_without_alt = 0;
        foreach ( $images as $img ) {
            if ( ! preg_match( '/alt=["\'][^"\']+["\']/', $img ) ) {
                $images_without_alt++;
            }
        }

        // Links
        preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content_raw, $link_matches );
        $links = $link_matches[1] ?? [];
        $home  = home_url();
        $internal_links = 0;
        $external_links = 0;
        foreach ( $links as $link ) {
            if ( strpos( $link, $home ) === 0 || strpos( $link, '/' ) === 0 ) {
                $internal_links++;
            } elseif ( strpos( $link, 'http' ) === 0 ) {
                $external_links++;
            }
        }

        // H1 tags
        preg_match_all( '/<h1[^>]*>(.*?)<\/h1>/is', $content_raw, $h1_matches );

        return [
            'post'               => $post,
            'post_id'            => $post->ID,
            'seo_title'          => $seo_title,
            'effective_title'    => $effective_title,
            'seo_desc'           => $seo_desc,
            'focus_kw'           => $focus_kw,
            'content_raw'        => $content_raw,
            'content_text'       => $content_text,
            'content_length'     => mb_strlen( $content_text ),
            'word_count'         => str_word_count( $content_text ),
            'permalink'          => $permalink,
            'slug'               => $slug,
            'images'             => $images,
            'images_count'       => count( $images ),
            'images_without_alt' => $images_without_alt,
            'internal_links'     => $internal_links,
            'external_links'     => $external_links,
            'h1_tags'            => $h1_matches[1] ?? [],
            'has_thumbnail'      => has_post_thumbnail( $post->ID ),
            'og_image'           => get_post_meta( $post->ID, '_rseo_og_image', true ),
            'schema_type'        => get_post_meta( $post->ID, '_rseo_schema_type', true ),
            'schema_faq'         => get_post_meta( $post->ID, '_rseo_schema_faq', true ),
        ];
    }

    // ========== CHECK METHODS ==========

    private static function check_title_exists( $d ) {
        if ( $d['seo_title'] ) {
            return [ 'pass' => true, 'message' => 'Egyedi SEO title be van állítva' ];
        }
        return [
            'pass'    => false,
            'message' => 'Nincs egyedi SEO title – a Google automatikus címet generál',
            'fix'     => 'Adj meg egyedi SEO title-t a RendanIT SEO metaboxban',
        ];
    }

    private static function check_title_length( $d ) {
        $len = mb_strlen( $d['effective_title'] );
        if ( $len >= 30 && $len <= 60 ) {
            return [ 'pass' => true, 'message' => "SEO title optimális ({$len} karakter)" ];
        }
        if ( $len > 60 ) {
            $over = $len - 60;
            return [
                'pass'    => 0.5,
                'message' => "SEO title túl hosszú ({$len} karakter, {$over} karakterrel több a kelleténél)",
                'fix'     => 'Rövidítsd 60 karakter alá – a Google levágja a végét',
            ];
        }
        if ( $len < 20 && $len > 0 ) {
            return [
                'pass'    => 0.5,
                'message' => "SEO title túl rövid ({$len} karakter)",
                'fix'     => 'Bővítsd ki releváns kulcsszavakkal (ajánlott: 30-60 karakter)',
            ];
        }
        return [
            'pass'    => 0.3,
            'message' => "SEO title nem optimális ({$len} karakter)",
            'fix'     => 'Ajánlott hossz: 30-60 karakter',
        ];
    }

    private static function check_description_exists( $d ) {
        if ( $d['seo_desc'] ) {
            return [ 'pass' => true, 'message' => 'Meta description be van állítva' ];
        }
        return [
            'pass'    => false,
            'message' => 'Nincs meta description – a Google random szöveget húz ki az oldalról',
            'fix'     => 'Írj 120-155 karakter közötti, kulcsszó-gazdag meta description-t',
        ];
    }

    private static function check_description_length( $d ) {
        if ( ! $d['seo_desc'] ) {
            return [ 'pass' => 0, 'message' => 'Nincs meta description', 'fix' => 'Állíts be meta description-t' ];
        }
        $len = mb_strlen( $d['seo_desc'] );
        if ( $len >= 120 && $len <= 155 ) {
            return [ 'pass' => true, 'message' => "Meta description tökéletes ({$len} karakter)" ];
        }
        if ( $len > 155 ) {
            return [
                'pass'    => 0.6,
                'message' => "Meta description túl hosszú ({$len}/155 karakter)",
                'fix'     => 'Rövidítsd 155 karakter alá',
            ];
        }
        if ( $len >= 50 ) {
            return [
                'pass'    => 0.7,
                'message' => "Meta description lehetne hosszabb ({$len}/155 karakter)",
                'fix'     => 'Ajánlott: 120-155 karakter a jobb CTR-hez',
            ];
        }
        return [
            'pass'    => 0.4,
            'message' => "Meta description túl rövid ({$len} karakter)",
            'fix'     => 'Bővítsd ki – ajánlott 120-155 karakter',
        ];
    }

    private static function check_h1_exists( $d ) {
        // WordPress post title is typically H1
        if ( $d['post']->post_title ) {
            return [ 'pass' => true, 'message' => 'H1 cím megvan (bejegyzés cím)' ];
        }
        return [
            'pass'    => false,
            'message' => 'Nincs H1 cím',
            'fix'     => 'Adj meg címet a bejegyzésnek',
        ];
    }

    private static function check_content_length( $d ) {
        $words = $d['word_count'];
        if ( $words >= 300 ) {
            return [ 'pass' => true, 'message' => "Tartalom megfelelő ({$words} szó)" ];
        }
        if ( $words >= 150 ) {
            return [
                'pass'    => 0.6,
                'message' => "Tartalom lehetne bővebb ({$words} szó)",
                'fix'     => 'Bővítsd min. 300 szóra – a hosszabb tartalom általában jobban rangsorol',
            ];
        }
        if ( $words >= 50 ) {
            return [
                'pass'    => 0.3,
                'message' => "Kevés tartalom ({$words} szó)",
                'fix'     => 'Írj min. 300 szó tartalmat releváns kulcsszavakkal',
            ];
        }
        return [
            'pass'    => 0,
            'message' => "Nagyon kevés vagy nincs tartalom ({$words} szó)",
            'fix'     => 'Tölts fel érdemi tartalmat – a Google a tartalmas oldalakat részesíti előnyben',
        ];
    }

    private static function check_focus_keyword_set( $d ) {
        if ( $d['focus_kw'] ) {
            return [ 'pass' => true, 'message' => "Fókusz kulcsszó: \"{$d['focus_kw']}\"" ];
        }
        return [
            'pass'    => false,
            'message' => 'Nincs fókusz kulcsszó megadva',
            'fix'     => 'Adj meg egy fő kulcsszót amire optimalizálni akarod az oldalt',
        ];
    }

    private static function check_keyword_in_title( $d ) {
        if ( ! $d['focus_kw'] ) {
            return [ 'pass' => 0, 'message' => 'Nincs fókusz kulcsszó', 'fix' => 'Adj meg fókusz kulcsszót' ];
        }
        if ( mb_stripos( $d['effective_title'], $d['focus_kw'] ) !== false ) {
            return [ 'pass' => true, 'message' => 'Fókusz kulcsszó szerepel a title-ben ✓' ];
        }
        return [
            'pass'    => false,
            'message' => "A \"{$d['focus_kw']}\" kulcsszó NINCS benne a title-ben",
            'fix'     => 'Építsd be a fókusz kulcsszót a SEO title elejébe',
        ];
    }

    private static function check_keyword_in_desc( $d ) {
        if ( ! $d['focus_kw'] || ! $d['seo_desc'] ) {
            return [ 'pass' => 0, 'message' => 'Hiányzó kulcsszó vagy description', 'fix' => 'Állítsd be mindkettőt' ];
        }
        if ( mb_stripos( $d['seo_desc'], $d['focus_kw'] ) !== false ) {
            return [ 'pass' => true, 'message' => 'Fókusz kulcsszó szerepel a meta descriptionben ✓' ];
        }
        return [
            'pass'    => false,
            'message' => 'Fókusz kulcsszó nincs a meta descriptionben',
            'fix'     => 'Építsd be természetesen a meta leírásba – emeli a CTR-t',
        ];
    }

    private static function check_keyword_in_content( $d ) {
        if ( ! $d['focus_kw'] ) {
            return [ 'pass' => 0, 'message' => 'Nincs fókusz kulcsszó' ];
        }
        $count = mb_substr_count( mb_strtolower( $d['content_text'] ), mb_strtolower( $d['focus_kw'] ) );
        if ( $count >= 3 ) {
            return [ 'pass' => true, 'message' => "Fókusz kulcsszó {$count}x szerepel a tartalomban ✓" ];
        }
        if ( $count >= 1 ) {
            return [
                'pass'    => 0.6,
                'message' => "Fókusz kulcsszó csak {$count}x szerepel a tartalomban",
                'fix'     => 'Használd természetesen legalább 3-5x a szövegben',
            ];
        }
        return [
            'pass'    => false,
            'message' => 'Fókusz kulcsszó NEM szerepel a tartalomban!',
            'fix'     => 'Építsd be a szövegbe természetesen, többször is',
        ];
    }

    private static function check_keyword_in_url( $d ) {
        if ( ! $d['focus_kw'] ) {
            return [ 'pass' => 0, 'message' => 'Nincs fókusz kulcsszó' ];
        }
        $kw_slug = sanitize_title( $d['focus_kw'] );
        // Check if any part of the keyword slug is in the URL slug
        $kw_parts = explode( '-', $kw_slug );
        $matches = 0;
        foreach ( $kw_parts as $part ) {
            if ( $part && mb_stripos( $d['slug'], $part ) !== false ) {
                $matches++;
            }
        }
        $ratio = count( $kw_parts ) > 0 ? $matches / count( $kw_parts ) : 0;

        if ( $ratio >= 0.5 ) {
            return [ 'pass' => true, 'message' => 'Fókusz kulcsszó (vagy része) szerepel az URL-ben ✓' ];
        }
        return [
            'pass'    => false,
            'message' => 'Fókusz kulcsszó nincs az URL slug-ban',
            'fix'     => 'Ha lehetséges, módosítsd az URL slug-ot hogy tartalmazza a kulcsszót',
        ];
    }

    private static function check_keyword_density( $d ) {
        if ( ! $d['focus_kw'] || $d['word_count'] < 50 ) {
            return [ 'pass' => 0, 'message' => 'Nem mérhető (nincs kulcsszó vagy kevés tartalom)' ];
        }

        $count = mb_substr_count( mb_strtolower( $d['content_text'] ), mb_strtolower( $d['focus_kw'] ) );
        $kw_words = str_word_count( $d['focus_kw'] );
        $density = ( $count * $kw_words / $d['word_count'] ) * 100;
        $density_str = number_format( $density, 1 );

        if ( $density >= 0.5 && $density <= 2.5 ) {
            return [ 'pass' => true, 'message' => "Kulcsszó sűrűség optimális ({$density_str}%)" ];
        }
        if ( $density > 2.5 ) {
            return [
                'pass'    => 0.5,
                'message' => "Kulcsszó sűrűség túl magas ({$density_str}%) – keyword stuffing veszély",
                'fix'     => 'Csökkentsd a kulcsszó ismétlését, használj szinonimákat',
            ];
        }
        return [
            'pass'    => 0.4,
            'message' => "Kulcsszó sűrűség alacsony ({$density_str}%)",
            'fix'     => 'Használd többször a kulcsszót (ajánlott: 0.5-2.5%)',
        ];
    }

    private static function check_has_featured_image( $d ) {
        if ( $d['has_thumbnail'] ) {
            return [ 'pass' => true, 'message' => 'Van kiemelt kép ✓' ];
        }
        return [
            'pass'    => false,
            'message' => 'Nincs kiemelt kép',
            'fix'     => 'Állíts be kiemelt képet – fontos a social megosztáshoz és a vizuális megjelenéshez',
        ];
    }

    private static function check_images_have_alt( $d ) {
        if ( $d['images_count'] === 0 ) {
            return [
                'pass'    => 0.5,
                'message' => 'Nincs kép a tartalomban',
                'fix'     => 'Adj hozzá releváns képeket a tartalomhoz',
            ];
        }
        if ( $d['images_without_alt'] === 0 ) {
            return [ 'pass' => true, 'message' => "Minden kép ({$d['images_count']}db) rendelkezik alt szöveggel ✓" ];
        }
        $ratio = 1 - ( $d['images_without_alt'] / $d['images_count'] );
        return [
            'pass'    => $ratio,
            'message' => "{$d['images_without_alt']}/{$d['images_count']} kép alt szöveg nélkül",
            'fix'     => 'Adj alt szöveget minden képhez – fontos az akadálymentesítéshez és SEO-hoz',
        ];
    }

    private static function check_has_internal_links( $d ) {
        if ( $d['internal_links'] >= 2 ) {
            return [ 'pass' => true, 'message' => "Belső linkek: {$d['internal_links']}db ✓" ];
        }
        if ( $d['internal_links'] === 1 ) {
            return [
                'pass'    => 0.6,
                'message' => 'Csak 1 belső link van',
                'fix'     => 'Adj hozzá még belső linkeket releváns oldalakra',
            ];
        }
        return [
            'pass'    => false,
            'message' => 'Nincs belső link a tartalomban',
            'fix'     => 'Linkelj be más releváns oldalakat – erősíti a belső link struktúrát',
        ];
    }

    private static function check_has_external_links( $d ) {
        if ( $d['external_links'] >= 1 ) {
            return [ 'pass' => true, 'message' => "Külső linkek: {$d['external_links']}db ✓" ];
        }
        return [
            'pass'    => false,
            'message' => 'Nincs külső link',
            'fix'     => 'Releváns külső források linkelése növeli a megbízhatóságot',
        ];
    }

    private static function check_url_length( $d ) {
        $url_path = parse_url( $d['permalink'], PHP_URL_PATH );
        $len = strlen( $url_path );
        if ( $len <= 75 ) {
            return [ 'pass' => true, 'message' => "URL hossz rendben ({$len} karakter)" ];
        }
        if ( $len <= 100 ) {
            return [
                'pass'    => 0.7,
                'message' => "URL kicsit hosszú ({$len} karakter)",
                'fix'     => 'Próbáld rövidíteni az URL slug-ot',
            ];
        }
        return [
            'pass'    => 0.3,
            'message' => "URL túl hosszú ({$len} karakter)",
            'fix'     => 'Rövidítsd le az URL-t – az ideális 75 karakter alatt',
        ];
    }

    private static function check_has_schema( $d ) {
        $has_global = (bool) RendanIT_SEO::get_setting( 'schema_name' );
        $has_page = (bool) $d['schema_type'] || (bool) $d['schema_faq'];

        if ( $has_global && $has_page ) {
            return [ 'pass' => true, 'message' => 'Schema markup beállítva (globális + oldal szintű) ✓' ];
        }
        if ( $has_global ) {
            return [ 'pass' => 0.7, 'message' => 'Globális schema van, de nincs oldal-specifikus', 'fix' => 'Adj hozzá FAQ vagy Service schemát ha releváns' ];
        }
        return [
            'pass'    => false,
            'message' => 'Nincs schema markup beállítva',
            'fix'     => 'Állítsd be a globális Schema-t a RendanIT SEO > Schema fülön',
        ];
    }

    private static function check_has_og_image( $d ) {
        if ( $d['og_image'] || $d['has_thumbnail'] || RendanIT_SEO::get_setting( 'og_default_image' ) ) {
            return [ 'pass' => true, 'message' => 'Open Graph kép elérhető ✓' ];
        }
        return [
            'pass'    => false,
            'message' => 'Nincs OG kép (social megosztás kép)',
            'fix'     => 'Állíts be kiemelt képet vagy egyedi OG képet (1200x630px)',
        ];
    }

    // ========== UTILITY ==========

    private static function score_to_grade( $score ) {
        if ( $score >= 90 ) return 'A+';
        if ( $score >= 80 ) return 'A';
        if ( $score >= 70 ) return 'B';
        if ( $score >= 55 ) return 'C';
        if ( $score >= 40 ) return 'D';
        return 'F';
    }

    /**
     * Get color for score
     */
    public static function score_color( $score ) {
        if ( $score >= 80 ) return '#00a32a';
        if ( $score >= 55 ) return '#dba617';
        if ( $score >= 30 ) return '#e65100';
        return '#d63638';
    }

    /**
     * Get label for category
     */
    public static function category_label( $cat ) {
        $labels = [
            'meta'      => '📝 Meta Tagek',
            'content'   => '📄 Tartalom',
            'keyword'   => '🔑 Kulcsszavak',
            'media'     => '🖼️ Képek & Média',
            'links'     => '🔗 Linkek',
            'technical' => '⚙️ Technikai',
            'social'    => '📱 Social',
        ];
        return $labels[ $cat ] ?? ucfirst( $cat );
    }
}
