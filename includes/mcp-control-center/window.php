<?php
/**
 * OpenStation — MCP Control Center native-window registration.
 *
 * @package OpenStation
 */

defined( 'ABSPATH' ) || exit;

/**
 * Network/capability mark shared by the window and desktop icon.
 *
 * @return string Raw SVG markup.
 */
function openstation_mcp_control_center_icon_svg() {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
		. '<path d="M16 18 32 32 49 17M32 32 49 47M32 32 15 48" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>'
		. '<circle cx="32" cy="32" r="8" fill="currentColor"/>'
		. '<circle cx="16" cy="18" r="5" fill="currentColor"/>'
		. '<circle cx="49" cy="17" r="5" fill="currentColor"/>'
		. '<circle cx="49" cy="47" r="5" fill="currentColor"/>'
		. '<circle cx="15" cy="48" r="5" fill="currentColor"/>'
		. '</svg>';
}

/**
 * Render the mount point cloned into each Control Center window.
 *
 * @return void
 */
function openstation_mcp_control_center_render_template() {
	ob_start();
	?>
	<div class="openstation-mcp-control-center" data-os-mcp-control-center-root>
		<div class="os-mcp-control-center__loading" role="status">
			<os-spinner></os-spinner>
			<span><?php esc_html_e( 'Auditing WordPress abilities…', 'desktop-mode' ); ?></span>
		</div>
	</div>
	<?php
	$html = (string) ob_get_clean();
	if ( function_exists( 'openstation_kses_native_window_template' ) ) {
		echo openstation_kses_native_window_template( $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper kses-escapes.
		return;
	}

	echo wp_kses( $html, wp_kses_allowed_html( 'post' ) );
}

/**
 * Register the administrator-only native window and desktop shortcut.
 *
 * @return void
 */
function openstation_mcp_control_center_register_window() {
	if ( ! openstation_mcp_control_center_user_can_use() ) {
		return;
	}

	$icon_uri   = 'data:image/svg+xml;base64,' . base64_encode( openstation_mcp_control_center_icon_svg() );
	$window_args = array(
		'title'        => __( 'MCP Control Center', 'desktop-mode' ),
		'icon'         => $icon_uri,
		'template'     => 'openstation_mcp_control_center_render_template',
		'script'       => 'openstation-mcp-control-center',
		'styles'       => array( 'openstation-mcp-control-center' ),
		'width'        => 1120,
		'height'       => 740,
		'min_width'    => 720,
		'min_height'   => 480,
		'placement'    => 'none',
		'capabilities' => array( 'manage_options' ),
		'config'       => array(
			'apiBase'    => esc_url_raw( rest_url( 'desktop-mode/v1/mcp-control-center' ) ),
			'restNonce'  => wp_create_nonce( 'wp_rest' ),
			'ajaxUrl'    => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'setupNonce' => wp_create_nonce( 'openstation_mcp_setup' ),
		),
	);

	/**
	 * Filter the MCP Control Center native-window registration args.
	 *
	 * @param array $window_args Args passed to openstation_register_window().
	 */
	$window_args = (array) apply_filters( 'openstation_mcp_control_center_window_args', $window_args );

	$registered = openstation_register_window( 'openstation-mcp-control-center', $window_args );
	if ( is_wp_error( $registered ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[openstation] MCP Control Center window registration failed: ' . $registered->get_error_message() );
		return;
	}

	$icon_args = array(
		'title'    => __( 'MCP Control Center', 'desktop-mode' ),
		'icon_svg' => openstation_mcp_control_center_icon_svg(),
		'window'   => 'openstation-mcp-control-center',
		'pinned'   => false,
		'position' => 26,
	);

	/**
	 * Filter the MCP Control Center desktop-icon registration args.
	 *
	 * @param array $icon_args Args passed to openstation_register_icon().
	 */
	$icon_args = (array) apply_filters( 'openstation_mcp_control_center_icon_args', $icon_args );

	openstation_register_icon( 'openstation-mcp-control-center', $icon_args );
}
add_action( 'init', 'openstation_mcp_control_center_register_window', 20 );
