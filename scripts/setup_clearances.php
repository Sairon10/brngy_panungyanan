<?php
require_once __DIR__ . '/../config.php';

// Create the barangay_clearances table if it doesn't exist
$pdo = get_db_connection();

$sql = "
CREATE TABLE IF NOT EXISTS barangay_clearances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    clearance_number VARCHAR(50) NOT NULL UNIQUE,
    purpose TEXT NOT NULL,
    validity_days INT NOT NULL DEFAULT 30,
    status ENUM(
        'pending',
        'approved',
        'rejected',
        'released'
    ) DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    approved_by INT DEFAULT NULL,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL
)";

try {
    $pdo->exec($sql);
    echo "Barangay clearances table created successfully!";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>
