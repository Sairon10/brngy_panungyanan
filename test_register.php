<?php
require 'config.php';
$pdo = get_db_connection();
try {
    $email = 'test@test.com' . time();
    $hash = 'hash';
    $full_name = 'Test Name';
    $first_name = 'Test';
    $last_name = 'Name';
    
    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, full_name, first_name, last_name, middle_name, suffix, role) VALUES (?,?,?,?,?,?,?,\'resident\')');
    $stmt->execute([$email, $hash, $full_name, $first_name, $last_name, null, null]);
    
    $user_id = $pdo->lastInsertId();
    $stmt = $pdo->prepare('INSERT INTO residents (user_id, address, previous_address, phone, birthdate, citizenship, civil_status, sex, purok, verification_status, is_solo_parent, is_pwd, is_senior) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', ?, ?, ?)');
    $stmt->execute([
        $user_id,
        'Test Address',
        null,
        '09123456789',
        '2000-01-01',
        'Filipino',
        'Single',
        'Male',
        'Purok 1',
        0,
        0,
        0
    ]);
    echo "Success";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
