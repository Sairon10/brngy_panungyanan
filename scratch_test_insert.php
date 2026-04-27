<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require 'config.php';
$pdo = get_db_connection();

$admin_uid = 1;
$wi_doc_type = 'Barangay Indigency';
$wi_requestor_name = 'Test User';
$wi_purpose = 'Testing';
$final_purpose = '[Walk-in Requestor: ' . $wi_requestor_name . '] ' . $wi_purpose;

try {
    $pdo->prepare('INSERT INTO document_requests (user_id, doc_type, purpose, status, created_at) VALUES (?, ?, ?, "pending", NOW())')
        ->execute([$admin_uid, $wi_doc_type, $final_purpose]);
    echo "Success!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
