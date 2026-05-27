<?php
$c = file_get_contents('admin/register_account.php');

$c = str_replace(
    "\$suffix = trim(\$_POST['suffix'] ?? '');",
    "\$suffix = trim(\$_POST['suffix'] ?? '');\n            \$birth_place = trim(\$_POST['birth_place'] ?? '');",
    $c
);

$c = str_replace(
    "INSERT INTO residents (user_id, address, phone, birthdate, citizenship, civil_status, sex, purok, verification_status, is_solo_parent, is_pwd, is_senior) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'verified', ?, ?, ?)",
    "INSERT INTO residents (user_id, address, phone, birthdate, birth_place, citizenship, civil_status, sex, purok, verification_status, is_solo_parent, is_pwd, is_senior) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'verified', ?, ?, ?)",
    $c
);

$c = str_replace(
    "\$stmt->execute([ \$user_id, \$address, \$phone ?: null, \$birthdate ?: null, \$citizenship ?: null, \$civil_status ?: null, \$sex ?: null, \$purok ?: null, \$is_solo_parent, \$is_pwd, \$is_senior ]);",
    "\$stmt->execute([ \$user_id, \$address, \$phone ?: null, \$birthdate ?: null, \$birth_place ?: null, \$citizenship ?: null, \$civil_status ?: null, \$sex ?: null, \$purok ?: null, \$is_solo_parent, \$is_pwd, \$is_senior ]);",
    $c
);

$c = str_replace(
    "INSERT INTO resident_records (first_name, last_name, middle_name, suffix, full_name, email, address, phone, birthdate, sex, citizenship, civil_status, purok, is_solo_parent, is_pwd, is_senior, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
    "INSERT INTO resident_records (first_name, last_name, middle_name, suffix, full_name, email, address, phone, birthdate, birth_place, sex, citizenship, civil_status, purok, is_solo_parent, is_pwd, is_senior, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
    $c
);

$c = str_replace(
    "execute([\$first_name, \$last_name, \$middle_name, \$suffix, \$full_name, \$email, \$address, \$phone, \$birthdate, \$sex, \$citizenship, \$civil_status, \$purok, \$is_solo_parent, \$is_pwd, \$is_senior, \$_SESSION['user_id']]);",
    "execute([\$first_name, \$last_name, \$middle_name, \$suffix, \$full_name, \$email, \$address, \$phone, \$birthdate, \$birth_place, \$sex, \$citizenship, \$civil_status, \$purok, \$is_solo_parent, \$is_pwd, \$is_senior, \$_SESSION['user_id']]);",
    $c
);

$c = str_replace(
    '<div class="col-md-4"><label class="form-label">Birthdate</label><input type="date" name="birthdate" class="form-control" max="<?php echo date(\'Y-m-d\', strtotime(\'-18 years\')); ?>" required></div>',
    '<div class="col-md-4"><label class="form-label">Place of Birth</label><input type="text" name="birth_place" class="form-control" placeholder="City, Province" required></div>
                    <div class="col-md-4"><label class="form-label">Birthdate</label><input type="date" name="birthdate" class="form-control" max="<?php echo date(\'Y-m-d\', strtotime(\'-18 years\')); ?>" required></div>',
    $c
);

$c = str_replace(
    '<div class="col-md-4"><label class="form-label">Civil Status</label>',
    '<div class="col-md-4"><label class="form-label">Civil Status</label>', // This is just to align, actually I need to fix the col-md-6 citizenship to col-md-4 to fit
    $c
);

$c = str_replace(
    '<div class="col-md-6"><label class="form-label">Citizenship</label><input type="text" name="citizenship" class="form-control" value="Filipino" required></div>',
    '<div class="col-md-4"><label class="form-label">Citizenship</label><input type="text" name="citizenship" class="form-control" value="Filipino" required></div>',
    $c
);

file_put_contents('admin/register_account.php', $c);
echo "fixed register\n";
