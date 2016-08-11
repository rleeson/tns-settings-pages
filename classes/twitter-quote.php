<?php
/**
 * Tool for channel manager to embed formatted Twitter links within their content
 * 
 * 	- Twitter Quote Shortcode [tns-tweet]
 * 
 * @since 1.0.0
 * @version 1.0.0
 * @package TNS Settings Pages
 * @author Ryan Leeson
 */
if ( !class_exists( 'TNS_Twitter_Quote' ) ) {
	class TNS_Twitter_Quote extends TNS_Settings_Class {
		/**
		 * Root URL for anchor tags for Twitter Intents
		 *
		 * @var string
		 */
		protected static $twitter_intent_url = 'https://twitter.com/intent/tweet';
		
		/**
		 * CSS class for the Twitter Quote link
		 *
		 * @var string
		 */
		protected static $twitter_quote_class = 'tns-twitter-quote';
		
		/**
		 * Twitter Quote media button ID for the post page
		 *
		 * @var string
		 */
		protected static $twitter_quote_id = 'add-tns-twitter-quote';
		
		/**
		 * Twitter Quote media button label for the post page
		 *
		 * @var string
		 */
		protected static $twitter_quote_label = 'Add Twitter Quote';
		
		/**
		 * Twitter Quote shortcode label
		 *
		 * @var string
		 */
		protected static $twitter_quote_shortcode = 'tns-tweet';
		
		/**
		 * Twitter Quote shortcode attribute for shortened tweet content
		 *
		 * @var string
		 */
		protected static $twitter_quote_shortcode_short_attr = 'short';
		
		/**
		 * Unique tns_Settings_Class admin element handle
		 * Twitter Intent output format
		 *
		 * @var string
		 */
		protected static $tweet_format_id = 'twitter-check-format-id';
		
		/**
		 * Unique tns_Settings_Class admin element handle
		 * Twitter Handle used in Intent
		 *
		 * @var string
		 */
		protected static $twitter_handle_id = 'twitter-handle-id';
		
		/**
		 * Unique tns_Settings_Class admin element handle
		 * Link reserve character length
		 *
		 * @var string
		 */
		protected static $tweet_link_reserve_id = 'twitter-link-reserve-id';
		
		/**
		 * Post meta key to hold the over limit reference array
		 *
		 * @var string
		 */
		protected static $twitter_quote_limit_key = 'tns-twitter-quote-limit';
		
		/**
		 * Match group containing the shortcode attributes
		 *
		 * @var integer
		 */
		protected $shortcode_attribute_match_group = 3;
		
		/**
		 * Match group containing the shortcode attribute short name
		 *
		 * @var string
		 */
		protected $shortcode_attribute_short_name = 'short';
		
		/**
		 * Match group containing the shortcode attribute regex
		 *
		 * @var string
		 */
		protected $shortcode_attribute_match_regex = '/(?:short=\")([^\"]+)(?:\")/';
		
		/**
		 * Match group containing the shortcode attribute value
		 *
		 * @var integer
		 */
		protected $shortcode_attribute_value_match_group = 1;
		
		/**
		 * Match group containing the shortcode content for get_shortcode_regex()
		 *
		 * @var integer
		 */
		protected $shortcode_content_match_group = 5;
		
		/**
		 * Match group containing the shortcode name for get_shortcode_regex()
		 *
		 * @var integer
		 */
		protected $shortcode_name_match_group = 2;
		
		/**
		 * Format string to use when checking tweet lengths, set on options page
		 *
		 * @var string
		 */
		protected $tweet_format_string = '';
		
		/**
		 * Twitter handle set on options page
		 *
		 * @var string
		 */
		protected $tweet_handle = '';
		
		/**
		 * Reserved length of a tweet for links
		 * - Twitter reserves its own minimum link length
		 *
		 * @var integer
		 */
		protected $tweet_link_reserved_length = 0;
		
		/**
		 * Maximum length of a tweet
		 *
		 * @var integer
		 */
		protected $tweet_max_length = 140;
		
		/**
		 * Setup the Twitter Quote actions/filters and shortcode registration
		 */
		public function __construct( TNS_Settings_Page $base_page ) {
			global $pagenow, $post;
			parent::__construct( $base_page );
			
			// Only load in admin on post pages
			if ( is_admin() && ( 'post.php' !== $pagenow && 'post-new.php' !== $pagenow ) ) {
				return;
			}
			
			// Load only on post type 'post' pages
			if ( isset( $post->post_type ) && 'post' !== $post->post_type ) {
				return;
			}
			
			// Retrieve base Twitter Quote settings
			$this->tweet_format_string = $this->get_string_value( self::$tweet_format_id );
			$this->tweet_handle = $this->get_string_value( self::$twitter_handle_id );
			$this->tweet_link_reserved_length = intval( $this->get_string_value( self::$tweet_link_reserve_id ) );
			
			// Validate settings, disable if not found
			if ( empty( $this->tweet_format_string ) || empty( $this->tweet_handle ) ) {
				_doing_it_wrong( 'TNS_Twitter_Quote::__construct()', 
					'Set Twitter Quote format string and handle on the options page to enable Twitter Quotes', TNS_SETTINGS_PAGES_VERSION );
				return;
			}
			
			// Initialize [tns-tweet] shortcode
			add_shortcode( self::$twitter_quote_shortcode, array ( $this, 'twitter_quote_shortcode' ) );
			
			// Register admin scripts/styles
			add_action( 'admin_enqueue_scripts', array ( $this, 'add_admin_scripts' ) );
			
			// Register admin notice for posts with over limit Twitter quotes
			add_action( 'admin_notices', array ( $this, 'admin_notice_over_limit' ), 20 );
			
			// Filter Twitter Quote instances to make sure content will fit a Tweet
			add_filter( 'save_post', array ( $this, 'save_twitter_quote_check' ), 10, 3 );
			
			// Add an Add Twitter Quote button to the Edit Post page after Add Media
			add_action( 'media_buttons', array ( $this, 'twitter_quote_button' ), 11 );
		}
		
		/**
		 * Set the required base parameters for tns_Settings_Class
		 *
		 * @see TNS_Settings_Class::set_base_parameters()
		 */
		public function set_base_parameters() {
			$this->admin_description = 'Manage Twitter Quote format and handle. Printf style format string, only three %s required/accepted, in order: Tweet, Bit.ly link, Twitter Handle';
			$this->admin_section = 'twitter-quote';
			$this->admin_title = 'Twitter Quote Options';
			$this->options_handle = 'tns_twitter_quote_options';
		}
		
		/**
		 * TNS_Setting_Element settings array to configure the settings section
		 */
		public function register_options_settings() {
			$this->setting_elements [ self::$tweet_format_id ] = array (
				'type'			=> 'text',
				'id'			=> self::$tweet_format_id,
				'label'			=> 'Format string for Twitter Quotes',
				'description'	=> 'Printf style format string, only three %s required/accepted, in order: Tweet, Post link, Twitter Handle',
				'option'		=> $this->section_options->get_name(),
				'data'			=> $this->section_options->get( self::$tweet_format_id ),
				'default'		=> '%s %s via %s',
				'order'			=> 10 
			);
			$this->setting_elements [ self::$twitter_handle_id ] = array (
				'type'			=> 'text',
				'id'			=> self::$twitter_handle_id,
				'label'			=> 'Twitter Handle',
				'description'	=> 'Enter the Twitter handle for the via of a Twitter Quote',
				'option'		=> $this->section_options->get_name(),
				'data'			=> $this->section_options->get( self::$twitter_handle_id ),
				'default'		=> '@handle',
				'order'			=> 20 
			);
			$this->setting_elements [ self::$tweet_link_reserve_id ] = array (
				'type'			=> 'text',
				'id'			=> self::$tweet_link_reserve_id,
				'label'			=> 'Twitter Link Reserve Length',
				'description'	=> 'Enter the number of characters to reserve for a link in the Twitter Quote',
				'option'		=> $this->section_options->get_name(),
				'data'			=> $this->section_options->get( self::$tweet_link_reserve_id ),
				'default'		=> '22',
				'order'			=> 30 
			);
		}
		
		/**
		 * Load specific jQuery module and supporting CSS styles for the New/Edit Post page
		 *
		 * @param $suffix -
		 *        	Theme option page suffix
		 */
		public function add_admin_scripts( $suffix ) {
			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'tns-twitter-quote', plugins_url( '../js/tns-twitter-quote.js', __FILE__ ), 
				array ( 'jquery-ui-button' ), false, true );
		}
		
		/**
		 * Display warning message on posts with Twitter Quotes over the tweet max text limit
		 */
		public function admin_notice_over_limit() {
			global $pagenow, $post;
			
			// Only process notices on existing posts
			if ( 'post.php' !== $pagenow ) {
				return;
			}
			
			// Get a list of any Twitter quotes over the max character limit, output a message if there are any
			$notice_items = get_post_meta( $post->ID, self::$twitter_quote_limit_key, true );
			
			if ( empty( $notice_items ) ) {
				return;
			}
			?>
			<div class="notice error">
				<p>
			<?php
			if ( strlen( $notice_items ) === 1 ) :
				printf( 'Twitter quote number %s is over the %s character limit. Please revise the post and save.', 
					esc_html( $notice_items ), esc_html( $this->tweet_max_length ) );
			else :
				printf( 'Twitter quote numbers %s are over the %s character limit. Please revise the post and save.', 
					esc_html( $notice_items ), esc_html( $this->tweet_max_length ) );
			endif;
			?>
				</p>
			</div>
			<?php
		}
		
		/**
		 * *********************
		 * Twitter Quote Setup
		 * *********************
		 */
		
		/**
		 * Set the Tweet Reserved text length, assumes all permalinks will be the same length
		 *
		 * @param
		 *        	integer Post ID
		 * @return integer
		 */
		protected function get_reserved_length( $post_id ) {
			if ( empty( $post_id ) ) {
				return 0;
			}
			
			// Find the link length, with minimum of the admin set reserved length
			$link_length = strlen( esc_url( wp_get_shortlink( $post_id ) ) );
			if ( $link_length < $this->tweet_link_reserved_length ) {
				$link_length = $this->tweet_link_reserved_length;
			}
			
			// Find the handle length along with padding from the format string
			$handle_length = strlen( sprintf( esc_html( $this->tweet_format_string ), '', '', esc_html( $this->tweet_handle ) ) );
			return ( $handle_length + $link_length );
		}
		
		/**
		 * Check the post content for instances and lengths of twitter quotes
		 * Returns an array of instance indecies where the tweet is over the charater limit
		 *
		 * @param string $content
		 *        	Post content
		 * @return mixed Array of instance indecies
		 */
		protected function check_tweet_length( $post_id, $content ) {
			// Array to hold instance number of Twitter Quotes which are over the tweet max lengtth
			$check = array ();
			
			// Skip processing on empty content
			if ( empty( $content ) ) {
				return $check;
			}
			
			$pattern = sprintf( '/%s/s', get_shortcode_regex() );
			$reserve = $this->get_reserved_length( $post_id );
			
			// Get the array of pattern matches, bypass processing if there are none
			$count = preg_match_all( $pattern, $content, $matches );
			if ( empty( $count ) ) {
				return;
			}
			
			// Process each match, checking for a shortcode name match
			$i = 1;
			foreach ( $matches [ $this->shortcode_name_match_group ] as $key => $match ) {
				if ( $match === self::$twitter_quote_shortcode ) {
					// Default to check the shortcode content
					$sc_content = $matches [ $this->shortcode_content_match_group ] [ $key ];
					
					// Check to see if a shorter tweet was added via shortcode attribute, process it if not empty
					preg_match( $this->shortcode_attribute_match_regex, $matches [ $this->shortcode_attribute_match_group ] [ $key ], 
						$short );
					if ( count( $short ) > 1 ) {
						$sc_content = $short [ $this->shortcode_attribute_value_match_group ];
					}
					
					// Check if the shortcode content plus the reserve length is more than the max length
					if ( ( strlen( $sc_content ) + $reserve ) > $this->tweet_max_length ) {
						$check [] = $i;
					}
				}
				$i++;
			}
			return $check;
		}
		
		/**
		 * Shortcode [tns-tweet] callback function
		 *
		 * @param array $atts
		 *        	Shortcode Attributes
		 * @param string $content
		 *        	Text content the shortcode wraps
		 * @return string Content block, empty string if there is no content
		 */
		public function twitter_quote_shortcode( $atts, $content = null ) {
			$output = '';
			
			// Stop processing if empty and skip output
			if ( empty( $content ) ) {
				return $output;
			}
			
			// Generate the Twitter Intent query string parameters
			$query_format = 'url=%s&text=%s&original_referer=%s&via=%s';
			$url = esc_url( wp_get_shortlink() );
			$referer = esc_url( get_permalink() );
			$via = wp_kses_post( $this->tweet_handle );
			
			// Determine if there is a short verson of the tweet, otherwise use the shortcode content
			$text = $content;
			if ( !empty( $atts ) && isset( $atts [ $this->shortcode_attribute_short_name ] ) && !empty( 
				$atts [ $this->shortcode_attribute_short_name ] ) ) {
				$text = $atts [ $this->shortcode_attribute_short_name ];
			}
			
			// Auto-trim any excess tweet length off of the content text
			$tweet_over_length = ( strlen( $text ) + $this->get_reserved_length( get_the_ID() ) ) - $this->tweet_max_length;
			if ( $tweet_over_length > 0 ) {
				// Trim, with one (1) char buffer for Twitter URL encoding issue of Bit.ly URLs
				$text = substr( $text, 0, -1 * $tweet_over_length );
			}
			
			// Assemble the query string
			$query_string = sprintf( $query_format, urlencode( $url ), urlencode( wp_kses_post( $text ) ), urlencode( $referer ), 
				urlencode( $via ) );
			
			// Create the Twitter quote output
			$format = '<a class="%s" href="%s?%s" target="_blank"><span class="text">%s</span>' . '<span class="twitter-icon"></span></a>';
			return sprintf( $format, esc_attr( self::$twitter_quote_class ), esc_url( self::$twitter_intent_url ), 
				htmlentities( $query_string ), esc_html( $content ) );
		}
		
		/**
		 * Action hook to add Twitter Quote button to edit post screen
		 */
		public function twitter_quote_button() {
			printf( 
				'<button id="%s" type="button" class="button ui-state-default ui-corner-all" role="button" aria-disabled="false" title="%s">', 
				esc_attr( self::$twitter_quote_id ), esc_attr( self::$twitter_quote_label ) );
			printf( '<span class="ui-button-icon-primary ui-icon ui-icon-note"></span><span class="ui-button-text">%s</span></button>', 
				esc_html( self::$twitter_quote_label ) );
		}
		
		/**
		 * Validate the quoted content of each Twitter Quote will fit within a tweet.
		 *
		 * Runs preg_match_all to return an array of all post shortcodes for processing, with the pattern from get_shortcode_regex()
		 *
		 * Checks the shortcode name match group (see class var $shortcode_name_match_group) for instances of tns-tweet.
		 * Matches for shortcode name are used as key reference to the shortcode content match group (class var $shortcode_content_match_group)
		 * Performs a length check on each instance, storing an error in post meta if over the limit.
		 *
		 * @param int $post_id
		 *        	Post ID
		 * @param WP_Post $post 
		 * 			Post object being saved
		 * @param bool $updated
		 *        	False when this is a new post
		 * @return string The filtered post content
		 */
		public function save_twitter_quote_check( $post_id, $post, $updated ) {
			// Skip processing if not a post and on the pre new post save, or the user does not have the right capabilities to save
			if ( !current_user_can( 'edit_posts' ) || false === $updated || 'post' !== $post->post_type ) {
				return;
			}
			
			$check = $this->check_tweet_length( $post_id, $post->post_content );
			
			// Add/remove the post meta check item list for any over limit Twitter Quotes (save as comma-delimited list)
			if ( count( $check ) > 0 ) {
				update_post_meta( $post_id, self::$twitter_quote_limit_key, join( $check, ', ' ) );
			}
			else {
				delete_post_meta( $post_id, self::$twitter_quote_limit_key );
			}
		}
	}
}
