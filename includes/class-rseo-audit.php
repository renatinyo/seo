<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RSEO_Audit {

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $posts = get_posts( [
            'post_type'      => [ 'post', 'page' ],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'lang'           => '', // All languages if Polylang
        ] );

        $issues = $this->run_audit( $posts );
        $global_issues = $this->run_global_audit();

        ?>
        <div class="wrap rseo-wrap">
            <h1><span class="dashicons dashicons-search"></span> SEO Audit</h1>

            <!-- Global Issues -->
            <div class="rseo-audit-section">
                <h2>🌐 Globális problémák</h2>
                <?php if ( empty( $global_issues ) ) : ?>
                    <p class="rseo-success">✅ Nincs globális probléma!</p>
                <?php else : ?>
                    <table class="widefat rseo-audit-table">
                        <thead>
                            <tr>
                                <th>Súlyosság</th>
                                <th>Probléma</th>
                                <th>Javaslat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $global_issues as $issue ) : ?>
                                <tr class="rseo-severity-<?php echo esc_attr( $issue['severity'] ); ?>">
                                    <td><?php echo $this->severity_badge( $issue['severity'] ); ?></td>
                                    <td><?php echo esc_html( $issue['problem'] ); ?></td>
                                    <td><?php echo esc_html( $issue['fix'] ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Summary -->
            <div class="rseo-audit-summary">
                <div class="rseo-audit-stat">
                    <span class="rseo-stat-number"><?php echo count( $posts ); ?></span>
                    <span class="rseo-stat-label">Összes oldal</span>
                </div>
                <div class="rseo-audit-stat rseo-stat-red">
                    <span class="rseo-stat-number"><?php echo $this->count_severity( $issues, 'critical' ); ?></span>
                    <span class="rseo-stat-label">Kritikus</span>
                </div>
                <div class="rseo-audit-stat rseo-stat-orange">
                    <span class="rseo-stat-number"><?php echo $this->count_severity( $issues, 'warning' ); ?></span>
                    <span class="rseo-stat-label">Figyelmeztetés</span>
                </div>
                <div class="rseo-audit-stat rseo-stat-green">
                    <span class="rseo-stat-number"><?php echo $this->count_ok( $posts, $issues ); ?></span>
                    <span class="rseo-stat-label">Rendben</span>
                </div>
            </div>

            <!-- Per-page Issues -->
            <div class="rseo-audit-section">
                <h2>📄 Oldal szintű problémák</h2>
                <?php if ( empty( $issues ) ) : ?>
                    <p class="rseo-success">✅ Minden oldal rendben van!</p>
                <?php else : ?>
                    <table class="widefat rseo-audit-table">
                        <thead>
                            <tr>
                                <th>Súlyosság</th>
                                <th>Oldal</th>
                                <th>Nyelv</th>
                                <th>Probléma</th>
                                <th>Művelet</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $issues as $issue ) : ?>
                                <tr class="rseo-severity-<?php echo esc_attr( $issue['severity'] ); ?>">
                                    <td><?php echo $this->severity_badge( $issue['severity'] ); ?></td>
                                    <td><strong><?php echo esc_html( $issue['title'] ); ?></strong></td>
                                    <td><?php echo esc_html( strtoupper( $issue['lang'] ) ); ?></td>
                                    <td><?php echo esc_html( $issue['problem'] ); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url( get_edit_post_link( $issue['post_id'] ) ); ?>" class="button button-small">
                                            Szerkesztés
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Run audit on all posts
     */
    private function run_audit( $posts ) {
        $issues = [];

        foreach ( $posts as $post ) {
            $post_id = $post->ID;
            $title   = get_post_meta( $post_id, '_rseo_title', true );
            $desc    = get_post_meta( $post_id, '_rseo_description', true );
            $focus   = get_post_meta( $post_id, '_rseo_focus_keyword', true );

            $lang = 'default';
            if ( RendanIT_SEO::has_polylang() && function_exists( 'pll_get_post_language' ) ) {
                $lang = pll_get_post_language( $post_id, 'slug' ) ?: 'default';
            }

            // Missing SEO title
            if ( ! $title ) {
                $issues[] = [
                    'post_id'  => $post_id,
                    'title'    => $post->post_title,
                    'lang'     => $lang,
                    'severity' => 'critical',
                    'problem'  => 'Hiányzó SEO title tag',
                ];
            } elseif ( mb_strlen( $title ) > 60 ) {
                $issues[] = [
                    'post_id'  => $post_id,
                    'title'    => $post->post_title,
                    'lang'     => $lang,
                    'severity' => 'warning',
                    'problem'  => 'SEO title túl hosszú (' . mb_strlen( $title ) . ' karakter, max 60)',
                ];
            } elseif ( mb_strlen( $title ) < 20 ) {
                $issues[] = [
                    'post_id'  => $post_id,
                    'title'    => $post->post_title,
                    'lang'     => $lang,
                    'severity' => 'warning',
                    'problem'  => 'SEO title túl rövid (' . mb_strlen( $title ) . ' karakter, min 20)',
                ];
            }

            // Missing meta description
            if ( ! $desc ) {
                $issues[] = [
                    'post_id'  => $post_id,
                    'title'    => $post->post_title,
                    'lang'     => $lang,
                    'severity' => 'critical',
                    'problem'  => 'Hiányzó meta description',
                ];
            } elseif ( mb_strlen( $desc ) > 155 ) {
                $issues[] = [
                    'post_id'  => $post_id,
                    'title'    => $post->post_title,
                    'lang'     => $lang,
                    'severity' => 'warning',
                    'problem'  => 'Meta description túl hosszú (' . mb_strlen( $desc ) . ' karakter, max 155)',
                ];
            } elseif ( mb_strlen( $desc ) < 50 ) {
                $issues[] = [
                    'post_id'  => $post_id,
                    'title'    => $post->post_title,
                    'lang'     => $lang,
                    'severity' => 'warning',
                    'problem'  => 'Meta description túl rövid (' . mb_strlen( $desc ) . ' karakter, min 50)',
                ];
            }

            // Missing focus keyword
            if ( ! $focus ) {
                $issues[] = [
                    'post_id'  => $post_id,
                    'title'    => $post->post_title,
                    'lang'     => $lang,
                    'severity' => 'info',
                    'problem'  => 'Nincs fókusz kulcsszó megadva',
                ];
            }

            // Focus keyword in title check
            if ( $focus && $title ) {
                if ( mb_stripos( $title, $focus ) === false ) {
                    $issues[] = [
                        'post_id'  => $post_id,
                        'title'    => $post->post_title,
                        'lang'     => $lang,
                        'severity' => 'warning',
                        'problem'  => 'Fókusz kulcsszó ("' . $focus . '") nem szerepel a title-ben',
                    ];
                }
            }

            // Focus keyword in description check
            if ( $focus && $desc ) {
                if ( mb_stripos( $desc, $focus ) === false ) {
                    $issues[] = [
                        'post_id'  => $post_id,
                        'title'    => $post->post_title,
                        'lang'     => $lang,
                        'severity' => 'info',
                        'problem'  => 'Fókusz kulcsszó nem szerepel a meta descriptionben',
                    ];
                }
            }

            // Content length check
            $content_length = mb_strlen( wp_strip_all_tags( $post->post_content ) );
            if ( $content_length < 300 ) {
                $issues[] = [
                    'post_id'  => $post_id,
                    'title'    => $post->post_title,
                    'lang'     => $lang,
                    'severity' => 'warning',
                    'problem'  => 'Kevés tartalom (' . $content_length . ' karakter, ajánlott min. 300)',
                ];
            }

            // Missing featured image
            if ( ! has_post_thumbnail( $post_id ) ) {
                $issues[] = [
                    'post_id'  => $post_id,
                    'title'    => $post->post_title,
                    'lang'     => $lang,
                    'severity' => 'info',
                    'problem'  => 'Nincs kiemelt kép (OG image-hez ajánlott)',
                ];
            }

            // Check Polylang translations exist
            if ( RendanIT_SEO::has_polylang() && function_exists( 'pll_get_post' ) && function_exists( 'pll_languages_list' ) ) {
                $languages = pll_languages_list( [ 'fields' => 'slug' ] );
                foreach ( $languages as $check_lang ) {
                    if ( $check_lang === $lang ) continue;
                    $translated = pll_get_post( $post_id, $check_lang );
                    if ( ! $translated || get_post_status( $translated ) !== 'publish' ) {
                        $issues[] = [
                            'post_id'  => $post_id,
                            'title'    => $post->post_title,
                            'lang'     => $lang,
                            'severity' => 'warning',
                            'problem'  => 'Hiányzó ' . strtoupper( $check_lang ) . ' fordítás (hreflang probléma)',
                        ];
                    }
                }
            }
        }

        // Sort by severity
        usort( $issues, function( $a, $b ) {
            $order = [ 'critical' => 0, 'warning' => 1, 'info' => 2 ];
            return ( $order[ $a['severity'] ] ?? 3 ) - ( $order[ $b['severity'] ] ?? 3 );
        });

        return $issues;
    }

    /**
     * Global audit checks
     */
    private function run_global_audit() {
        $issues = [];

        // Check schema settings
        if ( ! RendanIT_SEO::get_setting( 'schema_name' ) ) {
            $issues[] = [
                'severity' => 'critical',
                'problem'  => 'Schema.org vállalkozás név nincs beállítva',
                'fix'      => 'Menj a RendanIT SEO > Schema fülre és töltsd ki.',
            ];
        }

        if ( ! RendanIT_SEO::get_setting( 'schema_phone' ) ) {
            $issues[] = [
                'severity' => 'warning',
                'problem'  => 'Schema.org telefonszám hiányzik',
                'fix'      => 'Add meg a telefonszámot a Schema beállításoknál.',
            ];
        }

        if ( ! RendanIT_SEO::get_setting( 'schema_street' ) ) {
            $issues[] = [
                'severity' => 'warning',
                'problem'  => 'Schema.org cím (utca) hiányzik',
                'fix'      => 'Add meg a pontos címet a Schema beállításoknál.',
            ];
        }

        if ( ! RendanIT_SEO::get_setting( 'schema_lat' ) || ! RendanIT_SEO::get_setting( 'schema_lng' ) ) {
            $issues[] = [
                'severity' => 'info',
                'problem'  => 'GPS koordináták hiányoznak',
                'fix'      => 'Add meg a lat/lng koordinátákat a jobb helyi kereséshez.',
            ];
        }

        // OG image
        if ( ! RendanIT_SEO::get_setting( 'og_default_image' ) ) {
            $issues[] = [
                'severity' => 'warning',
                'problem'  => 'Alapértelmezett OG kép nincs beállítva',
                'fix'      => 'Állíts be egy 1200x630px-es képet a Social fülön.',
            ];
        }

        // Home page SEO
        if ( RendanIT_SEO::has_polylang() && function_exists( 'pll_languages_list' ) ) {
            $languages = pll_languages_list( [ 'fields' => 'slug' ] );
            foreach ( $languages as $lang ) {
                if ( ! RendanIT_SEO::get_setting( 'home_title_' . $lang ) ) {
                    $issues[] = [
                        'severity' => 'critical',
                        'problem'  => 'Főoldal SEO title hiányzik (' . strtoupper( $lang ) . ' nyelv)',
                        'fix'      => 'Állítsd be a Főoldal SEO fülön.',
                    ];
                }
                if ( ! RendanIT_SEO::get_setting( 'home_description_' . $lang ) ) {
                    $issues[] = [
                        'severity' => 'critical',
                        'problem'  => 'Főoldal meta description hiányzik (' . strtoupper( $lang ) . ' nyelv)',
                        'fix'      => 'Állítsd be a Főoldal SEO fülön.',
                    ];
                }
            }
        } else {
            if ( ! RendanIT_SEO::get_setting( 'home_title' ) ) {
                $issues[] = [
                    'severity' => 'critical',
                    'problem'  => 'Főoldal SEO title hiányzik',
                    'fix'      => 'Állítsd be a Főoldal SEO fülön.',
                ];
            }
        }

        // Sitemap
        if ( ! RendanIT_SEO::get_setting( 'sitemap_enabled', 1 ) ) {
            $issues[] = [
                'severity' => 'warning',
                'problem'  => 'XML Sitemap ki van kapcsolva',
                'fix'      => 'Kapcsold be az Indexelés fülön.',
            ];
        }

        // Opening hours
        if ( ! RendanIT_SEO::get_setting( 'schema_opening' ) ) {
            $issues[] = [
                'severity' => 'info',
                'problem'  => 'Nyitvatartás nincs megadva a Schema-ban',
                'fix'      => 'Add meg a nyitvatartást pl.: Mo-Sa 11:00-22:00',
            ];
        }

        return $issues;
    }

    private function severity_badge( $severity ) {
        $badges = [
            'critical' => '<span class="rseo-badge rseo-badge-critical">❌ Kritikus</span>',
            'warning'  => '<span class="rseo-badge rseo-badge-warning">⚠️ Figyelmeztetés</span>',
            'info'     => '<span class="rseo-badge rseo-badge-info">ℹ️ Info</span>',
        ];
        return $badges[ $severity ] ?? $severity;
    }

    private function count_severity( $issues, $severity ) {
        return count( array_filter( $issues, function( $i ) use ( $severity ) {
            return $i['severity'] === $severity;
        } ) );
    }

    private function count_ok( $posts, $issues ) {
        $problem_ids = array_unique( array_column( array_filter( $issues, function( $i ) {
            return in_array( $i['severity'], [ 'critical', 'warning' ] );
        }), 'post_id' ) );
        return count( $posts ) - count( $problem_ids );
    }
}
