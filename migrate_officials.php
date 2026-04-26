<?php
require_once 'config.php';
$pdo = get_db_connection();
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS barangay_officials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        position VARCHAR(100) NOT NULL,
        image_path VARCHAR(255) DEFAULT NULL,
        rank INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Seed initial positions if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM barangay_officials");
    if ($stmt->fetchColumn() == 0) {
        $officials = [
            ['name' => 'Pending Name', 'position' => 'Barangay Captain', 'rank' => 1],
            ['name' => 'Pending Name', 'position' => 'Barangay Councilor', 'rank' => 2],
            ['name' => 'Pending Name', 'position' => 'Barangay Councilor', 'rank' => 3],
            ['name' => 'Pending Name', 'position' => 'Barangay Councilor', 'rank' => 4],
            ['name' => 'Pending Name', 'position' => 'Barangay Councilor', 'rank' => 5],
            ['name' => 'Pending Name', 'position' => 'Barangay Councilor', 'rank' => 6],
            ['name' => 'Pending Name', 'position' => 'Barangay Councilor', 'rank' => 7],
            ['name' => 'Pending Name', 'position' => 'Barangay Councilor', 'rank' => 8],
            ['name' => 'Pending Name', 'position' => 'SK Chairman', 'rank' => 9],
            ['name' => 'Pending Name', 'position' => 'Barangay Secretary', 'rank' => 10],
            ['name' => 'Pending Name', 'position' => 'Barangay Treasurer', 'rank' => 11]
        ];
        $stmt = $pdo->prepare("INSERT INTO barangay_officials (name, position, rank) VALUES (?, ?, ?)");
        foreach ($officials as $o) {
            $stmt->execute([$o['name'], $o['position'], $o['rank']]);
        }
    }
    echo "Table created and seeded successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
