# Code Snippets — Instructions
Local instructions override training knowledge.

## Unique Naming Convention (CRITICAL)

- Namespace: `AngieSnippets\{PascalTitle}_{Id}` → `AngieSnippets\PricingTable_456`
- Every snippet has a unique numeric ID from `angie_create-snippet`. Use it as suffix in ALL identifiers
- Class: `{Title}_{Id}` → `Pricing_Table_456`
- Widget get_name(): `{snake_title}_{id}` → `pricing_table_456`
- Block name: `angie-snippets/{slug}-{id}` → `angie-snippets/pricing-table-456`
- Display title (get_title): human-readable, NO ID suffix
- When editing, preserve existing naming. Never change the ID suffix.

## File Structure
Each snippet folder: main.php (required), optional extra PHP/CSS/JS files.
Located in `wp-content/angie-snippets/dev/` or `wp-content/angie-snippets/prod/`.

## Asset Enqueuing
- Use `angie_cs_get_snippet_asset_url( __FILE__, 'filename' )` for URLs — NEVER `plugin_dir_url()`
- Enqueue via `wp_enqueue_scripts` hook with `wp_enqueue_style()`/`wp_enqueue_script()`
- Never hardcode `<script>`/`<link>` tags
- Define a single `{TITLE}_ASSETS_VERSION_{id}` constant at the top of main.php (e.g. `TESTIMONIAL_CARD_ASSETS_VERSION_456`) and use it in ALL enqueue/register calls
- When editing CSS or JS files, bump `{TITLE}_ASSETS_VERSION_{id}` for cache busting (semver: PATCH for tweaks, MINOR for features, MAJOR for rewrites)

## Production Code Rules
- No placeholders, stubs, TODOs, or PHPDoc — all code must work immediately
- Escape output: `esc_html()`, `esc_attr()`, `esc_url()`
- Sanitize input: `sanitize_text_field()`, `sanitize_title()`
- Always `$wpdb->prepare()` for dynamic SQL — never concatenate
- AJAX: `check_ajax_referer()` for logged-in users
- Vanilla JS only. Use Fetch API, `querySelector`, `addEventListener`
- CRITICAL: if you need to use jQuery, use it only when required (e.g. Elementor Handlers API) - use jQuery(...), NEVER $(...)
- Class selectors only (.class) — never ID selectors (#id)
- Text domain: `angie-snippets`
- Check plugin dependencies exist before using (Elementor, ACF, WooCommerce, etc.)

## Forbidden Function Names (CRITICAL)
Server-side validation uses regex to block execution-related keywords (even inside strings, labels, or comments) when followed by `(` — avoid such terms entirely and use safe alternatives like "Setup" or "Run".

## Visual Libraries
Carousels: Swiper.js | Charts: Chart.js | Fonts: Google Fonts | Icons: FontAwesome | Styling: Tailwind CSS

## Limits
PHP/CSS/JS only. Max 100 files/request, 100KB/file. Never auto-publish.