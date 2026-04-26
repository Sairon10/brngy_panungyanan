<?php
/**
 * Migration script to ensure all profile fields are available in the database
 * This ensures compatibility with the updated profile.php page
 */
require_once __DIR__ . '/../config.php';

try {
    $pdo = get_db_connection();
    
    echo "Starting database migration for profile fields...\n\n";
    
    // 1. Update users table - ensure email is nullable
    try {
        $pdo->exec("ALTER TABLE users MODIFY email VARCHAR(190) NULL");
        echo "✓ Made users.email nullable.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            echo "Notice: users.email modification - " . $e->getMessage() . "\n";
        }
    }
    
    // 2. Ensure all name fields exist in users table
    $nameFields = [
        'first_name' => 'VARCHAR(100) DEFAULT NULL',
        'last_name' => 'VARCHAR(100) DEFAULT NULL',
        'middle_name' => 'VARCHAR(100) DEFAULT NULL',
        'suffix' => 'VARCHAR(20) DEFAULT NULL'
    ];
    
    foreach ($nameFields as $field => $definition) {
        try {
            // Check if column exists
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE '$field'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE users ADD COLUMN $field $definition");
                echo "✓ Added column users.$field.\n";
            } else {
                echo "✓ Column users.$field already exists.\n";
            }
        } catch (PDOException $e) {
            echo "Notice: users.$field - " . $e->getMessage() . "\n";
        }
    }
    
    // 3. Ensure all fields exist in residents table
    $residentFields = [
        'citizenship' => 'VARCHAR(100) DEFAULT NULL',
        'civil_status' => "ENUM('Single', 'Married', 'Widowed', 'Divorced', 'Separated') DEFAULT NULL",
        'sex' => "ENUM('Male', 'Female', 'Other') DEFAULT NULL",
        'birthdate' => 'DATE DEFAULT NULL',
        'phone' => 'VARCHAR(50) DEFAULT NULL',
        'address' => 'VARCHAR(255) DEFAULT NULL',
        'household_id' => 'VARCHAR(64) DEFAULT NULL',
        'barangay_id' => 'VARCHAR(64) DEFAULT NULL'
    ];
    
    foreach ($residentFields as $field => $definition) {
        try {
            // Check if column exists
            $stmt = $pdo->query("SHOW COLUMNS FROM residents LIKE '$field'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE residents ADD COLUMN $field $definition");
                echo "✓ Added column residents.$field.\n";
            } else {
                echo "✓ Column residents.$field already exists.\n";
            }
        } catch (PDOException $e) {
            echo "Notice: residents.$field - " . $e->getMessage() . "\n";
        }
    }
    
    // 4. Update full_name for existing users if name parts exist but full_name is empty
    try {
        $stmt = $pdo->query("
            UPDATE users 
            SET full_name = TRIM(CONCAT_WS(' ', first_name, middle_name, last_name, suffix))
            WHERE (full_name IS NULL OR full_name = '') 
            AND (first_name IS NOT NULL OR last_name IS NOT NULL)
        ");
        $updated = $stmt->rowCount();
        if ($updated > 0) {
            echo "✓ Updated full_name for $updated users.\n";
        }
    } catch (PDOException $e) {
        echo "Notice: full_name update - " . $e->getMessage() . "\n";
    }
    
    echo "\n✓ Database migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

