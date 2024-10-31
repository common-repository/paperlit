<?php
/*
Plugin Name: Paperlit
Description: Paperlit turns Wordpress into a multi channel publishing environment.
Version: 0.9.98
Author: Paperlit SRL
Author URI: http://www.paperlit.com
License: GPLv2
*/

if (!defined( 'ABSPATH' )) exit; // Exit if accessed directly

require_once __DIR__ . '/autoload.php';

class TPL_Paperlit
{
	public $configs;
	public $edition;
	public $preview;

	public function __construct() {
		if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {

			$this->_load_configs();

			$this->_hooks();
			$this->_actions();
			$this->_filters();

			$this->_create_edition();
			$this->_create_preview();
		}
	}

	/**
	 * Activation plugin:
	 * Setup database tables and filesystem structure
	 *
	 * @void
	 */
	public function plugin_activation() {
		$response = Paperlit_Setup::install();
		if ( false !== $response ) {
			$html = '<h1>' . __('Paperlit') . '</h1>
			<p><b>' .__( 'An error occurred during activation. Please see details below.', 'paperlit_setup' ). '</b></p>
			<ul><li>' .implode( "</li><li>", $response ). '</li></ul>';
			wp_die( $html, __( 'Paperlit activation error', 'paperlit_setup' ), ('back_link=true') );
		}
		do_action( 'press_flush_rules' );
		flush_rewrite_rules();
	}

	/**
	 * Deactivation plugin
	 *
	 * @void
	 */
	public function plugin_deactivation() {
		flush_rewrite_rules();
		Paperlit_Cron::disable();
	}

	/**
	 * Add connection between
	 * the edition and other allowed post type.
	 *
	 * @void
	 */
	public function register_post_connection() {
		$types = $this->get_allowed_post_types();
		p2p_register_connection_type( array(
			'name' 		=> PAPERLIT_P2P_EDITION_CONNECTION,
			'from'	 	=> $types,
			'to' 			=> PAPERLIT_EDITION,
			'admin_column' => 'any',
			'admin_dropdown' => 'from',
			'sortable' 	=> false,
			'title' => array(
  				'from'	=> __( 'Included into issue', 'paperlit' )
  			),
			'to_labels' => array(
    			'singular_name'	=> __( 'Issue', 'paperlit' ),
    			'search_items' 	=> __( 'Search issue', 'paperlit' ),
    			'not_found'			=> __( 'No issue found.', 'paperlit' ),
    			'create'				=> __( 'Select an issue', 'paperlit' ),
				),
			'admin_box' => array(
				'show' 		=> 'from',
				'context'	=> 'side',
				'priority'	=> 'high',
			),
			'fields' => array(
				'status' => array(
					'title'		=> __( 'Visible', 'paperlit' ),
					'type'		=> 'checkbox',
					'default'	=> 1,
				),
				'template' => array(
					'title' 		=> '',
					'type' 		=> 'hidden',
					'values'		=>	array(),
				),
				'order' => array(
					'title'		=> '',
					'type' 		=> 'hidden',
					'default' 	=> 0,
					'values' 	=>	array(),
				),
			)
		) );
	}

	/**
	 * Add default theme template to post connection
	 *
	 * @param  int $p2p_id
	 * @void
	 */
	public function post_connection_add_default_theme( $p2p_id ) {
		$connection = p2p_get_connection( $p2p_id );
		if ( $connection->p2p_type == PAPERLIT_P2P_EDITION_CONNECTION ) {
			$themes = Paperlit_Theme::get_themes();
			$selected_theme = get_post_meta( $connection->p2p_to, '_paperlit_theme_select', true );
			if ( !empty( $themes ) && $selected_theme ) {
				$pages = $themes[$selected_theme];
				foreach ( $pages as $page ) {
					if ( isset( $page['rule'] ) && $page['rule'] == 'post' ) {
						p2p_add_meta( $p2p_id, 'template', $page['path'] );
					}
				}
			}
		}
	}

	/**
	 * Check admin notices and display
	 *
	 * @echo
	 */
	public function check_paperlit_notice() {
		if ( isset( $_GET['pmtype'], $_GET['pmcode'] ) ) {
			$msg_type = sanitize_text_field(esc_attr($_GET['pmtype']));
			$msg_code = sanitize_text_field(esc_attr($_GET['pmcode']));
			$msg_param = isset( $_GET['pmparam'] ) ? urldecode( sanitize_text_field(esc_attr($_GET['pmparam'])) ) : '';

			echo '<div class="paperlit-alert ' . $msg_type . '"><p>';
			switch ( $msg_code ) {
				case 'theme':
					echo _e( '<b>Error:</b> You must specify a theme for issue!', 'paperlit_notice' );
					break;
				case 'duplicate_entry':
					echo _e( sprintf('<b>Error:</b> Duplicate entry for <b>%s</b>. It must be unique', $msg_param ) );
					break;
				case 'failed_activated_license':
					echo _e( sprintf('<b>Error during activation:</b> %s', $msg_param ) );
					break;
				case 'success_activated_license':
					echo _e( sprintf('<b>Activation successfully:</b> %s', $msg_param ) );
					break;
				case 'failed_deactivated_license':
					echo _e( sprintf('<b>Error during deactivation:</b> %s', $msg_param ) );
					break;
				case 'success_deactivated_license':
					echo _e( '<b>License Deactivated.</b>' );
					break;
				case 'themes_cache_flushed':
					echo _e( '<b>Themes cache flushed successfully</b>' );
					break;
			}
			echo '</p></div>';
		}
	}

	/**
   * Unset theme root to exclude custom filter override
   *
   * @param string $path
   * return string
   */
  public function set_theme_root( $path ) {
		update_option( 'paperlit_theme_root', $path );
    if ( isset( $_GET['paperlit_no_theme'] ) ) {
      return PAPERLIT_THEMES_PATH;
    }
		return $path;
  }

	public function set_theme_uri( $uri ) {
		update_option( 'paperlit_theme_uri', $uri );
		if ( isset( $_GET['paperlit_no_theme'] ) ) {
			return PAPERLIT_THEME_URI;
		}
		return $uri;
	}

	/**
	 * Override default wordpress theme
	 *
	 * @param string $name
	 * return string
	 */
	public function set_template_name( $name ) {
		if ( isset( $_GET['paperlit_no_theme'], $_GET['edition_id'] ) ) {
			$name = Paperlit_Theme::get_theme_path( intval(esc_attr($_GET['edition_id'])), false );
		}
		return $name;
	}

	/*
	 * Get all allowed post types
	 *
	 * @return array
	 */
	public function get_allowed_post_types() {
		$types = array( 'post', 'page' );
		$custom_types = $this->_load_custom_post_types();
		$types = array_merge( $types, $custom_types );
		return $types;
	}

	/**
	 * Check if is add or edit page
	 *
	 * @param  string  $new_edit
	 * @return boolean
	 */
	public static function is_edit_page() {

		global $pagenow;
    	if ( !is_admin() ) {
			return false;
		}

		return in_array( $pagenow, array( 'post.php' ) );
	}

	/**
	* Render admin notice for permalink
	*
	* @void
	*/
	public function permalink_notice() {

		$setting_page_url = admin_url() . 'options-permalink.php';
		echo '
		<div class="error paperlit-alert">
			<p>' . __( sprintf( 'Paperlit: Paperlit require <i>Post Name</i> format for permalink. You can set it in <a href="%s">setting page</a>', $setting_page_url ), 'paperlit' ). '</p>
		</div>';
	}

	/**
   * Update Db Version in WordPress Database
	 */
	public function update_db_check() {
		if ( get_site_option( '_paperlit_table_db_version' ) != PAPERLIT_TABLE_DB_VERSION ) {
			Paperlit_Setup::setup_db_tables();
		}
	}

	/**
	 *  Load add-ons exporters
	 *
	 * @void
	 */
	public function load_exporters() {
		do_action_ref_array( 'paperlit/add_ons', array() );
	}

	/**
	* Get all core exporters from relative dir
	*
	* @return boolean
	*/
	public function load_core_exporters() {
		$exporters = Paperlit_Utils::search_dir( PAPERLIT_PACKAGER_EXPORTERS_PATH );
		if ( !empty( $exporters ) ) {
			foreach ( $exporters as $exporter ) {
				$file = trailingslashit( PAPERLIT_PACKAGER_EXPORTERS_PATH . $exporter ) . "index.php";
				if ( is_file( $file ) ) {
					require_once $file;
				}
			}
			return true;
		}
		return false;
	}

	
	public static function mte_remove_unused_shortcode($content) {
	  $pattern = self::_mte_get_unused_shortcode_regex_begin();
	  $content = preg_replace_callback( '/'. $pattern .'/s', 'strip_shortcode_tag', $content );

	  $pattern = self::_mte_get_unused_shortcode_regex_end();
	  $content = preg_replace_callback( '/'. $pattern .'/s', 'strip_shortcode_tag', $content );

	  return $content;  
	}

	/**
	 * Add jQuery datepicker script and css styles
	 * @void
	 */
	public function register_paperlit_styles() {

		wp_register_style( 'paperlit', PAPERLIT_ASSETS_URI . 'css/' . ( defined('WP_DEBUG') && WP_DEBUG ? 'paperlit.css' : 'paperlit.min.css' ) );
		wp_enqueue_style( 'paperlit' );
	}

	protected static function _mte_get_unused_shortcode_regex() {
	  global $shortcode_tags;

	  $tagnames = array_keys($shortcode_tags);
	  $tagregexp = join( '|', array_map('preg_quote', $tagnames) );
	  $regex .= "(?!$tagregexp)";
	  return $regex;
	}

	protected static function _mte_get_unused_shortcode_regex_begin() {
	  $regex = '\\[(\\[?)';
	  $regex .= self::_mte_get_unused_shortcode_regex();
	  $regex .= '\\b([^\\]\\/]*(?:\\/(?!\\])[^\\]\\/]*)*?)(?:(\\/)\\]|\\](?:([\\[]*+(?:\\[(?!\\/\\2\\])[^\\[]*+)*+)\\[\\/\\2\\])?)(\\]?)';
	  return $regex; 
	}

	protected static function _mte_get_unused_shortcode_regex_end() {
	  $regex = '\\[\\/(\\[?)';
	  $regex .= self::_mte_get_unused_shortcode_regex();
	  $regex .= '\\b([^\\]\\/]*?)(?:(\\/)\\]|\\](?:([^\\[]*+)\\[\\/\\2\\])?)(\\]?)';
	  return $regex; 
	}

	 /*
	 * Register hooks
	 * @void
	 */
	protected function _hooks() {
		register_activation_hook( __FILE__, array( $this, 'plugin_activation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'plugin_deactivation' ) );
	}

	/**
	 * Register actions
	 * @void
	 */
	protected function _actions() {
		if ( !$this->_check_permalink_structure() ) {
			add_action( 'admin_notices', array( $this, 'permalink_notice' ) );
		}

		add_action( 'admin_notices', array( $this, 'check_paperlit_notice' ), 20 );
		add_action( 'p2p_init', array( $this, 'register_post_connection' ) );

		/* Once all plugin are loaded, load core and external exporters */
		add_action( 'plugins_loaded', array( $this, 'update_db_check' ) );
		add_action( 'plugins_loaded', array( $this, 'load_exporters' ), 20 );
		add_action( 'plugins_loaded', array( $this, 'load_core_exporters' ), 30 );

		add_action( 'admin_init', array( $this, 'register_paperlit_styles' ), 20 );
	}

	/**
	 * Register filters
	 * @void
	 */
	protected function _filters() {
		add_filter( 'p2p_created_connection', array( $this, 'post_connection_add_default_theme' ) );

		/* Override default wordpress theme path */
		add_filter( 'theme_root', array( $this, 'set_theme_root' ), 10 );
		add_filter( 'theme_root_uri', array( $this, 'set_theme_uri' ), 10 );
		add_filter( 'template', array( $this, 'set_template_name'), 10 );
		add_filter( 'stylesheet', array( $this, 'set_template_name'), 10 );
	}

	/**
	 * Check if permalink structure is set to Post Name
	 * ( required for Editorial Project endpoint )
	 *
	 * @return boolean
	 */
	protected function _check_permalink_structure() {

		$structure = get_option('permalink_structure');
		if( $structure != "/%postname%/" ) {
			return false;
		}
		return true;
	}

	/**
	 * Load plugin configuration settings
	 *
	 * @void
	 */
	protected function _load_configs() {

		if ( is_null( $this->configs ) ) {
			$this->configs = get_option('paperlit_settings', array(
				'paperlit_custom_post_type' => array()
			));
		}
	}

	/**
	 * Load custom post types configured in settings page
	 *
	 * @return array - custom post types
	 */
	protected function _load_custom_post_types() {
		$types = array();
		if ( !empty( $this->configs ) && isset( $this->configs['paperlit_custom_post_type'] ) ) {
			$custom_types = $this->configs['paperlit_custom_post_type'];
			if ( is_array( $custom_types ) ) {
				foreach ( $custom_types as $post_type ) {
					if ( strlen( $post_type ) ) {
						array_push( $types, $post_type );
					}
				}
			} elseif ( is_string( $custom_types ) && strlen( $custom_types ) ) {
				array_push( $types, $custom_types );
			}
		}
		return $types;
	}

	/**
	 * Instance a new edition object
	 *
	 * @void
	 */
	protected function _create_edition() {
		if ( is_null( $this->edition ) ) {
			$this->edition = new Paperlit_Edition();
		}
	}

	/**
	 * Instance a new preview object
	 *
	 * @void
	 */
	protected function _create_preview() {
		if ( is_null( $this->preview ) ) {
			$this->preview = new Paperlit_Preview;
		}
	}
}

// Instantiate the plugin class
$tpl_paperlit = new TPL_Paperlit();
