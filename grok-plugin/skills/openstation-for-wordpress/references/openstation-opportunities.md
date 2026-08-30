# OpenStation MCP opportunities

These are product directions to evaluate beyond the current foundation. Discovery remains authoritative: roadmap text never proves an ability is available on a connected site.

## Current foundation: MCP Control Center

OpenStation includes a native administrator window that explains the site's AI-operable surface:

- local MCP Adapter and Abilities API readiness;
- abilities filterable by provider and exposure;
- input/output schema summaries;
- read-only, destructive, and idempotent annotations;
- MCP-public versus internal visibility; and
- a review lane for destructive, unspecified-risk, invalid-MCP-metadata, and publicly exposed write abilities.

The catalogue remains a read-only exposure audit: it does not execute abilities, probe permission callbacks, or claim which capability a callback may inspect. Separate, confirmed setup actions can install/activate the official adapter and opt into OpenStation's editorial pack.

OpenStation also exposes `desktop-mode/list-workspace-catalog`, a read-only catalogue of registered windows, icons, commands, widgets, wallpapers, and desktop themes. It excludes callbacks, asset handles, URLs, nonces, arbitrary config, theme assets/tokens, and browser-local workspace state.

Useful future Control Center work includes a privacy-bounded execution audit with redacted inputs and outputs, declarative capability hints supplied by ability authors, and explicit exposure policy tooling. Those features should not infer facts by invoking callbacks or recording credentials.

## Capability pack candidates

### Shipped: safe editorial handoff

The optional pack now supplies narrow list/read operations, draft-only creation and updates, media metadata reads/updates, and reversible comment moderation. It preserves raw block markup, requires stable IDs, remains private until an administrator confirms exposure, and keeps publishing/deletion/access/settings/plugin operations outside the pack.

### 2. Notes as an agent inbox

Add owner-scoped abilities to list, read, create, and convert OpenStation Notes. A Grok session could leave a reviewable note on the WordPress desktop or turn an approved note into a draft.

Reads and writes must be separate; visibility must follow note ownership/status; conversion must never publish; and returned note text remains untrusted data.

### 3. Files and folders

Start with metadata-only listing and search over the current user's OpenStation files. Keep byte download, upload, sharing, deletion, and folder archives out of the first release because they expand data exposure and destructive scope substantially.

Respect existing ownership, share, quota, MIME, and denied-extension policies rather than creating MCP-specific bypasses.

### 4. Diagnostics briefing

Create narrow read-only abilities for OpenStation version, enabled modules, registered extensions, failed compatibility checks, and recent redacted shell errors. Avoid raw arbitrary log access by default. Return bounded, structured records with secrets redacted on the WordPress side.

### 5. Admin link handoff

Extend admin-destination results with OpenStation window hints so Grok can return links that a user opens in the right workspace context. Keep the actual browser-local open/focus action user-initiated until a separately authenticated client bridge exists.

### 6. OpenStation agents

Do not make a generic “invoke any agent” ability the first capability. It creates nested tool authority, prompt-injection, history, rate-limit, and audit questions. If pursued, expose agent discovery separately from invocation, enforce the invoker's capability ceiling, require explicit agent IDs and source, preserve conversation history, and log every completed action.

## Prioritization

Evaluate the next slices in this order:

1. Diagnostics briefing.
2. Notes inbox.
3. Carefully bounded browser handoff.
4. Metadata-first files and folders.
5. Carefully constrained agent discovery before any invocation path.

Each slice should ship with permission-denial tests, schema validation tests, metadata/annotation tests, MCP visibility tests, and an example Grok workflow that cannot exceed the ability's stated effect.
