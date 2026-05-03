<?php
require_once __DIR__ . '/../config.php';
$pdo = get_db_connection();

try {
    $sql = "ALTER TABLE family_members 
            ADD COLUMN verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending' AFTER is_active,
            ADD COLUMN id_front_path VARCHAR(255) NULL AFTER id_photo_path,
            ADD COLUMN id_back_path VARCHAR(255) NULL AFTER id_front_path,
            ADD COLUMN verified_at TIMESTAMP NULL AFTER id_back_path,
            ADD COLUMN verified_by INT NULL AFTER verified_at,
            ADD COLUMN verification_notes TEXT NULL AFTER verified_by";
    
    $pdo->exec($sql);
    echo "Database migrated successfully: Added verification columns to family_members table.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Migration skipped: Columns already exist.\n";
    } else {
        echo "Migration error: " . $e->getMessage() . "\n";
    }
}
