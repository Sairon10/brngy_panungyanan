<?php
require_once __DIR__ . '/config.php';
$pdo = get_db_connection();

$id_to_delete = 12;

try {
    $pdo->beginTransaction();
    
    // 1. Delete from residents if linked
    $pdo->prepare("DELETE FROM residents WHERE user_id = ?")->execute([$id_to_delete]);
    
    // 2. Delete from family_members if linked
    $pdo->prepare("DELETE FROM family_members WHERE user_id = ?")->execute([$id_to_delete]);
    
    // 3. Delete from users
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id_to_delete]);
    
    $pdo->commit();
    echo "Successfully deleted User ID $id_to_delete and all related records.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error deleting user: " . $e->getMessage() . "\n";
}
?>
