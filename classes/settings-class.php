<?php
/**
 * Base class to register other classes which will plugin new page sections to TNS_Settings_Page instances
 *
 * @since 1.0.0
 * @version 1.0.0
 * @package TNS Settings Page
 * @author Ryan Leeson
 */
abstract class TNS_Settings_Class {
	/* Required - define the option page section along with title and descriptions */
	protected $admin_description = null;
	protected $admin_section = null;
	protected $admin_title = null;
	
	/* Required - TNS_Options key used to store class settings */
	protected $options_handle = null;
	
	/* Required - Associated TNS_Settings_Page_Factory object */
	protected $settings_page = null;
	
	/**
	 * Optional - Add filters to let outside functions/objects add options or validation to this section
	 * Options filter - Named tns-{$options_filter}, before adding settings to the page in add_options_section()
	 * Validation filter - Named tns-{$options_filter}, at end of validate_options() before returning $output
	 */
	protected $options_filter = null;
	protected $section_options;
	protected $settings_elements;
	
	/**
	 * Implement a function to set (at least) each of the required protected variables:
	 * $this->admin_description - Description displayed for admin section
	 * $this->admin_section - Handle assigned to admin section
	 * $this->admin_title - Title displayed for admin section
	 * $this->options_handle - Handle to reference TNS_Options
	 */
	abstract public function set_base_parameters();
	
	/**
	 * Implement the array of element settings
	 *
	 * Assign labeled arrays of settings configuration to $this->settings_elements
	 * Reference TNS_Setting_Element::__construct( $settings_array )
	 */
	abstract public function register_options_settings();
	
	/**
	 * Required - Base constructor must be called by child class to setup administration page interface and validation
	 *
	 * @param TNS_Settings_Page $settings_page
	 *        	Associated settings page to register section
	 */
	public function __construct( TNS_Settings_Page $settings_page ) {
		if ( empty( $settings_page ) || !is_a( $settings_page, 'TNS_Settings_Page' ) ) {
			_doing_it_wrong( 'TNS_Settings_Class::__construct', 'An instance of TNS_Settings_Page was not supplied.', 
				TNS_SETTINGS_PAGES_VERSION );
			return;
		}
		$this->settings_page = $settings_page;
		
		// Call to set required base parameters
		$this->set_base_parameters();
		if ( empty( $this->admin_description ) || empty( $this->admin_section ) || empty( $this->admin_title ) ) {
			_doing_it_wrong( 'TNS_Settings_Class::__construct', 
				'Register valid $admin_description, $admin_section, and _admin_title via set_base_parameters()', 
				TNS_SETTINGS_PAGES_VERSION );
			return;
		}
		
		// Initializes TNS_Options object and sets up functionality
		if ( !class_exists( 'TNS_Options' ) ) {
			_doing_it_wrong( 'TNS_Settings_Class::__construct', 'TNS_Options is not available.', TNS_SETTINGS_PAGES_VERSION );
			return;
		}
		if ( empty( $this->options_handle ) ) {
			_doing_it_wrong( 'TNS_Settings_Class::__construct', 'Handle to refer to TNS_Options instance is missing.', 
				TNS_SETTINGS_PAGES_VERSION );
			return;
		}
		$this->section_options = new TNS_Options( $this->options_handle, array () );
		
		add_action( 'admin_init', array (
			$this,
			'add_options_section' 
		) );
	}
	
	/**
	 * Register the options section for output and optional segmented validation
	 */
	public function add_options_section() {
		// Admin page feed options section arguments
		$section_args = array (
			'name' => $this->admin_title,
			'description' => $this->admin_description 
		);
		
		// Feed options arguments for validation
		$options_args = array (
			'name' => $this->section_options->get_name(),
			'validation' => array (
				$this,
				'validate_options' 
			) 
		);
		
		// Setup administration page
		$this->settings_page->add_options( $options_args );
		$this->settings_page->add_section( $this->admin_section, $section_args );
		
		// Register settings used by this plugin
		$this->register_options_settings();
		if ( !empty( $this->options_filter ) ) {
			// Filter for outside item registration/removal
			$this->setting_elements = apply_filters( sprintf( 'tns_%s', $this->options_filter ), $this->setting_elements, 
				$this->section_options );
		}
		foreach ( $this->setting_elements as $setting_args ) {
			$this->settings_page->add_setting( $this->admin_section, $setting_args );
		}
	}
	
	/**
	 * Return a sanitized boolean option, stored as the string 'on' for true, 'off' for false
	 *
	 * @param string $name
	 *        	Options key name for the boolean option
	 * @param bool $default
	 *        	Optional default value, pass 'on' for true, 'off' for false
	 * @return boolean When the option is present, returns true if the value is 'on', otherwise false;
	 *         If not set, it uses the default value
	 */
	public function get_boolean_value( $name, $default = 'off' ) {
		$safe_name = sanitize_key( $name );
		$value = $this->section_options->get( $safe_name );
		
		if ( !empty( $value ) ) {
			$value = ( $value === 'on' ) ? true : false;
			return $value;
		}
		return ( $default === 'on' ) ? true : false;
	}
	
	/**
	 * Return a sanitized HTML string option (via wp_kses_post)
	 *
	 * @param string $name
	 *        	Options key name for the html option
	 * @return string Sanitized string
	 */
	public function get_html_value( $name ) {
		$safe_name = sanitize_key( $name );
		$value = $this->section_options->get( $safe_name );
		
		if ( !empty( $value ) ) {
			$value = wp_kses_post( $value );
		}
		return $value;
	}
	
	/**
	 * Retrieve an integer value options, using an optional default value and check
	 * to ensure a positive (or zero) value is returned.
	 *
	 * If the default does not match the positive/sign requirement, it is set to 0.
	 *
	 * When a value is retrieved and does not match the positive/sign requirement to default
	 * value is returned (and subject to the default condition above).
	 *
	 * @param string $setting
	 *        	Option value key
	 * @param integer $default
	 *        	Default value
	 * @param boolean $positive
	 *        	When true only allows zero or positive integers.
	 * @return null|integer Null if no setting is defined, otherwise an integer
	 */
	public function get_integer_value( $setting, $default = 0, $positive = true ) {
		if ( empty( $setting ) ) {
			return null;
		}
		
		// Establish default is an integer and meets sign requirements
		if ( $positive && $default < 0 ) {
			$default = 0;
		}
		$default = intval( $default );
		
		$value = $this->get_string_value( $setting );
		
		if ( is_numeric( $value ) ) {
			$value = intval( $value );
			
			if ( $positive && $value < 0 ) {
				$value = $default;
			}
			return $value;
		}
		
		return $default;
	}
	
	/**
	 * Return an unsanitized string option, if not set, uses an optional default value
	 *
	 * @param
	 *        	string Options key name for the html option
	 * @param
	 *        	string
	 * @return string
	 */
	public function get_string_value( $name, $default = null ) {
		$value = $this->section_options->get( sanitize_key( $name ) );
		
		// Fallback to a default value if the option is not set
		if ( null === $value ) {
			$value = $default;
		}
		return $value;
	}
	
	/**
	 * Run validation on each of all registered options with default processing and a filter to override
	 */
	public function validate_options( $input ) {
		// Stop processing if there are no options registered
		if ( empty( $this->section_options ) ) {
			return;
		}
		
		$filtered_input = array ();
		$output = array ();
		
		// Build a list of relevant inputs to test and retrieve current values from stored options
		// Then validate all registered options based on their type
		foreach ( $this->setting_elements as $setting_id => $option ) {
			if ( !isset( $option [ 'type' ] ) || !isset( $option [ 'id' ] ) ) {
				continue;
			}
			
			$id = $option [ 'id' ];
			$type = $option [ 'type' ];
			
			// Get any stored value as a base
			$output [ $id ] = $this->section_options->get( $id );
			
			// Add to the filtered input array, defaulting to null, skipping if input doesn't exist
			if ( isset( $input [ $id ] ) ) {
				$filtered_input [ $id ] = $input [ $id ];
			}
			else {
				// Check for values which may be unset like checkboxes
				$unset = TNS_Setting_Factory::get_unset_value( $type );
				if ( null !== $unset ) {
					$filtered_input [ $id ] = $unset;
				}
				else {
					$filtered_input [ $id ] = null;
					continue;
				}
			}
			
			// Continue processing based on type, knowing the input exists
			$output [ $id ] = TNS_Setting_Factory::validate( $option, $filtered_input [ $id ] );
		}
		
		// Filter inserts settings validation into this section
		return apply_filters( sprintf( 'tns_%s_validation', esc_attr( $this->options_filter ) ), $filtered_input, $output );
	}
}