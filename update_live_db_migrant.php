<?php
require_once __DIR__ . '/config.php';

echo "<h2>Barangay System - Live Database Patch (Migrant Fields)</h2>";

try {
    $pdo = get_db_connection();
    
    // Columns to add to family_members table
    $columns_to_add = [
        'migrant_previous_residence' => "VARCHAR(255) DEFAULT NULL",
        'migrant_length_of_stay' => "VARCHAR(100) DEFAULT NULL",
        'migrant_reason_leaving' => "VARCHAR(255) DEFAULT NULL",
        'migrant_date_transfer' => "DATE DEFAULT NULL",
        'migrant_reason_for' => "VARCHAR(255) DEFAULT NULL",
        'migrant_duration' => "VARCHAR(100) DEFAULT NULL",
        'migrant_intention' => "VARCHAR(255) DEFAULT NULL"
    ];

    echo "<h3>Patching 'family_members' table...</h3>";
    $added = 0;
    
    foreach ($columns_to_add as $col => $definition) {
        $check = $pdo->query("SHOW COLUMNS FROM family_members LIKE '$col'");
        if ($check->rowCount() == 0) {
            echo "Adding column '$col'... ";
            $pdo->exec("ALTER TABLE family_members ADD COLUMN $col $definition");
            echo "<span style='color: green;'>DONE</span><br>";
            $added++;
        } else {
            echo "Column '$col' already exists. <span style='color: blue;'>SKIPPED</span><br>";
        }
    }

    echo "<br><h3 style='color: green;'>Database update complete! Added $added new column(s).</h3>";
    echo "<p>You should now be able to add family members without encountering a blank page.</p>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>PATCH FAILED!</h2>";
    echo "Error: " . $e->getMessage();
}
?>
