<?php

class Paperlit_Server
{
  public function __construct() {

    $files = Paperlit_Utils::search_files( __DIR__ , 'php' );
    if ( !empty( $files ) ) {
      foreach ( $files as $file ) {
        require_once $file;
      }
    }
    
    $this->_load_connectors();
  }

  /**
   * Load plugin connectors
   *
   * @void
   */
  protected function _load_connectors() {

    if ( is_dir( PAPERLIT_SERVER_CONNECTORS_PATH ) ) {
      $files = Paperlit_Utils::search_files( PAPERLIT_SERVER_CONNECTORS_PATH, 'php' );
      if ( !empty( $files ) ) {
        foreach ( $files as $file ) {
          require_once $file;
        }
      }
    }
  }
}

/* instantiate the plugin class */
$paperlit_server = new Paperlit_Server();
