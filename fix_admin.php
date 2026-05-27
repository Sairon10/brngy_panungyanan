<?php
$c = file_get_contents('admin/admin_info_view.php');

$c = str_replace(
    "\$birthdate = \$_POST['birthdate'] ?? null;",
    "\$birthdate = \$_POST['birthdate'] ?? null;\n            \$birth_place = trim(\$_POST['birth_place'] ?? '');",
    $c
);

$c = str_replace(
    "UPDATE residents SET address = ?, phone = ?, sex = ?, civil_status = ?, birthdate = ?, citizenship = ?, purok = ?, is_solo_parent = ?, is_pwd = ?, is_senior = ?, avatar = ?, religion = ?, occupation = ?, educational_attainment = ?, classification = ?, barangay_id = ? WHERE user_id = ?",
    "UPDATE residents SET address = ?, phone = ?, sex = ?, civil_status = ?, birthdate = ?, birth_place = ?, citizenship = ?, purok = ?, is_solo_parent = ?, is_pwd = ?, is_senior = ?, avatar = ?, religion = ?, occupation = ?, educational_attainment = ?, classification = ?, barangay_id = ? WHERE user_id = ?",
    $c
);

$c = str_replace(
    "execute([\$address, \$phone, \$sex, \$civil_status, \$birthdate, \$citizenship, \$purok, \$is_solo_parent, \$is_pwd, \$is_senior, \$profile_picture, \$religion, \$occupation, \$educational_attainment, \$classification_json, \$barangay_id, \$admin_id]);",
    "execute([\$address, \$phone, \$sex, \$civil_status, \$birthdate, \$birth_place, \$citizenship, \$purok, \$is_solo_parent, \$is_pwd, \$is_senior, \$profile_picture, \$religion, \$occupation, \$educational_attainment, \$classification_json, \$barangay_id, \$admin_id]);",
    $c
);

$c = str_replace(
    "INSERT INTO residents (user_id, address, phone, sex, civil_status, birthdate, citizenship, purok, is_solo_parent, is_pwd, is_senior, avatar, religion, occupation, educational_attainment, classification, barangay_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
    "INSERT INTO residents (user_id, address, phone, sex, civil_status, birthdate, birth_place, citizenship, purok, is_solo_parent, is_pwd, is_senior, avatar, religion, occupation, educational_attainment, classification, barangay_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
    $c
);

$c = str_replace(
    "execute([\$admin_id, \$address, \$phone, \$sex, \$civil_status, \$birthdate, \$citizenship, \$purok, \$is_solo_parent, \$is_pwd, \$is_senior, \$profile_picture, \$religion, \$occupation, \$educational_attainment, \$classification_json, \$barangay_id]);",
    "execute([\$admin_id, \$address, \$phone, \$sex, \$civil_status, \$birthdate, \$birth_place, \$citizenship, \$purok, \$is_solo_parent, \$is_pwd, \$is_senior, \$profile_picture, \$religion, \$occupation, \$educational_attainment, \$classification_json, \$barangay_id]);",
    $c
);

$html_block = '                                <div class="col-md-6">
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
    '<div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-calendar"></i> Birthdate</div>',
    $html_block,
    $c
);

file_put_contents('admin/admin_info_view.php', $c);
echo "fixed admin\n";
