<?php
$lines = file('admin/admin_info.php');
$output = [];
for ($i = 0; $i < count($lines); $i++) {
    $line = $lines[$i];
    
    if (strpos($line, '$birthdate = $_POST[\'birthdate\'];') !== false) {
        $output[] = $line;
        $output[] = "        \$birth_place = trim(\$_POST['birth_place'] ?? '');\n";
    }
    elseif (strpos($line, 'UPDATE residents SET address = ?, phone = ?, sex = ?, civil_status = ?, birthdate = ?, citizenship = ?, purok = ?, avatar = ? WHERE user_id = ?') !== false) {
        $output[] = str_replace('birthdate = ?, citizenship = ?', 'birthdate = ?, birth_place = ?, citizenship = ?', $line);
    }
    elseif (strpos($line, 'execute([$address, $phone, $sex, $civil_status, $birthdate, $citizenship, $purok, $profile_picture, $admin_id]);') !== false) {
        $output[] = str_replace('$birthdate, $citizenship', '$birthdate, $birth_place, $citizenship', $line);
    }
    elseif (strpos($line, 'INSERT INTO residents (user_id, address, phone, sex, civil_status, birthdate, citizenship, purok, avatar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)') !== false) {
        $output[] = str_replace('birthdate, citizenship', 'birthdate, birth_place, citizenship', str_replace('?, ?, ?)', '?, ?, ?, ?)', $line));
    }
    elseif (strpos($line, 'execute([$admin_id, $address, $phone, $sex, $civil_status, $birthdate, $citizenship, $purok, $profile_picture]);') !== false) {
        $output[] = str_replace('$birthdate, $citizenship', '$birthdate, $birth_place, $citizenship', $line);
    }
    elseif (strpos($line, 'SELECT u.*, r.address, r.phone, r.birthdate, r.sex, r.civil_status, r.citizenship, r.purok') !== false) {
        $output[] = str_replace('r.birthdate, r.sex', 'r.birthdate, r.birth_place, r.sex', $line);
    }
    elseif (strpos($line, '<div class="col-md-4">') !== false) {
        // Look ahead 2 lines to see what this block is
        $is_calendar = isset($lines[$i+2]) && strpos($lines[$i+2], 'fa-calendar') !== false;
        $is_sex = isset($lines[$i+2]) && strpos($lines[$i+2], 'fa-venus-mars') !== false;
        $is_civil = isset($lines[$i+2]) && strpos($lines[$i+2], 'fa-heart') !== false;
        
        if ($is_calendar) {
            $output[] = '                                <div class="col-md-6">' . "\n";
            $output[] = '                                    <div class="info-card">' . "\n";
            $output[] = '                                        <div class="section-label"><i class="fas fa-map-marker-alt"></i> Place of Birth</div>' . "\n";
            $output[] = '                                        <?php if ($is_editing): ?><input type="text" name="birth_place" class="form-control" value="<?php echo htmlspecialchars($admin[\'birth_place\'] ?? \'\'); ?>" placeholder="e.g. Manila" required>' . "\n";
            $output[] = '                                        <?php else: ?><div class="info-value"><?php echo htmlspecialchars($admin[\'birth_place\'] ?: \'N/A\'); ?></div><?php endif; ?>' . "\n";
            $output[] = '                                    </div>' . "\n";
            $output[] = '                                </div>' . "\n";
            $output[] = '                                <div class="col-md-6">' . "\n";
        } elseif ($is_sex) {
            $output[] = '                                <div class="col-md-6">' . "\n";
        } elseif ($is_civil) {
            $output[] = '                                <div class="col-md-6">' . "\n";
        } else {
            $output[] = $line;
        }
    } else {
        $output[] = $line;
    }
}
file_put_contents('admin/admin_info.php', implode('', $output));
echo "fixed admin info perfectly\n";
