<?php
$lines = file('admin/admin_info.php');
foreach ($lines as $i => &$line) {
    if (strpos($line, '$birthdate = $_POST[\'birthdate\'];') !== false) {
        $line = $line . "        \$birth_place = trim(\$_POST['birth_place'] ?? '');\n";
    }
    if (strpos($line, 'UPDATE residents SET address = ?, phone = ?, sex = ?, civil_status = ?, birthdate = ?, citizenship = ?, purok = ?, avatar = ? WHERE user_id = ?') !== false) {
        $line = str_replace('birthdate = ?, citizenship = ?', 'birthdate = ?, birth_place = ?, citizenship = ?', $line);
    }
    if (strpos($line, 'execute([$address, $phone, $sex, $civil_status, $birthdate, $citizenship, $purok, $profile_picture, $admin_id]);') !== false) {
        $line = str_replace('$birthdate, $citizenship', '$birthdate, $birth_place, $citizenship', $line);
    }
    if (strpos($line, 'INSERT INTO residents (user_id, address, phone, sex, civil_status, birthdate, citizenship, purok, avatar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)') !== false) {
        $line = str_replace('birthdate, citizenship', 'birthdate, birth_place, citizenship', str_replace('?, ?, ?)', '?, ?, ?, ?)', $line));
    }
    if (strpos($line, 'execute([$admin_id, $address, $phone, $sex, $civil_status, $birthdate, $citizenship, $purok, $profile_picture]);') !== false) {
        $line = str_replace('$birthdate, $citizenship', '$birthdate, $birth_place, $citizenship', $line);
    }
    if (strpos($line, 'SELECT u.*, r.address, r.phone, r.birthdate, r.sex, r.civil_status, r.citizenship, r.purok') !== false) {
        $line = str_replace('r.birthdate, r.sex', 'r.birthdate, r.birth_place, r.sex', $line);
    }
    // Fix UI (lines 193-214 area)
    if (strpos($line, '<div class="col-md-4">') !== false && isset($lines[$i+1]) && strpos($lines[$i+1], 'fa-calendar') !== false) {
        $line = '                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-map-marker-alt"></i> Place of Birth</div>
                                        <?php if ($is_editing): ?><input type="text" name="birth_place" class="form-control" value="<?php echo htmlspecialchars($admin[\'birth_place\'] ?? \'\'); ?>" placeholder="e.g. Manila" required>
                                        <?php else: ?><div class="info-value"><?php echo htmlspecialchars($admin[\'birth_place\'] ?: \'N/A\'); ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
';
    }
    if (strpos($line, '<div class="col-md-4">') !== false && isset($lines[$i+1]) && strpos($lines[$i+1], 'fa-venus-mars') !== false) {
        $line = '                                <div class="col-md-6">' . "\n";
    }
    if (strpos($line, '<div class="col-md-4">') !== false && isset($lines[$i+1]) && strpos($lines[$i+1], 'fa-heart') !== false) {
        $line = '                                <div class="col-md-6">' . "\n";
    }
}
file_put_contents('admin/admin_info.php', implode('', $lines));
echo "fixed admin info line by line\n";
