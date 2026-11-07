# Extending Elementor Features

This guide covers extending Elementor's platform features: registering dynamic tags, custom form fields, custom form actions, document types, theme locations, and post-level settings.

CRITICAL: NEVER subclass an existing widget. Always use hooks.

---

## 1. Registering Dynamic Tags

Dynamic tags let users bind dynamic data to any control that supports them.

### Hook: elementor/dynamic_tags/register
```php
add_action( 'elementor/dynamic_tags/register', function( $dynamic_tags ) {
    // Optional: register a custom group
    $dynamic_tags->register_group( 'my-group', [ 'title' => 'My Group' ] );
    $dynamic_tags->register( new My_Custom_Tag() );
} );
```

### Tag base classes
- `\Elementor\Core\DynamicTags\Tag` — implements `render()`, echoes text/HTML output
- `\Elementor\Core\DynamicTags\Data_Tag` — implements `get_value()`, returns raw data (images, URLs, structured data)

### Tag categories (determines which controls can use the tag)
`\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY`
`\Elementor\Modules\DynamicTags\Module::URL_CATEGORY`
`\Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY`
`\Elementor\Modules\DynamicTags\Module::NUMBER_CATEGORY`
`\Elementor\Modules\DynamicTags\Module::COLOR_CATEGORY`
`\Elementor\Modules\DynamicTags\Module::POST_META_CATEGORY`

### Tag groups (built-in)
`site` | `post` | `archive` | `media` | `action` | `author` | `comments`

### Example: Text dynamic tag with controls
```php
class My_API_Tag extends \Elementor\Core\DynamicTags\Tag {
    public function get_name() { return 'my-api-tag'; }
    public function get_title() { return esc_html__( 'API Data', 'angie-snippets' ); }
    public function get_group() { return 'site'; }
    public function get_categories() {
        return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
    }

    protected function register_controls() {
        $this->add_control( 'api_url', [
            'label' => esc_html__( 'API URL', 'angie-snippets' ),
            'type'  => \Elementor\Controls_Manager::TEXT,
        ] );
    }

    public function render() {
        $url = $this->get_settings( 'api_url' );
        if ( empty( $url ) ) { return; }
        $response = wp_remote_get( esc_url_raw( $url ), [ 'timeout' => 10 ] );
        if ( is_wp_error( $response ) ) { return; }
        echo esc_html( wp_remote_retrieve_body( $response ) );
    }
}
```

### Enabling dynamic tags on a control
Any control can accept dynamic tags by adding the `dynamic` parameter:
```php
$element->add_control( 'my_field', [
    'label'   => 'My Field',
    'type'    => \Elementor\Controls_Manager::TEXT,
    'dynamic' => [
        'active'     => true,
        'categories' => [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ],
    ],
] );
```

---

## 2. Custom Form Fields (Elementor Pro)

Add a new field type to the Elementor Pro Form widget.

### Base class: \ElementorPro\Modules\Forms\Fields\Field_Base
The constructor automatically hooks into the form fields system. You must implement:
- `get_type()` — unique field type slug
- `get_name()` — display label
- `render( $item, $item_index, $form )` — HTML output
- `update_controls( $widget )` — inject per-field controls into the form fields repeater

### CRITICAL: render() and pre-populated attributes
The Form widget ALREADY sets `type`, `name`, `id`, and `required` on the `'input' . $item_index` render attribute key BEFORE your `render()` is called. You must NOT add these attributes again with `add_render_attribute()` or they will be DUPLICATED in the HTML output (e.g. `type="text range"`, `name="form_fields[x] form_fields[x]"`).

In your `render()` method:
- To OVERRIDE the default `type` attribute (e.g. from `text` to `range`), use `set_render_attribute()` (overwrites) instead of `add_render_attribute()` (appends)
- Do NOT set `name`, `id`, or `required` — the Form widget handles these
- Only ADD extra attributes like `class`, `min`, `max`, `step`, `placeholder`, etc.

```php
public function render( $item, $item_index, $form ) {
    // OVERRIDE type (use set_ not add_)
    $form->set_render_attribute( 'input' . $item_index, 'type', 'range' );
    // ADD extra attributes (these are not pre-populated)
    $form->add_render_attribute( 'input' . $item_index, 'class', 'elementor-field-textual' );

    if ( isset( $item['field_min'] ) && '' !== $item['field_min'] ) {
        $form->add_render_attribute( 'input' . $item_index, 'min', $item['field_min'] );
    }
    if ( isset( $item['field_max'] ) && '' !== $item['field_max'] ) {
        $form->add_render_attribute( 'input' . $item_index, 'max', $item['field_max'] );
    }

    echo '<input ' . $form->get_render_attribute_string( 'input' . $item_index ) . '>';
}
```

### Injecting field controls into the repeater
Field_Base provides `$this->inject_field_controls()` which merges your controls into the repeater fields array at the correct position (after the "required" toggle). NEVER use `Controls_Manager::add_fields_to_stack()` — that method does NOT exist.
```php
public function update_controls( $widget ) {
    $elementor    = \ElementorPro\Plugin::elementor();
    $control_data = $elementor->controls_manager->get_control_from_stack( $widget->get_unique_name(), 'form_fields' );
    if ( is_wp_error( $control_data ) ) { return; }

    $field_controls = [
        'my_field_option' => [
            'name'      => 'my_field_option',
            'label'     => esc_html__( 'Option', 'angie-snippets' ),
            'type'      => \Elementor\Controls_Manager::TEXT,
            'condition' => [ 'field_type' => $this->get_type() ],
            'tab'       => 'content',
            'inner_tab' => 'form_fields_content_tab',
            'tabs_wrapper' => 'form_fields_tabs',
        ],
    ];

    // CORRECT: use $this->inject_field_controls() inherited from Field_Base
    $control_data['fields'] = $this->inject_field_controls( $control_data['fields'], $field_controls );
    $widget->update_control( 'form_fields', $control_data );
}
```

### CRITICAL: Editor preview for custom form fields (JS content template)
The Form widget's editor template uses a JS filter for unknown field types. Without handling this filter, your custom field will be INVISIBLE in the Elementor editor preview.

The filter is: `elementor_pro/forms/content_template/field/{your_field_type}`

You MUST register a JS hook that returns the HTML for your field in the editor. Chain: constructor → `elementor/preview/init` → `wp_footer` to inject the script:

```php
public function __construct() {
    parent::__construct();
    add_action( 'elementor/preview/init', function() {
        add_action( 'wp_footer', [ $this, 'content_template_script' ] );
    } );
}

public function content_template_script(): void { ?>
    <script>
    jQuery( document ).ready( () => {
        elementor.hooks.addFilter(
            'elementor_pro/forms/content_template/field/<?php echo esc_js( $this->get_type() ); ?>',
            function ( inputField, item, i, settings ) {
                const fieldClass = 'elementor-field elementor-size-' + settings.input_size;
                return '<input type="range" class="' + fieldClass + '">';
            }
        );
    });
    </script>
<?php }
```

The JS filter receives 4 arguments: `( inputField, item, i, settings )`
- `inputField` — empty string (default), return your HTML to replace it
- `item` — the repeater item data (contains your custom control values like `item.field_min`)
- `i` — the field index
- `settings` — the full widget settings (use `settings.input_size` for consistent sizing)

### Loading the field
Instantiate from `elementor_pro/init` — the parent constructor registers the field type automatically:
```php
add_action( 'elementor_pro/init', function() {
    new My_Form_Field_Type();
} );
```

---

## 3. Custom Form Actions (Elementor Pro)

Add a custom action that runs when a Pro form is submitted.

### Hook: elementor_pro/forms/actions/register
### Base class: \ElementorPro\Modules\Forms\Classes\Action_Base

Required methods:
- `get_name()` — unique slug
- `get_label()` — display name
- `register_settings_section( $widget )` — add action-specific controls
- `run( $record, $ajax_handler )` — execute the action on submit
- `on_export( $element )` — strip sensitive data (API keys) on template export

### CRITICAL: Registration and class definition must be structured correctly
The `elementor_pro/forms/actions/register` hook fires DURING `elementor_pro/init`. Both the class definition and registration must happen inside the `elementor_pro/forms/actions/register` callback itself. At that point `Action_Base` is guaranteed to be loaded, and the registrar is ready to accept your action.

CORRECT — define AND register inside the same hook:
```php
add_action( 'elementor_pro/forms/actions/register', function( $actions_registrar ) {
    class My_Form_Action extends \ElementorPro\Modules\Forms\Classes\Action_Base {
        public function get_name() { return 'my_action'; }
        public function get_label() { return esc_html__( 'My Action', 'angie-snippets' ); }
        public function register_settings_section( $widget ) { /* ... */ }
        public function run( $record, $ajax_handler ) { /* ... */ }
        public function on_export( $element ) { return $element; }
    }
    $actions_registrar->register( new My_Form_Action() );
} );
```

WRONG — do NOT split class definition into `elementor_pro/init` and registration into a separate hook (class may not exist yet), and do NOT nest registration inside `elementor_pro/init` (the registrar already fired by then).

### register_settings_section — MUST use submit_actions condition
The settings section must be conditionally shown only when the user has selected this action. Use the `condition` parameter:
```php
public function register_settings_section( $widget ) {
    $widget->start_controls_section( 'section_my_action', [
        'label'     => esc_html__( 'My Action', 'angie-snippets' ),
        'condition' => [ 'submit_actions' => $this->get_name() ],
    ] );
    $widget->add_control( 'my_api_key', [
        'label' => esc_html__( 'API Key', 'angie-snippets' ),
        'type'  => \Elementor\Controls_Manager::TEXT,
    ] );
    $widget->end_controls_section();
}
```

### on_export — strip sensitive fields
```php
public function on_export( $element ) {
    unset( $element['settings']['my_api_key'] );
    return $element;
}
```

### Reading form data in run()
```php
public function run( $record, $ajax_handler ) {
    $settings = $record->get( 'form_settings' );  // action settings from the panel
    $fields   = $record->get( 'fields' );          // submitted field values keyed by field ID
    $email_field_id = ! empty( $settings['my_email_field'] ) ? $settings['my_email_field'] : 'email';
    $email = isset( $fields[ $email_field_id ]['value'] ) ? sanitize_email( $fields[ $email_field_id ]['value'] ) : '';
    // Send to external API, etc.
}
```

### Best practice: Let users configure field mappings
When an action sends data to an external API, NEVER guess which form field contains the email/name/phone. Add TEXT controls in `register_settings_section()` so the user maps field IDs to API parameters. In `run()`, read via `$fields[ $settings['my_email_field'] ]['value']`. Set `'ai' => [ 'active' => false ]` on these controls to disable AI suggestions for IDs.

### Error handling in form actions
```php
public function run( $record, $ajax_handler ) {
    $response = wp_remote_post( $api_url, [ 'body' => $data ] );
    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
        $ajax_handler->add_error_message( 'Failed to submit form data' );
        $ajax_handler->add_admin_error_message( 'API error: ' . wp_remote_retrieve_body( $response ) );
        return;
    }
}
```

---

## 4. Document Types & Theme Locations (Elementor Pro)

### Registering a new document type
```php
add_action( 'elementor/documents/register', function( $documents_manager ) {
    $documents_manager->register_document_type( 'my-type', My_Document::get_class_full_name() );
} );
```

### Document class (extend Theme_Section_Document for theme builder integration)
```php
class My_Document extends \ElementorPro\Modules\ThemeBuilder\Documents\Theme_Section_Document {
    public static function get_properties() {
        $properties = parent::get_properties();
        $properties['location'] = 'my_location';
        $properties['support_kit'] = true;
        return $properties;
    }
    public static function get_type() { return 'my-type'; }
    public function get_name() { return 'my-type'; }
    public static function get_title() { return esc_html__( 'My Template', 'angie-snippets' ); }
    public static function get_plural_title() { return esc_html__( 'My Templates', 'angie-snippets' ); }
}
```

### Registering a theme location
```php
add_action( 'elementor/theme/register_locations', function( $locations_manager ) {
    $locations_manager->register_location( 'my_location', [
        'label'    => esc_html__( 'My Location', 'angie-snippets' ),
        'multiple' => false,
        'public'   => true,
    ] );
} );
```

### Rendering the location on the frontend
```php
add_action( 'wp_body_open', function() {
    if ( function_exists( 'elementor_theme_do_location' ) ) {
        elementor_theme_do_location( 'my_location' );
    }
} );
```

---

## 5. Post-Level (Document) Settings

### Adding controls to page/post settings
The hook uses the document type name. The most common type is `wp-post` which covers BOTH posts and pages:
```php
add_action( 'elementor/element/wp-post/document_settings/before_section_end', function( $document ) {
    $document->add_control( 'my_toggle', [
        'label'        => esc_html__( 'My Toggle', 'angie-snippets' ),
        'type'         => \Elementor\Controls_Manager::SWITCHER,
        'return_value' => 'yes',
        'separator'    => 'before',
    ] );
} );
```
NOTE: `wp-post` covers posts, pages, and any CPT edited with Elementor. You do NOT need separate hooks for `wp-page`. The document type `wp-page` does NOT exist — pages also use `wp-post`.

### Persisting Elementor document settings to post meta
When you need the same setting accessible from both the Elementor editor and the classic editor (meta box), store it in post meta and sync on save:
```php
add_action( 'elementor/document/after_save', function( $document, $data ) {
    if ( ! isset( $data['settings'] ) ) {
        return;
    }
    $post = $document->get_post();
    // IMPORTANT: When a SWITCHER is turned off, Elementor OMITS the key entirely.
    // Treat missing key as "off" — do NOT bail with isset() check.
    $enabled = ! empty( $data['settings']['my_toggle'] ) && 'yes' === $data['settings']['my_toggle'];
    if ( $enabled ) {
        update_post_meta( $post->ID, '_my_meta_key', '1' );
    } else {
        delete_post_meta( $post->ID, '_my_meta_key' );
    }
}, 10, 2 );
```

---

## 6. Lifecycle & Initialization Hooks (Reference)

| Hook | When | Use for |
|------|------|---------|
| `elementor/init` | Elementor loaded | Elementor (free) extensions |
| `elementor_pro/init` | Elementor Pro loaded | Pro extensions (form fields, share buttons, etc.) |
| `elementor/widgets/register` | Widget registration | Register NEW widgets (not for extending) |
| `elementor/dynamic_tags/register` | Tag registration | Register dynamic tags |
| `elementor/documents/register` | Document registration | Register document types |
| `elementor/theme/register_locations` | Location registration | Register theme locations |
| `elementor_pro/forms/actions/register` | Form actions | Register form submit actions |
| `elementor/widget/before_render_content` | Before widget renders | Inject settings before render() |
| `elementor/widget/render_content` | After widget renders | Filter rendered HTML |
| `elementor/widget/print_template` | Editor JS template | Modify editor preview template |
| `elementor/element/{name}/{section}/before_section_end` | Panel build | Inject controls |
| `elementor/document/after_save` | Document saved | Sync settings to post meta |

---

## 7. Enqueueing Scripts & Styles

### Frontend assets (only when widget is on the page)
```php
add_action( 'elementor/frontend/after_enqueue_scripts', function() {
    wp_enqueue_script( 'my-ext-frontend', angie_cs_get_snippet_asset_url( __FILE__, 'js/frontend.js' ), [], '1.0', true );
} );
add_action( 'elementor/frontend/after_enqueue_styles', function() {
    wp_enqueue_style( 'my-ext-frontend', angie_cs_get_snippet_asset_url( __FILE__, 'css/frontend.css' ), [], '1.0' );
} );
```

### Editor assets
```php
add_action( 'elementor/editor/before_enqueue_scripts', function() {
    wp_enqueue_script( 'my-ext-editor', angie_cs_get_snippet_asset_url( __FILE__, 'js/editor.js' ), [], '1.0', true );
} );
```

---

## 8. Common Pitfalls

1. **Elementor SWITCHER "off" omits the key**: When a SWITCHER control is turned off, Elementor does NOT send the key in the saved data. Never use `isset()` to check if it was toggled off — treat a missing key as "off".

2. **WordPress rewrite rules need flushing**: When adding `add_rewrite_rule()`, call it directly in the constructor (not via another `init` hook if your class is already instantiated during `init`). Always provide a way to flush rewrite rules (activation hook or manual flush).

3. **redirect_canonical interferes with custom endpoints**: WordPress may 301-redirect clean URLs (e.g. `/llms.txt` → `/llms.txt/`). Filter `redirect_canonical` to return false for your custom query var.

4. **Pro classes may not exist**: Always load Pro-dependent code inside `elementor_pro/init` or check `defined( 'ELEMENTOR_PRO_VERSION' )` first. Wrap class definitions that extend Pro base classes inside the hook callback so PHP doesn't try to parse them before the parent class is autoloaded (see section 3 for correct pattern).

5. **Form field controls need correct tab structure**: When using `update_controls()` to add fields to the form repeater, you MUST include `tab`, `inner_tab`, and `tabs_wrapper` or the control won't appear in the correct panel tab.

6. **Dynamic tag controls need `dynamic => ['active' => true]`**: If you add a control that should accept dynamic tags, you must explicitly enable it. Not all control types support dynamic tags by default.

7. **Form field render() duplicates attributes**: The Form widget pre-sets `type`, `name`, `id`, and `required` on the input render attributes before your field's `render()` is called. Using `add_render_attribute()` for these will DUPLICATE them (e.g. `type="text range"`). Use `set_render_attribute()` to override `type`, and do NOT set `name`, `id`, or `required` at all.

8. **Custom form fields invisible in editor**: The Form widget's JS template uses `elementor.hooks.applyFilters( 'elementor_pro/forms/content_template/field/' + item.field_type, '', ... )` for unknown field types. If you don't register a JS filter for your field type, it renders nothing in the editor. You MUST add a `content_template_script()` that hooks into this filter via `elementor/preview/init` → `wp_footer`.

9. **Localized settings filters registered too late**: `elementor_pro/frontend/localize_settings` and `elementor_pro/editor/localize_settings` may fire during `elementor_pro/init`. Register these filters at file load or construction time, NOT inside an `elementor_pro/init` callback.

10. **Form action not appearing in "Actions After Submit" dropdown**: The `elementor_pro/forms/actions/register` hook fires DURING `elementor_pro/init`. You must define the class AND register it ALL inside the `elementor_pro/forms/actions/register` callback. Do NOT split class definition into `elementor_pro/init` and registration into a separate hook — the class may not exist yet when the registration fires. Do NOT nest the registration inside `elementor_pro/init` either — it fires too late.

---

## 9. Rules Summary

- NEVER subclass existing widgets — always use hooks
- ALWAYS check plugin availability before using its APIs (`defined('ELEMENTOR_PRO_VERSION')`, `class_exists('ACF')`, etc.)
- Use `angie-snippets` text domain for all translatable strings
- Escape all output (`esc_html`, `esc_attr`, `esc_url`), sanitize all input (`sanitize_text_field`, `sanitize_email`, etc.)
- Wrap class definitions that extend Pro classes inside `elementor_pro/init` to avoid fatal errors
- Wrap class definitions that extend Elementor base classes inside `elementor/init` (or check `did_action`)
- SWITCHER "off" = key missing from data — treat missing key as "off"
- Include `condition` on form action settings sections: `'condition' => ['submit_actions' => $this->get_name()]`
- Document settings hook uses `wp-post` for ALL post types (posts, pages, CPTs) — there is no `wp-page`
- When adding rewrite rules, register them directly (not via nested `init` hooks) and always add a `redirect_canonical` filter if the URL ends with an extension (e.g. `.txt`)
