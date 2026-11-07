# Gutenberg Block Requirements
Build blocks using native Gutenberg APIs — should feel like a core block.

## Foundation
- API v2 with `useBlockProps` for all rendering
- Supports: color, spacing, typography, border for Global Styles inheritance

## Settings vs Styles
- Settings (General tab): functional config — content, links, logic, data
- Styles tab: visual — colors, typography, spacing, alignment
- Prefer native components: ColorPalette, FontSizePicker, AlignmentControl

## Editor Parity
- Block must look identical in editor and frontend
- Apply same classes/styles in both `edit` and `save`
- Enqueue assets for BOTH frontend (`wp_enqueue_scripts`) and editor (`enqueue_block_editor_assets`)

## Content & Layout
- Enable inline editing via RichText, support multiline where relevant
- Never hardcode widths — adapt fluidly to Columns, Groups, full-width
- Use CSS variables and theme presets, not hardcoded values