<?php
require_once 'config.php';
$pdo = get_db_connection();
try {
    $pdo->exec("ALTER TABLE family_members ADD COLUMN religion VARCHAR(100) NULL, ADD COLUMN occupation VARCHAR(100) NULL, ADD COLUMN birth_place VARCHAR(255) NULL, ADD COLUMN educational_attainment VARCHAR(100) NULL, ADD COLUMN classification TEXT NULL");
    echo "SUCCESS: Columns added to family_members.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "ALREADY EXISTS: Columns were likely added.\n";
    } else {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

// Check residents table columns too
try {
    // Add educational_status if missing
    $pdo->exec("ALTER TABLE residents ADD COLUMN educational_status VARCHAR(50) NULL");
    echo "SUCCESS: educational_status added to residents.\n";
} catch (Exception $e) {
    echo "NOTICE: " . $e->getMessage() . "\n";
}
try {
    // Add educational_status to family_members if missing
    $pdo->exec("ALTER TABLE family_members ADD COLUMN educational_status VARCHAR(50) NULL");
    echo "SUCCESS: educational_status added to family_members.\n";
} catch (Exception $e) {
    echo "NOTICE: " . $e->getMessage() . "\n";
}
