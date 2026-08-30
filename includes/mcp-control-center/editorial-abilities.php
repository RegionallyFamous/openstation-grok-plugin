<?php
/**
 * OpenStation — opt-in editorial abilities for the official MCP Adapter.
 *
 * The pack deliberately stops short of publication, deletion, user access,
 * settings, plugin/theme operations, file writes, and arbitrary execution.
 * Every ability still evaluates WordPress capabilities for the authenticated
 * Application Password user when it executes.
 *
 * @package OpenStation
 */

defined( 'ABSPATH' ) || exit;

/** Site option controlling MCP exposure for the editorial pack. */
const OPENSTATION_MCP_EDITORIAL_PACK_OPTION = 'openstation_mcp_editorial_pack_enabled';

/**
 * Whether the curated editorial ability pack is exposed over MCP.
 *
 * Abilities remain registered while disabled so administrators can inspect
 * their schemas in the Control Center before opting in.
 *
 * @return bool
 */
function openstation_mcp_editorial_pack_enabled() {
	$enabled = (bool) get_option( OPENSTATION_MCP_EDITORIAL_PACK_OPTION, false );

	/**
	 * Filter whether OpenStation's curated editorial abilities are MCP-public.
	 *
	 * @param bool $enabled Stored pack state. Default false.
	 */
	return (bool) apply_filters( 'openstation_mcp_editorial_pack_enabled', $enabled );
}

/**
 * Build a closed object schema.
 *
 * @param array $properties JSON Schema property map.
 * @param array $required   Required property names.
 * @return array
 */
function openstation_mcp_editorial_object_schema( array $properties, array $required = array() ) {
	$schema = array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'properties'           => $properties,
	);
	if ( ! empty( $required ) ) {
		$schema['required'] = $required;
	}
	return $schema;
}

/**
 * Shared metadata for one editorial ability.
 *
 * @param bool $readonly   Whether the ability has no side effects.
 * @param bool $idempotent Whether repeating the call has the same effect.
 * @return array
 */
function openstation_mcp_editorial_meta( $readonly, $idempotent ) {
	return array(
		'annotations'  => array(
			'readonly'    => (bool) $readonly,
			'destructive' => false,
			'idempotent'  => (bool) $idempotent,
		),
		'show_in_rest' => true,
		'mcp'          => array(
			'public' => openstation_mcp_editorial_pack_enabled(),
			'type'   => 'tool',
		),
	);
}

/**
 * Common exact output shape for a post or page.
 *
 * @return array
 */
function openstation_mcp_editorial_content_output_schema() {
	return openstation_mcp_editorial_object_schema(
		array(
			'id'           => array( 'type' => 'integer' ),
			'type'         => array( 'type' => 'string' ),
			'status'       => array( 'type' => 'string' ),
			'title'        => array( 'type' => 'string' ),
			'content'      => array( 'type' => 'string' ),
			'excerpt'      => array( 'type' => 'string' ),
			'author'       => array( 'type' => 'integer' ),
			'date_gmt'     => array( 'type' => 'string' ),
			'modified_gmt' => array( 'type' => 'string' ),
			'url'          => array( 'type' => 'string' ),
		),
		array( 'id', 'type', 'status', 'title', 'content', 'excerpt', 'author', 'date_gmt', 'modified_gmt', 'url' )
	);
}

/**
 * Project a post into the editorial response shape.
 *
 * Raw stored block content is intentional: an editor must retain Gutenberg
 * delimiter comments instead of flattening rendered HTML back into a post.
 *
 * @param WP_Post $post Post object.
 * @return array
 */
function openstation_mcp_editorial_shape_content( WP_Post $post ) {
	return array(
		'id'           => (int) $post->ID,
		'type'         => (string) $post->post_type,
		'status'       => (string) $post->post_status,
		'title'        => (string) $post->post_title,
		'content'      => (string) $post->post_content,
		'excerpt'      => (string) $post->post_excerpt,
		'author'       => (int) $post->post_author,
		'date_gmt'     => (string) $post->post_date_gmt,
		'modified_gmt' => (string) $post->post_modified_gmt,
		'url'          => (string) get_permalink( $post ),
	);
}

/**
 * Register the opt-in editorial ability pack.
 *
 * @return void
 */
function openstation_mcp_register_editorial_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	$content_id_input = openstation_mcp_editorial_object_schema(
		array(
			'post_id' => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => 'Numeric post or page id.',
			),
		),
		array( 'post_id' )
	);

	wp_register_ability(
		'desktop-mode/list-editorial-content',
		array(
			'label'               => __( 'List editorial content', 'desktop-mode' ),
			'description'         => 'List posts or pages the authenticated WordPress user may read, including drafts and pending review. Defaults to drafts. Results contain identity and status but not full content; use get-content-item for one selected id.',
			'category'            => OPENSTATION_AI_ABILITY_CATEGORY,
			'input_schema'        => openstation_mcp_editorial_object_schema(
				array(
					'type'     => array(
						'type'        => 'string',
						'enum'        => array( 'post', 'page', 'any' ),
						'description' => 'Content type. Defaults to any.',
					),
					'status'   => array(
						'type'        => 'string',
						'enum'        => array( 'draft', 'pending', 'private', 'publish' ),
						'description' => 'WordPress post status. Defaults to draft.',
					),
					'search'   => array(
						'type'        => 'string',
						'description' => 'Optional WordPress-native title/content search terms.',
					),
					'page'     => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'One-based page. Defaults to 1.',
					),
					'per_page' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 25,
						'description' => 'Items per page. Defaults to 10 and never exceeds 25.',
					),
				)
			),
			'output_schema'       => openstation_mcp_editorial_object_schema(
				array(
					'items' => array(
						'type'  => 'array',
						'items' => openstation_mcp_editorial_object_schema(
							array(
								'id'           => array( 'type' => 'integer' ),
								'type'         => array( 'type' => 'string' ),
								'status'       => array( 'type' => 'string' ),
								'title'        => array( 'type' => 'string' ),
								'excerpt'      => array( 'type' => 'string' ),
								'author'       => array( 'type' => 'integer' ),
								'modified_gmt' => array( 'type' => 'string' ),
								'url'          => array( 'type' => 'string' ),
							),
							array( 'id', 'type', 'status', 'title', 'excerpt', 'author', 'modified_gmt', 'url' )
						),
					),
					'count' => array( 'type' => 'integer' ),
				),
				array( 'items', 'count' )
			),
			'execute_callback'    => 'openstation_mcp_editorial_list_content',
			'permission_callback' => 'openstation_mcp_editorial_can_list_content',
			'meta'                => openstation_mcp_editorial_meta( true, true ),
		)
	);

	wp_register_ability(
		'desktop-mode/get-content-item',
		array(
			'label'               => __( 'Get content item', 'desktop-mode' ),
			'description'         => 'Return one post or page by numeric id. Content is the raw stored Gutenberg markup with block delimiter comments intact, never rendered HTML. WordPress checks whether the authenticated user may read the item.',
			'category'            => OPENSTATION_AI_ABILITY_CATEGORY,
			'input_schema'        => $content_id_input,
			'output_schema'       => openstation_mcp_editorial_content_output_schema(),
			'execute_callback'    => 'openstation_mcp_editorial_get_content',
			'permission_callback' => 'openstation_mcp_editorial_can_read_content',
			'meta'                => openstation_mcp_editorial_meta( true, true ),
		)
	);

	wp_register_ability(
		'desktop-mode/create-draft',
		array(
			'label'               => __( 'Create draft', 'desktop-mode' ),
			'description'         => 'Create a new post or page as a draft owned by the authenticated WordPress user. This ability always forces draft status and cannot publish.',
			'category'            => OPENSTATION_AI_ABILITY_CATEGORY,
			'input_schema'        => openstation_mcp_editorial_object_schema(
				array(
					'title'   => array( 'type' => 'string' ),
					'content' => array(
						'type'        => 'string',
						'description' => 'Raw HTML or Gutenberg block markup.',
					),
					'excerpt' => array( 'type' => 'string' ),
					'type'    => array(
						'type' => 'string',
						'enum' => array( 'post', 'page' ),
					),
				),
				array( 'title', 'content' )
			),
			'output_schema'       => openstation_mcp_editorial_content_output_schema(),
			'execute_callback'    => 'openstation_mcp_editorial_create_draft',
			'permission_callback' => 'openstation_mcp_editorial_can_create_draft',
			'meta'                => openstation_mcp_editorial_meta( false, false ),
		)
	);

	wp_register_ability(
		'desktop-mode/update-draft',
		array(
			'label'               => __( 'Update draft', 'desktop-mode' ),
			'description'         => 'Update title, raw content, or excerpt on an existing draft post or page. The target must already be a draft and remains a draft; this ability cannot change status or publish.',
			'category'            => OPENSTATION_AI_ABILITY_CATEGORY,
			'input_schema'        => openstation_mcp_editorial_object_schema(
				array(
					'post_id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'title'   => array( 'type' => 'string' ),
					'content' => array(
						'type'        => 'string',
						'description' => 'Raw HTML or Gutenberg block markup.',
					),
					'excerpt' => array( 'type' => 'string' ),
				),
				array( 'post_id' )
			),
			'output_schema'       => openstation_mcp_editorial_content_output_schema(),
			'execute_callback'    => 'openstation_mcp_editorial_update_draft',
			'permission_callback' => 'openstation_mcp_editorial_can_update_draft',
			'meta'                => openstation_mcp_editorial_meta( false, true ),
		)
	);

	wp_register_ability(
		'desktop-mode/get-media-item',
		array(
			'label'               => __( 'Get media item', 'desktop-mode' ),
			'description'         => 'Return safe Media Library details for one attachment: file URL, MIME type, dimensions, alt text, caption, description, and parent post id. Requires the authenticated user to be able to upload files.',
			'category'            => OPENSTATION_AI_ABILITY_CATEGORY,
			'input_schema'        => openstation_mcp_editorial_object_schema(
				array(
					'attachment_id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				array( 'attachment_id' )
			),
			'output_schema'       => openstation_mcp_editorial_object_schema(
				array(
					'id'          => array( 'type' => 'integer' ),
					'title'       => array( 'type' => 'string' ),
					'url'         => array( 'type' => 'string' ),
					'mime'        => array( 'type' => 'string' ),
					'width'       => array( 'type' => 'integer' ),
					'height'      => array( 'type' => 'integer' ),
					'alt_text'    => array( 'type' => 'string' ),
					'caption'     => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
					'parent'      => array( 'type' => 'integer' ),
					'date_gmt'    => array( 'type' => 'string' ),
				),
				array( 'id', 'title', 'url', 'mime', 'width', 'height', 'alt_text', 'caption', 'description', 'parent', 'date_gmt' )
			),
			'execute_callback'    => 'openstation_mcp_editorial_get_media',
			'permission_callback' => 'openstation_mcp_editorial_can_read_media',
			'meta'                => openstation_mcp_editorial_meta( true, true ),
		)
	);

	wp_register_ability(
		'desktop-mode/update-media-metadata',
		array(
			'label'               => __( 'Update media metadata', 'desktop-mode' ),
			'description'         => 'Update alt text, title, caption, and/or description for one Media Library item. The uploaded file is never replaced or deleted.',
			'category'            => OPENSTATION_AI_ABILITY_CATEGORY,
			'input_schema'        => openstation_mcp_editorial_object_schema(
				array(
					'attachment_id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'alt_text'      => array( 'type' => 'string' ),
					'title'         => array( 'type' => 'string' ),
					'caption'       => array( 'type' => 'string' ),
					'description'   => array( 'type' => 'string' ),
				),
				array( 'attachment_id' )
			),
			'output_schema'       => openstation_mcp_editorial_object_schema(
				array(
					'id'      => array( 'type' => 'integer' ),
					'updated' => array( 'type' => 'boolean' ),
				),
				array( 'id', 'updated' )
			),
			'execute_callback'    => 'openstation_mcp_editorial_update_media',
			'permission_callback' => 'openstation_mcp_editorial_can_update_media',
			'meta'                => openstation_mcp_editorial_meta( false, true ),
		)
	);

	$comment_id_input = openstation_mcp_editorial_object_schema(
		array(
			'comment_id' => array(
				'type'    => 'integer',
				'minimum' => 1,
			),
		),
		array( 'comment_id' )
	);
	$comment_output   = openstation_mcp_editorial_object_schema(
		array(
			'id'          => array( 'type' => 'integer' ),
			'post_id'     => array( 'type' => 'integer' ),
			'parent'      => array( 'type' => 'integer' ),
			'author_name' => array( 'type' => 'string' ),
			'content'     => array( 'type' => 'string' ),
			'status'      => array( 'type' => 'string' ),
			'date_gmt'    => array( 'type' => 'string' ),
		),
		array( 'id', 'post_id', 'parent', 'author_name', 'content', 'status', 'date_gmt' )
	);

	wp_register_ability(
		'desktop-mode/get-comment',
		array(
			'label'               => __( 'Get comment for moderation', 'desktop-mode' ),
			'description'         => 'Return one comment for editorial review without email address, IP address, user agent, or author URL. Requires comment moderation permission.',
			'category'            => OPENSTATION_AI_ABILITY_CATEGORY,
			'input_schema'        => $comment_id_input,
			'output_schema'       => $comment_output,
			'execute_callback'    => 'openstation_mcp_editorial_get_comment',
			'permission_callback' => 'openstation_mcp_editorial_can_moderate_comments',
			'meta'                => openstation_mcp_editorial_meta( true, true ),
		)
	);

	wp_register_ability(
		'desktop-mode/moderate-comment',
		array(
			'label'               => __( 'Moderate comment', 'desktop-mode' ),
			'description'         => 'Set one comment to approved, held for review, or spam. It cannot trash or permanently delete a comment. Read the comment first and use only with explicit user intent.',
			'category'            => OPENSTATION_AI_ABILITY_CATEGORY,
			'input_schema'        => openstation_mcp_editorial_object_schema(
				array(
					'comment_id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'status'     => array(
						'type' => 'string',
						'enum' => array( 'approve', 'hold', 'spam' ),
					),
				),
				array( 'comment_id', 'status' )
			),
			'output_schema'       => $comment_output,
			'execute_callback'    => 'openstation_mcp_editorial_moderate_comment',
			'permission_callback' => 'openstation_mcp_editorial_can_moderate_comments',
			'meta'                => openstation_mcp_editorial_meta( false, false ),
		)
	);
}
add_action( 'wp_abilities_api_init', 'openstation_mcp_register_editorial_abilities' );

/**
 * List content visible to the current user.
 *
 * @param array $args Validated input.
 * @return array
 */
function openstation_mcp_editorial_list_content( $args ) {
	$args     = (array) $args;
	$type     = isset( $args['type'] ) && 'any' !== $args['type'] ? (string) $args['type'] : array( 'post', 'page' );
	$status   = isset( $args['status'] ) ? (string) $args['status'] : 'draft';
	$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
	$per_page = isset( $args['per_page'] ) ? min( 25, max( 1, (int) $args['per_page'] ) ) : 10;
	$query    = new WP_Query(
		array(
			'post_type'              => $type,
			'post_status'            => $status,
			's'                      => isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '',
			'paged'                  => $page,
			'posts_per_page'         => $per_page,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$items = array();
	foreach ( $query->posts as $post ) {
		if ( ! ( $post instanceof WP_Post ) || ! current_user_can( 'read_post', $post->ID ) ) {
			continue;
		}
		$items[] = array(
			'id'           => (int) $post->ID,
			'type'         => (string) $post->post_type,
			'status'       => (string) $post->post_status,
			'title'        => (string) $post->post_title,
			'excerpt'      => (string) $post->post_excerpt,
			'author'       => (int) $post->post_author,
			'modified_gmt' => (string) $post->post_modified_gmt,
			'url'          => (string) get_permalink( $post ),
		);
	}

	return array(
		'items' => $items,
		'count' => count( $items ),
	);
}

/**
 * Permission callback for list-editorial-content.
 *
 * @param array $args Validated input.
 * @return bool
 */
function openstation_mcp_editorial_can_list_content( $args ) {
	$args = (array) $args;
	$type = isset( $args['type'] ) ? (string) $args['type'] : 'any';
	if ( 'post' === $type ) {
		return current_user_can( 'edit_posts' );
	}
	if ( 'page' === $type ) {
		return current_user_can( 'edit_pages' );
	}
	return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );
}

/**
 * Fetch one post or page.
 *
 * @param array $args Validated input.
 * @return array|WP_Error
 */
function openstation_mcp_editorial_get_content( $args ) {
	$post = get_post( isset( $args['post_id'] ) ? (int) $args['post_id'] : 0 );
	if ( ! ( $post instanceof WP_Post ) || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return new WP_Error( 'openstation_mcp_content_not_found', __( 'Post or page not found.', 'desktop-mode' ) );
	}
	return openstation_mcp_editorial_shape_content( $post );
}

/**
 * Permission callback for get-content-item.
 *
 * @param array $args Validated input.
 * @return bool
 */
function openstation_mcp_editorial_can_read_content( $args ) {
	$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
	return $post_id > 0 && current_user_can( 'read_post', $post_id );
}

/**
 * Create a draft post or page.
 *
 * @param array $args Validated input.
 * @return array|WP_Error
 */
function openstation_mcp_editorial_create_draft( $args ) {
	$args = (array) $args;
	$type = isset( $args['type'] ) && 'page' === $args['type'] ? 'page' : 'post';
	$id   = wp_insert_post(
		array(
			'post_type'    => $type,
			'post_status'  => 'draft',
			'post_author'  => get_current_user_id(),
			'post_title'   => sanitize_text_field( (string) ( $args['title'] ?? '' ) ),
			'post_content' => (string) ( $args['content'] ?? '' ),
			'post_excerpt' => isset( $args['excerpt'] ) ? (string) $args['excerpt'] : '',
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	return openstation_mcp_editorial_shape_content( get_post( $id ) );
}

/**
 * Permission callback for create-draft.
 *
 * @param array $args Validated input.
 * @return bool
 */
function openstation_mcp_editorial_can_create_draft( $args ) {
	$type = isset( $args['type'] ) && 'page' === $args['type'] ? 'page' : 'post';
	return current_user_can( 'page' === $type ? 'edit_pages' : 'edit_posts' );
}

/**
 * Update an existing draft without allowing a status transition.
 *
 * @param array $args Validated input.
 * @return array|WP_Error
 */
function openstation_mcp_editorial_update_draft( $args ) {
	$args    = (array) $args;
	$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
	$post    = get_post( $post_id );
	if ( ! ( $post instanceof WP_Post ) || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return new WP_Error( 'openstation_mcp_content_not_found', __( 'Post or page not found.', 'desktop-mode' ) );
	}
	if ( 'draft' !== $post->post_status ) {
		return new WP_Error( 'openstation_mcp_not_a_draft', __( 'Only existing drafts can be updated by this ability.', 'desktop-mode' ) );
	}

	$update = array(
		'ID'          => $post_id,
		'post_status' => 'draft',
	);
	if ( array_key_exists( 'title', $args ) ) {
		$update['post_title'] = sanitize_text_field( (string) $args['title'] );
	}
	if ( array_key_exists( 'content', $args ) ) {
		$update['post_content'] = (string) $args['content'];
	}
	if ( array_key_exists( 'excerpt', $args ) ) {
		$update['post_excerpt'] = (string) $args['excerpt'];
	}
	if ( 2 === count( $update ) ) {
		return new WP_Error( 'openstation_mcp_no_draft_changes', __( 'Provide at least one draft field to update.', 'desktop-mode' ) );
	}

	$result = wp_update_post( $update, true );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	return openstation_mcp_editorial_shape_content( get_post( $post_id ) );
}

/**
 * Permission callback for update-draft.
 *
 * @param array $args Validated input.
 * @return bool
 */
function openstation_mcp_editorial_can_update_draft( $args ) {
	$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
	return $post_id > 0 && current_user_can( 'edit_post', $post_id );
}

/**
 * Fetch one safe media projection.
 *
 * @param array $args Validated input.
 * @return array|WP_Error
 */
function openstation_mcp_editorial_get_media( $args ) {
	$attachment_id = isset( $args['attachment_id'] ) ? (int) $args['attachment_id'] : 0;
	$post          = get_post( $attachment_id );
	if ( ! ( $post instanceof WP_Post ) || 'attachment' !== $post->post_type ) {
		return new WP_Error( 'openstation_mcp_media_not_found', __( 'Media item not found.', 'desktop-mode' ) );
	}
	$meta = wp_get_attachment_metadata( $attachment_id );
	$meta = is_array( $meta ) ? $meta : array();

	return array(
		'id'          => (int) $post->ID,
		'title'       => (string) $post->post_title,
		'url'         => (string) wp_get_attachment_url( $attachment_id ),
		'mime'        => (string) get_post_mime_type( $post ),
		'width'       => isset( $meta['width'] ) ? (int) $meta['width'] : 0,
		'height'      => isset( $meta['height'] ) ? (int) $meta['height'] : 0,
		'alt_text'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		'caption'     => (string) $post->post_excerpt,
		'description' => (string) $post->post_content,
		'parent'      => (int) $post->post_parent,
		'date_gmt'    => (string) $post->post_date_gmt,
	);
}

/**
 * Permission callback for get-media-item.
 *
 * @param array $args Validated input.
 * @return bool
 */
function openstation_mcp_editorial_can_read_media( $args ) {
	$attachment_id = isset( $args['attachment_id'] ) ? (int) $args['attachment_id'] : 0;
	return $attachment_id > 0 && current_user_can( 'upload_files' );
}

/**
 * Update text metadata without touching the media file.
 *
 * @param array $args Validated input.
 * @return array|WP_Error
 */
function openstation_mcp_editorial_update_media( $args ) {
	$args          = (array) $args;
	$attachment_id = isset( $args['attachment_id'] ) ? (int) $args['attachment_id'] : 0;
	$post          = get_post( $attachment_id );
	if ( ! ( $post instanceof WP_Post ) || 'attachment' !== $post->post_type ) {
		return new WP_Error( 'openstation_mcp_media_not_found', __( 'Media item not found.', 'desktop-mode' ) );
	}

	$changed = array_key_exists( 'alt_text', $args );
	$update = array( 'ID' => $attachment_id );
	if ( array_key_exists( 'title', $args ) ) {
		$update['post_title'] = sanitize_text_field( (string) $args['title'] );
	}
	if ( array_key_exists( 'caption', $args ) ) {
		$update['post_excerpt'] = (string) $args['caption'];
	}
	if ( array_key_exists( 'description', $args ) ) {
		$update['post_content'] = (string) $args['description'];
	}
	if ( count( $update ) > 1 ) {
		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$changed = true;
	}
	if ( ! $changed ) {
		return new WP_Error( 'openstation_mcp_no_media_changes', __( 'Provide at least one media field to update.', 'desktop-mode' ) );
	}
	if ( array_key_exists( 'alt_text', $args ) ) {
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $args['alt_text'] ) );
	}

	return array(
		'id'      => $attachment_id,
		'updated' => true,
	);
}

/**
 * Permission callback for update-media-metadata.
 *
 * @param array $args Validated input.
 * @return bool
 */
function openstation_mcp_editorial_can_update_media( $args ) {
	$attachment_id = isset( $args['attachment_id'] ) ? (int) $args['attachment_id'] : 0;
	return $attachment_id > 0 && current_user_can( 'edit_post', $attachment_id );
}

/**
 * Project a comment without contact or network metadata.
 *
 * @param WP_Comment $comment Comment object.
 * @return array
 */
function openstation_mcp_editorial_shape_comment( WP_Comment $comment ) {
	return array(
		'id'          => (int) $comment->comment_ID,
		'post_id'     => (int) $comment->comment_post_ID,
		'parent'      => (int) $comment->comment_parent,
		'author_name' => (string) $comment->comment_author,
		'content'     => (string) $comment->comment_content,
		'status'      => (string) wp_get_comment_status( $comment ),
		'date_gmt'    => (string) $comment->comment_date_gmt,
	);
}

/**
 * Fetch one comment for moderation.
 *
 * @param array $args Validated input.
 * @return array|WP_Error
 */
function openstation_mcp_editorial_get_comment( $args ) {
	$comment = get_comment( isset( $args['comment_id'] ) ? (int) $args['comment_id'] : 0 );
	if ( ! ( $comment instanceof WP_Comment ) ) {
		return new WP_Error( 'openstation_mcp_comment_not_found', __( 'Comment not found.', 'desktop-mode' ) );
	}
	return openstation_mcp_editorial_shape_comment( $comment );
}

/**
 * Permission callback for comment review and moderation.
 *
 * @param array $args Validated input.
 * @return bool
 */
function openstation_mcp_editorial_can_moderate_comments( $args ) {
	$comment_id = isset( $args['comment_id'] ) ? (int) $args['comment_id'] : 0;
	return $comment_id > 0 && current_user_can( 'moderate_comments' );
}

/**
 * Apply one reversible moderation status.
 *
 * @param array $args Validated input.
 * @return array|WP_Error
 */
function openstation_mcp_editorial_moderate_comment( $args ) {
	$args       = (array) $args;
	$comment_id = isset( $args['comment_id'] ) ? (int) $args['comment_id'] : 0;
	$comment    = get_comment( $comment_id );
	if ( ! ( $comment instanceof WP_Comment ) ) {
		return new WP_Error( 'openstation_mcp_comment_not_found', __( 'Comment not found.', 'desktop-mode' ) );
	}
	$status_map = array(
		'approve' => 'approve',
		'hold'    => 'hold',
		'spam'    => 'spam',
	);
	$status     = isset( $args['status'], $status_map[ $args['status'] ] ) ? $status_map[ $args['status'] ] : '';
	if ( '' === $status ) {
		return new WP_Error( 'openstation_mcp_invalid_comment_status', __( 'Unsupported comment status.', 'desktop-mode' ) );
	}
	$result = wp_set_comment_status( $comment_id, $status, true );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	if ( ! $result ) {
		return new WP_Error( 'openstation_mcp_comment_update_failed', __( 'The comment status could not be updated.', 'desktop-mode' ) );
	}
	return openstation_mcp_editorial_shape_comment( get_comment( $comment_id ) );
}
