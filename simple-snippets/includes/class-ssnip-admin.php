<?php
/**
 * Admin screens: snippet editor, display rules, list columns.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SSnip_Admin {

	public static function init() {
		add_action( 'add_meta_boxes_' . SSNIP_CPT, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . SSNIP_CPT, array( __CLASS__, 'save' ), 5, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_ssnip_search_posts', array( __CLASS__, 'ajax_search' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );

		add_filter( 'manage_' . SSNIP_CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . SSNIP_CPT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_filter( 'post_updated_messages', array( __CLASS__, 'messages' ) );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
	}

	/* ------------------------------------------------------------------ */
	/* Assets                                                              */
	/* ------------------------------------------------------------------ */

	public static function assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || SSNIP_CPT !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		// WordPress's bundled CodeMirror (respects the user's "disable syntax highlighting" preference).
		$editor = wp_enqueue_code_editor( array( 'type' => 'text/html' ) );

		wp_enqueue_style( 'ssnip-admin', SSNIP_URL . 'assets/admin.css', array(), SSNIP_VERSION );
		wp_enqueue_script( 'ssnip-admin', SSNIP_URL . 'assets/admin.js', array( 'jquery' ), SSNIP_VERSION, true );
		wp_localize_script(
			'ssnip-admin',
			'SSNIP',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'ssnip_search' ),
				'editor'     => $editor ? $editor : false,
				'i18n'       => array(
					'noResults' => __( 'No results', 'simple-snippets' ),
					'remove'    => __( 'Remove', 'simple-snippets' ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Meta boxes                                                          */
	/* ------------------------------------------------------------------ */

	public static function meta_boxes() {
		add_meta_box( 'ssnip_code', __( 'Code', 'simple-snippets' ), array( __CLASS__, 'box_code' ), SSNIP_CPT, 'normal', 'high' );
		add_meta_box( 'ssnip_rules', __( 'Where to display', 'simple-snippets' ), array( __CLASS__, 'box_rules' ), SSNIP_CPT, 'normal', 'default' );
		add_meta_box( 'ssnip_settings', __( 'Snippet settings', 'simple-snippets' ), array( __CLASS__, 'box_settings' ), SSNIP_CPT, 'side', 'default' );
	}

	public static function box_code( $post ) {
		$s = SSnip_Snippet::get( $post->ID );
		wp_nonce_field( 'ssnip_save', 'ssnip_nonce' );
		?>
		<p class="description">
			<?php esc_html_e( 'HTML: printed as is. JavaScript: automatically wrapped in a <script> tag if you do not add one yourself.', 'simple-snippets' ); ?>
		</p>
		<textarea id="ssnip-code" name="ssnip_code" rows="18" class="widefat code" spellcheck="false"><?php echo esc_textarea( $s['code'] ); ?></textarea>
		<?php if ( ! current_user_can( 'unfiltered_html' ) ) : ?>
			<p class="notice notice-warning inline"><?php esc_html_e( 'Your account does not have the "unfiltered_html" capability, so code changes cannot be saved.', 'simple-snippets' ); ?></p>
		<?php endif; ?>
		<?php
	}

	public static function box_settings( $post ) {
		$s = SSnip_Snippet::get( $post->ID );
		?>
		<p><strong><?php esc_html_e( 'Code type', 'simple-snippets' ); ?></strong></p>
		<label class="ssnip-radio"><input type="radio" name="ssnip[type]" value="html" <?php checked( $s['type'], 'html' ); ?>> HTML</label>
		<label class="ssnip-radio"><input type="radio" name="ssnip[type]" value="js" <?php checked( $s['type'], 'js' ); ?>> JavaScript</label>

		<p><strong><?php esc_html_e( 'Location', 'simple-snippets' ); ?></strong></p>
		<label class="ssnip-radio"><input type="radio" name="ssnip[location]" value="head" <?php checked( $s['location'], 'head' ); ?>> <?php esc_html_e( 'Header', 'simple-snippets' ); ?> <code>&lt;head&gt;</code></label>
		<label class="ssnip-radio"><input type="radio" name="ssnip[location]" value="footer" <?php checked( $s['location'], 'footer' ); ?>> <?php esc_html_e( 'Footer', 'simple-snippets' ); ?> <code>wp_footer</code></label>

		<p><label for="ssnip-priority"><strong><?php esc_html_e( 'Order', 'simple-snippets' ); ?></strong></label></p>
		<input type="number" id="ssnip-priority" name="ssnip[priority]" value="<?php echo esc_attr( $s['priority'] ); ?>" min="0" max="999" class="small-text">
		<p class="description"><?php esc_html_e( 'Lower numbers are printed first.', 'simple-snippets' ); ?></p>

		<p class="description"><?php esc_html_e( 'Only published snippets are displayed. Save as draft to disable one.', 'simple-snippets' ); ?></p>
		<?php
	}

	public static function box_rules( $post ) {
		$s     = SSnip_Snippet::get( $post->ID );
		$types = SSnip_Snippet::targetable_post_types();
		?>
		<div class="ssnip-rules">
			<p>
				<label class="ssnip-radio"><input type="radio" name="ssnip[scope]" value="all" <?php checked( $s['scope'], 'all' ); ?>> <strong><?php esc_html_e( 'Entire site', 'simple-snippets' ); ?></strong></label>
				<label class="ssnip-radio"><input type="radio" name="ssnip[scope]" value="selected" <?php checked( $s['scope'], 'selected' ); ?>> <strong><?php esc_html_e( 'Only where I choose', 'simple-snippets' ); ?></strong></label>
			</p>

			<div class="ssnip-selected" <?php echo 'all' === $s['scope'] ? 'hidden' : ''; ?>>
				<h4><?php esc_html_e( 'All content of these post types', 'simple-snippets' ); ?></h4>
				<div class="ssnip-checks">
					<?php foreach ( $types as $name => $label ) : ?>
						<label>
							<input type="checkbox" name="ssnip[post_types][]" value="<?php echo esc_attr( $name ); ?>" <?php checked( in_array( $name, $s['post_types'], true ) ); ?>>
							<?php echo esc_html( $label ); ?> <code><?php echo esc_html( $name ); ?></code>
						</label>
					<?php endforeach; ?>
				</div>
				<label class="ssnip-inline">
					<input type="checkbox" name="ssnip[archives]" value="1" <?php checked( $s['archives'] ); ?>>
					<?php esc_html_e( 'Also on the archives of these post types (blog index, categories, tags, taxonomies…)', 'simple-snippets' ); ?>
				</label>
				<label class="ssnip-inline">
					<input type="checkbox" name="ssnip[front_page]" value="1" <?php checked( $s['front_page'] ); ?>>
					<?php esc_html_e( 'Also on the front page', 'simple-snippets' ); ?>
				</label>

				<h4><?php esc_html_e( 'Specific pages, posts or items', 'simple-snippets' ); ?></h4>
				<?php self::picker( 'items', $s['items'] ); ?>
			</div>

			<h4><?php esc_html_e( 'Never display on', 'simple-snippets' ); ?></h4>
			<?php self::picker( 'exclude', $s['exclude'] ); ?>
		</div>
		<?php
	}

	/**
	 * Searchable list of existing content, with chips for the chosen items.
	 */
	private static function picker( $field, array $ids ) {
		?>
		<div class="ssnip-picker" data-field="<?php echo esc_attr( $field ); ?>">
			<input type="search" class="ssnip-search regular-text" placeholder="<?php esc_attr_e( 'Type to search any page, post or item…', 'simple-snippets' ); ?>" autocomplete="off">
			<ul class="ssnip-results" hidden></ul>
			<ul class="ssnip-chips">
				<?php
				foreach ( $ids as $id ) :
					$p = get_post( $id );
					if ( ! $p ) {
						continue;
					}
					?>
					<li>
						<input type="hidden" name="ssnip[<?php echo esc_attr( $field ); ?>][]" value="<?php echo (int) $id; ?>">
						<?php echo esc_html( self::label( $p ) ); ?>
						<button type="button" class="ssnip-remove" aria-label="<?php esc_attr_e( 'Remove', 'simple-snippets' ); ?>">&times;</button>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	private static function label( WP_Post $p ) {
		$type  = get_post_type_object( $p->post_type );
		$title = '' !== $p->post_title ? $p->post_title : sprintf( '#%d', $p->ID );
		return sprintf( '%s (%s)', $title, $type ? $type->labels->singular_name : $p->post_type );
	}

	/* ------------------------------------------------------------------ */
	/* AJAX search                                                         */
	/* ------------------------------------------------------------------ */

	public static function ajax_search() {
		check_ajax_referer( 'ssnip_search', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		$term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		if ( strlen( $term ) < 2 && ! ctype_digit( $term ) ) {
			wp_send_json_success( array() );
		}

		$args = array(
			'post_type'      => array_keys( SSnip_Snippet::targetable_post_types() ),
			'post_status'    => array( 'publish', 'private', 'future', 'draft' ),
			'posts_per_page' => 20,
			'no_found_rows'  => true,
		);
		if ( ctype_digit( $term ) ) {
			$args['p'] = (int) $term;
		} else {
			$args['s'] = $term;
		}

		$out = array();
		foreach ( get_posts( $args ) as $p ) {
			$out[] = array(
				'id'    => $p->ID,
				'label' => self::label( $p ),
			);
		}
		wp_send_json_success( $out );
	}

	/* ------------------------------------------------------------------ */
	/* Save                                                                */
	/* ------------------------------------------------------------------ */

	public static function save( $post_id, $post ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['ssnip_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['ssnip_nonce'] ) ), 'ssnip_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$raw = isset( $_POST['ssnip'] ) && is_array( $_POST['ssnip'] ) ? wp_unslash( $_POST['ssnip'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		update_post_meta( $post_id, '_ssnip_settings', SSnip_Snippet::sanitize( $raw ) );

		// Raw code is stored untouched, but only for users allowed to post unfiltered HTML.
		if ( isset( $_POST['ssnip_code'] ) && current_user_can( 'unfiltered_html' ) ) {
			// wp_slash() because update_post_meta() unslashes its value.
			update_post_meta( $post_id, '_ssnip_code', wp_slash( wp_unslash( $_POST['ssnip_code'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		delete_transient( SSnip_Frontend::CACHE_KEY );
	}

	public static function notices() {
		$screen = get_current_screen();
		if ( $screen && 'edit-' . SSNIP_CPT === $screen->id && ! current_user_can( 'unfiltered_html' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Snippet code can only be edited by users with the "unfiltered_html" capability (on multisite: super admins).', 'simple-snippets' ) . '</p></div>';
		}
	}

	/* ------------------------------------------------------------------ */
	/* List table                                                          */
	/* ------------------------------------------------------------------ */

	public static function columns( $cols ) {
		$date = $cols['date'];
		unset( $cols['date'] );
		$cols['ssnip_type']     = __( 'Type', 'simple-snippets' );
		$cols['ssnip_location'] = __( 'Location', 'simple-snippets' );
		$cols['ssnip_where']    = __( 'Displayed on', 'simple-snippets' );
		$cols['date']           = $date;
		return $cols;
	}

	public static function column_content( $col, $post_id ) {
		$s = SSnip_Snippet::get( $post_id );

		switch ( $col ) {
			case 'ssnip_type':
				echo 'js' === $s['type'] ? 'JavaScript' : 'HTML';
				break;

			case 'ssnip_location':
				echo 'head' === $s['location'] ? '<code>&lt;head&gt;</code>' : esc_html__( 'Footer', 'simple-snippets' );
				break;

			case 'ssnip_where':
				$parts = array();
				if ( 'all' === $s['scope'] ) {
					$parts[] = esc_html__( 'Entire site', 'simple-snippets' );
				} else {
					$types = SSnip_Snippet::targetable_post_types();
					foreach ( $s['post_types'] as $pt ) {
						$parts[] = esc_html( isset( $types[ $pt ] ) ? $types[ $pt ] : $pt ) . ( $s['archives'] ? ' + ' . esc_html__( 'archives', 'simple-snippets' ) : '' );
					}
					if ( $s['front_page'] ) {
						$parts[] = esc_html__( 'Front page', 'simple-snippets' );
					}
					if ( $s['items'] ) {
						/* translators: %d: number of selected items */
						$parts[] = esc_html( sprintf( _n( '%d selected item', '%d selected items', count( $s['items'] ), 'simple-snippets' ), count( $s['items'] ) ) );
					}
					if ( ! $parts ) {
						$parts[] = '<em>' . esc_html__( 'Nowhere (no rule set)', 'simple-snippets' ) . '</em>';
					}
				}
				if ( $s['exclude'] ) {
					/* translators: %d: number of excluded items */
					$parts[] = esc_html( sprintf( __( 'except %d', 'simple-snippets' ), count( $s['exclude'] ) ) );
				}
				echo implode( ', ', $parts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- parts escaped above.
				break;
		}
	}

	public static function messages( $messages ) {
		$messages[ SSNIP_CPT ] = array(
			1  => __( 'Snippet updated.', 'simple-snippets' ),
			4  => __( 'Snippet updated.', 'simple-snippets' ),
			6  => __( 'Snippet published and active.', 'simple-snippets' ),
			7  => __( 'Snippet saved.', 'simple-snippets' ),
			10 => __( 'Snippet draft saved (inactive).', 'simple-snippets' ),
		);
		return $messages;
	}

	public static function title_placeholder( $text, $post ) {
		return SSNIP_CPT === $post->post_type ? __( 'Snippet name (e.g. Google Analytics)', 'simple-snippets' ) : $text;
	}
}
