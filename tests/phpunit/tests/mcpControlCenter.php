<?php
/**
 * Tests for the MCP Control Center and workspace catalogue ability.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group openstation
 * @group os-mcp
 */
class Tests_OpenStation_McpControlCenter extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available (requires WordPress 6.9+).' );
		}
	}

	public function tear_down() {
		delete_option( OPENSTATION_MCP_EDITORIAL_PACK_OPTION );
		parent::tear_down();
	}

	/**
	 * Explicit MCP metadata wins over the inherited public flag and all
	 * values are interpreted strictly.
	 *
	 * @covers ::openstation_mcp_control_center_resolve_exposure
	 */
	public function test_mcp_exposure_resolution_is_explicit_and_fail_closed() {
		$this->assertSame(
			array( 'public' => true, 'source' => 'mcp' ),
			openstation_mcp_control_center_resolve_exposure(
				array( 'mcp' => array( 'public' => true ) )
			)
		);
		$this->assertSame(
			array( 'public' => false, 'source' => 'mcp-opt-out' ),
			openstation_mcp_control_center_resolve_exposure(
				array( 'public' => true, 'mcp' => array( 'public' => false ) )
			)
		);
		$this->assertSame(
			array( 'public' => false, 'source' => 'mcp-invalid' ),
			openstation_mcp_control_center_resolve_exposure(
				array( 'mcp' => array( 'public' => 'true' ) )
			)
		);
		$this->assertSame(
			array( 'public' => true, 'source' => 'public' ),
			openstation_mcp_control_center_resolve_exposure( array( 'public' => true ) )
		);
		$this->assertNull(
			openstation_mcp_control_center_annotation( array( 'readonly' => null ), 'readonly' )
		);
		$this->assertNull(
			openstation_mcp_control_center_annotation( array( 'readonly' => 'true' ), 'readonly' )
		);
		$this->assertFalse(
			openstation_mcp_control_center_annotation( array( 'readonly' => false ), 'readonly' )
		);
	}

	/**
	 * @covers ::openstation_mcp_register_workspace_ability
	 */
	public function test_workspace_catalogue_ability_is_public_readonly_and_executes() {
		$ability = wp_get_ability( 'desktop-mode/list-workspace-catalog' );
		$this->assertInstanceOf( 'WP_Ability', $ability );

		$meta = (array) $ability->get_meta();
		$this->assertTrue( $meta['mcp']['public'] );
		$this->assertTrue( $meta['annotations']['readonly'] );
		$this->assertTrue( $meta['annotations']['idempotent'] );
		$schema = $ability->get_output_schema();
		$this->assertFalse( $schema['additionalProperties'] );
		$this->assertArrayHasKey( 'items', $schema['properties']['windows'] );
		$this->assertFalse( $schema['properties']['windows']['items']['additionalProperties'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$result = $ability->execute( array() );
		$this->assertNotWPError( $result );
		$this->assertSame(
			array( 'windows', 'icons', 'widgets', 'wallpapers', 'commands', 'themes' ),
			array_keys( $result['counts'] )
		);
	}

	/**
	 * The public catalogue is a projection, never a serialized registry.
	 *
	 * @covers ::openstation_mcp_workspace_catalog
	 */
	public function test_workspace_catalogue_omits_executable_and_session_data() {
		$result = openstation_mcp_workspace_catalog();
		$keys   = $this->collect_catalogue_keys( $result );

		$this->assertSame(
			array(),
			array_values(
				array_intersect(
					array( 'config', 'script', 'scripts', 'style', 'styles', 'url', 'nonce', 'tokens', 'assets' ),
					$keys
				)
			)
		);
	}

	/**
	 * @covers ::openstation_mcp_control_center_user_can_use
	 */
	public function test_control_center_is_administrator_only_by_default() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertFalse( openstation_mcp_control_center_user_can_use() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( openstation_mcp_control_center_user_can_use() );
	}

	/**
	 * @covers ::openstation_mcp_control_center_shape_ability
	 */
	public function test_audit_shape_does_not_hide_private_abilities() {
		$ability = wp_get_ability( 'desktop-mode/get-php-error-log' );
		$row     = openstation_mcp_control_center_shape_ability( $ability );

		$this->assertSame( 'desktop-mode', $row['provider'] );
		$this->assertFalse( $row['mcp_public'] );
		$this->assertSame( 'private', $row['exposure'] );
		$this->assertSame( 'read', $row['risk'] );
	}

	/**
	 * @covers ::openstation_mcp_register_editorial_abilities
	 * @covers ::openstation_mcp_editorial_meta
	 */
	public function test_editorial_pack_is_registered_but_mcp_private_by_default() {
		$names = array(
			'desktop-mode/list-editorial-content',
			'desktop-mode/get-content-item',
			'desktop-mode/create-draft',
			'desktop-mode/update-draft',
			'desktop-mode/get-media-item',
			'desktop-mode/update-media-metadata',
			'desktop-mode/get-comment',
			'desktop-mode/moderate-comment',
		);

		$this->assertFalse( openstation_mcp_editorial_pack_enabled() );
		foreach ( $names as $name ) {
			$ability = wp_get_ability( $name );
			$this->assertInstanceOf( 'WP_Ability', $ability, $name );
			$meta = (array) $ability->get_meta();
			$this->assertFalse( $meta['mcp']['public'], $name );
			$this->assertFalse( $meta['annotations']['destructive'], $name );
			$this->assertFalse( $ability->get_input_schema()['additionalProperties'], $name );
			$this->assertFalse( $ability->get_output_schema()['additionalProperties'], $name );
		}

		update_option( OPENSTATION_MCP_EDITORIAL_PACK_OPTION, true, false );
		$this->assertTrue( openstation_mcp_editorial_pack_enabled() );
		$this->assertTrue( openstation_mcp_editorial_meta( true, true )['mcp']['public'] );
	}

	/**
	 * @covers ::openstation_mcp_editorial_create_draft
	 * @covers ::openstation_mcp_editorial_update_draft
	 */
	public function test_editorial_pack_creates_and_updates_drafts_but_cannot_publish() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$create = wp_get_ability( 'desktop-mode/create-draft' );
		$result = $create->execute(
			array(
				'title'   => 'MCP draft',
				'content' => '<!-- wp:paragraph --><p>Raw blocks survive.</p><!-- /wp:paragraph -->',
				'type'    => 'post',
			)
		);
		$this->assertNotWPError( $result );
		$this->assertSame( 'draft', $result['status'] );
		$this->assertStringContainsString( '<!-- wp:paragraph -->', $result['content'] );

		$update = wp_get_ability( 'desktop-mode/update-draft' );
		$edited = $update->execute(
			array(
				'post_id' => $result['id'],
				'title'   => 'Edited MCP draft',
			)
		);
		$this->assertNotWPError( $edited );
		$this->assertSame( 'draft', $edited['status'] );
		$this->assertSame( 'Edited MCP draft', $edited['title'] );
		$this->assertArrayNotHasKey( 'status', $update->get_input_schema()['properties'] );

		wp_update_post(
			array(
				'ID'          => $result['id'],
				'post_status' => 'publish',
			)
		);
		$refused = $update->execute(
			array(
				'post_id' => $result['id'],
				'title'   => 'Should not change',
			)
		);
		$this->assertWPError( $refused );
		$this->assertSame( 'openstation_mcp_not_a_draft', $refused->get_error_code() );
		$this->assertSame( 'Edited MCP draft', get_post( $result['id'] )->post_title );
	}

	/**
	 * @covers ::openstation_mcp_editorial_can_read_content
	 * @covers ::openstation_mcp_editorial_can_create_draft
	 * @covers ::openstation_mcp_editorial_can_moderate_comments
	 */
	public function test_editorial_pack_keeps_wordpress_permissions_as_the_hard_boundary() {
		$draft_id   = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$comment_id = self::factory()->comment->create(
			array( 'comment_post_ID' => $draft_id )
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertWPError(
			wp_get_ability( 'desktop-mode/get-content-item' )->execute(
				array( 'post_id' => $draft_id )
			)
		);
		$this->assertWPError(
			wp_get_ability( 'desktop-mode/create-draft' )->execute(
				array(
					'title'   => 'Denied',
					'content' => 'Denied',
				)
			)
		);
		$this->assertWPError(
			wp_get_ability( 'desktop-mode/moderate-comment' )->execute(
				array(
					'comment_id' => $comment_id,
					'status'     => 'approve',
				)
			)
		);
	}

	/**
	 * @covers ::openstation_mcp_editorial_get_comment
	 * @covers ::openstation_mcp_editorial_moderate_comment
	 */
	public function test_comment_abilities_omit_contact_data_and_never_delete() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => 'Reader',
				'comment_author_email' => 'reader@example.test',
				'comment_author_IP'    => '192.0.2.10',
				'comment_approved'     => '0',
			)
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$get    = wp_get_ability( 'desktop-mode/get-comment' );
		$result = $get->execute( array( 'comment_id' => $comment_id ) );
		$this->assertNotWPError( $result );
		$this->assertSame( 'Reader', $result['author_name'] );
		$this->assertArrayNotHasKey( 'email', $result );
		$this->assertArrayNotHasKey( 'ip', $result );

		$moderate = wp_get_ability( 'desktop-mode/moderate-comment' );
		$updated  = $moderate->execute(
			array(
				'comment_id' => $comment_id,
				'status'     => 'spam',
			)
		);
		$this->assertNotWPError( $updated );
		$this->assertSame( 'spam', $updated['status'] );
		$this->assertSame( array( 'approve', 'hold', 'spam' ), $moderate->get_input_schema()['properties']['status']['enum'] );
	}

	/**
	 * @covers ::openstation_mcp_adapter_package_url
	 */
	public function test_adapter_setup_uses_only_the_official_release_channel() {
		$this->assertSame(
			'https://github.com/WordPress/mcp-adapter/releases/latest/download/mcp-adapter.zip',
			openstation_mcp_adapter_package_url()
		);
	}

	/**
	 * Collect every associative key in a nested catalogue response.
	 *
	 * @param mixed $value Response fragment.
	 * @return string[]
	 */
	private function collect_catalogue_keys( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$keys = array();
		foreach ( $value as $key => $child ) {
			if ( is_string( $key ) ) {
				$keys[] = strtolower( $key );
			}
			$keys = array_merge( $keys, $this->collect_catalogue_keys( $child ) );
		}
		return array_values( array_unique( $keys ) );
	}
}
