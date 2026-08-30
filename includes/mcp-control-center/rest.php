<?php
/**
 * OpenStation — MCP Control Center REST route.
 *
 * @package OpenStation
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the administrator-only snapshot route.
 *
 * @return void
 */
function openstation_mcp_control_center_register_route() {
	register_rest_route(
		'desktop-mode/v1',
		'/mcp-control-center',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'openstation_mcp_control_center_rest_snapshot',
			'permission_callback' => 'openstation_mcp_control_center_user_can_use',
		)
	);
}
add_action( 'rest_api_init', 'openstation_mcp_control_center_register_route' );

/**
 * Return the current adapter and ability exposure audit.
 *
 * @return WP_REST_Response
 */
function openstation_mcp_control_center_rest_snapshot() {
	return rest_ensure_response( openstation_mcp_control_center_snapshot() );
}
