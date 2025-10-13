<?php
/**
 * Moodle Configuration File for Google Cloud Run
 *
 * This file defines the Moodle configuration for deployment on Google Cloud Platform.
 * Environment variables are used for sensitive data (database credentials, etc.)
 *
 * @package    core
 * @copyright  2025 COR4EDU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

unset($CFG);
global $CFG;
$CFG = new stdClass();

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================

$CFG->dbtype    = 'mysqli';              // MySQL database driver
$CFG->dblibrary = 'native';              // Native database library
$CFG->dbhost    = 'localhost';           // Use localhost for Unix socket connection
$CFG->dbname    = getenv('MOODLE_DB_NAME') ?: 'moodle_lms';
$CFG->dbuser    = getenv('MOODLE_DB_USER') ?: 'moodle_user';
$CFG->dbpass    = getenv('MOODLE_DB_PASSWORD') ?: '';
$CFG->prefix    = 'mdl_';                // Table prefix for Moodle tables
$CFG->dboptions = [
    'dbpersist' => false,                // Don't use persistent connections
    'dbsocket'  => getenv('MOODLE_DB_HOST') ?: '/cloudsql/sms-edu-47:us-central1:sms-edu-db', // Unix socket path
    'dbport'    => '',                   // Not needed for Unix socket
    'dbcollation' => 'utf8mb4_unicode_ci', // Character collation
];

// ============================================================================
// SITE CONFIGURATION
// ============================================================================

// WWW Root - The URL of this Moodle instance
// Will be set automatically on first install via environment variable
$CFG->wwwroot   = getenv('MOODLE_WWWROOT') ?: 'http://localhost';

// Data Root - Directory for uploaded files (will be Cloud Storage mount)
$CFG->dataroot  = getenv('MOODLE_DATAROOT') ?: '/moodledata';

// Directory permissions
$CFG->directorypermissions = 0777;

// Admin directory
$CFG->admin = 'admin';

// ============================================================================
// SESSION CONFIGURATION (Cloud Run Compatible)
// ============================================================================

// Use database for session storage (persistent across container restarts)
$CFG->session_handler_class = '\core\session\database';
$CFG->session_database_acquire_lock_timeout = 120;

// Session timeout (2 hours)
$CFG->sessiontimeout = 7200;

// ============================================================================
// CACHE CONFIGURATION
// ============================================================================

// Local cache directory (ephemeral, okay for temporary cache)
$CFG->localcachedir = '/tmp/moodlelocal';

// Alternative session cache (if using Redis in future)
// $CFG->session_redis_host = 'localhost';
// $CFG->session_redis_port = 6379;
// $CFG->session_redis_database = 0;

// ============================================================================
// WEB SERVICES CONFIGURATION (Required for SMS Integration)
// ============================================================================

// Enable web services globally
$CFG->enablewebservices = 1;

// Enable REST protocol
$CFG->webserviceprotocols = 'rest';

// ============================================================================
// SECURITY CONFIGURATION
// ============================================================================

// Password policy
$CFG->passwordpolicy = 1;                // Enforce password policy
$CFG->minpasswordlength = 8;             // Minimum 8 characters
$CFG->minpassworddigits = 1;             // At least 1 digit
$CFG->minpasswordlower = 1;              // At least 1 lowercase letter
$CFG->minpasswordupper = 1;              // At least 1 uppercase letter
$CFG->minpasswordnonalphanum = 0;        // Special characters optional

// Force password change on first login
$CFG->passwordchangelogout = 1;

// Prevent session hijacking
$CFG->preventexecpath = true;

// ============================================================================
// PERFORMANCE CONFIGURATION
// ============================================================================

// Enable caching
$CFG->cachejs = true;
$CFG->themedesignermode = false;         // Disable for production

// Compress JavaScript
$CFG->yuicomboloading = true;

// ============================================================================
// CRON CONFIGURATION
// ============================================================================

// Cron will be triggered via Cloud Scheduler
// URL: https://moodle-lms-[hash]-uc.a.run.app/admin/cron.php
$CFG->cronclionly = false;               // Allow web-based cron
$CFG->cronremotepassword = getenv('MOODLE_CRON_PASSWORD') ?: '';

// ============================================================================
// EMAIL CONFIGURATION
// ============================================================================

// Email settings (configure SMTP via SendGrid or similar)
$CFG->smtphosts = getenv('SMTP_HOST') ?: '';
$CFG->smtpsecure = 'tls';
$CFG->smtpauthtype = 'LOGIN';
$CFG->smtpuser = getenv('SMTP_USER') ?: '';
$CFG->smtppass = getenv('SMTP_PASSWORD') ?: '';
$CFG->noreplyaddress = 'noreply@cor4edu.com';

// ============================================================================
// DEBUGGING & ERROR REPORTING
// ============================================================================

// Get environment (production vs development)
$environment = getenv('ENVIRONMENT') ?: 'production';

if ($environment === 'development' || $environment === 'dev') {
    // Development settings
    $CFG->debug = 32767;                 // E_ALL | E_STRICT (DEBUG_DEVELOPER)
    $CFG->debugdisplay = 1;              // Display errors
    $CFG->debugsmtp = true;              // Debug SMTP
    $CFG->perfdebug = 15;                // Performance debugging
    $CFG->debugpageinfo = 1;             // Show page info
} else {
    // Production settings
    $CFG->debug = 0;                     // No debugging
    $CFG->debugdisplay = 0;              // Don't display errors
    $CFG->debugsmtp = false;
    $CFG->perfdebug = 0;
    $CFG->debugpageinfo = 0;
}

// ============================================================================
// CLOUD RUN SPECIFIC CONFIGURATION
// ============================================================================

// Trust Cloud Run's load balancer for HTTPS detection
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = 443;
}

// Handle Cloud Run's dynamic port assignment
if (isset($_SERVER['PORT'])) {
    $_SERVER['SERVER_PORT'] = $_SERVER['PORT'];
}

// ============================================================================
// LOGGING CONFIGURATION
// ============================================================================

// Log to standard output (captured by Cloud Logging)
$CFG->log_manager = '\core\log\manager';
$CFG->log_standard = '\logstore_standard\log\store';

// ============================================================================
// FILE UPLOAD LIMITS
// ============================================================================

$CFG->maxbytes = 104857600;              // 100MB max upload

// ============================================================================
// THEME CONFIGURATION
// ============================================================================

// Default theme
$CFG->theme = 'boost';                   // Moodle's default responsive theme

// ============================================================================
// INTEGRATION CONFIGURATION (Custom for SMS Integration)
// ============================================================================

// SMS API configuration (for future bidirectional sync)
$CFG->sms_api_url = getenv('SMS_API_URL') ?: '';
$CFG->sms_api_token = getenv('SMS_API_TOKEN') ?: '';

// ============================================================================
// MAINTENANCE MODE
// ============================================================================

// Enable maintenance mode during deployment
// Set MOODLE_MAINTENANCE=1 in Cloud Run environment variables to enable
if (getenv('MOODLE_MAINTENANCE') === '1') {
    $CFG->maintenance_enabled = true;
    $CFG->maintenance_message = 'Moodle is currently undergoing scheduled maintenance. Please try again in a few minutes.';
}

// ============================================================================
// FINISH CONFIGURATION
// ============================================================================

// There is no php closing tag in this file, it is intentional because it
// prevents trailing whitespace problems!

// Load the Moodle library
require_once(__DIR__ . '/public/lib/setup.php');
