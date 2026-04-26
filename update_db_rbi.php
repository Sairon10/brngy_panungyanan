<?php
require_once __DIR__ . '/config.php';
$pdo = get_db_connection();

try {
    $columns = [
        "philsys_card_no VARCHAR(100) NULL",
        "is_family_head TINYINT(1) DEFAULT 0",
        "birth_place VARCHAR(255) NULL",
        "religion VARCHAR(100) NULL",
        "occupation VARCHAR(100) NULL",
        "educational_attainment VARCHAR(100) NULL",
        "classification TEXT NULL", // JSON or CSV for PWD, OFW, etc.
        "is_rbi_completed TINYINT(1) DEFAULT 0"
    ];

    foreach ($columns as $col) {
        $name = explode(' ', $col)[0];
        // Check if column exists
        $check = $pdo->query("SHOW COLUMNS FROM residents LIKE '$name'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE residents ADD COLUMN $col");
            echo "Added column: $name\n";
        } else {
            echo "Column exists: $name\n";
        }
    }
    echo "Database updated successfully for RBI Form.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
