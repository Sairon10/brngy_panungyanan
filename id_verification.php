<?php 
$page_title = 'ID Verification';
require_once __DIR__ . '/partials/user_dashboard_header.php'; 
?>
<?php if (!is_logged_in())
    redirect('login.php'); ?>

<?php
$pdo = get_db_connection();
$errors = [];
$success = '';

// Get current user's resident data
$stmt = $pdo->prepare('SELECT r.*, u.full_name, u.email FROM residents r JOIN users u ON r.user_id = u.id WHERE r.user_id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$resident = $stmt->fetch();

if (!$resident) {
    redirect('index.php');
} elseif (!$resident['is_rbi_completed']) {
    redirect('rbi_form.php');
}


// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_id') {
    if (!csrf_validate()) {
        $errors[] = 'Invalid CSRF token. Please refresh the page and try again.';
    } else {
        $id_front = null;
        $id_back = null;
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $upload_dir = __DIR__ . '/uploads/id_documents/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        // Handle Front ID
        if (isset($_FILES['id_front']) && $_FILES['id_front']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['id_front'];
            if (!in_array($file['type'], $allowed_types)) {
                $errors[] = 'Front ID: Only JPEG, PNG, and PDF files are allowed.';
            } elseif ($file['size'] > $max_size) {
                $errors[] = 'Front ID: File size must be less than 5MB.';
            } else {
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'id_front_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
                if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                    $id_front = $filename;
                } else {
                    $errors[] = 'Failed to upload Front ID.';
                }
            }
        } else {
            $errors[] = 'Front ID is required.';
        }

        // Handle Back ID
        if (isset($_FILES['id_back']) && $_FILES['id_back']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['id_back'];
            if (!in_array($file['type'], $allowed_types)) {
                $errors[] = 'Back ID: Only JPEG, PNG, and PDF files are allowed.';
            } elseif ($file['size'] > $max_size) {
                $errors[] = 'Back ID: File size must be less than 5MB.';
            } else {
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'id_back_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
                if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                    $id_back = $filename;
                } else {
                    $errors[] = 'Failed to upload Back ID.';
                }
            }
        } else {
            $errors[] = 'Back ID is required.';
        }

        if (empty(trim($_POST['address_note'] ?? ''))) {
            $errors[] = 'Address on ID is required.';
        }

        if (empty($errors)) {
            $address_note = trim($_POST['address_note']);
            $stmt = $pdo->prepare('UPDATE residents SET id_front_path = ?, id_back_path = ?, address_on_id = ?, verification_status = \'pending\' WHERE user_id = ?');
            $stmt->execute([$id_front, $id_back, $address_note, $_SESSION['user_id']]);
            
            // Notify all admins
            $admin_stmt = $pdo->query('SELECT id FROM users WHERE role = "admin"');
            foreach ($admin_stmt->fetchAll() as $admin) {
                $pdo->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "verification_update", "New ID Verification Upload", "A resident has uploaded ID (Front & Back) for verification.")')
                    ->execute([$admin['id']]);
            }
            
            $success = 'ID documents uploaded successfully. Please wait for admin verification.';

            // Refresh resident data
            $stmt = $pdo->prepare('SELECT r.*, u.full_name, u.email FROM residents r JOIN users u ON r.user_id = u.id WHERE r.user_id = ? LIMIT 1');
            $stmt->execute([$_SESSION['user_id']]);
            $resident = $stmt->fetch();
        }
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-11">
            <div class="row mb-5 align-items-end">
                <div class="col-md-8">
                    <h2 class="fw-bold text-dark mb-2">ID Verification</h2>
                    <p class="text-muted mb-0 lead">Upload a valid ID document to verify your residency and unlock all
                        features.</p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <span class="badge rounded-pill px-3 py-2
                        <?php
                        switch ($resident['verification_status']) {
                            case 'verified':
                                echo 'bg-success-subtle text-success border border-success-subtle';
                                break;
                            case 'rejected':
                                echo 'bg-danger-subtle text-danger border border-danger-subtle';
                                break;
                            default:
                                echo 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                        }
                        ?>">
                        <i class="fas fa-circle small me-2"></i>
                        Status: <?php echo ucfirst($resident['verification_status']); ?>
                    </span>
                </div>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-danger shadow-sm border-0 rounded-4 mb-4 animate__animated animate__headShake">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle fs-4 text-danger"></i>
                        </div>
                        <div class="ms-3">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $e): ?>
                                    <li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success shadow-sm border-0 rounded-4 mb-4 animate__animated animate__fadeIn">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle fs-4 text-success"></i>
                        </div>
                        <div class="ms-3">
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                            <h5 class="fw-bold text-dark mb-0"><i class="fas fa-upload me-2 text-primary"></i>Upload
                                Document</h5>
                        </div>
                        <div class="card-body p-4">

                            <?php if ($resident['verification_status'] === 'rejected' && $resident['verification_notes']): ?>
                                <div class="alert alert-warning border-0 bg-warning-subtle rounded-3 mb-4">
                                    <div class="d-flex">
                                        <i class="fas fa-exclamation-triangle mt-1 me-3 text-warning-emphasis"></i>
                                        <div>
                                            <strong class="text-warning-emphasis">Verification Notes:</strong>
                                            <p class="mb-0 text-dark mt-1">
                                                <?php echo nl2br(htmlspecialchars($resident['verification_notes'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($resident['verification_status'] !== 'verified'): ?>
                                <form method="post" enctype="multipart/form-data">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="upload_id">

                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium text-dark small text-uppercase letter-spacing-1">Front ID View <span class="text-danger">*</span></label>
                                            <div class="upload-zone border-2 border-dashed rounded-4 p-4 text-center bg-light position-relative overflow-hidden mb-2" style="height: 180px;">
                                                <input type="file" name="id_front" class="form-control position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer" accept="image/*,.pdf" <?php echo !$resident['id_front_path'] ? 'required' : ''; ?> onchange="previewID(this, 'front')">
                                                <div id="front_placeholder" class="d-flex flex-column align-items-center justify-content-center h-100 <?php echo $resident['id_front_path'] ? 'd-none' : ''; ?>">
                                                    <i class="fas fa-id-card fs-2 text-primary mb-2"></i>
                                                    <h6 class="fw-bold text-dark small mb-0">Upload Front</h6>
                                                </div>
                                                <div id="front_preview" class="<?php echo $resident['id_front_path'] ? '' : 'd-none'; ?> h-100 w-100">
                                                    <img src="<?php echo $resident['id_front_path'] ? 'uploads/id_documents/' . $resident['id_front_path'] : '#'; ?>" alt="Front Preview" class="w-100 h-100 object-fit-contain rounded-3">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium text-dark small text-uppercase letter-spacing-1">Back ID View <span class="text-danger">*</span></label>
                                            <div class="upload-zone border-2 border-dashed rounded-4 p-4 text-center bg-light position-relative overflow-hidden mb-2" style="height: 180px;">
                                                <input type="file" name="id_back" class="form-control position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer" accept="image/*,.pdf" <?php echo !$resident['id_back_path'] ? 'required' : ''; ?> onchange="previewID(this, 'back')">
                                                <div id="back_placeholder" class="d-flex flex-column align-items-center justify-content-center h-100 <?php echo $resident['id_back_path'] ? 'd-none' : ''; ?>">
                                                    <i class="fas fa-id-card fs-2 text-primary mb-2" style="transform: scaleX(-1);"></i>
                                                    <h6 class="fw-bold text-dark small mb-0">Upload Back</h6>
                                                </div>
                                                <div id="back_preview" class="<?php echo $resident['id_back_path'] ? '' : 'd-none'; ?> h-100 w-100">
                                                    <img src="<?php echo $resident['id_back_path'] ? 'uploads/id_documents/' . $resident['id_back_path'] : '#'; ?>" alt="Back Preview" class="w-100 h-100 object-fit-contain rounded-3">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label
                                            class="form-label fw-medium text-dark small text-uppercase letter-spacing-1">Address
                                            on ID <span class="text-danger">*</span></label>
                                        <textarea name="address_note"
                                            class="form-control bg-light border-0 rounded-3 p-3 focus-ring" rows="3"
                                            placeholder="Enter the complete address exactly as it appears on your ID document..."
                                            required><?php echo htmlspecialchars($resident['address_on_id'] ?? ''); ?></textarea>
                                        <div class="form-text mt-2"><i class="fas fa-exclamation-triangle me-1 text-warning"></i> 
                                            This is the address printed on your physical ID card. It is used to compare with your registered address for verification purposes.
                                        </div>
                                    </div>

                                    <div class="d-grid pt-2">
                                        <button type="submit"
                                            class="btn btn-primary btn-lg rounded-pill py-3 fw-bold shadow-primary transition-transform hover-scale">
                                            <i class="fas fa-paper-plane me-2"></i> Submit for Verification
                                        </button>
                                    </div>
                                </form>

                                <script>
                                    function previewID(input, side) {
                                        const file = input.files[0];
                                        const placeholder = document.getElementById(side + '_placeholder');
                                        const preview = document.getElementById(side + '_preview');
                                        const previewImg = preview.querySelector('img');
                                        
                                        if (file) {
                                            const reader = new FileReader();
                                            reader.onload = function(e) {
                                                previewImg.src = e.target.result;
                                                placeholder.classList.add('d-none');
                                                preview.classList.remove('d-none');
                                            };
                                            reader.readAsDataURL(file);
                                        } else {
                                            placeholder.classList.remove('d-none');
                                            preview.classList.add('d-none');
                                        }
                                    }
                                </script>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <div class="mb-4 text-success display-1 animate__animated animate__bounceIn">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <h3 class="fw-bold text-dark">Identity Verified!</h3>
                                    <p class="text-muted col-lg-8 mx-auto">You have successfully verified your identity. You
                                        now have full access to all resident services and portal features.</p>
                                    <div class="mt-4 d-flex flex-wrap justify-content-center gap-2">
                                        <a href="dashboard.php" class="btn btn-outline-primary rounded-pill px-4 py-2">
                                            <i class="fas fa-home me-2"></i> Go to Dashboard
                                        </a>
                                        <button type="button" class="btn btn-primary rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#viewMyIDModal">
                                            <i class="fas fa-eye me-2"></i> View My Submitted ID
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-body p-4">
                            <h6 class="fw-bold text-uppercase text-muted small mb-4 letter-spacing-1">Your Information
                            </h6>

                            <div class="d-flex align-items-center mb-4">
                                <div class="avatar-circle bg-primary-subtle text-primary me-3 flex-shrink-0"
                                    style="width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user fs-5"></i>
                                </div>
                                <div class="overflow-hidden">
                                    <small class="text-muted d-block small text-uppercase">Full Name</small>
                                    <span
                                        class="fw-bold text-dark text-truncate d-block"><?php echo htmlspecialchars($resident['full_name']); ?></span>
                                </div>
                            </div>

                            <div class="d-flex align-items-center mb-4">
                                <div class="avatar-circle bg-primary-subtle text-primary me-3 flex-shrink-0"
                                    style="width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-envelope fs-5"></i>
                                </div>
                                <div class="overflow-hidden">
                                    <small class="text-muted d-block small text-uppercase">Email Address</small>
                                    <span
                                        class="fw-bold text-dark text-truncate d-block"><?php echo htmlspecialchars($resident['email']); ?></span>
                                </div>
                            </div>

                            <div class="d-flex align-items-center">
                                <div class="avatar-circle bg-primary-subtle text-primary me-3 flex-shrink-0"
                                    style="width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-map-marker-alt fs-5"></i>
                                </div>
                                <div class="overflow-hidden">
                                    <small class="text-muted d-block small text-uppercase">Registered Address</small>
                                    <span
                                        class="fw-bold text-dark text-truncate d-block"><?php echo htmlspecialchars($resident['address']); ?></span>
                                </div>
                            </div>

                            <?php if ($resident['id_front_path'] || $resident['id_back_path'] || !empty($resident['id_document_path'])): ?>
                                <hr class="my-4 border-light">
                                <h6 class="fw-bold text-uppercase text-muted small mb-3 letter-spacing-1">Current Uploads
                                </h6>
                                 <div class="p-3 bg-light rounded-3 border border-light">
                                     <div class="d-flex align-items-center justify-content-between mb-3">
                                         <span class="badge bg-white text-dark border shadow-sm">Submitted Files</span>
                                         <?php if (isset($resident['verified_at']) && $resident['verified_at']): ?>
                                             <small
                                                 class="text-muted"><?php echo date('M j, Y', strtotime($resident['verified_at'])); ?></small>
                                         <?php endif; ?>
                                     </div>
                                     
                                     <button type="button" class="btn btn-sm btn-outline-primary w-100 rounded-pill" data-bs-toggle="modal" data-bs-target="#viewMyIDModal">
                                         <i class="fas fa-eye me-1"></i> View Details
                                     </button>
                                 </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div
                        class="card border-0 shadow-sm rounded-4 bg-primary text-white overflow-hidden position-relative">
                        <!-- Decorative circle -->
                        <div class="position-absolute top-0 end-0 translate-middle-y me-n4 mt-n4 rounded-circle bg-white opacity-10"
                            style="width: 150px; height: 150px;"></div>

                        <div class="card-body p-4 position-relative z-1">
                            <h5 class="fw-bold mb-4"><i class="fas fa-clipboard-check me-2"></i>Requirements</h5>
                            <div class="d-flex flex-column gap-3">
                                <div class="d-flex align-items-start">
                                    <div class="rounded-circle bg-white bg-opacity-25 p-1 me-3 flex-shrink-0">
                                        <i class="fas fa-check text-white small"></i>
                                    </div>
                                    <span>Valid government-issued ID</span>
                                </div>
                                <div class="d-flex align-items-start">
                                    <div class="rounded-circle bg-white bg-opacity-25 p-1 me-3 flex-shrink-0">
                                        <i class="fas fa-check text-white small"></i>
                                    </div>
                                    <span>Must show current address</span>
                                </div>
                                <div class="d-flex align-items-start">
                                    <div class="rounded-circle bg-white bg-opacity-25 p-1 me-3 flex-shrink-0">
                                        <i class="fas fa-check text-white small"></i>
                                    </div>
                                    <span>Clear, readable color image</span>
                                </div>
                                <div class="d-flex align-items-start">
                                    <div class="rounded-circle bg-white bg-opacity-25 p-1 me-3 flex-shrink-0">
                                        <i class="fas fa-check text-white small"></i>
                                    </div>
                                    <span>Not expired</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Viewing My ID Details -->
<div class="modal fade" id="viewMyIDModal" tabindex="-1" aria-labelledby="viewMyIDModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="viewMyIDModalLabel"><i class="fas fa-id-card me-2 text-primary"></i>My ID Verification Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4 justify-content-center">
                    <div class="col-12 mb-2">
                        <label class="form-label fw-bold small text-uppercase text-muted mb-1"><i class="fas fa-map-marker-alt me-1"></i> Address as written on ID:</label>
                        <div class="p-3 bg-light rounded-3 border fw-semibold text-dark" style="font-size: 0.95rem;">
                            <?php echo !empty($resident['address_on_id']) ? htmlspecialchars($resident['address_on_id']) : '<i class="text-muted fw-normal">No address provided.</i>'; ?>
                        </div>
                    </div>
                    <?php if (!empty($resident['id_front_path'])): ?>
                        <div class="col-md-6 text-center">
                            <label class="form-label fw-bold small text-uppercase text-muted mb-2">Front ID View</label>
                            <img src="uploads/id_documents/<?php echo htmlspecialchars($resident['id_front_path']); ?>" 
                                 class="img-fluid rounded border shadow-sm" style="max-height: 350px; cursor: pointer;"
                                 onclick="window.open(this.src, '_blank')">
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($resident['id_back_path'])): ?>
                        <div class="col-md-6 text-center">
                            <label class="form-label fw-bold small text-uppercase text-muted mb-2">Back ID View</label>
                            <img src="uploads/id_documents/<?php echo htmlspecialchars($resident['id_back_path']); ?>" 
                                 class="img-fluid rounded border shadow-sm" style="max-height: 350px; cursor: pointer;"
                                 onclick="window.open(this.src, '_blank')">
                        </div>
                    <?php endif; ?>
                    <?php if (empty($resident['id_front_path']) && empty($resident['id_back_path']) && !empty($resident['id_document_path'])): ?>
                        <div class="col-12 text-center">
                            <label class="form-label fw-bold small text-uppercase text-muted mb-2">ID Document (Legacy Format)</label>
                            <img src="uploads/id_documents/<?php echo htmlspecialchars($resident['id_document_path']); ?>" 
                                 class="img-fluid rounded border shadow-sm" style="max-height: 350px; cursor: pointer;"
                                 onclick="window.open(this.src, '_blank')">
                        </div>
                    <?php endif; ?>
                    <div class="col-12 mt-3 text-center">
                        <div class="alert alert-info border-0 rounded-3 small py-2 mb-0">
                            <i class="fas fa-info-circle me-1"></i> Tip: Click on an image to view it in full size.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/user_dashboard_footer.php'; ?>
