<?php
/**
 * API: Check if a payment reference number already exists in the system.
 * Returns JSON { exists: bool, message: string }
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['exists' => false, 'message' => 'Unauthorized']);
    exit;
}

$ref = trim($_GET['ref'] ?? '');

if (strlen($ref) < 5) {
    echo json_encode(['exists' => false, 'message' => 'Too short']);
    exit;
}

$pdo = get_db_connection();

// Check in barangay_clearances
$stmt = $pdo->prepare("
    SELECT id FROM barangay_clearances
    WHERE payment_reference_no = ?
      AND payment_status NOT IN ('rejected', 'refunded')
    LIMIT 1
");
$stmt->execute([$ref]);
if ($stmt->fetch()) {
    echo json_encode([
        'exists'  => true,
        'message' => 'This reference number has already been used for another payment. Please check your receipt and try a different one.',
    ]);
    exit;
}

// Check in document_requests
$stmt2 = $pdo->prepare("
    SELECT id FROM document_requests
    WHERE payment_reference_no = ?
      AND payment_status NOT IN ('rejected', 'refunded')
    LIMIT 1
");
$stmt2->execute([$ref]);
if ($stmt2->fetch()) {
    echo json_encode([
        'exists'  => true,
        'message' => 'This reference number has already been used for another payment. Please check your receipt and try a different one.',
    ]);
    exit;
}

echo json_encode(['exists' => false, 'message' => 'OK']);
