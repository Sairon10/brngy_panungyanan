<?php
require_once __DIR__ . '/../config.php';
$pdo = get_db_connection();

$search = '';
$params = [];
$query = '
    SELECT rr.*, u.full_name as created_by_name,
           res.verification_status
    FROM resident_records rr
    LEFT JOIN users u ON rr.created_by = u.id
    LEFT JOIN users ru ON (rr.email IS NOT NULL AND ru.email = rr.email) OR (rr.email IS NULL AND ru.full_name = rr.full_name)
    LEFT JOIN residents res ON res.user_id = ru.id
';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

$allFamilyMembers = [];
foreach ($records as $rec) {
    $uId = null;
    if (!empty($rec['email'])) {
        $uStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $uStmt->execute([$rec['email']]);
        $uRow = $uStmt->fetch();
        if ($uRow) $uId = $uRow['id'];
    }
    if (!$uId && !empty($rec['full_name'])) {
        $uStmt = $pdo->prepare('SELECT id FROM users WHERE full_name = ? LIMIT 1');
        $uStmt->execute([$rec['full_name']]);
        $uRow = $uStmt->fetch();
        if ($uRow) $uId = $uRow['id'];
    }
    if ($uId) {
        $fmStmt = $pdo->prepare('SELECT * FROM family_members WHERE user_id = ? ORDER BY full_name');
        $fmStmt->execute([$uId]);
        $allFamilyMembers[$rec['id']] = $fmStmt->fetchAll();
        echo "Found ".(count($allFamilyMembers[$rec['id']]))." members for RR ID {$rec['id']} (User ID {$uId}, Name: {$rec['full_name']})\n";
    } else {
        echo "No link for RR ID {$rec['id']} (Name: {$rec['full_name']})\n";
    }
}
