<?php
$content = file_get_contents('profile.php');

$content = str_replace("page_title = 'Profile Settings';", "page_title = 'Family Member Profile';", $content);

$fetch_logic = <<<EOT
\$fm_id = isset(\$_GET['id']) ? (int)\$_GET['id'] : 0;
if (\$fm_id === 0) {
    redirect('family_members.php');
}

// Ensure the family member belongs to the current user
\$stmt = \$pdo->prepare('SELECT * FROM family_members WHERE id = ? AND user_id = ?');
\$stmt->execute([\$fm_id, \$_SESSION['user_id']]);
\$data = \$stmt->fetch();

if (!\$data) {
    redirect('family_members.php');
}
EOT;

$content = preg_replace('/\/\/ Data fetching block\s*try \{.*?\} catch \(Exception \$e\) \{.*?\}/s', $fetch_logic, $content);

$post_logic = <<<EOT
            \$first_name = trim(\$_POST['first_name'] ?? '');
            \$last_name = trim(\$_POST['last_name'] ?? '');
            \$middle_name = trim(\$_POST['middle_name'] ?? '');
            \$suffix = trim(\$_POST['suffix'] ?? '');
            \$relationship = trim(\$_POST['relationship'] ?? '');
            \$birthdate = \$_POST['birthdate'] ?? null;
            \$sex = \$_POST['sex'] ?? null;
            \$citizenship = trim(\$_POST['citizenship'] ?? '');
            \$civil_status = trim(\$_POST['civil_status'] ?? '');
            \$religion = trim(\$_POST['religion'] ?? '');
            \$occupation = trim(\$_POST['occupation'] ?? '');
            \$philsys_card_no = trim(\$_POST['philsys_card_no'] ?? '');
            
            \$edu_base = trim(\$_POST['educational_attainment'] ?? '');
            \$edu_status = trim(\$_POST['edu_status'] ?? '');
            \$educational_attainment = \$edu_base . (\$edu_status ? " (\$edu_status)" : "");
            if (empty(trim(\$educational_attainment))) {
                \$educational_attainment = trim(\$_POST['educational_attainment_text'] ?? '');
            }
            
            \$classifications = \$_POST['classifications'] ?? [];
            \$classification_json = json_encode(\$classifications);

            \$is_solo_parent = in_array('Solo Parent', \$classifications) ? 1 : 0;
            \$is_pwd = in_array('PWD', \$classifications) ? 1 : 0;
            \$is_senior = in_array('Senior Citizen', \$classifications) ? 1 : 0;
            \$is_others = in_array('Indigenous People', \$classifications) ? 1 : 0;

            \$name_parts = array_filter([\$first_name, \$middle_name, \$last_name, \$suffix]);
            \$full_name = implode(' ', \$name_parts);

            \$errors = [];

            // Handle avatar upload
            \$avatarPath = null;
            if (isset(\$_FILES['profile_picture']) && \$_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                \$file = \$_FILES['profile_picture'];
                \$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                \$maxSize = 5 * 1024 * 1024;

                if (!in_array(\$file['type'], \$allowedTypes)) {
                    \$errors[] = 'Only JPG, JPEG, and PNG images are allowed.';
                } elseif (\$file['size'] > \$maxSize) {
                    \$errors[] = 'Profile picture size must not exceed 5MB.';
                } else {
                    \$uploadDir = __DIR__ . '/uploads/profile_pictures/';
                    if (!is_dir(\$uploadDir)) mkdir(\$uploadDir, 0777, true);

                    \$oldAvatarPath = \$data['avatar'] ?? null;
                    \$extension = pathinfo(\$file['name'], PATHINFO_EXTENSION);
                    \$filename = 'fm_' . \$fm_id . '_' . time() . '_' . uniqid() . '.' . \$extension;
                    \$uploadPath = \$uploadDir . \$filename;

                    if (move_uploaded_file(\$file['tmp_name'], \$uploadPath)) {
                        \$avatarPath = 'uploads/profile_pictures/' . \$filename;
                        if (\$oldAvatarPath && file_exists(__DIR__ . '/' . \$oldAvatarPath)) {
                            @unlink(__DIR__ . '/' . \$oldAvatarPath);
                        }
                    } else {
                        \$errors[] = 'Failed to upload profile picture. Check folder permissions.';
                    }
                }
            }
            
            if (\$first_name === '') \$errors[] = 'First name is required';
            if (\$last_name === '') \$errors[] = 'Last name is required';
            if (\$relationship === '') \$errors[] = 'Relationship is required';

            if (\$birthdate !== '' && \$birthdate !== null) {
                try {
                    \$birth_date = new DateTime(\$birthdate);
                    \$today = new DateTime();
                    if (\$birth_date >= \$today) {
                        \$errors[] = 'Birthdate must be in the past';
                    }
                } catch (Exception \$dtE) {
                    \$errors[] = 'Invalid birthdate format.';
                }
            }

            if (empty(\$errors)) {
                \$sql = 'UPDATE family_members SET first_name=?, last_name=?, middle_name=?, suffix=?, full_name=?, relationship=?, birthdate=?, sex=?, citizenship=?, civil_status=?, religion=?, occupation=?, educational_attainment=?, classification=?, is_pwd=?, is_senior=?, is_solo_parent=?, is_others=?, philsys_card_no=?';
                \$params = [\$first_name, \$last_name, \$middle_name ?: null, \$suffix ?: null, \$full_name, \$relationship, \$birthdate, \$sex, \$citizenship, \$civil_status, \$religion ?: null, \$occupation ?: null, \$educational_attainment ?: null, \$classification_json, \$is_pwd, \$is_senior, \$is_solo_parent, \$is_others, \$philsys_card_no];

                if (\$avatarPath !== null) {
                    \$sql .= ', avatar=?';
                    \$params[] = \$avatarPath;
                }
                
                \$sql .= ' WHERE id=? AND user_id=?';
                \$params[] = \$fm_id;
                \$params[] = \$_SESSION['user_id'];
                
                \$pdo->prepare(\$sql)->execute(\$params);
                \$msg = 'Profile saved successfully.';
                
                // Refresh data
                \$stmt = \$pdo->prepare('SELECT * FROM family_members WHERE id = ? AND user_id = ?');
                \$stmt->execute([\$fm_id, \$_SESSION['user_id']]);
                \$data = \$stmt->fetch();
            } else {
                \$msg = implode('. ', \$errors);
            }
EOT;

$content = preg_replace('/\/\/ Get form data.*?(?=^\s*\}\s*\} catch \(Exception \$e\))/sm', $post_logic, $content);

$remove_avatar = <<<EOT
        if (csrf_validate()) {
            if (\$data && !empty(\$data['avatar'])) {
                if (file_exists(__DIR__ . '/' . \$data['avatar'])) {
                    @unlink(__DIR__ . '/' . \$data['avatar']);
                }
                \$pdo->prepare('UPDATE family_members SET avatar = NULL WHERE id = ?')->execute([\$fm_id]);
                \$msg = 'Profile picture removed successfully.';
                \$data['avatar'] = null;
            }
        }
EOT;

$content = preg_replace('/if \(csrf_validate\(\)\) \{\s*\$stmt = \$pdo->prepare\(\'SELECT avatar FROM residents WHERE user_id = \?\'\);.*?(?=\} catch)/s', $remove_avatar, $content);

$back_button = <<<EOT
<div class="mb-4">
    <a href="family_members.php" class="btn btn-light shadow-sm rounded-pill px-4 text-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Household Members
    </a>
</div>
<div class="row justify-content-center animate__animated animate__fadeInUp">
EOT;

$content = str_replace('<div class="row justify-content-center animate__animated animate__fadeInUp">', $back_button, $content);

$content = preg_replace('/<div class="col-12">\s*<label class="form-label[^>]*>Purok.*?<\/label>\s*<input[^>]*name="purok"[^>]*>\s*<\/div>/s', '', $content);
$content = preg_replace('/<div class="col-12">\s*<label class="form-label[^>]*>Complete Address.*?<\/label>\s*<input[^>]*name="address"[^>]*>\s*<\/div>/s', '', $content);
$content = preg_replace('/\/\/ ===== PSGC API Cascading Dropdowns =====.*?\/\/ Save Button Loading State/s', '// Save Button Loading State', $content);

$content = str_replace('<input type="email" name="email"', '<input type="text" name="relationship"', $content);
$content = preg_replace('/value="<\?php echo htmlspecialchars\(\$data\[\'email\'\] \?\? \'\'\); \?>"/i', 'value="<?php echo htmlspecialchars($data[\'relationship\'] ?? \'\'); ?>"', $content);
$content = preg_replace('/Email <span\s*class="text-danger">\*<\/span><\/label>/i', 'Relationship to Head <span class="text-danger">*</span></label>', $content);

$content = str_replace('<input type="text" name="phone"', '<input type="text" name="philsys_card_no"', $content);
$content = preg_replace('/value="<\?php echo htmlspecialchars\(\$data\[\'phone\'\] \?\? \'\'\); \?>"/i', 'value="<?php echo htmlspecialchars($data[\'philsys_card_no\'] ?? \'\'); ?>"', $content);
$content = preg_replace('/Phone <span\s*class="text-danger">\*<\/span><\/label>/i', 'PhilSys Card Number</label>', $content);

$content = preg_replace('/<div class="col-md-6">\s*<label class="form-label[^>]*>Barangay ID<\/label>.*?<\/div>\s*<\/div>\s*<\/div>/s', '</div></div>', $content);

$content = preg_replace('/<\?php echo htmlspecialchars\(\$data\[\'email\'\] \?\? \'\'\); \?>/i', '<?php echo htmlspecialchars($data[\'relationship\'] ?? \'\'); ?>', $content);

file_put_contents('family_member_profile.php', $content);
echo "done\n";
