<?php
if ( ! defined( 'DS' ) ) {
	define( "DS", DIRECTORY_SEPARATOR );
}

// Core
define( "PAPERLIT_ROOT", plugin_dir_path( __FILE__ ) );
define( "PAPERLIT_PAGES_PATH", trailingslashit( PAPERLIT_ROOT . 'pages' ) );
define( "PAPERLIT_TAXONOMIES_PATH", trailingslashit( PAPERLIT_ROOT . 'taxonomies' ) );
define( "PAPERLIT_POST_TYPES_PATH", trailingslashit( PAPERLIT_ROOT . 'post_types' ) );
define( "PAPERLIT_LIBS_PATH", trailingslashit( PAPERLIT_ROOT . 'libs' ) );

// Packager
define( "PAPERLIT_PACKAGER_PATH", trailingslashit( PAPERLIT_ROOT . 'packager' ) );
define( "PAPERLIT_PACKAGER_CONNECTORS_PATH", trailingslashit( PAPERLIT_PACKAGER_PATH . 'connectors' ) );
define( "PAPERLIT_PACKAGER_EXPORTERS_PATH", trailingslashit( PAPERLIT_PACKAGER_PATH . 'exporters' ) );

// Server
define( "PAPERLIT_SERVER_PATH", trailingslashit( PAPERLIT_ROOT . 'server' ) );
define( "PAPERLIT_SERVER_CONNECTORS_PATH", trailingslashit( PAPERLIT_SERVER_PATH . 'connectors' ) );

// Preview
define( "PAPERLIT_PREVIEW_PATH", trailingslashit( PAPERLIT_ROOT . 'preview' ) );

// API
define( "PAPERLIT_API_PATH", trailingslashit( PAPERLIT_ROOT . 'api' ) );
define( "PAPERLIT_TMP_PATH", trailingslashit( PAPERLIT_API_PATH . 'tmp' ) );
define( "PAPERLIT_PREVIEW_TMP_PATH", trailingslashit( PAPERLIT_TMP_PATH . 'preview' ) );
define( "PAPERLIT_THEMES_DEFAULT_PATH", trailingslashit(PAPERLIT_ROOT. 'themes') );

// URL
define( "PAPERLIT_PLUGIN_URI", plugin_dir_url( PAPERLIT_LIBS_PATH ) );
define( "PAPERLIT_CORE_URI", PAPERLIT_PLUGIN_URI . 'core/' );
define( "PAPERLIT_ASSETS_URI", PAPERLIT_PLUGIN_URI. 'assets/' );
define( "PAPERLIT_PREVIEW_URI", PAPERLIT_PLUGIN_URI . 'api/tmp/preview/' );

// UPLOADS
$upload_dir = wp_upload_dir();
define( "PAPERLIT_UPLOAD_PATH", $upload_dir['basedir'] . '/paperlit/' );
define( "PAPERLIT_HPUB_PATH", trailingslashit( PAPERLIT_UPLOAD_PATH . 'hpub' ) );
define( "PAPERLIT_WEB_PATH", trailingslashit( PAPERLIT_UPLOAD_PATH . 'web' ) );
define( "PAPERLIT_SHELF_PATH", trailingslashit( PAPERLIT_UPLOAD_PATH . 'shelf' ) );
define( "PAPERLIT_IOS_SETTINGS_PATH", PAPERLIT_UPLOAD_PATH . 'settings/' );

define( "PAPERLIT_UPLOAD_URI", $upload_dir['baseurl'] . '/paperlit/' );
define( "PAPERLIT_HPUB_URI", PAPERLIT_UPLOAD_URI . 'hpub/' );
define( "PAPERLIT_WEB_URI", PAPERLIT_UPLOAD_URI . 'web/' );
define( "PAPERLIT_SHELF_URI", PAPERLIT_UPLOAD_URI . 'shelf/' );
define( "PAPERLIT_IOS_SETTINGS_URI", PAPERLIT_UPLOAD_URI . 'settings/' );

/* THEMES*/
define( "PAPERLIT_THEMES_PATH", trailingslashit( PAPERLIT_UPLOAD_PATH . 'themes' ) );

define( "PAPERLIT_THEME_URI", PAPERLIT_UPLOAD_URI . 'themes/' );

// @TODO change on production
define( "PAPERLIT_API_URL", 'http://press-room.io/' );
define( "PAPERLIT_API_EDD_URL", PAPERLIT_API_URL . 'edd-api/' );
/* Packager */
define( "PAPERLIT_EDITION_MEDIA", 'gfx/' );

// Custom post types
define( "PAPERLIT_EDITION", 'paperlit_edition' );
define( "PAPERLIT_P2P_EDITION_CONNECTION", 'edition_post' );

// Custom taxonomies
define( "PAPERLIT_EDITORIAL_PROJECT", 'paperlit_editorial_project' );

// Database
define( "PAPERLIT_TABLE_DB_VERSION", "1.0" );
define( "PAPERLIT_TABLE_RECEIPTS", 'paperlit_receipts' );
define( "PAPERLIT_TABLE_RECEIPT_TRANSACTIONS", 'paperlit_receipt_transactions' );
define( "PAPERLIT_TABLE_PURCHASED_ISSUES", 'paperlit_purchased_issues' );
define( "PAPERLIT_TABLE_AUTH_TOKENS", 'paperlit_auth_tokens' );
define( "PAPERLIT_TABLE_STATS" , 'paperlit_statistics' );
define( "PAPERLIT_TABLE_LOGS" , 'paperlit_logs' );
