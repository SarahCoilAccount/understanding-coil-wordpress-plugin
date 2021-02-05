<?php
/**
 * Plugin Name: Coil Web Monetization
 * Plugin URI: https://wordpress.org/plugins/coil-web-monetization/
 * Description: Coil offers an effortless way to share WordPress content online, and get paid for it.
 * Author: Coil
 * Author URI: https://coil.com
 * Version: 1.7.0
 * License: Apache-2.0
 * License URI: http://www.apache.org/licenses/LICENSE-2.0.txt
 * Text Domain: coil-web-monetization
 */

// ABSPATH is the absolute path to the WordPress directory - takes you to the root of you wordpress installation 
// The root is /home/sarahjanejones/Local Sites/demosite/app/public
// Exit if accessed directly. Prevents access to your files.
// ABSPATH is a constant 
// || is like or but if LHS returns true then the code on RHS is not run. 
defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '7.1', '<' ) ) {
	/**
	 * Show warning message to sites on old versions of PHP.
	 */
	function coil_show_php_warning() {
		echo '<div class="error"><p>' . esc_html__( 'Coil Web Monetization requires PHP 7.1 or newer. Please contact your web host for information on updating PHP.', 'coil-web-monetization' ) . '</p></div>';
		unset( $_GET['activate'] );
	}

	/**
	 * Deactivate the plugin.
	 */
	function coil_deactive_self() {
			deactivate_plugins( plugin_basename( __FILE__ ) );
	}

	add_action( 'admin_notices', 'coil_show_php_warning' );
	add_action( 'admin_init', 'coil_deactive_self' );

	return;
}

// The require_once keyword is used to embed PHP code from another file. If the file is not found, a fatal error is thrown and the program stops. If the file was already included previously, this statement will not include it again.
require_once __DIR__ . '/includes/admin/functions.php';
require_once __DIR__ . '/includes/settings/functions.php';
require_once __DIR__ . '/includes/gating/functions.php';
require_once __DIR__ . '/includes/user/functions.php';
require_once __DIR__ . '/includes/functions.php';

add_action( 'plugins_loaded', 'Coil\init_plugin' ); // Found in the functions.php file
