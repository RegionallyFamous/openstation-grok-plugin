# MCP Control Center

The MCP Control Center makes WordPress's agent-facing surface visible and configurable inside OpenStation. Administrators can open **MCP Control Center** from the desktop to set up the official bridge, opt into OpenStation's reviewed editorial pack, and audit every registered WordPress ability, its effective MCP exposure, provider, schemas, and declared effect annotations.

The ability catalogue is an audit surface, not an execution console. It never invokes an ability or its permission callback. Setup changes happen only after the administrator presses a named action and accepts its confirmation.

## Guided setup

The Control Center detects the difference between an uninstalled, installed-but-inactive, and active MCP Adapter. An administrator with both `install_plugins` and `activate_plugins` can choose **Install official adapter**. OpenStation then downloads the package URL published by the WordPress project's installation guide:

```text
https://github.com/WordPress/mcp-adapter/releases/latest/download/mcp-adapter.zip
```

The URL is hard-coded and cannot be filtered to another download source. OpenStation installs nothing in the background, never bundles the adapter, and does not make MCP Adapter a hard plugin dependency. Installation and activation are separate from enabling OpenStation's editorial abilities.

The connection card links to WordPress user and Application Password management, reports HTTPS/Application Password readiness, and provides a copyable environment template for the Grok plugin. Use a dedicated least-privileged user, keep the Application Password out of prompts and logs, and revoke it from WordPress when it is no longer needed.

## What the window reports

- whether the official WordPress MCP Adapter is installed and active;
- whether its default server is enabled;
- the default server endpoint and detected adapter version;
- all registered abilities, including abilities that are private over MCP;
- whether exposure comes from `meta.mcp.public`, an explicit MCP opt-out, inherited `meta.public`, or neither;
- read-only, write, destructive, and unspecified-risk classifications derived from ability annotations; and
- copyable input and output schemas.

The **Review** filter includes destructive abilities, abilities with unspecified risk, invalid MCP exposure or component-type metadata, and write abilities exposed publicly over MCP. It is a review aid, not a security verdict. WordPress capabilities and each ability's `permission_callback` remain the authorization boundary.

The window and its REST route require `manage_options` by default. The route is:

```text
GET /wp-json/desktop-mode/v1/mcp-control-center
```

The response may describe privileged internal abilities, so it must not be made public merely because it is read-only.

The nonce-protected setup actions use `wp_ajax_openstation_mcp_setup`. They require the Control Center's administrator gate, and installation/activation additionally use WordPress's native plugin capabilities.

## Optional editorial ability pack

The editorial pack is disabled by default. Enabling it makes these eight abilities MCP-public; disabling it makes them private again without changing site content:

| Ability | Effect | WordPress boundary |
| --- | --- | --- |
| `desktop-mode/list-editorial-content` | List permitted posts/pages by status, including drafts | `edit_posts` or `edit_pages`, then per-item `read_post` |
| `desktop-mode/get-content-item` | Read one permitted post/page with raw Gutenberg markup | `read_post` |
| `desktop-mode/create-draft` | Create a new draft post/page; cannot publish | `edit_posts` or `edit_pages` |
| `desktop-mode/update-draft` | Change title/content/excerpt only while the target remains a draft | `edit_post` |
| `desktop-mode/get-media-item` | Read safe Media Library metadata | `upload_files` |
| `desktop-mode/update-media-metadata` | Change text metadata; never replaces/deletes the file | `edit_post` on the attachment |
| `desktop-mode/get-comment` | Read a comment for moderation without email, IP, user agent, or author URL | `moderate_comments` |
| `desktop-mode/moderate-comment` | Approve, hold, or mark one comment as spam; cannot trash/delete | `moderate_comments` |

Publishing, deletion, user/role access, settings, plugin/theme changes, filesystem operations, arbitrary SQL, and arbitrary code execution are deliberately absent. Other WordPress plugins can still contribute their own abilities; the Control Center audits those registrations and the Grok plugin discovers whatever the connected site actually exposes.

## Workspace catalogue ability

OpenStation also registers the MCP-public, read-only ability:

```text
desktop-mode/list-workspace-catalog
```

It lists the OpenStation windows, desktop icons, widgets, wallpapers, commands, and desktop themes registered for the current WordPress user. This lets an MCP client explain what the installed OpenStation workspace offers without pretending it can control an open browser tab.

The result is intentionally a safe server-side projection. It excludes callbacks, script and style handles, URLs, nonces, arbitrary window config, theme assets and tokens, saved geometry, open-window state, and other browser-local data. The ability does not open, focus, rearrange, or customize anything.

As with every MCP ability, discovery is authoritative. A client should discover the current catalogue, inspect this ability's schema, and then execute it through MCP Adapter's meta-tools.

## Exposure rules

The audit mirrors MCP Adapter's fail-closed rules:

1. An explicit boolean `meta.mcp.public` wins.
2. Otherwise, a strict boolean `meta.public: true` is inherited.
3. Missing values and string values such as `"true"` are private.

An ability can still deny a particular user or input through its permission callback after discovery. Being listed as MCP-public means discoverable through the server; it does not grant authorization.

## Extension filters

`openstation_mcp_control_center_user_can_use`
: Changes the administrator gate used by both the window and REST route. Avoid widening it unless users are meant to see schemas for every private ability.

`openstation_mcp_control_center_window_args`
: Filters the native-window registration arguments.

`openstation_mcp_control_center_icon_args`
: Filters the desktop-icon registration arguments.

`openstation_mcp_editorial_pack_enabled`
: Filters the stored editorial-pack exposure state. Returning true makes all eight reviewed abilities MCP-public for the request; keep custom policy checks deterministic and site-side.

The workspace catalogue has no filter that injects arbitrary fields. Add a separate, narrowly shaped ability when an extension needs to expose additional data.
