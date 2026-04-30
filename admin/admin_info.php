<?php 
require_once __DIR__ . '/../config.php';
if (!is_admin()) redirect('../index.php');

$admin_id = $_SESSION['user_id'];
$pdo = get_db_connection();

$is_editing = isset($_GET['edit']) && $_GET['edit'] == 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_admin') {
    if (csrf_validate()) {
        $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_SPECIAL_CHARS);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_SPECIAL_CHARS);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS);
        $sex = $_POST['sex'];
        $civil_status = $_POST['civil_status'];
        $birthdate = $_POST['birthdate'];
        $citizenship = filter_input(INPUT_POST, 'citizenship', FILTER_SANITIZE_SPECIAL_CHARS);
        $purok = filter_input(INPUT_POST, 'purok', FILTER_SANITIZE_SPECIAL_CHARS);

        // Fetch current data to get current profile picture
        $stmt = $pdo->prepare('SELECT profile_picture FROM users WHERE id = ?');
        $stmt->execute([$admin_id]);
        $current_admin = $stmt->fetch();
        
        $profile_picture = $current_admin['profile_picture'];
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../public/uploads/profile_pics/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file_ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            $new_filename = 'admin_' . $admin_id . '_' . time() . '.' . $file_ext;
            $target_file = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                $profile_picture = 'public/uploads/profile_pics/' . $new_filename;
            }
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, profile_picture = ? WHERE id = ?');
            $stmt->execute([$full_name, $email, $profile_picture, $admin_id]);

            // Check if resident record exists, if not create one
            $stmt = $pdo->prepare('SELECT user_id FROM residents WHERE user_id = ?');
            $stmt->execute([$admin_id]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare('UPDATE residents SET address = ?, phone = ?, sex = ?, civil_status = ?, birthdate = ?, citizenship = ?, purok = ?, avatar = ? WHERE user_id = ?');
                $stmt->execute([$address, $phone, $sex, $civil_status, $birthdate, $citizenship, $purok, $profile_picture, $admin_id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO residents (user_id, address, phone, sex, civil_status, birthdate, citizenship, purok, avatar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$admin_id, $address, $phone, $sex, $civil_status, $birthdate, $citizenship, $purok, $profile_picture]);
            }
            $pdo->commit();
            redirect('admin_info.php?updated=1');
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Update failed: " . $e->getMessage();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_profile_pic') {
    if (csrf_validate()) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE users SET profile_picture = NULL WHERE id = ?');
            $stmt->execute([$admin_id]);
            
            $stmt = $pdo->prepare('UPDATE residents SET avatar = NULL WHERE user_id = ?');
            $stmt->execute([$admin_id]);
            
            $pdo->commit();
            redirect('admin_info.php?updated=1');
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Delete failed: ' . $e->getMessage();
        }
    }
}

$stmt = $pdo->prepare('
    SELECT u.*, r.address, r.phone, r.birthdate, r.sex, r.civil_status, r.citizenship, r.purok
    FROM users u 
    LEFT JOIN residents r ON r.user_id = u.id 
    WHERE u.id = ?
');
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (!$admin) redirect('account_management.php');

$page_title = 'Account Profile';
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
            <div class="card profile-card">
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_admin">
                    <div class="profile-header text-center">
                    <?php if (!$is_editing): ?>
                        <a href="?edit=1" class="edit-toggle-btn" title="Edit Profile"><i class="fas fa-pen fa-sm"></i></a>
                    <?php else: ?>
                        <a href="admin_info.php" class="edit-toggle-btn" title="Cancel Editing"><i class="fas fa-times"></i></a>
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
                        <span class="badge bg-white text-teal px-3 py-2 rounded-pill" style="color: #14b8a6; font-size: 0.65rem; font-weight: 800; letter-spacing: 0.5px;">
                            <?php echo ($_SESSION['user_id'] == 1) ? 'SYSTEM ADMINISTRATOR' : 'SUB-ADMINISTRATOR'; ?>
                        </span>
                        <span class="badge bg-success text-white px-3 py-1.5 rounded-pill shadow-sm" style="font-size: 0.6rem; font-weight: 800;"><i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>ACTIVE</span>
                    </div>
                </div>

                <div class="card-body p-4">
                    <?php if (isset($_GET['updated'])): ?>
                        <div class="alert alert-success border-0 rounded-3 p-2 mb-4 d-flex align-items-center gap-2 shadow-sm small">
                            <i class="fas fa-check-circle text-success ms-2"></i>
                            <div class="fw-bold">Your profile has been updated successfully!</div>
                        </div>
                    <?php endif; ?>


                        
                        <!-- SECTION: PERSONAL -->
                        <div class="mb-4">
                            <h6 class="fw-800 mb-3 d-flex align-items-center gap-2" style="color: #475569; font-size: 0.85rem;">
                                <span style="width: 3px; height: 16px; background: #14b8a6; display: inline-block; border-radius: 10px;"></span>
                                Personal Details
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-user"></i> Full Name</div>
                                        <?php if ($is_editing): ?><input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
                                        <?php else: ?><div class="info-value"><?php echo htmlspecialchars($admin['full_name']); ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-id-card"></i> Citizenship</div>
                                        <?php if ($is_editing): ?><input type="text" name="citizenship" class="form-control" value="<?php echo htmlspecialchars($admin['citizenship'] ?: 'Filipino'); ?>">
                                        <?php else: ?><div class="info-value"><?php echo htmlspecialchars($admin['citizenship'] ?: 'Filipino'); ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-calendar"></i> Birthdate</div>
                                        <?php if ($is_editing): ?><input type="date" name="birthdate" class="form-control" value="<?php echo $admin['birthdate']; ?>">
                                        <?php else: ?><div class="info-value"><?php echo $admin['birthdate'] ? date('M d, Y', strtotime($admin['birthdate'])) : 'N/A'; ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-venus-mars"></i> Sex</div>
                                        <?php if ($is_editing): ?>
                                            <select name="sex" class="form-select">
                                                <option value="Male" <?php echo $admin['sex'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="Female" <?php echo $admin['sex'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                            </select>
                                        <?php else: ?><div class="info-value"><?php echo htmlspecialchars($admin['sex'] ?: 'N/A'); ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-heart"></i> Civil Status</div>
                                        <?php if ($is_editing): ?>
                                            <select name="civil_status" class="form-select">
                                                <option value="Single" <?php echo $admin['civil_status'] === 'Single' ? 'selected' : ''; ?>>Single</option>
                                                <option value="Married" <?php echo $admin['civil_status'] === 'Married' ? 'selected' : ''; ?>>Married</option>
                                                <option value="Widowed" <?php echo $admin['civil_status'] === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                            </select>
                                        <?php else: ?><div class="info-value"><?php echo htmlspecialchars($admin['civil_status'] ?: 'N/A'); ?></div><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION: CONTACT -->
                        <div class="mb-4">
                            <h6 class="fw-800 mb-3 d-flex align-items-center gap-2" style="color: #475569; font-size: 0.85rem;">
                                <span style="width: 3px; height: 16px; background: #14b8a6; display: inline-block; border-radius: 10px;"></span>
                                Contact & Location
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-envelope"></i> Email Address</div>
                                        <?php if ($is_editing): ?><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                        <?php else: ?><div class="info-value" style="color: #14b8a6;"><?php echo htmlspecialchars($admin['email']); ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-phone"></i> Mobile Number</div>
                                        <?php if ($is_editing): ?><input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($admin['phone']); ?>" maxlength="11">
                                        <?php else: ?><div class="info-value"><?php echo htmlspecialchars($admin['phone'] ?: 'N/A'); ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-home"></i> Address</div>
                                        <?php if ($is_editing): ?><input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($admin['address']); ?>">
                                        <?php else: ?><div class="info-value text-truncate" title="<?php echo htmlspecialchars($admin['address']); ?>"><?php echo htmlspecialchars($admin['address'] ?: 'Panungyanan, General Trias, Cavite'); ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="info-card">
                                        <div class="section-label"><i class="fas fa-map-pin"></i> Purok</div>
                                        <?php if ($is_editing): ?><input type="text" name="purok" class="form-control" value="<?php echo htmlspecialchars($admin['purok']); ?>">
                                        <?php else: ?><div class="info-value"><?php echo htmlspecialchars($admin['purok'] ?: 'N/A'); ?></div><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($is_editing): ?>
                            <div class="text-center mt-4 pt-3 border-top border-light">
                                <button type="button" class="btn btn-primary btn-sm px-4 rounded-pill shadow-sm fw-800" onclick="confirmUpdate(this)">
                                    <i class="fas fa-save me-1"></i> SAVE MY CHANGES
                                </button>
                                <p class="small text-muted mt-2">Personal profile updates are applied immediately to your administrative session.</p>
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
        title: 'Save Profile Changes?',
        text: 'Are you sure you want to update your administrative account details?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#14b8a6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Update Profile',
        cancelButtonText: 'Review'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
}
</script>

<style>
    .text-teal { color: #14b8a6; } .fw-800 { font-weight: 800; }
</style>

<?php require_once __DIR__ . '/footer.php'; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
