<?php
/**
 * OpenStation — MCP Control Center assets.
 *
 * @package OpenStation
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the lazy native-window bundle and stylesheet.
 *
 * @return void
 */
function openstation_mcp_control_center_register_assets() {
	$version = OPENSTATION_VERSION;
	$suffix  = openstation_asset_suffix();

	$css_path = OPENSTATION_DIR . 'assets/css/mcp-control-center.css';
	wp_register_style(
		'openstation-mcp-control-center',
		OPENSTATION_URL . 'assets/css/mcp-control-center.css',
		array( 'os-variables', 'dashicons' ),
		file_exists( $css_path ) ? (string) filemtime( $css_path ) : $version
	);

	$js_path = OPENSTATION_DIR . 'assets/js/mcp-control-center' . $suffix . '.js';
	wp_register_script(
		'openstation-mcp-control-center',
		OPENSTATION_URL . 'assets/js/mcp-control-center' . $suffix . '.js',
		array( 'wp-i18n' ),
		file_exists( $js_path ) ? (string) filemtime( $js_path ) : $version,
		true
	);
	wp_set_script_translations(
		'openstation-mcp-control-center',
		'desktop-mode',
		OPENSTATION_DIR . 'languages'
	);
}
add_action( 'init', 'openstation_mcp_control_center_register_assets', 5 );
