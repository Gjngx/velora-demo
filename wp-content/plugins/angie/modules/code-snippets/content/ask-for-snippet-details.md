# Ask for Snippet Details

Use your host's native ask-question tool (e.g. Cursor AskQuestion, Claude Code ask user) — **not** an Angie MCP tool.

Ask guiding questions when a snippet or widget request is too vague to implement predictably.

## When to ask
- Only if you cannot answer internally: "Do I understand enough to act safely and predictably?" If yes, do not ask.
- If the answer would not change the implementation, do not ask.

**CRITICAL:** When you use the host ask tool, that must be the only Angie MCP **tool call** (`tools/call`) in the turn — no snippet list/create/update/publish tools alongside it. You may read Angie guide resources (`resources/read`, including this one) in the same turn before asking, or read them in an earlier turn. After asking, stop completely and wait for the user's response before any other Angie MCP tool calls.

## Rules
- Do not ask for information the user already provided or clearly implied.
- When the user asks to build a feature (e.g. "build a form", "add a popup", "create a slider") without specifying how to build it, and `context.website.platform` is not "elementor" or "gutenberg", include a "How would you like to build this?" question with these choices: Elementor, Gutenberg, Generic code snippet, Shortcode.
- When the build method is already clear from context (`context.website.platform` is "elementor" or "gutenberg", or the user has explicitly stated a method), do not ask about it.
- Users can skip any question; you must still continue afterward.
- Do not mention defaults, "recommended" options, or platform guesses unless the user raised them.

## What to ask (only gaps that matter)
1) **Purpose** — What outcome should this produce? What should site visitors see or do? What should the editor or admin control or trigger?
2) **Behavior** — What triggers it (page load, click, hover, form submit, login, cart event, etc.)? Distinct states or modes? Runs once, on repeat, or only under conditions? For PHP-only work, is the change frontend, admin, or both?
3) **Data** — What inputs and sources (fields, post types, taxonomies, query rules)? What renders on the public site vs what stays in the admin?
4) **Presentation** — Only if it changes implementation: layout, motion, or visibility.

## How to write each question and choice
- The question must name the decision you need in plain language (avoid vague prompts like "vision" or "vibe").
- Each choice must read as a complete answer to that question (not "Option A" or a single word that needs context).
- Good: "When someone opens this on the page, what should happen first?" with choices that describe distinct behaviors.
- Bad: "What is your goal?" with choices like "Better experience" or "More dynamic."
- Prefer choice-style questions with 3–5 concrete options so users can tap an answer.

Infer what you can from context first — only ask what you truly cannot figure out.