# Code Snippets Workflow

Tools live on the `wp-abilities-code-snippets` MCP server as `angie_<action>` (for example `angie_create-snippet`). Guide resources use `angie://code-snippet/guide/<slug>` URIs.

## Step 0 — Vague request?
If you cannot predict what to build or how it should behave, read the `angie/ask-for-snippet-details` resource (`angie://code-snippet/guide/ask-for-snippet-details`), then use your host's native ask-question tool — with **no Angie MCP tool calls** (`tools/call`) in that same turn besides reading guide resources if needed. After asking, **stop completely and wait** for the user's response before doing anything else. Before deciding to ask, check whether you already understand enough to act safely; if yes, skip asking. Do not repeat details the user already gave. Do not assume Elementor widget vs block vs PHP snippet — ask in neutral terms (outcome, triggers, data, what visitors see). Users may skip questions; continue with safe assumptions.

## Creating a New Snippet
1. `angie_create-snippet` with `title` + `type` → receive `{ id, title }`
2. Generate code using the naming convention with the returned `id`
3. `angie_update-snippet-files` with the `id`, files array (must include main.php), and for `elementor-widget` snippets **`elementor_preview_settings`** with control values for iframe preview (see below)
4. When the response includes `previewInstruction`, call `angie-preview-widget` directly with `{ "id": <post_id> }` to open the MCP App preview (do not use execute-ability for preview)

### Elementor widget preview settings (REQUIRED)
When `type` is `elementor-widget`, every `angie_update-snippet-files` call MUST include `elementor_preview_settings` — a JSON object keyed by control names from `register_controls()`.

- **MEDIA controls** (`Controls_Manager::MEDIA`): `{ "control_name": { "id": "", "url": "https://images.unsplash.com/photo-..." } }`
- **GALLERY controls** (`Controls_Manager::GALLERY`): `{ "gallery": [ { "id": "", "url": "https://images.unsplash.com/photo-..." }, ... ] }` — use at least 4 images for gallery widgets
- **REPEATER with media fields**: `{ "slides": [ { "image": { "id": "", "url": "https://images.unsplash.com/..." } }, ... ] }` — include enough items to show the widget layout (typically 3+)

Use high-quality Unsplash URLs (`https://images.unsplash.com/photo-...`) relevant to the widget topic. Never use placeholder.com or broken links.

If you omit `elementor_preview_settings`, the server applies a generic image fallback for preview — but explicit settings produce much better previews. Read `angie://code-snippet/guide/elementor-widget-requirements` for full details.

### Snippet Types
`code-snippet` | `elementor-widget` | `gutenberg-block` | `popup` | `form` | `visual-app` | `web-mcp`

## Editing an Existing Snippet
1. Get the `id` from `angie_list-snippets`
2. `angie_get-snippet-file` to read current code
3. `angie_update-snippet-files` with only changed files (auto-merge, unchanged files preserved). For `elementor-widget` snippets, include `elementor_preview_settings` when image/gallery controls changed or preview looks empty.

## Delete / Publish
- `angie_delete-snippet` with `id` — requires user confirmation
- `angie_publish-snippet` with `id` — ONLY when user explicitly asks

## Error Handling (CRITICAL)
When any tool returns `isError: true`:
1. NEVER pretend the operation succeeded — tell the user exactly what went wrong in simple terms
2. Explain what caused the error and suggest a concrete fix
3. If the error is `forbidden_function`, it means a word like "system", "exec", or "assert" appeared in the code followed by `(` — even inside a string. Rename the offending label/string and retry.
4. Ask the user how they want to proceed
