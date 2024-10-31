<?php

/**
 * Paperlit packager class.
 *
 */

require_once PAPERLIT_PACKAGER_PATH . 'paperlitprogressbar.php';
if (!headers_sent()) {
	header('Content-Encoding: none');
}
class Paperlit_Packager
{
	public static $verbose = true;
	public static $log_output;
	public $pb;
	public $linked_query;
	public $edition_dir;
	public $edition_cover_image;
	public $edition_post;
	public $package_type;
	public $log_id;
	public $toc_enabled;

	protected $_posts_attachments = array();

	public function __construct() {
		$this->_get_linked_posts();
		$this->pb = new PaperlitProgressBar();
	}

	/**
	 * Generate the edition package
	 *
	 * object $editorial_project
	 * @void
	 */
	public function run( $editorial_project ) {

		$current_user = wp_get_current_user();

		$log = array(
			'action'		=>	'package',
			'object_id'		=>	intval(esc_attr($_GET['edition_id'])),
			'ip'			=>	Paperlit_Utils::get_ip_address(),
			'author'		=>	$current_user->ID,
			'type'			=>	sanitize_text_field(esc_attr($_GET['packager_type'])),
		);

		$this->log_id = Paperlit_Logs::insert_log( $log );

		ob_start();

		if( !isset( $_GET['packager_type'] ) ) {
			self::print_line( __( 'No package type selected. ', 'edition' ), 'error' );
			exit;
		}

		if( sanitize_text_field(esc_attr($_GET['packager_type'])) != 'webcore' ) {
			$options = get_option( 'paperlit_settings' );
			$packager_type_var = sanitize_text_field(esc_attr($_GET['packager_type']));

			$exporter = isset( $options['paperlit_enabled_exporters'][$packager_type_var]) ? $options['paperlit_enabled_exporters'][$packager_type_var] : false ;
			if( !$exporter || !Paperlit_EDD_License::check_license( $exporter['itemid'], $exporter['name'] ) ) {
				$setting_page_url = admin_url() . 'admin.php?page=paperlit-addons';
				self::print_line( sprintf( __('Exporter %s not enabled. Please enable it from <a href="%s">Paperlit add-ons page</a>', 'edition'), $packager_type_var, $setting_page_url ), 'error' );
				$this->exit_on_error();
				return;
			}
		}

		$this->package_type = sanitize_text_field(esc_attr($_GET['packager_type']));
		if ( is_null( $this->edition_post ) ) {
			ob_end_flush();
			return;
		}

		$this->toc_enabled = sanitize_text_field(esc_attr($_GET['toc_enabled']));

		if ( has_action( "paperlit_packager_posts_validation" ) ) {
			do_action( "paperlit_packager_posts_validation", $this );
		} elseif ( empty( $this->linked_query->posts ) ) {
			self::print_line( __( 'No posts linked to this edition ', 'edition' ), 'error' );
			$this->exit_on_error();
			return;
		}

		self::print_line( sprintf( __( 'Create package for %s', 'edition' ), $editorial_project->name ), 'success' );

		$response = Paperlit_Setup::check_folder_system();
		if ( false === $response ) {
      		self::print_line( sprintf( __( 'Paperlit activation error', 'paperlit_setup' )), 'error' );
      		$this->set_progress( 100 );
			ob_end_flush();
			return;
		}

		// Create edition folder
		$edition_post = $this->edition_post;
		$edition_name = $editorial_project->slug . '_' . time();
		$this->edition_dir = Paperlit_Utils::make_dir( PAPERLIT_TMP_PATH, $edition_name );
		if ( !$this->edition_dir ) {
			self::print_line( sprintf( __( 'Failed to create folder %s ', 'edition' ), PAPERLIT_TMP_PATH . $edition_name ), 'error' );
			$this->set_progress( 100 );
			ob_end_flush();
			return;
		}

		self::print_line( sprintf( __( 'Create folder %s ', 'edition' ), $this->edition_dir ), 'success' );
		$this->set_progress( 5, __( 'Loading edition theme', 'edition' ) );

		// check if theme is active
		$theme_id = get_post_meta( $edition_post->ID, '_paperlit_theme_select', true );
		$themes = get_option( 'paperlit_themes' );
		$themes_page_url = admin_url() . 'admin.php?page=paperlit-themes';
		if( !isset( $themes[$theme_id]['active'] ) || !$themes[$theme_id]['active'] ) {
			self::print_line( sprintf( __('Theme %s not enabled. Please enable it from <a href="%s">Paperlit themes page</a>', 'edition'), $theme_id, $themes_page_url ), 'error' );
			$this->exit_on_error();
			return;
		}

		// Get associated theme
		$theme_assets_dir = Paperlit_Theme::get_theme_assets_path( $edition_post->ID );
		if ( !$theme_assets_dir ) {
			self::print_line( __( 'Failed to load edition theme', 'edition' ), 'error' );
			$this->exit_on_error();
			return;
		}

		do_action( "paperlit_packager_{$this->package_type}_start", $this, $editorial_project );

		$this->set_progress( 30, __( 'Downloading assets', 'edition' ) );

		// Download all assets
		$downloaded_assets = $this->_download_assets( $theme_assets_dir );
		if ( !$downloaded_assets ) {
			$this->exit_on_error();
			return;
		}

		$total_progress = 40;
		if ( !empty( $this->linked_query->posts ) ) {
			$progress_step = round( $total_progress / count( $this->linked_query->posts ) );
			foreach ( $this->linked_query->posts as $k => $post ) {
				# deleteAll shotcodes
  				$content = $post->post_content;
  				$post->post_content = TPL_Paperlit::mte_remove_unused_shortcode($content); 

				if( has_action( "paperlit_packager_parse_{$post->post_type}" ) ) {
					do_action_ref_array( "paperlit_packager_parse_{$post->post_type}", array( $this, $post ) );
					$parsed_post = false;
				}
				else {
					$parsed_post = $this->_parse_post( $post, $editorial_project );
					if ( !$parsed_post ) {
						self::print_line( sprintf( __( 'You have to select a layout for %s', 'edition' ), $post->post_title ), 'error' );
						continue;
					}
				}
				do_action( "paperlit_packager_{$this->package_type}", $this, $post, $editorial_project, $parsed_post );

				self::print_line( sprintf( __('Adding %s ', 'edition'), $post->post_title ) );
				$this->set_progress( $total_progress + $k * $progress_step );
			}
		}

		do_action( "paperlit_packager_{$this->package_type}_end", $this, $editorial_project );

		$this->_clean_temp_dir();
		Paperlit_Logs::update_log( $this->log_id, self::$log_output );
		$this->set_progress( 100, __( 'Successfully created package', 'edition' ) );
		self::print_line(__('Done', 'edition'), 'success');
		ob_end_flush();
	}

	/**
	* Set progressbar percentage
	*
	* @param int $percentage
	* @void
	*/
	public function set_progress( $percentage, $text = '' ) {
		$this->pb->setProgress( $percentage, $text );
	}

	/**
	 * Print live output
	 * @param string $output
	 * @param string $class
	 * @echo
 	 */
	public static function print_line( $output, $class = 'success', $enable_log = true ) {
		if ( self::$verbose ) {
			$out =  '<p class="liveoutput ' . $class . '"><span class="label">' . $class . '</span> ' . $output . '</p>';
			echo $out;
			if( $enable_log ) {
				self::$log_output .= $out;
			}
			ob_flush();
			flush();
		}
	}

	public function set_book_json() {
		// create the file book.json
		$book_create = $this->_book_create($this->edition_dir, $this->edition_post->post_title);
		if ( !$book_create ) {
			$this->exit_on_error();
			return;
		}
	}

	/**
	 * Save cover image into edition package
	 *
	 * @void
	 */
	public function save_cover_image() {

		$edition_cover_id = get_post_thumbnail_id( $this->edition_post->ID );
		if ( $edition_cover_id ) {

			$upload_dir = wp_upload_dir();
			$edition_cover_metadata = wp_get_attachment_metadata( $edition_cover_id );
			$edition_cover_path = $upload_dir['basedir'] . DS . $edition_cover_metadata['file'];
			$info = pathinfo( $edition_cover_path );

			if ( copy( $edition_cover_path, $this->edition_dir . DS . PAPERLIT_EDITION_MEDIA . $info['basename'] ) ) {
				$this->edition_cover_image = $info['basename'];
				self::print_line( sprintf( __( 'Copied cover image %s ', 'edition' ), $edition_cover_path ), 'success' );
			}
			else {
				self::print_line( sprintf( __( 'Can\'t copy cover image %s ', 'edition' ), $edition_cover_path ), 'error' );
			}
		}
	}

	/**
	 * Add package meta data to edition
	 *
	 * @void
	 */
	public function set_package_date() {

		$date = date( 'Y-m-d H:i:s' );
		add_post_meta( $this->edition_post->ID, '_paperlit_package_date', $date, true );
		update_post_meta( $this->edition_post->ID, '_paperlit_package_updated_date', $date );
	}

	/**
	 * Add function file if exist
	 *
	 * @void
	 */
	public function add_functions_file() {

		$theme_dir = Paperlit_Theme::get_theme_path( $this->edition_post->ID );
		$files = Paperlit_Utils::search_files( $theme_dir, 'php', true );
		if ( !empty( $files ) ) {
			foreach ( $files as $file ) {
				if ( strpos( $file, 'functions.php' ) !== false ) {
					require_once $file;
					break;
				}
			}
		}
	}

	/**
	* Save the html output into file
	*
	* @param  string $post
	* @param  boolean
	*/
	public function save_html_file( $post, $filename, $dir ) {

		return file_put_contents( $dir . DS . Paperlit_Utils::sanitize_string( $filename ) . '.html', $post);
	}

	/**
	* Parse toc mobile file
	*
	* @return string or boolean false
	*/
	public function toc_mobile_parse( $editorial_project ) {

    $toc = Paperlit_Theme::get_theme_layout( $this->edition_post->ID, 'toc-mobile' );

    if ( !$toc ) {
      return false;
    }

		ob_start();
		$edition = $this->edition_post;
		$editorial_project_id = $editorial_project->term_id;
		$paperlit_package_type = $this->package_type;
		$paperlit_theme_url = Paperlit_Theme::get_theme_uri( $this->edition_post->ID );

		$edition_posts = $this->linked_query;
		$this->add_functions_file();
		require( $toc );
		$output = ob_get_contents();
		ob_end_clean();

		return $output;
	}

	/**
	* Parse toc file
	*
	* @return string or boolean false
	*/
	public function toc_parse( $editorial_project ) {

    $toc = Paperlit_Theme::get_theme_layout( $this->edition_post->ID, 'toc' );
    if ( !$toc ) {
      return false;
    }

		ob_start();
		$edition = $this->edition_post;
		$editorial_project_id = $editorial_project->term_id;
		$paperlit_package_type = $this->package_type;
		$paperlit_theme_url = Paperlit_Theme::get_theme_uri( $this->edition_post->ID );

		$edition_posts = $this->linked_query;
		$this->add_functions_file();
		require( $toc );
		$output = ob_get_contents();
		ob_end_clean();

		return $output;
	}

	/**
	 * Create toc
	 *
	 * @param  object $editorial_project
	 * @param  string $dir
	 * @void
	 */
	public function make_toc( $editorial_project, $dir, $filename = "index" ) {

		// Parse html of toc index.php file
		$html = $this->toc_parse( $editorial_project );
		if ( !$html ) {
			self::print_line( __( 'Failed to parse toc file', 'edition' ), 'error' );
			$this->exit_on_error();
			return;
		}

		// Rewrite toc url
		if ( has_action( "paperlit_{$this->package_type}_toc_rewrite_url" ) ) {
			do_action_ref_array( "paperlit_{$this->package_type}_toc_rewrite_url", array( $this, &$html ) );
		}
		else {
			$html = $this->rewrite_url( $html );
		}

		$html = str_replace('../','', $html, $count); 

		$this->set_progress( 10, __( 'Saving toc file', 'edition' ) );

		// Save cover html file
		if ( $this->save_html_file( $html, $filename, $dir ) ) {
			self::print_line( __( 'Toc file correctly generated', 'edition' ), 'success' );
			$this->set_progress( 20, __( 'Saving edition posts', 'edition' ) );
		}
		else {
			self::print_line( __( 'Failed to save toc file', 'edition' ), 'error' );
			$this->exit_on_error();
			return;
		}
	}

	/**
	 * Create toc mobile
	 *
	 * @param  object $editorial_project
	 * @param  string $dir
	 * @void
	 */
	public function make_toc_mobile( $editorial_project, $dir, $filename = "index" ) {

		// Parse html of toc mobile index.php file
		$html = $this->toc_mobile_parse( $editorial_project );

		if ( !$html ) {
			self::print_line( __( 'Failed to parse toc mobile file', 'edition' ), 'error' );
			$this->exit_on_error();
			return;
		}

		// Rewrite toc mobile url
		if ( has_action( "paperlit_{$this->package_type}_toc_mobile_rewrite_url" ) ) {
			do_action_ref_array( "paperlit_{$this->package_type}_toc_mobile_rewrite_url", array( $this, &$html ) );
		}
		else {
			$html = $this->rewrite_url( $html );
		}

		$html = str_replace('../','', $html, $count); 

		$this->set_progress( 10, __( 'Saving toc mobile file', 'edition' ) );

		// Save cover html file
		if ( $this->save_html_file( $html, $filename, $dir ) ) {
			self::print_line( __( 'Toc mobile file correctly generated', 'edition' ), 'success' );
			$this->set_progress( 20, __( 'Saving edition posts', 'edition' ) );
		}
		else {
			self::print_line( __( 'Failed to save toc mobile file', 'edition' ), 'error' );
			$this->exit_on_error();
			return;
		}
	}

	/**
	* Get all url from the html string and replace with internal url of the package
	*
	* @param  string $html
	* @param  string $ext  = 'html' extension file output
	* @return string or false
	*/
	public function rewrite_url( $html, $extension = 'html', $media_folder = PAPERLIT_EDITION_MEDIA ) {

		if ( $html ) {
			$post_rewrite_urls = array();
			$external_urls = array();
			$urls = Paperlit_Utils::extract_urls( $html );
			foreach ( $urls as $url ) {
				if ( strpos( $url, site_url() ) !== false || strpos( $url, home_url() ) !== false ) {
					$post_id = url_to_postid( $url );
					if ( $post_id ) {
						foreach( $this->linked_query->posts as $post ) {
							if ( $post->ID == $post_id ) {
								$path = Paperlit_Utils::sanitize_string( $post->post_title ) . '.' . $extension;
								$post_rewrite_urls[$url] = $path;
							}
							else {
								array_push( $external_urls, $url);
							}
						}
					}
					else {
						$attachment_id = self::get_attachment_from_url( $url );
						if ( $attachment_id ) {
							$info = pathinfo( $url );
							$filename = $info['basename'];
							$post_rewrite_urls[$url] = $media_folder . $filename;
							// Add attachments that will be downloaded
							$this->_posts_attachments[$filename] = $url;
						}
					}
				}
				else { //external url
					array_push( $external_urls, $url);
				}
			}

			if ( !empty( $post_rewrite_urls ) ) {
				$html = str_replace( array_keys( $post_rewrite_urls ), $post_rewrite_urls, $html );
			}

			if ( !empty( $external_urls ) ) {
				foreach( $external_urls as $exturl ) {
					$html = str_replace( $exturl, $exturl . "?referrer=Baker", $html );
				}
			}
		}
		return $html;
	}

	/**
	* Copy attachments into the package folder
	*
	* @param  array $attachments
	* @param  string $media_dir path of the package folder
	* @void
	*/
	public function save_posts_attachments( $media_dir ) {

		if ( !empty( $this->_posts_attachments ) ) {
			$attachments = array_unique( $this->_posts_attachments );
			foreach ( $attachments as $filename => $url ) {
				if ( copy( $url, $media_dir . DS . $filename ) ) {
					Paperlit_Packager::print_line( sprintf( __( 'Copied %s ', 'edition' ), $url ), 'success' );
				}
				else {
					Paperlit_Packager::print_line( sprintf( __('Failed to copy %s ', 'edition'), $url ), 'error' );
				}
			}
		}
	}

	/**
	 * Add element to array of attachments
	 * @param array $attachments
	 */
	public function add_post_attachment( $key, $value ) {
		$this->_posts_attachments[$key] = $value;
	}

	/**
	 * Reset array of attachments
	 */
	public function reset_post_attachments() {
		$this->_posts_attachments = array();
	}

	/**
	* Stop packager procedure and clear temp folder
	*
	* @void
	*/
	public function exit_on_error() {
		$this->_clean_temp_dir();
		$this->set_progress( 100, __( 'Error creating package', 'edition' ) );
		Paperlit_Logs::update_log( $this->log_id, self::$log_output );
		ob_end_flush();
	}

	/**
	 * Get attachment ID by url
	 *
	 * @param string $attachment_url
	 * @return string or boolean false
	 */
	public static function get_attachment_from_url( $attachment_url ) {

		global $wpdb;
		$attachment_url = preg_replace( '/-[0-9]{1,4}x[0-9]{1,4}\.(jpg|jpeg|png|gif|bmp)$/i', '.$1', $attachment_url );
		$attachment = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid RLIKE '%s' LIMIT 1;", $attachment_url ) );
		if ( $attachment ) {
			return $attachment[0];
		}
		else {
			return false;
		}
	}

	/**
	* Select all posts in p2p connection with the current edition
	*
	* @void
	*/
	protected function _get_linked_posts() {
		if ( !isset( $_GET['edition_id'] ) ) {
			return array();
		}

		$this->edition_post = get_post(intval(esc_attr($_GET['edition_id'] )));

		$this->linked_query = Paperlit_Edition::get_linked_posts( intval(esc_attr($_GET['edition_id'])), array(
			'connected_meta' => array(
				array(
					'key'		=> 'status',
					'value'	=> 1,
					'type'	=> 'numeric'
				)
			)
		) );
	}

	protected function _book_create($pathDir, $title)
	{
		$listFiles = array();
		$files = Paperlit_Utils::search_files($pathDir, 'html', true );
		if ( !empty( $files ) ) {
			foreach ($files as $file) {
                if (( strpos( $file, 'toc.html' ) == false ) and (strpos( $file, 'toc-mobile.html' ) == false)){
					$file = str_replace($pathDir."\\","",$file);
					#strip slases for unix system folder
					$file = str_replace($pathDir."/","",$file);
					array_push($listFiles, $file);
				}
			}
		}

		$linked_posts = $this->linked_query->posts;
  		$orderPosts = [];
  		foreach( $linked_posts as $k => $post ) 
    	    array_push($orderPosts, Paperlit_Utils::sanitize_string($post->post_title));

		$listFilesOrder = [];
		foreach ($listFiles as $listfile) 
		{
			$nameFile = pathinfo($listfile,PATHINFO_FILENAME);
			$key = array_search($nameFile, $orderPosts);
			if ($key !== false)  {
				$listFilesOrder[$key] = $listfile;	
			} elseif($nameFile == "index")
				{
					$listFilesOrder[0] = $listfile;	
				}
		}
		ksort($listFilesOrder);

		if ($this->toc_enabled == "1") 
		{
			array_unshift($listFilesOrder, "toc-mobile.html");
		}
		
		$cover = "";
		if(isset($this->edition_cover_image)) {
			$cover = "gfx/".$this->edition_cover_image;
		}

		// create the date
		$data = array(
			"hpub" 						=> 	1,
			"title"						=> 	$title,
			"author" 					=> 	[""],
  			"creator" 					=>	[""],
			"url"						=>	"book://untitled2",
  			"orientation"				=>	 "",
  			"zoomable"					=>	 false,
  			"-baker-background"			=>	 "",
  			"-baker-vertical-bounce"	=>	 false,
  			"-baker-media-autoplay"		=>	 false,
  			"-baker-page-numbers-color" =>	 "",
			"date" 						=> 	date("Y-m-d"),
			"cover"						=>  $cover,
			"contents"					=> 	$listFilesOrder
		);

		// create book.json
		$jsonData = json_encode($data);
		file_put_contents($pathDir."/book.json", $jsonData);
		return true;
	}

	/**
	 * Download assets into package folder
	 *
	 * @param string $theme_assets_dir
	 * @return boolean
	 */
	protected function _download_assets( $theme_assets_dir ) {

		$edition_assets_dir = Paperlit_Utils::make_dir( $this->edition_dir, basename( $theme_assets_dir ), false );
		if ( !$edition_assets_dir ) {
			self::print_line( sprintf( __( 'Failed to create folder %s', 'edition' ), PAPERLIT_TMP_PATH . DS . basename( $theme_assets_dir ) ), 'error');
			return false;
		}

		self::print_line( sprintf( __( 'Created folder %s', 'edition' ), $edition_assets_dir ), 'success' );

		if ( !is_dir( $theme_assets_dir ) ) {
			self::print_line( sprintf( __( 'Error: Can\'t read assets folder %s', 'edition' ), $theme_assets_dir ), 'error' );
			return false;
		}

		$copied_files = Paperlit_Utils::recursive_copy( $theme_assets_dir, $edition_assets_dir );
		if ( is_array( $copied_files ) ) {
			foreach ( $copied_files as $file ) {
				self::print_line( sprintf( __( 'Error: Can\'t copy file %s ', 'edition' ), $file ), 'error' );
			}
			return false;
		}
		else {
			self::print_line( sprintf( __( 'Copy assets folder with %s files ', 'edition' ), $copied_files ), 'success' );
		}
		return true;
	}

	/**
	* Parsing html file
	*
	* @param  object $post
	* @return string
	*/
	protected function _parse_post( $linked_post, $editorial_project ) {
		$page = Paperlit_Theme::get_theme_page( $this->edition_post->ID, $linked_post->p2p_id );
		if ( !$page || !file_exists( $page )  ) {
			return false;
		}

		ob_start();
		$edition = $this->edition_post;
		$editorial_project_id = $editorial_project->term_id;
		$paperlit_package_type = $this->package_type;
		$paperlit_theme_url = Paperlit_Theme::get_theme_uri( $this->edition_post->ID );

		global $post;
		$post = $linked_post;
		setup_postdata($post);
		$this->add_functions_file();
		require( $page );
		$output = ob_get_contents();
		wp_reset_postdata();
		ob_end_clean();
		return $output;
	}

	/**
	* Clean the temporary files folder
	*
	* @void
	*/
	protected function _clean_temp_dir() {
		self::print_line(__('Cleaning temporary files ', 'edition') );
		Paperlit_Utils::remove_dir( $this->edition_dir );
	}
}
