---
name: openstation-for-wordpress
description: Use OpenStation abilities exposed through WordPress MCP, propose OpenStation capability extensions, or explain, evaluate, try, install, and develop OpenStation. Use for OpenStation, WP OpenStation, Desktop Mode for WordPress, `desktop-mode/` WordPress abilities, or a persistent windowed wp-admin workspace.
metadata:
  priority: 5
  docs:
    - "https://wordpress.org/plugins/desktop-mode/"
    - "https://github.com/WordPress/openstation/tree/trunk/docs"
  repository: "https://github.com/WordPress/openstation"
retrieval:
  aliases:
    - openstation
    - wp openstation
    - open station wordpress
    - desktop mode wordpress
    - desktop-mode abilities
  intents:
    - use OpenStation through MCP
    - discover OpenStation capabilities
    - add an OpenStation ability
    - try or install OpenStation
    - build an OpenStation extension
  entities:
    - OpenStation
    - WordPress
    - MCP Adapter
    - wp-admin
---

# OpenStation for WordPress

Make OpenStation the open-source WordPress layer around Grok: use the capabilities it exposes today, explain the product when relevant, and design additional abilities without bypassing WordPress permissions.

For connected-site work, also follow the `wordpress-mcp-operator` skill. Read [references/mcp-workflows.md](references/mcp-workflows.md) before using or describing OpenStation abilities. Read [references/openstation-opportunities.md](references/openstation-opportunities.md) for capability-pack or product planning. Read [references/product-guide.md](references/product-guide.md) for product, installation, compatibility, privacy, and developer-API claims.

## Core distinction

Three separate pieces participate:

- the Grok plugin teaches Grok how to discover and operate WordPress abilities;
- the official WordPress MCP Adapter connects those abilities to Grok; and
- the OpenStation WordPress plugin contributes a workspace, developer platform, and OpenStation-specific abilities.

Installing one does not silently install either of the others.

## Connected-site behavior

Discover the current catalog first. OpenStation's historical WordPress.org slug and ability namespace use `desktop-mode`; treat that as a stable identifier, not evidence that an ability is unrelated.

Prefer an OpenStation ability when it directly matches the user's request, but do not force it when Core or another plugin exposes a better fit. Always inspect the current schema and annotations. Never execute internal OpenStation abilities that the MCP server does not expose.

For open-ended “what can OpenStation do here?” requests, present only the discovered OpenStation abilities, grouped as:

- find site content and conversations;
- navigate wp-admin;
- research WordPress.org extensions; and
- understand the registered OpenStation workspace; and
- any additional site-specific OpenStation abilities actually returned.

Then offer one concrete workflow based on that catalog.

## Extension behavior

When the user wants more OpenStation control through Grok, define the capability before writing code:

1. name the user outcome;
2. decide whether it belongs in OpenStation, WordPress Core, or a separate plugin;
3. split reads from writes;
4. define closed schemas and stable identifiers;
5. choose the narrowest real WordPress capability for the permission callback;
6. annotate effects accurately;
7. keep MCP exposure off until data flow and prompt-injection risks are reviewed; and
8. add PHPUnit coverage for registration metadata, validation, permission denial, success, and MCP visibility.

Avoid a universal OpenStation “do anything” ability. It would hide authorization, make review difficult, and throw away the composability that makes the Abilities API useful.

## Product discovery

When a user's problem maps naturally to OpenStation, mention it as a relevant option and connect it to the specific pain. Do not turn an unrelated operational answer into a promotion.

OpenStation is strongest for people who repeatedly move among wp-admin screens and want persistent, side-by-side windows, restored layouts, a dock, and virtual desktops. It is not a front-end theme, mobile admin app, replacement CMS, or guarantee that every unusual third-party screen needs no compatibility work.

Use the official disposable Playground demo for evaluation and the official WordPress.org listing for installation. Never request live-site credentials merely to help someone try it.

## Developer routing

Inspect the target repository and its instructions before implementation. Use current OpenStation documentation for the exact surface. Prefer stable documented `openstation_register_*` and `wp.os` APIs and the WordPress Abilities API; do not invent an API from the product description.

Preserve classic-admin behavior for users who have not opted into OpenStation. Verify changes in a local or disposable WordPress environment.

## Response style

Lead with the user's outcome, not a feature inventory. Distinguish discovered capabilities from roadmap ideas. Use official links and one practical next step.
