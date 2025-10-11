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
	 * Table existence verified
	 *
	 * @var bool|null
	 */
	private $table_verified = null;

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
	 * Ensure tables exist (CRITICAL: Call before every query)
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function ensure_tables_exist() {
		if ( null !== $this->table_verified ) {
			return $this->table_verified;
		}

		global $wpdb;
		
		$table_exists = $wpdb->get_var( $wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$this->settings_table
		) );
		
		if ( $this->settings_table !== $table_exists ) {
			$this->create_tables();
			$table_exists = $wpdb->get_var( $wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$this->settings_table
			) );
		}
		
		$this->table_verified = ( $this->settings_table === $table_exists );
		return $this->table_verified;
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

		$sql[] = $wpdb->prepare(
			'CREATE TABLE IF NOT EXISTS %i (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				setting_key varchar(191) NOT NULL,
				setting_value longtext,
				PRIMARY KEY (id),
				UNIQUE KEY setting_key (setting_key)
			)',
			$this->settings_table
		) . ' ' . $charset_collate;

		$sql[] = $wpdb->prepare(
			'CREATE TABLE IF NOT EXISTS %i (
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
			)',
			$this->emails_table
		) . ' ' . $charset_collate;

		$sql[] = $wpdb->prepare(
			'CREATE TABLE IF NOT EXISTS %i (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				log_type varchar(50) NOT NULL,
				log_message text,
				log_date datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY log_type (log_type),
				KEY log_date (log_date)
			)',
			$this->logs_table
		) . ' ' . $charset_collate;

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
			if ( false === $this->get_setting( $key ) ) {
				$this->save_setting( $key, $value );
			}
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
		if ( null !== $this->settings ) {
			return $this->settings;
		}

		if ( ! $this->ensure_tables_exist() ) {
			return array();
		}
		
		global $wpdb;
		
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT setting_key, setting_value FROM %i',
				$this->settings_table
			),
			ARRAY_A
		);

		$this->settings = array();
		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				$key = $row['setting_key'] ?? '';
				$value = $row['setting_value'] ?? '';
				if ( ! empty( $key ) ) {
					$this->settings[ $key ] = maybe_unserialize( $value );
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
		if ( ! $this->ensure_tables_exist() ) {
			return new WP_Error( 'table_missing', __( 'Database tables not available', 'mail-extractor' ) );
		}

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
			return new WP_Error( 'db_error', __( 'Failed to save setting', 'mail-extractor' ) );
		}

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
		if ( ! $this->ensure_tables_exist() ) {
			return new WP_Error( 'table_missing', __( 'Database tables not available', 'mail-extractor' ) );
		}

		global $wpdb;

		$result = $wpdb->replace(
			$this->emails_table,
			array(
				'email_uid' => $email_data['uid'] ?? '',
				'email_from' => $email_data['from'] ?? '',
				'email_to' => $email_data['to'] ?? '',
				'email_subject' => $email_data['subject'] ?? '',
				'email_body' => $email_data['body'] ?? '',
				'email_date' => $email_data['date'] ?? current_time( 'mysql' ),
				'attachments_count' => $email_data['attachments_count'] ?? 0,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( false === $result ) {
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
		if ( ! $this->ensure_tables_exist() ) {
			return 0;
		}

		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i',
				$this->emails_table
			)
		);

		return (int) ( $count ?? 0 );
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
		if ( ! $this->ensure_tables_exist() ) {
			return array();
		}

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY email_date DESC LIMIT %d OFFSET %d',
				$this->emails_table,
				$limit,
				$offset
			),
			ARRAY_A
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Delete old emails
	 *
	 * @since 1.0.0
	 * @param int $days Days to keep
	 * @return int|false Number of deleted emails or false on error
	 */
	public function cleanup_old_emails( $days ) {
		if ( ! $this->ensure_tables_exist() ) {
			return false;
		}

		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE email_date < DATE_SUB(NOW(), INTERVAL %d DAY)',
				$this->emails_table,
				$days
			)
		);

		if ( false === $result ) {
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
		if ( ! $this->ensure_tables_exist() ) {
			return new WP_Error( 'table_missing', __( 'Database tables not available', 'mail-extractor' ) );
		}

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
		if ( ! $this->ensure_tables_exist() ) {
			return array();
		}

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY log_date DESC LIMIT %d',
				$this->logs_table,
				$limit
			),
			ARRAY_A
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Clear old logs
	 *
	 * @since 1.0.0
	 * @param int $days Days to keep
	 * @return int|false Number of deleted logs or false on error
	 */
	public function cleanup_old_logs( $days = 7 ) {
		if ( ! $this->ensure_tables_exist() ) {
			return false;
		}

		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE log_date < DATE_SUB(NOW(), INTERVAL %d DAY)',
				$this->logs_table,
				$days
			)
		);

		if ( false === $result ) {
			return false;
		}

		return $result;
	}
}
