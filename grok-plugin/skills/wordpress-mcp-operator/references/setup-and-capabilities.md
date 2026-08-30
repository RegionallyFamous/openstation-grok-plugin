# WordPress MCP setup and capability model

## Prerequisites

- WordPress 6.9 or newer
- PHP 7.4 or newer
- Official WordPress MCP Adapter active
- A WordPress user with only the capabilities needed for the intended work
- A revocable Application Password for that user
- HTTPS and a web server that forwards the `Authorization` header

When OpenStation is installed, its administrator-only **MCP Control Center** is the preferred setup surface. It can explicitly install/activate the latest official adapter release, show the exact endpoint, link to Application Password management, and enable OpenStation's reviewed editorial pack. Installation and pack exposure are separate confirmations; neither happens automatically.

The default remote endpoint is:

```text
https://example.com/wp-json/mcp/mcp-adapter-default-server
```

The Grok plugin expands these values into `.mcp.json` at load time:

```text
WORDPRESS_MCP_URL=<full endpoint URL>
WORDPRESS_MCP_AUTHORIZATION=Basic <base64-encoded username:application-password>
```

Never ask the user to paste the authorization value into chat. Diagnose presence, URL shape, HTTP status, and server behavior without showing the header.

## What “everything” means

The MCP Adapter does not grant universal access to WordPress. It exposes a dynamic subset of the WordPress Abilities registry:

- the ability must opt into public or MCP-public exposure;
- the configured WordPress user must pass its permission callback; and
- valid execution input must match its JSON schema.

This is the useful open-source twist: Core and plugins can contribute abilities to one discoverable contract. The Grok plugin can learn a site's current surface at runtime instead of hardcoding a proprietary action list.

## Connection diagnosis

Classify failures before suggesting a fix:

| Symptom | Likely area | Safe next check |
| --- | --- | --- |
| Missing URL variable | Grok configuration | Confirm `WORDPRESS_MCP_URL` exists and includes the full MCP route |
| 401 | Authentication | Confirm the dedicated user and Application Password; ensure the Authorization header reaches PHP |
| 403 | WordPress permission | Confirm the configured user is allowed to read/use MCP and the selected ability permits that user |
| 404 | Route or plugin | Confirm MCP Adapter is active and the endpoint path is exact |
| TLS or network error | Hosting/network | Confirm HTTPS certificate, DNS, firewall, and remote reachability |
| Empty catalog | Exposure policy | Confirm expected abilities set `meta.public` or `meta.mcp.public` and are registered on the request |
| Ability permission error | Per-ability policy | Keep the current credential; use a permitted ability or have a site owner review the callback |
| Schema validation error | Input construction | Re-read ability info and send only fields allowed by the current input schema |

If the user asks for `/wordpress setup` before a connection exists, direct them to **OpenStation → MCP Control Center** in wp-admin when available. Grok cannot install the WordPress-side adapter through a connection that does not exist. Do not claim the setup action succeeded unless the user or a healthy connection confirms it.

Do not recommend disabling TLS, making an ability public without review, replacing a permission callback with `__return_true`, or switching to an Administrator credential as a shortcut.

## Capability menu behavior

When showing what the site can do:

1. Discover the complete visible catalog.
2. Group by user outcome, not merely by namespace.
3. Show a short label and whether the ability is read-only or mutating when known.
4. Put ambiguous abilities in an “inspect before use” group.
5. Mention the active provider namespace when it helps the user identify Core, OpenStation, WooCommerce, or another plugin.
6. Offer two or three useful next requests based on the actual catalog.

Do not execute abilities while building a menu.

## Adding an ability

Use the WordPress Abilities API and current MCP Adapter documentation. A production ability needs:

- a unique namespaced name;
- a specific description and category;
- closed, typed input and output schemas;
- an execution callback;
- a real permission callback;
- accurate read-only, destructive, and idempotent annotations; and
- explicit MCP-public metadata only after its data exposure and effects are reviewed.

Keep a read ability separate from a write ability. Prefer small composable abilities over a general “run arbitrary WordPress” escape hatch.
