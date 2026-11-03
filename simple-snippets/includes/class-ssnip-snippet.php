<?php
/**
 * Snippet post type and settings accessor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SSnip_Snippet {

	/**
	 * Default settings for a snippet.
	 */
	public static function defaults() {
		return array(
			'type'          => 'html',   // html | js
			'location'      => 'head',   // head | footer
			'priority'      => 10,
			'scope'         => 'all',    // all | selected
			'post_types'    => array(),  // show on every single item of these post types
			'archives'      => false,    // also on archives of the selected post types
			'front_page'    => false,    // also on the front page
			'items'         => array(),  // specific post / page IDs
			'exclude'       => array(),  // IDs where the snippet must never appear
			'code'          => '',
		);
	}

	/**
	 * Register the (non-public) snippet post type. Only administrators can manage it.
	 */
	public static function register_post_type() {
		$cap = 'manage_options';

		register_post_type(
			SSNIP_CPT,
			array(
				'labels'              => array(
					'name'               => __( 'Snippets', 'simple-snippets' ),
					'singular_name'      => __( 'Snippet', 'simple-snippets' ),
					'add_new'            => __( 'Add snippet', 'simple-snippets' ),
					'add_new_item'       => __( 'Add new snippet', 'simple-snippets' ),
					'edit_item'          => __( 'Edit snippet', 'simple-snippets' ),
					'new_item'           => __( 'New snippet', 'simple-snippets' ),
					'search_items'       => __( 'Search snippets', 'simple-snippets' ),
					'not_found'          => __( 'No snippets found.', 'simple-snippets' ),
					'not_found_in_trash' => __( 'No snippets found in Trash.', 'simple-snippets' ),
					'all_items'          => __( 'All snippets', 'simple-snippets' ),
					'menu_name'          => __( 'Snippets', 'simple-snippets' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-editor-code',
				'menu_position'       => 81,
				'supports'            => array( 'title' ),
				'rewrite'             => false,
				'query_var'           => false,
				'map_meta_cap'        => false,
				'capabilities'        => array(
					'edit_post'              => $cap,
					'read_post'              => $cap,
					'delete_post'            => $cap,
					'edit_posts'             => $cap,
					'edit_others_posts'      => $cap,
					'delete_posts'           => $cap,
					'publish_posts'          => $cap,
					'read_private_posts'     => $cap,
					'delete_private_posts'   => $cap,
					'delete_published_posts' => $cap,
					'delete_others_posts'    => $cap,
					'edit_private_posts'     => $cap,
					'edit_published_posts'   => $cap,
					'create_posts'           => $cap,
				),
			)
		);
	}

	/**
	 * Read all settings of a snippet, merged with defaults.
	 */
	public static function get( $post_id ) {
		$stored = get_post_meta( $post_id, '_ssnip_settings', true );
		$data   = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );

		$data['code'] = (string) get_post_meta( $post_id, '_ssnip_code', true );

		return $data;
	}

	/**
	 * Sanitize settings coming from the edit screen.
	 */
	public static function sanitize( array $raw ) {
		$d = self::defaults();

		$d['type']     = ( isset( $raw['type'] ) && 'js' === $raw['type'] ) ? 'js' : 'html';
		$d['location'] = ( isset( $raw['location'] ) && 'footer' === $raw['location'] ) ? 'footer' : 'head';
		$d['priority'] = isset( $raw['priority'] ) ? max( 0, min( 999, (int) $raw['priority'] ) ) : 10;
		$d['scope']    = ( isset( $raw['scope'] ) && 'selected' === $raw['scope'] ) ? 'selected' : 'all';

		$allowed_types   = array_keys( self::targetable_post_types() );
		$d['post_types'] = isset( $raw['post_types'] ) && is_array( $raw['post_types'] )
			? array_values( array_intersect( array_map( 'sanitize_key', $raw['post_types'] ), $allowed_types ) )
			: array();

		$d['archives']   = ! empty( $raw['archives'] );
		$d['front_page'] = ! empty( $raw['front_page'] );
		$d['items']      = self::sanitize_ids( isset( $raw['items'] ) ? $raw['items'] : array() );
		$d['exclude']    = self::sanitize_ids( isset( $raw['exclude'] ) ? $raw['exclude'] : array() );

		unset( $d['code'] );

		return $d;
	}

	private static function sanitize_ids( $ids ) {
		if ( ! is_array( $ids ) ) {
			return array();
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/**
	 * Public post types a snippet can target (posts, pages, products, etc.).
	 */
	public static function targetable_post_types() {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );

		$out = array();
		foreach ( $types as $name => $obj ) {
			$out[ $name ] = $obj->labels->name;
		}
		return $out;
	}
}
