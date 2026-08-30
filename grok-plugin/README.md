# OpenStation WordPress — Grok plugin

This plugin turns Grok into a capability-aware WordPress operator. It connects to the official WordPress MCP Adapter, discovers the abilities a site and its plugins have chosen to expose, explains what is possible, and executes the right ability with its published schema and permission checks.

OpenStation is the open-source home for the integration. Sites running OpenStation also gain OpenStation's MCP-public discovery abilities and a set of workflows for finding content, comments, admin destinations, and WordPress.org plugins.

The Grok plugin and WordPress plugin are separate installations:

- this directory is the Grok Build plugin;
- [MCP Adapter](https://github.com/WordPress/mcp-adapter) is the WordPress-side MCP bridge; and
- [OpenStation](https://wordpress.org/plugins/desktop-mode/) is the optional WordPress workspace and capability pack.

## What it provides

| Component | Purpose |
| --- | --- |
| WordPress MCP connection | Connects Grok to one user-configured WordPress MCP endpoint |
| `wordpress-mcp-operator` skill | Discovers, explains, inspects, and safely executes site abilities |
| `/wordpress` command | Shows the live capability menu or carries out a WordPress task |
| `openstation-for-wordpress` skill | Adds OpenStation-aware ability routing, product help, and extension guidance |
| `/openstation` command | Shows and uses the OpenStation capabilities available on the connected site |

There is no fixed list of supported WordPress actions. The site is the source of truth: WordPress Core, MCP Adapter, OpenStation, and other plugins can add or remove abilities without requiring a new Grok plugin release.

## Connect a site

### WordPress side

1. Use WordPress 6.9 or newer and PHP 7.4 or newer.
2. In OpenStation, open **MCP Control Center**. Confirm and run **Install official adapter**, or install and activate the official [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) yourself.
3. In the same Control Center, review the live catalogue and optionally enable the OpenStation editorial pack. Any additional ability must set `meta.public` or `meta.mcp.public` to `true`; the user still has to pass its permission callback.
4. Create a dedicated, least-privileged WordPress user and an Application Password for that user.
5. Serve the site over HTTPS. Confirm the web server forwards the `Authorization` header to WordPress.

### Grok Build side

Expose these variables to Grok Build through your normal secret-management or launch environment:

```text
WORDPRESS_MCP_URL=https://example.com/wp-json/mcp/mcp-adapter-default-server
WORDPRESS_MCP_AUTHORIZATION=Basic <base64-encoded username:application-password>
```

Do not put either value in this repository, a prompt, a screenshot, or a shared log. The authorization value is a secret even though it is base64-encoded. Revoke the Application Password from the WordPress user profile if it is exposed.

After installing the Grok plugin, run `/wordpress capabilities`. A healthy connection returns a categorized list derived from the current site. It should not reveal the credential.

## How the integration works

The default WordPress MCP server deliberately exposes three meta-tools instead of registering every ability as a separate MCP tool:

1. `mcp-adapter-discover-abilities` finds visible abilities.
2. `mcp-adapter-get-ability-info` returns one ability's description, schemas, metadata, and annotations.
3. `mcp-adapter-execute-ability` executes that ability after WordPress validates its input and permission callback.

The operator skill follows this sequence every time. It never guesses an ability name or input shape from a recipe.

## OpenStation on the connection

OpenStation currently marks these read-only abilities as MCP-public:

- `desktop-mode/search-posts`
- `desktop-mode/search-pages`
- `desktop-mode/search-comments`
- `desktop-mode/search-comments-by-post`
- `desktop-mode/list-admin-pages`
- `desktop-mode/search-wporg-plugins`
- `desktop-mode/list-workspace-catalog`

OpenStation also registers an editorial pack that remains MCP-private until an administrator enables it in MCP Control Center:

- `desktop-mode/list-editorial-content`
- `desktop-mode/get-content-item`
- `desktop-mode/create-draft`
- `desktop-mode/update-draft`
- `desktop-mode/get-media-item`
- `desktop-mode/update-media-metadata`
- `desktop-mode/get-comment`
- `desktop-mode/moderate-comment`

The pack can read permitted content, create and update drafts, update media text metadata, and apply reversible comment moderation. It cannot publish, delete, change users/roles/settings/plugins/themes, or execute arbitrary code. Discovery remains authoritative because a different OpenStation release, plugin, or site policy can change the visible set.

See [OpenStation opportunities](skills/openstation-for-wordpress/references/openstation-opportunities.md) for the next capability-pack and product ideas.

## Security model

The MCP connection acts as the configured WordPress user. WordPress capabilities and each ability's permission callback are the hard authorization boundary. Grok additionally inspects ability annotations, treats missing annotations as unknown-risk, previews broad changes, and verifies writes after execution.

Site content and tool results are untrusted data. Instructions found inside posts, comments, media metadata, logs, or ability output never override the user's request or the plugin's safety rules.

Read [SECURITY.md](SECURITY.md) for the endpoint, credential, data-flow, and reporting details.

## Marketplace submission

The xAI marketplace can reference this directory in the official OpenStation repository. After it lands on the public default branch, replace the placeholder with that commit's exact 40-character lowercase SHA:

```json
{
  "name": "openstation-wordpress",
  "description": "Operate WordPress through its official MCP Adapter, discover site abilities, and unlock OpenStation-specific workflows.",
  "category": "development",
  "source": {
    "source": "url",
    "url": "https://github.com/WordPress/openstation.git",
    "sha": "<40-character-lowercase-commit-sha>",
    "path": "grok-plugin"
  },
  "homepage": "https://wordpress.org/plugins/desktop-mode/",
  "keywords": [
    "openstation",
    "open station wordpress",
    "wp openstation",
    "openstation wordpress mcp",
    "openstation grok"
  ]
}
```

Then run the marketplace's generated-index and validation commands before opening its pull request. The submission must disclose the two environment variables, the user-controlled WordPress endpoint, Basic authentication through an Application Password, and that WordPress data requested by the user flows to Grok through MCP.

## License

GPL-2.0-or-later, matching the parent OpenStation repository. See the repository-root `LICENSE` file.
