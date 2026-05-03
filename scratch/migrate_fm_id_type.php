<?php
require_once __DIR__ . '/../config.php';
$pdo = get_db_connection();

try {
    $sql = "ALTER TABLE family_members ADD COLUMN id_type VARCHAR(100) NULL AFTER id_back_path";
    $pdo->exec($sql);
    echo "Database migrated successfully: Added id_type column to family_members table.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Migration skipped: Column already exists.\n";
    } else {
        echo "Migration error: " . $e->getMessage() . "\n";
    }
}
