<?php

class Paperlit_logs_page {

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

    add_action( 'admin_menu', array( $this, 'paperlit_add_admin_menu' ) );
    add_action( 'admin_footer', array( $this, 'register_logs_script' ) );
  }

  /**
   * Add logs page to wordpress menu
   */
  public function paperlit_add_admin_menu() {

    add_submenu_page( 'paperlit', __( 'Exporter logs' ), __( 'Exporter logs' ), 'manage_options', 'paperlit-logs', array( $this, 'paperlit_logs_page' ) );
  }

  /**
   * Render logs page form
   *
   * @echo
   */
  public function paperlit_logs_page() {

    ?>
    <h2>Paperlit exporter logs</h2>
    <?php
    $this->add_presslist_logs();
  }

  /**
  * Paperlit metabox callback
  * Render Wp list table
  *
  * @return void
  */
  public function add_presslist_logs() {

    $paperlit_table = new Paperlit_Logs_Table();
    $paperlit_table->prepare_items();
    $paperlit_table->display();
  }

  public function register_logs_script() {
    Paperlit_Logs_Table::add_logslist_scripts();
  }
}
$paperlit_logs_page = new Paperlit_logs_page();
