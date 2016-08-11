<?php
/** 
 * Factory object to generate settings page objects 
 *
 * @since 1.0.0
 * @version 1.0.0
 * @package TNS Settings Pages
 * @author Ryan Leeson
 */
abstract class TNS_Setting_Page_Factory {
	protected static $pages = array();
		
	/**
	 * Constructs a new TNS_Settings_Page using a unique slug and the following arguments
	 *	
	 * 'menu' = Menu Title
	 * 'option' = Option Group for the form
	 * 'page' = Page Title
	 * 'position' = Menu List Position
	 *
	 * @param string $slug 
	 * 			Menu slug for the page
	 * @param array $args
	 *        	Set of arguments to build a settings page
	 */
	public static function build_page( $slug, $args ) {
		if ( !is_string( $args [ 'page' ] ) || empty( $args [ 'page' ] ) ) {
			_doing_it_wrong( 'TNS_Setting_Page_Factory::build_page', 'Set a valid page name for this settings page', 
				TNS_SETTINGS_PAGES_VERSION );
		}
		if ( !is_string( $args [ 'menu' ] ) || empty( $args [ 'menu' ] ) ) {
			_doing_it_wrong( 'TNS_Setting_Page_Factory::build_page', 'Set a valid menu name for this settings page', 
				TNS_SETTINGS_PAGES_VERSION );
		}
		if ( !is_string( $args [ 'options' ] ) || empty( $args [ 'options' ] ) ) {
			_doing_it_wrong( 'TNS_Setting_Page_Factory::build_page', 'Set a valid options group for this settings page', 
				TNS_SETTINGS_PAGES_VERSION );
		}
		if ( !is_int( $args [ 'position' ] ) && empty( $args [ 'position' ] ) ) {
			$args [ 'position' ] = 61;
		}
		
		// Validate the page slug
		if ( !is_string( $slug ) || empty( $slug ) ) {
			_doing_it_wrong( 'TNS_Setting_Page_Factory::build_page', 'Set a valid menu slug for this settings page', 
				TNS_SETTINGS_PAGES_VERSION );
		}
		$safe_slug = sanitize_key( $slug );
				
		// Validate the page slug is unique
		if ( isset( self::$pages[ $safe_slug ] ) ) {
			_doing_it_wrong( 'TNS_Setting_Page_Factory::build_page', sprintf( 'The page %s is already registered', 
				esc_attr( $safe_slug ) ), TNS_SETTINGS_PAGES_VERSION );
		}
				
		// Generate the page, register and return
		$page = new TNS_Settings_Page( $safe_slug, $args );
		if ( null == $page ) {
			_doing_it_wrong( 'TNS_Setting_Page_Factory::build_page', sprintf( 'The page %s could not load.', 
				esc_attr( $safe_slug ) ),TNS_SETTINGS_PAGES_VERSION );
		}
		
		/// Queue the style and scripts the first time around
		if ( count( self::$pages ) < 1 ) {
			add_action( 'admin_enqueue_scripts', array ( 'TNS_Setting_Page_Factory', 'add_admin_scripts' ) );
		}
		
		// Register the page for including the 
		self::$pages[ $safe_slug ] = sprintf( 'toplevel_page_%s', $slug );
		return $page;
	}

	/**
	 * Load specific jQuery module and supporting CSS styles for the setting page
	 *
	 * @param string $suffix
	 *        	Theme option page suffix
	 */
	public static function add_admin_scripts( $suffix ) {
		if ( in_array( $suffix, self::$pages ) || 'edit.php' == $suffix ) {
			wp_enqueue_style ( 'tns-core-style', plugins_url ( '../css/tns-core.css', __FILE__ ),
				array(), TNS_SETTINGS_PAGES_VERSION );
			wp_enqueue_script( 'tns-core-script', plugins_url( '../js/tns-core.js', __FILE__ ),
				array( 'jquery' ), TNS_SETTINGS_PAGES_VERSION, true );
		}
	}
}

/**
 * Wrapper class generates a new settings page
 */
class TNS_Settings_Page {
	protected static $base_capability = 'edit_theme_options';
	protected static $page_nonce = 'tns-options-nonce';
	protected $menu_slug, $menu_title, $page_position, $page_options, $page_title;
	protected $page_sections = array (), $page_validation = array ();
	
	/**
	 * Default constructor sets basic setting page values
	 * 'menu' = Menu Title
	 * 'option' = Option Group for the form
	 * 'page' = Page Title
	 * 'position' = Menu List Position
	 * 'slug' = Menu URL Slug
	 *
	 * @param array $args
	 *        	Set of arguments to build a settings page
	 */
	public function __construct( $slug, $args ) {
		if ( !is_string( $args [ 'page' ] ) || empty( $args [ 'page' ] ) ) {
			_doing_it_wrong( 'TNS_Setting_Page::build_page', 'Set a valid page name for this settings page', 
				TNS_SETTINGS_PAGES_VERSION );
		}
		if ( !is_string( $args [ 'menu' ] ) || empty( $args [ 'menu' ] ) ) {
			_doing_it_wrong( 'TNS_Setting_Page::build_page', 'Set a valid menu name for this settings page', 
				TNS_SETTINGS_PAGES_VERSION );
		}
		if ( !is_string( $slug ) || empty( $slug ) ) {
			_doing_it_wrong( 'TNS_Setting_Page::build_page', 'Set a valid menu slug for this settings page', 
				TNS_SETTINGS_PAGES_VERSION );
		}
		if ( !is_string( $args [ 'options' ] ) || empty( $args [ 'options' ] ) ) {
			_doing_it_wrong( 'TNS_Setting_Page::build_page', 'Set a valid options group for this settings page', 
				TNS_SETTINGS_PAGES_VERSION );
		}
		if ( !is_int( $args [ 'position' ] ) && empty( $args [ 'position' ] ) ) {
			$args [ 'position' ] = 61;
		}
		
		$this->page_title = $args [ 'page' ];
		$this->menu_title = $args [ 'menu' ];
		$this->menu_slug = sanitize_key( $slug );
		$this->page_options = $args [ 'options' ];
		$this->page_position = $args [ 'position' ];
		
		// Add validation later in admin_init to avoid timing issues with validation registration
		add_action( 'admin_init', array ( $this, 'add_option_validation' ), 15 );
		add_action( 'admin_menu', array ( $this, 'generate_page' ) );
	}
	
	/**
	 * Register a set of options on the settings page which will be used sanitized on submission
	 * using the supplied validation function
	 * 'name' = Option group name
	 * 'validation' = Callback function for validation
	 *
	 * @param array $args
	 *        	Name and validation function for the added options
	 * @return boolean True if the option is added, false if it exists or the handle is empty
	 */
	public function add_options( $args ) {
		if ( !empty( $args [ 'name' ] ) && !empty( $args [ 'validation' ] ) ) {
			$safe_name = sanitize_key( $args [ 'name' ] );
			
			$this->page_validation [] = array (
				'name' => $safe_name,
				'validation' => $args [ 'validation' ] 
			);
			return true;
		}
		return false;
	}
	
	/**
	 * Called during admin_init to register each option and its associated validation function
	 */
	public function add_option_validation() {
		foreach ( $this->page_validation as $validation ) {
			register_setting( $this->page_options, $validation [ 'name' ], $validation [ 'validation' ] );
		}
	}
	
	/**
	 * Create a settings section for the page
	 *
	 * @param string $key
	 *        	Index key to reference a page section, sanitized value
	 * @param string $title
	 *        	Human readable name for section
	 * @return boolean True if section is added, false if it already exists and fails to add
	 */
	public function add_section( $key, $header = array() ) {
		$safe_key = sanitize_key( $key );
		if ( !array_key_exists( $safe_key, $this->page_sections ) ) {
			$this->page_sections [ $safe_key ] = new TNS_Settings_Section( $header );
			return true;
		}
		return false;
	}
	
	/**
	 * Insert a setting field into a given section on a settings page
	 *
	 * @param string $section_key
	 *        	Setting section to add the element
	 * @param mixed $settings_array
	 *        	Element attributes array (see setting-element.php for attribute description)
	 * @return boolean True is setting is added, false if it exists already and fails to add
	 */
	public function add_setting( $section_key, $settings_array ) {
		if ( empty( $settings_array ) || empty( $section_key ) ) {
			return false;
		}
		
		$safe_key = sanitize_key( $section_key );
		
		if ( !array_key_exists( $safe_key, $this->page_sections ) ) {
			return false;
		}
		$this->page_sections [ $safe_key ]->add_element( $settings_array );
		return true;
	}
	
	/**
	 * Wrapper to call the Wordpress add_menu_page function
	 */
	public function generate_page() {
		add_menu_page( $this->page_title, $this->menu_title, self::$base_capability, $this->menu_slug, 
			array ( $this, 'render_page' ), '', $this->page_position );
	}
	
	/**
	 * Get the current pages default nonce_name
	 *
	 * @return string Settings page nonce_name, escaped like an HTML attribute
	 */
	public function get_nonce_name() {
		return esc_attr( self::$page_nonce );
	}
	
	/**
	 * Create the Theme options page
	 */
	public function render_page() { 
	?>
		<div class="wrap tns-settings">
			<h2><?php esc_html_e( $this->page_title ); ?></h2>
		<?php
			// Displays a message if the theme options were saved
			if ( isset( $_REQUEST [ 'settings-updated' ] ) && $_REQUEST [ 'settings-updated' ] === "true" ) :
		?>
			<div id="message" class="updated below-h2 confirmation-message">
				<p>
					<strong><?php _e( 'Options saved' ); ?> </strong>
				</p>
			</div>
		<?php endif; ?>
			<form method="post" action="options.php">
			<?php
				// Provide a nonce for the AJAX post in a hidden input field
				$options_nonce = wp_create_nonce( self::$page_nonce );
				printf( '<input type="hidden" id="%s" value="%s" />', esc_attr( self::$page_nonce ), $options_nonce );
				
				// Render the settings validation fields and each registered section
				settings_fields( $this->page_options );
				
				// Collect all of the section names and bodies into a set of array to render
				// Expects the section names are strings, and the section bodies are HTML chunks
				$section_headers = array ();
				$section_bodies = array ();
				$first = false;
				foreach ( $this->page_sections as $key => $section ) {
					// Get the section name, skip the section if there is no
					$name = $section->get_section_name();
					if ( empty( $name ) ) {
						$name = sprintf( '<Section %s>', esc_html( $key ) );
					}
					
					$active = '';
					if ( $first === false ) {
						$active = "class=active";
						$first = true;
					}
					
					$target = sprintf( 'tns-section-%s', esc_attr( $key ) );
					$section_headers [] = sprintf( "\t<option class=\"section-handle\" value=\"%s\">%s</option>", 
						esc_attr( $target ), esc_html( $name ) );
					$section_bodies [] = sprintf( "\t<div id=\"%s\" %s>\n%s\t</div>", esc_attr( $target ), esc_attr( $active ), 
						$section->get_section_body() );
				}
			?>
				<header>
					<label> <span class="field-title">Settings Section</span> <span class="field-control"><select 
						name="tns-section-handles"><?php printf( "\n%s\n", implode( "\n", $section_headers ) ); ?></select></span>
					</label>
				</header>
				<section class="tns-section-bodies"><?php printf( "\n%s\n", implode( "\n", $section_bodies ) ); ?></section>
				<footer><?php submit_button( 'Save Changes' ); ?></footer>
			</form>
		</div>
	<?php
	}
}

/**
 * Generic menu section for TNS_Settings_Page, allows external plugins to register their
 * own group of settings
 */
class TNS_Settings_Section {
	protected $description;
	protected $elements;
	protected $name;
	
	/**
	 * Constructor checks the $header for string array members 'name', for the section title, and
	 * 'description', for the description displayed before the form fields
	 *
	 * @param mixed $header        	
	 */
	public function __construct( $header ) {
		if ( isset( $header [ 'name' ] ) ) {
			$this->name = esc_html( $header [ 'name' ] );
		}
		if ( isset( $header [ 'description' ] ) ) {
			$this->description = esc_html( $header [ 'description' ] );
		}
	}
	
	/**
	 * Create an element based on the supplied attribute array.
	 *
	 * Sends to setting factory based on type set in array
	 *
	 * @param mixed $element_array
	 *        	Element attribute array
	 * @return boolean True if element if created, otherwise false
	 */
	public function add_element( $element_array ) {
		$type = sanitize_key( $element_array [ 'type' ] );
		if ( !empty( $type ) ) {
			$element = TNS_Setting_Factory::create( $type, $element_array );
			if ( !empty( $element ) ) {
				$this->elements [] = $element;
				return true;
			}
			else {
				return false;
			}
		}
		return false;
	}
	
	/**
	 * Sort array of elements by their order property
	 *
	 * Returns array sort boolean, if a's order > b's order
	 *
	 * @return boolean
	 */
	public function by_order( $a, $b ) {
		return ( $a->order > $b->order );
	}
	
	/**
	 * Retrieve the section name
	 *
	 * @return string
	 */
	public function get_section_name() {
		return $this->name;
	}
	
	/**
	 * Generate the Section HTML body
	 *
	 * @return string
	 */
	public function get_section_body() {
		// Confirm there are elements to render, otherwise return an empty body
		if ( false === $this->has_elements() ) {
			return '';
		}
		
		// Sort element array by their assigned 'order'
		usort( $this->elements, array (
			$this,
			'by_order' 
		) );
		
		$section_template = "";
		foreach ( $this->elements as $element ) {
			$section_template .= sprintf( "\t\t\t\t\t<label class=\"field\"><span class=\"field-title\">%s</span>\n", esc_html( $element->get_label() ) );
			$section_template .= sprintf( "\t\t\t\t\t<span class=\"field-control\">%s</span></label>\n", $element->get_element_body() );
		}
		return $section_template;
	}
	
	/**
	 * Boolean indicates if the section has elements assigned, true if there are elements
	 *
	 * @return boolean
	 */
	public function has_elements() {
		if ( empty( $this->elements ) ) {
			return false;
		}
		return true;
	}
}