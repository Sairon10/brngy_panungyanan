<?php
require_once __DIR__ . '/config.php';

// Only allow admins or run via command line (but for live, we might need a simpler check)
// For this specific task, we'll just run it.

$pdo = get_db_connection();

echo "Starting database update...<br>";

try {
    // 1. Add columns to residents table
    $columns_to_add = [
        'id_front_path' => "VARCHAR(255) DEFAULT NULL AFTER id_document_path",
        'id_back_path' => "VARCHAR(255) DEFAULT NULL AFTER id_front_path",
        'address_on_id' => "TEXT DEFAULT NULL AFTER id_back_path",
        'is_rbi_completed' => "TINYINT(1) DEFAULT 0 AFTER verification_status"
    ];

    foreach ($columns_to_add as $col => $definition) {
        $check = $pdo->query("SHOW COLUMNS FROM residents LIKE '$col'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE residents ADD COLUMN $col $definition");
            echo "Added column '$col' to residents table.<br>";
        } else {
            echo "Column '$col' already exists in residents table.<br>";
        }
    }

    // 2. Update notifications type enum
    // First check existing enum values
    $stmt = $pdo->prepare("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'notifications' AND COLUMN_NAME = 'type'");
    $stmt->execute();
    $row = $stmt->fetch();
    $enum_type = $row['COLUMN_TYPE'];

    if (strpos($enum_type, 'verification_update') === false) {
        // Need to update the enum
        // We'll just set it to a wide enough list or just add our new one
        // Standard way is to re-define the whole enum
        $pdo->exec("ALTER TABLE notifications MODIFY COLUMN type ENUM('incident_response', 'incident_update', 'general', 'verification_update', 'document_status') NOT NULL");
        echo "Updated notifications.type enum.<br>";
    } else {
        echo "notifications.type enum already contains 'verification_update'.<br>";
    }

    echo "<br><b>Database update completed successfully!</b>";
} catch (Exception $e) {
    echo "<br><b style='color:red;'>Error during update:</b> " . $e->getMessage();
}
?>
