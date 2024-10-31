<?php

class Paperlit_options_page {

  /**
   * constructor method
   * Add class functions to wordpress hooks
   *
   * @void
   */
  public function __construct() {

    if( !is_admin() ) {
      return;
    }

    add_action( 'admin_enqueue_scripts', array( $this, 'add_chosen_script' ) );
    add_action( 'admin_footer', array( $this, 'add_custom_script' ) );

    add_action( 'admin_menu', array( $this, 'paperlit_add_admin_menu' ) );
    add_action( 'admin_init', array( $this, 'paperlit_settings_init' ) );
    add_filter( 'pre_update_option_paperlit_settings', array( $this, 'paperlit_save_options' ), 10, 2 );

    $this->_load_pages();
  }

  /**
   * Add options page to wordpress menu
   */
  public function paperlit_add_admin_menu() {

    add_menu_page( 'paperlit', 'Paperlit', 'manage_options', 'paperlit', array( $this, 'paperlit_options_page' ) );
    add_submenu_page('paperlit', __('Settings'), __('Settings'), 'manage_options', 'paperlit', array( $this, 'paperlit_options_page' ));
  }

  /**
   * register section field
   *
   * @void
   */
  public function paperlit_settings_init() {

  	register_setting( 'paperlit', 'paperlit_settings' );

  	add_settings_section(
  		'paperlit_paperlit_section',
  		'',
  		array( $this, 'paperlit_settings_section_callback' ),
  		'paperlit'
  	);

    add_settings_field(
      'custom_post_type',
      __( 'Custom post types', 'paperlit' ),
      array( $this, 'paperlit_custom_post_type' ),
      'paperlit',
      'paperlit_paperlit_section'
    );

    do_action( 'paperlit_add_extra_options' );
  }

  /**
   * Render custom post_type field
   *
   * @void
   */
  public function paperlit_custom_post_type() {

    $options = get_option( 'paperlit_settings' );
    $post_types = get_post_types( null, 'objects' );
    $excluded = apply_filters( 'paperlit_excluded_cpt_filter', array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'paperlit_edition' ));
    
    $enabled_posttype = isset( $options['paperlit_custom_post_type'] ) ? $options['paperlit_custom_post_type'] : [];
    if ( !is_array( $enabled_posttype ) ) {
      $enabled_posttype = [ $enabled_posttype ];
    }

    echo '<fieldset>
    <legend class="screen-reader-text"><span>' . __( 'Custom post types', 'paperlit' ) . '</span></legend>';
    $i = 0;
    foreach ( $post_types as $post_type ) {
      if ( !in_array( $post_type->name, $excluded ) ) {
        $checked = in_array( $post_type->name, $enabled_posttype ) ? 'checked="checked"' : '';
        echo '<label title="' . $post_type->name . '"><input type="checkbox" name="paperlit_settings[paperlit_custom_post_type][]" value="' . $post_type->name . '" ' . $checked . ' />' . $post_type->labels->name . '</span></label><br>';
        $i++;
      }
    }
    if ( !$i ) {
      echo '<span>' . __( 'No custom post types founds', 'paperlit' ) . '</span>';
    }
    echo '</fieldset>';
  }

  /**
   * render setting section
   *
   * @echo
   */
  public function paperlit_settings_section_callback() {

  	echo __( '<hr/>', 'paperlit' );

  }

  /**
   * Render option page form
   *
   * @echo
   */
  public function paperlit_options_page() {
  ?>
    <form action='options.php' method='post'>
    <h2>Paperlit Options</h2>
  <?php
  	settings_fields( 'paperlit' );
  	do_settings_sections( 'paperlit' );
  	submit_button();
  ?>
  	</form>
  <?php
  }

  /**
   * filter value before saving.
   *
   * @param array $new_value
   * @param array $old_value
   * @return array $new_value
   */
  public function paperlit_save_options( $new_value, $old_value ) {

    if( isset( $new_value['paperlit_custom_post_type'] ) ) {
      $post_type = is_array( $new_value['paperlit_custom_post_type'] ) ? $new_value['paperlit_custom_post_type'] : explode( ',', $new_value['paperlit_custom_post_type'] );
      $new_value['paperlit_custom_post_type'] = $post_type;
    }

    return $new_value;
  }

  /**
   * add custom script to metabox
   *
   * @void
   */
  public function add_custom_script() {

    global $pagenow;
    if( $pagenow == 'admin.php' && sanitize_text_field(esc_attr($_GET['page'])) == 'paperlit' ) {
      wp_register_script( 'options_page', PAPERLIT_ASSETS_URI . '/js/paperlit.option_page.js', array( 'jquery' ), '1.0', true );
      wp_enqueue_script( 'options_page' );
    }
  }

  /**
   * add chosen.js to metabox
   *
   * @void
   */
  public function add_chosen_script() {

    global $pagenow;
    if( $pagenow == 'admin.php' && sanitize_text_field(esc_attr($_GET['page'])) == 'paperlit' ) {
      wp_enqueue_style( 'chosen', PAPERLIT_ASSETS_URI . 'css/chosen.min.css' );
      wp_register_script( 'chosen', PAPERLIT_ASSETS_URI . '/js/chosen.jquery.min.js', array( 'jquery'), '1.0', true );
      wp_enqueue_script( 'chosen' );
    }
  }

  /**
	 * Load plugin pages
	 *
	 * @void
	 */
  protected function _load_pages() {
    $files = Paperlit_Utils::search_files( __DIR__, 'php' );
  	if ( !empty( $files ) ) {
      foreach ( $files as $file ) {
        require_once $file;
      }
    }
  }
}

$paperlit_options_page = new Paperlit_options_page();
