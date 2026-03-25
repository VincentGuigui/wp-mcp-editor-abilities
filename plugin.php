<?php
/*
Plugin Name: MCP Editor Abilities
Plugin URI:  https://github.com/vincentguigui/wp-mcp-editor-abilities
Description: Exposes WordPress editor abilities through the MCP adapter.
Version:     2.0.0
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

// Register ability categories.
add_action( 'wp_abilities_api_categories_init', function() {
    wp_register_ability_category( 'post', array(
        'label'       => 'Post',
        'description' => 'Abilities that retrieve or modify posts and their content.',
    ) );
    wp_register_ability_category( 'taxonomy', array(
        'label'       => 'Taxonomy',
        'description' => 'Abilities that retrieve or modify categories and terms.',
    ) );
} );

// Register abilities.
add_action( 'wp_abilities_api_init', function() {

    // ==========================================================
    //  POSTS
    // ==========================================================

    wp_register_ability( 'mcp-editor/list-posts', array(
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
            'default'              => array(),
        ),
        'execute_callback'    => static function( $input = array() ): array {
            $posts = get_posts( array(
                'post_status'    => $input['status'] ?? 'publish',
                'posts_per_page' => $input['per_page'] ?? 10,
                'paged'          => $input['page'] ?? 1,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ) );
            return array_map( static function( $p ) {
                return array(
                    'id'     => $p->ID,
                    'title'  => $p->post_title,
                    'status' => $p->post_status,
                    'date'   => $p->post_date,
                    'url'    => get_permalink( $p->ID ),
                );
            }, $posts );
        },
        'permission_callback' => static function(): bool {
            return current_user_can( 'edit_posts' );
        },
        'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
    ) );

    wp_register_ability( 'mcp-editor/add-post', array(
        'label'               => 'Add Post',
        'description'         => 'Creates a new post (defaults to draft status).',
        'category'            => 'post',
        'input_schema'        => array(
            'type'                 => 'object',
            'required'             => array( 'title' ),
            'properties'           => array(
                'title'   => array( 'type' => 'string' ),
                'content' => array( 'type' => 'string', 'default' => '' ),
                'status'  => array( 'type' => 'string', 'default' => 'draft' ),
            ),
            'additionalProperties' => false,
        ),
        'execute_callback'    => static function( $input ): array {
            $result = wp_insert_post( array(
                'post_title'   => sanitize_text_field( $input['title'] ),
                'post_content' => $input['content'] ?? '',
                'post_status'  => $input['status'] ?? 'draft',
            ), true );
            if ( is_wp_error( $result ) ) {
                return array( 'error' => $result->get_error_message() );
            }
            $post = get_post( $result );
            return array( 'id' => $post->ID, 'title' => $post->post_title, 'status' => $post->post_status, 'url' => get_permalink( $post->ID ) );
        },
        'permission_callback' => static function(): bool {
            return current_user_can( 'edit_posts' );
        },
        'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ) ),
    ) );

    wp_register_ability( 'mcp-editor/duplicate-post', array(
        'label'               => 'Duplicate Post',
        'description'         => 'Duplicates an existing post as a new draft.',
        'category'            => 'post',
        'input_schema'        => array(
            'type'                 => 'object',
            'required'             => array( 'id' ),
            'properties'           => array(
                'id'    => array( 'type' => 'integer' ),
                'title' => array( 'type' => 'string' ),
            ),
            'additionalProperties' => false,
        ),
        'execute_callback'    => static function( $input ): array {
            $source = get_post( (int) $input['id'] );
            if ( ! $source ) {
                return array( 'error' => 'Post not found.' );
            }
            $new_title = $input['title'] ?? $source->post_title . ' (copy)';
            $result = wp_insert_post( array(
                'post_title'   => $new_title,
                'post_content' => $source->post_content,
                'post_excerpt' => $source->post_excerpt,
                'post_status'  => 'draft',
                'post_type'    => $source->post_type,
            ), true );
            if ( is_wp_error( $result ) ) {
                return array( 'error' => $result->get_error_message() );
            }
            // Copy categories.
            $cats = wp_get_post_categories( $source->ID );
            if ( ! empty( $cats ) ) {
                wp_set_post_categories( $result, $cats );
            }
            return array( 'id' => $result, 'title' => $new_title, 'source_id' => $source->ID );
        },
        'permission_callback' => static function( $input ): bool {
            return current_user_can( 'edit_post', (int) $input['id'] );
        },
        'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ) ),
    ) );

    $post_field_enum = array( 'title', 'status', 'date', 'excerpt', 'slug', 'content' );
    wp_register_ability( 'mcp-editor/update-post-field', array(
        'label'               => 'Update Post Field',
        'description'         => 'Updates a single field on a post. Allowed fields: ' . implode( ', ', $post_field_enum ) . '.',
        'category'            => 'post',
        'input_schema'        => array(
            'type'                 => 'object',
            'required'             => array( 'id', 'field', 'value' ),
            'properties'           => array(
                'id'    => array( 'type' => 'integer' ),
                'field' => array( 'type' => 'string', 'enum' => $post_field_enum ),
                'value' => array( 'type' => 'string' ),
            ),
            'additionalProperties' => false,
        ),
        'execute_callback'    => static function( $input ): array {
            $map = array(
                'title'   => 'post_title',
                'status'  => 'post_status',
                'date'    => 'post_date',
                'excerpt' => 'post_excerpt',
                'slug'    => 'post_name',
                'content' => 'post_content',
            );
            $result = wp_update_post( array( 'ID' => (int) $input['id'], $map[ $input['field'] ] => $input['value'] ), true );
            if ( is_wp_error( $result ) ) {
                return array( 'error' => $result->get_error_message() );
            }
            $post = get_post( $result );
            return array( 'id' => $post->ID, 'field' => $input['field'], 'value' => $post->{ $map[ $input['field'] ] } );
        },
        'permission_callback' => static function( $input ): bool {
            return current_user_can( 'edit_post', (int) $input['id'] );
        },
        'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ) ),
    ) );

    // ==========================================================
    //  POST BLOCKS
    // ==========================================================

    wp_register_ability( 'mcp-editor/list-post-blocks', array(
        'label'               => 'List Post Blocks',
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
        'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
    ) );

    wp_register_ability( 'mcp-editor/update-post-block', array(
        'label'               => 'Update Post Block',
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
            $result = wp_update_post( array( 'ID' => $post->ID, 'post_content' => serialize_blocks( $blocks ) ), true );
            if ( is_wp_error( $result ) ) {
                return array( 'error' => $result->get_error_message() );
            }
            return array( 'id' => $post->ID, 'block_index' => $idx, 'block_name' => $blocks[ $idx ]['blockName'] );
        },
        'permission_callback' => static function( $input ): bool {
            return current_user_can( 'edit_post', (int) $input['id'] );
        },
        'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ) ),
    ) );

    // ==========================================================
    //  CATEGORIES
    // ==========================================================

    wp_register_ability( 'mcp-editor/list-categories', array(
        'label'               => 'List Categories',
        'description'         => 'Returns a paginated list of categories.',
        'category'            => 'taxonomy',
        'input_schema'        => array(
            'type'                 => 'object',
            'properties'           => array(
                'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
                'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
            ),
            'additionalProperties' => false,
            'default'              => array(),
        ),
        'execute_callback'    => static function( $input = array() ): array {
            $per_page = $input['per_page'] ?? 20;
            $page     = $input['page'] ?? 1;
            $terms = get_terms( array(
                'taxonomy'   => 'category',
                'number'     => $per_page,
                'offset'     => ( $page - 1 ) * $per_page,
                'hide_empty' => false,
            ) );
            if ( is_wp_error( $terms ) ) {
                return array( 'error' => $terms->get_error_message() );
            }
            return array_map( static function( $t ) {
                return array(
                    'id'          => $t->term_id,
                    'name'        => $t->name,
                    'slug'        => $t->slug,
                    'description' => $t->description,
                    'parent'      => $t->parent,
                    'count'       => $t->count,
                );
            }, $terms );
        },
        'permission_callback' => static function(): bool {
            return current_user_can( 'manage_categories' );
        },
        'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
    ) );

    wp_register_ability( 'mcp-editor/add-category', array(
        'label'               => 'Add Category',
        'description'         => 'Creates a new category.',
        'category'            => 'taxonomy',
        'input_schema'        => array(
            'type'                 => 'object',
            'required'             => array( 'name' ),
            'properties'           => array(
                'name'        => array( 'type' => 'string' ),
                'slug'        => array( 'type' => 'string' ),
                'description' => array( 'type' => 'string' ),
                'parent'      => array( 'type' => 'integer' ),
            ),
            'additionalProperties' => false,
        ),
        'execute_callback'    => static function( $input ): array {
            $args = array();
            if ( isset( $input['slug'] ) )        $args['slug']        = $input['slug'];
            if ( isset( $input['description'] ) )  $args['description'] = $input['description'];
            if ( isset( $input['parent'] ) )       $args['parent']      = (int) $input['parent'];
            $result = wp_insert_term( sanitize_text_field( $input['name'] ), 'category', $args );
            if ( is_wp_error( $result ) ) {
                return array( 'error' => $result->get_error_message() );
            }
            $term = get_term( $result['term_id'], 'category' );
            return array( 'id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug );
        },
        'permission_callback' => static function(): bool {
            return current_user_can( 'manage_categories' );
        },
        'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ) ),
    ) );

    $cat_field_enum = array( 'name', 'slug', 'description', 'parent' );
    wp_register_ability( 'mcp-editor/update-category-field', array(
        'label'               => 'Update Category Field',
        'description'         => 'Updates a single field on a category. Allowed fields: ' . implode( ', ', $cat_field_enum ) . '.',
        'category'            => 'taxonomy',
        'input_schema'        => array(
            'type'                 => 'object',
            'required'             => array( 'id', 'field', 'value' ),
            'properties'           => array(
                'id'    => array( 'type' => 'integer' ),
                'field' => array( 'type' => 'string', 'enum' => $cat_field_enum ),
                'value' => array( 'type' => 'string' ),
            ),
            'additionalProperties' => false,
        ),
        'execute_callback'    => static function( $input ): array {
            $field = $input['field'];
            $value = $field === 'parent' ? (int) $input['value'] : $input['value'];
            $result = wp_update_term( (int) $input['id'], 'category', array( $field => $value ) );
            if ( is_wp_error( $result ) ) {
                return array( 'error' => $result->get_error_message() );
            }
            $term = get_term( $result['term_id'], 'category' );
            return array( 'id' => $term->term_id, 'field' => $field, 'value' => $term->$field );
        },
        'permission_callback' => static function(): bool {
            return current_user_can( 'manage_categories' );
        },
        'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ) ),
    ) );

    // ==========================================================
    //  POST ↔ CATEGORY
    // ==========================================================

    wp_register_ability( 'mcp-editor/assign-post-category', array(
        'label'               => 'Assign Category to Post',
        'description'         => 'Adds a category to a post.',
        'category'            => 'post',
        'input_schema'        => array(
            'type'                 => 'object',
            'required'             => array( 'post_id', 'category_id' ),
            'properties'           => array(
                'post_id'     => array( 'type' => 'integer' ),
                'category_id' => array( 'type' => 'integer' ),
            ),
            'additionalProperties' => false,
        ),
        'execute_callback'    => static function( $input ): array {
            $post_id = (int) $input['post_id'];
            $cat_id  = (int) $input['category_id'];
            $current = wp_get_post_categories( $post_id );
            if ( in_array( $cat_id, $current, true ) ) {
                return array( 'post_id' => $post_id, 'category_id' => $cat_id, 'status' => 'already_assigned' );
            }
            $current[] = $cat_id;
            wp_set_post_categories( $post_id, $current );
            return array( 'post_id' => $post_id, 'category_id' => $cat_id, 'status' => 'assigned' );
        },
        'permission_callback' => static function( $input ): bool {
            return current_user_can( 'edit_post', (int) $input['post_id'] );
        },
        'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ),
    ) );

    wp_register_ability( 'mcp-editor/remove-post-category', array(
        'label'               => 'Remove Category from Post',
        'description'         => 'Removes a category from a post.',
        'category'            => 'post',
        'input_schema'        => array(
            'type'                 => 'object',
            'required'             => array( 'post_id', 'category_id' ),
            'properties'           => array(
                'post_id'     => array( 'type' => 'integer' ),
                'category_id' => array( 'type' => 'integer' ),
            ),
            'additionalProperties' => false,
        ),
        'execute_callback'    => static function( $input ): array {
            $post_id = (int) $input['post_id'];
            $cat_id  = (int) $input['category_id'];
            $current = wp_get_post_categories( $post_id );
            if ( ! in_array( $cat_id, $current, true ) ) {
                return array( 'post_id' => $post_id, 'category_id' => $cat_id, 'status' => 'not_assigned' );
            }
            $updated = array_values( array_diff( $current, array( $cat_id ) ) );
            wp_set_post_categories( $post_id, $updated );
            return array( 'post_id' => $post_id, 'category_id' => $cat_id, 'status' => 'removed' );
        },
        'permission_callback' => static function( $input ): bool {
            return current_user_can( 'edit_post', (int) $input['post_id'] );
        },
        'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => true ) ),
    ) );

    // ==========================================================
    //  SITE
    // ==========================================================

    $site_field_enum = array( 'name', 'description' );
    wp_register_ability( 'mcp-editor/update-site-field', array(
        'label'               => 'Update Site Field',
        'description'         => 'Updates a site option. Allowed fields: ' . implode( ', ', $site_field_enum ) . '.',
        'category'            => 'site',
        'input_schema'        => array(
            'type'                 => 'object',
            'required'             => array( 'field', 'value' ),
            'properties'           => array(
                'field' => array( 'type' => 'string', 'enum' => $site_field_enum ),
                'value' => array( 'type' => 'string' ),
            ),
            'additionalProperties' => false,
        ),
        'execute_callback'    => static function( $input ): array {
            $map = array( 'name' => 'blogname', 'description' => 'blogdescription' );
            update_option( $map[ $input['field'] ], sanitize_text_field( $input['value'] ) );
            return array( 'field' => $input['field'], 'value' => get_option( $map[ $input['field'] ] ) );
        },
        'permission_callback' => static function(): bool {
            return current_user_can( 'manage_options' );
        },
        'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ) ),
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
