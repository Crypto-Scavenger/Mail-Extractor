<?php
/**
 * Core functionality for Mail Extractor
 *
 * @package     MailExtractor
 * @subpackage  Core
 * @since       1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles POP3 connections and email processing
 *
 * @since 1.0.0
 */
class Mail_Extractor_Core {

	/**
	 * Database instance
	 *
	 * @var Mail_Extractor_Database
	 */
	private $database;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @param Mail_Extractor_Database $database Database instance
	 */
	public function __construct( $database ) {
		$this->database = $database;
		
		add_action( 'mail_extractor_import_emails', array( $this, 'cron_import_emails' ) );
		add_action( 'mail_extractor_cleanup_emails', array( $this, 'cron_cleanup_emails' ) );
	}

	/**
	 * Test POP3 connection
	 *
	 * @since 1.0.0
	 * @param array $config Connection configuration
	 * @return array Result array with success status and message
	 */
	public function test_connection( $config ) {
		$server = sanitize_text_field( $config['server'] ?? '' );
		$port = (int) ( $config['port'] ?? 995 );
		$username = sanitize_text_field( $config['username'] ?? '' );
		$password = $config['password'] ?? '';
		$app_password = $config['app_password'] ?? '';
		$use_ssl = (bool) ( $config['use_ssl'] ?? true );

		if ( empty( $server ) || empty( $username ) ) {
			return array(
				'success' => false,
				'message' => __( 'Server and username are required', 'mail-extractor' ),
			);
		}

		if ( ! empty( $app_password ) ) {
			$password = $app_password;
		}

		if ( empty( $password ) ) {
			return array(
				'success' => false,
				'message' => __( 'Password or app password is required', 'mail-extractor' ),
			);
		}

		$connection = $this->connect_pop3( $server, $port, $username, $password, $use_ssl );

		if ( is_wp_error( $connection ) ) {
			return array(
				'success' => false,
				'message' => $connection->get_error_message(),
			);
		}

		$this->disconnect_pop3( $connection );

		return array(
			'success' => true,
			'message' => __( 'Connection successful!', 'mail-extractor' ),
		);
	}

	/**
	 * Connect to POP3 server
	 *
	 * @since 1.0.0
	 * @param string $server   POP3 server
	 * @param int    $port     Port number
	 * @param string $username Username
	 * @param string $password Password
	 * @param bool   $use_ssl  Use SSL
	 * @return resource|WP_Error Connection resource or error
	 */
	private function connect_pop3( $server, $port, $username, $password, $use_ssl ) {
		$errno = 0;
		$errstr = '';
		$timeout = 30;

		$connection_string = $use_ssl ? "ssl://{$server}" : $server;

		$connection = @fsockopen( $connection_string, $port, $errno, $errstr, $timeout );

		if ( ! $connection ) {
			return new WP_Error(
				'connection_failed',
				sprintf(
					/* translators: 1: error number, 2: error string */
					__( 'Connection failed: [%1$d] %2$s', 'mail-extractor' ),
					$errno,
					$errstr
				)
			);
		}

		$response = fgets( $connection );
		if ( false === strpos( $response, '+OK' ) ) {
			fclose( $connection );
			return new WP_Error( 'greeting_failed', __( 'Invalid server greeting', 'mail-extractor' ) );
		}

		fputs( $connection, "USER {$username}\r\n" );
		$response = fgets( $connection );
		if ( false === strpos( $response, '+OK' ) ) {
			fclose( $connection );
			return new WP_Error( 'user_failed', __( 'Username not accepted', 'mail-extractor' ) );
		}

		fputs( $connection, "PASS {$password}\r\n" );
		$response = fgets( $connection );
		if ( false === strpos( $response, '+OK' ) ) {
			fclose( $connection );
			return new WP_Error( 'auth_failed', __( 'Authentication failed. Check password/app password.', 'mail-extractor' ) );
		}

		return $connection;
	}

	/**
	 * Disconnect from POP3 server
	 *
	 * @since 1.0.0
	 * @param resource $connection Connection resource
	 */
	private function disconnect_pop3( $connection ) {
		if ( is_resource( $connection ) ) {
			fputs( $connection, "QUIT\r\n" );
			fclose( $connection );
		}
	}

	/**
	 * Import emails manually
	 *
	 * @since 1.0.0
	 * @return array Result array with success status and message
	 */
	public function import_emails_now() {
		$server = $this->database->get_setting( 'pop3_server' );
		$port = (int) $this->database->get_setting( 'pop3_port', 995 );
		$username = $this->database->get_setting( 'username' );
		$password = $this->database->get_setting( 'password' );
		$app_password = $this->database->get_setting( 'app_password' );
		$use_ssl = (bool) $this->database->get_setting( 'use_ssl', true );

		if ( empty( $server ) || empty( $username ) || ( empty( $password ) && empty( $app_password ) ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please configure POP3 settings first.', 'mail-extractor' ),
			);
		}

		if ( ! empty( $app_password ) ) {
			$password = $app_password;
		}

		$connection = $this->connect_pop3( $server, $port, $username, $password, $use_ssl );

		if ( is_wp_error( $connection ) ) {
			$this->database->add_log( 'error', $connection->get_error_message() );
			return array(
				'success' => false,
				'message' => $connection->get_error_message(),
			);
		}

		fputs( $connection, "STAT\r\n" );
		$response = fgets( $connection );
		$parts = explode( ' ', $response );
		$message_count = isset( $parts[1] ) ? (int) $parts[1] : 0;

		if ( 0 === $message_count ) {
			$this->disconnect_pop3( $connection );
			return array(
				'success' => true,
				'message' => __( 'No new emails to import.', 'mail-extractor' ),
			);
		}

		$imported = 0;
		for ( $i = 1; $i <= $message_count; $i++ ) {
			$email_data = $this->retrieve_email( $connection, $i );
			
			if ( ! is_wp_error( $email_data ) ) {
				$result = $this->database->save_email( $email_data );
				if ( ! is_wp_error( $result ) ) {
					$imported++;
				}
			}
		}

		$this->disconnect_pop3( $connection );

		$message = sprintf(
			/* translators: %d: number of imported emails */
			_n( '%d email imported successfully.', '%d emails imported successfully.', $imported, 'mail-extractor' ),
			$imported
		);

		$this->database->add_log( 'import', $message );

		return array(
			'success' => true,
			'message' => $message,
		);
	}

	/**
	 * Retrieve single email from POP3
	 *
	 * @since 1.0.0
	 * @param resource $connection Connection resource
	 * @param int      $msg_number Message number
	 * @return array|WP_Error Email data or error
	 */
	private function retrieve_email( $connection, $msg_number ) {
		fputs( $connection, "UIDL {$msg_number}\r\n" );
		$response = fgets( $connection );
		$uid_parts = explode( ' ', trim( $response ) );
		$uid = $uid_parts[2] ?? '';

		if ( empty( $uid ) ) {
			return new WP_Error( 'uid_failed', __( 'Failed to get email UID', 'mail-extractor' ) );
		}

		fputs( $connection, "RETR {$msg_number}\r\n" );
		$response = fgets( $connection );
		
		if ( false === strpos( $response, '+OK' ) ) {
			return new WP_Error( 'retr_failed', __( 'Failed to retrieve email', 'mail-extractor' ) );
		}

		$email_content = '';
		while ( $line = fgets( $connection ) ) {
			if ( ".\r\n" === $line ) {
				break;
			}
			$email_content .= $line;
		}

		$parsed = $this->parse_email( $email_content );
		$parsed['uid'] = $uid;

		return $parsed;
	}

	/**
	 * Parse email content
	 *
	 * @since 1.0.0
	 * @param string $content Raw email content
	 * @return array Parsed email data
	 */
	private function parse_email( $content ) {
		$lines = explode( "\n", $content );
		$headers = array();
		$body = '';
		$in_body = false;

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( empty( $line ) && ! $in_body ) {
				$in_body = true;
				continue;
			}

			if ( ! $in_body ) {
				if ( false !== strpos( $line, ':' ) ) {
					list( $key, $value ) = explode( ':', $line, 2 );
					$headers[ strtolower( trim( $key ) ) ] = trim( $value );
				}
			} else {
				$body .= $line . "\n";
			}
		}

		$from = isset( $headers['from'] ) ? $this->extract_email_address( $headers['from'] ) : '';
		$to = isset( $headers['to'] ) ? $this->extract_email_address( $headers['to'] ) : '';
		$subject = isset( $headers['subject'] ) ? $this->decode_header( $headers['subject'] ) : '';
		$date = isset( $headers['date'] ) ? $this->parse_date( $headers['date'] ) : current_time( 'mysql' );

		return array(
			'from' => sanitize_email( $from ),
			'to' => sanitize_email( $to ),
			'subject' => sanitize_text_field( $subject ),
			'body' => wp_kses_post( $body ),
			'date' => $date,
			'attachments_count' => 0,
		);
	}

	/**
	 * Extract email address from header
	 *
	 * @since 1.0.0
	 * @param string $header Header value
	 * @return string Email address
	 */
	private function extract_email_address( $header ) {
		if ( preg_match( '/<([^>]+)>/', $header, $matches ) ) {
			return $matches[1];
		}
		return $header;
	}

	/**
	 * Decode email header
	 *
	 * @since 1.0.0
	 * @param string $header Header value
	 * @return string Decoded header
	 */
	private function decode_header( $header ) {
		if ( function_exists( 'iconv_mime_decode' ) ) {
			return iconv_mime_decode( $header, 0, 'UTF-8' );
		}
		return $header;
	}

	/**
	 * Parse email date
	 *
	 * @since 1.0.0
	 * @param string $date Date string
	 * @return string MySQL formatted date
	 */
	private function parse_date( $date ) {
		$timestamp = strtotime( $date );
		if ( false === $timestamp ) {
			return current_time( 'mysql' );
		}
		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Cron job for importing emails
	 *
	 * @since 1.0.0
	 */
	public function cron_import_emails() {
		$frequency = (int) $this->database->get_setting( 'import_frequency', 60 );
		$last_import = get_transient( 'mail_extractor_last_import' );

		if ( $last_import && ( time() - $last_import ) < ( $frequency * MINUTE_IN_SECONDS ) ) {
			return;
		}

		$this->import_emails_now();
		set_transient( 'mail_extractor_last_import', time(), 12 * HOUR_IN_SECONDS );
	}

	/**
	 * Cron job for cleaning up emails
	 *
	 * @since 1.0.0
	 */
	public function cron_cleanup_emails() {
		$auto_cleanup = (bool) $this->database->get_setting( 'auto_cleanup', false );
		
		if ( ! $auto_cleanup ) {
			return;
		}

		$cleanup_days = (int) $this->database->get_setting( 'cleanup_days', 30 );
		$deleted = $this->database->cleanup_old_emails( $cleanup_days );

		if ( false !== $deleted && $deleted > 0 ) {
			$message = sprintf(
				/* translators: %d: number of deleted emails */
				_n( '%d old email deleted.', '%d old emails deleted.', $deleted, 'mail-extractor' ),
				$deleted
			);
			$this->database->add_log( 'cleanup', $message );
		}

		$this->database->cleanup_old_logs( 7 );
	}

	/**
	 * Get database instance
	 *
	 * @since 1.0.0
	 * @return Mail_Extractor_Database Database instance
	 */
	public function get_database() {
		return $this->database;
	}
}
