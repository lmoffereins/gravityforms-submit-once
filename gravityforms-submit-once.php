<?php

/**
 * The Gravity Forms Submit Once Plugin
 * 
 * @package Gravity Forms Submit Once
 * @subpackage Main
 */

/**
 * Plugin Name:       Gravity Forms Submit Once
 * Description:       Limit forms in Gravity Forms to accept only one entry per user.
 * Plugin URI:        https://github.com/lmoffereins/gravityforms-submit-once/
 * Version:           1.0.1
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
	 * The plugin setting's meta key
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $meta_key = 'submitOnce';

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

		// Displaying the form
		add_filter( 'gform_get_form_filter', array( $this, 'handle_form_display' ), 90, 2 );

		// Form settings
		add_filter( 'gform_form_settings',          array( $this, 'register_form_setting' ), 10, 2 );
		add_filter( 'gform_pre_form_settings_save', array( $this, 'update_form_setting'   )        );
	}

	/** Public methods **************************************************/

	/**
	 * Do not display the form when the current user already submitted once
	 *
	 * @since 1.0.0
	 *
	 * @uses get_current_user_id()
	 * @uses GravityForms_Submit_Once::get_form_setting()
	 * @uses GravityForms_Submit_Once::get_gf_translation()
	 * @uses GFCommon::gform_do_shortcode()
	 * @uses GravityForms_Submit_Once::get_user_form_entries()
	 * 
	 * @param string $content The form response HTML
	 * @param array $form Form meta data
	 * @return string Form HTML
	 */
	public function handle_form_display( $content, $form ) {

		// Get the current user
		$user_id = get_current_user_id();

		// Form is marked to allow submissions once
		if ( ! empty( $form ) && $this->get_form_setting( $form, $this->meta_key ) ) {

			// User is not logged in
			if ( empty( $user_id ) ) {

				// Hide the form when login is not explicitly required
				if ( ! isset( $form['requireLogin'] ) || ! $form['requireLogin'] ) {

					// Display not-loggedin message
					$content = '<p>' . ( empty( $form['requireLoginMessage'] ) ? $this->get_gf_translation( 'Sorry. You must be logged in to view this form.' ) : GFCommon::gform_do_shortcode( $form['requireLoginMessage'] ) ) . '</p>';
				}

			// User has already submitted this form. Hide the form
			} elseif ( (bool) $this->get_user_form_entries( $form['id'], $user_id ) ) {
				$content = '<p>' . __( 'Sorry. You can only submit this form once.', 'gravityforms-submit-once' ) . '</p>';
			}
		}

		return $content;
	}

	/**
	 * Return the given form's meta value
	 *
	 * @since 1.0.0
	 *
	 * @uses GFFormsModel::get_form_meta()
	 * 
	 * @param array|int $form Form object or form ID
	 * @param string $meta_key Form meta key
	 * @return mixed Form setting's value or NULL when not found
	 */
	public function get_form_setting( $form, $meta_key ) {

		// Get form metadata
		if ( ! is_array( $form ) && is_numeric( $form ) ) {
			$form = GFFormsModel::get_form_meta( (int) $form );
		} elseif ( ! is_array( $form ) ) {
			return null;
		}

		// Get form setting
		return isset( $form[ $meta_key ] ) ? $form[ $meta_key ] : null;
	}

	/**
	 * Return the given form's entries ids for the given user
	 *
	 * @since 1.0.0
	 * 
	 * @global wpdb
	 *
	 * @uses get_current_user_id()
	 * @uses GFFormsModel::get_lead_table_name()
	 * @uses wpdb::get_col()
	 * @uses wpdb::prepare()
	 * 
	 * @param int $form_id Form ID
	 * @param int $user_id Optional. User ID. Defaults to current user ID
	 * @return array The user's form entries ids
	 */
	public function get_user_form_entries( $form_id, $user_id = 0 ) {
		global $wpdb;

		// Default to current user
		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		// Since GF hasn't any such query function, we'll have to write
		// our own SQL query to get the user's form entries.
		$table_name = GFFormsModel::get_lead_table_name();
		$entries = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table_name WHERE form_id = %d AND created_by = %d AND status = %s", $form_id, $user_id, 'active' ) );

		return apply_filters( 'gf_submit_once_get_user_form_entries', $entries, $form_id, $user_id );
	}

	/**
	 * Return a translated string with the 'gravityforms' context
	 *
	 * @since 1.0.0
	 *
	 * @uses call_user_func_array() To call __() indirectly
	 * @param string $string String to be translated
	 * @return string Translation
	 */
	public function get_gf_translation( $string ) {
		return call_user_func_array( '__', array( $string, 'gravityforms' ) );
	}

	/** Admin Settings **************************************************/

	/**
	 * Display the plugin form setting's field
	 *
	 * @since 1.0.0
	 *
	 * @uses GravityForms_Submit_Once::get_form_setting()
	 * @uses GravityForms_Submit_Once::get_gf_translation()
	 * 
	 * @param array $settings Form settings sections and their fields
	 * @param int $form Form object
	 */
	public function register_form_setting( $settings, $form ) {

		// Start output buffer and setup our setting's field
		ob_start(); ?>

		<tr>
			<th><?php _e( 'Submit once', 'gravityforms-submit-once' ); ?></th>
			<td>
				<input type="checkbox" name="submit-once" id="gform_submit_once" value="1" <?php checked( $this->get_form_setting( $form, $this->meta_key ) ); ?>>
				<label for="gform_submit_once"><?php _e( 'Limit this form to accept only one entry per user', 'gravityforms-submit-once' ); ?></label>
			</td>
		</tr>

		<?php

		// Store and end the output buffer in a variable
		$field = ob_get_clean();

		// Settings sections are stored by their translatable title
		$section = $this->get_gf_translation( 'Restrictions' );

		// Define field key to insert ours after
		$position = array_search( 'entry_limit_message', array_keys( $settings[ $section ] ) ) + 1;

		/**
		 * Insert our field at the given position
		 * 
		 * @link http://stackoverflow.com/questions/3353745/how-to-insert-element-into-array-to-specific-position/3354804#3354804
		 */
		$settings[ $section ] = array_slice( $settings[ $section ], 0, $position, true ) +
			array( $this->meta_key => $field ) +
			array_slice( $settings[ $section ], $position, null, true );

		return $settings;
	}

	/**
	 * Run the update form setting logic
	 *
	 * @since 1.0.0
	 * 
	 * @param array $settings Form settings
	 * @return array Form settings
	 */
	public function update_form_setting( $settings ) {

		// Sanitize form from $_POST var
		$settings[ $this->meta_key ] = isset( $_POST['submit-once'] ) ? (int) $_POST['submit-once'] : 0;

		return $settings;
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

	// Bail if GF is not active
	if ( ! class_exists( 'GFForms' ) )
		return;

	return GravityForms_Submit_Once::instance();
}

// Initiate on plugins_loaded
add_action( 'plugins_loaded', 'gravityforms_submit_once' );

endif; // class_exists
