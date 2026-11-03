<?php
/**
 * Plugin Name:       Simple Snippets
 * Description:       Add HTML and JavaScript snippets to the frontend, in the &lt;head&gt; or the footer, on the pages, posts or post types you choose.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Your Name
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-snippets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SSNIP_VERSION', '1.0.0' );
define( 'SSNIP_FILE', __FILE__ );
define( 'SSNIP_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSNIP_URL', plugin_dir_url( __FILE__ ) );
define( 'SSNIP_CPT', 'ssnip_snippet' );

require_once SSNIP_DIR . 'includes/class-ssnip-snippet.php';
require_once SSNIP_DIR . 'includes/class-ssnip-frontend.php';

if ( is_admin() ) {
	require_once SSNIP_DIR . 'includes/class-ssnip-admin.php';
}

add_action( 'init', array( 'SSnip_Snippet', 'register_post_type' ) );
add_action( 'plugins_loaded', array( 'SSnip_Frontend', 'init' ) );

if ( is_admin() ) {
	add_action( 'plugins_loaded', array( 'SSnip_Admin', 'init' ) );
}
