<?php
/**
 * Vercel PHP Bridge (main.php)
 * This script handles routing for the entire application.
 */

// Basic configuration
$root_dir = realpath(__DIR__ . '/..');
$uri = $_SERVER['REQUEST_URI'] ?? '/';

// Strip query string
$path_uri = explode('?', $uri)[0];

// Security: Prevent directory traversal
if (strpos($path_uri, '..') !== false) {
    header("HTTP/1.1 400 Bad Request");
    exit("Invalid request");
}

// Mapping logic
if ($path_uri === '/' || $path_uri === '') {
    $target = 'index.php';
} else {
    $target = ltrim($path_uri, '/');
    
    // Check if it's a directory
    if (is_dir($root_dir . '/' . $target)) {
        $target = rtrim($target, '/') . '/index.php';
    }
    
    // Support for clean URLs (if no extension, try .php)
    if (!pathinfo($target, PATHINFO_EXTENSION)) {
        if (file_exists($root_dir . '/' . $target . '.php')) {
            $target .= '.php';
        }
    }
}

$full_path = $root_dir . '/' . $target;

if (file_exists($full_path) && is_file($full_path)) {
    // Set working directory to the target file's directory
    chdir(dirname($full_path));
    
    // Set server variables for compatibility
    $_SERVER['SCRIPT_FILENAME'] = $full_path;
    $_SERVER['SCRIPT_NAME'] = '/' . $target;
    $_SERVER['PHP_SELF'] = '/' . $target;
    
    // Include the file
    require basename($full_path);
} else {
    header("HTTP/1.1 404 Not Found");
    
    // Fallback error page
    if (file_exists($root_dir . '/404.php')) {
        chdir($root_dir);
        require '404.php';
    } else {
        echo "<h1>404 Not Found</h1>";
        echo "<p>The requested path <code>" . htmlspecialchars($path_uri) . "</code> was not found.</p>";
        echo "<!-- Target was: " . htmlspecialchars($target) . " -->";
    }
}
