# Create Elementor Pages via execute-php

Guide for building full Elementor pages using `angie/execute-php` (super admin). Read this resource before any execute-php calls that create or update Elementor page JSON.

Resource URI: `angie://super-admin/guide/create-elementor-page`

## Prerequisites

1. Confirm super admin is enabled: `angie/get-super-admin-status` → `{ "enabled": true }`
2. Fetch kit settings for fonts and colors: `angie/get-elementor-kit`
3. Use `angie-get-ability-info` before every `angie-execute-ability` call — never guess parameter shapes

## Optimal call sequence

```
1. get-super-admin-status  → confirm enabled
2. get-elementor-kit       → grab fonts/colors
3–7. execute-php × ~5      → build sections into transients
8. execute-php             → assemble, insert post, wp_slash save, verify, clear cache
```

Total: ~8 calls, each under the code size limit, with validation at the end.

## 1. wp_slash() is mandatory for _elementor_data

WordPress `update_post_meta` (and `meta_input` in `wp_insert_post`) runs `wp_unslash()` internally, which strips backslashes that JSON uses to escape double quotes inside string values. This silently corrupts the JSON and the page appears empty.

**Fix:** Create the post first without meta, then save `_elementor_data` separately:

```php
$post_id = wp_insert_post( [ /* no meta_input */ ] );
update_post_meta( $post_id, '_elementor_data', wp_slash( $json ) );
```

## 2. Avoid double quotes in HTML content values

Even with `wp_slash`, inline HTML like `<p style="text-align:center;">` creates fragile escaped-quote nesting. Safer alternatives:

- Use Elementor's `'align' => 'center'` setting on the text-editor widget instead of inline `style=""`
- If inline styles are unavoidable, use single quotes: `style='text-align:center;'`
- Avoid `&` in text — use `and` or `&amp;` explicitly

## 3. execute-php has a code length limit

A single call cannot handle all sections at once. The sweet spot is ~2–3 sections per call (~2KB of PHP).

**Pattern:** Store chunks in WordPress transients, then assemble:

```
Call 1: build hero + story     → set_transient( 'lp1', [ ... ], 600 )
Call 2: build values + practice → set_transient( 'lp2', [ ... ], 600 )
...
Final call: get all transients → array_merge → wp_json_encode → wp_insert_post + wp_slash save
```

## 4. Always verify stored JSON roundtrips

After saving, confirm the stored data decodes back correctly:

```php
$stored = get_post_meta( $post_id, '_elementor_data', true );
$verify = json_decode( $stored, true );
// check is_array( $verify ) && count( $verify ) === expected
```

## 5. Required post meta for Elementor pages

```php
update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
update_post_meta( $post_id, '_elementor_data', wp_slash( $json ) );
update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
update_post_meta( $post_id, '_wp_page_template', 'elementor_canvas' );
\Elementor\Plugin::$instance->files_manager->clear_cache();
```

## 6. Container-based structure (Elementor 3.x+)

Use `elType: "container"` with `isInner: true/false`, not legacy sections/columns. Each element needs a unique 7-character hex ID.

## Error handling

When `angie/execute-php` returns an error:

1. Never pretend the operation succeeded — report the error clearly
2. If the payload was too large, split into smaller transient chunks and retry
3. If the page renders empty after insert, re-read section 1 (wp_slash) and section 4 (verify roundtrip)
4. Ask the user how they want to proceed before retrying destructive steps
