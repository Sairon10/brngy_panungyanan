<?php
$page_title = 'Family Member Profile';
require_once __DIR__ . '/partials/user_dashboard_header.php';
?>
<?php if (!is_logged_in())
    redirect('login.php'); ?>
<?php
$pdo = get_db_connection();
$msg = '';
$family_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_validate()) {
            $msg = 'Invalid session. Please reload and try again.';
        } else {
                        $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $middle_name = trim($_POST['middle_name'] ?? '');
            $suffix = trim($_POST['suffix'] ?? '');
            $relationship = trim($_POST['relationship'] ?? '');
            $birthdate = $_POST['birthdate'] ?? null;
            $sex = $_POST['sex'] ?? null;
            $citizenship = trim($_POST['citizenship'] ?? '');
            $civil_status = trim($_POST['civil_status'] ?? '');
            $religion = trim($_POST['religion'] ?? '');
            $occupation = trim($_POST['occupation'] ?? '');
            $philsys_card_no = trim($_POST['philsys_card_no'] ?? '');
            $birth_place = trim($_POST['birth_place'] ?? '');
            
            $edu_base = trim($_POST['educational_attainment'] ?? '');
            $edu_status = trim($_POST['edu_status'] ?? '');
            $educational_attainment = $edu_base . ($edu_status ? " ($edu_status)" : "");
            if (empty(trim($educational_attainment))) {
                $educational_attainment = trim($_POST['educational_attainment_text'] ?? '');
            }
            
            $classifications = $_POST['classifications'] ?? [];
            $classification_json = json_encode($classifications);

            $is_solo_parent = in_array('Solo Parent', $classifications) ? 1 : 0;
            $is_pwd = in_array('PWD', $classifications) ? 1 : 0;
            $is_senior = in_array('Senior Citizen', $classifications) ? 1 : 0;
            $is_others = in_array('Indigenous People', $classifications) ? 1 : 0;

            $fm_migrant_previous_residence = trim($_POST['fm_migrant_previous_residence'] ?? '');
            $fm_migrant_length_of_stay = trim($_POST['fm_migrant_length_of_stay'] ?? '');
            
            $fm_migrant_reason_leaving_raw = trim($_POST['fm_migrant_reason_leaving'] ?? '');
            $fm_migrant_reason_leaving_other = trim($_POST['fm_migrant_reason_leaving_other'] ?? '');
            $fm_migrant_reason_leaving = ($fm_migrant_reason_leaving_raw === 'Others' && $fm_migrant_reason_leaving_other !== '') ? $fm_migrant_reason_leaving_other : $fm_migrant_reason_leaving_raw;
            
            $fm_migrant_date_transfer = trim($_POST['fm_migrant_date_transfer'] ?? '') ?: null;
            
            $fm_migrant_reason_for_raw = trim($_POST['fm_migrant_reason_for'] ?? '');
            $fm_migrant_reason_for_other = trim($_POST['fm_migrant_reason_for_other'] ?? '');
            $fm_migrant_reason_for = ($fm_migrant_reason_for_raw === 'Others' && $fm_migrant_reason_for_other !== '') ? $fm_migrant_reason_for_other : $fm_migrant_reason_for_raw;
            
            $fm_migrant_duration = trim($_POST['fm_migrant_duration'] ?? '');
            
            $fm_migrant_intention_raw = trim($_POST['fm_migrant_intention'] ?? '');
            $fm_migrant_intention_other = trim($_POST['fm_migrant_intention_other'] ?? '');
            $fm_migrant_intention = ($fm_migrant_intention_raw === 'Others' && $fm_migrant_intention_other !== '') ? $fm_migrant_intention_other : $fm_migrant_intention_raw;

            $name_parts = array_filter([$first_name, $middle_name, $last_name, $suffix]);
            $full_name = implode(' ', $name_parts);

            $errors = [];

            // Handle avatar upload
            $avatarPath = null;
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_picture'];
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                $maxSize = 5 * 1024 * 1024;

                if (!in_array($file['type'], $allowedTypes)) {
                    $errors[] = 'Only JPG, JPEG, and PNG images are allowed.';
                } elseif ($file['size'] > $maxSize) {
                    $errors[] = 'Profile picture size must not exceed 5MB.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/profile_pictures/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                    $oldAvatarPath = $data['avatar'] ?? null;
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'fm_' . $fm_id . '_' . time() . '_' . uniqid() . '.' . $extension;
                    $uploadPath = $uploadDir . $filename;

                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        $avatarPath = 'uploads/profile_pictures/' . $filename;
                        if ($oldAvatarPath && file_exists(__DIR__ . '/' . $oldAvatarPath)) {
                            @unlink(__DIR__ . '/' . $oldAvatarPath);
                        }
                    } else {
                        $errors[] = 'Failed to upload profile picture. Check folder permissions.';
                    }
                }
            }
            
            if ($first_name === '') $errors[] = 'First name is required';
            if ($last_name === '') $errors[] = 'Last name is required';
            if ($relationship === '') $errors[] = 'Relationship is required';

            if ($birthdate !== '' && $birthdate !== null) {
                try {
                    $birth_date = new DateTime($birthdate);
                    $today = new DateTime();
                    if ($birth_date >= $today) {
                        $errors[] = 'Birthdate must be in the past';
                    }
                } catch (Exception $dtE) {
                    $errors[] = 'Invalid birthdate format.';
                }
            }

            if (empty($errors)) {
                $sql = 'UPDATE family_members SET first_name=?, last_name=?, middle_name=?, suffix=?, full_name=?, relationship=?, birthdate=?, sex=?, citizenship=?, civil_status=?, religion=?, occupation=?, educational_attainment=?, classification=?, is_pwd=?, is_senior=?, is_solo_parent=?, is_others=?, philsys_card_no=?, birth_place=?, migrant_previous_residence=?, migrant_length_of_stay=?, migrant_reason_leaving=?, migrant_date_transfer=?, migrant_reason_for=?, migrant_duration=?, migrant_intention=?';
                $params = [$first_name, $last_name, $middle_name ?: null, $suffix ?: null, $full_name, $relationship, $birthdate, $sex, $citizenship, $civil_status, $religion ?: null, $occupation ?: null, $educational_attainment ?: null, $classification_json, $is_pwd, $is_senior, $is_solo_parent, $is_others, $philsys_card_no, $birth_place ?: null, $fm_migrant_previous_residence, $fm_migrant_length_of_stay, $fm_migrant_reason_leaving, $fm_migrant_date_transfer, $fm_migrant_reason_for, $fm_migrant_duration, $fm_migrant_intention];

                if ($avatarPath !== null) {
                    $sql .= ', avatar=?';
                    $params[] = $avatarPath;
                }
                
                $sql .= ' WHERE id=? AND user_id=?';
                $params[] = $fm_id;
                $params[] = $_SESSION['user_id'];
                
                $pdo->prepare($sql)->execute($params);
                $msg = 'Profile saved successfully.';
                
                // Refresh data
                $stmt = $pdo->prepare('SELECT * FROM family_members WHERE id = ? AND user_id = ?');
                $stmt->execute([$fm_id, $_SESSION['user_id']]);
                $data = $stmt->fetch();
            } else {
                $msg = implode('. ', $errors);
            }        }
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/profile_error_log.txt', date('Y-m-d H:i:s') . ' - POST ERROR: ' . $e->getMessage() . "\n", FILE_APPEND);
        $msg = 'A server error occurred while saving: ' . $e->getMessage();
    }
}

// Handle avatar removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_avatar') {
    try {
                if (csrf_validate()) {
            if ($data && !empty($data['avatar'])) {
                if (file_exists(__DIR__ . '/' . $data['avatar'])) {
                    @unlink(__DIR__ . '/' . $data['avatar']);
                }
                $pdo->prepare('UPDATE family_members SET avatar = NULL WHERE id = ?')->execute([$fm_id]);
                $msg = 'Profile picture removed successfully.';
                $data['avatar'] = null;
            }
        }} catch (Exception $e) {
        $msg = 'Error removing picture: ' . $e->getMessage();
    }
}

$fm_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($fm_id === 0) {
    redirect('family_members.php');
}

// Ensure the family member belongs to the current user
$stmt = $pdo->prepare('SELECT * FROM family_members WHERE id = ? AND user_id = ?');
$stmt->execute([$fm_id, $_SESSION['user_id']]);
$data = $stmt->fetch();

if (!$data) {
    redirect('family_members.php');
}

$head_stmt = $pdo->prepare('SELECT purok, address FROM residents WHERE user_id = ?');
$head_stmt->execute([$_SESSION['user_id']]);
$head_data = $head_stmt->fetch();
$head_purok = $head_data['purok'] ?? '';
$head_address = $head_data['address'] ?? '';

$barangay_id_display = date('Y', strtotime($data['created_at'] ?? 'now')) . '-F' . str_pad((string)$fm_id, 4, '0', STR_PAD_LEFT);
?>

<div class="mb-4">
    <a href="family_members.php" class="btn btn-light shadow-sm rounded-pill px-4 text-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Household Members
    </a>
</div>
<div class="row justify-content-center animate__animated animate__fadeInUp">
    <div class="col-lg-10">
        <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
            <!-- Profile Header/Cover -->
            <div class="bg-gradient-primary p-5 text-center position-relative"
                style="background: linear-gradient(135deg, var(--primary), var(--secondary));">
                <div class="position-absolute top-0 start-0 w-100 h-100 bg-pattern opacity-10"></div>

                <?php if (!empty($data['avatar'])): ?>
                    <div class="position-relative d-inline-block mb-3">
                        <img src="<?php echo htmlspecialchars($data['avatar']); ?>" alt="Profile Picture"
                            class="rounded-circle shadow-lg"
                            style="width: 120px; height: 120px; object-fit: cover; border: 4px solid white; cursor: pointer;"
                            onclick="previewProfileImage(this.src)">
                    </div>
                <?php else: ?>
                    <div class="avatar-circle bg-white text-primary fw-bold mx-auto mb-3 shadow-lg fs-2 d-flex align-items-center justify-content-center"
                        style="width: 120px; height: 120px; border-radius: 50%;">
                        <?php echo strtoupper(substr($data['full_name'] ?? 'U', 0, 1)); ?>
                    </div>
                <?php endif; ?>

                <h3 class="text-white fw-bold mb-1 position-relative z-1">
                    <?php echo htmlspecialchars($data['full_name'] ?? 'User'); ?></h3>
                <p class="text-white-50 mb-2 position-relative z-1">
                    <?php echo htmlspecialchars($data['relationship'] ?? ''); ?></p>
                <div class="position-relative z-1">
                    <?php
                    $status = $data['verification_status'] ?? 'unverified';
                    $badgeClass = 'bg-warning text-dark';
                    $icon = 'fa-exclamation-circle';
                    $label = 'Needs Verification';

                    if ($status === 'verified') {
                        $badgeClass = 'bg-success text-white';
                        $icon = 'fa-check-circle';
                        $label = 'Verified Account';
                    } elseif ($status === 'pending') {
                        $badgeClass = 'bg-info text-white';
                        $icon = 'fa-clock';
                        $label = 'Verification Pending';
                    }
                    ?>
                    <a href="id_verification.php" class="text-decoration-none">
                        <span class="badge <?php echo $badgeClass; ?> rounded-pill px-3 py-2 shadow-sm">
                            <i class="fas <?php echo $icon; ?> me-1"></i> <?php echo $label; ?>
                        </span>
                    </a>
                </div>
            </div>

            <div class="card-body p-5">
                <?php if ($msg || $family_msg): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const msg = <?php echo json_encode($msg ?: $family_msg); ?>;
                        const isSuccess = msg.toLowerCase().includes('successfully') || msg.toLowerCase().includes('removed');
                        Swal.fire({
                            icon: isSuccess ? 'success' : 'error',
                            title: isSuccess ? 'Success!' : 'Notice',
                            text: msg,
                            confirmButtonColor: '#0f766e'
                        });
                    });
                </script>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" id="profileForm">
                    <?php echo csrf_field(); ?>

                    <h6 class="text-dark opacity-50 fw-bold small  mb-4 pb-2 border-bottom">Profile Picture</h6>

                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <label class="form-label fw-semibold text-secondary small ">Upload Profile
                                Picture</label>
                            <input type="file" name="profile_picture" class="form-control"
                                accept="image/jpeg,image/jpg,image/png" id="profilePictureInput">
                            <div class="form-text small text-muted">Accepted formats: JPG, JPEG, PNG. Maximum size: 5MB
                            </div>
                            <?php if (!empty($data['avatar'])): ?>
                                <div class="mt-2 d-flex align-items-center gap-2">
                                    <small class="text-muted">Current profile picture:</small>
                                    <a href="<?php echo htmlspecialchars($data['avatar']); ?>" 
                                       target="_blank" class="btn btn-sm btn-outline-info py-0 px-2">View</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" 
                                            onclick="confirmRemoveAvatar()">Delete</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h6 class="text-dark opacity-50 fw-bold small  mb-4 pb-2 border-bottom mt-5">Personal
                        Information</h6>

                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark opacity-50 small ">First Name <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control"
                                value="<?php echo htmlspecialchars($data['first_name'] ?? ''); ?>"
                                placeholder="e.g. Juan" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark opacity-50 small ">Last Name <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control"
                                value="<?php echo htmlspecialchars($data['last_name'] ?? ''); ?>"
                                placeholder="e.g. Dela Cruz" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark opacity-50 small ">Middle
                                Name</label>
                            <input type="text" name="middle_name" class="form-control"
                                value="<?php echo htmlspecialchars($data['middle_name'] ?? ''); ?>"
                                placeholder="e.g. Santos">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark opacity-50 small ">Suffix</label>
                            <input type="text" name="suffix" class="form-control"
                                value="<?php echo htmlspecialchars($data['suffix'] ?? ''); ?>"
                                placeholder="e.g. Jr., Sr., III">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark opacity-50 small  mb-1">Barangay ID</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="fas fa-id-badge"></i></span>
                                <input type="text" name="barangay_id" class="form-control bg-light fw-bold"
                                    value="<?php echo htmlspecialchars($barangay_id_display); ?>" readonly>
                                <span class="input-group-text bg-light text-secondary" title="Auto-generated"><i class="fas fa-lock"></i></span>
                            </div>
                            <div class="form-text small text-muted"><i class="fas fa-info-circle me-1"></i>Auto-generated</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark opacity-50 small ">Relationship to Head <span class="text-danger">*</span></label>
                            <input type="text" name="relationship" class="form-control"
                                value="<?php echo htmlspecialchars($data['relationship'] ?? ''); ?>"
                                placeholder="name@example.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark opacity-50 small ">PhilSys Card Number</label>
                            <input type="text" name="philsys_card_no" class="form-control"
                                value="<?php echo htmlspecialchars($data['philsys_card_no'] ?? ''); ?>"
                                placeholder="e.g. 09123456789" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark opacity-50 small ">Birthdate <span
                                    class="text-danger">*</span></label>
                            <input type="date" name="birthdate" id="birthdate" class="form-control"
                                min="<?php echo date('Y-m-d', strtotime('-120 years')); ?>" 
                                max="<?php echo date('Y-m-d', strtotime('-4 years')); ?>" 
                                value="<?php echo htmlspecialchars($data['birthdate'] ?? ''); ?>" required>
                            <div class="form-text small text-muted">Must be between 4 and 120 years old</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark opacity-50 small ">Birth Place</label>
                            <input type="text" name="birth_place" class="form-control"
                                value="<?php echo htmlspecialchars(ucwords(strtolower($data['birth_place'] ?? ''))); ?>"
                                placeholder="e.g. Manila">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark opacity-50 small ">Sex <span
                                    class="text-danger">*</span></label>
                            <select name="sex" class="form-select" required>
                                <option value="">Select Sex</option>
                                <?php foreach (['Male', 'Female'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo (isset($data['sex']) && $data['sex'] === $s) ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark opacity-50 small ">Citizenship <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="citizenship" class="form-control"
                                value="<?php echo htmlspecialchars(ucwords(strtolower($data['citizenship'] ?? ''))); ?>"
                                placeholder="e.g. Filipino" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark opacity-50 small ">Civil Status <span
                                    class="text-danger">*</span></label>
                            <select name="civil_status" class="form-select" required>
                                <option value="">Select Civil Status</option>
                                <?php foreach (['Single', 'Married', 'Widowed', 'Divorced', 'Separated'] as $cs): ?>
                                    <option value="<?php echo $cs; ?>" <?php echo (isset($data['civil_status']) && $data['civil_status'] === $cs) ? 'selected' : ''; ?>><?php echo $cs; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark opacity-50 small ">Purok</label>
                            <input type="text" name="purok" class="form-control bg-light"
                                value="<?php echo htmlspecialchars($head_purok); ?>"
                                placeholder="e.g. Purok 1, Purok 2" readonly title="Auto-filled from Household Head">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark opacity-50 small ">Complete Address</label>
                            <input type="text" name="address" class="form-control bg-light"
                                value="<?php echo htmlspecialchars($head_address); ?>"
                                placeholder="e.g. 123 Main St., Panungyanan, General Trias, Cavite" readonly title="Auto-filled from Household Head">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark opacity-50 small ">Religion</label>
                            <input type="text" name="religion" class="form-control"
                                value="<?php echo htmlspecialchars(ucwords(strtolower($data['religion'] ?? ''))); ?>"
                                placeholder="e.g. Roman Catholic">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark opacity-50 small ">Profession / Occupation</label>
                            <input type="text" name="occupation" class="form-control"
                                value="<?php echo htmlspecialchars(ucwords(strtolower($data['occupation'] ?? ''))); ?>"
                                placeholder="e.g. Teacher, Farmer">
                        </div>
                        <div class="col-12">
                            <div class="row g-3">
                                <div class="col-md-6 border-end pe-4">
                                    <label class="form-label fw-semibold text-dark opacity-50 small  d-block mb-3">HIGHEST EDUCATIONAL ATTAINMENT</label>
                                    <div class="d-flex flex-column gap-2">
                                        <?php
                                        $current_edu = $data['educational_attainment'] ?? '';
                                        $edu_opts = ['Elementary', 'High School', 'College', 'Post Grad', 'Vocational'];
                                        foreach ($edu_opts as $opt):
                                            $is_checked = stripos($current_edu, $opt) !== false;
                                            ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="educational_attainment"
                                                    value="<?php echo $opt; ?>" id="edu_<?php echo str_replace(' ','_',$opt); ?>" 
                                                    <?php echo $is_checked ? 'checked' : ''; ?>>
                                                <label class="form-check-label"
                                                    for="edu_<?php echo str_replace(' ','_',$opt); ?>"><?php echo $opt; ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-6 ps-4">
                                    <label class="form-label fw-semibold text-dark opacity-50 small  d-block mb-3">PLEASE SPECIFY</label>
                                    <div class="d-flex flex-column gap-2">
                                        <?php
                                        $is_under = stripos($current_edu, 'under graduate') !== false || stripos($current_edu, 'undergraduate') !== false;
                                        $is_grad = stripos($current_edu, 'graduate') !== false && !$is_under;
                                        ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="edu_status"
                                                value="Graduate" id="gradY" <?php echo $is_grad ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="gradY">Graduate</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="edu_status"
                                                value="Under Graduate" id="gradN" <?php echo $is_under ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="gradN">Under Graduate</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark opacity-50 small ">Special Classification (Optional - Multiple Selection)</label>
                            <?php
                            $saved_classes = [];
                            if (!empty($data['classification'])) {
                                $decoded = json_decode($data['classification'], true);
                                if (is_array($decoded)) {
                                    $saved_classes = $decoded;
                                } elseif (is_string($decoded)) {
                                    // Sometimes it might be double encoded like "[\"Unemployed\"]"
                                    $re_decoded = json_decode($decoded, true);
                                    if (is_array($re_decoded)) $saved_classes = $re_decoded;
                                } else {
                                    $saved_classes = array_map('trim', explode(',', $data['classification']));
                                }
                            }
                            $all_classes = [
                                'Labor/Employed', 'Unemployed', 'PWD', 'OFW', 'Solo Parent',
                                'Out of School Youth (OSY)', 'Out of School Children (OSC)', 'Indigenous People', 'Senior Citizen', 'Migrant / Transferee'
                            ];
                            ?>
                            <div class="row g-2">
                                <?php foreach ($all_classes as $idx => $cls): ?>
                                <div class="col-md-4">
                                    <div class="form-check p-2 border rounded-3 h-100 d-flex align-items-center">
                                        <input class="form-check-input ms-0 me-2" type="checkbox" name="classifications[]"
                                            id="cls_<?php echo $idx; ?>" value="<?php echo htmlspecialchars($cls); ?>"
                                            <?php echo in_array($cls, $saved_classes) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold small" for="cls_<?php echo $idx; ?>"><?php echo $cls; ?></label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        </div>

                        <div id="fm_migrant_info_wrap" style="display: block;">
                            <h6 class="text-dark opacity-50 fw-bold small  mb-4 pb-2 border-bottom mt-5">Migrant Information</h6>

                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark opacity-50 small ">Previous residence</label>
                                <input type="text" name="fm_migrant_previous_residence" class="form-control"
                                    value="<?php echo htmlspecialchars(ucfirst($data['migrant_previous_residence'] ?? '')); ?>"
                                    placeholder="e.g. Manila">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark opacity-50 small ">Length of stay</label>
                                <select name="fm_migrant_length_of_stay" class="form-select">
                                    <option value="">-- SELECT LENGTH OF STAY --</option>
                                    <?php 
                                    $options = ['1 Month','2 Months','3 Months','4 Months','5 Months','6 Months','7 Months','8 Months','9 Months','10 Months','11 Months',
                                                '1 Year','2 Years','3 Years','4 Years','5 Years','6 Years','7 Years','8 Years','9 Years','10 Years','More than 10 Years'];
                                    foreach ($options as $opt) {
                                        $selected = ($data['migrant_length_of_stay'] ?? '') === $opt ? 'selected' : '';
                                        echo "<option value=\"$opt\" $selected>$opt</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark opacity-50 small ">Reason for leaving</label>
                                <?php 
                                    $stdReasons = ['Employment / Work', 'Studies / Education', 'Family Relocation', 'End of Lease'];
                                    $dbReason = $data['migrant_reason_leaving'] ?? '';
                                    $isOther = $dbReason && !in_array($dbReason, $stdReasons);
                                ?>
                                <select name="fm_migrant_reason_leaving" id="fm_migrant_reason_leaving" class="form-select" onchange="toggleMigrantReasonOthers()">
                                    <option value="">-- SELECT REASON --</option>
                                    <?php foreach ($stdReasons as $opt) {
                                        $selected = ($dbReason === $opt) ? 'selected' : '';
                                        echo "<option value=\"$opt\" $selected>$opt</option>";
                                    } ?>
                                    <option value="Others" <?php echo $isOther ? 'selected' : ''; ?>>Others</option>
                                </select>
                                <div id="fm_migrant_reason_leaving_other_wrap" style="display:<?php echo $isOther ? 'block' : 'none'; ?>; margin-top:6px;">
                                    <input type="text" name="fm_migrant_reason_leaving_other" id="fm_migrant_reason_leaving_other" class="form-control" placeholder="Please specify reason..." value="<?php echo $isOther ? htmlspecialchars(ucfirst($dbReason)) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark opacity-50 small ">Date of transfer</label>
                                <input type="date" name="fm_migrant_date_transfer" class="form-control"
                                    value="<?php echo htmlspecialchars($data['migrant_date_transfer'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark opacity-50 small ">Reason for transferring</label>
                                <?php 
                                    $dbReasonFor = $data['migrant_reason_for'] ?? '';
                                    $isOtherFor = $dbReasonFor && !in_array($dbReasonFor, $stdReasons);
                                ?>
                                <select name="fm_migrant_reason_for" id="fm_migrant_reason_for" class="form-select" onchange="toggleMigrantReasonForOthers()">
                                    <option value="">-- SELECT REASON --</option>
                                    <?php foreach ($stdReasons as $opt) {
                                        $selected = ($dbReasonFor === $opt) ? 'selected' : '';
                                        echo "<option value=\"$opt\" $selected>$opt</option>";
                                    } ?>
                                    <option value="Others" <?php echo $isOtherFor ? 'selected' : ''; ?>>Others</option>
                                </select>
                                <div id="fm_migrant_reason_for_other_wrap" style="display:<?php echo $isOtherFor ? 'block' : 'none'; ?>; margin-top:6px;">
                                    <input type="text" name="fm_migrant_reason_for_other" id="fm_migrant_reason_for_other" class="form-control" placeholder="Please specify reason..." value="<?php echo $isOtherFor ? htmlspecialchars(ucfirst($dbReasonFor)) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark opacity-50 small ">Duration of stay</label>
                                <select name="fm_migrant_duration" class="form-select">
                                    <option value="">-- SELECT DURATION --</option>
                                    <?php 
                                    $optionsDur = ['1 Month','2 Months','3 Months','4 Months','5 Months','6 Months','7 Months','8 Months','9 Months','10 Months','11 Months',
                                                '1 Year','2 Years','3 Years','4 Years','5 Years','6 Years','7 Years','8 Years','9 Years','10 Years','More than 10 Years', 'Permanent', 'Undecided'];
                                    foreach ($optionsDur as $opt) {
                                        $selected = ($data['migrant_duration'] ?? '') === $opt ? 'selected' : '';
                                        echo "<option value=\"$opt\" $selected>$opt</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark opacity-50 small ">Intention to stay</label>
                                <?php 
                                    $stdIntentions = ['Settle Permanently', 'Temporary Stay', 'Undecided'];
                                    $dbIntention = $data['migrant_intention'] ?? '';
                                    $isOtherInt = $dbIntention && !in_array($dbIntention, $stdIntentions);
                                ?>
                                <select name="fm_migrant_intention" id="fm_migrant_intention" class="form-select" onchange="toggleMigrantIntentionOthers()">
                                    <option value="">-- SELECT INTENTION --</option>
                                    <?php foreach ($stdIntentions as $opt) {
                                        $selected = ($dbIntention === $opt) ? 'selected' : '';
                                        echo "<option value=\"$opt\" $selected>$opt</option>";
                                    } ?>
                                    <option value="Others" <?php echo $isOtherInt ? 'selected' : ''; ?>>Others</option>
                                </select>
                                <div id="fm_migrant_intention_other_wrap" style="display:<?php echo $isOtherInt ? 'block' : 'none'; ?>; margin-top:6px;">
                                    <input type="text" name="fm_migrant_intention_other" id="fm_migrant_intention_other" class="form-control" placeholder="Please specify intention..." value="<?php echo $isOtherInt ? htmlspecialchars(ucfirst($dbIntention)) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        </div>

                    </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button class="btn btn-primary btn-lg rounded-pill px-5" type="submit" id="saveBtn">
                    <i class="fas fa-save me-2"></i> Save Changes
                </button>
            </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Set date input limits for age 18-59
        const birthdateInput = document.getElementById('birthdate');
        if (birthdateInput) {
            const today = new Date();
            const maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
            birthdateInput.setAttribute('max', maxDate.toISOString().split('T')[0]);
            // Removed min date limit for seniors

            // Add validation on change
            birthdateInput.addEventListener('change', function () {
                const date = new Date(this.value);
                const today = new Date();
                const age = today.getFullYear() - date.getFullYear();
                const monthDiff = today.getMonth() - date.getMonth();
                const dayDiff = today.getDate() - date.getDate();

                // Calculate exact age
                let exactAge = age;
                if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
                    exactAge--;
                }

                if (date >= today) {
                    this.setCustomValidity('Birthdate must be in the past');
                    this.classList.add('is-invalid');
                } else if (exactAge < 4 || exactAge > 120) {
                    this.setCustomValidity('Family member must be between 4 and 120 years old');
                    this.classList.add('is-invalid');
                } else {
                    this.setCustomValidity('');
                    this.classList.remove('is-invalid');
                }
            });

            // Remove invalid class on input
            birthdateInput.addEventListener('input', function () {
                this.classList.remove('is-invalid');
            });
        }

        // Save Button Loading State
        const profileForm = document.getElementById('profileForm');
        const saveBtn = document.getElementById('saveBtn');
        if (profileForm && saveBtn) {
            profileForm.addEventListener('submit', function() {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
            });
        }


    });

    function toggleMigrantReasonOthers() {
        const sel = document.getElementById('fm_migrant_reason_leaving');
        const wrap = document.getElementById('fm_migrant_reason_leaving_other_wrap');
        if (!sel || !wrap) return;
        wrap.style.display = sel.value === 'Others' ? 'block' : 'none';
    }
    function toggleMigrantReasonForOthers() {
        const sel = document.getElementById('fm_migrant_reason_for');
        const wrap = document.getElementById('fm_migrant_reason_for_other_wrap');
        if (!sel || !wrap) return;
        wrap.style.display = sel.value === 'Others' ? 'block' : 'none';
    }
    function toggleMigrantIntentionOthers() {
        const sel = document.getElementById('fm_migrant_intention');
        const wrap = document.getElementById('fm_migrant_intention_other_wrap');
        if (!sel || !wrap) return;
        wrap.style.display = sel.value === 'Others' ? 'block' : 'none';
    }

    function confirmRemoveAvatar() {
        Swal.fire({
            title: 'Remove photo?',
            text: "Are you sure you want to remove your profile picture?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, remove it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create a temporary form to submit the removal
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="remove_avatar">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    function previewProfileImage(src) {
        Swal.fire({
            html: `<img src="${src}" class="rounded-circle shadow" style="width: 300px; height: 300px; object-fit: cover; border: 5px solid white;">`,
            showConfirmButton: false,
            showCloseButton: true,
            background: 'transparent',
            customClass: {
                popup: 'border-0 shadow-none'
            }
        });
    }
</script>

<?php require_once __DIR__ . '/partials/user_dashboard_footer.php'; ?>
