<?php
require_once __DIR__ . '/../config.php';

try {
    $pdo = get_db_connection();

    // 1. Modify resident_records table
    // Remove UNIQUE constraint on email by dropping the index (usually named 'email')
    try {
        $pdo->exec("ALTER TABLE resident_records DROP INDEX email");
        echo "Dropped index 'email' from resident_records.\n";
    } catch (PDOException $e) {
        // Index might not exist or have a different name, ignore for now or check
        echo "Notice: " . $e->getMessage() . "\n";
    }

    // Make email nullable
    $pdo->exec("ALTER TABLE resident_records MODIFY email VARCHAR(190) NULL");
    echo "Made resident_records.email nullable.\n";

    // 2. Modify users table
    // Make email nullable
    $pdo->exec("ALTER TABLE users MODIFY email VARCHAR(190) NULL");
    echo "Made users.email nullable.\n";

    // Make full_name UNIQUE (since we might login with it)
    // Note: This might fail if duplicates exist.
    // We'll try it. If it fails, we might need manual cleanup recommendation.
    try {
        $pdo->exec("ALTER TABLE users ADD UNIQUE (full_name)");
        echo "Added UNIQUE constraint to users.full_name.\n";
    } catch (PDOException $e) {
        echo "Notice: Could not make users.full_name UNIQUE. " . $e->getMessage() . "\n";
    }

    // Remove UNIQUE constraint on email in users table if we want to allow multiple NULLs? 
    // MySQL unique constraint allows multiple NULLs. So we don't strictly need to drop the unique constraint on email 
    // unless we want to allow non-null duplicates (we don't).

    echo "Database migration completed.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
