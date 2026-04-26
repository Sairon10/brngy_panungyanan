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
} elseif ($resident['verification_status'] === 'verified') {
    redirect('dashboard.php');
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_id') {
    if (!csrf_validate()) {
        $errors[] = 'Invalid CSRF token. Please refresh the page and try again.';
    } else {
        if (isset($_FILES['id_document']) && $_FILES['id_document']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['id_document'];
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($file['type'], $allowed_types)) {
                $errors[] = 'Only JPEG, PNG, and PDF files are allowed.';
            } elseif ($file['size'] > $max_size) {
                $errors[] = 'File size must be less than 5MB.';
            } elseif (empty(trim($_POST['address_note'] ?? ''))) {
                $errors[] = 'Address on ID is required.';
            } else {
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'id_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
                $upload_path = __DIR__ . '/uploads/id_documents/' . $filename;

                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    // Update resident record with document path
                    $address_note = trim($_POST['address_note']);
                    $stmt = $pdo->prepare('UPDATE residents SET id_document_path = ?, address_on_id = ?, verification_status = \'pending\' WHERE user_id = ?');
                    $stmt->execute([$filename, $address_note, $_SESSION['user_id']]);
                    
                    // Notify all admins
                    $admin_stmt = $pdo->query('SELECT id FROM users WHERE role = "admin"');
                    foreach ($admin_stmt->fetchAll() as $admin) {
                        $pdo->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "verification_update", "New ID Verification Upload", "A resident has uploaded an ID for verification.")')
                            ->execute([$admin['id']]);
                    }
                    
                    $success = 'ID document uploaded successfully. Please wait for admin verification.';

                    // Refresh resident data
                    $stmt = $pdo->prepare('SELECT r.*, u.full_name, u.email FROM residents r JOIN users u ON r.user_id = u.id WHERE r.user_id = ? LIMIT 1');
                    $stmt->execute([$_SESSION['user_id']]);
                    $resident = $stmt->fetch();
                } else {
                    $errors[] = 'Failed to upload file. Please try again.';
                }
            }
        } else {
            $errors[] = 'Please select a valid file to upload.';
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

                                    <div class="mb-4">
                                        <label
                                            class="form-label fw-medium text-dark small text-uppercase letter-spacing-1">ID
                                            Document <span class="text-danger">*</span></label>
                                        <div
                                            class="upload-zone border-2 border-dashed rounded-4 p-5 text-center bg-light transition-all position-relative overflow-hidden group-hover-border-primary">
                                            <input type="file" name="id_document"
                                                class="form-control position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer"
                                                accept="image/*,.pdf" required id="idDocumentInput">
                                            <div class="pointer-events-none" id="uploadPlaceholder">
                                                <div class="mb-3">
                                                    <div class="icon-circle bg-white shadow-sm d-inline-flex align-items-center justify-content-center rounded-circle"
                                                        style="width: 64px; height: 64px;">
                                                        <i class="fas fa-cloud-upload-alt fs-3 text-primary"></i>
                                                    </div>
                                                </div>
                                                <h6 class="fw-bold text-dark mb-1">Click or drag file to upload</h6>
                                                <p class="text-muted small mb-0">Accepted formats: JPEG, PNG, PDF (Max 5MB)
                                                </p>
                                            </div>
                                            <div id="previewContainer" class="d-none position-relative z-1 pointer-events-none">
                                                <div class="preview-img-wrapper mb-3 mx-auto" style="max-width: 300px; max-height: 200px; overflow: hidden; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                                                    <img id="imagePreview" src="#" alt="Preview" class="w-100 h-auto d-none" style="object-fit: contain;">
                                                    <div id="pdfPreview" class="d-none p-4 bg-white rounded-3 shadow-sm">
                                                        <i class="fas fa-file-pdf text-danger display-4 mb-2"></i>
                                                        <p class="mb-0 fw-bold text-dark">PDF Selected</p>
                                                    </div>
                                                </div>
                                                <div id="fileName" class="badge bg-primary-subtle text-primary"></div>
                                            </div>
                                        </div>
                                        <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i> Upload a valid
                                            government-issued ID (Driver's License, Passport, Postal ID, etc.)</div>
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
                                    document.getElementById('idDocumentInput').addEventListener('change', function (e) {
                                        const file = e.target.files[0];
                                        const placeholder = document.getElementById('uploadPlaceholder');
                                        const previewContainer = document.getElementById('previewContainer');
                                        const imagePreview = document.getElementById('imagePreview');
                                        const pdfPreview = document.getElementById('pdfPreview');
                                        const fileNameEl = document.getElementById('fileName');
                                        
                                        if (file) {
                                            placeholder.classList.add('d-none');
                                            previewContainer.classList.remove('d-none');
                                            fileNameEl.textContent = file.name;
                                            
                                            const reader = new FileReader();
                                            
                                            if (file.type.startsWith('image/')) {
                                                reader.onload = function(e) {
                                                    imagePreview.src = e.target.result;
                                                    imagePreview.classList.remove('d-none');
                                                    pdfPreview.classList.add('d-none');
                                                };
                                                reader.readAsDataURL(file);
                                            } else if (file.type === 'application/pdf') {
                                                imagePreview.classList.add('d-none');
                                                pdfPreview.classList.remove('d-none');
                                            }
                                        } else {
                                            placeholder.classList.remove('d-none');
                                            previewContainer.classList.add('d-none');
                                        }
                                    });
                                </script>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <div class="mb-4 text-success display-1 animate__animated animate__bounceIn">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <h3 class="fw-bold text-dark">Identity Verified!</h3>
                                    <p class="text-muted col-lg-8 mx-auto">You have successfully verified your identity. You
                                        now have full access to all resident services and portal features.</p>
                                    <div class="mt-4">
                                        <a href="index.php" class="btn btn-outline-primary rounded-pill px-4 py-2">
                                            <i class="fas fa-home me-2"></i> Go to Dashboard
                                        </a>
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

                            <?php if ($resident['id_document_path']): ?>
                                <hr class="my-4 border-light">
                                <h6 class="fw-bold text-uppercase text-muted small mb-3 letter-spacing-1">Current Upload
                                </h6>
                                <div class="p-3 bg-light rounded-3 border border-light">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="badge bg-white text-dark border shadow-sm">Latest File</span>
                                        <?php if ($resident['verified_at']): ?>
                                            <small
                                                class="text-muted"><?php echo date('M j, Y', strtotime($resident['verified_at'])); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-truncate small text-muted font-monospace mb-2">
                                        <i
                                            class="fas fa-file-alt me-2"></i><?php echo htmlspecialchars(basename($resident['id_document_path'])); ?>
                                    </div>
                                    <?php if (!empty($resident['address_on_id'])): ?>
                                        <div class="small text-muted border-top pt-2">
                                            <i class="fas fa-map-pin me-2 text-secondary"></i>
                                            <span class="fst-italic"><?php echo htmlspecialchars($resident['address_on_id']); ?></span>
                                        </div>
                                    <?php endif; ?>
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

<?php require_once __DIR__ . '/partials/user_dashboard_footer.php'; ?>
