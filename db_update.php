<?php
require 'config.php';

echo "<h2 style='font-family:sans-serif;'>🔧 Database Updater</h2>";
echo "<div style='font-family:sans-serif; max-width:700px;'>";

$updates = [
    // residents table
    [
        'table' => 'residents',
        'column' => 'previous_address',
        'sql' => "ALTER TABLE residents ADD COLUMN previous_address VARCHAR(255) DEFAULT NULL AFTER address;"
    ],
    // resident_records table
    [
        'table' => 'resident_records',
        'column' => 'previous_address',
        'sql' => "ALTER TABLE resident_records ADD COLUMN previous_address VARCHAR(255) DEFAULT NULL AFTER address;"
    ],
    // barangay_clearances table
    [
        'table' => 'barangay_clearances',
        'column' => 'payment_method',
        'sql' => "ALTER TABLE barangay_clearances ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL AFTER status;"
    ],
    [
        'table' => 'barangay_clearances',
        'column' => 'payment_receipt',
        'sql' => "ALTER TABLE barangay_clearances ADD COLUMN payment_receipt VARCHAR(255) DEFAULT NULL AFTER payment_method;"
    ],
    [
        'table' => 'barangay_clearances',
        'column' => 'payment_reference_no',
        'sql' => "ALTER TABLE barangay_clearances ADD COLUMN payment_reference_no VARCHAR(100) DEFAULT NULL AFTER payment_receipt;"
    ],
    [
        'table' => 'barangay_clearances',
        'column' => 'payment_amount_paid',
        'sql' => "ALTER TABLE barangay_clearances ADD COLUMN payment_amount_paid DECIMAL(10,2) DEFAULT NULL AFTER payment_reference_no;"
    ],
    [
        'table' => 'barangay_clearances',
        'column' => 'payment_status',
        'sql' => "ALTER TABLE barangay_clearances ADD COLUMN payment_status VARCHAR(50) DEFAULT 'pending' AFTER payment_amount_paid;"
    ],
    [
        'table' => 'barangay_clearances',
        'column' => 'refund_number',
        'sql' => "ALTER TABLE barangay_clearances ADD COLUMN refund_number VARCHAR(100) DEFAULT NULL AFTER payment_status;"
    ],
    [
        'table' => 'barangay_clearances',
        'column' => 'refund_notes',
        'sql' => "ALTER TABLE barangay_clearances ADD COLUMN refund_notes TEXT DEFAULT NULL AFTER refund_number;"
    ],
    [
        'table' => 'barangay_clearances',
        'column' => 'refund_receipt',
        'sql' => "ALTER TABLE barangay_clearances ADD COLUMN refund_receipt VARCHAR(255) DEFAULT NULL AFTER refund_notes;"
    ],
    [
        'table' => 'barangay_clearances',
        'column' => 'admin_refund_number',
        'sql' => "ALTER TABLE barangay_clearances ADD COLUMN admin_refund_number VARCHAR(100) DEFAULT NULL AFTER refund_receipt;"
    ],
    [
        'table' => 'barangay_clearances',
        'column' => 'admin_refund_notes',
        'sql' => "ALTER TABLE barangay_clearances ADD COLUMN admin_refund_notes TEXT DEFAULT NULL AFTER admin_refund_number;"
    ],
    [
        'table' => 'barangay_clearances',
        'column' => 'admin_refund_amount',
        'sql' => "ALTER TABLE barangay_clearances ADD COLUMN admin_refund_amount DECIMAL(10,2) DEFAULT NULL AFTER admin_refund_notes;"
    ],
    [
        'table' => 'barangay_clearances',
        'column' => 'family_member_id',
        'sql' => "ALTER TABLE barangay_clearances ADD COLUMN family_member_id INT(11) DEFAULT NULL AFTER created_at;"
    ],
    [
        'table' => 'barangay_clearances',
        'column' => 'requestor_type',
        'sql' => "ALTER TABLE barangay_clearances ADD COLUMN requestor_type ENUM('self','family_member') DEFAULT 'self' AFTER family_member_id;"
    ],
    // document_requests table
    [
        'table' => 'document_requests',
        'column' => 'payment_method',
        'sql' => "ALTER TABLE document_requests ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL AFTER status;"
    ],
    [
        'table' => 'document_requests',
        'column' => 'payment_receipt',
        'sql' => "ALTER TABLE document_requests ADD COLUMN payment_receipt VARCHAR(255) DEFAULT NULL AFTER payment_method;"
    ],
    [
        'table' => 'document_requests',
        'column' => 'payment_reference_no',
        'sql' => "ALTER TABLE document_requests ADD COLUMN payment_reference_no VARCHAR(100) DEFAULT NULL AFTER payment_receipt;"
    ],
    [
        'table' => 'document_requests',
        'column' => 'payment_amount_paid',
        'sql' => "ALTER TABLE document_requests ADD COLUMN payment_amount_paid DECIMAL(10,2) DEFAULT NULL AFTER payment_reference_no;"
    ],
    [
        'table' => 'document_requests',
        'column' => 'payment_status',
        'sql' => "ALTER TABLE document_requests ADD COLUMN payment_status VARCHAR(50) DEFAULT 'pending' AFTER payment_amount_paid;"
    ],
    [
        'table' => 'document_requests',
        'column' => 'refund_number',
        'sql' => "ALTER TABLE document_requests ADD COLUMN refund_number VARCHAR(100) DEFAULT NULL AFTER payment_status;"
    ],
    [
        'table' => 'document_requests',
        'column' => 'refund_notes',
        'sql' => "ALTER TABLE document_requests ADD COLUMN refund_notes TEXT DEFAULT NULL AFTER refund_number;"
    ],
    [
        'table' => 'document_requests',
        'column' => 'refund_receipt',
        'sql' => "ALTER TABLE document_requests ADD COLUMN refund_receipt VARCHAR(255) DEFAULT NULL AFTER refund_notes;"
    ],
    [
        'table' => 'document_requests',
        'column' => 'admin_refund_number',
        'sql' => "ALTER TABLE document_requests ADD COLUMN admin_refund_number VARCHAR(100) DEFAULT NULL AFTER refund_receipt;"
    ],
    [
        'table' => 'document_requests',
        'column' => 'admin_refund_notes',
        'sql' => "ALTER TABLE document_requests ADD COLUMN admin_refund_notes TEXT DEFAULT NULL AFTER admin_refund_number;"
    ],
    [
        'table' => 'document_requests',
        'column' => 'admin_refund_amount',
        'sql' => "ALTER TABLE document_requests ADD COLUMN admin_refund_amount DECIMAL(10,2) DEFAULT NULL AFTER admin_refund_notes;"
    ],
    [
        'table' => 'document_requests',
        'column' => 'indigency_purposes',
        'sql' => "ALTER TABLE document_requests ADD COLUMN indigency_purposes VARCHAR(255) DEFAULT NULL AFTER notes;"
    ],
    [
        'table' => 'document_requests',
        'column' => 'family_member_id',
        'sql' => "ALTER TABLE document_requests ADD COLUMN family_member_id INT(11) DEFAULT NULL AFTER indigency_purposes;"
    ],
    [
        'table' => 'document_requests',
        'column' => 'requestor_type',
        'sql' => "ALTER TABLE document_requests ADD COLUMN requestor_type ENUM('self','family_member') DEFAULT 'self' AFTER family_member_id;"
    ],
    // family_members table
    [
        'table' => 'family_members',
        'column' => 'avatar',
        'sql' => "ALTER TABLE family_members ADD COLUMN avatar VARCHAR(255) DEFAULT NULL AFTER sex;"
    ],
];

try {
    $pdo = get_db_connection();
    $added = 0;
    $skipped = 0;

    foreach ($updates as $update) {
        $check = $pdo->query("SHOW COLUMNS FROM `{$update['table']}` LIKE '{$update['column']}'");
        if ($check->rowCount() == 0) {
            $pdo->exec($update['sql']);
            echo "<p style='color:green;'>✅ Added <strong>'{$update['column']}'</strong> to <strong>'{$update['table']}'</strong>.</p>";
            $added++;
        } else {
            echo "<p style='color:#888;'>ℹ️ <strong>'{$update['column']}'</strong> already exists in <strong>'{$update['table']}'</strong> — skipped.</p>";
            $skipped++;
        }
    }

    echo "<hr><p style='background:#d4edda;padding:12px;border-radius:6px;'>";
    echo "<strong>✅ Done!</strong> Added: <strong>$added</strong> column(s). Skipped (already exist): <strong>$skipped</strong>.<br>";
    echo "<strong>⚠️ Important: Please delete this file (db_update.php) from your live server after running it.</strong>";
    echo "</p>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div>";
?>
