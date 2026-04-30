<?php
$page_title = 'Profile Settings';
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
            // Get form data
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $middle_name = trim($_POST['middle_name'] ?? '');
            $suffix = trim($_POST['suffix'] ?? '');
            $email = trim($_POST['email'] ?? '');
            // Address components
            $province = trim($_POST['province'] ?? '');
            $municipality = trim($_POST['municipality'] ?? '');
            $barangay = trim($_POST['barangay'] ?? '');
            
            $address_direct = trim($_POST['address'] ?? '');
            if ($address_direct !== '') {
                $address = $address_direct;
            } else {
                $address = implode(', ', array_filter([$barangay, $municipality, $province]));
            }
            $phone = trim($_POST['phone'] ?? '');
            $birthdate = $_POST['birthdate'] ?? null;
            $sex = $_POST['sex'] ?? null;
            $citizenship = trim($_POST['citizenship'] ?? '');
            $civil_status = trim($_POST['civil_status'] ?? '');
            
            $barangay_id = date('Y') . '-' . str_pad((string) $_SESSION['user_id'], 4, '0', STR_PAD_LEFT);
            $purok = trim($_POST['purok'] ?? '');
            $is_solo_parent = isset($_POST['is_solo_parent']) ? 1 : 0;
            $is_pwd = isset($_POST['is_pwd']) ? 1 : 0;
            $is_senior = isset($_POST['is_senior']) ? 1 : 0;
            $religion = trim($_POST['religion'] ?? '');
            $occupation = trim($_POST['occupation'] ?? '');
            
            $edu_base = trim($_POST['educational_attainment'] ?? '');
            $edu_status = trim($_POST['edu_status'] ?? '');
            $educational_attainment = $edu_base . ($edu_status ? " ($edu_status)" : "");
            if (empty(trim($educational_attainment))) {
                $educational_attainment = trim($_POST['educational_attainment_text'] ?? '');
            }
            $classifications = $_POST['classifications'] ?? [];
            $classification_json = json_encode($classifications);

            $name_parts = array_filter([$first_name, $middle_name, $last_name, $suffix]);
            $full_name = implode(' ', $name_parts);

            $errors = [];

            // Handle profile picture upload
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
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $stmt = $pdo->prepare('SELECT avatar FROM residents WHERE user_id = ?');
                    $stmt->execute([$_SESSION['user_id']]);
                    $existingResident = $stmt->fetch();
                    $oldAvatarPath = $existingResident['avatar'] ?? null;

                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '_' . uniqid() . '.' . $extension;
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
            if ($email === '') {
                $errors[] = 'Email is required';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Valid email is required';
            }
            if ($phone === '') $errors[] = 'Phone is required';

            if ($birthdate !== '' && $birthdate !== null) {
                try {
                    $birth_date = new DateTime($birthdate);
                    $today = new DateTime();
                    if ($today->diff($birth_date)->y < 18) {
                        $errors[] = 'You must be at least 18 years old';
                    }
                } catch (Exception $dtE) {
                    $errors[] = 'Invalid birthdate format.';
                }
            }

            if (empty($errors)) {
                // Get current data for fallback
                $stmt = $pdo->prepare('SELECT * FROM residents WHERE user_id = ?');
                $stmt->execute([$_SESSION['user_id']]);
                $current = $stmt->fetch();

                // Fallback logic in PHP to avoid SQL collation issues (NULLIF/COALESCE)
                $final_religion = !empty($religion) ? $religion : ($current['religion'] ?? null);
                $final_occupation = !empty($occupation) ? $occupation : ($current['occupation'] ?? null);
                $final_edu = !empty($educational_attainment) ? $educational_attainment : ($current['educational_attainment'] ?? null);
                
                // If classifications is empty, keep old ones
                $final_classification = ($classification_json !== '[]') ? $classification_json : ($current['classification'] ?? '[]');

                // Update users table
                $stmt = $pdo->prepare('UPDATE users SET first_name=?, last_name=?, middle_name=?, suffix=?, full_name=?, email=? WHERE id=?');
                $stmt->execute([$first_name, $last_name, $middle_name ?: null, $suffix ?: null, $full_name, $email, $_SESSION['user_id']]);

                if ($current) {
                    if ($avatarPath !== null) {
                        $pdo->prepare('UPDATE residents SET address=?, phone=?, birthdate=?, sex=?, citizenship=?, civil_status=?, barangay_id=?, purok=?, is_solo_parent=?, is_pwd=?, is_senior=?, avatar=?, religion=?, occupation=?, educational_attainment=?, classification=? WHERE user_id=?')
                            ->execute([$address, $phone, $birthdate, $sex, $citizenship, $civil_status, $barangay_id, $purok, $is_solo_parent, $is_pwd, $is_senior, $avatarPath, $final_religion, $final_occupation, $final_edu, $final_classification, $_SESSION['user_id']]);
                    } else {
                        $pdo->prepare('UPDATE residents SET address=?, phone=?, birthdate=?, sex=?, citizenship=?, civil_status=?, barangay_id=?, purok=?, is_solo_parent=?, is_pwd=?, is_senior=?, religion=?, occupation=?, educational_attainment=?, classification=? WHERE user_id=?')
                            ->execute([$address, $phone, $birthdate, $sex, $citizenship, $civil_status, $barangay_id, $purok, $is_solo_parent, $is_pwd, $is_senior, $final_religion, $final_occupation, $final_edu, $final_classification, $_SESSION['user_id']]);
                    }
                } else {
                    $pdo->prepare('INSERT INTO residents (user_id, address, phone, birthdate, sex, citizenship, civil_status, barangay_id, purok, is_solo_parent, is_pwd, is_senior, avatar, religion, occupation, educational_attainment, classification) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$_SESSION['user_id'], $address, $phone, $birthdate, $sex, $citizenship, $civil_status, $barangay_id, $purok, $is_solo_parent, $is_pwd, $is_senior, $avatarPath, $religion ?: null, $occupation ?: null, $educational_attainment ?: null, $classification_json]);
                }
                $msg = 'Profile saved successfully.';
            } else {
                $msg = implode('. ', $errors);
            }
        }
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/profile_error_log.txt', date('Y-m-d H:i:s') . ' - POST ERROR: ' . $e->getMessage() . "\n", FILE_APPEND);
        $msg = 'A server error occurred while saving: ' . $e->getMessage();
    }
}

// Handle avatar removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_avatar') {
    try {
        if (csrf_validate()) {
            $stmt = $pdo->prepare('SELECT avatar FROM residents WHERE user_id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $res = $stmt->fetch();
            if ($res && !empty($res['avatar'])) {
                if (file_exists(__DIR__ . '/' . $res['avatar'])) {
                    @unlink(__DIR__ . '/' . $res['avatar']);
                }
                $pdo->prepare('UPDATE residents SET avatar = NULL WHERE user_id = ?')->execute([$_SESSION['user_id']]);
                $msg = 'Profile picture removed successfully.';
            }
        }
    } catch (Exception $e) {
        $msg = 'Error removing picture: ' . $e->getMessage();
    }
}

// Data fetching block
try {
    $stmt = $pdo->prepare('SELECT u.first_name, u.last_name, u.middle_name, u.suffix, u.full_name, u.email, r.* FROM users u LEFT JOIN residents r ON r.user_id = u.id WHERE u.id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $data = $stmt->fetch();

    $barangay_id_display = date('Y') . '-' . str_pad((string) $_SESSION['user_id'], 4, '0', STR_PAD_LEFT);

    $provinceValue = $municipalityValue = $barangayValue = '';
    if (!empty($data['address'])) {
        $storedAddressParts = array_map('trim', explode(',', $data['address']));
        if (count($storedAddressParts) >= 3) {
            $barangayValue = $storedAddressParts[0];
            $municipalityValue = $storedAddressParts[1];
            $provinceValue = $storedAddressParts[2];
        } elseif (count($storedAddressParts) === 2) {
            $barangayValue = $storedAddressParts[0];
            $municipalityValue = $storedAddressParts[1];
        } elseif (count($storedAddressParts) === 1) {
            $municipalityValue = $storedAddressParts[0];
        }
    }
} catch (Exception $e) {
    file_put_contents(__DIR__ . '/profile_error_log.txt', date('Y-m-d H:i:s') . ' - FETCH ERROR: ' . $e->getMessage() . "\n", FILE_APPEND);
    die('A critical error occurred while loading your profile data. Please check the error log.');
}

?>

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
                    <?php echo htmlspecialchars($data['email'] ?? ''); ?></p>
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

                    <h6 class="text-uppercase text-secondary fw-bold small mb-4 pb-2 border-bottom">Profile Picture</h6>

                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <label class="form-label fw-semibold text-secondary small text-uppercase">Upload Profile
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

                    <h6 class="text-uppercase text-secondary fw-bold small mb-4 pb-2 border-bottom mt-5">Personal
                        Information</h6>

                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small text-uppercase">First Name <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control"
                                value="<?php echo htmlspecialchars($data['first_name'] ?? ''); ?>"
                                placeholder="e.g. Juan" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small text-uppercase">Last Name <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control"
                                value="<?php echo htmlspecialchars($data['last_name'] ?? ''); ?>"
                                placeholder="e.g. Dela Cruz" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small text-uppercase">Middle
                                Name</label>
                            <input type="text" name="middle_name" class="form-control"
                                value="<?php echo htmlspecialchars($data['middle_name'] ?? ''); ?>"
                                placeholder="e.g. Santos">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small text-uppercase">Suffix</label>
                            <input type="text" name="suffix" class="form-control"
                                value="<?php echo htmlspecialchars($data['suffix'] ?? ''); ?>"
                                placeholder="e.g. Jr., Sr., III">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small text-uppercase">Email <span
                                    class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control"
                                value="<?php echo htmlspecialchars($data['email'] ?? ''); ?>"
                                placeholder="name@example.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small text-uppercase">Phone <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control"
                                value="<?php echo htmlspecialchars($data['phone'] ?? ''); ?>"
                                placeholder="e.g. 09123456789" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-secondary small text-uppercase">Birthdate <span
                                    class="text-danger">*</span></label>
                            <input type="date" name="birthdate" id="birthdate" class="form-control"
                                value="<?php echo htmlspecialchars($data['birthdate'] ?? ''); ?>" required>
                            <div class="form-text small text-muted">Must be at least 18 years old</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-secondary small text-uppercase">Sex <span
                                    class="text-danger">*</span></label>
                            <select name="sex" class="form-select" required>
                                <option value="">Select Sex</option>
                                <?php foreach (['Male', 'Female'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo (isset($data['sex']) && $data['sex'] === $s) ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-secondary small text-uppercase">Citizenship <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="citizenship" class="form-control"
                                value="<?php echo htmlspecialchars($data['citizenship'] ?? ''); ?>"
                                placeholder="e.g. Filipino" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small text-uppercase">Civil Status <span
                                    class="text-danger">*</span></label>
                            <select name="civil_status" class="form-select" required>
                                <option value="">Select Civil Status</option>
                                <?php foreach (['Single', 'Married', 'Widowed', 'Divorced', 'Separated'] as $cs): ?>
                                    <option value="<?php echo $cs; ?>" <?php echo (isset($data['civil_status']) && $data['civil_status'] === $cs) ? 'selected' : ''; ?>><?php echo $cs; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-secondary small text-uppercase">Purok</label>
                            <input type="text" name="purok" class="form-control"
                                value="<?php echo htmlspecialchars($data['purok'] ?? ''); ?>"
                                placeholder="e.g. Purok 1, Purok 2">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-secondary small text-uppercase">Complete Address</label>
                            <input type="text" name="address" class="form-control"
                                value="<?php echo htmlspecialchars($data['address'] ?? ''); ?>"
                                placeholder="e.g. 123 Main St., Panungyanan, General Trias, Cavite">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-secondary small text-uppercase">Religion</label>
                            <input type="text" name="religion" class="form-control"
                                value="<?php echo htmlspecialchars($data['religion'] ?? ''); ?>"
                                placeholder="e.g. Roman Catholic">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-secondary small text-uppercase">Profession / Occupation</label>
                            <input type="text" name="occupation" class="form-control"
                                value="<?php echo htmlspecialchars($data['occupation'] ?? ''); ?>"
                                placeholder="e.g. Teacher, Farmer">
                        </div>
                        <div class="col-12">
                            <div class="row g-3">
                                <div class="col-md-6 border-end pe-4">
                                    <label class="form-label fw-semibold text-secondary small text-uppercase d-block mb-3">HIGHEST EDUCATIONAL ATTAINMENT</label>
                                    <div class="d-flex flex-column gap-2">
                                        <?php
                                        $current_edu = $data['educational_attainment'] ?? '';
                                        $base_edu = trim(explode(' (', $current_edu)[0]);
                                        $edu_opts = ['Elementary', 'High School', 'College', 'Post Grad', 'Vocational'];
                                        foreach ($edu_opts as $opt):
                                            ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="educational_attainment"
                                                    value="<?php echo $opt; ?>" id="edu_<?php echo str_replace(' ','_',$opt); ?>" 
                                                    <?php echo ($base_edu === $opt) ? 'checked' : ''; ?>>
                                                <label class="form-check-label"
                                                    for="edu_<?php echo str_replace(' ','_',$opt); ?>"><?php echo $opt; ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-6 ps-4">
                                    <label class="form-label fw-semibold text-secondary small text-uppercase d-block mb-3">PLEASE SPECIFY</label>
                                    <div class="d-flex flex-column gap-2">
                                        <?php
                                        $is_grad = stripos($current_edu, '(graduate)') !== false;
                                        $is_under = stripos($current_edu, '(under graduate)') !== false;
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
                            <label class="form-label fw-semibold text-secondary small text-uppercase">Special Classification (Optional - Multiple Selection)</label>
                            <?php
                            $saved_classes = [];
                            if (!empty($data['classification'])) {
                                $decoded = json_decode($data['classification'], true);
                                if (is_array($decoded)) $saved_classes = $decoded;
                            }
                            $all_classes = [
                                'Labor/Employed', 'Unemployed', 'PWD', 'OFW', 'Solo Parent',
                                'Out of School Youth (OSY)', 'Out of School Children (OSC)', 'Indigenous People', 'Senior Citizen'
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

                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small text-uppercase mb-1">Barangay ID</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="fas fa-id-badge"></i></span>
                                <input type="text" name="barangay_id" class="form-control bg-light fw-bold"
                                    value="<?php echo htmlspecialchars($barangay_id_display); ?>" readonly>
                                <span class="input-group-text bg-light text-secondary" title="Auto-generated"><i class="fas fa-lock"></i></span>
                            </div>
                            <div class="form-text small text-muted"><i class="fas fa-info-circle me-1"></i>Auto-generated</div>
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
                } else if (exactAge < 18) {
                    this.setCustomValidity('You must be at least 18 years old');
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

        // ===== PSGC API Cascading Dropdowns =====
        const PSGC_API = 'https://psgc.gitlab.io/api';
        const provinceSelect = document.getElementById('province');
        const municipalitySelect = document.getElementById('municipality');
        const barangaySelect = document.getElementById('barangay');

        if (!provinceSelect || !municipalitySelect || !barangaySelect) {
            console.log('Address dropdowns not found, skipping PSGC initialization.');
            return;
        }

        const selectedProv = provinceSelect.dataset.selected;
        const selectedMun = municipalitySelect.dataset.selected;
        const selectedBrgy = barangaySelect.dataset.selected;

        // Load all provinces
        fetch(`${PSGC_API}/provinces/`)
            .then(res => res.json())
            .then(data => {
                data.sort((a, b) => a.name.localeCompare(b.name));
                data.forEach(prov => {
                    const opt = document.createElement('option');
                    opt.value = prov.name;
                    opt.textContent = prov.name;
                    opt.dataset.code = prov.code;
                    if (prov.name === selectedProv) opt.selected = true;
                    provinceSelect.appendChild(opt);
                });

                if (selectedProv) {
                    provinceSelect.dispatchEvent(new Event('change'));
                }
            })
            .catch(() => {
                // Fallback: allow text input if API fails
                provinceSelect.outerHTML = '<input type="text" name="province" id="province" class="form-control" value="' + selectedProv + '" required>';
                municipalitySelect.outerHTML = '<input type="text" name="municipality" id="municipality" class="form-control" value="' + selectedMun + '" required>';
                barangaySelect.outerHTML = '<input type="text" name="barangay" id="barangay" class="form-control" value="' + selectedBrgy + '" required>';
            });

        // When province changes, load municipalities
        provinceSelect.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            if (!selected) return;
            const code = selected.dataset.code;

            municipalitySelect.innerHTML = '<option value="">Loading...</option>';
            municipalitySelect.disabled = true;
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            barangaySelect.disabled = true;

            if (!code) {
                municipalitySelect.innerHTML = '<option value="">Select Municipality</option>';
                return;
            }

            fetch(`${PSGC_API}/provinces/${code}/cities-municipalities/`)
                .then(res => res.json())
                .then(data => {
                    municipalitySelect.innerHTML = '<option value="">Select Municipality</option>';
                    data.sort((a, b) => a.name.localeCompare(b.name));
                    data.forEach(mun => {
                        const opt = document.createElement('option');
                        opt.value = mun.name;
                        opt.textContent = mun.name;
                        opt.dataset.code = mun.code;
                        if (mun.name === selectedMun) opt.selected = true;
                        municipalitySelect.appendChild(opt);
                    });
                    municipalitySelect.disabled = false;

                    if (selectedMun && selected.value === selectedProv) {
                        municipalitySelect.dispatchEvent(new Event('change'));
                    }
                });
        });

        // When municipality changes, load barangays
        municipalitySelect.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            if (!selected) return;
            const code = selected.dataset.code;

            barangaySelect.innerHTML = '<option value="">Loading...</option>';
            barangaySelect.disabled = true;

            if (!code) {
                barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
                return;
            }

            // Helper function to load barangays from a specific path
            const loadBarangays = (path) => {
                return fetch(`${PSGC_API}/${path}/${code}/barangays/`)
                    .then(res => {
                        if (!res.ok) throw new Error('Not found ' + path);
                        return res.json();
                    });
            };

            // Try the combined endpoint first, then fall back to city or municipality specific ones
            loadBarangays('cities-municipalities')
                .catch(() => loadBarangays('cities')) // Fallback 1: Try as city
                .catch(() => loadBarangays('municipalities')) // Fallback 2: Try as municipality
                .then(data => {
                    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
                    if (!data || !Array.isArray(data) || data.length === 0) {
                        barangaySelect.innerHTML = '<option value="">No barangays found</option>';
                        barangaySelect.disabled = false;
                        return;
                    }
                    data.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
                    data.forEach(brgy => {
                        const opt = document.createElement('option');
                        opt.value = brgy.name;
                        opt.textContent = brgy.name;
                        if (brgy.name === selectedBrgy) opt.selected = true;
                        barangaySelect.appendChild(opt);
                    });
                    barangaySelect.disabled = false;
                })
                .catch(err => {
                    console.error('Final fetch error:', err);
                    barangaySelect.innerHTML = '<option value="">Manual Entry Mode</option>';
                    // If all API calls fail, allow manual entry as fallback
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.name = 'barangay';
                    input.id = 'barangay';
                    input.className = 'form-control';
                    input.value = selectedBrgy || '';
                    input.required = true;
                    barangaySelect.replaceWith(input);
                });
        });

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
