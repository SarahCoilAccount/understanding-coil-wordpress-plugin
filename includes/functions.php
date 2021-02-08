<?php
declare(strict_types=1);
/**
 * Coil loader file.
 * Sets up action and filter hooks for almost all the files
 * Loads JS and CSS scripts and stylesheets
 * Gets the payment pointer
 */

namespace Coil;

// This is referring to namespacing not paths
// It can be useful to give a namespace or class an alias to make it easier to write. This is done with the use keyword.
// So that when you use Admin it will actually be using Coil\Admin
use \Coil\Admin;
use \Coil\Gating;
use \Coil\User;

/**
 * @var string Plugin version number.
 */
const PLUGIN_VERSION = '1.7.0';

/**
 * @var string Plugin root folder.
 */
const COIL__FILE__ = __DIR__;

/**
 * Initialise and set up the plugin.
 *
 * @return void
 */
function init_plugin() : void {

	// CSS/JS.
	// In the function call you supply, simply use wp_enqueue_script and wp_enqueue_style to add your functionality to the Gutenberg editor. run under both the editor and front-end contexts. used to enqueue stylesheets common to the front-end and the editor.
	add_action( 'enqueue_block_assets', __NAMESPACE__ . '\load_block_frontend_assets' );
	// In the function call you supply, simply use wp_enqueue_script and wp_enqueue_style to add your functionality to the block editor. Only applicable for the Gutenberg editor. run in the Gutenberg editor context when the editor is ready to receive additional scripts and stylesheets.
	add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\load_block_editor_assets' );
	// wp_enqueue_scripts is the proper hook to use when enqueuing scripts and styles that are meant to appear on the front end. Despite the name, it is used for enqueuing both scripts and styles. It doesn't add style to the plugin admin. 
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\load_full_assets' );
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\load_messaging_assets' );

	// Admin-only CSS/JS.
	// admin_enqueue_scripts loads style to front end AND toall *admin* pages (including the the plugin admin)
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\Admin\load_admin_assets' );
	// admin_body_class - Filters the CSS classes for the body tag in the admin.
	add_filter( 'admin_body_class', __NAMESPACE__ . '\Admin\add_admin_body_class' );

	// Modify output.
	// Filters the list of CSS body class names for the current post or page. Updates the $classes variable which is a parameter to the body_class function. 
	add_filter( 'body_class', __NAMESPACE__ . '\add_body_class' );
	// the_content - Filters the post content.
	add_filter( 'the_content', __NAMESPACE__ . '\Gating\maybe_restrict_content' );
	// the_title - Filters the post title.
	add_filter( 'the_title', __NAMESPACE__ . '\Gating\maybe_add_padlock_to_title', 10, 2 );
	// Adds monetiation tag to html head
	add_action( 'wp_head', __NAMESPACE__ . '\print_meta_tag' );

	// Admin screens and settings.
	// How does a page count as a filter?? Where is this page?
	add_filter( 'plugin_action_links_coil-web-monetization/plugin.php', __NAMESPACE__ . '\Admin\add_plugin_action_links' );
	// plugin_row_meta - Filters the array of row meta for each plugin in the Plugins list table.
	add_filter( 'plugin_row_meta', __NAMESPACE__ . '\Admin\add_plugin_meta_link', 10, 2 );
	// The admin_menu hook is used to call a function that calls the add_menu_page WordPress function which creates the plugin settings page - when your plugin becomes a menu option on the WordPress admin page
	add_action( 'admin_menu', __NAMESPACE__ . '\Settings\register_admin_menu' );
	add_action( 'admin_init', __NAMESPACE__ . '\Settings\register_admin_content_settings' );
	add_action( 'admin_notices', __NAMESPACE__ . '\Settings\admin_welcome_notice' );
	add_action( 'wp_ajax_dismiss_welcome_notice', __NAMESPACE__ . '\Settings\dismiss_welcome_notice' );

	// Term meta.
	add_action( 'edit_term', __NAMESPACE__ . '\Admin\maybe_save_term_meta' );
	add_action( 'create_term', __NAMESPACE__ . '\Admin\maybe_save_term_meta' );
	add_action( 'delete_term', __NAMESPACE__ . '\Admin\delete_term_monetization_meta' );
	add_term_edit_save_form_meta_actions();

	// Customizer settings.
	// Contains the default message content and allows admin users to change this content in the customization menu
	// The ‘customize_register‘ action hook is used to customize and manipulate the Theme Customization admin screen 
	// This hook gives you access to the $wp_customize object, which is an instance of the WP_Customize_Manager class. 
	// It is this class object that controls the Theme Customizer screen - add_setting, add_section_add_control and set_setting
	add_action( 'customize_register', __NAMESPACE__ . '\Admin\add_customizer_messaging_panel' );
	add_action( 'customize_register', __NAMESPACE__ . '\Admin\add_customizer_options_panel' );
	add_action( 'customize_register', __NAMESPACE__ . '\Admin\add_customizer_learn_more_button_settings_panel' );

	// User profile settings - pretty much sums up the entire user/functions.php file
	add_action( 'personal_options', __NAMESPACE__ . '\User\add_user_profile_payment_pointer_option' );
	add_action( 'personal_options_update', __NAMESPACE__ . '\User\maybe_save_user_profile_payment_pointer_option' );
	add_action( 'edit_user_profile_update', __NAMESPACE__ . '\User\maybe_save_user_profile_payment_pointer_option' );
	add_filter( 'option_coil_payment_pointer_id', __NAMESPACE__ . '\User\maybe_output_user_payment_pointer' );

	// Metaboxes.
	// I can't even find this load-post.php
	// Seems like load-post.php is a Wordpres thing Runs when an administration menu page is loaded. 
	// Administration Menus are the interfaces displayed in WordPress Administration. They allow you to add option pages for your plugin. The Top-level menus are rendered along the left side of the WordPress Administration.
	add_action( 'load-post.php', __NAMESPACE__ . '\Admin\load_metaboxes' );
	add_action( 'load-post-new.php', __NAMESPACE__ . '\Admin\load_metaboxes' );
	// Called in admin/functions.php 
	add_action( 'save_post', __NAMESPACE__ . '\Admin\maybe_save_post_metabox' );

	// Modal messaging
	add_action( 'wp_footer', __NAMESPACE__ . '\load_plugin_templates' );

	// Load order - important.
	// Fires after WordPress has finished loading but before any headers are sent.
	add_action( 'init', __NAMESPACE__ . '\Gating\register_content_meta' );
	add_action( 'init', __NAMESPACE__ . '\Gating\register_term_meta' );
}

/**
 * Enqueue block frontend assets.
 * Enqueue a CSS stylesheet.
 * Registers the style if source provided (does NOT overwrite) and enqueues.
 *
 * @return void
 */
function load_block_frontend_assets() : void {

	// defined — Checks whether a given named constant exists
	// SCRIPT_DEBUG is a related constant that will force WordPress to use the “dev” versions of core CSS and JavaScript files rather than the minified versions that are normally loaded. 
	$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

	// Rather then loading the stylesheet in your header.php file, you should load it in using wp_enqueue_style. In order to load your main stylesheet, you can enqueue it in functions.php
	wp_enqueue_style(
		'coil-blocks', // handle - Name of the stylesheet. Should be unique.
		esc_url_raw( plugin_dir_url( __DIR__ ) . 'dist/blocks.style.build' . $suffix . '.css' ), // URL of stylesheet
		[], //$deps - An array of registered stylesheet handles this stylesheet depends on.
		PLUGIN_VERSION // const defined above
	);
}

/**
 * Enqueue block editor assets.
 *
 * @return void
 */
function load_block_editor_assets() : void {

	// Need to be admin
	if ( ! is_admin() ) {
		return;
	}

	// Current page isn't dealing custom posts - insertings, trashing, categorising, images, slugs, attachments, post meta etc. I think this is referring to the post page for editing posts in the admin page.
	if ( $GLOBALS['pagenow'] !== 'post.php' && $GLOBALS['pagenow'] !== 'post-new.php' ) {
		return;
	}

	// SCRIPT_DEBUG is a related constant that will force WordPress to use the “dev” versions of core CSS and JavaScript files rather than the minified versions that are normally loaded. This is useful when you are testing modifications to any built-in .js or .css files. Default is false.
	$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

	wp_enqueue_style(
		'coil-editor-css', // Different handle from above - these two must occur under different circumstances - this one seems to be more editor based - hence the requirement to be an admin
		esc_url_raw( plugin_dir_url( __DIR__ ) . 'dist/blocks.editor.build' . $suffix . '.css' ),
		[],
		PLUGIN_VERSION
	);

	// Scripts.
	// Uses wp_enque_style and script, enque_block_assets only includes the style function. 
	// Any additional JavaScript files required 
	wp_enqueue_script(
		'coil-editor',
		esc_url_raw( plugin_dir_url( __DIR__ ) . 'dist/blocks.build.js' ),
		// $deps is an array that can handle any script that your new script depends on, such as jQuery.
		[ 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-plugins', 'wp-components', 'wp-edit-post', 'wp-api', 'wp-editor', 'wp-hooks', 'wp-data' ],
		PLUGIN_VERSION,
		// $in_footer is a boolean parameter (true/false) that allows you to place your scripts in the footer of your HTML document rather then in the header, so that it does not delay the loading of the DOM tree.
		false // Why would it be beneficial to have the script load in the header??
	);

	// Load JS i18n, requires WP 5.0+.
	if ( ! function_exists( 'wp_set_script_translations' ) ) {
		return;
	}

	// Takes handle and domain as arguments
	// Sets translated strings for a script.
	// Not set unless the script has already been added - script was presumably set just above this function.
	wp_set_script_translations( 'coil-editor', 'coil-web-monetization' );
}

/**
 * Enqueue required CSS/JS.
 *
 * @return void
 */
function load_full_assets() : void {

	// Only load Coil on actual content. ?? Why would this not apply to the home page etc?
	if ( is_admin() || is_home() || is_front_page() || ! is_singular() || is_feed() || is_preview() ) {
		return;
	}

	// What is the queried object here? When was it queried/ Where was it quried? 
	if ( ! Gating\is_content_monetized( get_queried_object_id() ) ) {
		return;
	}

	$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

	wp_enqueue_style(
		'coil-monetize-css',
		esc_url_raw( plugin_dir_url( __DIR__ ) . 'assets/css/frontend/coil' . $suffix . '.css' ),
		[],
		PLUGIN_VERSION
	);

	wp_enqueue_script(
		'coil-monetization-js',
		esc_url_raw( plugin_dir_url( __DIR__ ) . 'assets/js/initialize-monetization' . $suffix . '.js' ),
		[ 'jquery', 'wp-util' ],
		PLUGIN_VERSION,
		true
	);

	$site_logo = false;
	if ( function_exists( 'get_custom_logo' ) ) {
		$site_logo = get_custom_logo();
	}

	// these are defined in admin/function.php - some can be customized
	$strings = apply_filters(
		'coil_js_ui_messages',
		[
			'content_container'         => Admin\get_global_settings( 'coil_content_container' ),
			'browser_extension_missing' => Admin\get_customizer_text_field( 'coil_unsupported_message' ),
			'unable_to_verify'          => Admin\get_customizer_text_field( 'coil_unable_to_verify_message' ),
			'voluntary_donation'        => Admin\get_customizer_text_field( 'coil_voluntary_donation_message' ),
			'loading_content'           => Admin\get_customizer_text_field( 'coil_verifying_status_message' ),
			'partial_gating'            => Admin\get_customizer_text_field( 'coil_partial_gating_message' ),
			/**!! coil_fully_gated_excerpt_message and coil_partially_gated_excerpt_message seem to be missing here ?? */
			'learn_more_button_text'    => Admin\get_customizer_text_field( 'coil_learn_more_button_text' ),
			'learn_more_button_link'    => Admin\get_customizer_text_field( 'coil_learn_more_button_link' ),
			'full_gating_header'    	=> Admin\get_customizer_text_field('coil_fully_gated_content_heading'),
			'full_gating_footer'    	=> Admin\get_customizer_text_field('coil_fully_gated_content_footer'),
			/**Where does this donation bar appear?? */
			'show_donation_bar'         => get_theme_mod( 'coil_show_donation_bar' ),
			'post_excerpt'              => get_the_excerpt(),
			'site_logo'                 => $site_logo,

			/* translators: 1 + 2) HTML link tags (to the Coil settings page). */
			'admin_missing_id_notice'   => sprintf( __( 'This post is monetized but you have not set your payment pointer ID in the %1$sCoil settings page%2$s. Only content set to show for all visitors will show.', 'coil-web-monetization' ), '<a href="' . admin_url( 'admin.php?page=coil' ) . '">', '</a>' ),
		],
		get_queried_object_id()
	);

	wp_localize_script(
		'coil-monetization-js',
		'coil_params',
		$strings
	);
}

/**
 * Enqueue messaging CSS.
 *
 * @return void
 */
function load_messaging_assets() : void {

	if ( is_admin() ) {
		return;
	}

	$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

	wp_enqueue_style(
		'coil-messaging',
		esc_url_raw( plugin_dir_url( __DIR__ ) . 'assets/css/messages/coil' . $suffix . '.css' ),
		[],
		PLUGIN_VERSION
	);

	// Only load Coil cookie message styles on singular posts.
	// Singular - Determines whether the query is for an existing single post of any post type (post, attachment, page, custom post types).
	if ( is_home() || is_front_page() || ! is_singular() || is_feed() || is_preview() ) {
		return;
	}

	wp_enqueue_script(
		'messaging-cookies',
		esc_url_raw( plugin_dir_url( __DIR__ ) . 'assets/js/js-cookie.3.0.0.min.js' ),
		[],
		PLUGIN_VERSION,
		true
	);
}

/**
 * Load templates used by the plugin to render in javascript using
 * WP Template.
 *
 * @return void
 */
function load_plugin_templates() : void {
	// I believe __FILE__ retrievs the path up to the coil-web-monetization folder
	// __FILE__ returns value relative to where it is called which is why it is only called inside this file. And once in plugins.php to deactivate the plugin. 
	// Brings in the messages from templates folder. 
	require_once plugin_dir_path( __FILE__ ) . '../templates/messages/subscriber-only-message.php';
	require_once plugin_dir_path( __FILE__ ) . '../templates/messages/split-content-message.php';
	require_once plugin_dir_path( __FILE__ ) . '../templates/messages/banner-message.php';
}

/**
 * Add body class if content has enabled monetization.
 *
 * @param array $classes Initial body classes.
 *
 * @return array $classes Updated body classes.
 */
// Manipulating the $classes variable which passed to this callback function from the body_class filter. 
function add_body_class( $classes ) : array {

	// is_singular - Determines whether the query is for an existing single post of any post type
	if ( ! is_singular() ) {
		return $classes;
	}

	// get_global_settings is a function defiened in admin/functions.php
	$payment_pointer_id = Admin\get_global_settings( 'coil_payment_pointer_id' );

	if ( Gating\is_content_monetized( get_queried_object_id() ) ) {
		$classes[] = 'monetization-not-initialized';

		$coil_status = Gating\get_content_gating( get_queried_object_id() );
		$classes[]   = sanitize_html_class( 'coil-' . $coil_status );

		if ( ! empty( $payment_pointer_id ) ) {
			$classes[] = ( Gating\get_excerpt_gating( get_queried_object_id() ) ) ? 'coil-show-excerpt' : 'coil-hide-excerpt';
		} else {
			// Error: payment pointer ID is missing.
			$classes[] = 'coil-missing-id';

			// If current user is an admin,toggle error message in wp-admin.
			if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
				$classes[] = 'coil-show-admin-notice';
			}
		}
	}

	return $classes;
}

/**
 * Print the monetisation tag to <head>.
 *
 * @return void
 */
function print_meta_tag() : void {

	$payment_pointer_id  = get_payment_pointer();
	$payment_pointer_url = $payment_pointer_id;

	// check if url starts with $
	// Got an error for being unitialised so I added an if condition to check if the variable has any content in it !!
	if ( '' !== $payment_pointer_url && $payment_pointer_url[0] === '$' ) { /**Changelog ALA added condition  '' !== $payment_pointer_url &&  */
		// replace $ with https://
		$payment_pointer_url = str_replace( '$', 'https://', $payment_pointer_url );
		// remove trailing slash
		$payment_pointer_url = rtrim( $payment_pointer_url, '/' );
		// check if url path exists
		$parsed_url = wp_parse_url( $payment_pointer_url, PHP_URL_PATH );

		// if no url path, append /.well-known/pay
		if ( empty( $parsed_url ) ) {
			$payment_pointer_url = $payment_pointer_url . '/.well-known/pay';
		}
	}

	if ( ! empty( $payment_pointer_id ) ) {
		echo '<meta name="monetization" content="' . esc_attr( $payment_pointer_id ) . '" />' . PHP_EOL;
		echo '<link rel="monetization" href="' . esc_url( $payment_pointer_url ) . '" />' . PHP_EOL;
	}
}

/**
 * Get the filterable payment pointer meta option from the database.
 *
 * @return string
 */
function get_payment_pointer() : string {

	// Fetch the global payment pointer
	$global_payment_pointer_id = Admin\get_global_settings( 'coil_payment_pointer_id' );

	// If payment pointer is set on the user, use that instead of the global payment pointer.
	$payment_pointer_id = User\maybe_output_user_payment_pointer( $global_payment_pointer_id );

	// If the post is not set for monetising, bail out. 
	if ( ! Gating\is_content_monetized( get_queried_object_id() ) || empty( $payment_pointer_id ) ) { /**Changelog ALA removed (int) casting from get_queried_object_id() But note that the function already retrieves the ID of the currently queried object and returns it as an integer. Not sure then why (int) would do any harm?? */
		return '';
	}

	return $payment_pointer_id;
}

/**
 * Generate actions for every taxonomy to handle the output
 * of the gating options for the term add/edit forms.
 *
 * @return array $actions Array of WordPress actions.
 */
function add_term_edit_save_form_meta_actions() {

	$valid_taxonomies = Admin\get_valid_taxonomies();

	$actions = [];
	if ( is_array( $valid_taxonomies ) && ! empty( $valid_taxonomies ) ) {
		foreach ( $valid_taxonomies as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				$actions[] = add_action( esc_attr( $taxonomy ) . '_edit_form_fields', __NAMESPACE__ . '\Settings\coil_add_term_custom_meta', 10, 2 );
				$actions[] = add_action( esc_attr( $taxonomy ) . '_add_form_fields', __NAMESPACE__ . '\Settings\coil_edit_term_custom_meta', 10, 2 );
			}
		}
	}
	return $actions;
}

/**
 * Return post types to integrate with Coil.
 *
 * @param string $output Optional. The type of output to return. Either 'names' or 'objects'. Default 'names'.
 *
 * @return array Supported post types (if $output is 'names', then strings, otherwise WP_Post objects).
 */
function get_supported_post_types( $output = 'names' ) : array {

	$output        = ( $output === 'names' ) ? 'names' : 'objects';
	$content_types = get_post_types(
		[ 'public' => true ],
		$output
	);

	$excluded_types = [
		'attachment',
		'custom_css',
		'customize_changeset',
		'revision',
		'nav_menu_item',
		'oembed_cache',
		'user_request',
		'wp_block',
	];

	$supported_types = [];

	foreach ( $content_types as $post_type ) {
		$type_name = ( $output === 'names' ) ? $post_type : $post_type->name;

		if ( ! in_array( $type_name, $excluded_types, true ) ) {
			$supported_types[] = $post_type;
		}
	}

	return apply_filters( 'coil_supported_post_types', $supported_types, $output );
}
