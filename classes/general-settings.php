<?php
/**
 * General administration settings
 *
 * @since 1.0.0
 * @version 1.0.0
 * @package TNS Settings Page
 * @author Ryan Leeson
 */
class TNS_General_Settings extends TNS_Settings_Class {
	/**
	 * Site Tagline ID
	 * @var string
	 */
	protected static $site_tagline_id = 'tns-site-tagline';
	
	/**
	 * Site Title ID
	 * @var string
	 */
	protected static $site_title_id = 'tns-site-title';
	
	/**
	 * Setup the base administation page and options
	 * @param TNS_Options $options
	 *        	Supplied set of options for the admistration object
	 */
	public function __construct( TNS_Settings_Page $base_page ) {
		parent::__construct ( $base_page );
	}
	
	/**
	 * Set the required base parameters for TNS_Settings_Class
	 *
	 * @see TNS_Settings_Class::set_base_parameters()
	 */
	public function set_base_parameters() {
		$this->admin_description	= 'Title and tagline fields which allow HTML tags';
		$this->admin_section		= 'tns-general';
		$this->admin_title			= 'General Options';
		$this->options_handle		= 'tns_general_options';
	}
	
	/**
	 * Setup the settings shown in the General section of the Administration page
	 */
	public function register_options_settings() {
		$this->setting_elements [ self::$site_title_id ] = array (
			'type'		=> 'text',
			'section'	=> $this->admin_section,
			'id'		=> self::$site_title_id,
			'label'		=> 'Site Title',
			'option'	=> $this->section_options->get_name (),
			'data' 		=> $this->section_options->get ( self::$site_title_id ),
			'default'	=> '',
			'order'		=> 10 
		);
		$this->setting_elements [ self::$site_tagline_id ] = array (
			'type'		=> 'text',
			'section'	=> $this->admin_section,
			'id'		=> self::$site_tagline_id,
			'label' 	=> 'Site Tagline',
			'option'	=> $this->section_options->get_name (),
			'data'		=> $this->section_options->get ( self::$site_tagline_id ),
			'default'	=> '',
			'order'		=> 20
		);
	}
}