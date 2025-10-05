<?php
/**
 * Uninstall handler for Mail Extractor
 *
 * @package MailExtractor
 * @since   1.0.0
 */

// Security check
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Include database class
require_once plugin_dir_path( __FILE__ ) . 'includes/class-database.php';

// Get cleanup preference
$database = new Mail_Extractor_Database();
$cleanup = $database->get_setting( 'cleanup_on_uninstall' );

if ( '1' === $cleanup ) {
	global $wpdb;

	// Drop custom tables
	$tables = array(
		$wpdb->prefix . 'mail_extractor_settings',
		$wpdb->prefix . 'mail_extractor_emails',
		$wpdb->prefix . 'mail_extractor_logs',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $table ) );
	}

	// Clean transients
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM {$wpdb->options} 
		WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_mail_extractor_' ) . '%'
	) );

	$wpdb->query( $wpdb->prepare(
		"DELETE FROM {$wpdb->options} 
		WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_timeout_mail_extractor_' ) . '%'
	) );

	// Clear scheduled events
	wp_clear_scheduled_hook( 'mail_extractor_import_emails' );
	wp_clear_scheduled_hook( 'mail_extractor_cleanup_emails' );

	// Clear object cache
	wp_cache_flush();
}
