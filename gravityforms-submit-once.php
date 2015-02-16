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
 * Version:           1.1.0
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
	 * The plugin setting's message meta key
	 *
	 * @since 1.2.0
	 * @var string
	 */
	private $message_meta_key = 'submitOnceMessage';

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
			$instance->setup_globals();
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
	 * Setup default class globals
	 *
	 * @since 1.1.0
	 */
	private function setup_globals() {

		/** Versions **********************************************************/

		$this->version    = '1.0.0';

		/** Paths *************************************************************/

		$this->file       = __FILE__;
		$this->basename   = plugin_basename( $this->file );
		$this->plugin_dir = plugin_dir_path( $this->file );
		$this->plugin_url = plugin_dir_url(  $this->file );

		// Languages
		$this->lang_dir   = trailingslashit( $this->plugin_dir . 'languages' );

		/** Misc **************************************************************/

		$this->domain     = 'gravityforms-submit-once';
	}

	/**
	 * Setup default actions and filters
	 *
	 * @since 1.0.0
	 */
	private function setup_actions() {

		// Translation
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 11 );

		// Displaying the form
		add_filter( 'gform_get_form_filter', array( $this, 'handle_form_display' ), 50, 2 );

		// Form settings
		add_filter( 'gform_form_settings',          array( $this, 'register_form_setting' ), 10, 2 );
		add_filter( 'gform_pre_form_settings_save', array( $this, 'update_form_setting'   )        );

		// Tooltips
		add_filter( 'gform_tooltips', array( $this, 'tooltips' ) );
	}

	/** Plugin **********************************************************/

	/**
	 * Loads the textdomain file for this plugin
	 *
	 * @since 1.1.0
	 *
	 * @uses apply_filters() Calls 'plugin_locale' with {@link get_locale()} value
	 * @uses load_textdomain() To load the textdomain
	 * @uses load_plugin_textdomain() To load the plugin textdomain
	 */
	public function load_textdomain() {

		// Traditional WordPress plugin locale filter
		$locale        = apply_filters( 'plugin_locale', get_locale(), $this->domain );
		$mofile        = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

		// Setup paths to current locale file
		$mofile_local  = $this->lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/gravityforms-submit-once/' . $mofile;

		// Look in global /wp-content/languages/gravityforms-submit-once folder first
		load_textdomain( $this->domain, $mofile_global );

		// Look in global /wp-content/languages/plugins/ and local plugin languages folder
		load_plugin_textdomain( $this->domain, false, 'gravityforms-submit-once/languages' );
	}

	/** Public methods **************************************************/

	/**
	 * Do not display the form when the current user already submitted once
	 *
	 * @since 1.0.0
	 *
	 * @uses get_current_user_id()
	 * @uses GravityForms_Submit_Once::get_form_meta()
	 * @uses GravityForms_Submit_Once::translate()
	 * @uses GFCommon::gform_do_shortcode()
	 * @uses GravityForms_Submit_Once::get_user_form_entries()
	 * 
	 * @param string $content The form response HTML
	 * @param array $form Form meta data
	 * @return string Form HTML
	 */
	public function handle_form_display( $content, $form ) {

		// Bail when form or content is empty
		if ( empty( $content ) || empty( $form ) )
			return $content;

		// Get the current user
		$user_id = get_current_user_id();

		// Form is marked to allow submissions once
		if ( (bool) $this->get_form_meta( $form, $this->meta_key ) ) {

			// User is not logged in. Hide the form when login is not explicitly required
			if ( empty( $user_id ) && empty( $form['requireLogin'] ) ) {

				// Display not-loggedin message
				$content = '<p>' . ( empty( $form['requireLoginMessage'] ) ? $this->translate( 'Sorry. You must be logged in to view this form.' ) : GFCommon::gform_do_shortcode( $form['requireLoginMessage'] ) ) . '</p>';

			// User has already submitted this form. Display only-submit-once message
			} elseif ( (bool) $this->get_user_form_entries( $form['id'], $user_id ) ) {
				$content = '<p>' . ( empty( $form[ $this->message_meta_key ] ) ? __( 'Sorry. You can only submit this form once.', 'gravityforms-submit-once' ) : GFCommon::gform_do_shortcode( $form[ $this->message_meta_key ] ) ) . '</p>';
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
	public function get_form_meta( $form, $meta_key ) {

		// Get form metadata
		if ( is_numeric( $form ) ) {
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
			if ( empty( $user_id ) ) {
				return array();
			}
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
	 * @param string $context Optional. Translation context. Defaults to `gravityforms`
	 * @return string Translation
	 */
	public function translate( $string, $context = 'gravityforms' ) {
		return call_user_func_array( '__', array( $string, $context ) );
	}

	/** Admin Settings **************************************************/

	/**
	 * Display the plugin form setting's field
	 *
	 * @since 1.0.0
	 *
	 * @uses GravityForms_Submit_Once::get_form_meta()
	 * @uses GravityForms_Submit_Once::translate()
	 * 
	 * @param array $settings Form settings sections and their fields
	 * @param int $form Form object
	 */
	public function register_form_setting( $settings, $form ) {

		// Define local variable(s)
		$enabled = $this->get_form_meta( $form, $this->meta_key );
		$style   = ! $enabled ? 'style="display:none;"' : '';

		// Start output buffer and setup our setting's field
		ob_start(); ?>

		<tr>
			<th><?php _e( 'Submit once', 'gravityforms-submit-once' ); ?> <?php gform_tooltip( 'submit_once' ); ?></th>
			<td>
				<input type="checkbox" name="submit-once" id="gform_submit_once" value="1" <?php checked( $enabled ); ?> onclick="window[ ( this.checked ? 'Show' : 'Hide' ) + 'SettingRow' ]( '#submit_once_details' );" />
				<label for="gform_submit_once"><?php _e( 'Accept only one entry per user', 'gravityforms-submit-once' ); ?></label>
			</td>
		</tr>

		<tr id="submit_once_details" class="child_setting_row" <?php echo $style; ?>>
			<td colspan="2" class="gf_sub_settings_cell">
				<div class="gf_animate_sub_settings">
					<table>

						<tr>
							<th><?php _e( 'Once Submitted Message', 'gravityforms-submit-once' ); ?></th>
							<td>
								<textarea name="submit-once-message" class="fieldwidth-3"><?php echo esc_textarea( $this->get_form_meta( $form, $this->message_meta_key ) ); ?></textarea>
							</td>
						</tr>

					</table>
				</div><!-- .gf_animate_sub_settings -->
			</td><!-- .gf_sub_settings_cell -->
		</tr>

		<?php

		// Store and end the output buffer in a variable
		$field = ob_get_clean();

		// Settings sections are stored by their translatable title
		$section = $this->translate( 'Restrictions' );

		// Define field key to insert ours after
		$position = array_search( 'entry_limit_message', array_keys( $settings[ $section ] ) ) + 1;

		/**
		 * Insert our field at the given position
		 * 
		 * @link http://stackoverflow.com/a/3354804/3601434
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

		// Sanitize submit-once
		$settings[ $this->meta_key ] = isset( $_POST['submit-once'] ) ? (int) $_POST['submit-once'] : 0;

		// Sanitize message
		$settings[ $this->message_meta_key ] = isset( $_POST['submit-once-message'] ) ? wp_kses( $_POST['submit-once-message'], array(
			'a' => array(
				'href' => array(),
				'title' => array()
			),
			'p' => array(),
			'br' => array(),
			'em' => array(),
			'strong' => array(),
		) ) : '';

		return $settings;
	}

	/** Tooltips ***********************************************************/

	/**
	 * Append our custom tooltips to GF's tooltip collection
	 *
	 * @since 1.1.0
	 *
	 * @link gravityforms/tooltips.php
	 * 
	 * @param array $tips Tooltips
	 * @return array Tooltips
	 */
	public function tooltips( $tips ) {

		// Append our tooltip. Each tooltip consists of an <h6> header with a short description after it
		$tips['submit_once'] = sprintf( '<h6>%s</h6>%s', __( 'Submit Once', 'gravityforms-submit-once' ), __( 'Check this option to limit the number of times a user can submit this form, to one. Requires a user to be logged in to view this form.', 'gravityforms-submit-once' ) );

		return $tips;
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

	// Bail when GF is not active
	if ( ! class_exists( 'GFForms' ) )
		return;

	return GravityForms_Submit_Once::instance();
}

// Initiate on plugins_loaded
add_action( 'plugins_loaded', 'gravityforms_submit_once' );

endif; // class_exists
