# Velora block theme

Velora requires WordPress 7.1 or newer and PHP 8.4 or newer. Edit its layouts, colors, and typography in **Appearance → Editor** or in `templates/`, `parts/`, and `theme.json`.

The theme includes a shared header and footer plus templates for posts, pages, archives, search results, and 404 pages. Set a static home page in **Settings → Reading** if the site needs a custom landing page.

## Local development

The Docker project mounts this directory into WordPress. `code/wp-config.php` enables the local environment, theme development mode, PHP error logging, unminified WordPress scripts, and Elementor deprecation debugging. Errors are written to `wp-content/debug.log`, which Git ignores.

Build reusable Gutenberg blocks in a companion plugin and register each block with `block.json`; the theme can then style them through `theme.json`. Elementor Pro is not required for Gutenberg blocks.
