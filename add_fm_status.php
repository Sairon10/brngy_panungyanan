<?php
require_once __DIR__ . '/config.php';
$pdo = get_db_connection();

try {
    $pdo->exec("ALTER TABLE family_members ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER id_photo_path");
    echo "Successfully added is_active column to family_members table.\n";
} catch (Exception $e) {
    echo "Error or column already exists: " . $e->getMessage() . "\n";
}
?>
