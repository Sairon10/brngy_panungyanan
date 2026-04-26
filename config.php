<?php
// Database configuration - Use environment variables for deployment (Vercel/Cloud)
// For local development, it will fallback to XAMPP defaults if ENV vars are not set
$is_local = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1');

if ($is_local) {
    // Local Development (XAMPP)
    $db_host = '127.0.0.1';
    $db_name = 'barangay_system';
    $db_user = 'root';
    $db_pass = '';
} else {
    // Production (InfinityFree / Vercel / Cloud)
    // For Vercel, it uses getenv. For InfinityFree, you can hardcode here or use standard env.
    $db_host = getenv('DB_HOST') ?: 'localhost';
    $db_name = getenv('DB_NAME') ?: 'u113173814_db_Rxl9chEO';
    $db_user = getenv('DB_USER') ?: 'u113173814_usr_Rxl9chEO';
    $db_pass = getenv('DB_PASSWORD') ?: '^d/i2H!Kod2L';
}
$db_charset = 'utf8mb4';

// PDO connection
function get_db_connection(): PDO
{
    global $db_host, $db_name, $db_user, $db_pass, $db_charset;

    try {
        $dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        return new PDO($dsn, $db_user, $db_pass, $options);
    } catch (PDOException $e) {
        // For production, you might want to log this and show a generic message
        die("Database connection failed: " . $e->getMessage());
    }
}


// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie path to root and enable HTTPOnly/Secure for security
    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $is_https
    ]);
    session_start();
}

// Helpers
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}
function is_admin(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
function redirect(string $path): void
{
    // If path is login.php, we might want to remember where we came from
    if (basename($path) === 'login.php' && basename($_SERVER['PHP_SELF']) !== 'login.php') {
        $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
    }
    header("Location: {$path}");
    exit;
}

// CSRF protection
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}
function csrf_validate(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Load email service
require_once __DIR__ . '/includes/email_service.php';
// Load SMS service
require_once __DIR__ . '/includes/sms_service.php';

