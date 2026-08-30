<?php
/**
 * OpenStation — MCP-public workspace catalogue ability.
 *
 * @package OpenStation
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalize one catalogue label or identifier to plain single-line text.
 *
 * @param mixed $value Registry value.
 * @return string
 */
function openstation_mcp_workspace_text( $value ) {
	return sanitize_text_field( (string) $value );
}

/**
 * Normalize one catalogue description while preserving intentional lines.
 *
 * @param mixed $value Registry value.
 * @return string
 */
function openstation_mcp_workspace_description( $value ) {
	return sanitize_textarea_field( (string) $value );
}

/**
 * Sort catalogue entries by their human label, then stable id.
 *
 * @param array[] $entries Catalogue entries.
 * @return array[]
 */
function openstation_mcp_workspace_sort_entries( array $entries ) {
	usort(
		$entries,
		static function ( $a, $b ) {
			$label = strcasecmp( (string) ( $a['label'] ?? '' ), (string) ( $b['label'] ?? '' ) );
			return 0 !== $label ? $label : strcasecmp( (string) ( $a['id'] ?? '' ), (string) ( $b['id'] ?? '' ) );
		}
	);
	return $entries;
}

/**
 * Build a safe, read-only catalogue of server-registered OpenStation items.
 *
 * The projection intentionally excludes callbacks, script/style handles,
 * URLs, nonces, theme tokens/assets, arbitrary window config, and every
 * browser-local state value. Registry entries are already capability-gated
 * for the current user when they are registered.
 *
 * @return array
 */
function openstation_mcp_workspace_catalog() {
	$windows = array();
	if ( function_exists( 'openstation_native_window_registry' ) ) {
		foreach ( (array) openstation_native_window_registry() as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['id'] ) ) {
				continue;
			}
			$windows[] = array(
				'id'        => openstation_mcp_workspace_text( $entry['id'] ),
				'label'     => openstation_mcp_workspace_text( $entry['title'] ?? $entry['id'] ),
				'placement' => openstation_mcp_workspace_text( $entry['placement'] ?? 'dock' ),
				'kind'      => openstation_mcp_workspace_text( $entry['nav_kind'] ?? 'app' ),
				'placeable' => ! empty( $entry['placeable'] ),
			);
		}
	}

	$icons = array();
	if ( function_exists( 'openstation_desktop_icon_registry' ) ) {
		foreach ( (array) openstation_desktop_icon_registry() as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['id'] ) ) {
				continue;
			}
			$icons[] = array(
				'id'     => openstation_mcp_workspace_text( $entry['id'] ),
				'label'  => openstation_mcp_workspace_text( $entry['title'] ?? $entry['id'] ),
				'target' => ! empty( $entry['window'] ) ? 'window' : 'link',
				'pinned' => ! empty( $entry['pinned'] ),
			);
		}
	}

	$widgets = array();
	if ( function_exists( 'openstation_desktop_widget_registry' ) ) {
		foreach ( (array) openstation_desktop_widget_registry() as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['id'] ) ) {
				continue;
			}
			$widgets[] = array(
				'id'          => openstation_mcp_workspace_text( $entry['id'] ),
				'label'       => openstation_mcp_workspace_text( $entry['label'] ?? $entry['id'] ),
				'description' => openstation_mcp_workspace_description( $entry['description'] ?? '' ),
				'movable'     => ! empty( $entry['movable'] ),
				'resizable'   => ! empty( $entry['resizable'] ),
			);
		}
	}

	$wallpapers = array();
	if ( function_exists( 'openstation_desktop_wallpaper_registry' ) ) {
		foreach ( (array) openstation_desktop_wallpaper_registry() as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['id'] ) ) {
				continue;
			}
			$wallpapers[] = array(
				'id'          => openstation_mcp_workspace_text( $entry['id'] ),
				'label'       => openstation_mcp_workspace_text( $entry['label'] ?? $entry['id'] ),
				'description' => openstation_mcp_workspace_description( $entry['description'] ?? '' ),
				'type'        => openstation_mcp_workspace_text( $entry['type'] ?? 'canvas' ),
			);
		}
	}

	$commands = array();
	if ( function_exists( 'openstation_desktop_command_registry' ) ) {
		foreach ( (array) openstation_desktop_command_registry() as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['slug'] ) ) {
				continue;
			}
			$commands[] = array(
				'id'          => openstation_mcp_workspace_text( $entry['slug'] ),
				'label'       => openstation_mcp_workspace_text( $entry['label'] ?? $entry['slug'] ),
				'description' => openstation_mcp_workspace_description( $entry['description'] ?? '' ),
				'hint'        => openstation_mcp_workspace_text( $entry['hint'] ?? '' ),
			);
		}
	}

	$themes = array();
	if ( function_exists( 'openstation_build_desktop_themes_payload' ) ) {
		foreach ( openstation_build_desktop_themes_payload() as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['slug'] ) ) {
				continue;
			}
			$themes[] = array(
				'id'          => openstation_mcp_workspace_text( $entry['slug'] ),
				'label'       => openstation_mcp_workspace_text( $entry['name'] ?? $entry['slug'] ),
				'description' => openstation_mcp_workspace_description( $entry['description'] ?? '' ),
				'version'     => openstation_mcp_workspace_text( $entry['version'] ?? '' ),
				'author'      => openstation_mcp_workspace_text( $entry['author'] ?? '' ),
				'source'      => openstation_mcp_workspace_text( $entry['source'] ?? '' ),
			);
		}
	}

	$catalogue = array(
		'windows'    => openstation_mcp_workspace_sort_entries( $windows ),
		'icons'      => openstation_mcp_workspace_sort_entries( $icons ),
		'widgets'    => openstation_mcp_workspace_sort_entries( $widgets ),
		'wallpapers' => openstation_mcp_workspace_sort_entries( $wallpapers ),
		'commands'   => openstation_mcp_workspace_sort_entries( $commands ),
		'themes'     => openstation_mcp_workspace_sort_entries( $themes ),
	);

	$counts = array();
	foreach ( $catalogue as $kind => $entries ) {
		$counts[ $kind ] = count( $entries );
	}
	$catalogue['counts'] = $counts;

	return $catalogue;
}

/**
 * Build the schema for one catalogue list whose rows have a closed shape.
 *
 * @param array $properties JSON Schema properties for one row.
 * @return array
 */
function openstation_mcp_workspace_list_schema( array $properties ) {
	return array(
		'type'  => 'array',
		'items' => array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array_keys( $properties ),
			'properties'           => $properties,
		),
	);
}

/**
 * Closed output schema for the public workspace catalogue ability.
 *
 * @return array
 */
function openstation_mcp_workspace_output_schema() {
	$string      = array( 'type' => 'string' );
	$description = array(
		'type'        => 'string',
		'description' => 'Plain-text description supplied by the server-side registration.',
	);
	$properties  = array(
		'windows'    => openstation_mcp_workspace_list_schema(
			array(
				'id'        => $string,
				'label'     => $string,
				'placement' => $string,
				'kind'      => $string,
				'placeable' => array( 'type' => 'boolean' ),
			)
		),
		'icons'      => openstation_mcp_workspace_list_schema(
			array(
				'id'     => $string,
				'label'  => $string,
				'target' => array(
					'type' => 'string',
					'enum' => array( 'window', 'link' ),
				),
				'pinned' => array( 'type' => 'boolean' ),
			)
		),
		'widgets'    => openstation_mcp_workspace_list_schema(
			array(
				'id'          => $string,
				'label'       => $string,
				'description' => $description,
				'movable'     => array( 'type' => 'boolean' ),
				'resizable'   => array( 'type' => 'boolean' ),
			)
		),
		'wallpapers' => openstation_mcp_workspace_list_schema(
			array(
				'id'          => $string,
				'label'       => $string,
				'description' => $description,
				'type'        => $string,
			)
		),
		'commands'   => openstation_mcp_workspace_list_schema(
			array(
				'id'          => $string,
				'label'       => $string,
				'description' => $description,
				'hint'        => $string,
			)
		),
		'themes'     => openstation_mcp_workspace_list_schema(
			array(
				'id'          => $string,
				'label'       => $string,
				'description' => $description,
				'version'     => $string,
				'author'      => $string,
				'source'      => $string,
			)
		),
		'counts'     => array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'windows', 'icons', 'widgets', 'wallpapers', 'commands', 'themes' ),
			'properties'           => array_fill_keys(
				array( 'windows', 'icons', 'widgets', 'wallpapers', 'commands', 'themes' ),
				array(
					'type'    => 'integer',
					'minimum' => 0,
				)
			),
		),
	);

	return array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array_keys( $properties ),
		'properties'           => $properties,
	);
}

/**
 * Register the MCP-public workspace catalogue.
 *
 * @return void
 */
function openstation_mcp_register_workspace_ability() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	wp_register_ability(
		'desktop-mode/list-workspace-catalog',
		array(
			'label'               => __( 'List OpenStation workspace catalogue', 'desktop-mode' ),
			'description'         => 'Lists the OpenStation windows, desktop icons, widgets, wallpapers, commands, and desktop themes registered for the current user. Returns safe server-side metadata only: it excludes callbacks, script and style handles, URLs, nonces, arbitrary config, theme assets and tokens, window geometry, open-window state, and other browser-local data. Use this to explain what the OpenStation workspace offers, not to claim that an item was opened or changed.',
			'category'            => OPENSTATION_AI_ABILITY_CATEGORY,
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array(),
				'properties'           => (object) array(),
			),
			'output_schema'       => openstation_mcp_workspace_output_schema(),
			'execute_callback'    => static function () {
				return openstation_mcp_workspace_catalog();
			},
			'permission_callback' => 'openstation_ai_ability_can_read',
			'meta'                => array(
				'annotations'  => array(
					'readonly'   => true,
					'idempotent' => true,
				),
				'show_in_rest' => true,
				'mcp'          => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		)
	);
}
add_action( 'wp_abilities_api_init', 'openstation_mcp_register_workspace_ability' );
