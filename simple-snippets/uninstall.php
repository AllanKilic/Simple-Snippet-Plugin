<?php
/**
 * Removes all snippets when the plugin is deleted from the Plugins screen.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$ssnip_ids = get_posts(
	array(
		'post_type'      => 'ssnip_snippet',
		'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'auto-draft' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $ssnip_ids as $ssnip_id ) {
	wp_delete_post( $ssnip_id, true );
}

delete_transient( 'ssnip_active_snippets' );
