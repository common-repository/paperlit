<?php
define( 'PAPERLIT_ADDON_CONFIG_FILE', 'config.xml' );

class Paperlit_Addons
{
	protected static $_addons = array();
	protected static $_errors = array();

	public function __construct() {
		$this->search();
	}

	/**
	 * Search addons installed
	 *
	 * @void
	 */
	public function search() {
		$settings = get_option( 'paperlit_settings' );
		$addons = $settings['paperlit_enabled_exporters'] ;
		$addons_to_check = array();

		foreach( $addons as $addon ) {
			if( !isset( $addon['config'] ) ) {
				continue;
			}

			$filename = basename( $addon['config'] );
			if ( $filename != PAPERLIT_ADDON_CONFIG_FILE ) {
				return;
			}
			array_push( $addons_to_check, $addon);
		}

		$addons = self::_validate_addons( $addons_to_check );
		if ( empty( self::$_errors ) ) {
			$settings['paperlit_enabled_exporters'] = $addons;
			update_option( 'paperlit_settings', $settings );
		}
	}

	/**
	 * Get add-ons objects
	 *
	 * @return array
	 */
	public static function get() {
		$model = new self();
		return $model::$_addons;
	}

	/**
	 * Validate addons array and check if all property is set
	 *
	 * @return array
	 */
	protected static function _validate_addons( $addons ) {

		self::$_errors = array();
		if ( empty($addons) ) {
			return;
		}

		foreach ( $addons as $k => $addon ) {
			$config_file = $addon['config'];
			$addon_meta = self::_parse_addon_config( $config_file );

			if ( !$addon_meta ) {
				array_push( self::$_errors, self::_addon_error_notice( 'Error: <b>malformed or missing xml config file ' . $config_file .'</b>' ) );
				continue;
			}

			if ( !$addon_meta['itemid'] ) {
				array_push( self::$_errors, self::_addon_error_notice( 'Error: <b>itemid can\'t be empty</b> in <b>' . $config_file .'</b>' ) );
				continue;
			}

			if ( empty( $addon_meta['slug'] ) ) {
				array_push( self::$_errors, self::_addon_error_notice( 'Error: <b>slug section can\'t be empty</b> in <b>' . $config_file .'</b>' ) );
				continue;
			}
			self::$_addons[$addon_meta['slug']] = array_merge( $addon, $addon_meta );
		}

		return self::$_addons;
	}

	/**
	 * Get property from config.xml file
	 * @param  string $config_file
	 *
	 * @return bool or array $metadata
	 */
	protected static function _parse_addon_config( $config_file ) {

		if(!file_exists( $config_file ) ) {
			return false;
		}

		$xml = simplexml_load_file( $config_file );

		if ( !isset( $xml->slug ) ) {
			return false;
		}

		// Get properties
		$metadata = array(
			'itemid'				=> isset( $xml->itemid ) ? $xml->itemid[0]->__toString() : false ,
			'name'					=> isset( $xml->name ) ? $xml->name[0]->__toString() : false ,
			'slug'					=> isset( $xml->slug ) ? $xml->slug[0]->__toString() : false ,
			'version'				=> isset( $xml->version ) ? $xml->version[0]->__toString() : false ,
			'paid'					=> isset( $xml->paid ) ? $xml->paid[0]->__toString() : false ,
			'description'		=> isset( $xml->description ) ? $xml->description[0]->__toString() : false ,
		);

		return $metadata;
	}


  /**
   * Generate a error notice for config files
   * @param  string $message
   *
   * @return string;
   */
  protected static function _addon_error_notice( $message ) {

    $html = '<div class="error">';
    $html.= '<p>' . _e( $message ) . '</p>';
    $html.= '</div>';

    return $html;
  }
}
