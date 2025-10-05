<?php
/**
 * Database operations for Mail Extractor
 *
 * @package     MailExtractor
 * @subpackage  Database
 * @since       1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all database operations
 *
 * @since 1.0.0
 */
class Mail_Extractor_Database {

	/**
	 * Settings cache
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Table names
	 *
	 * @var string
	 */
	private $settings_table;
	private $emails_table;
	private $logs_table;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->settings_table = $wpdb->prefix . 'mail_extractor_settings';
		$this->emails_table = $wpdb->prefix . 'mail_extractor_emails';
		$this->logs_table = $wpdb->prefix . 'mail_extractor_logs';
	}

	/**
	 * Activation hook
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		$instance = new self();
		$instance->create_tables();
		$instance->set_default_settings();
		$instance->schedule_cron();
	}

	/**
	 * Deactivation hook
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'mail_extractor_import_emails' );
		wp_clear_scheduled_hook( 'mail_extractor_cleanup_emails' );
	}

	/**
	 * Create database tables
	 *
	 * @since 1.0.0
	 */
	private function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = array();

		// Settings table
		$sql[] = $wpdb->prepare(
			"CREATE TABLE IF NOT EXISTS %i (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				setting_key varchar(191) NOT NULL,
				setting_value longtext,
				PRIMARY KEY (id),
				UNIQUE KEY setting_key (setting_key)
			) %s",
			$this->settings_table,
			$charset_collate
		);

		// Emails table
		$sql[] = $wpdb->prepare(
			"CREATE TABLE IF NOT EXISTS %i (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				email_uid varchar(255) NOT NULL,
				email_from varchar(255) NOT NULL,
				email_to varchar(255) NOT NULL,
				email_subject text,
				email_body longtext,
				email_date datetime DEFAULT NULL,
				imported_date datetime DEFAULT CURRENT_TIMESTAMP,
				attachments_count int(11) DEFAULT 0,
				PRIMARY KEY (id),
				UNIQUE KEY email_uid (email_uid),
				KEY email_date (email_date),
				KEY imported_date (imported_date)
			) %s",
			$this->emails_table,
			$charset_collate
		);

		// Logs table
		$sql[] = $wpdb->prepare(
			"CREATE TABLE IF NOT EXISTS %i (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				log_type varchar(50) NOT NULL,
				log_message text,
				log_date datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY log_type (log_type),
				KEY log_date (log_date)
			) %s",
			$this->logs_table,
			$charset_collate
		);

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
	}

	/**
	 * Set default settings
	 *
	 * @since 1.0.0
	 */
	private function set_default_settings() {
		$defaults = array(
			'pop3_server' => '',
			'pop3_port' => '995',
			'username' => '',
			'email_address' => '',
			'password' => '',
			'app_password' => '',
			'use_ssl' => '1',
			'import_frequency' => '60',
			'auto_cleanup' => '0',
			'cleanup_days' => '30',
			'cleanup_on_uninstall' => '0',
		);

		foreach ( $defaults as $key => $value ) {
			$this->save_setting( $key, $value );
		}
	}

	/**
	 * Schedule cron jobs
	 *
	 * @since 1.0.0
	 */
	private function schedule_cron() {
		if ( ! wp_next_scheduled( 'mail_extractor_import_emails' ) ) {
			wp_schedule_event( time(), 'hourly', 'mail_extractor_import_emails' );
		}
		if ( ! wp_next_scheduled( 'mail_extractor_cleanup_emails' ) ) {
			wp_schedule_event( time(), 'daily', 'mail_extractor_cleanup_emails' );
		}
	}

	/**
	 * Get settings with lazy loading
	 *
	 * @since 1.0.0
	 * @return array Settings array
	 */
	private function get_all_settings() {
		if ( null === $this->settings ) {
			global $wpdb;
			
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT setting_key, setting_value FROM %i",
					$this->settings_table
				),
				ARRAY_A
			);

			$this->settings = array();
			if ( $results ) {
				foreach ( $results as $row ) {
					$this->settings[ $row['setting_key'] ] = maybe_unserialize( $row['setting_value'] );
				}
			}
		}

		return $this->settings;
	}

	/**
	 * Get a setting value
	 *
	 * @since 1.0.0
	 * @param string $key     Setting key
	 * @param mixed  $default Default value
	 * @return mixed Setting value or default
	 */
	public function get_setting( $key, $default = '' ) {
		$settings = $this->get_all_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Save a setting value
	 *
	 * @since 1.0.0
	 * @param string $key   Setting key
	 * @param mixed  $value Setting value
	 * @return bool|WP_Error Success or error
	 */
	public function save_setting( $key, $value ) {
		global $wpdb;

		$result = $wpdb->replace(
			$this->settings_table,
			array(
				'setting_key' => $key,
				'setting_value' => maybe_serialize( $value ),
			),
			array( '%s', '%s' )
		);

		if ( false === $result ) {
			error_log( 'Mail Extractor DB Error: ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to save setting', 'mail-extractor' ) );
		}

		// Clear cache
		$this->settings = null;

		return true;
	}

	/**
	 * Save email to database
	 *
	 * @since 1.0.0
	 * @param array $email_data Email data
	 * @return bool|WP_Error Success or error
	 */
	public function save_email( $email_data ) {
		global $wpdb;

		$result = $wpdb->replace(
			$this->emails_table,
			array(
				'email_uid' => $email_data['uid'],
				'email_from' => $email_data['from'],
				'email_to' => $email_data['to'],
				'email_subject' => $email_data['subject'],
				'email_body' => $email_data['body'],
				'email_date' => $email_data['date'],
				'attachments_count' => $email_data['attachments_count'],
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( false === $result ) {
			error_log( 'Mail Extractor DB Error: ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to save email', 'mail-extractor' ) );
		}

		return true;
	}

	/**
	 * Get emails count
	 *
	 * @since 1.0.0
	 * @return int Emails count
	 */
	public function get_emails_count() {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i",
				$this->emails_table
			)
		);

		return (int) $count;
	}

	/**
	 * Get emails
	 *
	 * @since 1.0.0
	 * @param int $limit  Limit
	 * @param int $offset Offset
	 * @return array Emails array
	 */
	public function get_emails( $limit = 20, $offset = 0 ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i ORDER BY email_date DESC LIMIT %d OFFSET %d",
				$this->emails_table,
				$limit,
				$offset
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Delete old emails
	 *
	 * @since 1.0.0
	 * @param int $days Days to keep
	 * @return int|false Number of deleted emails or false on error
	 */
	public function cleanup_old_emails( $days ) {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE email_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$this->emails_table,
				$days
			)
		);

		if ( false === $result ) {
			error_log( 'Mail Extractor DB Error: ' . $wpdb->last_error );
			return false;
		}

		return $result;
	}

	/**
	 * Add log entry
	 *
	 * @since 1.0.0
	 * @param string $type    Log type
	 * @param string $message Log message
	 * @return bool|WP_Error Success or error
	 */
	public function add_log( $type, $message ) {
		global $wpdb;

		$result = $wpdb->insert(
			$this->logs_table,
			array(
				'log_type' => $type,
				'log_message' => $message,
			),
			array( '%s', '%s' )
		);

		if ( false === $result ) {
			error_log( 'Mail Extractor DB Error: ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to add log', 'mail-extractor' ) );
		}

		return true;
	}

	/**
	 * Get recent logs
	 *
	 * @since 1.0.0
	 * @param int $limit Limit
	 * @return array Logs array
	 */
	public function get_recent_logs( $limit = 50 ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i ORDER BY log_date DESC LIMIT %d",
				$this->logs_table,
				$limit
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Clear old logs
	 *
	 * @since 1.0.0
	 * @param int $days Days to keep
	 * @return int|false Number of deleted logs or false on error
	 */
	public function cleanup_old_logs( $days = 7 ) {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE log_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$this->logs_table,
				$days
			)
		);

		if ( false === $result ) {
			error_log( 'Mail Extractor DB Error: ' . $wpdb->last_error );
			return false;
		}

		return $result;
	}
}
