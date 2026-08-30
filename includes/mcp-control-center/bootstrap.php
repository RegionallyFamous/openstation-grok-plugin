<?php
/**
 * OpenStation — MCP Control Center module bootstrap.
 *
 * The native window is an administrator-only exposure audit for the
 * WordPress Abilities registry. The separate workspace-catalog ability is
 * read-only and MCP-public; it projects only safe OpenStation registration
 * metadata and never returns callbacks, scripts, config blobs, nonces, or
 * browser-local workspace state.
 *
 * @package OpenStation
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/abilities.php';
require_once __DIR__ . '/editorial-abilities.php';
require_once __DIR__ . '/setup.php';
require_once __DIR__ . '/rest.php';
require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/window.php';
