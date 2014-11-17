<?php

/**
 * The Gravity Forms Submit Once Plugin
 * 
 * @package Gravity Forms Submit Once
 * @subpackage Main
 */

/**
 * Plugin Name:       Gravity Forms Submit Once
 * Description:       Limit Gravity Form forms to accept only one entry per user.
 * Plugin URI:        https://github.com/lmoffereins/gravityforms-submit-once/
 * Version:           1.0.0
 * Author:            Laurens Offereins
 * Author URI:        https://github.com/lmoffereins/
 * Text Domain:       gravityforms-submit-once
 * Domain Path:       /languages/
 * GitHub Plugin URI: lmoffereins/gravityforms-submit-once
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'GravityForms_Submit_Once' ) ) :
/**
 * The main plugin class
 *
 * @since 1.0.0
 */
final class GravityForms_Submit_Once {

	/**
	 * The plugin meta field key
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $meta_key = 'gform-submit-once';

	/**
	 * Setup and return the singleton pattern
	 *
	 * @since 1.0.0
	 *
	 * @uses GravityForms_Submit_Once::setup_globals()
	 * @uses GravityForms_Submit_Once::setup_actions()
	 * @return The single GravityForms_Submit_Once
	 */
	public static function instance() {

		// Store instance locally
		static $instance = null;

		if ( null === $instance ) {
			$instance = new GravityForms_Submit_Once;
			$instance->setup_actions();
		}

		return $instance;
	}

	/**
	 * Prevent the plugin class from being loaded more than once
	 */
	private function __construct() { /* Nothing to do */ }

	/** Private methods *************************************************/

	/**
	 * Setup default actions and filters
	 *
	 * @since 1.0.0
	 */
	private function setup_actions() {

		// Displaying form
		add_filter( '', array( $this, 'handle_form_display' ), 10, 2 );

		// Admin settings
		add_filter( '', array( $this, 'register_form_meta'   ) );
		add_action( '', array( $this, 'display_form_setting' ) );
	}

	/** Public methods **************************************************/

	/**
	 * Do not display the form when the current user already submitted once
	 *
	 * @since 1.0.0
	 *
	 * @uses get_current_user_id()
	 * @uses apply_filters() Calls 'gf_submit_once_bail_form'
	 * 
	 * @param bool $output The form output HTML
	 * @param int $form_id The form ID
	 * @return bool Whether to continue displaying the form
	 */
	public function handle_form_display( $output = true, $form_id = 0 ) {

		// Get the current user
		$user_id = get_current_user_id();

		// Form is marked to allow submissions once
		if ( gform_get_form_meta( $form_id, $this->meta_key ) ) {

			// User is not logged in. Hide the form.
			if ( empty( $user_id ) ) {
				$output = ''; // Default text to required login?

			// User has already submitted this form. Hide the form.
			} elseif ( gform_get_user_form_entries( $form_id, $user_id ) ) {
				$output = __( 'Sorry, you can only submit this form once.', 'gravityforms-submit-once' );
			}
		}

		return apply_filters( 'gf_submit_once_bail_form', $output, $form_id, $user_id );
	}

	/** Admin Settings **************************************************/

	/**
	 * Register plugin form meta field
	 *
	 * GF also updates our meta field by this registration. So the meta field key
	 * should correspond with the name attribute of the settings input field.
	 *
	 * @since 1.0.0
	 *
	 * @param array $form_meta Form meta fields
	 * @return array Form meta fields
	 */
	public function register_form_meta( $form_meta = array() ) {

		// Append our meta field
		$form_meta[ $this->meta_key ] = __( 'Limit entries', 'gravityforms-submit-once' );

		return $form_meta;
	}

	/**
	 * Display the plugin form setting
	 *
	 * @since 1.0.0
	 *
	 * @param int $form_id Form ID
	 */
	public function display_form_setting( $form_id ) { ?>

		<label>
			<input type="checkbox" name="submit-once" value="1" <?php checked( gform_get_form_meta( $form_id, $this->meta_key ) ); ?>>
			<span class="description"><?php _e( 'Limit this form to accept only one entry per user.', 'gravityforms-submit-once' ); ?></span>
		</label>

		<?php
	}
}

/**
 * Return single instance of this main plugin class
 *
 * @since 1.0.0
 * 
 * @return GravityForms_Submit_Once
 */
function gravityforms_submit_once() {
	return GravityForms_Submit_Once::instance();
}

// Initiation depends on GF
add_action( 'gform_init', 'gravityforms_submit_once' );

endif; // class_exists
