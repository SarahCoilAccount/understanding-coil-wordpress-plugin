<?php
declare(strict_types=1);
/**
 * Coil gating.
 * Contains function to get the appropriate gating setting
 */

namespace Coil\Gating;

use Coil\Admin;

/**
 * Register post/user meta.
 */
// coil_monetize_post_statuscoil_monetize_post_status saves gating information stored in each post
// Every field has a "unique meta key" setting that must be filled. This is automatically created for you when you create the field.
// Must be unique - the name is wpum_ followed by the ID
 // The meta key determines how the field will be stored into the database of your website.
// The metakey is used to retrieve the saved value from the database and display it.
// Used to save this metadata key and value to a databse like MySQL. The meta value is the  is the gating for that post.
// Called using the init hook
function register_content_meta() : void {

	// Registers a meta key
	register_meta(
		'post', // object type
		'_coil_monetize_post_status', // Meta key to register. Used to get, update, save and delete meta. As well as for display purposes in the HTML
		[ // args data used to describe the meta key
			'auth_callback' => function() {
				// A function or method to call when performing edit_post_meta, add_post_meta, and delete_post_meta capability checks.
				return current_user_can( 'edit_posts' );
			},
			'show_in_rest'  => true, // Whether data associated with this meta key can be considered public and should be accessible via the REST API. A custom post type must also declare support for custom fields for registered meta to be accessible via REST.
			'single'        => true, // meta key has one value per object
			'type'          => 'string', // The type of data associated with this meta key
		]
	);
} // returns true if the meta key was successfully registered in the global array

/**
 * Register term meta.
 *
 * @return void
 */

 // Registers a meta key for terms - stores gating information assigned to categories and tags.
 // Terms are part of custom taxonomies which also include tags and categories. Basically an umbrella term that encompasses categories and taxonomies. It includes a term_id, the taxonomy (e.g. category), if there is a parent (like one category can have children categories) the name of it, the slug etc.
 // The names for the different groupings in a taxonomy are called terms. In your database you get a number of tables relating to terms and taxonomy. They are, wp_term, wp_term_relationships, wp_term_taxonomy.
 // Called using the init hook added in admin/functions.php
function register_term_meta() {
	register_meta(
		'term', // Taxonomy to register a meta key for. Pass an empty string to register the meta key across all existing taxonomies.
		'_coil_monetize_term_status', // The meta key to register. Used to get, update, save and delete term meta. As well as for display purposes in the HTML
		[
			'auth_callback' => function() {
				// A function or method to call when performing edit_post_meta, add_post_meta, and delete_post_meta capability checks.
				return current_user_can( 'edit_posts' );
			},
			'show_in_rest'  => true, // Whether data associated with this meta key can be considered public and should be accessible via the REST API. A custom post type must also declare support for custom fields for registered meta to be accessible via REST.
			'single'        => true, // meta key has one value per object
			'type'          => 'string', // The type of data associated with this meta key
		]
	);
}

/**
 * Store the monetization options.
 * Called in Admin/functions.php
 * @param bool $show_default Whether or not to show the default option.
 * @return array
 */
function get_monetization_setting_types( $show_default = false ) : array {

	if ( true === $show_default ) {
		$settings['default'] = esc_html__( 'Use Default', 'coil-web-monetization' );
	}

	$settings['no']        = esc_html__( 'No Monetization', 'coil-web-monetization' );
	$settings['no-gating'] = esc_html__( 'Monetized and Public', 'coil-web-monetization' );
	$settings['gate-all']  = esc_html__( 'Coil Members Only', 'coil-web-monetization' );
	// Doesn't include the split content option here. Mentioned in admin/functions - only added to the array if the Gutenberg editor is being used.
	return $settings;
}

/**
 * Declare all the valid gating slugs used as a reference
 * before the particular option is saved in the database.
 *
 * @return array An array of valid gating slug types.
 */
function get_valid_gating_types() {
	$valid = [
		'gate-all', // Coil members only.
		'gate-tagged-blocks', // split content.
		'no', // no monetization.
		'no-gating', // monetixed and public.
		'default', // whatever is set on the post to revert back.
	];
	return $valid;
}

/**
 * Maybe prefix a padlock emoji to the post title.
 *
 * Used on archive pages to represent if a post is gated.
 *
 * @param string $title The post title.
 * @param int    $id    The post ID.
 *
 * @return string The updated post title.
 */

 // Called by a filter hook - the_title
function maybe_add_padlock_to_title( string $title, int $id ) : string {

	// If the theme isn't coil_title_padlock just return the title as is
	if ( ! get_theme_mod( 'coil_title_padlock', true ) ) {
		return $title;
	}

	// Don't add the padlock if the gating is neither gate_all nor gated_tagged_blocks
	$status = get_content_gating( $id );
	if ( $status !== 'gate-all' && $status !== 'gate-tagged-blocks' ) {
		return $title;
	}

	// !! This where we need to change the title being being padlocked if any post is padlocked if we so wish
	// sprintf_ returns a formatted string returning the padlock next the title
	$post_title = sprintf(
		/* translators: %s: Gated post title. */
		__( '🔒 %s', 'coil-web-monetization' ),
		$title
	);

	// apply_filters is a WordPress function taking the tag and value as parameters
	// It calls the callback functions that have been added to a filter hook - in this case the_title
	// The apply_filters function craetes a new filter hook when
	// The filter hook is coil_maybe_add_padlock_to_title, the value to be filetred is #post_title and $title and $id are adidtional args passed to the callback function
	// I don't understand why this isn't simply return $title??
	return apply_filters( 'coil_maybe_add_padlock_to_title', $post_title, $title, $id );
}

/**
 * Maybe restrict (gate) visibility of the post content on archive pages, home pages, and feeds.
 *
 * @param string $content Post content.
 *
 * @return string $content Updated post content.
 */
function maybe_restrict_content( string $content ) : string {

	// Plugins can call the `the_content` filter outside of the post loop.
	if ( is_singular() || ! get_the_ID() ) {
		return $content;
	}

	$coil_status     = get_content_gating( get_the_ID() );
	$post_obj        = get_post( get_the_ID() );
	$content_excerpt = $post_obj->post_excerpt;
	$public_content  = '';
	$cta_button_html = sprintf(
		'<p><a href="%1$s" class="coil-serverside-message-button">%2$s</a></p>',
		esc_url( Admin\get_customizer_text_field( 'coil_learn_more_button_link' ) ),
		esc_html( Admin\get_customizer_text_field( 'coil_learn_more_button_text' ) )
	);

	switch ( $coil_status ) {
		case 'gate-all':
			// Restrict all content (Coil members only).
			if ( get_excerpt_gating( get_queried_object_id() ) ) {
				$public_content .= $content_excerpt;
			}

			$full_gated_message = Admin\get_customizer_text_field( 'coil_fully_gated_excerpt_message' );

			$public_content .= '<p>' . esc_html( $full_gated_message ) . '</p>';
			$public_content .= $cta_button_html;
			break;

		case 'gate-tagged-blocks':
			// Restrict some part of this content (split content).
			if ( get_excerpt_gating( get_queried_object_id() ) ) {
				$public_content .= $content_excerpt;
			}

			$partially_gated_message = Admin\get_customizer_text_field( 'coil_partially_gated_excerpt_message' );

			$public_content .= '<p>' . esc_html( $partially_gated_message ) . '</p>';
			$public_content .= $cta_button_html;
			break;

		/**
		 * case 'default': doesn't exist in this context because the last possible
		 * saved option will be 'no', after a post has fallen back to the taxonomy
		 * and then the default post options.
		 */
		case 'no':
		case 'no-gate':
		default:
			$public_content = $content;
			break;
	}

	return apply_filters( 'coil_maybe_restrict_content', $public_content, $content, $coil_status );
}

/**
 * Get the gating type for the specified post.
 * Called from admin/functions.php as well as inside this file
 * @param integer $post_id The post to check.
 *
 * @return string Either "no" (default), "no", "no-gating", "gate-all", "gate-tagged-blocks".
 */
function get_post_gating( int $post_id ) : string {

	$gating = get_post_meta( $post_id, '_coil_monetize_post_status', true ); // Can get monetization status for any post

	if ( empty( $gating ) ) {
		$gating = 'default';
	}

	return $gating;
}

/**
 * Get the value of the "Display Excerpt" setting for this post .
 *
 * @param integer $post_id The post to check.
 * @return bool true show excerpt, false hide excerpt.
 */
function get_excerpt_gating( int $post_id ) : bool {
	$post_type = get_post_type( $post_id );

	$display_excerpt  = false;
	$excerpt_settings = get_global_excerpt_settings();
	if ( ! empty( $excerpt_settings ) && isset( $excerpt_settings[ $post_type ] ) ) {
		$display_excerpt = $excerpt_settings[ $post_type ];
	}
	return $display_excerpt;
}


/**
 * Get the gating type for the specified term.
 *
 * @param integer $term_id The term_id to check.
 *
 * @return string Either "default" (default), "no", "no-gating", "gate-all".
 */
function get_term_gating( $term_id ) {

	// Returns the gating assigned to this category/tag/etc
	$term_gating = get_term_meta( $term_id, '_coil_monetize_term_status', true );

	if ( empty( $term_gating ) ) {
		$term_gating = 'default';
	}
	return $term_gating;
}

/**
 * Get any terms attached to the post and return their gating status,
 * ranked by priority order.
 *
 * @return string Gating type.
 */
function get_taxonomy_term_gating( $post_id ) {

	$term_default = 'default';

	$valid_taxonomies = Admin\get_valid_taxonomies();

	// 1) Get any terms assigned to the post.
	$post_terms = wp_get_post_terms(
		$post_id,
		$valid_taxonomies,
		[
			'fields' => 'ids',
		]
	);

	// 2) Do these terms have gating?
	$term_gating_options = [];
	if ( ! is_wp_error( $post_terms ) && ! empty( $post_terms ) ) {

		foreach ( $post_terms as $term_id ) {

			$post_term_gating = get_term_gating( $term_id );
			if ( ! in_array( $post_term_gating, $term_gating_options, true ) ) {
				$term_gating_options[] = $post_term_gating;
			}
		}
	}

	if ( empty( $term_gating_options ) ) {
		return $term_default;
	}

	// 3) If terms have gating, rank by priority.
	if ( in_array( 'gate-all', $term_gating_options, true ) ) {

		// Priority 1 - Monetized Member Only.
		return 'gate-all';

	} elseif ( in_array( 'no-gating', $term_gating_options, true ) ) {

		// Priority 2 - Monetized and Public.
		return 'no-gating';

	} elseif ( in_array( 'no', $term_gating_options, true ) ) {

		// Priority 3 - No Monetization.
		return 'no';

	} else {
		return $term_default;
	}
}

/**
 * Return the single source of truth for post gating based on the fallback
 * options if the post gating selection is 'default'. E.g.
 * If return value of each function is default, move onto the next function,
 * otherwise return immediately.
 *
 * @param integer $post_id
 * @return string $content_gating Gating slug type.
 */
// php functions allow type declarations for the return types by enabling the strict requirement (using : ), it will throw a "Fatal Error" on a type mismatch.
function get_content_gating( int $post_id ) : string {

	$post_gating = get_post_gating( $post_id );

	// Set a default monetization value.
	$content_gating = 'no';

	// Hierarchy 1 - Check what is set on the post.
	if ( 'default' !== $post_gating ) {

		$content_gating = $post_gating; // Honour what is set on the post.

	} else {

		// Hierarchy 2 - Check what is set on the taxonomy.
		$taxonomy_gating = get_taxonomy_term_gating( $post_id );
		if ( 'default' !== $taxonomy_gating ) {

			$content_gating = $taxonomy_gating; // Honour what is set on the taxonomy.

		} else {

			// Hierarchy 3 - Check what is set in the global default.
			// Get the post type for this post to check against what is set for default.
			$post = get_post( $post_id );

			// Get the post type from what is saved in global options
			$global_gating_settings = get_global_posts_gating();

			//  The isset() function checks whether a variable is set, which means that it has to be declared and is not NULL.
			if ( ! empty( $global_gating_settings ) && ! empty( $post ) && isset( $global_gating_settings[ $post->post_type ] ) ) { /**Changelog ALA included an additional condition && ! empty( $post ).*/
				$content_gating = $global_gating_settings[ $post->post_type ];
			}
		}
	}

	return $content_gating;
}

/**
 * Get whatever settings are stored in the plugin as the default
 * content gating settings (post, page, cpt etc).
 *
 * @return array Setting stored in options, or blank array.
 */
function get_global_posts_gating() : array {
	$global_settings = get_option( 'coil_content_settings_posts_group' );
	if ( ! empty( $global_settings ) ) {
		return $global_settings;
	}

	return [];
}

/**
 * Get whatever settings are stored in the plugin as the default
 * excerpt settings for the various content types.
 *
 * @return void
 */
function get_global_excerpt_settings() {
	$global_excerpt_settings = get_option( 'coil_content_settings_excerpt_group' );
	if ( ! empty( $global_excerpt_settings ) ) {
		return $global_excerpt_settings;
	}

	return [];
}

/**
 * Set the gating type for the specified post.
 *
 * @param integer $post_id    The post to set gating for.
 * @param string $gating_type Either "default", "no", "no-gating", "gate-all", "gate-tagged-blocks".
 *
 * @return void
 */
function set_post_gating( int $post_id, string $gating_type ) : void {

	$valid_gating_types = get_valid_gating_types();
	if ( ! in_array( $gating_type, $valid_gating_types, true ) ) {
		return;
	}

	// This will save this metadata key and value to a databse like MySQL
	// post_type = post or page depending, meta_key = _coil_monetize_post_status and it contains a meta_value which is the gating for that post
	update_post_meta( $post_id, '_coil_monetize_post_status', $gating_type );
}

/**
 * Set the gating type for the specified term.
 *
 * @param integer $term_id    The term to set gating for.
 * @param string $gating_type Either "default", "no", "no-gating", "gate-all", "gate-tagged-blocks".
 *
 * @return void
 */
function set_term_gating( int $term_id, string $gating_type ) : void {

	$valid_gating_types = get_valid_gating_types();
	if ( ! in_array( $gating_type, $valid_gating_types, true ) ) {
		return;
	}

	// updates the gating info stored in the category/tag/etc
	update_term_meta( $term_id, '_coil_monetize_term_status', $gating_type );
}

/**
 * New function to determine if the content is monetized
 * based on the output of get_content_gating.
 *
 * @param int $post_id
 * @return boolean
 */
function is_content_monetized( $post_id ) : bool {
	$coil_status = get_content_gating( $post_id );
	return ( $coil_status === 'no' ) ? false : true;
}
