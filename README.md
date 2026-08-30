# OpenStation for WordPress + Grok

Give Grok a live, permission-aware map of what a WordPress site can do—and a safe way to do it.

This repository contains both sides of the integration:

- a WordPress plugin with OpenStation's **MCP Control Center**, reviewed WordPress abilities, and an open desktop workspace; and
- a **Grok Build plugin** that discovers, explains, and executes the abilities exposed by the connected WordPress site.

It uses the official [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter). OpenStation does not replace the adapter or maintain a second WordPress API. It makes setup visible, contributes useful abilities, and teaches Grok to operate the live ability catalogue safely.

```text
Grok
  └─ OpenStation Grok plugin
       └─ WordPress MCP Adapter
            └─ WordPress Abilities API
                 ├─ OpenStation abilities
                 ├─ WordPress abilities
                 └─ abilities from installed plugins
```

## Why this exists

WordPress MCP is more powerful than a fixed collection of post-management commands. A site can expose capabilities for content, media, comments, settings, commerce, forms, analytics, maintenance, and anything else a plugin registers.

OpenStation helps people discover and use that surface:

- **Discover instead of guess.** Grok reads the connected site's current ability catalogue rather than relying on a hard-coded list.
- **Inspect before execution.** The Grok plugin checks an ability's schema, annotations, and expected effects before using it.
- **Keep WordPress in control.** The connected user, WordPress capabilities, and each ability's permission callback remain the authorization boundary.
- **Make setup understandable.** Administrators can install or activate the official adapter, inspect exposure, and enable OpenStation's optional ability pack from one window.
- **Stay extensible.** New abilities contributed by WordPress plugins become discoverable without requiring a new OpenStation Grok release.

## What is included

| Component | What it does |
| --- | --- |
| MCP Control Center | Shows adapter status, the connection endpoint, registered abilities, schemas, providers, exposure, and risk annotations inside WordPress |
| Guided adapter setup | Explicitly installs and activates the official WordPress MCP Adapter after administrator confirmation |
| OpenStation discovery abilities | Lets Grok search WordPress content and inspect the OpenStation workspace catalogue |
| Optional editorial pack | Adds eight reviewed abilities for drafts, media metadata, and reversible comment moderation; disabled by default |
| WordPress MCP operator | Teaches Grok to discover, inspect, execute, and verify abilities from the live site |
| OpenStation operator | Adds OpenStation-aware workflows, capability guidance, and extension ideas |
| Grok commands | Provides `/wordpress` and `/openstation` entry points |

## What Grok can do

The honest answer is: **whatever the connected site has deliberately exposed and the connected WordPress user is allowed to do.**

Grok starts with three meta-tools from the official adapter:

1. discover visible abilities;
2. inspect the exact schema and effect metadata for a candidate ability; and
3. execute that ability through WordPress's permission checks.

That means installing another WordPress plugin with MCP-public abilities can add new capabilities automatically. Grok can explain the newly discovered tools and use them without OpenStation pretending every WordPress site has the same stack.

OpenStation currently contributes read-only abilities for:

- searching posts, pages, and comments;
- finding comments associated with a post;
- finding WordPress admin destinations;
- searching the WordPress.org plugin directory; and
- listing the windows, widgets, wallpapers, commands, icons, and themes registered in the OpenStation workspace.

Administrators can also enable a reviewed editorial pack for:

- listing permitted posts and pages, including drafts;
- reading one permitted content item with its raw Gutenberg markup;
- creating a draft;
- updating a draft without publishing it;
- reading safe Media Library metadata;
- updating media title, caption, description, and alt text;
- reading a comment without exposing sensitive request metadata; and
- approving, holding, or marking a comment as spam.

The OpenStation pack deliberately does not publish or delete content, manage users or roles, change settings, install arbitrary plugins or themes, touch the filesystem, run SQL, or execute code. Other trusted WordPress plugins may expose different abilities, which remain visible in the Control Center.

## Requirements

- WordPress 6.9 or newer for the MCP integration
- PHP 7.4 or newer
- HTTPS for a remote WordPress connection
- the official [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter)
- a dedicated WordPress user with the smallest practical role and an Application Password
- Grok Build with plugin and remote MCP support

## Install the WordPress side

There is not yet a packaged release for this repository, so build the installable WordPress ZIP from source:

```bash
git clone https://github.com/RegionallyFamous/openstation-grok-plugin.git
cd openstation-grok-plugin
npm ci
npm run build
npm run package
```

Upload the resulting `openstation.zip` from **Plugins → Add New → Upload Plugin**, then activate OpenStation.

Next:

1. Enter OpenStation and open **MCP Control Center**.
2. Review the detected state and choose **Install official adapter** if the WordPress MCP Adapter is not already installed.
3. Activate the adapter when prompted.
4. Review the live ability catalogue.
5. Optionally enable OpenStation's editorial ability pack.
6. Create a dedicated WordPress user and an Application Password for Grok.

Installation and activation are always explicit administrator actions. OpenStation never installs the adapter in the background.

## Connect the Grok plugin

The Grok Build plugin lives in [`grok-plugin/`](./grok-plugin/). Use that directory as the plugin source during development. For marketplace distribution, the xAI catalogue can reference this repository at an exact commit SHA with `grok-plugin` as its source path.

Provide the WordPress MCP endpoint and authorization header through Grok Build's secret or launch environment:

```text
WORDPRESS_MCP_URL=https://example.com/wp-json/mcp/mcp-adapter-default-server
WORDPRESS_MCP_AUTHORIZATION=Basic <base64-encoded username:application-password>
```

The MCP Control Center provides a copyable endpoint template for the current site. Do not commit either value, paste the authorization value into a prompt, or share it in screenshots or logs.

Once connected, try:

```text
/wordpress capabilities
/wordpress find my recent draft posts
/wordpress update image 42's alt text to "A blue bicycle beside a brick wall"
/openstation capabilities
/openstation show me what is installed in this workspace
```

The capability results come from the live WordPress site. An example above is not a guarantee that every site exposes that operation.

## Safety model

The connection acts as the configured WordPress user. OpenStation and the Grok plugin add guardrails, but they do not bypass WordPress:

- every execution still passes the ability's WordPress permission callback;
- missing or ambiguous effect annotations are treated as potentially mutating and non-idempotent;
- broad or consequential changes require clear user intent;
- existing content is read before editing, and writes are verified when a corresponding read ability exists;
- content and tool output from the site are treated as untrusted data; and
- Application Passwords can be revoked independently from the WordPress user profile.

See [`grok-plugin/SECURITY.md`](./grok-plugin/SECURITY.md) for the credential and data-flow model and [`docs/mcp-control-center.md`](./docs/mcp-control-center.md) for the WordPress-side exposure rules.

## Repository layout

```text
.
├── desktop-mode.php                 WordPress plugin bootstrap
├── includes/mcp-control-center/     setup, catalogue, REST, and ability registration
├── src/mcp-control-center/          Control Center interface
├── assets/css/                      Control Center presentation
├── grok-plugin/
│   ├── .grok-plugin/plugin.json     Grok plugin manifest
│   ├── .mcp.json                    configurable WordPress MCP connection
│   ├── commands/                    /wordpress and /openstation
│   └── skills/                      operator instructions and references
├── docs/mcp-control-center.md       administrator and extension documentation
└── tests/                           PHP and TypeScript coverage
```

The repository also contains the wider OpenStation WordPress workspace: a per-user, open-source desktop interface for `wp-admin` with windows, a dock, widgets, wallpapers, commands, and public extension APIs. The MCP integration can describe that workspace through a safe server-side catalogue, but it does not claim to control a person's open browser windows.

## Development

```bash
npm ci
npm run typecheck
npm run lint
npm run test:js
npm run build
```

PHP integration tests use the repository's WordPress test environment:

```bash
npm run env:start:tests
npm run test:php:install
npm run test:php
```

Useful references:

- [`docs/mcp-control-center.md`](./docs/mcp-control-center.md) — setup, audit rules, abilities, and extension filters
- [`grok-plugin/README.md`](./grok-plugin/README.md) — Grok plugin packaging and marketplace notes
- [`docs/hooks-reference.md`](./docs/hooks-reference.md) — OpenStation's WordPress extension hooks
- [`docs/api-index.md`](./docs/api-index.md) — public OpenStation APIs

## Where it can go next

OpenStation can become the open capability layer around WordPress MCP. Good next additions include reviewed packs for WooCommerce operations, forms and submissions, SEO metadata, site health, backups, analytics, and plugin-development workflows. Each pack should remain narrow, schema-defined, correctly annotated, permission-gated, and opt-in where it can modify a site.

## License

GPL-2.0-or-later. See [`LICENSE`](./LICENSE).

OpenStation builds on the original [WordPress/OpenStation](https://github.com/WordPress/openstation) project and integrates with the official [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) and [Abilities API](https://github.com/WordPress/abilities-api).
