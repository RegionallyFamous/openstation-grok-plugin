---
description: Discover what a connected WordPress site can do through MCP, explain its abilities, or carry out a WordPress task safely.
---

# WordPress operator

Interpret `$ARGUMENTS` as a natural-language WordPress request. Use the `wordpress-mcp-operator` skill for every route.

## Routes

- No arguments, `help`, `capabilities`, or “what can I do?”: discover the live ability catalog and present a concise categorized menu. Do not execute a site ability.
- `explain <capability or task>`: discover candidate abilities, inspect their full information, and explain what is possible, what data is required, and whether it reads or changes the site. Do not execute unless the user also asks to do so.
- `setup` or a connection error: diagnose the MCP connection without requesting or printing the authorization secret. If OpenStation is installed, direct the user to its administrator-only MCP Control Center for confirmed adapter installation, ability-pack review, endpoint copying, and Application Password guidance. Grok cannot run WordPress-side setup before a connection exists.
- Any other request: discover, inspect, and execute only the abilities needed to satisfy it. Follow the mutation and verification rules in the skill.

Examples include finding content, preparing drafts, updating media metadata, reviewing comments, locating admin screens, checking settings, inspecting store data, or using abilities contributed by installed plugins. These are examples, not guarantees; the connected site's catalog is authoritative.

## Response

Lead with the result. Name an executed ability when that helps the user verify what happened. For writes, state what changed and how it was verified. If the site does not expose the required capability, name the gap and give the smallest safe way to add or enable an appropriate WordPress ability.
