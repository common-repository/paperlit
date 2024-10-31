<?php

class Paperlit_addons_page {

  public $paperlit_options = array();

  public function __construct() {

    if( !is_admin() ) {
      return;
    }

    add_action( 'admin_footer', array( $this, 'add_custom_scripts' ) );
    add_action( 'admin_menu', array( $this, 'paperlit_add_admin_menu' ) );
    add_action( 'wp_ajax_paperlit_get_remote_addons', array( $this, 'get_remote_addons' ) );
    add_action( 'wp_ajax_paperlit_dismiss_notice', array( $this, 'dismiss_notice' ) );

    $this->paperlit_options = get_option( 'paperlit_settings' );
  }

  /**
  * Add options page to wordpress menu
  */
  public function paperlit_add_admin_menu() {
    add_submenu_page( 'paperlit', __( 'Add-ons' ), __( 'Add-ons' ), 'manage_options', 'paperlit-addons', array( $this, 'paperlit_addons_page' ));
  }

  /**
  * Render a single addon
  * @param array $addon
  * @return string
  */
  public function render_add_on( $addon, $installed, $activated, $free, $trial ) {

    $options = get_option( 'paperlit_settings' );
    $item_id = $addon->info->id;
    $item_slug = $addon->info->slug;
    $item_name = $addon->info->title;

    if ( $trial ) {

      $item_price = '';
      $item_button = '';
      $i = 0;
      foreach ( $addon->pricing as $key => $price ) {
        $i++;
        if( $price > 0 ) {
          $item_price .= $price . '$' . ' ';
        }
        $item_link = PAPERLIT_API_URL . 'checkout?edd_action=add_to_cart&download_id=' . $addon->info->id . '&edd_options[price_id]=' . ( $i );
        $item_button .= '<a class="button button-primary paperlit-theme-deactivate" target="_blank" href="'.$item_link.'">'.__( $price == 0 ? 'Trial' : 'Buy', 'paperlit-themes' ).'</a>';
      }
    }
    else {
      $item_price = $addon->pricing->amount . '$';
      $item_link = PAPERLIT_API_URL . 'checkout?edd_action=add_to_cart&download_id=' . $addon->info->id;
      $item_button = '<a class="button button-primary paperlit-theme-deactivate" target="_blank" href="'.$item_link.'">'.__( "Buy", 'paperlit-themes' ).'</a>';
    }

    $html = '<div class="theme ' . ( $activated ? 'active' : '' ) . '" data-name="' . $item_id . '" tabindex="0">
    <form method="post" name="' . $item_slug . '">
    <div class="theme-screenshot paperlit-theme-screenshot">
    <img src="'.$addon->info->thumbnail.'" alt="">
    </div>
    <p class="paperlit-theme-description">' . $addon->info->content . '</p>
    <p class="paperlit-theme-description">';

    $html .= '
    <span>' . __("Category", 'paperlit-themes' ) . ' <a href="#" target="_blank">' . $addon->info->category[0]->name . '</a></span>
    </p>';

    if ( $installed && $activated ) {
      $html .= '<p class="paperlit-theme-description"><span>' . __("Your license key", 'paperlit-addons' ) . ' <b>' . $options['paperlit_license_key_' . $item_slug] . '</b></span></p>';
    }
    else if ( $installed ) {
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
    elseif ( $installed && !$free ) {
      $html .= '<input type="submit" class="button button-primary paperlit-theme-activate" name="paperlit_license_key_' . $item_slug . '_activate" value="' . __( "Activate", 'paperlit-addons' ) . '"/>';
    }
    elseif( !$installed && $free ) {
      $html .= $item_button;
    }
    elseif( !$installed  && !$free ) {
      $html .= $item_button;
    }

    $html .= '</div>
    <input type="hidden" name="item_' . $item_slug . '_name" value="' . $item_name . '" />
    <input type="hidden" name="item_slug" value="' . $item_slug . '" />
    <input type="hidden" name="return_page" value="paperlit-addons" />
    <input type="hidden" name="type" value="exporter" />
    </form>
    </div>';

    return $html;
  }

  /**
  * Render a single installed addon
  * @param array $addon
  * @return string
  */
  public function render_installed_addon( $addon, $installed, $activated, $free ) {

    $options = get_option( 'paperlit_settings' );
    $item_id = $addon['itemid'];
    $item_slug = $addon['slug'];
    $item_name = $addon['name'];
    $item_thumbnail = plugins_url( $addon['dir'] ) . '/thumb.png';

    $html = '<div class="theme ' . ( $activated ? 'active' : '' ) . '" data-name="' . $item_id . '" tabindex="0">
    <form method="post" name="' . $item_slug . '">
    <div class="theme-screenshot paperlit-theme-screenshot">
    <img src="'.$item_thumbnail.'" alt="">
    </div>
    <p class="paperlit-theme-description">' . $addon['description'] . '</p>';

    if ( $installed && $activated ) {
      $html .= '<p class="paperlit-theme-description"><span>' . __("Your license key", 'paperlit-addons' ) . ' <b>' . $options['paperlit_license_key_' . $item_slug] . '</b></span></p>';
    }
    else if ( $installed ) {
      $html .= '<p class="paperlit-theme-description paperlit-theme-description-input"><input type="text" id="paperlit_license_key" name="paperlit_settings[paperlit_license_key_' . $item_slug . ']" style="width:100%" placeholder="' . __("Enter your license key", 'paperlit-addons' ) . '"></p>';
    }
    elseif( $free ) {
      $html .= '<p class="paperlit-theme-description paperlit-theme-description">'.__( "Free ", 'paperlit-themes' ). '</p>';
    }

    $html .= '<h3 class="theme-name" id="' . $item_id . '-name">' . $item_name . '</h3>';
    $html .= '<div class="theme-actions">';
    if ( $installed && $activated && !$free ) {
      $html .= '<input type="submit" class="button button-primary paperlit-theme-deactivate" name="paperlit_license_key_' . $item_slug . '_deactivate" value="' . __( "Deactivate", 'paperlit-addons' ) . '"/>';
    }
    else if ( $installed && !$free ) {
      $html .= '<input type="submit" class="button button-primary paperlit-theme-activate" name="paperlit_license_key_' . $item_slug . '_activate" value="' . __( "Activate", 'paperlit-addons' ) . '"/>';
    }

    $html .= '</div>
    <input type="hidden" name="item_' . $item_slug . '_name" value="' . $item_name . '" />
    <input type="hidden" name="item_slug" value="' . $item_slug . '" />
    <input type="hidden" name="return_page" value="paperlit-addons" />
    <input type="hidden" name="type" value="exporter" />
    </form>
    </div>';

    return $html;
  }

  /**
   * Render addons page
   * @echo
   */
  public function paperlit_addons_page() {

    $enabled_exporters = isset( $this->paperlit_options['paperlit_enabled_exporters'] ) ? $this->paperlit_options['paperlit_enabled_exporters'] : false ;
    $addons = Paperlit_Addons::get();
    $current_user = wp_get_current_user();

    echo '<div class="wrap" id="addons-container">
    <h2>Paperlit Add-ons</h2>';

    echo '<h2 class="nav-tab-wrapper paperlit-tab-wrapper">';
    echo '<a class="nav-tab nav-tab-active' . '" data-tab="installed" href="#">' . __('Installed', 'paperlit-addons') . '</a>';
    echo '</h2>';
    echo '<div id="paperlit-progressbar"></div><br/>';


    echo'
    <div class="theme-browser rendered" id="addons-installed">
    <div class="themes">';
    if ( $addons ) {
      foreach ( $addons as $addon ) {

        $is_installed = false;
        $filepath = isset( $addon['filepath'] ) ? $addon['filepath'] : false ;

        // check if file exist and is out of paperlit plugin dir ( embedded web exporter )
        if ( file_exists( $filepath ) ) {
          $is_installed = true;
        }

        $is_free = isset( $addon['paid'] ) && $addon['paid'] ? false : true;

        $is_activated = Paperlit_EDD_License::check_license( $addon['slug'], $addon['name'], $is_free );

        echo $this->render_installed_addon( $addon, $is_installed, $is_activated, $is_free );
      }
    }

    echo '
    </div>
    <br class="clear">
    </div>
    </div>';
  }

  /**
   * Get addons list from online feed
   *
   * @echo
   */
  public function get_remote_addons() {

    $enabled_exporters = isset( $this->paperlit_options['paperlit_enabled_exporters'] ) ? $this->paperlit_options['paperlit_enabled_exporters'] : false ;
    $addons = Paperlit_Addons::get_remote_addons();
    echo'
    <div class="theme-browser rendered" id="addons-remote">
    <div class="themes">';
    if ( $addons ) {
      foreach ( $addons as $addon ) {

        $is_installed = false;
        $is_trial = false;
        
        $filepath = isset( $enabled_exporters[$addon->info->slug]['filepath'] ) ? $enabled_exporters[$addon->info->slug]['filepath'] : false ;
        // check if file exist and is out of paperlit plugin dir ( embedded web exporter )
        if ( file_exists( $filepath ) ) {
          $is_installed = true;
        }
        if( isset( $addon->pricing->amount ) ) {
          $is_free = $addon->pricing->amount == 0 ? true : false;
        }
        else {
          foreach( $addon->pricing as $price ) {

            if( $price == 0 ) {
              $is_free = true;
              $is_trial = true;
            }
            else {
              $is_free = false;
            }
          }
        }


        $is_activated = Paperlit_EDD_License::check_license( $addon->info->slug, $addon->info->title, $is_free );
        if( !$is_installed ) {
          echo $this->render_add_on( $addon, $is_installed, $is_activated, $is_free, $is_trial );
        }
      }
    }

    echo '
    </div>
    <br class="clear">
    </div>';
  }

  /**
   * Ajax callback to dismiss notice
   *
   * @json success
   */
  public function dismiss_notice() {

    $current_user = wp_get_current_user();
    if( update_user_meta($current_user->ID, 'paperlit_addons_notice_' . intval(esc_attr($_POST['id'])), true) ) {
      wp_send_json_success();
    }

  }
  /**
   * Add custom scripts to footer
   *
   * @void
   */
  public function add_custom_scripts() {

    global $pagenow;
    if( $pagenow == 'admin.php' && sanitize_text_field(esc_attr($_GET['page'])) == 'paperlit-addons' ) {
      wp_register_script( "nanobar", PAPERLIT_ASSETS_URI . "/js/nanobar.min.js", array( 'jquery' ), '1.0', true );
      wp_enqueue_script( "nanobar" );
      wp_register_script( "paperlit_addons_page", PAPERLIT_ASSETS_URI . "/js/paperlit.addons_page.js", array( 'jquery' ), '1.0', true );
      wp_enqueue_script( "paperlit_addons_page" );
      //wp_localize_script( "paperlit_themes_page", "pr", $this->_get_i18n_strings() );
    }
  }

  /**
  * Active / Deactive addon
  * @void
  */
  protected function _update_add_on_status() {

    if ( isset( $_GET['add_on_status'], $_GET['add_on_slug'] ) && strlen( $_GET['add_on_slug'] ) ) {
      Paperlit_Addons::set_add_on_status(sanitize_title_with_dashes($_GET['add_on_slug']), sanitize_text_field(esc_attr($_GET['add_on_status'])) == 'true' );
    }
  }
}

$paperlit_addons_page = new Paperlit_addons_page();
