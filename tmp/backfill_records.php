<?php
require_once __DIR__ . '/../config.php';
$pdo = get_db_connection();

$verified_residents = $pdo->query('
    SELECT r.*, u.full_name, u.first_name, u.last_name, u.middle_name, u.suffix, u.email 
    FROM residents r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.verification_status = "verified"
')->fetchAll();

foreach ($verified_residents as $res) {
    $check_sql = 'SELECT id FROM resident_records WHERE ';
    $check_params = [];
    if (!empty($res['email'])) {
        $check_sql .= 'email = ? OR ';
        $check_params[] = $res['email'];
    }
    $check_sql .= 'full_name = ? LIMIT 1';
    $check_params[] = $res['full_name'];
    
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute($check_params);
    if (!$check_stmt->fetch()) {
        echo "Adding missing record for: " . $res['full_name'] . "\n";
        $insert = $pdo->prepare('
            INSERT INTO resident_records (
                email, first_name, last_name, middle_name, suffix, full_name, 
                address, phone, birthdate, sex, citizenship, civil_status, 
                purok, is_active, created_by, is_solo_parent, is_pwd, is_senior
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,1,?,?,?)
        ');
        $insert->execute([
            $res['email'] ?: null,
            $res['first_name'],
            $res['last_name'],
            $res['middle_name'] ?: null,
            $res['suffix'] ?: null,
            $res['full_name'],
            $res['address'],
            $res['phone'] ?: null,
            $res['birthdate'] ?: null,
            $res['sex'] ?: null,
            $res['citizenship'] ?: null,
            $res['civil_status'] ?: null,
            $res['purok'] ?: null,
            $res['is_solo_parent'] ?? 0,
            $res['is_pwd'] ?? 0,
            $res['is_senior'] ?? 0
        ]);
    } else {
        echo "Record exists for: " . $res['full_name'] . "\n";
    }
}
