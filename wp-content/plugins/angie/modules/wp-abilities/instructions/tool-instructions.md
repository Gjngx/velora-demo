Angie WordPress MCP adapter for the site MCP server.

## Discover pattern (mandatory)

This server exposes four MCP adapter tools. Site abilities (Angie and third-party) are invoked through the adapter — never as direct MCP tool names.

**Hard rule:** ALWAYS call `angie-get-ability-info` immediately before EVERY `angie-execute-ability` call for the same `ability_name`. No exceptions.

Required sequence:

1. `angie-discover-abilities` — lists exposed tool abilities, `angie_instructions`, and markdown `guides`.
2. `angie-get-ability-info` — with `{ "ability_name": "namespace/ability" }`; read `input_schema` and `output_schema`.
3. `angie-execute-ability` — with `{ "ability_name": "namespace/ability", "parameters": { ... } }` using **only** fields from step 2.

**Guide resources:** Read markdown guides with `angie-read-resource` and `{ "uri": "..." }` (not execute-ability).

If execute fails with invalid input, call `get-ability-info` again — do not retry with guessed fields.

Ability names use a slash (`angie/list-snippets`, `core/get-site-info`), not a colon or hyphen.
