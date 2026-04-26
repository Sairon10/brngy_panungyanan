<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$response = [
    'status' => 'error',
    'message' => 'Unknown error',
    'details' => []
];

try {
    $db = get_db_connection();
    $response['status'] = 'success';
    $response['message'] = 'Database connection successful!';
    
    // Check tables
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $response['details']['tables_found'] = count($tables);
    $response['details']['tables'] = $tables;
    
    // Check current host
    $response['details']['db_host'] = $db_host; // From config.php
    $response['details']['db_name'] = $db_name;
    
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = 'Connection failed: ' . $e->getMessage();
    $response['details']['db_host'] = $db_host ?? 'not set';
    $response['details']['db_name'] = $db_name ?? 'not set';
    $response['details']['db_user'] = $db_user ?? 'not set';
    // Don't show password in response
}

echo json_encode($response, JSON_PRETTY_PRINT);
