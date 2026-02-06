# RendanIT SEO - WordPress SEO Plugin

Magyar nyelvű, Elementor és Polylang kompatibilis SEO plugin WordPresshez.

## Funkciók

### Alapvető SEO
- **Meta címek és leírások** - Egyedi SEO title és meta description minden oldalhoz
- **Fókusz kulcsszó** - Kulcsszó optimalizálás ellenőrzéssel
- **Google előnézet** - Valós idejű keresési találat előnézet
- **Karakter számlálók** - Title (60) és description (155) hossz ellenőrzés

### Haladó SEO
- **Schema.org markup** - LocalBusiness, FAQPage, Article, BlogPosting, Service
- **Open Graph** - Facebook és LinkedIn megosztás optimalizálás
- **Twitter Cards** - Twitter megosztás optimalizálás
- **Canonical URL** - Duplikált tartalom kezelés
- **Robots meta** - noindex/nofollow beállítások

### Technikai SEO
- **XML Sitemap** - Automatikus sitemap generálás (`/rseo-sitemap.xml`)
- **Hreflang** - Többnyelvű oldal támogatás (Polylang integráció)
- **301 Redirectek** - URL átirányítás kezelő
- **404 Monitor** - 404 hibák naplózása és átirányítás javaslatok

### Elemzés
- **SEO Pontszám** - 0-100% részletes értékelés
- **SEO Audit** - Teljes weboldal elemzés
- **Olvashatóság** - Magyar nyelvű Flesch-index
- **Admin bar** - Gyors SEO státusz minden oldalon

### Integráció
- **Elementor** - Teljes tartalom, kép és link kinyerés
- **Polylang** - Hreflang és nyelvi sitemap támogatás
- **Gutenberg** - Block editor kompatibilitás
- **Classic Editor** - Klasszikus szerkesztő támogatás

## Telepítés

### WordPress Admin
1. Töltsd le a `rendanit-seo.zip` fájlt
2. Menj: Bővítmények → Új hozzáadása → Bővítmény feltöltése
3. Válaszd ki a ZIP fájlt és kattints "Telepítés most"
4. Aktiváld a plugint

### Kézi telepítés
1. Csomagold ki a ZIP-et
2. Töltsd fel az `rendanit-seo` mappát a `/wp-content/plugins/` könyvtárba
3. Aktiváld a WordPress adminban

## Használat

### Beállítások
**WordPress Admin → RendanIT SEO**

- **Általános** - Site név, elválasztó karakter
- **Főoldal** - Home page SEO beállítások
- **Schema** - Üzleti adatok, nyitvatartás
- **Social** - OG és Twitter alapértelmezések
- **Technikai** - noindex beállítások, robots.txt
- **Tracking** - Google Tag Manager, GA4

### Bejegyzés/Oldal szerkesztés
A szerkesztő alatt megjelenik a **RendanIT SEO** metabox:

- **SEO tab** - Title, description, fókusz kulcsszó
- **Social tab** - OG címek, leírások, képek
- **Haladó tab** - Canonical, noindex, schema
- **Elemzés tab** - Valós idejű SEO pontszám

### Admin Bar
Minden oldalon látható a SEO pontszám az admin sávban.

## Követelmények

- WordPress 6.0 vagy újabb
- PHP 7.4 vagy újabb
- MySQL 5.6 vagy újabb

## Opcionális függőségek

- **Elementor** - Jobb tartalom kinyerés page builder oldalakhoz
- **Polylang** - Többnyelvű SEO támogatás

## API Hooks

### Filters
```php
// Tartalom kinyerés módosítása
add_filter( 'rseo_get_post_content', 'my_content_filter', 10, 2 );

// H1 címsor felülírása
add_filter( 'rseo_get_post_h1', 'my_h1_filter', 10, 2 );

// Képek listájának bővítése
add_filter( 'rseo_get_post_images', 'my_images_filter', 10, 2 );

// Linkek listájának bővítése
add_filter( 'rseo_get_post_links', 'my_links_filter', 10, 2 );
```

### Functions
```php
// SEO pontszám lekérése
$score = RSEO_Score::get_score( $post_id );

// Beállítás lekérése
$value = RendanIT_SEO::get_setting( 'key', 'default' );

// Polylang ellenőrzés
if ( RendanIT_SEO::has_polylang() ) {
    // Többnyelvű logika
}
```

## Changelog

### 1.3.3
- Elementor képek és linkek felismerése
- Alt text ellenőrzés attachment meta-ból

### 1.3.2
- Server-side AJAX elemzés
- Elementor H1 felismerés javítás

### 1.3.1
- Sitemap rewrite rules auto-flush
- Olvashatóság elemzés

### 1.3.0
- Redirect manager
- 404 monitor
- Bulk editor
- Social preview
- Link suggestions

## Támogatás

- GitHub Issues: [github.com/rendanit/rendanit-seo](https://github.com/rendanit/rendanit-seo)
- Email: support@rendanit.com
- Web: [rendanit.com](https://rendanit.com)

## Licenc

GPL v2 or later

## Készítette

**RendanIT** - [rendanit.com](https://rendanit.com)
