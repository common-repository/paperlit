<?php

class Paperlit_themes_page {

  public function __construct() {

    if( !is_admin() ) {
      return;
    }

    add_action( 'admin_footer', array( $this, 'add_custom_scripts' ) );

    add_action( 'admin_menu', array( $this, 'paperlit_add_admin_menu' ) );
    add_action( 'wp_ajax_paperlit_flush_themes_cache', array( $this, 'paperlit_flush_themes_cache' ) );
    add_action( 'wp_ajax_paperlit_delete_theme', array( $this, 'paperlit_delete_theme' ) );
    add_action( 'wp_ajax_paperlit_upload_theme', array( $this, 'paperlit_upload_theme' ) );
    add_action( 'wp_ajax_paperlit_dismiss_notice', array( $this, 'dismiss_notice' ) );
  }

  /**
  * Add options page to wordpress menu
  */
  public function paperlit_add_admin_menu() {

    add_submenu_page('paperlit', __('Themes', 'paperlit-themes'), __('Themes', 'paperlit-themes'), 'manage_options', 'paperlit-themes', array( $this, 'paperlit_themes_page' ));
  }

  /**
  * Render a single theme
  * @param array $theme
  * @return string
  */
  public function render_theme( $theme, $installed, $activated, $free ) {

    $options    = get_option( 'paperlit_settings' );
    $item_id    = isset( $theme['id'] ) ? $theme['id'] : false;
    $item_slug  = isset( $theme['slug'] ) ? $theme['slug'] : false;
    $item_name  = isset( $theme['title'] ) ? $theme['title'] : false;
    $item_price = isset( $theme['price'] ) ? $theme['price'] . '$' : false;
    $item_link  = isset( $theme['link'] ) ? $theme['link'] : false;

    $item_thumbnail  = isset( $theme['thumbnail'] ) ? $theme['thumbnail'] : false;
    $item_content  = isset( $theme['content'] ) ? $theme['content'] : false;

    $html = '<div class="theme ' . ( $activated ? 'active' : '' ) . '" data-name="' . $item_id . '" tabindex="0">
    <form method="post" name="' . $item_slug . '">
    <div class="theme-screenshot paperlit-theme-screenshot">
    <img src="'.$item_thumbnail.'" alt="">
    </div>
    <p class="paperlit-theme-description">' . $item_content . '</p>';

    if ( $installed && $activated && !$free ) {
      $html .= '<p class="paperlit-theme-description"><span>' . __("Your license key", 'paperlit-addons' ) . ' <b>' . $options['paperlit_license_key_' . $item_slug] . '</b></span></p>';
    }
    elseif ( $installed && !$free ) {
      $html .= '<p class="paperlit-theme-description paperlit-theme-description-input"><input type="text" id="paperlit_license_key" name="paperlit_settings[paperlit_license_key_' . $item_slug . ']" style="width:100%" placeholder="' . __("Enter your license key", 'paperlit-addons' ) . '"></p>';
    }
    elseif( $free ) {
      $html .= '<p class="paperlit-theme-description paperlit-theme-description">'.__( "Free ", 'paperlit-themes' ). '</p>';
    }
    else {
      $html .= '<p class="paperlit-theme-description paperlit-theme-description">'.__( "Price ", 'paperlit-themes' ). $item_price .'</p>';
    }

    $html .= '<h3 class="theme-name" id="' . $item_id . '-name">' . $item_name . '</h3>';
    $html .= '<div class="theme-actions">';
    if ( $installed && $activated && !$free ) {
      $html .= '<input type="submit" class="button button-primary paperlit-theme-deactivate" name="paperlit_license_key_' . $item_slug . '_deactivate" value="' . __( "Deactivate", 'paperlit-addons' ) . '"/>';
    }
    else if ( $installed && !$free ) {
      $html .= '<input type="submit" class="button button-primary paperlit-theme-activate" name="paperlit_license_key_' . $item_slug . '_activate" value="' . __( "Activate", 'paperlit-addons' ) . '"/>';
    }
    elseif( !$installed && $free ) {
      $html .= '<a class="button button-primary paperlit-theme-deactivate" target="_blank" href="'.$item_link.'">'.__( "Download", 'paperlit-themes' ).'</a>';
    }
    elseif( !$installed  && !$free ) {
      $html .= '<a class="button button-primary paperlit-theme-deactivate" target="_blank" href="'.$item_link.'">'.__( "Buy", 'paperlit-themes' ).'</a>';
    }

    $html .= '</div>
    <input type="hidden" name="item_' . $item_slug . '_name" value="' . $item_name . '" />
    <input type="hidden" name="item_slug" value="' . $item_slug . '" />
    <input type="hidden" name="return_page" value="paperlit-themes" />
    <input type="hidden" name="type" value="theme" />
    </form>
    </div>';

    return $html;
  }

 /**
 * Render a single theme
 * @param array $theme
 * @return string
 */
 public function render_theme_installed( $theme ) {

   $html = '<div class="theme ' . ( $theme['active'] ? 'active' : '' ) . '" data-name="' . $theme['uniqueid'] . '" tabindex="0">
   <div class="theme-screenshot paperlit-theme-screenshot">
   <img src="' . PAPERLIT_THEME_URI . $theme['path'] . DS . $theme['thumbnail'] . '" alt="">
   </div>
   <p class="paperlit-theme-description">' . $theme['description'] . '</p>
   <p class="paperlit-theme-description">
   <span class="paperlit-theme-version">' . __("Version ", 'paperlit-themes' ) . $theme['version'] . '</span>
   <span>' . __("Made by", 'paperlit-themes' ) . ' <a href="' . $theme['author_site'] . '" target="_blank">' . $theme['author_name'] . '</a></span>
   <span>' . __("Theme url", 'paperlit-themes' ) . ' <a href="' . $theme['website'] . '" target="_blank">' . $theme['website'] . '</a></span>
   </p>';

   if ( $theme['active'] ) {
     $html.= '<h3 class="theme-name" id="' . $theme['uniqueid'] . '-name"><span>Attivo:</span> ' . $theme['name'] . '</h3>
     <div class="theme-actions">
     <a class="button button-primary paperlit-theme-deactivate" href="' . admin_url('admin.php?page=paperlit-themes&theme_id='. $theme['uniqueid'] .'&theme_status=false') . '">Deactivate</a>
     <a class="button button-secondary paperlit-theme-delete" href="#">Delete</a>
     </div>';
   }
   else {
     $html.= '<h3 class="theme-name" id="' . $theme['uniqueid'] . '-name">' . $theme['name'] . '</h3>
     <div class="theme-actions paperlit-theme-actions">
     <a class="button button-primary paperlit-theme-activate" href="' . admin_url('admin.php?page=paperlit-themes&theme_id='. $theme['uniqueid'] .'&theme_status=true') . '">Activate</a>
     <a class="button button-secondary paperlit-theme-delete" href="#">Delete</a>
     </div>';
   }

   $html.= '</div>';

   return $html;
 }

  /**
   * Render themes page
   * @echo
   */
  public function paperlit_themes_page() {

    $this->_update_theme_status();
    $this->_upload_theme();

    echo '<div class="wrap" id="themes-container">
    <h2>Paperlit Themes
    <a href="#" class="button button-primary right" id="paperlit-flush-themes-cache">' . __("Flush themes cache", 'paperlit-themes') . '</a>
    </h2>
    <br/>';

    $current_user = wp_get_current_user();

    echo '<h2 class="nav-tab-wrapper paperlit-tab-wrapper">';
    echo '<a class="nav-tab nav-tab-active' . '" data-tab="installed" href="#">' . __('Installed', 'paperlit-themes') . '</a>';
    echo '</h2>';
    echo '<div id="paperlit-progressbar"></div><br/>';

    echo'
    <div class="theme-browser rendered" id="themes-installed">
    <div class="themes">';

    $installed_themes = Paperlit_Theme::get_themes();
    if( $installed_themes ) {
      foreach ( $installed_themes as $theme ) {
        if( isset( $theme['paid'] ) && $theme['paid'] ) {
          $theme = array(
            'slug'      =>  $theme['uniqueid'],
            'title'     =>  $theme['name'],
            'thumbnail' =>  PAPERLIT_THEME_URI . $theme['path'] . DS . $theme['thumbnail'],
            'content'   =>  $theme['description'],
          );
          $this->prepare_theme( $theme, false );
        }
        else {
          echo $this->render_theme_installed( $theme );
        }
      }
    }
    echo '<div class="theme add-new-theme">
    <form method="post" id="paperlit-theme-form" enctype="multipart/form-data">
    <a href="#" id="paperlit-theme-add">
    <div class="theme-screenshot"><span></span></div>
    <h3 class="theme-name">Aggiungi un nuovo tema</h3>
    </a>
    <input type="file" name="paperlit-theme-upload" id="paperlit-theme-upload" accept="zip"  />
    </form>
    </div>
    </div>
    <br class="clear">
    </div>

    </div>';
  }

  /**
   * Check theme status before render
   * @param  array $theme
   * @param  boolean $is_free
   *
   * @echo
   */
  public function prepare_theme( $theme, $is_free ) {

    $paperlit_license = new Paperlit_EDD_License( __FILE__, $theme['slug'], '1.0', 'Paperlit' );
    $available_themes = get_option('paperlit_themes');
    $is_installed = false;

    if( isset( $available_themes[$theme['slug']] ) ) {
      $filepath = isset( $available_themes[$theme['slug']]['path'] ) ? PAPERLIT_THEMES_PATH . $available_themes[$theme['slug']]['path'] : false;
      // check if file exist and is out of paperlit plugin dir ( embedded web exporter )
      if ( file_exists( $filepath ) ) {
        $is_installed = true;
      }

    }

    $is_activated = Paperlit_EDD_License::check_license( $theme['slug'], $theme['title'], $is_free  );
    if( !$is_installed) {
      echo $this->render_theme( $theme, $is_installed, $is_activated, $is_free  );
    }    
  }

  /**
   * Add custom scripts to footer
   *
   * @void
   */
  public function add_custom_scripts() {

    global $pagenow;
    if( $pagenow == 'admin.php' && sanitize_text_field(esc_attr($_GET['page'])) == 'paperlit-themes' ) {
      wp_register_script( "nanobar", PAPERLIT_ASSETS_URI . "/js/nanobar.min.js", array( 'jquery' ), '1.0', true );
      wp_enqueue_script( "nanobar" );
      wp_register_script( "paperlit_themes_page", PAPERLIT_ASSETS_URI . "/js/paperlit.themes_page.js", array( 'jquery' ), '1.0', true );
      wp_enqueue_script( "paperlit_themes_page" );
      wp_localize_script( "paperlit_themes_page", "paperlit", $this->_get_i18n_strings() );
    }
  }

  /**
   * Ajax function for delete theme
   * @return json string
   */
  public function paperlit_delete_theme() {

    if ( isset( $_POST['theme_id'] ) && strlen( $_POST['theme_id'] ) ) {
      if ( Paperlit_Theme::delete_theme( sanitize_text_field(esc_attr($_POST['theme_id'])) ) ) {
        delete_option( 'paperlit_themes' );
        wp_send_json_success();
      }
    }
    wp_send_json_error();
  }

  /**
   * Ajax function for upload theme
   * @return json string
   */
  public function paperlit_upload_theme() {

    if ( $this->_upload_theme() ) {
      wp_send_json_success();
    }
    wp_send_json_error();
  }

  /*
   * Ajax function for upload theme
   * @return json string
   */
  public function paperlit_upload_theme_paperlit() {

    if ( $this->upload_theme_paperlit() ) {
      wp_send_json_success();
    }
    wp_send_json_error();
  }

  /**
   * Ajax function for flush themes cache
   * @return json string
   */
  public function paperlit_flush_themes_cache() {

    if ( delete_option( 'paperlit_themes' ) ) {
      wp_send_json_success();
    }
    wp_send_json_error();
  }

  /**
   * Ajax callback to dismiss notice
   *
   * @json success
   */
  public function dismiss_notice() {

    $current_user = wp_get_current_user();
    if( update_user_meta($current_user->ID, 'paperlit_themes_notice_' . intval(esc_attr($_POST['id'])), true) ) {
      wp_send_json_success();
    }

  }

  /**
   * Script i18n
   * @return array
   */
  protected function _get_i18n_strings() {

    return array(
      'delete_confirm'      => __( "Are you sure you want to delete this theme?", 'paperlit-themes' ),
      'delete_failed'       => __( "An error occurred during deletion", 'paperlit-themes' ),
      'theme_upload_error'  => __( "An error occurred during theme upload", 'paperlit-themes' ),
      'flush_failed'        => __( "An error occurred during cache flush", 'paperlit-themes' ),
      'flush_redirect_url'  => admin_url('admin.php?page=paperlit-themes&refresh_cache=true&pmtype=updated&pmcode=themes_cache_flushed'),
    );
  }

  /**
   * Upload a new theme
   * @return boolean
   */
  protected function _upload_theme() {

    if ( !empty( $_FILES['paperlit-theme-upload'] ) ) {
      if ( !function_exists( 'wp_handle_upload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
      }

      $folder = PAPERLIT_THEMES_DEFAULT_PATH;

      // copy from source to dst
      self::recurse_copy($folder, PAPERLIT_THEMES_PATH);

      // unzip files zip in the destination folder
      self::extractZip(PAPERLIT_THEMES_PATH);
      
      if (is_dir($folder) !== false) {
        delete_option( 'paperlit_themes' );
        self::rrmdir($folder); // delete the folder
      }

      return false;
    }
  }

  /**
  * Active / Deactive theme
  * @void
  */
  protected function _update_theme_status() {

    if ( isset( $_GET['theme_status'], $_GET['theme_id'] ) && strlen( $_GET['theme_id'] ) ) {
      Paperlit_Theme::set_theme_status( sanitize_text_field(esc_attr($_GET['theme_id'])), sanitize_text_field(esc_attr($_GET['theme_status'])) == 'true' );
    }
  }

  /**
   * Upload a new theme paperlit
   * @return boolean
   */
  public function upload_theme_paperlit() {
    if ( !function_exists( 'wp_handle_upload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
      }

      $folder = PAPERLIT_THEMES_DEFAULT_PATH;

      // copy from source to dst
      self::recurse_copy($folder, PAPERLIT_THEMES_PATH);

      // unzip files zip in the destination folder
      self::extractZip(PAPERLIT_THEMES_PATH);

      if (is_dir($folder) !== false) {
        delete_option( 'paperlit_themes' );
        self::rrmdir($folder); // delete the folder
      }
      return true;
    }

  private function recurse_copy($src, $dst) {
    if (is_dir($src) !== false) { 
      $dir = opendir($src); 
      @mkdir($dst); 
      while(false !== ( $file = readdir($dir)) ) { 
          if (( $file != '.' ) && ( $file != '..' )) { 
              if ( is_dir($src . '/' . $file) ) { 
                  self::recurse_copy($src . '/' . $file,$dst . '/' . $file); 
              } 
              else { 
                  copy($src . '/' . $file,$dst . '/' . $file); 
              } 
          } 
      } 
      closedir($dir); 
    }
  } 

 function rrmdir($dir) { 
   if (is_dir($dir)) { 
     $objects = scandir($dir); 
     foreach ($objects as $object) { 
       if ($object != "." && $object != "..") { 
         if (filetype($dir."/".$object) == "dir") self::rrmdir($dir."/".$object); else unlink($dir."/".$object); 
       } 
     } 
     reset($objects); 
     rmdir($dir); 
     } 
  } 

function extractZip($dst) {
  $zipes  = glob(PAPERLIT_THEMES_PATH.'*.{zip,rar}', GLOB_BRACE);

  foreach ($zipes as $zipFile) 
  { 
    $zip = new ZipArchive;
    $attached_file = $zipFile;

    if ( $zip->open( $attached_file ) ) {
      $zip->extractTo(PAPERLIT_THEMES_PATH); 
    }
    $zip->close();
    
    // delete the file
    unlink($attached_file);
    }
    return true;
  }

}
$paperlit_themes_page = new Paperlit_themes_page();