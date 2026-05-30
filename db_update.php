<?php
require 'config.php';

echo "<h2>Database Updater</h2>";

try {
    $pdo = get_db_connection();
    
    // Check if previous_address exists in residents table
    $check_residents = $pdo->query("SHOW COLUMNS FROM residents LIKE 'previous_address'");
    if ($check_residents->rowCount() == 0) {
        $pdo->exec("ALTER TABLE residents ADD COLUMN previous_address VARCHAR(255) DEFAULT NULL AFTER address;");
        echo "<p style='color:green;'>✅ Successfully added 'previous_address' to 'residents' table.</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ 'previous_address' already exists in 'residents' table.</p>";
    }

    // Check if previous_address exists in resident_records table
    $check_records = $pdo->query("SHOW COLUMNS FROM resident_records LIKE 'previous_address'");
    if ($check_records->rowCount() == 0) {
        $pdo->exec("ALTER TABLE resident_records ADD COLUMN previous_address VARCHAR(255) DEFAULT NULL AFTER address;");
        echo "<p style='color:green;'>✅ Successfully added 'previous_address' to 'resident_records' table.</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ 'previous_address' already exists in 'resident_records' table.</p>";
    }

    echo "<hr><p><strong>Update complete! You can now safely delete this file (db_update.php) from your live server.</strong></p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
