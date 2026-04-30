<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=barangay_system;charset=utf8mb4', 'root', '');
try {
    $record_id = 49;
    $linked_user_id = 45;
    $address = "123 Test";
    $phone = "09123456789";
    $birthdate = "2000-01-01";
    $sex = "Male";
    $citizenship = "Filipino";
    $civil_status = "Single";
    $purok = "Purok 1";
    $is_solo_parent = 0;
    $is_pwd = 0;
    $is_senior = 0;
    $religion = "Catholic";
    $occupation = "Student";
    $educational_attainment = "College";
    $classification_json = "[]";
    $barangay_id = "BGY-123";

    $pdo->prepare('UPDATE residents SET address=?, phone=?, birthdate=?, sex=?, citizenship=?, civil_status=?, purok=?, is_solo_parent=?, is_pwd=?, is_senior=?, religion=?, occupation=?, educational_attainment=?, classification=?, barangay_id=? WHERE user_id=?')
        ->execute([$address, $phone ?: null, $birthdate ?: null, $sex ?: null, $citizenship ?: null, $civil_status ?: null, $purok ?: null, $is_solo_parent, $is_pwd, $is_senior, $religion ?: null, $occupation ?: null, $educational_attainment ?: null, $classification_json, $barangay_id, $linked_user_id]);
    echo "Success residents\n";
    
    $email = "test@test.com";
    $first_name = "First";
    $last_name = "Last";
    $middle_name = "Middle";
    $suffix = "";
    $full_name = "First Middle Last";
    $is_active = 1;
    
    $pdo->prepare('UPDATE resident_records SET email = ?, first_name = ?, last_name = ?, middle_name = ?, suffix = ?, full_name = ?, address = ?, phone = ?, birthdate = ?, sex = ?, citizenship = ?, civil_status = ?, purok = ?, is_active = ?, is_solo_parent = ?, is_pwd = ?, is_senior = ?, barangay_id = ? WHERE id = ?')
        ->execute([$email ?: null, $first_name, $last_name, $middle_name ?: null, $suffix ?: null, $full_name, $address, $phone ?: null, $birthdate ?: null, $sex ?: null, $citizenship ?: null, $civil_status ?: null, $purok ?: null, $is_active, $is_solo_parent, $is_pwd, $is_senior, $barangay_id ?: null, $record_id]);
        
    echo "Success resident_records\n";
    
    $pdo->prepare('UPDATE users SET first_name=?, last_name=?, middle_name=?, suffix=?, full_name=? WHERE id=?')
                                ->execute([$first_name, $last_name, $middle_name ?: null, $suffix ?: null, $full_name, $linked_user_id]);
    echo "Success users\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
