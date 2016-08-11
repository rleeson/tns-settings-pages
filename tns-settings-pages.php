<?php
/**
 * Plugin Name: TNS Settings Pages
 * Plugin URI: N/A
 * Description: Basic Settings Page Plugin, provides a standard base class to easily combine functionality options
 * Version: 1.0.0
 * Author: Ryan Leeson
 * Author URI: http://www.ryanleeson.com
 * License: GPL v2
 */

define( 'TNS_SETTINGS_PAGES_VERSION', '1.0.0' );

// Settings and base page infrastructure
require_once( __DIR__ . '/classes/options.php' );
require_once( __DIR__ . '/classes/settings-page.php' );
require_once( __DIR__ . '/classes/setting-element.php' );
require_once( __DIR__ . '/classes/settings-class.php' );

/// General options and example Twitter shortcode for in post embedded intents
require_once( __DIR__ . '/classes/general-settings.php' );
require_once( __DIR__ . '/classes/twitter-quote.php' );

global $tns_base_page, $tns_general_settings, $tns_twitter_quote;

tns_admin_init();
tns_plugin_init();

/** 
 * Wrapper function to load the stock base page
 */
function tns_admin_init() {
	global $tns_base_page;
	
	$setting_page_array = array (
		'menu' 		=> 'Custom Options',
		'options' 	=> 'tns-custom-options',
		'page' 		=> 'Custom Theme Options',
		'position' 	=> 62,
	);
	
	// Build the base settings page
	$tns_base_page = TNS_Setting_Page_Factory::build_page( 'tns-custom-options', $setting_page_array );
	
	// Ensure the administration module is present and initializaed
	if ( empty( $tns_base_page ) ) {
		_doing_it_wrong( 'tns_admin_init', 'Could not load the base settings page.', 
			TNS_SETTINGS_PAGES_VERSION );
	}
}

/**
 * Wrapper function to load additional plugin functionality
 */
function tns_plugin_init() {	
	global $tns_base_page, $tns_general_settings, $tns_twitter_quote;
	
	$tns_general_settings	= new TNS_General_Settings( $tns_base_page ) ;
	$tns_twitter_quote		= new TNS_Twitter_Quote( $tns_base_page );
}

/**
 * Returns the site tagline set in the plugin options
 * @return null|string Site Tagline
 */
function tns_get_site_tagline() {
	global $tns_general_settings;
	return $tns_general_settings->get_html_value( 'site-tagline' );
}

/**
 * Returns the site title set in the plugin options
 * @return null|string Site Title
 */
function tns_get_site_title() {
	global $tns_general_settings;
	return $tns_general_settings->get_html_value( 'site-title' );
}