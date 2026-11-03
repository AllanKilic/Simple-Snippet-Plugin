<?php
/**
 * Frontend output of snippets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SSnip_Frontend {

	const CACHE_KEY = 'ssnip_active_snippets';

	/** @var array|null Snippets matching the current request, grouped by location. */
	private static $matched = null;

	public static function init() {
		// Cache invalidation (runs in admin and frontend).
		add_action( 'save_post_' . SSNIP_CPT, array( __CLASS__, 'flush_cache' ) );
		add_action( 'deleted_post', array( __CLASS__, 'maybe_flush_cache' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'maybe_flush_on_status' ), 10, 3 );

		if ( is_admin() ) {
			return;
		}

		add_action( 'wp_head', array( __CLASS__, 'print_head' ), 99 );
		add_action( 'wp_footer', array( __CLASS__, 'print_footer' ), 99 );
	}

	public static function flush_cache() {
		delete_transient( self::CACHE_KEY );
	}

	public static function maybe_flush_cache( $post_id ) {
		if ( SSNIP_CPT === get_post_type( $post_id ) ) {
			self::flush_cache();
		}
	}

	public static function maybe_flush_on_status( $new, $old, $post ) {
		if ( $post instanceof WP_Post && SSNIP_CPT === $post->post_type && $new !== $old ) {
			self::flush_cache();
		}
	}

	public static function print_head() {
		self::render( 'head' );
	}

	public static function print_footer() {
		self::render( 'footer' );
	}

	/**
	 * All published snippets with their settings, cached in a transient.
	 */
	private static function all_active() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$ids = get_posts(
			array(
				'post_type'        => SSNIP_CPT,
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'menu_order ID',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);

		$snippets = array();
		foreach ( $ids as $id ) {
			$data = SSnip_Snippet::get( $id );
			if ( '' === trim( $data['code'] ) ) {
				continue;
			}
			$data['id'] = (int) $id;
			$snippets[] = $data;
		}

		usort(
			$snippets,
			function ( $a, $b ) {
				return ( $a['priority'] <=> $b['priority'] ) ?: ( $a['id'] <=> $b['id'] );
			}
		);

		set_transient( self::CACHE_KEY, $snippets, DAY_IN_SECONDS );

		return $snippets;
	}

	/**
	 * Output the snippets for one location.
	 */
	private static function render( $location ) {
		if ( is_feed() || is_embed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( null === self::$matched ) {
			self::$matched = array(
				'head'   => array(),
				'footer' => array(),
			);
			foreach ( self::all_active() as $snippet ) {
				if ( self::matches( $snippet ) ) {
					self::$matched[ $snippet['location'] ][] = $snippet;
				}
			}
		}

		foreach ( self::$matched[ $location ] as $snippet ) {
			$code = $snippet['code'];

			// JS snippets are wrapped in a <script> tag unless the user already did it.
			if ( 'js' === $snippet['type'] && ! preg_match( '/<script[\s>]/i', $code ) ) {
				$code = "<script>\n" . $code . "\n</script>";
			}

			/**
			 * Filter the final code of a snippet before output.
			 *
			 * @param string $code    Code to print.
			 * @param array  $snippet Snippet settings.
			 */
			$code = apply_filters( 'ssnip_snippet_output', $code, $snippet );

			// Raw output is intended: only users with unfiltered_html can save snippet code.
			echo "\n<!-- Simple Snippets #" . (int) $snippet['id'] . " -->\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Does this snippet apply to the current request?
	 */
	public static function matches( array $s ) {
		$object_id = self::current_object_id();

		// Exclusions always win.
		if ( $object_id && in_array( $object_id, $s['exclude'], true ) ) {
			return false;
		}

		$match = false;

		if ( 'all' === $s['scope'] ) {
			$match = true;
		} else {
			// 1. Specific posts / pages.
			if ( $object_id && in_array( $object_id, $s['items'], true ) ) {
				$match = true;
			}

			// 2. Front page.
			if ( ! $match && $s['front_page'] && is_front_page() ) {
				$match = true;
			}

			// 3. Every single item of the chosen post types.
			if ( ! $match && ! empty( $s['post_types'] ) && is_singular( $s['post_types'] ) ) {
				$match = true;
			}

			// 4. Archives of the chosen post types.
			if ( ! $match && $s['archives'] && ! empty( $s['post_types'] ) ) {
				$match = self::is_archive_of( $s['post_types'] );
			}
		}

		/**
		 * Final say on whether a snippet is displayed on the current request.
		 *
		 * @param bool  $match   Result of the rules.
		 * @param array $snippet Snippet settings.
		 */
		return (bool) apply_filters( 'ssnip_snippet_matches', $match, $s );
	}

	/**
	 * ID of the post/page being viewed, including a static front page and the posts page.
	 */
	private static function current_object_id() {
		if ( is_singular() ) {
			return (int) get_queried_object_id();
		}
		if ( is_home() && ! is_front_page() ) {
			return (int) get_option( 'page_for_posts' );
		}
		return 0;
	}

	private static function is_archive_of( array $post_types ) {
		if ( is_post_type_archive( $post_types ) ) {
			return true;
		}

		if ( in_array( 'post', $post_types, true ) && ( is_home() || is_category() || is_tag() || is_author() || is_date() ) ) {
			return true;
		}

		if ( is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$tax = get_taxonomy( $term->taxonomy );
				if ( $tax && array_intersect( (array) $tax->object_type, $post_types ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
