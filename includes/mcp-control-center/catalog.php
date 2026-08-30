<?php
/**
 * OpenStation — MCP Control Center catalogue and exposure audit.
 *
 * @package OpenStation
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the current user may inspect the full abilities registry.
 *
 * Ability schemas can describe privileged internal operations even when the
 * ability is not MCP-public, so the Control Center is administrator-only by
 * default.
 *
 * @return bool
 */
function openstation_mcp_control_center_user_can_use() {
	$can = current_user_can( 'manage_options' );

	/**
	 * Filter whether the current user may use the MCP Control Center.
	 *
	 * @param bool $can Default: current user can manage_options.
	 */
	return (bool) apply_filters( 'openstation_mcp_control_center_user_can_use', $can );
}

/**
 * Resolve an ability's effective MCP exposure.
 *
 * An explicit `meta.mcp.public` value wins. Otherwise the MCP Adapter
 * inherits the high-level `meta.public` flag. Strict booleans match the
 * adapter's fail-closed behavior; strings such as "true" are not public.
 *
 * @param array $meta Ability metadata.
 * @return array{public:bool,source:string}
 */
function openstation_mcp_control_center_resolve_exposure( array $meta ) {
	$mcp = isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) ? $meta['mcp'] : array();
	if ( array_key_exists( 'public', $mcp ) ) {
		if ( true !== $mcp['public'] && false !== $mcp['public'] ) {
			return array(
				'public' => false,
				'source' => 'mcp-invalid',
			);
		}
		return array(
			'public' => true === $mcp['public'],
			'source' => true === $mcp['public'] ? 'mcp' : 'mcp-opt-out',
		);
	}

	if ( isset( $meta['public'] ) && true === $meta['public'] ) {
		return array(
			'public' => true,
			'source' => 'public',
		);
	}

	return array(
		'public' => false,
		'source' => 'private',
	);
}

/**
 * Read a boolean annotation without pretending a missing value is false.
 *
 * @param array  $annotations Annotation map.
 * @param string $key         Annotation key.
 * @return bool|null
 */
function openstation_mcp_control_center_annotation( array $annotations, $key ) {
	if ( ! array_key_exists( $key, $annotations ) || ! is_bool( $annotations[ $key ] ) ) {
		return null;
	}
	return $annotations[ $key ];
}

/**
 * Project one ability into the administrator audit shape.
 *
 * Permission callbacks are deliberately not invoked here. Many need the
 * ability's real input (an object id, target status, etc.), so a zero-input
 * probe would report false denials and could trigger callback side effects.
 *
 * @param WP_Ability $ability Registered WordPress ability.
 * @return array
 */
function openstation_mcp_control_center_shape_ability( WP_Ability $ability ) {
	$meta        = (array) $ability->get_meta();
	$annotations = isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ? $meta['annotations'] : array();
	$exposure    = openstation_mcp_control_center_resolve_exposure( $meta );
	$name        = (string) $ability->get_name();
	$slash       = strpos( $name, '/' );
	$provider    = false === $slash ? $name : substr( $name, 0, $slash );
	$mcp         = isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) ? $meta['mcp'] : array();
	$type_set    = array_key_exists( 'type', $mcp );
	$type_valid  = ! $type_set || ( is_string( $mcp['type'] ) && in_array( $mcp['type'], array( 'tool', 'resource', 'prompt' ), true ) );
	$mcp_type    = $type_set && $type_valid ? $mcp['type'] : 'tool';

	$readonly    = openstation_mcp_control_center_annotation( $annotations, 'readonly' );
	$destructive = openstation_mcp_control_center_annotation( $annotations, 'destructive' );
	if ( true === $destructive ) {
		$risk = 'destructive';
	} elseif ( true === $readonly ) {
		$risk = 'read';
	} elseif ( false === $readonly ) {
		$risk = 'write';
	} else {
		$risk = 'unknown';
	}

	return array(
		'name'          => $name,
		'label'         => (string) $ability->get_label(),
		'description'   => (string) $ability->get_description(),
		'provider'      => (string) $provider,
		'category'      => (string) $ability->get_category(),
		'mcp_public'    => $exposure['public'],
		'exposure'      => $exposure['source'],
		'mcp_type'      => $mcp_type,
		'mcp_type_valid' => $type_valid,
		'risk'          => $risk,
		'annotations'   => array(
			'readonly'    => $readonly,
			'destructive' => $destructive,
			'idempotent'  => openstation_mcp_control_center_annotation( $annotations, 'idempotent' ),
		),
		'input_schema'  => $ability->get_input_schema(),
		'output_schema' => $ability->get_output_schema(),
	);
}

/**
 * Return the full ability audit visible to an administrator.
 *
 * @return array[]
 */
function openstation_mcp_control_center_abilities() {
	if ( ! function_exists( 'wp_get_abilities' ) ) {
		return array();
	}

	$rows = array();
	foreach ( wp_get_abilities() as $ability ) {
		if ( $ability instanceof WP_Ability ) {
			$rows[] = openstation_mcp_control_center_shape_ability( $ability );
		}
	}

	usort(
		$rows,
		static function ( $a, $b ) {
			$provider = strcasecmp( (string) $a['provider'], (string) $b['provider'] );
			return 0 !== $provider ? $provider : strcasecmp( (string) $a['name'], (string) $b['name'] );
		}
	);

	return $rows;
}

/**
 * Count the exposure and risk states shown by the Control Center.
 *
 * @param array[] $abilities Shaped ability rows.
 * @return array{total:int,public:int,private:int,read:int,write:int,destructive:int,unknown:int}
 */
function openstation_mcp_control_center_summary( array $abilities ) {
	$summary = array(
		'total'       => count( $abilities ),
		'public'      => 0,
		'private'     => 0,
		'read'        => 0,
		'write'       => 0,
		'destructive' => 0,
		'unknown'     => 0,
	);

	foreach ( $abilities as $ability ) {
		$summary[ ! empty( $ability['mcp_public'] ) ? 'public' : 'private' ]++;
		$risk = isset( $ability['risk'] ) && isset( $summary[ $ability['risk'] ] ) ? $ability['risk'] : 'unknown';
		$summary[ $risk ]++;
	}

	return $summary;
}

/**
 * Describe the locally installed MCP bridge without making a network call.
 *
 * @return array
 */
function openstation_mcp_control_center_adapter_status() {
	$plugin_file = function_exists( 'openstation_mcp_adapter_plugin_file' ) ? openstation_mcp_adapter_plugin_file() : '';
	$active      = defined( 'WP_MCP_VERSION' ) || class_exists( '\\WP\\MCP\\Core\\McpAdapter' );
	$installed   = $active || '' !== $plugin_file;
	$version     = defined( 'WP_MCP_VERSION' ) ? (string) constant( 'WP_MCP_VERSION' ) : '';
	if ( '' === $version && '' !== $plugin_file ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		$version = isset( $plugins[ $plugin_file ]['Version'] ) ? (string) $plugins[ $plugin_file ]['Version'] : '';
	}
	// The filter belongs to the official WordPress MCP Adapter plugin.
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	$default_server = $active && (bool) apply_filters( 'mcp_adapter_create_default_server', true );

	return array(
		'installed'      => $installed,
		'active'         => $active,
		'version'        => $version,
		'default_server' => $default_server,
		'endpoint'       => $active && $default_server ? esc_url_raw( rest_url( 'mcp/mcp-adapter-default-server' ) ) : '',
		'abilities_api'  => function_exists( 'wp_get_abilities' ),
		'requirements_met' => function_exists( 'openstation_mcp_adapter_requirements_met' ) ? openstation_mcp_adapter_requirements_met() : false,
		'can_install'    => current_user_can( 'install_plugins' ) && current_user_can( 'activate_plugins' ),
		'can_activate'   => current_user_can( 'activate_plugins' ),
		'project_url'    => 'https://github.com/WordPress/mcp-adapter',
	);
}

/**
 * Complete REST payload for the MCP Control Center window.
 *
 * @return array
 */
function openstation_mcp_control_center_snapshot() {
	$abilities = openstation_mcp_control_center_abilities();

	return array(
		'adapter'   => openstation_mcp_control_center_adapter_status(),
		'setup'     => array(
			'editorial_pack_enabled'       => openstation_mcp_editorial_pack_enabled(),
			'application_passwords_available' => function_exists( 'wp_is_application_passwords_available_for_user' ) && wp_is_application_passwords_available_for_user( wp_get_current_user() ),
			'application_passwords_url'   => esc_url_raw( get_edit_profile_url( get_current_user_id() ) . '#application-passwords-section' ),
			'users_url'                   => esc_url_raw( admin_url( 'users.php' ) ),
			'https'                       => function_exists( 'wp_is_using_https' ) ? wp_is_using_https() : is_ssl(),
		),
		'summary'   => openstation_mcp_control_center_summary( $abilities ),
		'abilities' => $abilities,
	);
}
