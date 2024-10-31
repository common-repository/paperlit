<?php
/**
 * Paperlit setup cronjobs.
 */

class Paperlit_Cron
{

  public function __construct() {}

 /**
  * Setup cronjobs
  *
  * @void
  */
  public static function setup() {
    wp_schedule_event( time(), 'daily', 'paperlit_checkexpiredtoken' );
    wp_schedule_event( time(), 'monthly', 'paperlit_clean_logs' );
  }

  /**
   * Clear schedule on deactivation
   *
   * @void
   */
  public static function disable() {
    wp_clear_scheduled_hook( 'paperlit_checkexpiredtoken' );
    wp_clear_scheduled_hook( 'paperlit_clean_logs' );
  }

}

/**
 * Remove expired token
 *
 * @void
 */
function do_checkexpiredtoken() {
  global $wpdb;
  $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . PAPERLIT_TABLE_AUTH_TOKENS . ' WHERE ( created_time + expires_in ) < UNIX_TIMESTAMP()' );
}
add_action( 'paperlit_checkexpiredtoken', 'do_checkexpiredtoken');

/**
 * Add monthly schedule
 *
 * @param array $schedules
 */
function paperlit_add_a_cron_schedule( $schedules ) {

    $schedules['monthly'] = array(

        'interval' => 108000, // month

        'display'  => __( 'Monthly' ),
    );

    return $schedules;
}
add_filter( 'cron_schedules', 'paperlit_add_a_cron_schedule' );

/**
 * Remove old logs
 *
 * @void
 */
function do_cleanlogs() {

  $expiry_time = 3600 * 24 * 90; // 90 days
  global $wpdb;
  $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . PAPERLIT_TABLE_LOGS . ' WHERE ( log_date + '.$expiry_time.' ) < UNIX_TIMESTAMP()' );
}
add_action( 'paperlit_clean_logs', 'do_cleanlogs');
