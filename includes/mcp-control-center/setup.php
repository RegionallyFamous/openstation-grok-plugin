<?php
/**
 * OpenStation — deliberate setup actions for WordPress MCP.
 *
 * Plugin installation is an administrator-initiated admin-ajax action because
 * WordPress's upgrader and filesystem credential flow are admin facilities.
 * The package URL is pinned to the official WordPress GitHub release channel
 * and is intentionally not filterable into an arbitrary remote download.
 *
 * @package OpenStation
 */

defined( 'ABSPATH' ) || exit;

/**
 * Official release package used by the MCP Adapter installation guide.
 *
 * @return string
 */
function openstation_mcp_adapter_package_url() {
	return 'https://github.com/WordPress/mcp-adapter/releases/latest/download/mcp-adapter.zip';
}

/**
 * Find the official adapter's relative plugin file when installed.
 *
 * @return string Relative path such as mcp-adapter/mcp-adapter.php.
 */
function openstation_mcp_adapter_plugin_file() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$plugins = get_plugins();
	if ( isset( $plugins['mcp-adapter/mcp-adapter.php'] ) ) {
		return 'mcp-adapter/mcp-adapter.php';
	}

	// Accommodate an administrator-renamed directory while still requiring
	// the official main filename, package name, and project URI.
	foreach ( $plugins as $plugin_file => $headers ) {
		if ( 'mcp-adapter.php' !== basename( (string) $plugin_file ) ) {
			continue;
		}
		if ( 'MCP Adapter' !== (string) ( $headers['Name'] ?? '' ) ) {
			continue;
		}
		if ( 'https://github.com/WordPress/mcp-adapter' !== untrailingslashit( (string) ( $headers['PluginURI'] ?? '' ) ) ) {
			continue;
		}
		return (string) $plugin_file;
	}

	return '';
}

/**
 * Whether the current runtime meets the adapter's published requirements.
 *
 * @return bool
 */
function openstation_mcp_adapter_requirements_met() {
	global $wp_version;
	return version_compare( (string) $wp_version, '6.9', '>=' ) && version_compare( PHP_VERSION, '7.4', '>=' );
}

/**
 * Activate an installed adapter plugin.
 *
 * @return true|WP_Error
 */
function openstation_mcp_adapter_activate() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return new WP_Error(
			'openstation_mcp_cannot_activate',
			__( 'You do not have permission to activate plugins.', 'desktop-mode' ),
			array( 'status' => 403 )
		);
	}
	$plugin_file = openstation_mcp_adapter_plugin_file();
	if ( '' === $plugin_file ) {
		return new WP_Error(
			'openstation_mcp_adapter_missing',
			__( 'The official MCP Adapter is not installed.', 'desktop-mode' ),
			array( 'status' => 404 )
		);
	}
	if ( ! function_exists( 'activate_plugin' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$result = activate_plugin( $plugin_file, '', false, true );
	if ( is_wp_error( $result ) ) {
		$result->add_data( array( 'status' => 500 ) );
		return $result;
	}
	return true;
}

/**
 * Install the latest official MCP Adapter release, then activate it.
 *
 * @return true|WP_Error
 */
function openstation_mcp_adapter_install() {
	if ( ! current_user_can( 'install_plugins' ) ) {
		return new WP_Error(
			'openstation_mcp_cannot_install',
			__( 'You do not have permission to install plugins.', 'desktop-mode' ),
			array( 'status' => 403 )
		);
	}
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return new WP_Error(
			'openstation_mcp_cannot_activate',
			__( 'You do not have permission to activate plugins.', 'desktop-mode' ),
			array( 'status' => 403 )
		);
	}
	if ( ! openstation_mcp_adapter_requirements_met() ) {
		return new WP_Error(
			'openstation_mcp_adapter_requirements',
			__( 'MCP Adapter requires WordPress 6.9 or newer and PHP 7.4 or newer.', 'desktop-mode' ),
			array( 'status' => 409 )
		);
	}
	if ( '' !== openstation_mcp_adapter_plugin_file() ) {
		return openstation_mcp_adapter_activate();
	}

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if ( ! class_exists( 'WP_Upgrader' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	}
	if ( ! class_exists( 'Plugin_Upgrader' ) || ! class_exists( 'Automatic_Upgrader_Skin' ) ) {
		return new WP_Error(
			'openstation_mcp_upgrader_unavailable',
			__( 'The WordPress plugin upgrader is unavailable.', 'desktop-mode' ),
			array( 'status' => 503 )
		);
	}

	$skin     = new Automatic_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader( $skin );
	$result   = $upgrader->install( openstation_mcp_adapter_package_url() );

	if ( is_wp_error( $skin->result ) ) {
		$skin->result->add_data( array( 'status' => 500 ) );
		return $skin->result;
	}
	if ( $skin->get_errors()->has_errors() ) {
		$errors = $skin->get_errors();
		$errors->add_data( array( 'status' => 500 ) );
		return $errors;
	}
	if ( is_wp_error( $result ) ) {
		$result->add_data( array( 'status' => 500 ) );
		return $result;
	}
	if ( false === $result || null === $result ) {
		return new WP_Error(
			'openstation_mcp_install_failed',
			__( 'MCP Adapter could not be installed. Check filesystem access and outbound HTTPS.', 'desktop-mode' ),
			array( 'status' => 500 )
		);
	}

	$plugin_file = openstation_mcp_adapter_plugin_file();
	if ( '' === $plugin_file ) {
		return new WP_Error(
			'openstation_mcp_installed_file_missing',
			__( 'The package installed, but WordPress could not identify the official MCP Adapter plugin file.', 'desktop-mode' ),
			array( 'status' => 500 )
		);
	}

	return openstation_mcp_adapter_activate();
}

/**
 * Normalize an action result into a JSON error response.
 *
 * @param WP_Error $error Setup error.
 * @return void
 */
function openstation_mcp_setup_send_error( WP_Error $error ) {
	$data   = $error->get_error_data();
	$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500;
	wp_send_json_error(
		array(
			'code'    => (string) $error->get_error_code(),
			'message' => (string) $error->get_error_message(),
		),
		$status
	);
}

/**
 * Handle the Control Center's explicit setup actions.
 *
 * @return void
 */
function openstation_mcp_setup_ajax() {
	if ( false === check_ajax_referer( 'openstation_mcp_setup', 'nonce', false ) ) {
		openstation_mcp_setup_send_error(
			new WP_Error(
				'openstation_mcp_invalid_nonce',
				__( 'The setup request expired. Reload OpenStation and try again.', 'desktop-mode' ),
				array( 'status' => 403 )
			)
		);
	}
	if ( ! openstation_mcp_control_center_user_can_use() ) {
		openstation_mcp_setup_send_error(
			new WP_Error(
				'openstation_mcp_forbidden',
				__( 'You do not have permission to manage WordPress MCP setup.', 'desktop-mode' ),
				array( 'status' => 403 )
			)
		);
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
	$setup_action = isset( $_POST['setupAction'] ) ? sanitize_key( wp_unslash( $_POST['setupAction'] ) ) : '';
	$result       = true;
	$message      = '';

	switch ( $setup_action ) {
		case 'install_adapter':
			$result  = openstation_mcp_adapter_install();
			$message = __( 'The official WordPress MCP Adapter is installed and active.', 'desktop-mode' );
			break;
		case 'activate_adapter':
			$result  = openstation_mcp_adapter_activate();
			$message = __( 'The WordPress MCP Adapter is active.', 'desktop-mode' );
			break;
		case 'enable_editorial_pack':
			update_option( OPENSTATION_MCP_EDITORIAL_PACK_OPTION, true, false );
			$message = __( 'The OpenStation editorial ability pack is now MCP-public.', 'desktop-mode' );
			break;
		case 'disable_editorial_pack':
			delete_option( OPENSTATION_MCP_EDITORIAL_PACK_OPTION );
			$message = __( 'The OpenStation editorial ability pack is private again.', 'desktop-mode' );
			break;
		default:
			$result = new WP_Error(
				'openstation_mcp_unknown_setup_action',
				__( 'Unknown MCP setup action.', 'desktop-mode' ),
				array( 'status' => 400 )
			);
	}

	if ( is_wp_error( $result ) ) {
		openstation_mcp_setup_send_error( $result );
	}

	wp_send_json_success(
		array(
			'message' => $message,
		)
	);
}
add_action( 'wp_ajax_openstation_mcp_setup', 'openstation_mcp_setup_ajax' );
