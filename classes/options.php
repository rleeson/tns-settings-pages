<?php
/**
 * Options wrapper class
 *
 * Control an array of options stored in the Wordpress options table
 * Provides wrapper tools to provide value updates mid-request
 *
 * @since 1.0.0
 * @version 1.0.0
 * @package TNS Page Settings
 * @author Ryan Leeson
 */
if ( ! class_exists ( 'TNS_Options' ) ) :
	class TNS_Options {
		protected $defaults;
		protected $name;
		protected $options;
		
		/**
		 * Create an instance of the current option array stored for the site based on
		 * supplied $defaults and $name
		 *
		 * @param array $defaults
		 *        	Define a default options array, empty set if not specified
		 * @param string $name
		 *        	Name of the option in the wp_options table, validated with sanitize key
		 */
		public function __construct( $name, $defaults = array() ) {
			if ( ! empty ( $name ) && is_string ( $name ) ) {
				$this->name = sanitize_key ( $name );
				$this->defaults = $defaults;
				$this->refresh_options ();
			}
			else {
				_doing_it_wrong ( 'TNS_Options::__construct', 'Invalid Wordpress options name specified', TNS_SETTINGS_PAGES_VERSION );
			}
		}
		
		/**
		 * Return an option value given any index, or all values if no index is specified
		 *
		 * @param int|string $index
		 *        	Array index to retrieve
		 * @return null|Ambigous Value object from options array
		 */
		public function get( $index = '' ) {
			if ( empty ( $index ) ) {
				return $this->options;
			}
			if ( is_string ( $index ) || is_int ( $index ) ) {
				if ( array_key_exists ( $index, $this->options ) ) {
					return $this->options [ $index ];
				}
			}
			return null;
		}
		
		/**
		 * Return the options array name
		 *
		 * @return string Name of options array, escaped as an HTML attribute
		 */
		public function get_name() {
			$name = '';
			if ( ! empty ( $this->name ) ) {
				$name = esc_attr ( $this->name );
			}
			return $name;
		}
		
		/**
		 * Sets entire option value or by index, by default saves to the database.
		 * (Optional) Queue multiple updates by setting $multi to true, must call
		 * save_options method afterwards to save queued values to database.
		 *
		 * @param Ambigous $value
		 *        	Object to save in the options array
		 * @param int|string $index
		 *        	Array index to set, if blank, all values
		 * @param boolean $multi
		 *        	Set to true to allow queueing of multiple updates
		 * @return boolean Returns true if the array value is updated
		 */
		public function set( $value, $index = '', $multi = false ) {
			// Sets appropriate values
			if ( empty ( $index ) ) {
				$this->options = $value;
			}
			elseif ( is_string ( $index ) || is_int ( $index ) ) {
				$this->options [ $index ] = $value;
			}
			
			// Saves options back to database
			if ( empty ( $index ) || ! $multi ) {
				$success = $this->save_options ();
				if ( $success ) {
					// Refresh in case there are overlapping actions
					$this->refresh_options ();
					return true;
				}
				return false;
			}
			return false;
		}
		
		/**
		 * Wrapper functions to refresh the in memory copy of the options array
		 *
		 * @return boolean Returns false if no option name is specified
		 */
		public function refresh_options() {
			if ( ! empty ( $this->name ) ) {
				$this->options = get_option ( $this->name, $this->defaults );
				return true;
			}
			else {
				return false;
			}
		}
		
		/**
		 * Saves the current version of the options array to the database
		 *
		 * @return boolean Returns true if the database successfully updated
		 */
		public function save_options() {
			if ( ! empty ( $this->name ) ) {
				return update_option ( $this->name, $this->options );
			}
			else {
				return false;
			}
		}
	}


endif;