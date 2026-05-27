<?php 
require_once __DIR__ . '/../config.php';
$admin_id = (int)($_GET['id'] ?? 0);
if (!is_admin() || ($_SESSION['user_id'] != 1 && $_SESSION['user_id'] != $admin_id)) redirect('../index.php');

if ($admin_id <= 0) redirect('sub_admin_management.php');

$pdo = get_db_connection();
$stmt = $pdo->prepare('
    SELECT u.first_name, u.last_name, u.middle_name, u.suffix, u.full_name, u.email, u.role, u.is_active, u.created_at, u.profile_picture,
           r.*
    FROM users u 
    LEFT JOIN residents r ON r.user_id = u.id 
    WHERE u.id = ? AND u.role = "admin"
');
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (!$admin) {
    echo '<div class="container-fluid py-5 text-center"><h4>Admin account not found.</h4><a href="sub_admin_management.php" class="btn btn-primary mt-3">Back to List</a></div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents(__DIR__ . '/profile_error_log.txt', "[" . date('Y-m-d H:i:s') . "] POST Action: " . ($_POST['action'] ?? 'none') . " | POST Data: " . json_encode($_POST) . "\n", FILE_APPEND);
    
    if (isset($_POST['action']) && $_POST['action'] === 'update_admin') {
        if (csrf_validate()) {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $middle_name = trim($_POST['middle_name'] ?? '');
            $suffix = trim($_POST['suffix'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $birthdate = $_POST['birthdate'] ?? null;
            $birth_place = trim($_POST['birth_place'] ?? '');
            $sex = $_POST['sex'] ?? null;
            $citizenship = trim($_POST['citizenship'] ?? '');
            $civil_status = trim($_POST['civil_status'] ?? '');
            $purok = trim($_POST['purok'] ?? '');
            $address = trim($_POST['address'] ?? '');
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
            
            // Set demographic flags for compatibility
            $is_solo_parent = in_array('Solo Parent', $classifications) ? 1 : 0;
            $is_pwd = in_array('PWD', $classifications) ? 1 : 0;
            $is_senior = in_array('Senior Citizen', $classifications) ? 1 : 0;

            $name_parts = array_filter([$first_name, $middle_name, $last_name, $suffix]);
            $full_name = implode(' ', $name_parts);

            $profile_picture = $admin['profile_picture'];
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../public/uploads/profile_pics/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $file_ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($file_ext, $allowed_extensions)) {
                    $new_filename = 'admin_' . $admin_id . '_' . uniqid() . '.' . $file_ext;
                    $target_file = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                        $profile_picture = 'public/uploads/profile_pics/' . $new_filename;
                    } else {
                        $error = 'Failed to move uploaded file to destination.';
                    }
                } else {
                    $error = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
                }
            }

            if (!isset($error)) {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, middle_name = ?, suffix = ?, full_name = ?, email = ?, profile_picture = ? WHERE id = ?');
                    $stmt->execute([$first_name, $last_name, $middle_name ?: null, $suffix ?: null, $full_name, $email, $profile_picture, $admin_id]);

                    $barangay_id = date('Y') . '-' . str_pad((string) $admin_id, 4, '0', STR_PAD_LEFT);

                    $stmt = $pdo->prepare('SELECT user_id FROM residents WHERE user_id = ?');
                    $stmt->execute([$admin_id]);
                    if ($stmt->fetch()) {
                        $stmt = $pdo->prepare('UPDATE residents SET address = ?, phone = ?, sex = ?, civil_status = ?, birthdate = ?, birth_place = ?, citizenship = ?, purok = ?, is_solo_parent = ?, is_pwd = ?, is_senior = ?, avatar = ?, religion = ?, occupation = ?, educational_attainment = ?, classification = ?, barangay_id = ? WHERE user_id = ?');
                        $stmt->execute([$address, $phone, $sex, $civil_status, $birthdate, $birth_place, $citizenship, $purok, $is_solo_parent, $is_pwd, $is_senior, $profile_picture, $religion, $occupation, $educational_attainment, $classification_json, $barangay_id, $admin_id]);
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO residents (user_id, address, phone, sex, civil_status, birthdate, birth_place, citizenship, purok, is_solo_parent, is_pwd, is_senior, avatar, religion, occupation, educational_attainment, classification, barangay_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                        $stmt->execute([$admin_id, $address, $phone, $sex, $civil_status, $birthdate, $birth_place, $citizenship, $purok, $is_solo_parent, $is_pwd, $is_senior, $profile_picture, $religion, $occupation, $educational_attainment, $classification_json, $barangay_id]);
                    }
                    $pdo->commit();
                    header("Location: admin_info_view.php?id=$admin_id&updated=1");
                    exit;
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $error = 'Database Error: ' . $e->getMessage();
                    file_put_contents(__DIR__ . '/profile_error_log.txt', "[" . date('Y-m-d H:i:s') . "] Admin Update Error: " . $e->getMessage() . "\n", FILE_APPEND);
                }
            }
        } else {
            $error = 'Security Token mismatch. Please refresh and try again.';
            file_put_contents(__DIR__ . '/profile_error_log.txt', "[" . date('Y-m-d H:i:s') . "] Admin Update CSRF Error\n", FILE_APPEND);
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_profile_pic') {
        if (csrf_validate()) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('UPDATE users SET profile_picture = NULL WHERE id = ?');
                $stmt->execute([$admin_id]);
                
                $stmt = $pdo->prepare('UPDATE residents SET avatar = NULL WHERE user_id = ?');
                $stmt->execute([$admin_id]);
                
                $pdo->commit();
                file_put_contents(__DIR__ . '/profile_error_log.txt', "[" . date('Y-m-d H:i:s') . "] Photo Deleted Successfully for Admin ID: $admin_id\n", FILE_APPEND);
                header("Location: admin_info_view.php?id=$admin_id&updated=1");
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = 'Delete failed: ' . $e->getMessage();
                file_put_contents(__DIR__ . '/profile_error_log.txt', "[" . date('Y-m-d H:i:s') . "] Photo Delete Error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        } else {
            $error = 'Security Token mismatch during deletion.';
            file_put_contents(__DIR__ . '/profile_error_log.txt', "[" . date('Y-m-d H:i:s') . "] Photo Delete CSRF Error\n", FILE_APPEND);
        }
    }
}

$is_editing = isset($_GET['edit']);
$is_system_admin = ($admin_id == 1);
$page_title = $is_system_admin ? 'Admin Profile' : 'Sub-admin Profile';
$breadcrumb = [
    ['title' => 'Account Management', 'url' => 'account_management.php']
];
if (!$is_system_admin) {
    $breadcrumb[] = ['title' => 'Sub-admin Management', 'url' => 'sub_admin_management.php'];
    $breadcrumb[] = ['title' => 'View Details'];
} else {
    $breadcrumb[] = ['title' => 'Admin Profile'];
}
require_once __DIR__ . '/header.php'; 
?>

<style>
    :root { --p-grad: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); --sys-teal: #14b8a6; }
    .profile-card { border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; }
    .profile-header { background: var(--p-grad); padding: 2.5rem 1.5rem; position: relative; overflow: hidden; }
    .profile-header::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%); animation: rotate 20s linear infinite; }
    @keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .profile-img-wrapper { position: relative; z-index: 2; margin-bottom: 1rem; }
    .profile-img { width: 85px; height: 85px; border: 3px solid rgba(255,255,255,0.3); padding: 4px; background: white; object-fit: cover; }
    .info-card { background: #fdfdfd; border-radius: 12px; padding: 1.1rem; border: 1px solid #f1f5f9; transition: all 0.2s ease; height: 100%; }
    .info-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.04); border-color: #14b8a6; }
    .section-label { font-size: 0.65rem; color: #64748b; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 6px; }
    .section-label i { color: #14b8a6; font-size: 0.75rem; }
    .info-value { font-weight: 700; color: #334155; font-size: 0.95rem; line-height: 1.2; }
    .edit-toggle-btn { position: absolute; top: 15px; right: 15px; z-index: 10; background: rgba(255,255,255,0.15); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.25); color: white; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; transition: 0.3s; }
    .edit-toggle-btn:hover { background: white; color: #14b8a6; }
    .form-control, .form-select { border-radius: 10px; border: 1.5px solid #e2e8f0; padding: 0.6rem 0.8rem; font-weight: 600; font-size: 0.9rem; }
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="mb-3 d-flex align-items-center justify-content-between">
                <?php if ($admin_id == 1): ?>
                <a href="account_management.php" class="btn btn-link text-decoration-none text-muted fw-bold p-0 small">
                    <i class="fas fa-arrow-left me-2"></i> Back to Admin List
                </a>
                <?php else: ?>
                <a href="sub_admin_management.php" class="btn btn-link text-decoration-none text-muted fw-bold p-0 small">
                    <i class="fas fa-arrow-left me-2"></i> Back to Staff List
                </a>
                <?php endif; ?>
            </div>

            <div class="card profile-card">
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_admin">

                    <div class="profile-header text-center">
                    <?php if (!$is_editing): ?>
                        <a href="?id=<?php echo $admin_id; ?>&edit=1" class="edit-toggle-btn" title="Edit Profile"><i class="fas fa-pen fa-sm"></i></a>
                    <?php else: ?>
                        <a href="?id=<?php echo $admin_id; ?>" class="edit-toggle-btn" title="Cancel Editing"><i class="fas fa-times"></i></a>
                    <?php endif; ?>

                    <div class="profile-img-wrapper">
                        <?php 
                            $p_img = !empty($admin['profile_picture']) ? '../' . $admin['profile_picture'] . '?v=' . time() : '../public/img/barangaylogo.png';
                        ?>
                        <img src="<?php echo $p_img; ?>" 
                             class="profile-img rounded-circle shadow-sm" 
                             alt="Profile Logo" 
                             id="avatarPreview" 
                             style="cursor: pointer; position: relative; z-index: 5;"
                             onclick="viewProfileCircle('<?php echo $p_img; ?>')">
                        <?php if ($is_editing): ?>
                            <div class="mt-2">
                                <label for="profile_pic" class="badge bg-white text-dark py-2 px-3 shadow-sm border" style="cursor:pointer;">
                                    <i class="fas fa-camera me-1"></i> Change Photo
                                </label>
                                <input type="file" id="profile_pic" name="profile_pic" class="d-none" accept="image/*" onchange="previewImage(this)">
                                <?php if (!empty($admin['profile_picture'])): ?>
                                    <button type="button" class="badge bg-danger text-white py-2 px-3 shadow-sm border-0 ms-1" onclick="confirmDeletePhoto()">
                                        <i class="fas fa-trash me-1"></i> Delete Photo
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h2 class="text-white fw-800 mb-1" style="font-size: 1.75rem; letter-spacing: -0.5px;"><?php echo htmlspecialchars($admin['full_name']); ?></h2>
                    <div class="d-flex flex-column align-items-center gap-2">
                        <span class="badge bg-white text-secondary px-3 py-2 rounded-pill" style="font-size: 0.65rem; font-weight: 800; letter-spacing: 0.5px;">
                            <?php echo ($admin_id == 1) ? 'SYSTEM ADMINISTRATOR' : 'SUB-ADMINISTRATOR'; ?>
                        </span>
                        <?php if ($admin['is_active']): ?>
                            <span class="badge bg-success text-white px-3 py-1.5 rounded-pill shadow-sm" style="font-size: 0.6rem; font-weight: 800;"><i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>ACTIVE</span>
                        <?php else: ?>
                            <span class="badge bg-danger text-white px-3 py-1.5 rounded-pill shadow-sm" style="font-size: 0.6rem; font-weight: 800;"><i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>INACTIVE</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body p-4">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger border-0 rounded-3 p-2 mb-4 d-flex align-items-center gap-2 shadow-sm small">
                            <i class="fas fa-exclamation-circle text-danger ms-2"></i>
                            <div class="fw-bold"><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['updated'])): ?>
                        <div class="alert alert-success border-0 rounded-3 p-2 mb-4 d-flex align-items-center gap-2 shadow-sm small">
                            <i class="fas fa-check-circle text-success ms-2"></i>
                            <div class="fw-bold">Profile updated successfully!</div>
                        </div>
                    <?php endif; ?>

                        <!-- SECTION: PERSONAL -->
                        <div class="mb-4">
                            <h6 class="fw-800 mb-3 d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                                <span style="width: 3px; height: 16px; background: #14b8a6; display: inline-block; border-radius: 10px;"></span>
                                Personal Details
                            </h6>
                            <div class="row g-3">
                                <?php if ($is_editing): ?>
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <div class="section-label"><i class="fas fa-user"></i> First Name</div>
                                            <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($admin['first_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <div class="section-label"><i class="fas fa-user"></i> Last Name</div>
                                            <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($admin['last_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <div class="section-label"><i class="fas fa-user"></i> Middle Name</div>
                                            <input type="text" name="middle_name" class="form-control" value="<?php echo htmlspecialchars($admin['middle_name'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <div class="section-label"><i class="fas fa-user"></i> Suffix</div>
                                            <input type="text" name="suffix" class="form-control" value="<?php echo htmlspecialchars($admin['suffix'] ?? ''); ?>">
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="col-12">
                                        <div class="info-card">
                                            <div class="section-label"><i class="fas fa-user"></i> Full Name</div>
                                            <div class="info-value"><?php echo htmlspecialchars($admin['full_name'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-id-card"></i> Citizenship</div>
                                        <?php if ($is_editing): ?><input type="text" name="citizenship" class="form-control" value="<?php echo htmlspecialchars($admin['citizenship'] ?? ''); ?>" required>
                                        <?php else: ?><div class="info-value"><?php echo htmlspecialchars($admin['citizenship'] ?: 'N/A'); ?></div><?php endif; ?>
                                    </div>
                                </div>
                                                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-map-marker-alt"></i> Place of Birth</div>
                                        <?php if ($is_editing): ?><input type="text" name="birth_place" class="form-control" value="<?php echo htmlspecialchars($admin['birth_place'] ?? ''); ?>" placeholder="e.g. Manila" required>
                                        <?php else: ?><div class="info-value"><?php echo htmlspecialchars($admin['birth_place'] ?: 'N/A'); ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-calendar"></i> Birthdate</div>
                                        <?php if ($is_editing): ?><input type="date" name="birthdate" class="form-control" value="<?php echo $admin['birthdate'] ?? ''; ?>" max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>" required>
                                        <?php else: ?><div class="info-value"><?php echo (!empty($admin['birthdate']) && $admin['birthdate'] !== '0000-00-00') ? date('M d, Y', strtotime($admin['birthdate'])) : 'N/A'; ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-venus-mars"></i> Sex</div>
                                        <?php if ($is_editing): ?>
                                            <select name="sex" class="form-select" required>
                                                <option value="Male" <?php echo ($admin['sex'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="Female" <?php echo ($admin['sex'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                            </select>
                                        <?php else: ?><div class="info-value"><?php echo htmlspecialchars($admin['sex'] ?: 'N/A'); ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-heart"></i> Civil Status</div>
                                        <?php if ($is_editing): ?>
                                            <select name="civil_status" class="form-select" required>
                                                <option value="Single" <?php echo ($admin['civil_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                                                <option value="Married" <?php echo ($admin['civil_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
                                                <option value="Widowed" <?php echo ($admin['civil_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                                <option value="Divorced" <?php echo ($admin['civil_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                                <option value="Separated" <?php echo ($admin['civil_status'] ?? '') === 'Separated' ? 'selected' : ''; ?>>Separated</option>
                                            </select>
                                        <?php else: ?><div class="info-value"><?php echo htmlspecialchars($admin['civil_status'] ?: 'N/A'); ?></div><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION: CONTACT & LOCATION -->
                        <div class="mb-4">
                            <h6 class="fw-800 mb-3 d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                                <span style="width: 3px; height: 16px; background: #14b8a6; display: inline-block; border-radius: 10px;"></span>
                                Contact & Location
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-envelope"></i> Email</div>
                                        <?php if ($is_editing): ?><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                        <?php else: ?><div class="info-value text-secondary"><?php echo htmlspecialchars($admin['email']); ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-phone"></i> Phone</div>
                                        <?php if ($is_editing): ?><input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>" maxlength="11" pattern="[0-9]{11}" required>
                                        <?php else: ?><div class="info-value"><?php echo htmlspecialchars($admin['phone'] ?: 'N/A'); ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-home"></i> Full Address</div>
                                        <?php if ($is_editing): ?><input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($admin['address'] ?? ''); ?>">
                                        <?php else: ?><div class="info-value text-truncate" title="<?php echo htmlspecialchars($admin['address'] ?? ''); ?>"><?php echo htmlspecialchars($admin['address'] ?: 'N/A'); ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-map-pin"></i> Purok</div>
                                        <?php if ($is_editing): ?><input type="text" name="purok" class="form-control" value="<?php echo htmlspecialchars($admin['purok'] ?? ''); ?>">
                                        <?php else: ?><div class="info-value"><?php echo htmlspecialchars($admin['purok'] ?: 'N/A'); ?></div><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION: RELIGION, OCCUPATION & EDUCATION -->
                        <div class="mb-4">
                            <h6 class="fw-800 mb-3 d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                                <span style="width: 3px; height: 16px; background: #14b8a6; display: inline-block; border-radius: 10px;"></span>
                                Additional Profile Details
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-praying-hands"></i> Religion</div>
                                        <?php if ($is_editing): ?><input type="text" name="religion" class="form-control" value="<?php echo htmlspecialchars($admin['religion'] ?? ''); ?>" placeholder="e.g. Roman Catholic">
                                        <?php else: ?><div class="info-value"><?php echo htmlspecialchars($admin['religion'] ?: 'N/A'); ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-briefcase"></i> Profession / Occupation</div>
                                        <?php if ($is_editing): ?><input type="text" name="occupation" class="form-control" value="<?php echo htmlspecialchars($admin['occupation'] ?? ''); ?>" placeholder="e.g. Teacher, Civil Servant">
                                        <?php else: ?><div class="info-value"><?php echo htmlspecialchars($admin['occupation'] ?: 'N/A'); ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-graduation-cap"></i> Highest Educational Attainment</div>
                                        <?php if ($is_editing): ?>
                                            <div class="row g-3">
                                                <div class="col-md-6 border-end pe-3">
                                                    <div class="d-flex flex-column gap-2 pt-1">
                                                        <?php
                                                        $current_edu = $admin['educational_attainment'] ?? '';
                                                        $base_edu = trim(explode(' (', $current_edu)[0]);
                                                        $edu_opts = ['Elementary', 'High School', 'College', 'Post Grad', 'Vocational'];
                                                        foreach ($edu_opts as $opt):
                                                            ?>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="educational_attainment"
                                                                    value="<?php echo $opt; ?>" id="edu_<?php echo str_replace(' ','_',$opt); ?>" 
                                                                    <?php echo ($base_edu === $opt) ? 'checked' : ''; ?>>
                                                                <label class="form-check-label small fw-bold"
                                                                    for="edu_<?php echo str_replace(' ','_',$opt); ?>"><?php echo $opt; ?></label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 ps-3">
                                                    <div class="d-flex flex-column gap-2 pt-1">
                                                        <?php
                                                        $is_grad = stripos($current_edu, '(graduate)') !== false;
                                                        $is_under = stripos($current_edu, '(under graduate)') !== false;
                                                        ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="edu_status"
                                                                value="Graduate" id="gradY" <?php echo $is_grad ? 'checked' : ''; ?>>
                                                            <label class="form-check-label small fw-bold" for="gradY">Graduate</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="edu_status"
                                                                value="Under Graduate" id="gradN" <?php echo $is_under ? 'checked' : ''; ?>>
                                                            <label class="form-check-label small fw-bold" for="gradN">Under Graduate</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="info-value"><?php echo htmlspecialchars($admin['educational_attainment'] ?: 'N/A'); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION: MISC -->
                        <div class="mb-2">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-tags"></i> Special Classifications</div>
                                        <?php
                                        $saved_classes = [];
                                        if (!empty($admin['classification'])) {
                                            $decoded = json_decode($admin['classification'], true);
                                            if (is_array($decoded)) $saved_classes = $decoded;
                                        }
                                        $all_classes = [
                                            'Labor/Employed', 'Unemployed', 'PWD', 'OFW', 'Solo Parent',
                                            'Out of School Youth (OSY)', 'Out of School Children (OSC)', 'Indigenous People', 'Senior Citizen'
                                        ];
                                        if ($is_editing):
                                        ?>
                                            <div class="row g-2 pt-1">
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
                                        <?php else: ?>
                                            <div class="d-flex flex-wrap gap-2 pt-1">
                                                <?php foreach ($saved_classes as $cls): 
                                                    $bgClass = 'bg-secondary';
                                                    if ($cls === 'Solo Parent') $bgClass = 'bg-teal-soft text-teal';
                                                    elseif ($cls === 'PWD') $bgClass = 'bg-orange-soft text-orange';
                                                    elseif ($cls === 'Senior Citizen') $bgClass = 'bg-blue-soft text-blue';
                                                ?>
                                                    <span class="badge <?php echo $bgClass; ?> px-2.5 py-1.5" style="font-size: 0.7rem; font-weight: 700;"><?php echo htmlspecialchars($cls); ?></span>
                                                <?php endforeach; ?>
                                                <?php if (empty($saved_classes)): ?><span class="text-muted small">None</span><?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-circle-check"></i> Account Integrity</div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="info-value <?php echo $admin['is_active'] ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $admin['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                            </div>
                                            <div class="text-muted font-monospace small" style="font-size: 0.75rem; font-weight: 600;"><?php echo date('M Y', strtotime($admin['created_at'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-id-badge"></i> Barangay ID</div>
                                        <div class="info-value font-monospace text-primary">
                                            <?php 
                                                $barangay_id_display = date('Y') . '-' . str_pad((string) $admin_id, 4, '0', STR_PAD_LEFT);
                                                echo htmlspecialchars($admin['barangay_id'] ?: $barangay_id_display); 
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($is_editing): ?>
                            <div class="text-center mt-4 pt-3 border-top border-light">
                                <button type="button" class="btn btn-primary btn-sm px-4 rounded-pill shadow-sm fw-800" onclick="confirmUpdate(this)">
                                    <i class="fas fa-save me-1"></i> SAVE CHANGES
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewProfileCircle(imgSrc) {
    Swal.fire({
        imageUrl: imgSrc,
        imageWidth: 400,
        imageHeight: 400,
        imageAlt: 'Profile Picture',
        showConfirmButton: false,
        background: 'transparent',
        backdrop: `rgba(0,0,123,0.4)`,
        customClass: {
            image: 'rounded-circle shadow-lg border border-4 border-white'
        }
    });
}

function confirmDeletePhoto() {
    Swal.fire({
        title: 'Delete Profile Photo?',
        text: 'This will remove your current profile picture and revert to the default.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Delete It',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_profile_pic">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function confirmUpdate(btn) {
    const form = btn.closest('form');
    Swal.fire({
        title: 'Save Changes?',
        text: 'Are you sure you want to update this administrative profile?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#14b8a6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Save it!',
        cancelButtonText: 'Review Form'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
}
</script>

<style>
    .bg-teal-soft { background: rgba(20, 184, 166, 0.1); } .text-teal { color: #14b8a6; }
    .bg-orange-soft { background: rgba(249, 115, 22, 0.1); } .text-orange { color: #f97316; }
    .bg-blue-soft { background: rgba(59, 130, 246, 0.1); } .text-blue { color: #3b82f6; }
    .fw-800 { font-weight: 800; }
</style>

<?php require_once __DIR__ . '/footer.php'; ?>
