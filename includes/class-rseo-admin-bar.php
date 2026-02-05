<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin Bar SEO Score Display
 * 
 * Shows SEO score in the admin bar for logged-in admins.
 * Panel loads via AJAX so zero frontend impact.
 */
class RSEO_Admin_Bar {

    public function __construct() {
        // Admin bar node
        add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_node' ], 100 );

        // Enqueue assets (admin bar + panel)
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );

        // Footer: panel HTML shell
        add_action( 'wp_footer', [ $this, 'render_panel_shell' ] );

        // AJAX endpoints
        add_action( 'wp_ajax_rseo_get_score', [ $this, 'ajax_get_score' ] );
        add_action( 'wp_ajax_rseo_get_homepage_score', [ $this, 'ajax_get_homepage_score' ] );

        // Invalidate cache on save
        add_action( 'save_post', [ 'RSEO_Score', 'invalidate_cache' ] );
    }

    /**
     * Add node to admin bar
     */
    public function add_admin_bar_node( $wp_admin_bar ) {
        if ( ! current_user_can( 'edit_posts' ) ) return;
        if ( is_admin() && ! $this->is_edit_screen() ) return;

        $post_id = $this->get_current_post_id();

        // Quick score for admin bar (from cache if available)
        $score_text = '...';
        $score_color = '#999';

        if ( $post_id ) {
            $cached = get_transient( 'rseo_score_' . $post_id );
            if ( $cached ) {
                $score_text = $cached['score'] . '/100';
                $score_color = RSEO_Score::score_color( $cached['score'] );
            }
        }

        $wp_admin_bar->add_node( [
            'id'    => 'rseo-score',
            'title' => '<span class="rseo-ab-icon">🔍</span> <span class="rseo-ab-label">SEO</span> <span class="rseo-ab-score" style="color:' . $score_color . '" id="rseo-ab-score-value">' . $score_text . '</span>',
            'href'  => '#',
            'meta'  => [
                'class'   => 'rseo-admin-bar-node',
                'onclick' => 'rseoTogglePanel(); return false;',
            ],
        ] );
    }

    /**
     * Enqueue on frontend (only for logged-in admins)
     */
    public function enqueue_frontend() {
        if ( ! current_user_can( 'edit_posts' ) || ! is_admin_bar_showing() ) return;

        wp_enqueue_style( 'rseo-panel', RSEO_PLUGIN_URL . 'admin/css/panel.css', [], RSEO_VERSION );
        wp_enqueue_script( 'rseo-panel', RSEO_PLUGIN_URL . 'admin/js/panel.js', [ 'jquery' ], RSEO_VERSION, true );

        $post_id = $this->get_current_post_id();

        wp_localize_script( 'rseo-panel', 'rseoPanel', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'rseo_score_nonce' ),
            'postId'  => $post_id,
            'isHome'  => is_front_page() || is_home(),
            'editUrl' => $post_id ? get_edit_post_link( $post_id, 'raw' ) : '',
        ] );
    }

    /**
     * Also enqueue on post edit screens
     */
    public function enqueue_admin( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ] ) ) return;

        wp_enqueue_style( 'rseo-panel', RSEO_PLUGIN_URL . 'admin/css/panel.css', [], RSEO_VERSION );
        wp_enqueue_script( 'rseo-panel', RSEO_PLUGIN_URL . 'admin/js/panel.js', [ 'jquery' ], RSEO_VERSION, true );

        global $post;
        wp_localize_script( 'rseo-panel', 'rseoPanel', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'rseo_score_nonce' ),
            'postId'  => $post ? $post->ID : 0,
            'isHome'  => false,
            'editUrl' => '',
        ] );
    }

    /**
     * Render empty panel shell in footer (filled via AJAX)
     */
    public function render_panel_shell() {
        if ( ! current_user_can( 'edit_posts' ) || ! is_admin_bar_showing() ) return;
        ?>
        <div id="rseo-panel-overlay" class="rseo-panel-hidden">
            <div id="rseo-panel">
                <div class="rseo-panel-header">
                    <h3>🔍 RendanIT SEO Elemzés</h3>
                    <button type="button" id="rseo-panel-close" onclick="rseoTogglePanel()">&times;</button>
                </div>
                <div id="rseo-panel-body">
                    <div class="rseo-panel-loading">
                        <div class="rseo-spinner"></div>
                        <p>Elemzés folyamatban...</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Get score for a post
     */
    public function ajax_get_score() {
        check_ajax_referer( 'rseo_score_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Nincs jogosultság' );
        }

        $post_id = intval( $_POST['post_id'] ?? 0 );
        $force   = ! empty( $_POST['force'] );

        if ( ! $post_id ) {
            wp_send_json_error( 'Nincs post_id' );
        }

        if ( $force ) {
            RSEO_Score::invalidate_cache( $post_id );
        }

        $result = RSEO_Score::get_score( $post_id );

        // Render HTML
        $html = $this->render_score_html( $result );

        wp_send_json_success( [
            'score' => $result['score'],
            'grade' => $result['grade'],
            'color' => RSEO_Score::score_color( $result['score'] ),
            'html'  => $html,
        ] );
    }

    /**
     * AJAX: Homepage score (checks global settings too)
     */
    public function ajax_get_homepage_score() {
        check_ajax_referer( 'rseo_score_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Nincs jogosultság' );
        }

        // Get the front page post ID
        $front_id = get_option( 'page_on_front' );
        $checks = [];
        $total = 0;
        $earned = 0;

        // Check home title per language
        if ( RendanIT_SEO::has_polylang() && function_exists( 'pll_languages_list' ) ) {
            $languages = pll_languages_list( [ 'fields' => 'slug' ] );
            foreach ( $languages as $lang ) {
                $w = 15;
                $total += $w;
                $ht = RendanIT_SEO::get_setting( 'home_title_' . $lang );
                if ( $ht ) {
                    $earned += $w;
                    $checks[] = [ 'severity' => 'good', 'message' => strtoupper($lang) . " főoldal title OK: \"{$ht}\"", 'fix' => '' ];
                } else {
                    $checks[] = [ 'severity' => 'critical', 'message' => strtoupper($lang) . ' főoldal title HIÁNYZIK!', 'fix' => 'Menj a RendanIT SEO > Főoldal SEO fülre' ];
                }

                $w = 12;
                $total += $w;
                $hd = RendanIT_SEO::get_setting( 'home_description_' . $lang );
                if ( $hd ) {
                    $len = mb_strlen( $hd );
                    if ( $len >= 120 && $len <= 155 ) {
                        $earned += $w;
                        $checks[] = [ 'severity' => 'good', 'message' => strtoupper($lang) . " meta description OK ({$len} karakter)", 'fix' => '' ];
                    } else {
                        $earned += $w * 0.6;
                        $checks[] = [ 'severity' => 'warning', 'message' => strtoupper($lang) . " meta description nem optimális ({$len} karakter)", 'fix' => 'Ajánlott: 120-155 karakter' ];
                    }
                } else {
                    $checks[] = [ 'severity' => 'critical', 'message' => strtoupper($lang) . ' meta description HIÁNYZIK!', 'fix' => 'Menj a RendanIT SEO > Főoldal SEO fülre' ];
                }
            }
        }

        // Schema checks
        $schema_checks = [
            'schema_name'    => [ 'label' => 'Schema: vállalkozás neve', 'w' => 10 ],
            'schema_phone'   => [ 'label' => 'Schema: telefonszám', 'w' => 5 ],
            'schema_street'  => [ 'label' => 'Schema: cím', 'w' => 5 ],
            'schema_opening' => [ 'label' => 'Schema: nyitvatartás', 'w' => 4 ],
            'schema_lat'     => [ 'label' => 'Schema: GPS koordináta', 'w' => 3 ],
        ];

        foreach ( $schema_checks as $key => $sc ) {
            $total += $sc['w'];
            if ( RendanIT_SEO::get_setting( $key ) ) {
                $earned += $sc['w'];
                $checks[] = [ 'severity' => 'good', 'message' => $sc['label'] . ' ✓', 'fix' => '' ];
            } else {
                $checks[] = [ 'severity' => 'warning', 'message' => $sc['label'] . ' hiányzik', 'fix' => 'Beállítás: RendanIT SEO > Schema fül' ];
            }
        }

        // OG image
        $total += 5;
        if ( RendanIT_SEO::get_setting( 'og_default_image' ) ) {
            $earned += 5;
            $checks[] = [ 'severity' => 'good', 'message' => 'Alapértelmezett OG kép beállítva ✓', 'fix' => '' ];
        } else {
            $checks[] = [ 'severity' => 'warning', 'message' => 'Alapértelmezett OG kép hiányzik', 'fix' => 'RendanIT SEO > Social fül' ];
        }

        // Sitemap
        $total += 3;
        if ( RendanIT_SEO::get_setting( 'sitemap_enabled', 1 ) ) {
            $earned += 3;
            $checks[] = [ 'severity' => 'good', 'message' => 'XML Sitemap aktív ✓', 'fix' => '' ];
        } else {
            $checks[] = [ 'severity' => 'warning', 'message' => 'XML Sitemap kikapcsolva', 'fix' => 'Kapcsold be az Indexelés fülön' ];
        }

        $score = $total > 0 ? round( ( $earned / $total ) * 100 ) : 0;

        // Sort checks
        usort( $checks, function( $a, $b ) {
            $order = [ 'critical' => 0, 'warning' => 1, 'info' => 2, 'good' => 3 ];
            return ( $order[ $a['severity'] ] ?? 4 ) - ( $order[ $b['severity'] ] ?? 4 );
        });

        $html = $this->render_home_score_html( $score, $checks );

        wp_send_json_success( [
            'score' => $score,
            'grade' => $this->score_to_grade( $score ),
            'color' => RSEO_Score::score_color( $score ),
            'html'  => $html,
        ] );
    }

    /**
     * Render score HTML for AJAX response
     */
    private function render_score_html( $result ) {
        ob_start();
        $score = $result['score'];
        $grade = $result['grade'];
        $color = RSEO_Score::score_color( $score );
        ?>
        <!-- Score Circle -->
        <div class="rseo-score-circle-wrap">
            <div class="rseo-score-circle" style="--score-color: <?php echo $color; ?>; --score-pct: <?php echo $score; ?>">
                <div class="rseo-score-inner">
                    <span class="rseo-score-number"><?php echo $score; ?></span>
                    <span class="rseo-score-grade"><?php echo $grade; ?></span>
                </div>
            </div>
            <div class="rseo-score-subtitle">
                <?php
                if ( $score >= 80 ) echo '🎉 Kiváló SEO!';
                elseif ( $score >= 55 ) echo '👍 Elfogadható, de javítható';
                elseif ( $score >= 30 ) echo '⚠️ Több javítás szükséges';
                else echo '❌ Sürgős beavatkozás kell';
                ?>
            </div>
        </div>

        <!-- Category bars -->
        <div class="rseo-category-bars">
            <?php foreach ( $result['categories'] as $cat => $data ) :
                $pct = $data['total'] > 0 ? round( ( $data['earned'] / $data['total'] ) * 100 ) : 0;
                $bar_color = RSEO_Score::score_color( $pct );
            ?>
                <div class="rseo-cat-bar">
                    <div class="rseo-cat-label">
                        <?php echo RSEO_Score::category_label( $cat ); ?>
                        <span class="rseo-cat-score"><?php echo $pct; ?>%</span>
                    </div>
                    <div class="rseo-bar-bg">
                        <div class="rseo-bar-fill" style="width:<?php echo $pct; ?>%; background:<?php echo $bar_color; ?>"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Detailed checks -->
        <div class="rseo-checks-list">
            <h4>Részletes elemzés</h4>
            <?php foreach ( $result['checks'] as $check ) : ?>
                <div class="rseo-check rseo-check-<?php echo esc_attr( $check['severity'] ); ?>">
                    <div class="rseo-check-icon">
                        <?php
                        switch ( $check['severity'] ) {
                            case 'good':     echo '✅'; break;
                            case 'critical': echo '❌'; break;
                            case 'warning':  echo '⚠️'; break;
                            case 'info':     echo 'ℹ️'; break;
                        }
                        ?>
                    </div>
                    <div class="rseo-check-content">
                        <div class="rseo-check-msg"><?php echo esc_html( $check['message'] ); ?></div>
                        <?php if ( ! empty( $check['fix'] ) ) : ?>
                            <div class="rseo-check-fix">💡 <?php echo esc_html( $check['fix'] ); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="rseo-check-points">+<?php echo $check['points']; ?>/<?php echo $check['weight']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Actions -->
        <div class="rseo-panel-actions">
            <button type="button" class="rseo-btn rseo-btn-refresh" onclick="rseoRefreshScore()">🔄 Újraellenőrzés</button>
            <?php
            $edit_url = isset( $result['post_id'] ) ? get_edit_post_link( $result['post_id'], 'raw' ) : '';
            if ( $edit_url ) :
            ?>
                <a href="<?php echo esc_url( $edit_url ); ?>" class="rseo-btn rseo-btn-edit">✏️ Szerkesztés</a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render home page score HTML
     */
    private function render_home_score_html( $score, $checks ) {
        ob_start();
        $color = RSEO_Score::score_color( $score );
        $grade = $this->score_to_grade( $score );
        ?>
        <div class="rseo-score-circle-wrap">
            <div class="rseo-score-circle" style="--score-color: <?php echo $color; ?>; --score-pct: <?php echo $score; ?>">
                <div class="rseo-score-inner">
                    <span class="rseo-score-number"><?php echo $score; ?></span>
                    <span class="rseo-score-grade"><?php echo $grade; ?></span>
                </div>
            </div>
            <div class="rseo-score-subtitle">Főoldal & Globális SEO</div>
        </div>

        <div class="rseo-checks-list">
            <h4>Főoldal & beállítások ellenőrzése</h4>
            <?php foreach ( $checks as $check ) : ?>
                <div class="rseo-check rseo-check-<?php echo esc_attr( $check['severity'] ); ?>">
                    <div class="rseo-check-icon">
                        <?php
                        switch ( $check['severity'] ) {
                            case 'good':     echo '✅'; break;
                            case 'critical': echo '❌'; break;
                            case 'warning':  echo '⚠️'; break;
                            case 'info':     echo 'ℹ️'; break;
                        }
                        ?>
                    </div>
                    <div class="rseo-check-content">
                        <div class="rseo-check-msg"><?php echo esc_html( $check['message'] ); ?></div>
                        <?php if ( ! empty( $check['fix'] ) ) : ?>
                            <div class="rseo-check-fix">💡 <?php echo esc_html( $check['fix'] ); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="rseo-panel-actions">
            <button type="button" class="rseo-btn rseo-btn-refresh" onclick="rseoRefreshScore()">🔄 Újraellenőrzés</button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rendanit-seo&tab=homepage' ) ); ?>" class="rseo-btn rseo-btn-edit">⚙️ Beállítások</a>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_current_post_id() {
        if ( is_admin() ) {
            global $post;
            return $post ? $post->ID : 0;
        }
        if ( is_singular() ) {
            return get_queried_object_id();
        }
        if ( is_front_page() || is_home() ) {
            return get_option( 'page_on_front' ) ?: 0;
        }
        return 0;
    }

    private function is_edit_screen() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        return $screen && in_array( $screen->base, [ 'post', 'edit' ] );
    }

    private function score_to_grade( $score ) {
        if ( $score >= 90 ) return 'A+';
        if ( $score >= 80 ) return 'A';
        if ( $score >= 70 ) return 'B';
        if ( $score >= 55 ) return 'C';
        if ( $score >= 40 ) return 'D';
        return 'F';
    }
}

new RSEO_Admin_Bar();
