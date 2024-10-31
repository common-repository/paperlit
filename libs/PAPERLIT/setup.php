<?php
/**
 * Paperlit setup class.
 * Add custom tables into database
 * Set cronjobs
 */

class Paperlit_Setup
{

  public function __construct() {}

  /**
  * Plugin installation
  *
  * @return boolean or array of error messages
  */
  public static function install() {

    $errors = array();
    $check_libs = self::_check_php_libs();
    if ( $check_libs ) {
      array_push( $errors, __( "Missing required extensions: <b>" . implode( ', ', $check_libs ) . "</b>", 'paperlit_setup' ) );
    }
    if ( !self::setup_db_tables() ) {
      array_push( $errors, __( "Error creating required tables. Check your database permissions.", 'paperlit_setup' ) );
    }
    if ( !self::_setup_filesystem() ) {
      array_push( $errors, __( "Error creating required directory: <b>&quot;" . PAPERLIT_ROOT . "api/&quot;</b> or <b>&quot;" . PAPERLIT_UPLOAD_PATH . "api/&quot;</b>. Check your write files permissions.", 'paperlit_setup' ) );
    }

    Paperlit_Cron::setup();

    if ( !empty( $errors ) ) {
      return $errors;
    }

    return false;
  }

  public static function check_folder_system() {

    $wp_upload_dir = wp_upload_dir();
    $api_dir = Paperlit_Utils::make_dir( PAPERLIT_ROOT, 'api' );
    $upload_dir = Paperlit_Utils::make_dir( $wp_upload_dir['basedir'], 'paperlit' );

    if ( !$api_dir || !$upload_dir ) {
      return false;
    }

    $api_dir = $api_dir && Paperlit_Utils::make_dir( PAPERLIT_API_PATH, 'tmp' );
    $api_dir = $api_dir && Paperlit_Utils::make_dir( PAPERLIT_TMP_PATH, 'preview' );

    $upload_dir = Paperlit_Utils::make_dir( PAPERLIT_UPLOAD_PATH, 'hpub' );
    $upload_dir = $upload_dir && Paperlit_Utils::make_dir( PAPERLIT_UPLOAD_PATH, 'web' );
    $upload_dir = $upload_dir && Paperlit_Utils::make_dir( PAPERLIT_UPLOAD_PATH, 'shelf' );
    $upload_dir = $upload_dir && Paperlit_Utils::make_dir( PAPERLIT_UPLOAD_PATH, 'themes' );
    $upload_dir = $upload_dir && Paperlit_Utils::make_dir( PAPERLIT_UPLOAD_PATH, 'settings' );

    // add themes paperlit in the setup plugins
    $paperlit_themes_page   = new Paperlit_themes_page();    
    $paperlit_upload_themes = $paperlit_themes_page->upload_theme_paperlit(); 
    
    // remove the folder themes in the zip paperlit in the setup plugins
    Paperlit_Utils::remove_dir(PAPERLIT_THEMES_DEFAULT_PATH);

    return !$api_dir && !$upload_dir ? false : true;
 }

  /**
   * Install supporting tables
   *
   * @return boolean
   */
  public static function setup_db_tables() {

    global $wpdb;
    $table_receipts = $wpdb->prefix . PAPERLIT_TABLE_RECEIPTS;
    $table_purchased_issues = $wpdb->prefix . PAPERLIT_TABLE_PURCHASED_ISSUES;
    $table_auth_tokens = $wpdb->prefix . PAPERLIT_TABLE_AUTH_TOKENS;
    $table_stats = $wpdb->prefix . PAPERLIT_TABLE_STATS;
    $table_logs = $wpdb->prefix . PAPERLIT_TABLE_LOGS;

    $charset_collate = '';
    if ( !empty( $wpdb->charset ) ) {
      $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
    }

    if ( ! empty( $wpdb->collate ) ) {
      $charset_collate .= " COLLATE {$wpdb->collate}";
    }

    if ( $wpdb->get_var( "SHOW TABLES LIKE '". $table_receipts."'") != $table_receipts ) {
      $sql_receipts = "CREATE TABLE $table_receipts (
      receipt_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      app_bundle_id VARCHAR(128),
      device_id VARCHAR(60),
      transaction_id VARCHAR(32),
      base64_receipt TEXT CHARACTER SET ascii COLLATE ascii_bin,
      product_id VARCHAR(120),
      type VARCHAR(32),
      PRIMARY KEY  (receipt_id),
      INDEX app_and_user USING BTREE (app_bundle_id, device_id) COMMENT ''
      ) $charset_collate; ";

      require_once ABSPATH . 'wp-admin/includes/upgrade.php';
      dbDelta( $sql_receipts );
    }

    if ( $wpdb->get_var( "SHOW TABLES LIKE '". $table_purchased_issues."'" ) != $table_purchased_issues ) {
      $sql_purchased_issues = "CREATE TABLE $table_purchased_issues (
      app_id VARCHAR(120),
      user_id VARCHAR(120),
      product_id VARCHAR(120),
      PRIMARY KEY  (app_id, user_id, product_id)
      ) $charset_collate; ";

      require_once ABSPATH . 'wp-admin/includes/upgrade.php';
      dbDelta( $sql_purchased_issues );
    }

    if ( $wpdb->get_var( "SHOW TABLES LIKE '". $table_auth_tokens."'"  ) != $table_auth_tokens ) {
      $sql_auth_tokens = "CREATE TABLE $table_auth_tokens (
      app_id VARCHAR(120),
      user_id VARCHAR(120),
      access_token VARCHAR(120),
      created_time int(10) UNSIGNED NOT NULL,
      expires_in int(10) UNSIGNED NOT NULL,
      PRIMARY KEY  (app_id, user_id, access_token)
      ) $charset_collate; ";

      require_once ABSPATH . 'wp-admin/includes/upgrade.php';
      dbDelta( $sql_auth_tokens );
    }

    if ( $wpdb->get_var( "SHOW TABLES LIKE '". $table_stats."'"  ) != $table_stats ) {
      $sql_stats = "CREATE TABLE $table_stats (
      scenario VARCHAR(128),
      object_id INT(10) UNSIGNED NOT NULL,
      stat_date INT(10) UNSIGNED NOT NULL,
      counter INT(10) UNSIGNED NOT NULL,
      PRIMARY KEY  (scenario, stat_date, object_id)
      ) $charset_collate; ";

      require_once ABSPATH . 'wp-admin/includes/upgrade.php';
      dbDelta( $sql_stats );
    }

    if ( $wpdb->get_var( "SHOW TABLES LIKE '". $table_logs."'" ) != $table_logs ) {
      $sql_logs = "CREATE TABLE $table_logs (
        id int(10) unsigned NOT NULL AUTO_INCREMENT,
        action varchar(128) NOT NULL DEFAULT '',
        object_id int(10) unsigned NOT NULL,
        log_date int(10) unsigned NOT NULL,
        ip varchar(40) NOT NULL,
        detail longtext DEFAULT NULL,
        author int(10) unsigned NOT NULL,
        type varchar(50) NOT NULL,
        PRIMARY KEY  (id)
      ) $charset_collate;";

      require_once ABSPATH . 'wp-admin/includes/upgrade.php';
      dbDelta( $sql_logs );
    }

    update_option( "_paperlit_table_db_version", PAPERLIT_TABLE_DB_VERSION );
    return true;
  }

  /**
  * Check if the required libraries are installed
  *
  * @return boolean or array of errors
  */
  private static function _check_php_libs() {

    $errors = array();
    $extensions = array( 'zlib', 'zip', 'libxml' );
    foreach ( $extensions as $extension ) {

      if( !extension_loaded( $extension ) ) {
        array_push( $errors, $extension );
      }
    }

    if ( !empty( $errors ) )
    return $errors;

    return false;
  }

  /**
  * Install the plugin folders
  *
  * @return boolean
  */
  private static function _setup_filesystem() {

    $wp_upload_dir = wp_upload_dir();
    $api_dir = Paperlit_Utils::make_dir( PAPERLIT_ROOT, 'api' );
    $upload_dir = Paperlit_Utils::make_dir( $wp_upload_dir['basedir'], 'paperlit' );

    if ( !$api_dir || !$upload_dir ) {
      return false;
    }

    $api_dir = $api_dir && Paperlit_Utils::make_dir( PAPERLIT_API_PATH, 'tmp' );
    $api_dir = $api_dir && Paperlit_Utils::make_dir( PAPERLIT_TMP_PATH, 'preview' );

    $upload_dir = Paperlit_Utils::make_dir( PAPERLIT_UPLOAD_PATH, 'hpub' );
    $upload_dir = $upload_dir && Paperlit_Utils::make_dir( PAPERLIT_UPLOAD_PATH, 'web' );
    $upload_dir = $upload_dir && Paperlit_Utils::make_dir( PAPERLIT_UPLOAD_PATH, 'shelf' );
    $upload_dir = $upload_dir && Paperlit_Utils::make_dir( PAPERLIT_UPLOAD_PATH, 'themes' );
    $upload_dir = $upload_dir && Paperlit_Utils::make_dir( PAPERLIT_UPLOAD_PATH, 'settings' );

    // add themes paperlit in the setup plugins
    $paperlit_themes_page   = new Paperlit_themes_page();    
    $paperlit_upload_themes = $paperlit_themes_page->upload_theme_paperlit(); 
    
    // remove the folder themes ion the zip paperlit in the setup plugins
    Paperlit_Utils::remove_dir(PAPERLIT_THEMES_DEFAULT_PATH);

    return !$api_dir && !$upload_dir ? false : true;
  }
}
