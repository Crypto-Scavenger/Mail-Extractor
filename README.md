# Mail Extractor

A WordPress plugin that imports emails from POP3 mail services with automatic cleanup and scheduling functionality.

## Description

Mail Extractor connects to any POP3-enabled email service and imports emails directly into your WordPress database. Perfect for archiving emails, creating email-based content, or integrating email communications into your WordPress workflow.

## Features

- **POP3 Email Import**: Connect to any POP3-enabled email service
- **Google App Password Support**: Special support for Gmail accounts with 2FA
- **Automatic Import**: Schedule automatic email imports at configurable intervals (minimum 5 minutes)
- **Auto Cleanup**: Automatically delete old emails after a specified number of days
- **SSL/TLS Support**: Secure connections with SSL encryption (recommended)
- **Manual Import**: On-demand email import functionality via admin interface
- **Connection Testing**: Test POP3 connections before saving settings
- **Activity Logs**: Track import operations and errors
- **Custom Database Tables**: Uses dedicated tables to avoid bloating wp_options
- **Configurable Cleanup**: Choose whether to delete all data on uninstall

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- POP3-enabled email account
- SSL support (recommended)

## Installation

1. Upload the `mail-extractor` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'Mail Extractor' in the WordPress admin menu
4. Configure your POP3 settings and save

## Configuration

### POP3 Settings

- **POP3 Server**: Your email provider's POP3 server address (e.g., pop.gmail.com)
- **POP3 Port**: Usually 995 for SSL or 110 for non-SSL connections
- **Username**: Your email username (usually your email address)
- **Email Address**: The email address for this account
- **Password**: Your email password
- **App Password**: For Gmail/Google accounts with 2FA (leave empty if not using Google)
- **Use SSL**: Enable SSL connection (strongly recommended)

### Import Settings

- **Import Frequency**: How often to check for new emails (in minutes, minimum 5)
- **Auto Cleanup**: Enable automatic deletion of old emails
- **Cleanup Days**: Delete emails older than this many days
- **Cleanup on Uninstall**: Choose whether to delete all data when plugin is uninstalled

## Common Email Provider Configurations

### Gmail Configuration

1. Enable POP3 in Gmail settings (Settings > Forwarding and POP/IMAP)
2. If using 2FA, generate an App Password:
   - Go to Google Account > Security > 2-Step Verification > App passwords
   - Generate a password for "Mail" on "Other (Custom name)"
   - Copy the generated password

**Settings:**
- **POP3 Server**: `pop.gmail.com`
- **Port**: `995`
- **SSL**: Enabled
- **Username**: `your-email@gmail.com`
- **App Password**: Required if 2FA is enabled

### Outlook/Hotmail Configuration

1. Enable POP3 in Outlook settings (Settings > Sync email > POP)

**Settings:**
- **POP3 Server**: `outlook.office365.com`
- **Port**: `995`
- **SSL**: Enabled
- **Username**: `your-email@outlook.com` or `your-email@hotmail.com`

### Yahoo Mail Configuration

1. Enable POP3 in Yahoo Mail settings (Settings > More Settings > Mailboxes)
2. May require generating an app password for third-party apps

**Settings:**
- **POP3 Server**: `pop.mail.yahoo.com`
- **Port**: `995`
- **SSL**: Enabled
- **Username**: `your-email@yahoo.com`

## Usage

### Testing Connection

1. Configure your POP3 settings
2. Click the "Test Connection" button
3. Wait for connection confirmation before saving

### Manual Import

1. Click the "Import Now" button on the settings page
2. The plugin will immediately import all available emails
3. Check the "Emails" page to view imported messages

### Viewing Imported Emails

1. Navigate to Mail Extractor > Emails
2. View all imported emails with sender, recipient, subject, and dates
3. Emails are paginated for easy navigation

### Monitoring Activity

1. Navigate to Mail Extractor > Logs
2. View import operations, errors, and cleanup activities
3. Logs are automatically cleaned up after 7 days

## Database Structure

The plugin creates three custom database tables:

### wp_mail_extractor_settings
Stores plugin configuration settings with key-value pairs.

**Columns:**
- `id`: Primary key
- `setting_key`: Unique setting identifier
- `setting_value`: Serialized setting value

### wp_mail_extractor_emails
Stores imported email messages.

**Columns:**
- `id`: Primary key
- `email_uid`: Unique email identifier from POP3 server
- `email_from`: Sender email address
- `email_to`: Recipient email address
- `email_subject`: Email subject line
- `email_body`: Full email body content
- `email_date`: Original email date
- `imported_date`: Date imported into WordPress
- `attachments_count`: Number of attachments (currently 0, future feature)

### wp_mail_extractor_logs
Stores activity logs for monitoring and debugging.

**Columns:**
- `id`: Primary key
- `log_type`: Type of log entry (import, cleanup, error)
- `log_message`: Detailed log message
- `log_date`: Timestamp of log entry

## Automatic Operations

### Scheduled Email Import

The plugin uses WordPress cron to check for new emails based on your configured frequency. The actual check only runs if the specified time interval has passed since the last import.

**Cron Job**: `mail_extractor_import_emails` (runs hourly, but respects frequency setting)

### Automatic Cleanup

If auto cleanup is enabled, the plugin runs a daily check to delete emails older than the specified number of days.

**Cron Job**: `mail_extractor_cleanup_emails` (runs daily)

## Security Features

- **Nonce Verification**: All forms and AJAX requests use WordPress nonces
- **Capability Checks**: All operations require `manage_options` capability
- **Input Sanitization**: All user inputs are sanitized before processing
- **Output Escaping**: All outputs are escaped to prevent XSS attacks
- **Prepared Statements**: All database queries use prepared statements to prevent SQL injection
- **SSL Support**: Encrypted connections to email servers

## Performance Considerations

- **Lazy Loading**: Settings are only loaded when needed
- **Custom Tables**: Dedicated tables prevent wp_options bloat
- **Transient Caching**: Caches last import time to prevent unnecessary checks
- **Indexed Database**: Proper database indexes for fast queries
- **Conditional Asset Loading**: Admin assets only load on plugin pages

## Troubleshooting

### Connection Failed

- Verify POP3 server address and port
- Ensure SSL is enabled for secure servers (port 995)
- Check that POP3 is enabled in your email provider settings
- For Gmail with 2FA, ensure you're using an App Password, not your regular password
- Check if your hosting provider blocks outbound connections on port 995

### Authentication Failed

- Double-check username and password
- For Gmail, ensure you're using an App Password if 2FA is enabled
- Verify email provider allows POP3 access
- Some providers require enabling "less secure apps" or "app passwords"

### No Emails Imported

- Verify emails exist in your inbox
- Some providers only allow POP3 access to new emails
- Check if emails were already imported (duplicate UIDs are skipped)
- Review activity logs for detailed error messages

### Import Frequency Not Working

- Ensure WordPress cron is functioning (some hosting providers disable it)
- Check that the frequency setting is at least 5 minutes
- Consider using a system cron job instead of WordPress cron for better reliability

## File Structure

```
mail-extractor/
├── mail-extractor.php          # Main plugin file (initialization only)
├── README.md                   # This file
├── uninstall.php              # Cleanup on plugin deletion
├── index.php                  # Security stub (prevents directory listing)
├── assets/                    # Frontend and admin assets
│   ├── admin.css             # Admin interface styles
│   ├── admin.js              # Admin AJAX functionality
│   └── index.php             # Security stub
└── includes/                  # Core plugin classes
    ├── class-database.php    # All database operations
    ├── class-core.php        # POP3 connection and email processing
    ├── class-admin.php       # Admin interface and pages
    └── index.php             # Security stub
```

## Development

### WordPress Coding Standards

This plugin follows WordPress coding standards:
- PSR-4 autoloading for classes
- WordPress naming conventions (snake_case for functions, PascalCase for classes)
- Proper documentation blocks for all methods
- Security best practices (sanitization, validation, escaping, prepared statements)
- Custom database tables instead of wp_options
- Lazy loading for performance

### Adding Custom Functionality

The plugin is designed to be extensible. Key extension points:

**Email Processing Hook:**
```php
add_action('mail_extractor_after_save', function($email_data) {
    // Process email data
}, 10, 1);
```

**Custom Email Parsing:**
```php
add_filter('mail_extractor_parse_email', function($parsed_data, $raw_content) {
    // Modify parsed email data
    return $parsed_data;
}, 10, 2);
```

## Changelog

### 1.0.0
- Initial release
- POP3 email import functionality
- Gmail App Password support
- Automatic scheduling and cleanup
- SSL/TLS support
- Connection testing
- Activity logging
- Custom database tables
- Admin interface with settings, emails, and logs pages

## License

This plugin is licensed under the GPL v2 or later.

## Privacy

This plugin does not send any data to external services. All email data is stored locally in your WordPress database. The plugin only connects to the POP3 server you specify in the settings.

## Future Enhancements

Planned features for future releases:
- IMAP support in addition to POP3
- Attachment downloading and storage
- Email filtering and rules
- Multiple account support
- Email forwarding to WordPress users
- Custom post type integration
- Email-to-post conversion
- Search functionality
- Advanced email parsing (HTML emails, multipart messages)
