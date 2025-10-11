<?php
/**
 * Uninstall handler for Mail Extractor
 *
 * @package MailExtractor
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-database.php';

$database = new Mail_Extractor_Database();
$cleanup = $database->get_setting( 'cleanup_on_uninstall', '0' );

if ( '1' !== $cleanup ) {
	return;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'mail_extractor_settings',
	$wpdb->prefix . 'mail_extractor_emails',
	$wpdb->prefix . 'mail_extractor_logs',
);

foreach ( $tables as $table ) {
	$table_exists = $wpdb->get_var( $wpdb->prepare(
		'SHOW TABLES LIKE %s',
		$table
	) );
	
	if ( $table === $table_exists ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}
}

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

wp_clear_scheduled_hook( 'mail_extractor_import_emails' );
wp_clear_scheduled_hook( 'mail_extractor_cleanup_emails' );

wp_cache_flush();
