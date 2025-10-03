<?php
// Database configuration and session bootstrap

// App constants
if (!defined('APP_NAME')) {
	define('APP_NAME', 'Smart Waste');
}
if (!defined('BASE_URL')) {
	// Change this if your app folder name changes
	define('BASE_URL', '/smart_waste/');
}

// Optional absolute public URL for links in emails (set to your LAN IP or domain)
// You can set an environment variable APP_PUBLIC_URL (e.g., http://192.168.1.10/smart_waste/)
if (!defined('APP_PUBLIC_URL')) {
    $envPublicUrl = getenv('APP_PUBLIC_URL');
    if ($envPublicUrl) {
        // Normalize to ensure trailing slash
        $normalized = rtrim($envPublicUrl, "/") . '/';
        define('APP_PUBLIC_URL', $normalized);
    }
}
// Explicit public URL for LAN access (user-provided IPv4)
if (!defined('APP_PUBLIC_URL')) {
    define('APP_PUBLIC_URL', 'http://192.168.1.166/smart_waste/');
}

// Outbound email (Gmail SMTP) - configure these for real email sending
if (!defined('SMTP_HOST')) {
	define('SMTP_HOST', 'smtp.gmail.com');
}
if (!defined('SMTP_PORT')) {
	define('SMTP_PORT', 587); // 587 for TLS
}
if (!defined('SMTP_SECURE')) {
	define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'
}
if (!defined('SMTP_USERNAME')) {
	define('SMTP_USERNAME', 'khanyaolor123@gmail.com'); // your Gmail address
}
if (!defined('SMTP_PASSWORD')) {
	define('SMTP_PASSWORD', 'grxy qpus askr sqlg'); // Gmail App Password
}
if (!defined('SMTP_FROM_EMAIL')) {
	define('SMTP_FROM_EMAIL', 'yourgmail@gmail.com');
}
if (!defined('SMTP_FROM_NAME')) {
	define('SMTP_FROM_NAME', APP_NAME);
}

// App absolute base URL helper for links in emails
if (!defined('APP_BASE_URL_ABS')) {
	if (defined('APP_PUBLIC_URL') && APP_PUBLIC_URL) {
		define('APP_BASE_URL_ABS', APP_PUBLIC_URL);
	} else {
		$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
		define('APP_BASE_URL_ABS', $scheme . '://' . $host . BASE_URL);
	}
}

// Realtime (Pusher) configuration
if (!defined('PUSHER_APP_ID')) {
	define('PUSHER_APP_ID', getenv('PUSHER_APP_ID') ?: '2052658');
}
if (!defined('PUSHER_KEY')) {
	define('PUSHER_KEY', getenv('PUSHER_KEY') ?: 'ebf39a7912a236bca336');
}
if (!defined('PUSHER_SECRET')) {
	define('PUSHER_SECRET', getenv('PUSHER_SECRET') ?: '4e133b75a01f9172dc05');
}
if (!defined('PUSHER_CLUSTER')) {
	define('PUSHER_CLUSTER', getenv('PUSHER_CLUSTER') ?: 'ap1');
}
if (!defined('PUSHER_USE_TLS')) {
	define('PUSHER_USE_TLS', true);
}

// Web Push (VAPID) configuration for background notifications
// Generate your VAPID keys at: https://web-push-codelab.glitch.me/
if (!defined('VAPID_PUBLIC_KEY')) {
	define('VAPID_PUBLIC_KEY', getenv('VAPID_PUBLIC_KEY') ?: 'BNvLENZKU1QKigg3b6sgmdOM_MrWkOyMLEuwAO1twPPJOXDr6bL20l2YWksiZfxEawFYyoFUskDmTU33T8Y5AHM');
}
if (!defined('VAPID_PRIVATE_KEY')) {
	define('VAPID_PRIVATE_KEY', getenv('VAPID_PRIVATE_KEY') ?: '4cyTd66JDG5JuRsr73stOTqWue7IL7cYdybb1KwBTkQ');
}
if (!defined('VAPID_SUBJECT')) {
	define('VAPID_SUBJECT', getenv('VAPID_SUBJECT') ?: 'mailto:khanyaolor@gmail.com');
}

// Update these values to match your local MySQL setup (XAMPP defaults shown)
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'smart_waste';

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');

// Start a session if one hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// Create a reusable MySQLi connection
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// Set MySQL timezone to Philippine time
$conn->query("SET time_zone = '+08:00'");

if ($conn->connect_error) {
	die('Database connection failed: ' . $conn->connect_error);
}

// Helper to sanitize output
function e(string $value): string {
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Helper to check if user is logged in
function is_logged_in(): bool {
	return isset($_SESSION['user_id']);
}

// Helper to enforce login
function require_login(): void {
	if (!is_logged_in()) {
		header('Location: ' . BASE_URL . 'login.php');
		exit;
	}
}

// Helper to format date in Philippine time
function format_ph_date($date_string, $format = 'M j, Y g:i A'): string {
	$date = new DateTime($date_string);
	$date->setTimezone(new DateTimeZone('Asia/Manila'));
	return $date->format($format);
}

// Helper to get current Philippine time
function get_ph_time($format = 'Y-m-d H:i:s'): string {
	$date = new DateTime('now', new DateTimeZone('Asia/Manila'));
	return $date->format($format);
}

?>


