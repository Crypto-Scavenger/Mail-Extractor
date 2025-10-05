<?php
/**
 * Admin interface for Mail Extractor
 *
 * @package     MailExtractor
 * @subpackage  Admin
 * @since       1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin interface and operations
 *
 * @since 1.0.0
 */
class Mail_Extractor_Admin {

	/**
	 * Core instance
	 *
	 * @var Mail_Extractor_Core
	 */
	private $core;

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
	 * @param Mail_Extractor_Core     $core     Core instance
	 * @param Mail_Extractor_Database $database Database instance
	 */
	public function __construct( $core, $database ) {
		$this->core = $core;
		$this->database = $database;

		// Admin hooks
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_mail_extractor_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_mail_extractor_import_now', array( $this, 'ajax_import_now' ) );
	}

	/**
	 * Add admin menu
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Mail Extractor', 'mail-extractor' ),
			__( 'Mail Extractor', 'mail-extractor' ),
			'manage_options',
			'mail-extractor',
			array( $this, 'render_settings_page' ),
			'dashicons-email-alt',
			80
		);

		add_submenu_page(
			'mail-extractor',
			__( 'Settings', 'mail-extractor' ),
			__( 'Settings', 'mail-extractor' ),
			'manage_options',
			'mail-extractor',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'mail-extractor',
			__( 'Emails', 'mail-extractor' ),
			__( 'Emails', 'mail-extractor' ),
			'manage_options',
			'mail-extractor-emails',
			array( $this, 'render_emails_page' )
		);

		add_submenu_page(
			'mail-extractor',
			__( 'Logs', 'mail-extractor' ),
			__( 'Logs', 'mail-extractor' ),
			'manage_options',
			'mail-extractor-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( false === strpos( $hook, 'mail-extractor' ) ) {
			return;
		}

		wp_enqueue_style(
			'mail-extractor-admin',
			MAIL_EXTRACTOR_URL . 'assets/admin.css',
			array(),
			MAIL_EXTRACTOR_VERSION
		);

		wp_enqueue_script(
			'mail-extractor-admin',
			MAIL_EXTRACTOR_URL . 'assets/admin.js',
			array( 'jquery' ),
			MAIL_EXTRACTOR_VERSION,
			true
		);

		wp_localize_script(
			'mail-extractor-admin',
			'mailExtractorData',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'mail_extractor_ajax' ),
			)
		);
	}

	/**
	 * Render settings page
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access', 'mail-extractor' ) );
		}

		// Handle form submission
		if ( isset( $_POST['mail_extractor_settings_nonce'] ) ) {
			$this->save_settings();
		}

		$settings = $this->get_settings_for_display();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors( 'mail_extractor_messages' ); ?>

			<form method="post" action="" id="mail-extractor-settings-form">
				<?php wp_nonce_field( 'mail_extractor_save_settings', 'mail_extractor_settings_nonce' ); ?>

				<h2><?php esc_html_e( 'POP3 Connection Settings', 'mail-extractor' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="pop3_server">
								<?php esc_html_e( 'POP3 Server', 'mail-extractor' ); ?>
							</label>
						</th>
						<td>
							<input 
								type="text" 
								id="pop3_server" 
								name="pop3_server"
								value="<?php echo esc_attr( $settings['pop3_server'] ); ?>"
								class="regular-text"
								placeholder="pop.gmail.com"
							/>
							<p class="description">
								<?php esc_html_e( 'Your email provider\'s POP3 server address', 'mail-extractor' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pop3_port">
								<?php esc_html_e( 'POP3 Port', 'mail-extractor' ); ?>
							</label>
						</th>
						<td>
							<input 
								type="number" 
								id="pop3_port" 
								name="pop3_port"
								value="<?php echo esc_attr( $settings['pop3_port'] ); ?>"
								class="small-text"
								min="1"
								max="65535"
							/>
							<p class="description">
								<?php esc_html_e( 'Usually 995 for SSL or 110 for non-SSL', 'mail-extractor' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="username">
								<?php esc_html_e( 'Username', 'mail-extractor' ); ?>
							</label>
						</th>
						<td>
							<input 
								type="text" 
								id="username" 
								name="username"
								value="<?php echo esc_attr( $settings['username'] ); ?>"
								class="regular-text"
								placeholder="user@example.com"
							/>
							<p class="description">
								<?php esc_html_e( 'Your email username (usually your email address)', 'mail-extractor' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="email_address">
								<?php esc_html_e( 'Email Address', 'mail-extractor' ); ?>
							</label>
						</th>
						<td>
							<input 
								type="email" 
								id="email_address" 
								name="email_address"
								value="<?php echo esc_attr( $settings['email_address'] ); ?>"
								class="regular-text"
								placeholder="user@example.com"
							/>
							<p class="description">
								<?php esc_html_e( 'The email address for this account', 'mail-extractor' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="password">
								<?php esc_html_e( 'Password', 'mail-extractor' ); ?>
							</label>
						</th>
						<td>
							<input 
								type="password" 
								id="password" 
								name="password"
								value="<?php echo esc_attr( $settings['password'] ); ?>"
								class="regular-text"
								autocomplete="new-password"
							/>
							<p class="description">
								<?php esc_html_e( 'Your email password', 'mail-extractor' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="app_password">
								<?php esc_html_e( 'App Password', 'mail-extractor' ); ?>
							</label>
						</th>
						<td>
							<input 
								type="password" 
								id="app_password" 
								name="app_password"
								value="<?php echo esc_attr( $settings['app_password'] ); ?>"
								class="regular-text"
								autocomplete="new-password"
							/>
							<p class="description">
								<?php esc_html_e( 'For Gmail/Google accounts with 2FA (leave empty if not using Google)', 'mail-extractor' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Use SSL', 'mail-extractor' ); ?>
						</th>
						<td>
							<label>
								<input 
									type="checkbox" 
									id="use_ssl" 
									name="use_ssl"
									value="1"
									<?php checked( $settings['use_ssl'], '1' ); ?>
								/>
								<?php esc_html_e( 'Enable SSL connection (recommended)', 'mail-extractor' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Import Settings', 'mail-extractor' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="import_frequency">
								<?php esc_html_e( 'Import Frequency', 'mail-extractor' ); ?>
							</label>
						</th>
						<td>
							<input 
								type="number" 
								id="import_frequency" 
								name="import_frequency"
								value="<?php echo esc_attr( $settings['import_frequency'] ); ?>"
								class="small-text"
								min="5"
								max="1440"
							/>
							<?php esc_html_e( 'minutes', 'mail-extractor' ); ?>
							<p class="description">
								<?php esc_html_e( 'How often to check for new emails (minimum 5 minutes)', 'mail-extractor' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Auto Cleanup', 'mail-extractor' ); ?>
						</th>
						<td>
							<label>
								<input 
									type="checkbox" 
									id="auto_cleanup" 
									name="auto_cleanup"
									value="1"
									<?php checked( $settings['auto_cleanup'], '1' ); ?>
								/>
								<?php esc_html_e( 'Enable automatic deletion of old emails', 'mail-extractor' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="cleanup_days">
								<?php esc_html_e( 'Cleanup Days', 'mail-extractor' ); ?>
							</label>
						</th>
						<td>
							<input 
								type="number" 
								id="cleanup_days" 
								name="cleanup_days"
								value="<?php echo esc_attr( $settings['cleanup_days'] ); ?>"
								class="small-text"
								min="1"
								max="365"
							/>
							<?php esc_html_e( 'days', 'mail-extractor' ); ?>
							<p class="description">
								<?php esc_html_e( 'Delete emails older than this many days', 'mail-extractor' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Cleanup on Uninstall', 'mail-extractor' ); ?>
						</th>
						<td>
							<label>
								<input 
									type="checkbox" 
									id="cleanup_on_uninstall" 
									name="cleanup_on_uninstall"
									value="1"
									<?php checked( $settings['cleanup_on_uninstall'], '1' ); ?>
								/>
								<?php esc_html_e( 'Delete all data when plugin is uninstalled', 'mail-extractor' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Warning: This will permanently delete all imported emails and settings', 'mail-extractor' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<?php submit_button( __( 'Save Settings', 'mail-extractor' ), 'primary', 'submit', false ); ?>
					<button type="button" class="button button-secondary" id="test-connection">
						<?php esc_html_e( 'Test Connection', 'mail-extractor' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="import-now">
						<?php esc_html_e( 'Import Now', 'mail-extractor' ); ?>
					</button>
				</p>
			</form>

			<div id="mail-extractor-message" style="display:none;"></div>

			<h2><?php esc_html_e( 'Quick Setup Guides', 'mail-extractor' ); ?></h2>
			<div class="mail-extractor-provider-guides">
				<h3><?php esc_html_e( 'Gmail Configuration', 'mail-extractor' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'POP3 Server: pop.gmail.com', 'mail-extractor' ); ?></li>
					<li><?php esc_html_e( 'Port: 995', 'mail-extractor' ); ?></li>
					<li><?php esc_html_e( 'SSL: Enabled', 'mail-extractor' ); ?></li>
					<li><?php esc_html_e( 'App Password: Required if 2FA is enabled', 'mail-extractor' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Outlook/Hotmail Configuration', 'mail-extractor' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'POP3 Server: outlook.office365.com', 'mail-extractor' ); ?></li>
					<li><?php esc_html_e( 'Port: 995', 'mail-extractor' ); ?></li>
					<li><?php esc_html_e( 'SSL: Enabled', 'mail-extractor' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Yahoo Mail Configuration', 'mail-extractor' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'POP3 Server: pop.mail.yahoo.com', 'mail-extractor' ); ?></li>
					<li><?php esc_html_e( 'Port: 995', 'mail-extractor' ); ?></li>
					<li><?php esc_html_e( 'SSL: Enabled', 'mail-extractor' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Get settings for display
	 *
	 * @since 1.0.0
	 * @return array Settings array
	 */
	private function get_settings_for_display() {
		return array(
			'pop3_server' => $this->database->get_setting( 'pop3_server' ),
			'pop3_port' => $this->database->get_setting( 'pop3_port', '995' ),
			'username' => $this->database->get_setting( 'username' ),
			'email_address' => $this->database->get_setting( 'email_address' ),
			'password' => $this->database->get_setting( 'password' ),
			'app_password' => $this->database->get_setting( 'app_password' ),
			'use_ssl' => $this->database->get_setting( 'use_ssl', '1' ),
			'import_frequency' => $this->database->get_setting( 'import_frequency', '60' ),
			'auto_cleanup' => $this->database->get_setting( 'auto_cleanup', '0' ),
			'cleanup_days' => $this->database->get_setting( 'cleanup_days', '30' ),
			'cleanup_on_uninstall' => $this->database->get_setting( 'cleanup_on_uninstall', '0' ),
		);
	}

	/**
	 * Save settings
	 *
	 * @since 1.0.0
	 */
	private function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access', 'mail-extractor' ) );
		}

		if ( ! isset( $_POST['mail_extractor_settings_nonce'] ) ||
		     ! wp_verify_nonce( $_POST['mail_extractor_settings_nonce'], 'mail_extractor_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed', 'mail-extractor' ) );
		}

		$settings = array(
			'pop3_server' => isset( $_POST['pop3_server'] ) ? sanitize_text_field( $_POST['pop3_server'] ) : '',
			'pop3_port' => isset( $_POST['pop3_port'] ) ? absint( $_POST['pop3_port'] ) : 995,
			'username' => isset( $_POST['username'] ) ? sanitize_text_field( $_POST['username'] ) : '',
			'email_address' => isset( $_POST['email_address'] ) ? sanitize_email( $_POST['email_address'] ) : '',
			'password' => isset( $_POST['password'] ) ? $_POST['password'] : '',
			'app_password' => isset( $_POST['app_password'] ) ? $_POST['app_password'] : '',
			'use_ssl' => isset( $_POST['use_ssl'] ) ? '1' : '0',
			'import_frequency' => isset( $_POST['import_frequency'] ) ? absint( $_POST['import_frequency'] ) : 60,
			'auto_cleanup' => isset( $_POST['auto_cleanup'] ) ? '1' : '0',
			'cleanup_days' => isset( $_POST['cleanup_days'] ) ? absint( $_POST['cleanup_days'] ) : 30,
			'cleanup_on_uninstall' => isset( $_POST['cleanup_on_uninstall'] ) ? '1' : '0',
		);

		foreach ( $settings as $key => $value ) {
			$result = $this->database->save_setting( $key, $value );
			if ( is_wp_error( $result ) ) {
				add_settings_error(
					'mail_extractor_messages',
					'mail_extractor_error',
					$result->get_error_message(),
					'error'
				);
				return;
			}
		}

		add_settings_error(
			'mail_extractor_messages',
			'mail_extractor_success',
			__( 'Settings saved successfully.', 'mail-extractor' ),
			'success'
		);
	}

	/**
	 * Render emails page
	 *
	 * @since 1.0.0
	 */
	public function render_emails_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access', 'mail-extractor' ) );
		}

		$page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page = 20;
		$offset = ( $page - 1 ) * $per_page;

		$total_emails = $this->database->get_emails_count();
		$emails = $this->database->get_emails( $per_page, $offset );
		$total_pages = ceil( $total_emails / $per_page );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Imported Emails', 'mail-extractor' ); ?></h1>

			<p>
				<?php
				printf(
					/* translators: %d: total number of emails */
					esc_html__( 'Total: %d emails', 'mail-extractor' ),
					(int) $total_emails
				);
				?>
			</p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'mail-extractor' ); ?></th>
						<th><?php esc_html_e( 'From', 'mail-extractor' ); ?></th>
						<th><?php esc_html_e( 'To', 'mail-extractor' ); ?></th>
						<th><?php esc_html_e( 'Subject', 'mail-extractor' ); ?></th>
						<th><?php esc_html_e( 'Imported', 'mail-extractor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $emails ) ) : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No emails imported yet.', 'mail-extractor' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $emails as $email ) : ?>
							<tr>
								<td><?php echo esc_html( $email['email_date'] ); ?></td>
								<td><?php echo esc_html( $email['email_from'] ); ?></td>
								<td><?php echo esc_html( $email['email_to'] ); ?></td>
								<td><?php echo esc_html( $email['email_subject'] ); ?></td>
								<td><?php echo esc_html( $email['imported_date'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php
						echo paginate_links( array(
							'base' => add_query_arg( 'paged', '%#%' ),
							'format' => '',
							'prev_text' => __( '&laquo;', 'mail-extractor' ),
							'next_text' => __( '&raquo;', 'mail-extractor' ),
							'total' => $total_pages,
							'current' => $page,
						) );
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render logs page
	 *
	 * @since 1.0.0
	 */
	public function render_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access', 'mail-extractor' ) );
		}

		$logs = $this->database->get_recent_logs( 100 );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Activity Logs', 'mail-extractor' ); ?></h1>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'mail-extractor' ); ?></th>
						<th><?php esc_html_e( 'Type', 'mail-extractor' ); ?></th>
						<th><?php esc_html_e( 'Message', 'mail-extractor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr>
							<td colspan="3"><?php esc_html_e( 'No logs yet.', 'mail-extractor' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log['log_date'] ); ?></td>
								<td><?php echo esc_html( ucfirst( $log['log_type'] ) ); ?></td>
								<td><?php echo esc_html( $log['log_message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * AJAX test connection
	 *
	 * @since 1.0.0
	 */
	public function ajax_test_connection() {
		if ( ! check_ajax_referer( 'mail_extractor_ajax', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed', 'mail-extractor' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'mail-extractor' ) );
		}

		$config = array(
			'server' => isset( $_POST['server'] ) ? sanitize_text_field( $_POST['server'] ) : '',
			'port' => isset( $_POST['port'] ) ? absint( $_POST['port'] ) : 995,
			'username' => isset( $_POST['username'] ) ? sanitize_text_field( $_POST['username'] ) : '',
			'password' => isset( $_POST['password'] ) ? $_POST['password'] : '',
			'app_password' => isset( $_POST['app_password'] ) ? $_POST['app_password'] : '',
			'use_ssl' => isset( $_POST['use_ssl'] ) && '1' === $_POST['use_ssl'],
		);

		$result = $this->core->test_connection( $config );

		if ( $result['success'] ) {
			wp_send_json_success( $result['message'] );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}

	/**
	 * AJAX import now
	 *
	 * @since 1.0.0
	 */
	public function ajax_import_now() {
		if ( ! check_ajax_referer( 'mail_extractor_ajax', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed', 'mail-extractor' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'mail-extractor' ) );
		}

		$result = $this->core->import_emails_now();

		if ( $result['success'] ) {
			wp_send_json_success( $result['message'] );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}
}
