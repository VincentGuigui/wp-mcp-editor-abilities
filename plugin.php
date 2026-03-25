<?php
/*
Plugin Name: MCP Editor Abilities
Plugin URI:  https://github.com/vincentguigui/wp-mcp-editor-abilities
Description: Exposes WordPress editor abilities through the MCP adapter.
Version:     1.3.0
Author:      Vincent Guigui
Author URI:  https://github.com/vincentguigui
License:     GPL-2.0-or-later
*/

use WP\MCP\Core\McpAdapter;

// Mark every registered ability as MCP-public.
add_filter( 'wp_register_ability_args', function( array $args ): array {
    $args['meta']['mcp']['public'] = true;
    return $args;
} );

// Register the 'post' category (core only ships 'site' and 'user').
add_action( 'wp_abilities_api_categories_init', function() {
    wp_register_ability_category( 'post', array(
        'label'       => 'Post',
        'description' => 'Abilities that retrieve or modify posts and their content.',
    ) );
} );

// Register custom abilities.
add_action( 'wp_abilities_api_init', function() {

    // ----------------------------------------------------------------
    // list-posts
    // ----------------------------------------------------------------
    wp_register_ability( 'mcp-editor-abilities/list-posts', array(
        'label'               => 'List Posts',
        'description'         => 'Returns a paginated list of posts.',
        'category'            => 'post',
        'input_schema'        => array(
            'type'                 => 'object',
            'properties'           => array(
                'status'   => array( 'type' => 'string', 'default' => 'publish' ),
                'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10 ),
                'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
            ),
            'additionalProperties' => false,
        ),
        'execute_callback'    => static function( $input = array() ): array {
            $per_page = $input['per_page'] ?? 10;
            $page     = $input['page'] ?? 1;
            $status   = $input['status'] ?? 'publish';
            $posts    = get_posts( array(
                'post_status'    => $status,
                'posts_per_page' => $per_page,
                'paged'          => $page,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ) );
            return array_map( static function( $post ) {
                return array(
                    'id'     => $post->ID,
                    'title'  => $post->post_title,
                    'status' => $post->post_status,
                    'date'   => $post->post_date,
                    'url'    => get_permalink( $post->ID ),
                );
            }, $posts );
        },
        'permission_callback' => static function(): bool {
            return current_user_can( 'edit_posts' );
        },
        'meta'                => array(
            'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
        ),
    ) );

    // ----------------------------------------------------------------
    // edit-post
    // ----------------------------------------------------------------
    wp_register_ability( 'mcp-editor-abilities/edit-post', array(
        'label'               => 'Edit Post',
        'description'         => "Updates a post's title and/or content.",
        'category'            => 'post',
        'input_schema'        => array(
            'type'                 => 'object',
            'required'             => array( 'id' ),
            'properties'           => array(
                'id'      => array( 'type' => 'integer' ),
                'title'   => array( 'type' => 'string' ),
                'content' => array( 'type' => 'string' ),
            ),
            'additionalProperties' => false,
        ),
        'execute_callback'    => static function( $input ): array {
            $update = array( 'ID' => (int) $input['id'] );
            if ( isset( $input['title'] ) )   $update['post_title']   = $input['title'];
            if ( isset( $input['content'] ) ) $update['post_content'] = $input['content'];
            $result = wp_update_post( $update, true );
            if ( is_wp_error( $result ) ) {
                return array( 'error' => $result->get_error_message() );
            }
            $post = get_post( $result );
            return array(
                'id'      => $post->ID,
                'title'   => $post->post_title,
                'content' => $post->post_content,
            );
        },
        'permission_callback' => static function( $input ): bool {
            return current_user_can( 'edit_post', (int) $input['id'] );
        },
        'meta'                => array(
            'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
        ),
    ) );

    // ----------------------------------------------------------------
    // edit-post-title
    // ----------------------------------------------------------------
    wp_register_ability( 'mcp-editor-abilities/edit-post-title', array(
        'label'               => 'Edit Post Title',
        'description'         => 'Updates only the title of a post.',
        'category'            => 'post',
        'input_schema'        => array(
            'type'                 => 'object',
            'required'             => array( 'id', 'title' ),
            'properties'           => array(
                'id'    => array( 'type' => 'integer' ),
                'title' => array( 'type' => 'string' ),
            ),
            'additionalProperties' => false,
        ),
        'execute_callback'    => static function( $input ): array {
            $result = wp_update_post( array( 'ID' => (int) $input['id'], 'post_title' => $input['title'] ), true );
            if ( is_wp_error( $result ) ) {
                return array( 'error' => $result->get_error_message() );
            }
            return array( 'id' => (int) $result, 'title' => $input['title'] );
        },
        'permission_callback' => static function( $input ): bool {
            return current_user_can( 'edit_post', (int) $input['id'] );
        },
        'meta'                => array(
            'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
        ),
    ) );

    // ----------------------------------------------------------------
    // edit-site-name
    // ----------------------------------------------------------------
    wp_register_ability( 'mcp-editor-abilities/edit-site-name', array(
        'label'               => 'Edit Site Name',
        'description'         => 'Updates the WordPress site name.',
        'category'            => 'site',
        'input_schema'        => array(
            'type'                 => 'object',
            'required'             => array( 'name' ),
            'properties'           => array(
                'name' => array( 'type' => 'string' ),
            ),
            'additionalProperties' => false,
        ),
        'execute_callback'    => static function( $input ): array {
            update_option( 'blogname', sanitize_text_field( $input['name'] ) );
            return array( 'name' => get_option( 'blogname' ) );
        },
        'permission_callback' => static function(): bool {
            return current_user_can( 'manage_options' );
        },
        'meta'                => array(
            'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
        ),
    ) );

    // ----------------------------------------------------------------
    // get-post-blocks
    // ----------------------------------------------------------------
    wp_register_ability( 'mcp-editor-abilities/get-post-blocks', array(
        'label'               => 'Get Post Blocks',
        'description'         => 'Returns the list of Gutenberg blocks in a specific post.',
        'category'            => 'post',
        'input_schema'        => array(
            'type'                 => 'object',
            'required'             => array( 'id' ),
            'properties'           => array(
                'id' => array( 'type' => 'integer' ),
            ),
            'additionalProperties' => false,
        ),
        'execute_callback'    => static function( $input ): array {
            $post = get_post( (int) $input['id'] );
            if ( ! $post ) {
                return array( 'error' => 'Post not found.' );
            }
            $blocks = parse_blocks( $post->post_content );
            $result = array();
            foreach ( $blocks as $index => $block ) {
                if ( empty( $block['blockName'] ) ) continue;
                $result[] = array(
                    'index'      => $index,
                    'block_name' => $block['blockName'],
                    'attrs'      => $block['attrs'],
                    'inner_html' => trim( $block['innerHTML'] ),
                );
            }
            return $result;
        },
        'permission_callback' => static function( $input ): bool {
            return current_user_can( 'edit_post', (int) $input['id'] );
        },
        'meta'                => array(
            'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
        ),
    ) );

    // ----------------------------------------------------------------
    // edit-post-block
    // ----------------------------------------------------------------
    wp_register_ability( 'mcp-editor-abilities/edit-post-block', array(
        'label'               => 'Edit Post Block',
        'description'         => 'Replaces the attributes and/or inner HTML of a block at a given index in a post.',
        'category'            => 'post',
        'input_schema'        => array(
            'type'                 => 'object',
            'required'             => array( 'id', 'block_index' ),
            'properties'           => array(
                'id'          => array( 'type' => 'integer' ),
                'block_index' => array( 'type' => 'integer', 'minimum' => 0 ),
                'attrs'       => array( 'type' => 'object' ),
                'inner_html'  => array( 'type' => 'string' ),
            ),
            'additionalProperties' => false,
        ),
        'execute_callback'    => static function( $input ): array {
            $post = get_post( (int) $input['id'] );
            if ( ! $post ) {
                return array( 'error' => 'Post not found.' );
            }
            $blocks = parse_blocks( $post->post_content );
            $idx    = (int) $input['block_index'];
            if ( ! isset( $blocks[ $idx ] ) || empty( $blocks[ $idx ]['blockName'] ) ) {
                return array( 'error' => "Block at index {$idx} not found." );
            }
            if ( isset( $input['attrs'] ) ) {
                $blocks[ $idx ]['attrs'] = array_merge( $blocks[ $idx ]['attrs'], (array) $input['attrs'] );
            }
            if ( isset( $input['inner_html'] ) ) {
                $blocks[ $idx ]['innerHTML']    = $input['inner_html'];
                $blocks[ $idx ]['innerContent'] = array( $input['inner_html'] );
            }
            $new_content = serialize_blocks( $blocks );
            $result      = wp_update_post( array( 'ID' => $post->ID, 'post_content' => $new_content ), true );
            if ( is_wp_error( $result ) ) {
                return array( 'error' => $result->get_error_message() );
            }
            return array(
                'id'          => $post->ID,
                'block_index' => $idx,
                'block_name'  => $blocks[ $idx ]['blockName'],
            );
        },
        'permission_callback' => static function( $input ): bool {
            return current_user_can( 'edit_post', (int) $input['id'] );
        },
        'meta'                => array(
            'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
        ),
    ) );

} );

// Boot the MCP adapter.
add_action( 'wp_loaded', function() {
    if ( ! class_exists( McpAdapter::class ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>MCP Adapter plugin is missing.</p></div>';
        } );
        return;
    }
    McpAdapter::instance();
} );
