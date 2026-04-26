<?php
require_once __DIR__ . '/config.php';
$pdo = get_db_connection();
try {
    $pdo->exec("ALTER TABLE family_members 
        ADD COLUMN IF NOT EXISTS first_name VARCHAR(255) AFTER id,
        ADD COLUMN IF NOT EXISTS middle_name VARCHAR(255) AFTER first_name,
        ADD COLUMN IF NOT EXISTS last_name VARCHAR(255) AFTER middle_name");
    
    // Migrate data if new columns are empty
    $stmt = $pdo->query("SELECT id, full_name, first_name FROM family_members");
    while ($row = $stmt->fetch()) {
        if (empty($row['first_name'])) {
            $parts = explode(' ', trim($row['full_name']));
            $first = $parts[0] ?? '';
            $last = count($parts) > 1 ? end($parts) : '';
            $middle = count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : '';
            
            $update = $pdo->prepare("UPDATE family_members SET first_name=?, middle_name=?, last_name=? WHERE id=?");
            $update->execute([$first, $middle, $last, $row['id']]);
        }
    }
    echo "Success: Migration complete.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
