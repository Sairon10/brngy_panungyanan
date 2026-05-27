<?php
$c = file_get_contents('admin/admin_info.php');

$c = str_replace(
    "\$birthdate = \$_POST['birthdate'];",
    "\$birthdate = \$_POST['birthdate'];\n        \$birth_place = trim(\$_POST['birth_place'] ?? '');",
    $c
);

$c = str_replace(
    "UPDATE residents SET address = ?, phone = ?, sex = ?, civil_status = ?, birthdate = ?, citizenship = ?, purok = ?, avatar = ? WHERE user_id = ?",
    "UPDATE residents SET address = ?, phone = ?, sex = ?, civil_status = ?, birthdate = ?, birth_place = ?, citizenship = ?, purok = ?, avatar = ? WHERE user_id = ?",
    $c
);

$c = str_replace(
    "execute([\$address, \$phone, \$sex, \$civil_status, \$birthdate, \$citizenship, \$purok, \$profile_picture, \$admin_id]);",
    "execute([\$address, \$phone, \$sex, \$civil_status, \$birthdate, \$birth_place, \$citizenship, \$purok, \$profile_picture, \$admin_id]);",
    $c
);

$c = str_replace(
    "INSERT INTO residents (user_id, address, phone, sex, civil_status, birthdate, citizenship, purok, avatar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
    "INSERT INTO residents (user_id, address, phone, sex, civil_status, birthdate, birth_place, citizenship, purok, avatar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
    $c
);

$c = str_replace(
    "execute([\$admin_id, \$address, \$phone, \$sex, \$civil_status, \$birthdate, \$citizenship, \$purok, \$profile_picture]);",
    "execute([\$admin_id, \$address, \$phone, \$sex, \$civil_status, \$birthdate, \$birth_place, \$citizenship, \$purok, \$profile_picture]);",
    $c
);

$c = str_replace(
    "SELECT u.*, r.address, r.phone, r.birthdate, r.sex, r.civil_status, r.citizenship, r.purok",
    "SELECT u.*, r.address, r.phone, r.birthdate, r.birth_place, r.sex, r.civil_status, r.citizenship, r.purok",
    $c
);

$html_block = '<div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-map-marker-alt"></i> Place of Birth</div>
                                        <?php if ($is_editing): ?><input type="text" name="birth_place" class="form-control" value="<?php echo htmlspecialchars($admin[\'birth_place\'] ?? \'\'); ?>" placeholder="e.g. Manila" required>
                                        <?php else: ?><div class="info-value"><?php echo htmlspecialchars($admin[\'birth_place\'] ?: \'N/A\'); ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-calendar"></i> Birthdate</div>';

$c = str_replace(
    '<div class="col-md-4">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-calendar"></i> Birthdate</div>',
    $html_block,
    $c
);

// I need to fix the col-md sizes in admin_info.php
// Line 186: citizenship was col-md-6, now I need to fit birthdate and birth_place
// Wait, the original was citizenship (col-md-6) and birthdate (col-md-4), sex(4), civil_status(4) => wait, that doesn't add up to 12.
// In admin_info.php: 
// 179: full_name (col-md-6)
// 186: citizenship (col-md-6)  <- row 1 = 12
// 193: birthdate (col-md-4)
// 200: sex (col-md-4)
// 211: civil_status (col-md-4) <- row 2 = 12

// If I add place of birth, I can make:
// row 2: birth_place (col-md-6), birthdate (col-md-6)
// row 3: sex (col-md-6), civil_status (col-md-6)

$c = str_replace(
    '<div class="col-md-4">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-venus-mars"></i> Sex</div>',
    '<div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-venus-mars"></i> Sex</div>',
    $c
);

$c = str_replace(
    '<div class="col-md-4">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-heart"></i> Civil Status</div>',
    '<div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-heart"></i> Civil Status</div>',
    $c
);

file_put_contents('admin/admin_info.php', $c);
echo "fixed admin info\n";
