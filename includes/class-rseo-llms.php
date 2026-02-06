<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LLMS.txt Generator
 *
 * Generates an llms.txt file for AI systems to understand the website.
 * This helps AI assistants (ChatGPT, Claude, etc.) know about the site.
 */
class RSEO_LLMS {

    public function __construct() {
        // Serve llms.txt via rewrite
        add_action( 'init', [ $this, 'add_rewrite_rules' ] );
        add_action( 'template_redirect', [ $this, 'serve_llms_txt' ] );
        add_filter( 'query_vars', [ $this, 'add_query_vars' ] );

        // Check rewrite rules
        add_action( 'admin_init', [ $this, 'maybe_flush_rewrite_rules' ] );

        // Admin settings
        add_action( 'rseo_settings_tabs', [ $this, 'add_settings_tab' ] );
        add_action( 'rseo_settings_content', [ $this, 'render_settings' ] );
    }

    public function add_rewrite_rules() {
        add_rewrite_rule( 'llms\.txt$', 'index.php?rseo_llms=1', 'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'rseo_llms';
        return $vars;
    }

    public function maybe_flush_rewrite_rules() {
        $rules = get_option( 'rewrite_rules' );
        if ( ! isset( $rules['llms\.txt$'] ) ) {
            flush_rewrite_rules();
        }
    }

    /**
     * Serve llms.txt content
     */
    public function serve_llms_txt() {
        if ( ! get_query_var( 'rseo_llms' ) ) {
            return;
        }

        header( 'Content-Type: text/plain; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex' );

        echo $this->generate_llms_txt();
        exit;
    }

    /**
     * Generate llms.txt content
     */
    public function generate_llms_txt() {
        $settings = get_option( 'rseo_settings', [] );

        // Get custom content if set
        $custom_content = $settings['llms_content'] ?? '';
        if ( $custom_content ) {
            return $custom_content;
        }

        // Auto-generate from site data
        $site_name = get_bloginfo( 'name' );
        $site_desc = get_bloginfo( 'description' );
        $site_url = home_url();

        // Schema data
        $schema_name = $settings['schema_name'] ?? $site_name;
        $schema_desc = $settings['schema_description'] ?? $site_desc;
        $schema_type = $settings['schema_type'] ?? 'Organization';
        $schema_phone = $settings['schema_phone'] ?? '';
        $schema_email = $settings['schema_email'] ?? '';
        $schema_city = $settings['schema_city'] ?? '';
        $schema_country = $settings['schema_country'] ?? '';
        $schema_street = $settings['schema_street'] ?? '';

        $txt = "# {$site_name}\n\n";

        if ( $site_desc ) {
            $txt .= "> {$site_desc}\n\n";
        }

        // Business info
        if ( $schema_type && $schema_type !== 'Organization' ) {
            $txt .= "## Üzlet típusa\n\n";
            $txt .= "{$schema_type}\n\n";
        }

        if ( $schema_desc && $schema_desc !== $site_desc ) {
            $txt .= "## Leírás\n\n";
            $txt .= "{$schema_desc}\n\n";
        }

        // Contact
        $has_contact = $schema_phone || $schema_email;
        if ( $has_contact ) {
            $txt .= "## Kapcsolat\n\n";
            if ( $schema_phone ) {
                $txt .= "- Telefon: {$schema_phone}\n";
            }
            if ( $schema_email ) {
                $txt .= "- Email: {$schema_email}\n";
            }
            $txt .= "\n";
        }

        // Address
        $has_address = $schema_street || $schema_city;
        if ( $has_address ) {
            $txt .= "## Cím\n\n";
            if ( $schema_street ) {
                $txt .= "{$schema_street}\n";
            }
            if ( $schema_city ) {
                $txt .= "{$schema_city}";
                if ( $schema_country ) {
                    $txt .= ", {$schema_country}";
                }
                $txt .= "\n";
            }
            $txt .= "\n";
        }

        // Opening hours
        $opening = $settings['schema_opening'] ?? '';
        if ( $opening ) {
            $txt .= "## Nyitvatartás\n\n";
            $txt .= "{$opening}\n\n";
        }

        // Services/Pages
        $txt .= "## Főbb oldalak\n\n";

        // Get main pages
        $pages = get_pages( [
            'sort_column' => 'menu_order',
            'number'      => 10,
            'post_status' => 'publish',
        ] );

        foreach ( $pages as $page ) {
            $title = $page->post_title;
            $url = get_permalink( $page->ID );
            $excerpt = get_post_meta( $page->ID, '_rseo_description', true );
            if ( ! $excerpt ) {
                $excerpt = wp_trim_words( $page->post_content, 15, '...' );
            }

            $txt .= "- [{$title}]({$url})";
            if ( $excerpt ) {
                $txt .= " - {$excerpt}";
            }
            $txt .= "\n";
        }

        $txt .= "\n## Weboldal\n\n";
        $txt .= "- URL: {$site_url}\n";

        // Language
        $lang = get_locale();
        $lang_name = $lang === 'hu_HU' ? 'Magyar' : $lang;
        $txt .= "- Nyelv: {$lang_name}\n";

        return $txt;
    }

    /**
     * Add settings tab
     */
    public function add_settings_tab( $tabs ) {
        $tabs['llms'] = '🤖 AI (llms.txt)';
        return $tabs;
    }

    /**
     * Render settings
     */
    public function render_settings( $active_tab ) {
        if ( $active_tab !== 'llms' ) {
            return;
        }

        $settings = get_option( 'rseo_settings', [] );
        $llms_content = $settings['llms_content'] ?? '';
        $llms_url = home_url( '/llms.txt' );
        ?>
        <div class="rseo-settings-section">
            <h2>🤖 AI Felismerés (llms.txt)</h2>

            <p class="description">
                Az <code>llms.txt</code> egy új szabvány ami segít az AI rendszereknek (ChatGPT, Claude, Gemini)
                megérteni a weboldalad tartalmát. Ha valaki megkérdezi az AI-t a cégedről, az AI tudni fog róla.
            </p>

            <div class="rseo-info-box" style="background:#e7f5ff;padding:15px;border-radius:8px;margin:15px 0;">
                <strong>📍 Az llms.txt elérhető itt:</strong><br>
                <a href="<?php echo esc_url( $llms_url ); ?>" target="_blank"><?php echo esc_html( $llms_url ); ?></a>
            </div>

            <h3>Automatikus generálás</h3>
            <p>Ha üresen hagyod az alábbi mezőt, a plugin automatikusan generálja a tartalmat a Schema beállításokból és az oldalakból.</p>

            <h3>Egyedi tartalom (opcionális)</h3>
            <p class="description">Ha szeretnéd teljesen testre szabni, írd be ide:</p>

            <textarea name="rseo_settings[llms_content]" rows="20" class="large-text code"
                placeholder="Hagyd üresen az automatikus generáláshoz..."><?php echo esc_textarea( $llms_content ); ?></textarea>

            <h3>Példa formátum</h3>
            <pre style="background:#f5f5f5;padding:15px;border-radius:4px;overflow-x:auto;"># Cégnév

> Rövid leírás a cégről.

## Szolgáltatások

- Szolgáltatás 1
- Szolgáltatás 2
- Szolgáltatás 3

## Kapcsolat

- Telefon: +36 1 234 5678
- Email: info@example.com

## Cím

Budapest, Példa utca 123.

## Nyitvatartás

Hétfő-Péntek: 9:00-18:00
Szombat: 10:00-14:00</pre>

            <h3>Hogyan segít ez?</h3>
            <ul>
                <li>✅ Az AI rendszerek automatikusan olvassák az llms.txt fájlt</li>
                <li>✅ Ha valaki megkérdezi pl. "Mi az [cégnév] telefonszáma?", az AI tudni fogja</li>
                <li>✅ A Google és más keresők is értelmezik</li>
                <li>✅ Javítja az AI-alapú keresési találatokat</li>
            </ul>
        </div>
        <?php
    }
}

new RSEO_LLMS();
