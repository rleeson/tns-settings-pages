<?php
/**
 * Factory pattern to dynamically register and generate new setting elements
 * 
 * @since 1.0.0
 * @version 1.0.0
 * @package TNS Settings Pages
 * @author Ryan Leeson
 */
abstract class TNS_Setting_Factory {
	protected static $types = array ();
	
	/**
	 * Register a new element type as a shortname to call an element class.
	 * Type is santiized to contain only lower case alphacumeric, dashes and underscores.
	 *
	 * @param string $type
	 *        	Reference name of class, used to create new elements
	 * @param string $classname
	 *        	Name of the class being instantiated
	 * @return boolean True if type is registered, false otherwise
	 */
	public static function register($type, $classname) {
		if (empty ( $type ) || empty ( $classname )) {
			return false;
		}
		$safetype = sanitize_key ( $type );
		
		if (! self::is_registered ( $type )) {
			self::$types [$safetype] = $classname;
			return true;
		}
		return false;
	}
	
	/**
	 * Determine if a specific element type is currently registered.
	 * Runs the type through sanitize_key before making the check.
	 *
	 * @param string $type
	 *        	Reference type name for the element
	 * @return boolean True if a type is registered with the provided name
	 */
	public static function is_registered($type) {
		if (empty ( $type )) {
			return null;
		}
		$safetype = sanitize_key ( $type );
		
		if (isset ( self::$types [$safetype] )) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Debugging function to return the list of currently registered element types
	 *
	 * @return string Human-readable output of the registered element type array
	 */
	public static function list_registered() {
		return print_r ( self::$types, true );
	}
	
	/**
	 * Create a requested element type with a set of supplied parameters
	 * Type is run through sanitize_key prior to searching for the element type
	 *
	 * @param string $type
	 *        	Reference type name for the element
	 * @param array $parameters
	 *        	Array of parameters to create the requested element
	 * @return null|mixed
	 */
	public static function create($type, $parameters) {
		if (empty ( $type ) || empty ( $parameters )) {
			return null;
		}
		$safetype = sanitize_key ( $type );
		
		if (isset ( self::$types [$safetype] ) && class_exists ( self::$types [$safetype] )) {
			return new self::$types [$safetype] ( $parameters );
		} else {
			return null;
		}
	}
	
	/**
	 * Returns the value for a type when unset
	 * Type is run through sanitize_key prior to searching for the element type
	 *
	 * @param string $type
	 *        	Type of element to check
	 * @return null|mixed
	 */
	public static function get_unset_value($type) {
		if (empty ( $type )) {
			return null;
		}
		$safetype = sanitize_key ( $type );
		
		if (isset ( self::$types [$safetype] ) && class_exists ( self::$types [$safetype] )) {
			return call_user_func ( array (
					self::$types [$safetype],
					'get_unset_value' 
			) );
		} else {
			return null;
		}
	}
	
	/**
	 * Run a requested elements validation
	 * Type is run through sanitize_key prior to searching for the element type
	 *
	 * @param array $setting_array
	 *        	Settings array for the element
	 * @param mixed $input
	 *        	Value to validate
	 * @return null|mixed
	 */
	public static function validate($setting_array, $input) {
		if (empty ( $setting_array ) || ! isset ( $setting_array ['type'] )) {
			return null;
		}
		$safetype = sanitize_key ( $setting_array ['type'] );
		
		if (isset ( self::$types [$safetype] ) && class_exists ( self::$types [$safetype] )) {
			return call_user_func ( array (
					self::$types [$safetype],
					'validate' 
			), $input, $setting_array );
		} else {
			return null;
		}
	}
}

// Register the default setting types
TNS_Setting_Factory::register ( 'text', 'TNS_Setting_Base' );
TNS_Setting_Factory::register ( 'boolean', 'TNS_Setting_Boolean' );
TNS_Setting_Factory::register ( 'callback', 'TNS_Setting_Callback' );
TNS_Setting_Factory::register ( 'dropdown', 'TNS_Setting_Dropdown' );
TNS_Setting_Factory::register ( 'number', 'TNS_Setting_Number' );

/**
 * Establish common pattern for setting elements
 */
interface TNS_Setting_Element {
	/**
	 * *
	 * Construct a settings element for a TNS_Settings_Page instance, using a configuration array
	 *
	 * @param mixed $setting_array        	
	 */
	public function __construct($setting_array);
	
	/**
	 * *
	 * Retrieve the element data value
	 *
	 * @return mixed
	 */
	public function get_data();
	
	/**
	 * *
	 * Retrieve the element HTML body
	 *
	 * @return string
	 */
	public function get_element_body();
	
	/**
	 * *
	 * Retrieve the element label/name
	 *
	 * @return string
	 */
	public function get_label();
	
	/**
	 * *
	 * Return the base value to be saved when the setting is not present during validation
	 * (i.e.
	 * Checkbox is not set during postbask)
	 *
	 * @return null|mixed
	 */
	public static function get_unset_value();
	
	/**
	 * *
	 * Validate the contents of this element type
	 *
	 * Input is the value to be tested
	 *
	 * @param mixed $input
	 *        	Value to be processed
	 * @param mixed $setting_array
	 *        	Element being processed
	 */
	public static function validate($input, $setting_array);
}

/**
 * Base class for elements on a TNS_Settings_Page, contained with a TNS_Settings_Section
 * Generates an input field with a label and id specified during creation.
 * Base type is a input text field.
 */
class TNS_Setting_Base implements TNS_Setting_Element {
	protected $data = null;
	protected $default = null;
	protected $id = '';
	protected $label = '';
	protected $option = '';
	protected $type = '';
	public $order = - 1;
	
	/**
	 * Create the base setting element, based on input array.
	 *
	 * Accepts the follow parameters:
	 * id - Form element id, key index for options array
	 * type - Type of form element being created
	 * label - Form element label
	 * option - Name of option group for this setting
	 * data Current value of form element
	 * 
	 * @param array $setting_array
	 *        	Array of element properties
	 */
	public function __construct($setting_array) {
		if (is_string ( $setting_array ['id'] ) && is_string ( $setting_array ['label'] ) && is_string ( $setting_array ['option'] )) {
			$this->id = esc_attr ( $setting_array ['id'] );
			$this->type = esc_attr ( $setting_array ['type'] );
			$this->label = esc_html ( $setting_array ['label'] );
			$this->option = esc_attr ( $setting_array ['option'] );
			$this->order = intval ( $setting_array ['order'] );
			
			// Set the data, not distinctly processed as this could be any type
			$this->data = $setting_array ['data'];
			
			// Set the default, if it isn't set or passed, it defaults to an empty string (override in subclasses)
			$this->default = empty ( $setting_array ['default'] ) ? '' : $setting_array ['default'];
		} else {
			return null;
		}
	}
	
	/**
	 * *
	 * Get the value, using a default if the value isn't yet specified
	 *
	 * @return mixed
	 */
	public function get_data() {
		if (null === $this->data) {
			return $this->default;
		}
		return $this->data;
	}
	
	/**
	 * *
	 * Retrieve the element HTML body
	 * {@inheritDoc}
	 * 
	 * @see TNS_Setting_Element::get_element_body()
	 */
	public function get_element_body() {
		return sprintf ( "<input id=\"%s\" type=\"text\" name=\"%s[%s]\" value=\"%s\" />", esc_attr ( $this->id ), esc_attr ( $this->option ), esc_attr ( $this->id ), esc_html ( $this->get_data () ) );
	}
	
	/**
	 * Return the sanitized form element label
	 *
	 * @return string
	 */
	public function get_label() {
		return esc_html ( $this->label );
	}
	
	/**
	 * *
	 * Return the base value to be saved when the setting is not present during validation
	 * Base/default is null for any type
	 *
	 * @return null
	 */
	public static function get_unset_value() {
		return null;
	}
	
	/**
	 * Basic validation for this element
	 * 
	 * @see TNS_Setting_Element::validate()
	 *
	 * @return string
	 */
	public static function validate($input, $setting_array) {
		// Sanitize the site title field allowing standard post HTML
		return wp_kses_post ( $input );
	}
}

/**
 * Extended setting class for checkbox to act as a boolean value
 * May set a description for the checkbox with the $description property
 *
 * Default value when no value is set, or 'default' parameter is passed is false.
 * Valid 'data' and 'default' values are boolean true/false and 'on'/'off'
 */
class TNS_Setting_Boolean extends TNS_Setting_Base {
	public $description = '';
	
	/**
	 * *
	 * Calls the base element configruation along with the additional processing:
	 * 'description': Comment describing the checkbox functionality
	 * 'data': True if the string value is 'on' or true, false if 'off' or false, otherwise uses 'default'
	 * 'default': Same as 'data', though is false if not otherwise specified
	 *
	 * @param mixed $setting_array        	
	 */
	public function __construct($setting_array) {
		parent::__construct ( $setting_array );
		
		// Store a description for the field
		$this->description = esc_html ( $setting_array ['description'] );
		
		// Process the boolean field, capturing boolean true/false or 'on'/'off'
		// Sets null otherwise, to allow for default value
		$this->data = null;
		if (isset ( $setting_array ['data'] )) {
			if ('on' === $setting_array ['data'] || true === $setting_array ['data']) {
				$this->data = true;
			} else if ('off' === $setting_array ['data'] || false === $setting_array ['data']) {
				$this->data = false;
			}
		}
		
		// Process the default value, allowing values of boolean(true) or 'on'
		if (isset ( $setting_array ['default'] ) && ('on' === $setting_array ['default'] || true === $setting_array ['default'])) {
			$this->default = true;
		} else {
			$this->default = false;
		}
	}
	
	/**
	 * *
	 * Retrieve the element HTML body for a checkbox
	 * {@inheritDoc}
	 * 
	 * @see TNS_Setting_Base::get_element_body()
	 */
	public function get_element_body() {
		return sprintf ( "<span class=\"control\"><input type=\"checkbox\" name=\"%s[%s]\" %s></input></span><span class=\"description\">%s</span>", esc_attr ( $this->option ), esc_attr ( $this->id ), esc_attr ( checked ( $this->get_data (), true, false ) ), esc_html ( $this->description ) );
	}
	
	/**
	 * *
	 * Return the base value to be saved when the setting is not present during validation
	 * Checkbox is not part of a post when unchecked, so return off
	 *
	 * @return string Set the value 'off'
	 */
	public static function get_unset_value() {
		return 'off';
	}
	
	/**
	 * Base validation for this element
	 *
	 * @see TNS_Setting_Element::validate()
	 */
	public static function validate($input, $setting_array) {
		return ($input === 'on') ? 'on' : 'off';
	}
}

/**
 * General setting class to run a callback function to render a form element
 * Takes the additional settings array variable 'callback' to define the function
 */
class TNS_Setting_Callback extends TNS_Setting_Base {
	public $callback_function = null;
	public function __construct($setting_array) {
		parent::__construct ( $setting_array );
		$this->callback_function = $setting_array ['callback'];
	}
	
	/**
	 * *
	 * Retrieve the element HTML body for the callback field
	 * {@inheritDoc}
	 * 
	 * @see TNS_Setting_Base::get_element_body()
	 */
	public function get_element_body() {
		if (! empty ( $this->callback_function )) {
			return call_user_func ( $this->callback_function );
		}
	}
	
	/**
	 * Basic validation for this element
	 * 
	 * @see TNS_Setting_Element::validate()
	 */
	public static function validate($input, $setting_array) {
		// Pass-through value
		return $input;
	}
}

/**
 * Extended settings class for drop-down option lists
 * Must have the public property $data_list set to an array of options for the drop-down.
 * The array is output as options with the key as the option value, and value as display text.
 * Current selected value is set by the $data variable, compared against the array key.
 */
class TNS_Setting_Dropdown extends TNS_Setting_Base {
	public $data_list = array ();
	public function __construct($setting_array) {
		parent::__construct ( $setting_array );
		$this->data_list = $setting_array ['data_list'];
	}
	
	/**
	 * *
	 * Retrieve the element HTML body for a dropdown
	 * {@inheritDoc}
	 * 
	 * @see TNS_Setting_Base::get_element_body()
	 */
	public function get_element_body() {
		if (empty ( $this->data_list )) {
			return;
		}
		
		$option_array = array ();
		
		foreach ( $this->data_list as $key => $value ) {
			$option_array [] = sprintf ( "<option value=\"%s\" %s>%s</option>", esc_attr ( $key ), esc_attr ( selected ( $this->data, $key, false ) ), esc_html ( $value ) );
		}
		
		return sprintf ( "<select id=\"%s\" name=\"%s[%s]\">%s</select>", esc_attr ( $this->id ), esc_attr ( $this->option ), esc_attr ( $this->id ), implode ( '', $option_array ) );
	}
	
	/**
	 * Basic validation for this element
	 * 
	 * @see TNS_Setting_Element::validate()
	 */
	public static function validate($input, $setting_array) {
		$output = null;
		
		// Build allowed page array and clear cached page data
		$pages = get_pages ();
		$allowed_pages = array ();
		foreach ( $pages as $page ) {
			// Clear any transient associated with updated page IDs
			$allowed_pages [] = $page->ID;
		}
		
		// Validate the new page id is valid and assign
		if (in_array ( $input, $allowed_pages )) {
			$output = ( int ) $input;
		}
		
		return $output;
	}
}

/**
 * Extended setting class for number inputs with minimum/maximum values.
 * Preference is given to the minimum, if maximum is less than minimum,
 * the maximum is set to the same value, essentially making a constant.
 * All values are clamped between PHP_INT_MIN and PHP_INT_MAX.
 *
 * Set the step value to an integer to use this as an integer only field
 *
 * Defaults assume any positive real number less than PHP_MAX_INT.
 */
class TNS_Setting_Number extends TNS_Setting_Base {
	public $maximum = PHP_INT_MAX;
	public $minimum = 0;
	public $step = 0;
	
	/**
	 * *
	 * Calls the base element configruation along with the additional processing:
	 * 'maximum': Maximum allowed value (default PHP_INT_MAX)
	 * 'minimum': Minimum allowed value (default 0)
	 * 'step': Increment between consecutive values (default 0)
	 *
	 * @param mixed $setting_array        	
	 */
	public function __construct($setting_array) {
		parent::__construct ( $setting_array );
		$minimum = $maximum = $step = null;
		
		// Process the minimum value, ensures it's less than PHP_INT_MAX
		if (isset ( $setting_array ['min'] ) && is_numeric ( $setting_array ['min'] )) {
			$minimum = $setting_array ['min'];
		}
		if (null !== $minimum && $minimum <= PHP_INT_MAX && $minimum >= (- 1 * PHP_INT_MAX)) {
			$this->minimum = $minimum;
		}
		
		// Process the maximum value
		if (isset ( $setting_array ['max'] ) && is_numeric ( $setting_array ['max'] )) {
			$maximum = $setting_array ['max'];
		}
		if (null !== $maximum && $maximum >= $this->minimum && $maximum <= PHP_INT_MAX) {
			$this->maximum = $maximum;
		}
		
		// Process the field step value
		if (isset ( $setting_array ['step'] ) && is_numeric ( $setting_array ['step'] )) {
			$step = $setting_array ['step'];
		}
		if (! empty ( $step ) && $step > 0) {
			$this->step = $step;
		}
		
		// Set the default value, use the minimum if not specified
		if (isset ( $setting_array ['default'] ) && is_numeric ( $setting_array ['default'] )) {
			$this->default = $setting_array ['default'];
		} else {
			$this->default = $this->minimum;
		}
	}
	
	/**
	 * Check the input value is within the set min/max values
	 *
	 * @param number $input        	
	 */
	public function constrain_value($input) {
		// Invalid values or values less than the minimum are set as the minimum
		if (! is_numeric ( $input ) || $input < $this->minimum) {
			return $this->minimum;
		}
		if ($input > $this->maximum) {
			return $this->maximum;
		}
		return $input;
	}
	
	/**
	 * Get the current value or default, applying the element min/max constraints
	 * {@inheritDoc}
	 * 
	 * @see TNS_Setting_Base::get_data()
	 * @return number
	 */
	public function get_data() {
		if (null == $this->data) {
			$this->data = $this->default;
		}
		return $this->constrain_value ( $this->data );
	}
	
	/**
	 * *
	 * Retrieve the element HTML body for a number field
	 * {@inheritDoc}
	 * 
	 * @see TNS_Setting_Base::get_element_body()
	 * @return string
	 */
	public function get_element_body() {
		$step_attribute = $step_text = "";
		if ($this->step > 0) {
			$step_attribute = sprintf ( " step=\"%s\"", $this->step );
			$step_text = sprintf ( ", increments of %s", $this->step );
		}
		
		return sprintf ( "<span class=\"control\"><input type=\"number\" min=\"%s\" max=\"%s\" name=\"%s[%s]\" value=\"%s\"%s></input></span>" . "<span class=\"description\">Minimum of %s, maximum of %s%s</span>", esc_attr ( $this->minimum ), esc_attr ( $this->maximum ), esc_attr ( $this->option ), esc_attr ( $this->id ), esc_attr ( $this->get_data () ), esc_attr ( $step_attribute ), esc_html ( $this->minimum ), esc_html ( $this->maximum ), esc_html ( $step_text ) );
	}
	
	/**
	 * Check the provided value versus the field constraints
	 *
	 * @param mixed $input        	
	 * @return number
	 */
	public function validate_constraints($input) {
		$input = $this->constrain_value ( $input );
		
		// If a step/increment is assigned
		if ($this->step > 0) {
			$remainder = $input % $this->step;
			$rounded = $input - $remainder;
			if ($remainder > 0 && $rounded > $this->minimum) {
				return $rounded;
			}
		}
		return $input;
	}
	
	/**
	 * Bypass general validation, constraints are required to validate and need to be implemented separately
	 *
	 * @see TNS_Setting_Element::validate()
	 * @param mixed $input        	
	 * @param mixed $setting_array        	
	 * @return number
	 */
	public static function validate($input, $setting_array) {
		if (empty ( $setting_array )) {
			return null;
		}
		// Use the setting array to initialize and validate the input
		$validate = new TNS_Setting_Number ( $setting_array );
		return $validate->validate_constraints ( $input );
	}
}
