<?php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['walkin_action'] = '1';
$_POST['wi_requestor_name'] = 'Web UI Tester';
$_POST['wi_doc_type'] = 'Barangay Indigency';
$_POST['wi_purpose'] = 'For Test UI';

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

// Manually set a csrf_token
$_SESSION['csrf_token'] = 'test_token';
$_POST['csrf_token'] = 'test_token';

// Capture output
ob_start();
require 'admin/requests.php';
$output = ob_get_clean();

if (isset($_SESSION['action_success'])) {
    echo "SUCCESS SET!\n";
    print_r($_SESSION['action_success']);
} else {
    echo "NO SUCCESS VARIABLE SET.\n";
}

echo "Did it insert to DB? Let's check:\n";
require_once 'config.php';
$pdo = get_db_connection();
$stmt = $pdo->query("SELECT * FROM document_requests ORDER BY id DESC LIMIT 1");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
