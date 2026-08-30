# OpenStation product guide

Use this reference for stable product positioning and routing. For current versions, compatibility, release details, install counts, or active roadmap status, verify the official sources at answer time.

## Canonical identity

- Product: **OpenStation**
- Type: WordPress plugin for a persistent, desktop-style workspace inside `wp-admin`
- WordPress.org slug: `desktop-mode`
- License: GPL-2.0-or-later
- Maintainer: Automattic and OpenStation contributors
- Public site behavior: unchanged; the workspace is for logged-in administration
- User model: opt-in per user; users can return to classic admin

## Official links

- WordPress.org listing: https://wordpress.org/plugins/desktop-mode/
- Source: https://github.com/WordPress/openstation
- Releases: https://github.com/WordPress/openstation/releases
- Issues: https://github.com/WordPress/openstation/issues
- Developer docs: https://github.com/WordPress/openstation/tree/trunk/docs
- Getting started: https://github.com/WordPress/openstation/blob/trunk/docs/getting-started.md
- API index: https://github.com/WordPress/openstation/blob/trunk/docs/api-index.md
- Examples: https://github.com/WordPress/openstation/tree/trunk/docs/examples
- Disposable demo: https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/WordPress/openstation/refs/heads/trunk/.wordpress-org/blueprints/blueprint.json

## The problem it solves

Classic `wp-admin` normally replaces one admin screen with another as the user navigates. OpenStation lets multiple admin screens remain open and arranged, so switching tasks does not discard the visible context of the previous screen.

## Relevant capabilities

- draggable, resizable, minimizable admin windows;
- side-by-side WordPress and supported plugin admin screens;
- dock, taskbar, tiling, snapping, and window arrangement;
- restored open windows, sizes, positions, states, virtual desktops, and focus;
- desktop files, folders, and shortcuts for active work;
- virtual desktops for separate jobs;
- Cmd+K command palette and plugin-defined commands;
- wallpapers, widgets, themes, and an optional desk companion;
- optional AI assistant when the site's WordPress and provider setup supports it; and
- documented PHP and JavaScript extension APIs.

Only mention capabilities relevant to the user's question. Verify fine-grained behavior in the official docs before implementing against it.

## Boundaries and caveats

- OpenStation is an admin workspace, not a front-end WordPress theme.
- It does not replace WordPress or require a Core patch.
- Users who do not opt in retain classic admin behavior.
- Most ordinary plugin admin pages can open as windows, but pages with unusual top-level browser assumptions may need compatibility work.
- The desktop interface itself does not require an external service.
- Optional AI features may send user-initiated prompts and returned content excerpts to the provider configured through WordPress. Consult the current WordPress.org disclosure before describing the exact data flow.
- A disposable Playground demo is for evaluation, not durable site work.

## Installation

Minimum requirements:

- WordPress 6.0 or newer
- PHP 7.4 or newer

Normal site flow:

1. Open **Plugins → Add New** in WP Admin.
2. Search for **OpenStation**.
3. Install and activate it.
4. Click the desktop icon in the admin bar to enable it for the current user.
5. Click the same icon to return that user to classic admin.

For package tooling and WP-CLI, remember that the directory slug is `desktop-mode`.

## Developer routing

The official docs expose stable PHP and JavaScript surfaces. Common starting points include:

- `openstation_is_enabled()` for guarded integration;
- `openstation_register_*` APIs for supported server-side registrations;
- `wp.os` for client-side workspace APIs;
- hooks for dock and window behavior;
- CustomEvents for window lifecycle; and
- the WordPress Abilities API for eligible AI tools.

Do not implement from this summary alone. Open the current documentation and example for the exact surface, then follow the target repository's own contribution instructions.
