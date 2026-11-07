# Elementor Widget Requirements
Build widgets exactly like Elementor Core widgets — native, responsive, accessible (WCAG 2.1).

## File Structure
- **main.php**: registration hook + asset registration
- **widget-{name}.php**: widget class extending `\Elementor\Widget_Base` (separate file, `namespace AngieSnippets`)
- **script.js**: (optional) JavaScript handler using Elementor Handlers API
- **style.css** (optional): structural styles not covered by Elementor controls

## Tabs Layout
- Content tab: data & structure | Style tab: visual styling | Advanced: default
- EVERY widget MUST have a Style tab — a widget without one is broken. Use `$this->start_controls_section( 'section_style_*', [ 'tab' => Controls_Manager::TAB_STYLE ] )` with COLOR, Typography, Border, DIMENSIONS controls

## Asset Loading (CRITICAL)
- Register (NOT enqueue) in `wp_enqueue_scripts` hook: `wp_register_script()` / `wp_register_style()`
- URL: `angie_cs_get_snippet_asset_url( __FILE__, 'filename' )` — NEVER `plugin_dir_url()`
- Widget loads via `get_script_depends()` / `get_style_depends()` — assets loaded only when widget is on page
- Scripts must depend on `elementor-frontend`

## Widget Class Rules
- Separate file `widget-{name}.php` with `namespace AngieSnippets;` and `class_exists` guard
- `get_name()` → snake_case with ID suffix: `testimonial_card_456`
- `get_title()` → human-readable, NO ID suffix
- `get_categories()` → `[ 'angie-widgets', 'general' ]`
- `get_icon()` → **STRICT CONSTRAINT — only values from the exact list below are valid. Do NOT guess or invent icon names, MANDATORY: If a value is not in the list, choose the closest match from the list:**
  <!-- Icon list source: https://elementor.github.io/elementor-icons/ — update when icon library changes -->
  eicon-elementor,eicon-elementor-circle,eicon-wordpress,eicon-wordpress-light,eicon-editor-link,eicon-editor-unlink,eicon-editor-external-link,eicon-editor-close,eicon-editor-list-ol,eicon-editor-list-ul,eicon-editor-bold,eicon-editor-italic,eicon-editor-underline,eicon-editor-paragraph,eicon-editor-h1,eicon-editor-h2,eicon-editor-h3,eicon-editor-h4,eicon-editor-h5,eicon-editor-h6,eicon-editor-quote,eicon-editor-code,eicon-pojome,eicon-plus,eicon-menu-bar,eicon-apps,eicon-accordion,eicon-alert,eicon-animation-text,eicon-animation,eicon-banner,eicon-blockquote,eicon-button,eicon-call-to-action,eicon-captcha,eicon-carousel,eicon-checkbox,eicon-columns,eicon-countdown,eicon-counter,eicon-date,eicon-divider-shape,eicon-divider,eicon-download-button,eicon-dual-button,eicon-email-field,eicon-facebook-comments,eicon-facebook-like-box,eicon-form-horizontal,eicon-form-vertical,eicon-gallery-grid,eicon-gallery-group,eicon-gallery-justified,eicon-gallery-masonry,eicon-icon-wrapper,eicon-image-before-after,eicon-image-box,eicon-image-hotspot,eicon-image-rollover,eicon-info-box,eicon-inner-section,eicon-mailchimp,eicon-menu-card,eicon-navigation-horizontal,eicon-nav-menu,eicon-navigation-vertical,eicon-number-field,eicon-parallax,eicon-php7,eicon-post-list,eicon-post-slider,eicon-post,eicon-posts-carousel,eicon-posts-grid,eicon-posts-group,eicon-posts-justified,eicon-posts-masonry,eicon-posts-ticker,eicon-price-list,eicon-price-table,eicon-radio,eicon-rtl,eicon-scroll,eicon-search,eicon-select,eicon-share,eicon-sidebar,eicon-skill-bar,eicon-slider-3d,eicon-slider-album,eicon-slider-device,eicon-slider-full-screen,eicon-slider-push,eicon-slider-vertical,eicon-slider-video,eicon-slides,eicon-social-icons,eicon-spacer,eicon-table,eicon-tabs,eicon-tel-field,eicon-text-area,eicon-text-field,eicon-thumbnails-down,eicon-thumbnails-half,eicon-thumbnails-right,eicon-time-line,eicon-toggle,eicon-url,eicon-t-letter,eicon-text,eicon-anchor,eicon-bullet-list,eicon-code,eicon-favorite,eicon-google-maps,eicon-image,eicon-photo-library,eicon-woocommerce,eicon-youtube,eicon-flip-box,eicon-settings,eicon-headphones,eicon-testimonial,eicon-counter-circle,eicon-person,eicon-chevron-right,eicon-chevron-left,eicon-close,eicon-file-download,eicon-save,eicon-zoom-in,eicon-shortcode,eicon-nerd,eicon-device-desktop,eicon-device-tablet,eicon-device-mobile,eicon-document-file,eicon-folder-o,eicon-hypster,eicon-h-align-left,eicon-h-align-right,eicon-h-align-center,eicon-h-align-stretch,eicon-v-align-top,eicon-v-align-bottom,eicon-v-align-middle,eicon-v-align-stretch,eicon-mail,eicon-lock-user,eicon-testimonial-carousel,eicon-media-carousel,eicon-section,eicon-column,eicon-edit,eicon-clone,eicon-trash,eicon-play,eicon-angle-right,eicon-angle-left,eicon-animated-headline,eicon-menu-toggle,eicon-fb-embed,eicon-fb-feed,eicon-twitter-embed,eicon-twitter-feed,eicon-sync,eicon-import-export,eicon-check-circle,eicon-library-save,eicon-insert,eicon-preview-medium,eicon-sort-down,eicon-sort-up,eicon-heading,eicon-logo,eicon-meta-data,eicon-post-content,eicon-post-excerpt,eicon-post-navigation,eicon-yoast,eicon-nerd-chuckle,eicon-nerd-wink,eicon-comments,eicon-download-circle-o,eicon-library-upload,eicon-save-o,eicon-upload-circle-o,eicon-ellipsis-h,eicon-ellipsis-v,eicon-arrow-left,eicon-arrow-right,eicon-arrow-up,eicon-arrow-down,eicon-play-o,eicon-archive-posts,eicon-archive-title,eicon-featured-image,eicon-post-info,eicon-post-title,eicon-site-logo,eicon-site-search,eicon-site-title,eicon-plus-square,eicon-minus-square,eicon-cloud-check,eicon-drag-n-drop,eicon-welcome,eicon-handle,eicon-cart,eicon-product-add-to-cart,eicon-product-breadcrumbs,eicon-product-categories,eicon-product-description,eicon-product-images,eicon-product-info,eicon-product-meta,eicon-product-pages,eicon-product-price,eicon-product-rating,eicon-product-related,eicon-product-stock,eicon-product-tabs,eicon-product-title,eicon-product-upsell,eicon-products,eicon-bag-light,eicon-bag-medium,eicon-bag-solid,eicon-basket-light,eicon-basket-medium,eicon-basket-solid,eicon-cart-light,eicon-cart-medium,eicon-cart-solid,eicon-exchange,eicon-preview-thin,eicon-device-laptop,eicon-collapse,eicon-expand,eicon-navigator,eicon-plug,eicon-dashboard,eicon-typography,eicon-info-circle-o,eicon-integration,eicon-plus-circle-o,eicon-rating,eicon-review,eicon-tools,eicon-loading,eicon-sitemap,eicon-click,eicon-clock,eicon-library-open,eicon-warning,eicon-flow,eicon-cursor-move,eicon-arrow-circle-left,eicon-flash,eicon-redo,eicon-ban,eicon-barcode,eicon-calendar,eicon-caret-left,eicon-caret-right,eicon-caret-up,eicon-chain-broken,eicon-check-circle-o,eicon-check,eicon-chevron-double-left,eicon-chevron-double-right,eicon-undo,eicon-filter,eicon-circle-o,eicon-circle,eicon-clock-o,eicon-cog,eicon-cogs,eicon-commenting-o,eicon-copy,eicon-database,eicon-dot-circle-o,eicon-envelope,eicon-external-link-square,eicon-eyedropper,eicon-folder,eicon-font,eicon-adjust,eicon-lightbox,eicon-heart-o,eicon-history,eicon-image-bold,eicon-info-circle,eicon-link,eicon-long-arrow-left,eicon-long-arrow-right,eicon-caret-down,eicon-paint-brush,eicon-pencil,eicon-plus-circle,eicon-zoom-in-bold,eicon-sort-amount-desc,eicon-sign-out,eicon-spinner,eicon-square,eicon-star-o,eicon-star,eicon-text-align-justify,eicon-text-align-center,eicon-tags,eicon-text-align-left,eicon-text-align-right,eicon-close-circle,eicon-trash-o,eicon-font-awesome,eicon-user-circle-o,eicon-video-camera,eicon-heart,eicon-wrench,eicon-help,eicon-help-o,eicon-zoom-out-bold,eicon-plus-square-o,eicon-minus-square-o,eicon-minus-circle,eicon-minus-circle-o,eicon-code-bold,eicon-cloud-upload,eicon-search-bold,eicon-map-pin,eicon-meetup,eicon-slideshow,eicon-t-letter-bold,eicon-preferences,eicon-table-of-contents,eicon-tv,eicon-upload,eicon-instagram-comments,eicon-instagram-nested-gallery,eicon-instagram-post,eicon-instagram-video,eicon-instagram-gallery,eicon-instagram-likes,eicon-facebook,eicon-twitter,eicon-pinterest,eicon-frame-expand,eicon-frame-minimize,eicon-archive,eicon-colors-typography,eicon-custom,eicon-footer,eicon-header,eicon-layout-settings,eicon-lightbox-expand,eicon-error-404,eicon-theme-style,eicon-search-results,eicon-single-post,eicon-site-identity,eicon-theme-builder,eicon-download-bold,eicon-share-arrow,eicon-global-settings,eicon-user-preferences,eicon-lock,eicon-export-kit,eicon-import-kit,eicon-lottie,eicon-products-archive,eicon-single-product,eicon-disable-trash-o,eicon-single-page,eicon-cogs-check,eicon-custom-css,eicon-global-colors,eicon-globe,eicon-typography-1,eicon-background,eicon-device-responsive,eicon-device-wide,eicon-code-highlight,eicon-video-playlist,eicon-download-kit,eicon-kit-details,eicon-kit-parts,eicon-kit-upload,eicon-kit-plugins,eicon-kit-upload-alt,eicon-hotspot,eicon-paypal-button,eicon-shape,eicon-wordart,eicon-checkout,eicon-container,eicon-flip,eicon-info,eicon-my-account,eicon-purchase-summary,eicon-page-transition,eicon-spotify,eicon-stripe-button,eicon-woo-settings,eicon-woo-cart,eicon-grow,eicon-order-end,eicon-nowrap,eicon-order-start,eicon-progress-tracker,eicon-shrink,eicon-wrap,eicon-align-center-h,eicon-align-center-v,eicon-align-end-h,eicon-align-end-v,eicon-align-start-h,eicon-align-start-v,eicon-align-stretch-h,eicon-align-stretch-v,eicon-justify-center-h,eicon-justify-center-v,eicon-justify-end-h,eicon-justify-end-v,eicon-justify-space-around-h,eicon-justify-space-around-v,eicon-justify-space-between-h,eicon-justify-space-between-v,eicon-justify-space-evenly-h,eicon-justify-space-evenly-v,eicon-justify-start-h,eicon-justify-start-v,eicon-woocommerce-cross-sells,eicon-woocommerce-notices,eicon-inner-container,eicon-warning-full,eicon-exit,eicon-loop-builder,eicon-notes,eicon-read,eicon-unread,eicon-carousel-loop,eicon-mega-menu,eicon-nested-carousel,eicon-ai,eicon-taxonomy-filter,eicon-atomic,eicon-container-grid,eicon-pro-icon,eicon-upgrade,eicon-advanced,eicon-div-block,eicon-notification,eicon-light-mode,eicon-dark-mode,eicon-upgrade-crown,eicon-off-canvas,eicon-speakerphone,eicon-ehp-zigzag,eicon-ehp-hero,eicon-ehp-cta,eicon-ehp-forms,eicon-e-button,eicon-flexbox,eicon-paragraph,eicon-icon,eicon-e-image,eicon-video,eicon-svg,eicon-e-divider,eicon-e-heading,eicon-library-delete,eicon-library-copy,eicon-library-folder-empty,eicon-library-move,eicon-library-edit,eicon-library-download,eicon-library-subscription-upgrade,eicon-library-folder-view,eicon-library-grid,eicon-library-cloud-connect,eicon-library-import,eicon-library-list,eicon-library-cloud-empty,eicon-folder-plus,eicon-library-folder,eicon-accessibility,eicon-lock-outline,eicon-e-youtube,eicon-atomic-label,eicon-atomic-form,eicon-atomic-submit-button,eicon-atomic-text-area,eicon-atomic-input,eicon-contact,eicon-layout,eicon-components,eicon-tab-content,eicon-tab-menu,eicon-atomic-select,eicon-atomic-checkbox,eicon-atomic-radiobutton
- Use `selectors` in style controls for Elementor-managed CSS
- Escape output: `esc_html()`, `esc_attr()`, `esc_url()`

## Editor Parity (REQUIRED)
Every widget MUST implement `content_template()` using Underscore.js template syntax so the editor preview matches the frontend.

## Iframe Preview Settings (REQUIRED for MCP preview)
Snippet iframe previews (`previewUrl`, `angie-preview-widget`) render the widget with saved `elementor_preview_settings` merged over widget defaults. Pass these on every `angie_update-snippet-files` call.

Match control names exactly from `register_controls()`:

| Control type | `elementor_preview_settings` shape |
|---|---|
| `Controls_Manager::MEDIA` | `{ "image": { "id": "", "url": "https://images.unsplash.com/photo-..." } }` |
| `Controls_Manager::GALLERY` | `{ "gallery": [ { "id": "", "url": "..." }, ... ] }` — minimum 4 images |
| `Controls_Manager::REPEATER` with media fields | `{ "items": [ { "image": { "id": "", "url": "..." } }, ... ] }` — enough rows to show layout |

Use topic-relevant Unsplash URLs. If omitted, the server fills generic placeholder images as a fallback — always prefer explicit, contextual images.

## Dynamic Icon Rendering in content_template() (CRITICAL)
Icons (`Controls_Manager::ICONS`) MUST use `elementor.helpers.renderIcon()` in `content_template()` — never static `<i>` tags.
Pattern: `var iconHTML = elementor.helpers.renderIcon( view, settings.my_icon, { 'aria-hidden': 'true' }, 'i', 'object' );`
Then output: `<# if ( iconHTML && iconHTML.value ) { #>{{{ iconHTML.value }}}<# } #>`
Inside Repeaters — same pattern per item: `elementor.helpers.renderIcon( view, item.icon, ... )`
PHP equivalent in `render()`: `\Elementor\Icons_Manager::render_icon( $settings['my_icon'], [ 'aria-hidden' => 'true' ] )`

## Icon Styling Selectors (CRITICAL)
Elementor renders icons as inline SVGs by default — `font-size` and `color` only affect `<i>` font icons and have NO effect on SVGs. Every icon size/color control MUST target both:
- **Size**: `'{{WRAPPER}} .my-icon' => 'font-size: {{SIZE}}{{UNIT}};', '{{WRAPPER}} .my-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'`
- **Color**: `'{{WRAPPER}} .my-icon' => 'color: {{VALUE}};', '{{WRAPPER}} .my-icon svg' => 'fill: {{VALUE}};'`

## JavaScript — Handlers API (REQUIRED)
Use `elementorModules.frontend.handlers.Base` with `getDefaultSettings`, `getDefaultElements`, `bindEvents`.
Register: `elementorFrontend.hooks.addAction('frontend/element_ready/{widget_name}.default', addHandler)`.
Never use function-based handlers or manual DOM queries.

### Allowed `elementorFrontend` API (STRICT — do NOT use anything else)
Only these `elementorFrontend` members are safe to call:
- `elementorFrontend.hooks.addAction()` / `addFilter()` — event registration
- `elementorFrontend.elementsHandler.addHandler()` — handler registration
- `elementorFrontend.isEditMode()` — check if inside the editor
- `elementorFrontend.config` — read-only configuration

**NEVER call `elementorFrontend.utils.*`, `elementorFrontend.utils.lightbox`, `elementorFrontend.modules.*`, or any other internal utility.** These are undocumented private APIs that vary across Elementor versions and will cause runtime errors.
For features like lightboxes, modals, video embeds, popups, or overlays — **always build them from scratch** using JS DOM manipulation (append/remove overlay elements) as shown in the handler example. Never delegate to Elementor internal helpers.

## Extending Existing Widgets
Use Elementor action hooks — never subclass existing widgets:
`elementor/element/{widget}/{section}/before_section_end`

---

## Canonical File Structure Example (snippet ID 456)

### main.php
```php
<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

const TESTIMONIAL_CARD_ASSETS_VERSION_456 = '1.0.0';

function register_testimonial_card_widget_456( $widgets_manager ) {
    require_once __DIR__ . '/widget-testimonial-card.php';
    $widgets_manager->register( new \AngieSnippets\Testimonial_Card_456() );
}
add_action( 'elementor/widgets/register', 'register_testimonial_card_widget_456' );

function register_testimonial_card_assets_456() {
	wp_register_script( 'testimonial-card-script-456', angie_cs_get_snippet_asset_url( __FILE__, 'script.js' ), [ 'elementor-frontend' ], TESTIMONIAL_CARD_ASSETS_VERSION_456, true );
	wp_register_style( 'testimonial-card-style-456', angie_cs_get_snippet_asset_url( __FILE__, 'style.css' ), [], TESTIMONIAL_CARD_ASSETS_VERSION_456 );
}
add_action( 'wp_enqueue_scripts', 'register_testimonial_card_assets_456' );
```

### widget-testimonial-card.php
```php
<?php

namespace AngieSnippets;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Testimonial_Card_456 extends \Elementor\Widget_Base {
    public function get_name() { return 'testimonial_card_456'; }
    public function get_title() { return esc_html__( 'Testimonial Card', 'angie-snippets' ); }
    public function get_icon() { return 'eicon-testimonial'; }
    public function get_categories() { return [ 'angie-widgets', 'general' ]; } // CRITICAL: Always use these exact categories for Angie widgets
    public function get_script_depends() { return [ 'testimonial-card-script-456' ]; }
    public function get_style_depends() { return [ 'testimonial-card-style-456' ]; }

    protected function register_controls() { /* Content tab + Style tab controls */ }
    protected function render() { /* PHP output with esc_html/esc_attr/esc_url */ }
    protected function content_template() { /* Underscore.js template: {{ }}, {{{ }}}, <# #> */ }
}
```

### script.js
```javascript
class TestimonialCardHandler extends elementorModules.frontend.handlers.Base {
    getDefaultSettings() { return { selectors: { card: '.tc-456-card' } }; }
    getDefaultElements() { return { $card: this.$element.find(this.getSettings('selectors').card) }; }
    // jQuery(window).resize()
    // jQuery(window).scroll()
    bindEvents() { /* widget interactivity */ }
}

jQuery(window).on('elementor/frontend/init', () => {
    const addHandler = ($element) => {
        elementorFrontend.elementsHandler.addHandler(TestimonialCardHandler, { $element });
    };
    elementorFrontend.hooks.addAction('frontend/element_ready/testimonial_card_456.default', addHandler);
});
```