<?php
/**
 * Plugin Name: Mail Extractor
 * Description: Import emails from POP3 mail services with automatic cleanup and scheduling
 * Version: 1.0.0
 * Text Domain: mail-extractor
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package MailExtractor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MAIL_EXTRACTOR_VERSION', '1.0.0' );
define( 'MAIL_EXTRACTOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'MAIL_EXTRACTOR_URL', plugin_dir_url( __FILE__ ) );

require_once MAIL_EXTRACTOR_DIR . 'includes/class-database.php';
require_once MAIL_EXTRACTOR_DIR . 'includes/class-core.php';
require_once MAIL_EXTRACTOR_DIR . 'includes/class-admin.php';

/**
 * Initialize plugin
 *
 * @since 1.0.0
 */
function mail_extractor_init() {
	$database = new Mail_Extractor_Database();
	$core = new Mail_Extractor_Core( $database );
	
	if ( is_admin() ) {
		new Mail_Extractor_Admin( $core, $database );
	}
}
add_action( 'plugins_loaded', 'mail_extractor_init' );

register_activation_hook( __FILE__, array( 'Mail_Extractor_Database', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Mail_Extractor_Database', 'deactivate' ) );
