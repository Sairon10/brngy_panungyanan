<?php
/**
 * Migration script to create document_types table
 * Run this once to set up the document types system
 */

require_once __DIR__ . '/../config.php';

$pdo = get_db_connection();

try {
    // Create document_types table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS document_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            requires_validity TINYINT(1) NOT NULL DEFAULT 0,
            requires_special_handling TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            display_order INT NOT NULL DEFAULT 0,
            price DECIMAL(10, 2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Add price column if it doesn't exist (for existing installations)
    try {
        $pdo->exec("ALTER TABLE document_types ADD COLUMN IF NOT EXISTS price DECIMAL(10, 2) DEFAULT 0.00 AFTER display_order");
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }
    
    // Insert default document types
    $default_types = [
        ['name' => 'Barangay Clearance', 'requires_validity' => 1, 'requires_special_handling' => 1, 'display_order' => 1, 'price' => 50.00],
        ['name' => 'Certificate of Residency', 'requires_validity' => 0, 'requires_special_handling' => 0, 'display_order' => 2, 'price' => 30.00],
        ['name' => 'Indigency', 'requires_validity' => 0, 'requires_special_handling' => 0, 'display_order' => 3, 'price' => 25.00],
        ['name' => 'Resident ID', 'requires_validity' => 0, 'requires_special_handling' => 0, 'display_order' => 4, 'price' => 100.00],
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO document_types (name, requires_validity, requires_special_handling, display_order, price, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE 
            requires_validity = VALUES(requires_validity),
            requires_special_handling = VALUES(requires_special_handling),
            display_order = VALUES(display_order),
            price = VALUES(price)
    ");
    
    foreach ($default_types as $type) {
        $stmt->execute([
            $type['name'],
            $type['requires_validity'],
            $type['requires_special_handling'],
            $type['display_order'],
            $type['price']
        ]);
    }
    
    echo "✓ Document types table created successfully!\n";
    echo "✓ Default document types inserted.\n";
    echo "\nYou can now manage document types from the admin panel.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

