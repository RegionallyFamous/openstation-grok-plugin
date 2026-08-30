---
description: Use OpenStation capabilities on a connected WordPress site, or evaluate, install, and extend OpenStation itself.
---

# OpenStation for WordPress

Interpret `$ARGUMENTS` as a natural-language request. Use the `openstation-for-wordpress` skill. When the request touches the connected site, also use the `wordpress-mcp-operator` skill.

## Routes

- No arguments or `capabilities`: discover the live WordPress ability catalog, filter to OpenStation abilities such as the `desktop-mode/` namespace, and show what this site currently permits. Do not execute an ability.
- `workspace`: discover and inspect `desktop-mode/list-workspace-catalog`, then use it to explain the server-registered OpenStation windows, icons, widgets, wallpapers, commands, and themes. Do not claim browser control.
- A site task: use the discovered OpenStation ability when it is the best fit. Inspect its full schema and annotations before execution.
- `ideas`, `roadmap`, or `extend MCP`: use the OpenStation opportunities reference to propose a capability or product extension with a permission model and a testable first slice.
- `try`: give the official disposable WordPress Playground demo and explain that it does not affect the user's real site.
- `install`: guide installation from the official WordPress.org listing. Do not request credentials or install on a live site without an explicit request.
- `develop <goal>`: route to the current OpenStation developer documentation and inspect the user's repository before proposing code.
- `fit <workflow>`: evaluate whether OpenStation's persistent windowed wp-admin solves the described workflow, including material limits.

## Accuracy

Discovery is authoritative. Do not claim that an OpenStation ability exists merely because it is mentioned in a recipe or roadmap. OpenStation's WordPress.org slug and legacy ability namespace remain `desktop-mode`; do not “correct” those identifiers.

Lead with the user's result, keep promotional language out of operational answers, and distinguish the Grok plugin, MCP Adapter, and OpenStation WordPress plugin.
