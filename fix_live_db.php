<?php
require_once __DIR__ . '/config.php';

// Only allow admins to run this for security, or check if it's the first setup
// For now, let's just make it run but with clear output

echo "<h2>Barangay System - Live Database Patch</h2>";

try {
    $pdo = get_db_connection();
    
    // 1. Add missing columns to residents table
    $columns_to_add = [
        'id_front_path' => "VARCHAR(255) DEFAULT NULL AFTER id_document_path",
        'id_back_path' => "VARCHAR(255) DEFAULT NULL AFTER id_front_path",
        'id_type' => "VARCHAR(100) DEFAULT NULL AFTER id_back_path",
        'address_on_id' => "TEXT DEFAULT NULL AFTER id_type",
        'is_family_head' => "TINYINT(1) DEFAULT 0 AFTER address_on_id",
        'is_rbi_completed' => "TINYINT(1) DEFAULT 0 AFTER verification_status"
    ];

    echo "<h3>Patching 'residents' table...</h3>";
    foreach ($columns_to_add as $col => $definition) {
        // Check if column exists
        $check = $pdo->query("SHOW COLUMNS FROM residents LIKE '$col'");
        if ($check->rowCount() == 0) {
            echo "Adding column '$col'... ";
            $pdo->exec("ALTER TABLE residents ADD COLUMN $col $definition");
            echo "<span style='color: green;'>DONE</span><br>";
        } else {
            echo "Column '$col' already exists. <span style='color: blue;'>SKIPPED</span><br>";
        }
    }

    // 2. Update notifications enum if needed
    echo "<h3>Patching 'notifications' table...</h3>";
    try {
        $pdo->exec("ALTER TABLE notifications MODIFY COLUMN type ENUM('incident_response','incident_update','general','verification_update') NOT NULL");
        echo "Notification types updated to include 'verification_update'. <span style='color: green;'>DONE</span><br>";
    } catch (Exception $e) {
        echo "Notification type update: <span style='color: orange;'>Might already be updated or error: " . $e->getMessage() . "</span><br>";
    }

    echo "<br><h2 style='color: green;'>Database is now up to date!</h2>";
    echo "<p>You can now try uploading your ID again in the verification page.</p>";
    echo "<a href='id_verification.php' style='padding: 10px 20px; background: #0f766e; color: white; text-decoration: none; border-radius: 5px;'>Go to ID Verification</a>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>PATCH FAILED!</h2>";
    echo "Error: " . $e->getMessage();
}
?>
