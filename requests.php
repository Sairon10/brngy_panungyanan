<?php
require_once __DIR__ . '/config.php';
if (!is_logged_in())
    redirect('login.php');

// Check if user is verified and active (for residents only)
if ($_SESSION['role'] === 'resident') {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT verification_status, is_active FROM users u LEFT JOIN residents r ON r.user_id = u.id WHERE u.id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();

    // Check verification
    if ($user_data && $user_data['verification_status'] !== 'verified') {
        redirect('id_verification.php');
    }

    // Check active status
    $is_account_active = (bool) ($user_data['is_active'] ?? true);
} else {
    $is_account_active = true;
}

$page_title = 'Documents';

$pdo = get_db_connection();

$message = $_SESSION['info'] ?? '';
unset($_SESSION['info']);

// Get active document types
$document_types = [];
try {
    $doc_types_stmt = $pdo->query('
        SELECT * FROM document_types 
        WHERE is_active = 1 
        ORDER BY display_order ASC, name ASC
    ');
    $document_types = $doc_types_stmt->fetchAll();
} catch (PDOException $e) {
    // If table doesn't exist, use fallback
    $document_types = [];
}

// Define available purposes for Indigency certificates
$indigency_purposes_list = [
    'Financial/Medical Assistance',
    'Burial Assistance',
    'Senior Citizen Social Pension',
    'Vaccination Requirements',
    'Educational Assistance',
    'Other\'s'
];

// Define available purposes for Clearance certificates
$clearance_purposes_list = [
    'Local Employment',
    'Postal ID Application',
    'Medical/Financial Assistance',
    'Bank Requirements',
    'Scholarship Program',
    'Water/Electric Connection',
    'Educational Assistance',
    'Other\'s'
];

// Fetch user's active AND VERIFIED family members for "Request For" selector
$fm_stmt = $pdo->prepare('SELECT * FROM family_members WHERE user_id = ? AND is_active = 1 AND verification_status = "verified" ORDER BY full_name ASC');
$fm_stmt->execute([$_SESSION['user_id']]);
$family_members = $fm_stmt->fetchAll();

// Also check if there are pending ones to show a notice
$pending_fm_stmt = $pdo->prepare('SELECT COUNT(*) FROM family_members WHERE user_id = ? AND is_active = 1 AND verification_status != "verified"');
$pending_fm_stmt->execute([$_SESSION['user_id']]);
$pending_fm_count = $pending_fm_stmt->fetchColumn();

// Handle form submission
$was_cancel = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $message = 'Invalid session. Please reload and try again.';
    } else if (isset($_POST['action']) && $_POST['action'] === 'cancel') {
        $req_id = (int) ($_POST['req_id'] ?? 0);
        $req_type = $_POST['req_type'] ?? '';

        if ($req_id && $req_type) {
            $cancel_reason = trim($_POST['cancel_reason'] ?? '');
            if ($req_type === 'clearance') {
                $pdo->prepare("UPDATE barangay_clearances SET status = 'canceled', notes = ? WHERE id = ? AND user_id = ? AND status = 'pending'")
                    ->execute([$cancel_reason, $req_id, $_SESSION['user_id']]);
            } else if ($req_type === 'document') {
                $pdo->prepare("UPDATE document_requests SET status = 'canceled', notes = ? WHERE id = ? AND user_id = ? AND status = 'pending'")
                    ->execute([$cancel_reason, $req_id, $_SESSION['user_id']]);
            }
            $_SESSION['info'] = 'Request successfully canceled.';
            $_SESSION['was_cancel'] = true;
            header("Location: requests.php?status_filter=all");
            exit;
        }
    } else if (isset($_POST['action']) && $_POST['action'] === 'refund') {
        $req_id = (int) ($_POST['req_id'] ?? 0);
        $req_type = $_POST['req_type'] ?? '';

        if ($req_id && $req_type) {
            $refund_number = trim($_POST['refund_number'] ?? '');
            $refund_notes = trim($_POST['refund_notes'] ?? '');
            if ($req_type === 'clearance') {
                $pdo->prepare("UPDATE barangay_clearances SET payment_status = 'refund_pending', status = 'canceled', notes = ?, refund_number = ?, refund_notes = ? WHERE id = ? AND user_id = ? AND payment_status IN ('pending', 'confirmed')")
                    ->execute([$refund_notes, $refund_number, $refund_notes, $req_id, $_SESSION['user_id']]);
            } else if ($req_type === 'document') {
                $pdo->prepare("UPDATE document_requests SET payment_status = 'refund_pending', status = 'canceled', notes = ?, refund_number = ?, refund_notes = ? WHERE id = ? AND user_id = ? AND payment_status IN ('pending', 'confirmed')")
                    ->execute([$refund_notes, $refund_number, $refund_notes, $req_id, $_SESSION['user_id']]);
            }
            $_SESSION['info'] = 'Refund request submitted successfully.';
            $_SESSION['was_cancel'] = false;
            header("Location: requests.php?status_filter=all");
            exit;
        }
    } else {
        $doc_type = trim($_POST['doc_type'] ?? '');
        $purpose = trim($_POST['purpose'] ?? '');

        // Get selected purpose for Indigency certificates (single selection only)
        $indigency_purpose = trim($_POST['indigency_purpose'] ?? '');

        // Get selected purpose for Clearance (single selection only)
        $clearance_purpose = trim($_POST['clearance_purpose'] ?? '');

        // If the document type uses a preset radio button, assign it to $purpose so validation passes
        if (stripos($doc_type, 'Indigency') !== false && $indigency_purpose !== '') {
            $purpose = $indigency_purpose;
        } else if (($doc_type === 'Barangay Clearance' || stripos($doc_type, 'clearance') !== false) && $clearance_purpose !== '') {
            $purpose = $clearance_purpose;
        }

        if ($doc_type !== '' && $purpose !== '') {
            // Check if this document type requires special handling
            $doc_type_info = null;
            foreach ($document_types as $dt) {
                if ($dt['name'] === $doc_type) {
                    $doc_type_info = $dt;
                    break;
                }
            }

            // If not found in database, check if it's Barangay Clearance (backward compatibility)
            if (!$doc_type_info && $doc_type === 'Barangay Clearance') {
                $requires_special_handling = true;
                $requires_validity = true;
            } else {
                $requires_special_handling = $doc_type_info['requires_special_handling'] ?? false;
                $requires_validity = $doc_type_info['requires_validity'] ?? false;
            }

            // Common requestor info initialization for all document types
            $requestor_type = $_POST['requestor_type'] ?? 'self';
            $family_member_id = ($requestor_type === 'family_member') ? (int) ($_POST['family_member_id'] ?? 0) : null;

            // Handle optional e-wallet reference number and amount paid
            $payment_reference_no = trim($_POST['payment_reference_no'] ?? '');
            $payment_amount_paid  = !empty($_POST['payment_amount_paid']) ? (float)$_POST['payment_amount_paid'] : null;

            // Handle optional GCash payment receipt upload
            $payment_receipt_path = null;
            if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['payment_receipt'];
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                $maxSize = 5 * 1024 * 1024; // 5MB

                if (!in_array($file['type'], $allowedTypes)) {
                    $message = 'Error: Only JPG, JPEG, PNG, and WEBP images are allowed for receipts.';
                    goto skip_request;
                } elseif ($file['size'] > $maxSize) {
                    $message = 'Error: Receipt image size must not exceed 5MB.';
                    goto skip_request;
                } else {
                    $uploadDir = __DIR__ . '/uploads/receipts/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'receipt_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
                    $uploadPath = $uploadDir . $filename;

                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        $payment_receipt_path = 'uploads/receipts/' . $filename;
                    } else {
                        $message = 'Error: Failed to upload receipt. Please try again.';
                        goto skip_request;
                    }
                }
            }

            // Critical security check: Is the family member active, belongs to the user, and VERIFIED?
            if ($family_member_id) {
                $fm_check = $pdo->prepare('SELECT id, verification_status FROM family_members WHERE id = ? AND user_id = ? AND is_active = 1 LIMIT 1');
                $fm_check->execute([$family_member_id, $_SESSION['user_id']]);
                $fm_data = $fm_check->fetch();
                if (!$fm_data) {
                    $message = 'Error: Selected family member is inactive or unauthorized.';
                    goto skip_request;
                }
                if ($fm_data['verification_status'] !== 'verified') {
                    $message = 'Error: Selected family member is not yet verified. Please wait for admin approval.';
                    goto skip_request;
                }

                if (stripos($doc_type, 'Resident ID') !== false) {
                    $message = 'Error: Resident IDs can only be requested for the account owner. Family members must create their own accounts.';
                    goto skip_request;
                }
            }

            // Duplicate reference number check — block if ref no. already used
            if (!empty($payment_reference_no)) {
                $dup1 = $pdo->prepare("SELECT id FROM barangay_clearances WHERE payment_reference_no = ? AND payment_status NOT IN ('rejected','refunded') LIMIT 1");
                $dup1->execute([$payment_reference_no]);
                $dup2 = $pdo->prepare("SELECT id FROM document_requests WHERE payment_reference_no = ? AND payment_status NOT IN ('rejected','refunded') LIMIT 1");
                $dup2->execute([$payment_reference_no]);
                if ($dup1->fetch() || $dup2->fetch()) {
                    $message = 'Error: This reference number has already been used for another payment. Please check your receipt.';
                    goto skip_request;
                }
            }

            if ($requires_special_handling) {
                // Handle special document types (like Barangay Clearance)
                $db_months = isset($doc_type_info['validity_months']) ? (int)$doc_type_info['validity_months'] : 1;
                $validity_days = $requires_validity ? ($db_months * 30) : 30;

                // Generate unique clearance number
                $year = date('Y');
                $user_id_padded = str_pad((string) $_SESSION['user_id'], 6, '0', STR_PAD_LEFT);

                // Get count of existing clearances for this user this year
                $count_stmt = $pdo->prepare('
                    SELECT COUNT(*) as count 
                    FROM barangay_clearances 
                    WHERE user_id = ? AND YEAR(created_at) = ?
                ');
                $count_stmt->execute([$_SESSION['user_id'], $year]);
                $count_result = $count_stmt->fetch();
                $sequence = (int) $count_result['count'] + 1;

                // Generate clearance number with sequence
                $clearance_number = 'BC-' . $year . '-' . $user_id_padded . '-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

                // Double-check for uniqueness (in case of race condition)
                $max_attempts = 10;
                $attempt = 0;
                while ($attempt < $max_attempts) {
                    $check_stmt = $pdo->prepare('SELECT COUNT(*) as count FROM barangay_clearances WHERE clearance_number = ?');
                    $check_stmt->execute([$clearance_number]);
                    $exists = $check_stmt->fetch();

                    if ($exists['count'] == 0) {
                        break; // Clearance number is unique
                    }
                    $attempt++;
                    $sequence++;
                    $clearance_number = 'BC-' . $year . '-' . $user_id_padded . '-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
                }

                $stmt = $pdo->prepare('INSERT INTO barangay_clearances (user_id, clearance_number, purpose, validity_days, status, payment_receipt, payment_reference_no, payment_amount_paid) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$_SESSION['user_id'], $clearance_number, $purpose, $validity_days, 'pending', $payment_receipt_path, $payment_reference_no ?: null, $payment_amount_paid]);

                $clearance_id = $pdo->lastInsertId();
                if ($family_member_id) {
                    $pdo->prepare('UPDATE barangay_clearances SET family_member_id=?, requestor_type=? WHERE id=?')
                        ->execute([$family_member_id, 'family_member', $clearance_id]);
                }

                // Notify all admins
                $admin_stmt = $pdo->query('SELECT id FROM users WHERE role = "admin"');
                foreach ($admin_stmt->fetchAll() as $admin) {
                    $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_request_id) VALUES (?, "request_update", "New Document Request", ?, ?)')
                        ->execute([$admin['id'], "A new Barangay Clearance request has been submitted by a resident.", $clearance_id]);
                }

                $message = 'Document request submitted successfully!';
            } else {
                // Check if it's an Indigency document
                $is_indigency_doc = (stripos($doc_type, 'Indigency') !== false);

                if ($is_indigency_doc && !empty($indigency_purpose)) {
                    // Save with selected indigency purpose
                    $pdo->prepare('INSERT INTO document_requests (user_id, doc_type, purpose, indigency_purposes, family_member_id, requestor_type, payment_receipt, payment_reference_no, payment_amount_paid) VALUES (?,?,?,?,?,?,?,?,?)')
                        ->execute([$_SESSION['user_id'], $doc_type, $purpose, $indigency_purpose, $family_member_id ?: null, $family_member_id ? 'family_member' : 'self', $payment_receipt_path, $payment_reference_no ?: null, $payment_amount_paid]);
                } else {
                    // Save with text purpose
                    $pdo->prepare('INSERT INTO document_requests (user_id, doc_type, purpose, family_member_id, requestor_type, payment_receipt, payment_reference_no, payment_amount_paid) VALUES (?,?,?,?,?,?,?,?)')
                        ->execute([$_SESSION['user_id'], $doc_type, $purpose, $family_member_id ?: null, $family_member_id ? 'family_member' : 'self', $payment_receipt_path, $payment_reference_no ?: null, $payment_amount_paid]);
                }

                $request_id = $pdo->lastInsertId();
                // Notify all admins
                $admin_stmt = $pdo->query('SELECT id FROM users WHERE role = "admin"');
                foreach ($admin_stmt->fetchAll() as $admin) {
                    $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_request_id) VALUES (?, "request_update", "New Document Request", ?, ?)')
                        ->execute([$admin['id'], "A new {$doc_type} request has been submitted by a resident.", $request_id]);
                }

                $message = 'Document request submitted successfully!';
            }
        }
    }
}
skip_request:

require_once __DIR__ . '/partials/user_dashboard_header.php';
?>

<?php
// Get user's clearance requests
$clearances_stmt = $pdo->prepare('
    SELECT bc.*, u.full_name AS user_name, fm.full_name AS fm_name, fm.is_pwd AS fm_is_pwd, fm.is_senior AS fm_is_senior
    FROM barangay_clearances bc 
    JOIN users u ON u.id = bc.user_id 
    LEFT JOIN family_members fm ON bc.family_member_id = fm.id
    WHERE bc.user_id = ? 
    ORDER BY bc.created_at DESC
');
$clearances_stmt->execute([$_SESSION['user_id']]);
$clearances = $clearances_stmt->fetchAll();

// Get user's document requests with family member info
$documents_stmt = $pdo->prepare('
    SELECT dr.*, u.full_name AS user_name, fm.full_name AS fm_name, fm.is_pwd AS fm_is_pwd, fm.is_senior AS fm_is_senior 
    FROM document_requests dr 
    JOIN users u ON u.id = dr.user_id
    LEFT JOIN family_members fm ON dr.family_member_id = fm.id 
    WHERE dr.user_id = ? 
    ORDER BY dr.id DESC
');
$documents_stmt->execute([$_SESSION['user_id']]);
$documents = $documents_stmt->fetchAll();
?>

<!-- Request Document Modal -->
<div class="modal fade" id="requestDocumentModal" tabindex="-1" aria-labelledby="requestDocumentModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div
                        class="width-12 height-12 rounded-3 bg-teal-50 text-teal-600 d-flex align-items-center justify-content-center">
                        <i class="fas fa-file-signature fa-lg"></i>
                    </div>
                    <h4 class="fw-bold mb-0 text-dark">Request Document</h4>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form method="post" id="requestForm" enctype="multipart/form-data" onsubmit="return validatePurpose()">
                    <?php echo csrf_field(); ?>

                    <?php if (!$is_account_active): ?>
                        <div class="alert alert-danger border-0 bg-rose-50 text-rose-600 rounded-3 mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Account Inactive:</strong> Your account is currently deactivated. You cannot submit new
                            requests at this time.
                        </div>
                    <?php endif; ?>
                    <div id="form_step_1">
                        <!-- Request For selector (first field) -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark opacity-50 small text-uppercase">Request
                                For</label>
                            <input type="hidden" name="requestor_type" id="requestor_type_hidden" value="self">
                            <input type="hidden" name="family_member_id" id="family_member_id_hidden" value="">
                            <select id="request_for_select" class="form-select form-select-lg bg-light border-0" required
                                onchange="handleRequestForChange(this)">
                                <option value="self">
                                    <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Account Owner'); ?> — Owner
                                </option>
                                <?php if (!empty($family_members)): ?>
                                    <?php foreach ($family_members as $fm): ?>
                                        <option value="<?php echo $fm['id']; ?>">
                                            <?php echo htmlspecialchars($fm['full_name']); ?> — Family Member
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <option value="add_new_fm" class="text-primary fw-bold">+ Add Family Member</option>
                            </select>
                            <?php if ($pending_fm_count > 0): ?>
                                <div class="form-text small text-amber-600 mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <?php echo $pending_fm_count; ?> family member(s) are hidden because they are still pending
                                    verification.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark opacity-50 small text-uppercase">Document
                                Type</label>
                            <select name="doc_type" id="doc_type" class="form-select form-select-lg bg-light border-0"
                                required>
                                <option value="">Select Document...</option>
                                <?php if (empty($document_types)): ?>
                                    <option value="Barangay Clearance" data-price="0">Barangay Clearance</option>
                                    <option value="Certificate of Residency" data-price="0">Certificate of Residency</option>
                                    <option value="Indigency" data-price="0">Indigency</option>
                                    <option value="Resident ID" data-price="0">Resident ID</option>
                                <?php else: ?>
                                    <?php foreach ($document_types as $dt): ?>
                                        <option value="<?php echo htmlspecialchars($dt['name']); ?>"
                                            data-requires-special="<?php echo $dt['requires_special_handling'] ? '1' : '0'; ?>"
                                            data-price="<?php echo isset($dt['price']) ? htmlspecialchars($dt['price']) : '0'; ?>">
                                            <?php echo htmlspecialchars($dt['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="mb-3" id="document_price_container" style="display: none;">
                            <label class="form-label fw-semibold text-dark opacity-50 small text-uppercase">Price</label>
                            <div class="p-3 bg-light rounded text-success fs-5 fw-bold border" id="document_price_display">
                                Free
                            </div>
                        </div>

                        <!-- Purpose Selection for Indigency -->
                        <div class="mb-3" id="indigency_purpose_field" style="display: none;">
                            <label class="form-label fw-semibold text-dark opacity-50 small text-uppercase">Select
                                Purpose:</label>
                            <div class="border rounded p-3 bg-light">
                                <div class="d-flex flex-column gap-2">
                                    <?php foreach ($indigency_purposes_list as $purpose): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="indigency_purpose"
                                                value="<?php echo htmlspecialchars($purpose); ?>"
                                                id="indigency_purpose_<?php echo md5($purpose); ?>">
                                            <label class="form-check-label"
                                                for="indigency_purpose_<?php echo md5($purpose); ?>">
                                                <?php echo htmlspecialchars($purpose); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Purpose Selection for Clearance -->
                        <div class="mb-3" id="clearance_purpose_field" style="display: none;">
                            <label class="form-label fw-semibold text-dark opacity-50 small text-uppercase">Select
                                Purpose:</label>
                            <div class="border rounded p-3 bg-light">
                                <div class="d-flex flex-column gap-2">
                                    <?php foreach ($clearance_purposes_list as $purpose): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="clearance_purpose"
                                                value="<?php echo htmlspecialchars($purpose); ?>"
                                                id="clearance_purpose_<?php echo md5($purpose); ?>">
                                            <label class="form-check-label"
                                                for="clearance_purpose_<?php echo md5($purpose); ?>">
                                                <?php echo htmlspecialchars($purpose); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4" id="purpose_text_field">
                            <label class="form-label fw-semibold text-dark opacity-50 small text-uppercase">Purpose</label>
                            <textarea name="purpose" class="form-control bg-light border-0" rows="4"
                                placeholder="State your purpose..." id="purpose_textarea"></textarea>
                        </div>
                    </div>

                    <div id="form_step_2" style="display: none;">
                        <!-- E-Wallet Payment (Optional) Section -->
                        <!-- Tesseract.js for OCR -->
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>

    <style>
                            .border-dashed {
                                border-style: dashed !important;
                                border-width: 2px !important;
                                border-color: #0d9488 !important; /* teal-600 */
                                transition: all 0.2s ease-in-out;
                            }
                            .border-dashed:hover {
                                background-color: #f0fdfa !important; /* light teal */
                                border-color: #0f766e !important;
                            }
                            .cursor-pointer {
                                cursor: pointer !important;
                            }
                        </style>
                        <div id="payment_section" style="display: none;" class="mb-4">
                            <hr class="my-4 opacity-10">
                            <div class="fw-bold text-dark opacity-75 mb-1 fs-5">E-Wallet Payment (Optional)</div>
                            <p class="text-secondary small mb-3">Scan QR code and upload receipt to complete payment</p>
                            
                            <div class="row g-3 mb-3">
                                <!-- Amount Due Box -->
                                <div class="col-6">
                                    <div class="p-3 bg-light rounded-3 border text-center h-100 d-flex flex-column justify-content-center">
                                        <span class="text-secondary small fw-semibold text-uppercase d-block mb-1">Amount Due</span>
                                        <span id="amount_due_display" class="fw-bold text-dark fs-6">₱ 0.00</span>
                                    </div>
                                </div>
                                <!-- Amount Paid Box -->
                                <div class="col-6">
                                    <div class="p-3 bg-light rounded-3 border text-center h-100 d-flex flex-column justify-content-center">
                                        <span class="text-secondary small fw-semibold text-uppercase d-block mb-1">Amount Paid</span>
                                        <span id="amount_paid_display" class="fw-bold text-teal-600 fs-5">PHP 0.00</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Scan QR Code Button -->
                            <div class="text-center mb-3">
                                <button type="button" class="btn btn-link text-teal-600 text-decoration-none fw-semibold small d-inline-flex align-items-center gap-1 shadow-none p-0" id="btn_toggle_qr">
                                    <i class="fas fa-qrcode"></i> Scan QR Code
                                </button>
                            </div>

                            <!-- Upload Receipt Box -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark opacity-50 small text-uppercase">Upload Receipt</label>
                                <div class="border border-dashed rounded-3 p-4 text-center cursor-pointer position-relative bg-light" id="receipt_dropzone" onclick="document.getElementById('payment_receipt').click();">
                                    <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                                        <div class="rounded-circle bg-teal-50 text-teal-600 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                            <i class="fas fa-upload fa-lg"></i>
                                        </div>
                                        <div class="fw-semibold text-dark" id="upload_status">Upload Receipt</div>
                                        <div class="text-secondary small">PNG, JPG, WEBP - Max size: 5MB</div>
                                    </div>
                                    <input type="file" name="payment_receipt" id="payment_receipt" class="d-none" accept="image/png, image/jpeg, image/jpg, image/webp" onchange="handleReceiptSelected(this)">
                                </div>
                            </div>

                            <!-- OCR Scanning Status -->
                            <div id="ocr_scan_status" class="d-none mb-3">
                                <div class="d-flex align-items-center gap-2 p-3 rounded-3 bg-light border">
                                    <div class="spinner-border spinner-border-sm text-teal-600" id="ocr_spinner"></div>
                                    <span class="small text-secondary" id="ocr_status_text">Scanning receipt for reference number...</span>
                                </div>
                            </div>

                            <!-- Reference Number Field -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark opacity-50 small text-uppercase d-flex align-items-center gap-2">
                                    Reference No.
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="fas fa-hashtag text-teal-600" style="font-size:.85rem;"></i>
                                    </span>
                                    <input type="text" name="payment_reference_no" id="payment_reference_no"
                                        class="form-control border-start-0 ps-1 rounded-end-3 bg-white"
                                        placeholder="Upload receipt above"
                                        maxlength="100" readonly>
                                </div>
                                <div class="text-muted mt-1" style="font-size:.75rem;">
                                    <i class="fas fa-shield-alt me-1 text-teal-600"></i>
                                    Strictly extracted from your uploaded receipt for security.
                                </div>
                            </div>

                            <!-- Amount Paid Field -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark opacity-50 small text-uppercase d-flex align-items-center gap-2">
                                    Amount Paid (₱)
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">₱</span>
                                    <input type="text" name="payment_amount_paid" id="payment_amount_paid"
                                        class="form-control border-start-0 ps-1 rounded-end-3 bg-white"
                                        placeholder="0.00" readonly>
                                </div>
                                <div class="text-muted mt-1" style="font-size:.75rem;">
                                    <i class="fas fa-shield-alt me-1 text-teal-600"></i>
                                    Strictly extracted from your uploaded receipt for security.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stepped Navigation Buttons -->
                    <div class="d-flex gap-3 mt-4">
                        <button type="button" class="btn btn-light rounded-pill px-4 w-50" id="btn_prev_step" style="display: none;">
                            <i class="fas fa-arrow-left me-2"></i> Back
                        </button>
                        <button type="button" class="btn btn-primary rounded-pill px-4 w-100" id="btn_next_step" style="display: none;">
                            Next <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                        <button class="btn btn-primary rounded-pill px-4 w-100" type="submit" id="btn_submit_request" <?php echo !$is_account_active ? 'disabled' : ''; ?>>
                            <i class="fas fa-paper-plane me-2"></i> Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 animate__animated animate__fadeInUp">
    <!-- History Section (Full Width) -->
    <div class="col-lg-12">
        <div class="card shadow-sm border-0 rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
                    <div
                        class="width-12 height-12 rounded-3 bg-amber-50 text-amber-600 d-flex align-items-center justify-content-center">
                        <i class="fas fa-history fa-lg"></i>
                    </div>
                    <h4 class="fw-bold mb-0 text-dark">Request History</h4>
                    <div class="ms-auto flex-shrink-0 d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal"
                            data-bs-target="#requestDocumentModal">
                            <i class="fas fa-plus me-2"></i>Request Documents
                        </button>
                        <form method="GET" class="m-0">
                            <select name="status_filter"
                                class="form-select border-0 bg-transparent fw-semibold text-primary shadow-none ps-0 font-monospace"
                                style="outline: none; cursor: pointer; text-align: right;"
                                onchange="this.form.submit()">
                                <option value="all" <?php echo empty($_GET['status_filter']) || $_GET['status_filter'] === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="pending" <?php echo isset($_GET['status_filter']) && $_GET['status_filter'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo isset($_GET['status_filter']) && $_GET['status_filter'] === 'approved' ? 'selected' : ''; ?>>Ready to Pick Up</option>
                                <option value="released" <?php echo isset($_GET['status_filter']) && $_GET['status_filter'] === 'released' ? 'selected' : ''; ?>>Released</option>
                                <option value="canceled" <?php echo isset($_GET['status_filter']) && $_GET['status_filter'] === 'canceled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </form>
                    </div>
                </div>

                <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-3 ps-4 rounded-start" style="width: 50px;">#</th>
                                <th class="py-3">Name</th>
                                <th class="py-3">Type</th>
                                <th class="py-3">Status</th>
                                <th class="py-3">Date</th>
                                <th class="py-3 pe-4 rounded-end text-center" style="width: 100px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $all_requests = [];
                            foreach ($clearances as $c) {
                                $all_requests[] = [
                                    'type' => 'clearance',
                                    'id' => $c['id'],
                                    'number' => $c['clearance_number'],
                                    'purpose' => $c['purpose'],
                                    'status' => $c['status'],
                                    'date' => $c['created_at'],
                                    'validity' => $c['validity_days'],
                                    'notes' => $c['notes'] ?? null,
                                    'fm_name' => $c['fm_name'] ?? null,
                                    'fm_is_pwd' => $c['fm_is_pwd'] ?? 0,
                                    'fm_is_senior' => $c['fm_is_senior'] ?? 0,
                                    'user_name' => $c['user_name'] ?? '',
                                    'payment_receipt' => $c['payment_receipt'] ?? null,
                                    'payment_status' => $c['payment_status'] ?? 'pending',
                                    'payment_amount_paid' => $c['payment_amount_paid'] ?? null,
                                    'refund_number' => $c['refund_number'] ?? null,
                                    'refund_notes' => $c['refund_notes'] ?? null,
                                    'refund_receipt' => $c['refund_receipt'] ?? null,
                                    'admin_refund_number' => $c['admin_refund_number'] ?? null,
                                    'admin_refund_notes' => $c['admin_refund_notes'] ?? null
                                ];
                            }
                            foreach ($documents as $d) {
                                $all_requests[] = [
                                    'type' => 'document',
                                    'id' => $d['id'],
                                    'doc_type' => $d['doc_type'],
                                    'purpose' => $d['purpose'],
                                    'status' => $d['status'],
                                    'date' => $d['created_at'],
                                    'notes' => $d['notes'] ?? null,
                                    'fm_name' => $d['fm_name'] ?? null,
                                    'fm_is_pwd' => $d['fm_is_pwd'] ?? 0,
                                    'fm_is_senior' => $d['fm_is_senior'] ?? 0,
                                    'user_name' => $d['user_name'] ?? '',
                                    'payment_receipt' => $d['payment_receipt'] ?? null,
                                    'payment_status' => $d['payment_status'] ?? 'pending',
                                    'payment_amount_paid' => $d['payment_amount_paid'] ?? null,
                                    'refund_number' => $d['refund_number'] ?? null,
                                    'refund_notes' => $d['refund_notes'] ?? null,
                                    'refund_receipt' => $d['refund_receipt'] ?? null,
                                    'admin_refund_number' => $d['admin_refund_number'] ?? null,
                                    'admin_refund_notes' => $d['admin_refund_notes'] ?? null
                                ];
                            }

                            // Filter by status wrapper BEFORE pagination
                            $status_filter = $_GET['status_filter'] ?? 'all';
                            if ($status_filter !== 'all') {
                                $all_requests = array_filter($all_requests, function ($r) use ($status_filter) {
                                    return strtolower($r['status']) === strtolower($status_filter);
                                });
                            }
                            // Sort by date descending
                            usort($all_requests, function ($a, $b) {
                                return strtotime($b['date']) - strtotime($a['date']);
                            });

                            // Pagination logic
                            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
                            $per_page = 10;
                            $total_requests = count($all_requests);
                            $total_pages = ceil($total_requests / $per_page);
                            if ($total_pages > 0 && $page > $total_pages) {
                                $page = $total_pages;
                            }
                            $offset = ($page - 1) * $per_page;
                            $paginated_requests = array_slice($all_requests, $offset, $per_page);
                            $row_number = $offset + 1;
                            ?>
                            <?php if (empty($paginated_requests)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="text-dark opacity-50 mb-2">
                                            <i class="fas fa-folder-open fa-3x"></i>
                                        </div>
                                        <p class="text-dark opacity-75 small mb-0">No requests found.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($paginated_requests as $req): ?>
                                    <?php
                                    $is_fm = !empty($req['fm_name']);
                                    $requesterName = $is_fm ? $req['fm_name'] : $req['user_name'];
                                    $displayDocType = ($req['type'] === 'clearance') ? 'Barangay Clearance' : ($req['doc_type'] ?? '');

                                    $statusClass = 'bg-secondary bg-opacity-10 text-secondary';
                                    $statusLabel = ucfirst($req['status']);
                                    $icon = 'fa-circle';

                                    switch ($req['status']) {
                                        case 'approved':
                                            $statusClass = 'bg-teal-50 text-teal-600';
                                            $statusLabel = 'Ready to Pick Up';
                                            $icon = 'fa-box-open';
                                            break;
                                        case 'pending':
                                            $statusClass = 'bg-amber-50 text-amber-600';
                                            $statusLabel = 'Pending';
                                            $icon = 'fa-clock';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'bg-rose-50 text-rose-600';
                                            $statusLabel = 'Rejected';
                                            $icon = 'fa-times-circle';
                                            break;
                                        case 'released':
                                            $statusClass = 'bg-blue-50 text-blue-600';
                                            $statusLabel = 'Released';
                                            $icon = 'fa-check-double';
                                            break;
                                        case 'canceled':
                                            $statusClass = 'bg-secondary bg-opacity-10 text-secondary';
                                            $statusLabel = 'Cancelled';
                                            $icon = 'fa-ban';
                                            break;
                                    }
                                    ?>
                                    <tr>
                                        <!-- # -->
                                        <td class="ps-4 text-dark opacity-50 fw-semibold"><?php echo $row_number++; ?></td>
                                        <!-- Name -->
                                        <td>
                                            <div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($requesterName); ?>
                                            </div>
                                        </td>
                                        <!-- Type -->
                                        <td>
                                            <span class="text-dark"><?php echo htmlspecialchars($displayDocType); ?></span>
                                        </td>
                                        <!-- Status -->
                                        <td>
                                            <div role="button"
                                                class="badge <?php echo $statusClass; ?> rounded-pill px-3 py-2 border border-0 btn-view-detail mx-auto"
                                                style="cursor: pointer; display: inline-block;"
                                                data-doc="<?php echo htmlspecialchars($displayDocType, ENT_QUOTES); ?>"
                                                data-requester="<?php echo htmlspecialchars($requesterName, ENT_QUOTES); ?>"
                                                data-requester-type="<?php echo $is_fm ? 'Family Member' : 'Owner'; ?>"
                                                data-purpose="<?php echo htmlspecialchars($req['purpose'] ?? 'N/A', ENT_QUOTES); ?>"
                                                data-status-label="<?php echo htmlspecialchars($statusLabel, ENT_QUOTES); ?>"
                                                data-status-class="<?php echo htmlspecialchars($statusClass, ENT_QUOTES); ?>"
                                                data-icon="<?php echo htmlspecialchars($icon, ENT_QUOTES); ?>"
                                                data-date="<?php echo date('F d, Y', strtotime($req['date'])); ?>"
                                                data-notes="<?php echo htmlspecialchars($req['notes'] ?? '', ENT_QUOTES); ?>"
                                                data-payment-receipt="<?php echo htmlspecialchars($req['payment_receipt'] ?? '', ENT_QUOTES); ?>"
                                                data-payment-status="<?php echo htmlspecialchars($req['payment_status'] ?? 'pending', ENT_QUOTES); ?>"
                                                data-refund-number="<?php echo htmlspecialchars($req['refund_number'] ?? '', ENT_QUOTES); ?>"
                                                data-refund-notes="<?php echo htmlspecialchars($req['refund_notes'] ?? '', ENT_QUOTES); ?>"
                                                data-refund-receipt="<?php echo htmlspecialchars($req['refund_receipt'] ?? '', ENT_QUOTES); ?>"
                                                data-admin-refund-number="<?php echo htmlspecialchars($req['admin_refund_number'] ?? '', ENT_QUOTES); ?>"
                                                data-admin-refund-notes="<?php echo htmlspecialchars($req['admin_refund_notes'] ?? '', ENT_QUOTES); ?>"
                                                data-req-id="<?php echo $req['id']; ?>"
                                                data-req-type="<?php echo htmlspecialchars($req['type']); ?>">
                                                <i class="fas <?php echo $icon; ?> me-1"></i>
                                                <?php echo $statusLabel; ?>
                                            </div>
                                        </td>
                                        <!-- Date -->
                                        <td class="text-dark opacity-75 small">
                                            <i class="far fa-calendar-alt me-1 opacity-50"></i>
                                            <?php echo date('M d, Y', strtotime($req['date'])); ?>
                                        </td>
                                        <!-- Action -->
                                        <td class="pe-4 text-center">
                                            <div class="d-flex align-items-center justify-content-center gap-2">
                                                <!-- View Details -->
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-primary rounded-circle d-flex align-items-center justify-content-center btn-view-detail"
                                                    style="width: 32px; height: 32px;" title="View Details"
                                                    data-doc="<?php echo htmlspecialchars($displayDocType, ENT_QUOTES); ?>"
                                                    data-requester="<?php echo htmlspecialchars($requesterName, ENT_QUOTES); ?>"
                                                    data-requester-type="<?php echo $is_fm ? 'Family Member' : 'Owner'; ?>"
                                                    data-purpose="<?php echo htmlspecialchars($req['purpose'] ?? 'N/A', ENT_QUOTES); ?>"
                                                    data-status-label="<?php echo htmlspecialchars($statusLabel, ENT_QUOTES); ?>"
                                                    data-status-class="<?php echo htmlspecialchars($statusClass, ENT_QUOTES); ?>"
                                                    data-icon="<?php echo htmlspecialchars($icon, ENT_QUOTES); ?>"
                                                    data-date="<?php echo date('F d, Y', strtotime($req['date'])); ?>"
                                                    data-notes="<?php echo htmlspecialchars($req['notes'] ?? '', ENT_QUOTES); ?>"
                                                    data-payment-receipt="<?php echo htmlspecialchars($req['payment_receipt'] ?? '', ENT_QUOTES); ?>"
                                                    data-payment-status="<?php echo htmlspecialchars($req['payment_status'] ?? 'pending', ENT_QUOTES); ?>"
                                                    data-refund-number="<?php echo htmlspecialchars($req['refund_number'] ?? '', ENT_QUOTES); ?>"
                                                    data-refund-notes="<?php echo htmlspecialchars($req['refund_notes'] ?? '', ENT_QUOTES); ?>"
                                                    data-refund-receipt="<?php echo htmlspecialchars($req['refund_receipt'] ?? '', ENT_QUOTES); ?>"
                                                    data-admin-refund-number="<?php echo htmlspecialchars($req['admin_refund_number'] ?? '', ENT_QUOTES); ?>"
                                                    data-admin-refund-notes="<?php echo htmlspecialchars($req['admin_refund_notes'] ?? '', ENT_QUOTES); ?>"
                                                    data-req-id="<?php echo $req['id']; ?>"
                                                    data-req-type="<?php echo htmlspecialchars($req['type']); ?>">
                                                    <i class="fas fa-eye" style="font-size: 0.8rem;"></i>
                                                </button>
                                                <!-- Cancel (only for pending) -->
                                                <?php if ($req['status'] === 'pending'): ?>
                                                    <form method="POST" class="d-inline cancel-req-form">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action" value="cancel">
                                                        <input type="hidden" name="req_id" value="<?php echo $req['id']; ?>">
                                                        <input type="hidden" name="req_type"
                                                            value="<?php echo htmlspecialchars($req['type']); ?>">
                                                        <button type="button"
                                                            class="btn btn-sm btn-outline-danger rounded-circle d-flex align-items-center justify-content-center"
                                                            style="width: 32px; height: 32px;"
                                                            onclick="showCancelModal(this.closest('form'))" title="Cancel Request">
                                                            <i class="fas fa-times" style="font-size: 0.8rem;"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>
                            <?php endif; ?>
                    </table>
                </div>

                <?php if (!empty($all_requests) && $total_pages > 1): ?>
                    <div class="table-info-bar">
                        <div>
                            Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $per_page, $total_requests); ?></strong> of <strong><?php echo $total_requests; ?></strong> requests
                        </div>
                    </div>
                    <nav class="table-pagination">
                        <ul class="pagination">
                            <?php
                            $q_status = isset($_GET['status_filter']) ? '&status_filter=' . urlencode($_GET['status_filter']) : '';
                            ?>
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $q_status; ?>"><i class="fas fa-chevron-left" style="font-size:.65rem;"></i> Prev</a>
                            </li>
                            <?php
                            $max_visible = 10;
                            $start = max(1, $page - floor($max_visible / 2));
                            $end = min($total_pages, $start + $max_visible - 1);
                            if ($end - $start + 1 < $max_visible) {
                                $start = max(1, $end - $max_visible + 1);
                            }

                            if ($start > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1<?php echo $q_status; ?>">1</a>
                                </li>
                                <?php if ($start > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $q_status; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($end < $total_pages): ?>
                                <?php if ($end < $total_pages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo $q_status; ?>"><?php echo $total_pages; ?></a>
                                </li>
                            <?php endif; ?>

                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $q_status; ?>">Next <i class="fas fa-chevron-right" style="font-size:.65rem;"></i></a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Detail Modal (shared) -->
<div class="modal fade" id="viewDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div
                        class="width-12 height-12 rounded-3 bg-teal-50 text-teal-600 d-flex align-items-center justify-content-center">
                        <i class="fas fa-file-signature fa-lg"></i>
                    </div>
                    <h5 class="fw-bold mb-0 text-dark">Request Details</h5>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-dark opacity-75 fw-semibold small" style="width: 130px;">Document</td>
                        <td class="fw-bold" id="detail_document"></td>
                    </tr>
                    <tr>
                        <td class="text-dark opacity-75 fw-semibold small">Requester</td>
                        <td id="detail_requester"></td>
                    </tr>
                    <tr>
                        <td class="text-dark opacity-75 fw-semibold small">Purpose</td>
                        <td id="detail_purpose"></td>
                    </tr>
                    <tr>
                        <td class="text-dark opacity-75 fw-semibold small">Status</td>
                        <td id="detail_status"></td>
                    </tr>
                    <tr>
                        <td class="text-dark opacity-75 fw-semibold small">Date Filed</td>
                        <td id="detail_date"></td>
                    </tr>
                    <tr id="detail_payment_row" style="display: none;">
                        <td class="text-dark opacity-75 fw-semibold small">Payment</td>
                        <td id="detail_payment_value"></td>
                    </tr>
                </table>

                <!-- Resident Refund Request Details (collapsible) -->
                <div id="detail_resident_refund_section" class="mt-3 p-3 border border-light-subtle rounded-3 bg-light bg-opacity-50" style="display: none;">
                    <h6 class="fw-bold text-indigo-600 mb-2 small text-uppercase tracking-wider" style="font-size: 0.75rem;"><i class="fas fa-user-circle me-1"></i> Resident Refund Request Details</h6>
                    <div class="row g-2">
                        <div class="col-12">
                            <span class="text-secondary small d-block">GCash / Maya / Account No.</span>
                            <strong id="res_refund_number_val" class="font-monospace text-dark small"></strong>
                        </div>
                        <div class="col-12">
                            <span class="text-secondary small d-block">Reason for Refund</span>
                            <span id="res_refund_notes_val" class="text-dark small"></span>
                        </div>
                    </div>
                </div>

                <!-- Admin Refunded Details (collapsible) -->
                <div id="detail_admin_refund_section" class="mt-3 p-3 border border-light-subtle rounded-3 bg-light bg-opacity-50" style="display: none;">
                    <h6 class="fw-bold text-purple-600 mb-2 small text-uppercase tracking-wider" style="font-size: 0.75rem;"><i class="fas fa-check-circle me-1"></i> Refunded Details</h6>
                    <div class="row g-2">
                        <div class="col-12">
                            <span class="text-secondary small d-block">Refund Ref No.</span>
                            <strong id="admin_refund_number_val" class="font-monospace text-dark small"></strong>
                        </div>
                        <div class="col-12">
                            <span class="text-secondary small d-block">Admin Remarks / Notes</span>
                            <span id="admin_refund_notes_val" class="text-dark small"></span>
                        </div>
                        <div class="col-12 mt-2" id="admin_refund_receipt_container">
                            <!-- View Refund Receipt Button -->
                        </div>
                    </div>
                </div>
                <div class="mt-4 text-center d-none" id="detail_refund_action_container">
                    <button type="button" class="btn rounded-pill px-4" id="btn_request_refund_trigger" style="color: #6b21a8; border-color: #d8b4fe; background: #faf5ff; border: 1px solid #d8b4fe; font-size: 0.85rem; font-weight: 600;">
                        <i class="fas fa-undo-alt me-2"></i>Request Refund
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Confirmation Modal -->
<div class="modal fade" id="cancelConfirmModal" tabindex="-1" aria-labelledby="cancelConfirmModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body text-center p-5">
                <div class="mb-4">
                    <div class="rounded-circle bg-danger bg-opacity-10 d-inline-flex align-items-center justify-content-center mx-auto"
                        style="width: 80px; height: 80px;">
                        <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                    </div>
                </div>
                <h5 class="fw-bold text-dark mb-2">Cancel Request?</h5>
                <p class="text-secondary mb-3">Please provide a reason for cancelling this request.</p>
                <div class="mb-4 text-start">
                    <textarea id="cancelReasonInput" class="form-control bg-light border-0" rows="3"
                        placeholder="State your reason for cancellation..." required></textarea>
                    <div id="cancelReasonError" class="text-danger small mt-1" style="display: none;">Please provide a
                        reason.</div>
                </div>
                <div class="d-flex gap-3 justify-content-center">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">
                        <i class="fas fa-arrow-left me-2"></i>Go Back
                    </button>
                    <button type="button" class="btn btn-danger rounded-pill px-4" id="confirmCancelBtn">
                        <i class="fas fa-times me-2"></i>Yes, Cancel It
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resident Refund Modal -->
<div class="modal fade" id="residentRefundModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="width-12 height-12 rounded-3 bg-purple-50 text-purple-600 d-flex align-items-center justify-content-center" style="width:42px; height:42px; background: #faf5ff;">
                        <i class="fas fa-undo-alt fa-lg" style="color: #6b21a8;"></i>
                    </div>
                    <h5 class="fw-bold mb-0 text-dark">Request Refund</h5>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="residentRefundForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="refund">
                    <input type="hidden" name="req_id" id="refund_modal_req_id">
                    <input type="hidden" name="req_type" id="refund_modal_req_type">
                    
                    <p class="text-secondary small mb-3">Please provide your e-wallet (GCash/Maya) or bank details where we can send your refund.</p>
                    
                    <div class="mb-3">
                        <label for="res_refund_number" class="form-label small fw-bold text-dark mb-1">GCash / Maya / Account No. <span class="text-danger">*</span></label>
                        <input type="text" name="refund_number" id="res_refund_number" class="form-control rounded-3" placeholder="e.g. GCash: 0917XXXXXXX" required>
                    </div>

                    <div class="mb-4">
                        <label for="res_refund_notes" class="form-label small fw-bold text-dark mb-1">Reason for Refund / Notes <span class="text-danger">*</span></label>
                        <textarea name="refund_notes" id="res_refund_notes" class="form-control rounded-3" rows="3"
                            placeholder="e.g. Cancelled request, wrong document type, etc." required></textarea>
                    </div>

                    <div class="d-flex gap-3 justify-content-center">
                        <button type="button" class="btn btn-light rounded-pill px-4 w-50" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit" class="btn text-white rounded-pill px-4 w-50" style="background: #0d9488; border: none;">
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Show detail modal via data attributes
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-view-detail');
        if (!btn) return;

        const statusLabel = btn.dataset.statusLabel || '';
        const statusLower = statusLabel.toLowerCase();
        const notes = btn.dataset.notes || '';
        const hasNotes = notes.trim() !== '';

        const reqId = btn.dataset.reqId || '';
        const reqType = btn.dataset.reqType || '';

        const payStatus = btn.dataset.paymentStatus || 'pending';
        const payStatusLower = payStatus.toLowerCase();
        const isRefund = payStatusLower === 'refund_pending' || payStatusLower === 'refunded';

        // Show "View Details" link for both Rejected and Cancelled statuses if they have notes/reasons (excluding refund cases)
        const showReasonLink = (statusLower === 'rejected' || ((statusLower === 'cancelled' || statusLower === 'canceled') && !isRefund)) && hasNotes;

        document.getElementById('detail_document').textContent = btn.dataset.doc;
        document.getElementById('detail_requester').innerHTML = btn.dataset.requester + ' <span class="badge bg-light text-secondary">' + btn.dataset.requesterType + '</span>';
        document.getElementById('detail_purpose').textContent = btn.dataset.purpose;
        document.getElementById('detail_status').innerHTML = '<span class="badge ' + btn.dataset.statusClass + ' rounded-pill px-3 py-2"><i class="fas ' + btn.dataset.icon + ' me-1"></i>' + statusLabel + '</span>' +
            (showReasonLink ? ' <a href="javascript:void(0)" class="text-primary ms-2 small btn-show-reason" title="View Reason"><i class="fas fa-eye"></i> View Details</a>' : '');
        document.getElementById('detail_date').textContent = btn.dataset.date;

        // Reset refund sections display on modal open
        document.getElementById('detail_resident_refund_section').style.display = 'none';
        document.getElementById('detail_admin_refund_section').style.display = 'none';

        // Populate refund data fields
        const refundNumber = btn.dataset.refundNumber || '';
        const refundNotes = btn.dataset.refundNotes || '';
        const refundReceipt = btn.dataset.refundReceipt || '';
        const adminRefundNumber = btn.dataset.adminRefundNumber || '';
        const adminRefundNotes = btn.dataset.adminRefundNotes || '';

        document.getElementById('res_refund_number_val').textContent = refundNumber || 'Not provided';
        document.getElementById('res_refund_notes_val').textContent = refundNotes || notes || '—';
        document.getElementById('admin_refund_number_val').textContent = adminRefundNumber || '—';
        document.getElementById('admin_refund_notes_val').textContent = adminRefundNotes || 'No remarks provided.';

        const adminRefundReceiptContainer = document.getElementById('admin_refund_receipt_container');
        if (refundReceipt) {
            adminRefundReceiptContainer.innerHTML = `
                <a href="${refundReceipt}" target="_blank" class="btn btn-sm w-100 border fw-semibold rounded-3 py-2 mt-1 text-decoration-none d-flex align-items-center justify-content-center gap-1"
                    style="font-size: 0.8rem; color: #0d9488; border-color: #99f6e4 !important; background: #f0fdfa;">
                    <i class="far fa-image me-1"></i>View Refund Receipt Photo
                </a>
            `;
        } else {
            adminRefundReceiptContainer.innerHTML = '';
        }

        // Payment row logic
        const paymentRow = document.getElementById('detail_payment_row');
        const paymentVal = document.getElementById('detail_payment_value');
        const receipt = btn.dataset.paymentReceipt || '';

        if (receipt) {
            let badgeClass = 'bg-amber-50 text-amber-600';
            let statusText = 'Pending Verification';
            if (payStatusLower === 'verified' || payStatusLower === 'approved' || payStatusLower === 'success' || payStatusLower === 'confirmed') {
                badgeClass = 'bg-teal-50 text-teal-600';
                statusText = 'Verified';
            } else if (payStatusLower === 'rejected') {
                badgeClass = 'bg-rose-50 text-rose-600';
                statusText = 'Rejected';
            } else if (payStatusLower === 'refunded') {
                badgeClass = 'bg-purple-50 text-purple-600';
                statusText = 'Refunded';
            } else if (payStatusLower === 'refund_pending') {
                badgeClass = 'bg-indigo-50 text-indigo-600';
                statusText = 'Refund Pending';
            }

            let paymentHtml = `<span class="badge ${badgeClass} rounded-pill px-3 py-2"><i class="fas fa-receipt me-1"></i>${statusText}</span>`;
            if (payStatusLower === 'refund_pending') {
                paymentHtml = `
                    <span class="badge ${badgeClass} rounded-pill px-3 py-2" style="cursor: pointer;" onclick="toggleResidentRefundDetails()"><i class="fas fa-hourglass-half me-1"></i>${statusText}</span>
                    <a href="javascript:void(0)" class="text-indigo-600 fw-bold ms-2 small d-inline-flex align-items-center gap-1 text-decoration-none" onclick="toggleResidentRefundDetails()">
                        <i class="fas fa-info-circle"></i> View Refund Request
                    </a>
                `;
            } else if (payStatusLower === 'refunded') {
                paymentHtml = `
                    <span class="badge ${badgeClass} rounded-pill px-3 py-2" style="cursor: pointer;" onclick="toggleAdminRefundDetails()"><i class="fas fa-undo-alt me-1"></i>${statusText}</span>
                    <a href="javascript:void(0)" class="text-purple-600 fw-bold ms-2 small d-inline-flex align-items-center gap-1 text-decoration-none" onclick="toggleAdminRefundDetails()">
                        <i class="fas fa-eye"></i> View Refund Details
                    </a>
                `;
            } else {
                paymentHtml += `
                    <a href="${receipt}" target="_blank" class="text-teal-600 fw-bold ms-2 small d-inline-flex align-items-center gap-1 text-decoration-none">
                        <i class="fas fa-image"></i> View Receipt
                    </a>
                `;
            }

            paymentVal.innerHTML = paymentHtml;
            paymentRow.style.display = 'table-row';
        } else {
            paymentRow.style.display = 'none';
        }

        // Show refund action if receipt exists, document status is pending, and payment is either pending or confirmed
        const refundContainer = document.getElementById('detail_refund_action_container');
        if (refundContainer) {
            const payStatusLower = payStatus.toLowerCase();
            const isRefundablePayment = payStatusLower === 'pending' || payStatusLower === 'confirmed' || payStatusLower === 'verified';
            if (receipt && statusLower === 'pending' && isRefundablePayment) {
                refundContainer.classList.remove('d-none');
                const triggerBtn = document.getElementById('btn_request_refund_trigger');
                triggerBtn.onclick = function() {
                    var mainModalEl = document.getElementById('viewDetailModal');
                    var modal = bootstrap.Modal.getInstance(mainModalEl);
                    if (modal) modal.hide();
                    
                    document.getElementById('refund_modal_req_id').value = reqId;
                    document.getElementById('refund_modal_req_type').value = reqType;
                    document.getElementById('res_refund_number').value = '';
                    document.getElementById('res_refund_notes').value = '';
                    
                    setTimeout(() => {
                        var refModalEl = document.getElementById('residentRefundModal');
                        var refModal = bootstrap.Modal.getOrCreateInstance(refModalEl);
                        refModal.show();
                    }, 300);
                };
            } else {
                refundContainer.classList.add('d-none');
            }
        }

        // Store notes in a way that's easy to access for the "show reason" link
        var reasonLink = document.querySelector('#viewDetailModal .btn-show-reason');
        if (reasonLink) {
            reasonLink.onclick = function () { showRequestReason(notes, statusLabel); };
        }

        var mainModalEl = document.getElementById('viewDetailModal');
        var modal = bootstrap.Modal.getOrCreateInstance(mainModalEl);
        modal.show();
    });

    function showRequestReason(notes, status) {
        const statusLower = status.toLowerCase();
        const isCancellation = statusLower === 'cancelled' || statusLower === 'canceled';
        const titleColor = isCancellation ? 'text-secondary' : 'text-rose-600';
        const borderColor = isCancellation ? 'border-secondary' : 'border-rose-500';
        const btnColor = isCancellation ? '#6c757d' : '#e11d48';
        const titleText = isCancellation ? 'Reason for Cancellation' : 'Reason for Rejection';

        Swal.fire({
            title: '<div class="' + titleColor + ' fw-bold">' + titleText + '</div>',
            html: '<div class="text-start p-3 bg-light rounded border-start border-4 ' + borderColor + '" style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.6;">' + notes + '</div>',
            icon: 'info',
            confirmButtonText: 'Understood',
            confirmButtonColor: btnColor,
            width: '600px',
            customClass: {
                title: 'fs-4',
                confirmButton: 'px-4 py-2 rounded-pill fw-bold'
            }
        });
    }

    function toggleResidentRefundDetails() {
        const el = document.getElementById('detail_resident_refund_section');
        if (el.style.display === 'none') {
            el.style.display = 'block';
        } else {
            el.style.display = 'none';
        }
    }

    function toggleAdminRefundDetails() {
        const el = document.getElementById('detail_admin_refund_section');
        if (el.style.display === 'none') {
            el.style.display = 'block';
            // Also show resident's request info so they can see both
            document.getElementById('detail_resident_refund_section').style.display = 'block';
        } else {
            el.style.display = 'none';
            document.getElementById('detail_resident_refund_section').style.display = 'none';
        }
    }

    let _cancelForm = null;

    function showCancelModal(form) {
        const row = form.closest('tr');
        const viewBtn = row ? row.querySelector('.btn-view-detail') : null;
        const receipt = viewBtn ? (viewBtn.dataset.paymentReceipt || '') : '';
        const payStatus = viewBtn ? (viewBtn.dataset.paymentStatus || 'pending') : 'pending';
        const payStatusLower = payStatus.toLowerCase();
        
        if (receipt && (payStatusLower === 'pending' || payStatusLower === 'confirmed' || payStatusLower === 'verified')) {
            Swal.fire({
                title: '<div class="text-teal-600 fw-bold">Paid Request Detected</div>',
                html: '<div class="text-start p-3 bg-light rounded border-start border-4 border-teal-500" style="font-size: 0.95rem; line-height: 1.6;">You have already uploaded a payment receipt for this request. To cancel, please submit a Refund Request so we can return your payment to GCash/Maya.</div>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#0d9488',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Request Refund Now',
                cancelButtonText: 'Go Back',
                customClass: {
                    title: 'fs-4',
                    confirmButton: 'px-4 py-2 rounded-pill fw-bold',
                    cancelButton: 'px-4 py-2 rounded-pill fw-bold'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const reqId = viewBtn.dataset.reqId || '';
                    const reqType = viewBtn.dataset.reqType || '';
                    document.getElementById('refund_modal_req_id').value = reqId;
                    document.getElementById('refund_modal_req_type').value = reqType;
                    document.getElementById('res_refund_number').value = '';
                    document.getElementById('res_refund_notes').value = '';
                    
                    var refModalEl = document.getElementById('residentRefundModal');
                    var refModal = bootstrap.Modal.getOrCreateInstance(refModalEl);
                    refModal.show();
                }
            });
            return;
        }

        _cancelForm = form;
        document.getElementById('cancelReasonInput').value = '';
        document.getElementById('cancelReasonError').style.display = 'none';
        var modal = new bootstrap.Modal(document.getElementById('cancelConfirmModal'));
        modal.show();
    }

    document.getElementById('confirmCancelBtn').addEventListener('click', function () {
        var reason = document.getElementById('cancelReasonInput').value.trim();
        if (!reason) {
            document.getElementById('cancelReasonError').style.display = 'block';
            document.getElementById('cancelReasonInput').focus();
            return;
        }
        if (_cancelForm) {
            // Add cancel reason to the form
            var reasonInput = _cancelForm.querySelector('input[name="cancel_reason"]');
            if (!reasonInput) {
                reasonInput = document.createElement('input');
                reasonInput.type = 'hidden';
                reasonInput.name = 'cancel_reason';
                _cancelForm.appendChild(reasonInput);
            }
            reasonInput.value = reason;
            bootstrap.Modal.getInstance(document.getElementById('cancelConfirmModal')).hide();
            _cancelForm.submit();
        }
    });

    // Handle Request For dropdown change
    function handleRequestForChange(select) {
        if (select.value === 'add_new_fm') {
            window.location.href = 'family_members.php';
            return;
        }

        const hiddenType = document.getElementById('requestor_type_hidden');
        const hiddenFmId = document.getElementById('family_member_id_hidden');
        const docTypeSelect = document.getElementById('doc_type');
        const isSelf = select.value === 'self';

        if (isSelf) {
            hiddenType.value = 'self';
            hiddenFmId.value = '';
        } else {
            hiddenType.value = 'family_member';
            hiddenFmId.value = select.value;
        }

        // Disable Resident ID for family members
        Array.from(docTypeSelect.options).forEach(option => {
            if (option.value === 'Resident ID') {
                option.disabled = !isSelf;
                if (!isSelf && docTypeSelect.value === 'Resident ID') {
                    docTypeSelect.value = '';
                    docTypeSelect.dispatchEvent(new Event('change'));
                    Swal.fire({
                        title: 'Not Allowed',
                        text: 'Resident IDs can only be requested for the account owner. For a family member to get a Resident ID, they must create their own verified account.',
                        icon: 'warning'
                    });
                }
            }
        });
    }

    // Multi-Step Request Wizard State and Controls
    let currentStep = 1;

    function updateNavigationButtons() {
        const docTypeSelect = document.getElementById('doc_type');
        const selectedOption = docTypeSelect.options[docTypeSelect.selectedIndex];
        const btnPrev = document.getElementById('btn_prev_step');
        const btnNext = document.getElementById('btn_next_step');
        const btnSubmit = document.getElementById('btn_submit_request');

        const price = selectedOption && selectedOption.value !== "" ? parseFloat(selectedOption.getAttribute('data-price') || "0") : 0;

        if (currentStep === 1) {
            btnPrev.style.display = 'none';
            btnSubmit.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Submit Request';
            if (price > 0) {
                // Paid document in Step 1: show Next button (w-100), hide Submit button
                btnNext.style.display = 'block';
                btnNext.classList.remove('w-50');
                btnNext.classList.add('w-100');
                btnSubmit.style.display = 'none';
            } else {
                // Free document in Step 1: show Submit button (w-100), hide Next button
                btnNext.style.display = 'none';
                btnSubmit.style.display = 'block';
                btnSubmit.classList.remove('w-50');
                btnSubmit.classList.add('w-100');
            }
        } else if (currentStep === 2) {
            // Step 2 (paid documents only): show Back (w-50) and Submit (w-50)
            btnNext.style.display = 'none';
            btnSubmit.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Submit';

            btnPrev.style.display = 'block';
            btnPrev.classList.remove('w-100');
            btnPrev.classList.add('w-50');

            btnSubmit.style.display = 'block';
            btnSubmit.classList.remove('w-100');
            btnSubmit.classList.add('w-50');
        }
    }

    // Step 1 Validation
    function validateStep1() {
        const docTypeSelect = document.getElementById('doc_type');
        if (!docTypeSelect.value) {
            Swal.fire({
                title: 'Required Field',
                text: 'Please select a document type to proceed.',
                icon: 'warning',
                confirmButtonColor: '#0d9488'
            });
            return false;
        }

        const docType = docTypeSelect.value.toLowerCase();
        if (docType.includes('indigency')) {
            const selectedIndigency = document.querySelector('input[name="indigency_purpose"]:checked');
            if (!selectedIndigency) {
                Swal.fire({
                    title: 'Purpose Required',
                    text: 'Please select a purpose for the Indigency certificate.',
                    icon: 'warning',
                    confirmButtonColor: '#0d9488'
                });
                return false;
            }
        } else if (docType.includes('clearance') || docTypeSelect.value === 'Barangay Clearance') {
            const selectedClearance = document.querySelector('input[name="clearance_purpose"]:checked');
            if (!selectedClearance) {
                Swal.fire({
                    title: 'Purpose Required',
                    text: 'Please select a purpose for the Barangay Clearance.',
                    icon: 'warning',
                    confirmButtonColor: '#0d9488'
                });
                return false;
            }
        } else {
            const purposeTextarea = document.getElementById('purpose_textarea');
            if (!purposeTextarea.value.trim()) {
                Swal.fire({
                    title: 'Purpose Required',
                    text: 'Please state your purpose for the document request.',
                    icon: 'warning',
                    confirmButtonColor: '#0d9488'
                });
                purposeTextarea.focus();
                return false;
            }
        }
        return true;
    }

    // Form submission validation (uses same logic as Step 1)
    function validatePurpose() {
        return validateStep1();
    }

    // Step Navigation Click Handlers
    document.getElementById('btn_next_step').addEventListener('click', function () {
        if (validateStep1()) {
            currentStep = 2;
            document.getElementById('form_step_1').style.display = 'none';
            document.getElementById('form_step_2').style.display = 'block';
            updateNavigationButtons();
        }
    });

    document.getElementById('btn_prev_step').addEventListener('click', function () {
        currentStep = 1;
        document.getElementById('form_step_2').style.display = 'none';
        document.getElementById('form_step_1').style.display = 'block';
        updateNavigationButtons();
    });

    // Reset Wizard to step 1 and clean states
    function resetWizard() {
        document.getElementById('requestForm').reset();
        document.getElementById('requestor_type_hidden').value = 'self';
        document.getElementById('family_member_id_hidden').value = '';

        currentStep = 1;
        document.getElementById('form_step_1').style.display = 'block';
        document.getElementById('form_step_2').style.display = 'none';

        document.getElementById('document_price_container').style.display = 'none';
        document.getElementById('document_price_display').textContent = 'Free';
        document.getElementById('payment_section').style.display = 'none';
        document.getElementById('upload_status').textContent = 'Upload Receipt';
        
        const amountPaidDisplay = document.getElementById('amount_paid_display');
        if (amountPaidDisplay) amountPaidDisplay.textContent = '₱ 0.00';
        
        const refInput = document.getElementById('payment_reference_no');
        if (refInput) refInput.value = '';
        
        const amountInput = document.getElementById('payment_amount_paid');
        if (amountInput) amountInput.value = '';
        
        const ocrStatus = document.getElementById('ocr_scan_status');
        if (ocrStatus) ocrStatus.classList.add('d-none');

        const btnToggleQr = document.getElementById('btn_toggle_qr');
        if (btnToggleQr) {
            btnToggleQr.innerHTML = '<i class="fas fa-qrcode"></i> Scan QR Code';
        }

        document.getElementById('indigency_purpose_field').style.display = 'none';
        document.getElementById('clearance_purpose_field').style.display = 'none';
        
        const purposeTextField = document.getElementById('purpose_text_field');
        if (purposeTextField) {
            purposeTextField.style.display = 'block';
        }
        const purposeTextarea = document.getElementById('purpose_textarea');
        if (purposeTextarea) {
            purposeTextarea.setAttribute('required', 'required');
        }

        updateNavigationButtons();
    }

    // Modal show/hidden hooks to reset wizard cleanly
    const requestModalEl = document.getElementById('requestDocumentModal');
    if (requestModalEl) {
        requestModalEl.addEventListener('show.bs.modal', resetWizard);
        requestModalEl.addEventListener('hidden.bs.modal', resetWizard);
    }

    // Show/hide purpose selection based on document type
    document.getElementById('doc_type').addEventListener('change', function () {
        const indigencyPurposeField = document.getElementById('indigency_purpose_field');
        const clearancePurposeField = document.getElementById('clearance_purpose_field');
        const purposeTextField = document.getElementById('purpose_text_field');
        const purposeTextarea = document.getElementById('purpose_textarea');
        const selectedOption = this.options[this.selectedIndex];
        const docTypeValue = this.value.toLowerCase();

        // Price display logic
        const price = selectedOption && selectedOption.value !== "" ? parseFloat(selectedOption.getAttribute('data-price') || "0") : 0;
        const priceContainer = document.getElementById('document_price_container');
        const priceDisplay = document.getElementById('document_price_display');

        // E-Wallet/GCash Section
        const paymentSection = document.getElementById('payment_section');
        const amountDueDisplay = document.getElementById('amount_due_display');

        if (selectedOption && selectedOption.value !== "") {
            priceContainer.style.display = 'block';
            if (price > 0) {
                priceDisplay.textContent = '₱ ' + price.toFixed(2);
                paymentSection.style.display = 'block';
                amountDueDisplay.textContent = '₱ ' + price.toFixed(2);
            } else {
                priceDisplay.textContent = 'Free';
                paymentSection.style.display = 'none';
            }
        } else {
            priceContainer.style.display = 'none';
            paymentSection.style.display = 'none';
        }

        // Show/hide purpose selection based on document type
        if (docTypeValue.includes('indigency')) {
            // Show indigency purpose selection, hide clearance and text purpose
            indigencyPurposeField.style.display = 'block';
            clearancePurposeField.style.display = 'none';
            purposeTextField.style.display = 'none';
            purposeTextarea.removeAttribute('required');
        } else if (this.value === 'Barangay Clearance') {
            // Show clearance purpose selection, hide indigency and text purpose
            clearancePurposeField.style.display = 'block';
            indigencyPurposeField.style.display = 'none';
            purposeTextField.style.display = 'none';
            purposeTextarea.removeAttribute('required');
        } else {
            // Show text purpose, hide both purpose selections
            indigencyPurposeField.style.display = 'none';
            clearancePurposeField.style.display = 'none';
            purposeTextField.style.display = 'block';
            purposeTextarea.setAttribute('required', 'required');
        }

        // Update stepped wizard navigation buttons immediately when price changes
        updateNavigationButtons();
    });

    // Show QR code in screen overlay
    document.addEventListener('DOMContentLoaded', function() {
        const btnToggleQr = document.getElementById('btn_toggle_qr');
        if (btnToggleQr) {
            btnToggleQr.addEventListener('click', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Scan QR Code',
                    html: `
                        <div class="text-center p-2">
                            <div class="d-inline-block p-3 bg-white rounded-3 border shadow-sm mb-3">
                                <img src="public/img/gcash_qr.png" alt="InstaPay QR Code" class="img-fluid" style="max-width: 280px; width: 100%; height: auto;">
                            </div>
                            <div class="fw-bold text-teal-600 fs-6">Barangay Panungyanan Payment Portal</div>
                        </div>
                    `,
                    showCloseButton: true,
                    confirmButtonText: 'Done Scanning',
                    confirmButtonColor: '#0d9488',
                    customClass: {
                        popup: 'rounded-4 shadow'
                    }
                });
            });
        }
    });

    // Update upload status when file is chosen
    // Update upload status when file is chosen and run OCR
    async function handleReceiptSelected(input) {
        const statusDiv = document.getElementById('upload_status');
        const ocrStatus = document.getElementById('ocr_scan_status');
        const refInput = document.getElementById('payment_reference_no');
        const spinner = document.getElementById('ocr_spinner');
        const statusText = document.getElementById('ocr_status_text');

        if (input.files && input.files[0]) {
            const file = input.files[0];
            const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
            statusDiv.innerHTML = `<span class="text-teal-600 fw-bold"><i class="fas fa-check-circle me-1"></i> Selected: ${file.name} (${sizeInMB} MB)</span>`;

            // Start OCR
            ocrStatus.classList.remove('d-none');
            spinner.classList.remove('d-none');
            spinner.classList.add('text-teal-600');
            spinner.classList.remove('text-success', 'text-danger');
            statusText.textContent = 'Scanning receipt for reference number...';
            statusText.className = 'small text-secondary';
            refInput.value = '';

            try {
                // Create object URL for Tesseract
                const imageUrl = URL.createObjectURL(file);
                
                // Initialize worker
                const worker = await Tesseract.createWorker('eng');
                
                // Recognize text
                const ret = await worker.recognize(imageUrl);
                const text = ret.data.text;
                
                await worker.terminate();
                URL.revokeObjectURL(imageUrl);

                // Try to find reference number using common patterns
                // Search line-by-line to avoid merging with adjacent text (dates, etc.)
                const lines = text.split(/\n/);
                
                let foundRef = '';
                
                // 1. Look for a line with "Ref" label and extract ONLY the continuous block of digits from it
                for (const line of lines) {
                    const refLineMatch = line.match(/(?:ref(?:erence)?\.?\s*(?:no\.?|num(?:ber)?|id)?|trans(?:action)?\.?\s*(?:no\.?|id)?)\s*[:.-]?\s*([\d\sA-Za-z]+)/i);
                    if (refLineMatch) {
                        // Remove spaces first, then find the first block of 10-15 digits
                        const noSpaces = refLineMatch[1].replace(/\s+/g, '');
                        // Check for GCash (10-15 digits)
                        const gcashMatch = noSpaces.match(/\d{10,15}/);
                        if (gcashMatch) {
                            foundRef = gcashMatch[0];
                            break;
                        }
                        
                        // Check for Maya (strictly 12 alphanumeric chars)
                        const mayaMatch = noSpaces.match(/[A-Za-z0-9]{12}/);
                        if (mayaMatch && /[A-Z]/i.test(mayaMatch[0]) && /[0-9]/.test(mayaMatch[0])) {
                            foundRef = mayaMatch[0].toUpperCase();
                            break;
                        }
                    }
                }
                
                // 2. If not found via label, look for standalone 10-15 digit number per line
                if (!foundRef) {
                    for (const line of lines) {
                        // Extract all digits from the line, check if there's a 10-15 digit cluster
                        // Maya standalone check (12 alphanumeric)
                        const mayaMatch = line.match(/\b([A-Z0-9]{12})\b/i);
                        if (mayaMatch && /[A-Z]/i.test(mayaMatch[1]) && /[0-9]/.test(mayaMatch[1])) {
                            foundRef = mayaMatch[1].toUpperCase();
                            break;
                        }
                        
                        const digitsMatch = line.match(/\b(\d[\d\s]{9,16}\d)\b/);
                        if (digitsMatch) {
                            const clean = digitsMatch[1].replace(/\s+/g, '');
                            if (clean.length >= 10 && clean.length <= 15) {
                                foundRef = clean;
                                break;
                            }
                        }
                    }
                }
                
                // 3. Last resort: look for any 13-digit number anywhere
                if (!foundRef) {
                    const allDigits = text.replace(/\s/g, '').match(/\d{13}/);
                    if (allDigits) foundRef = allDigits[0];
                }

                // Try to find amount paid
                let foundAmount = '';
                const amountMatch = text.match(/(?:amount|total|php|p|₱)\s*[:.-]?\s*([0-9,]+\.\d{2})/i);
                if (amountMatch) {
                    foundAmount = amountMatch[1].replace(/,/g, ''); // remove commas
                } else {
                    // Fallback: just look for any standard currency format XX.XX
                    const fallbackMatch = text.match(/\b([0-9,]+\.\d{2})\b/);
                    if (fallbackMatch) {
                        foundAmount = fallbackMatch[1].replace(/,/g, '');
                    }
                }

                spinner.classList.add('d-none');
                
                const amountInput = document.getElementById('payment_amount_paid');
                
                if (foundRef || foundAmount) {
                    if (foundRef) {
                        refInput.value = foundRef;

                        // ── Duplicate ref-number check ──────────────────
                        try {
                            const chkRes  = await fetch(`/api/check_ref.php?ref=${encodeURIComponent(foundRef)}`);
                            const chkData = await chkRes.json();

                            if (chkData.exists) {
                                statusText.innerHTML = `<span class="text-danger fw-bold"><i class="fas fa-ban me-1"></i> Duplicate Reference No.!</span> ${chkData.message}`;
                                refInput.value = '';

                                // Disable submit button
                                const submitBtn = document.getElementById('btn_submit_request');
                                if (submitBtn) {
                                    submitBtn.disabled = true;
                                    submitBtn.title = 'Cannot submit: reference number already used.';
                                }

                                spinner.classList.add('d-none');
                                if (foundAmount && amountInput) {
                                    amountInput.value = foundAmount;
                                    document.getElementById('amount_paid_display').textContent = '₱ ' + foundAmount;
                                }
                                return; // stop here
                            } else {
                                // Re-enable submit in case it was disabled before
                                const submitBtn = document.getElementById('btn_submit_request');
                                if (submitBtn) {
                                    submitBtn.disabled = false;
                                    submitBtn.title = '';
                                }
                            }
                        } catch(e) {
                            // If the check fails (network error), allow submission (server will catch it)
                            console.warn('Ref check failed:', e);
                        }
                        // ─────────────────────────────────────────────────
                    }

                    if (foundAmount && amountInput) {
                        amountInput.value = foundAmount;
                        document.getElementById('amount_paid_display').textContent = '₱ ' + foundAmount;
                        
                        // ── Insufficient Payment Check ──────────────────
                        const docTypeSelect = document.getElementById('doc_type');
                        const selectedOption = docTypeSelect.options[docTypeSelect.selectedIndex];
                        const amountDue = selectedOption && selectedOption.value !== "" ? parseFloat(selectedOption.getAttribute('data-price') || "0") : 0;
                        const numericFoundAmount = parseFloat(foundAmount.replace(/,/g, ''));
                        
                        if (amountDue > 0 && numericFoundAmount < amountDue) {
                            statusText.innerHTML = `<span class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> Insufficient payment!</span> Amount paid (₱ ${numericFoundAmount.toFixed(2)}) is less than the required amount (₱ ${amountDue.toFixed(2)}).`;
                            
                            // Disable submit button
                            const submitBtn = document.getElementById('btn_submit_request');
                            if (submitBtn) {
                                submitBtn.disabled = true;
                                submitBtn.title = 'Cannot submit: insufficient payment amount.';
                            }
                            spinner.classList.add('d-none');
                            return; // stop here
                        }
                    }
                    
                    statusText.innerHTML = `<span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> Scan complete!</span> Details extracted from receipt.`;
                } else {
                    statusText.innerHTML = `<span class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> Could not detect details.</span> Please ensure the receipt is clear.`;
                }

            } catch (error) {
                console.error("OCR Error:", error);
                spinner.classList.add('d-none');
                statusText.innerHTML = `<span class="text-danger fw-bold"><i class="fas fa-times-circle me-1"></i> Scan failed.</span> Please ensure the receipt is clear.`;
            }

        } else {
            statusDiv.textContent = 'Upload Receipt';
            ocrStatus.classList.add('d-none');
            refInput.value = '';
            const amountInput = document.getElementById('payment_amount_paid');
            if (amountInput) amountInput.value = '';
        }
    }

    <?php
    $display_msg = $message ?: ($_SESSION['info'] ?? '');
    $is_cancel = $was_cancel || ($_SESSION['was_cancel'] ?? false);
    $is_error = stripos($display_msg, 'Error') !== false;
    if (isset($_SESSION['was_cancel']))
        unset($_SESSION['was_cancel']);
    if (isset($_SESSION['info']))
        unset($_SESSION['info']);

    if ($display_msg): ?>
        // Create and show success/error modal
        var successModal = document.createElement('div');
        successModal.className = 'modal fade';
        successModal.id = 'successModal';
        successModal.setAttribute('tabindex', '-1');
        successModal.setAttribute('data-bs-backdrop', 'static');
        successModal.setAttribute('data-bs-keyboard', 'false');
        <?php if ($is_error): ?>
            successModal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-body text-center p-5">
                        <div class="mb-4">
                            <div class="rounded-circle bg-danger bg-opacity-10 d-inline-flex align-items-center justify-content-center mx-auto" style="width: 80px; height: 80px;">
                                <i class="fas fa-exclamation-triangle fa-3x text-danger"></i>
                            </div>
                        </div>
                        <h4 class="fw-bold text-dark mb-3">Request Failed</h4>
                        <p class="text-secondary mb-4"><?php echo htmlspecialchars(str_replace('Error: ', '', $display_msg)); ?></p>
                        <button type="button" class="btn btn-danger btn-lg rounded-pill px-5" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Close
                        </button>
                    </div>
                </div>
            </div>
        `;
        <?php elseif ($is_cancel): ?>
            successModal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-body text-center p-5">
                        <div class="mb-4">
                            <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center mx-auto" style="width: 80px; height: 80px;">
                                <i class="fas fa-check-circle fa-3x text-success"></i>
                            </div>
                        </div>
                        <p class="fw-semibold text-dark mb-4"><?php echo htmlspecialchars($display_msg); ?></p>
                        <button type="button" class="btn btn-primary btn-lg rounded-pill px-5" data-bs-dismiss="modal">
                            <i class="fas fa-check me-2"></i>OK
                        </button>
                    </div>
                </div>
            </div>
        `;
        <?php else: ?>
            successModal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-body text-center p-5">
                        <div class="mb-4">
                            <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center mx-auto" style="width: 80px; height: 80px;">
                                <i class="fas fa-check-circle fa-3x text-success"></i>
                            </div>
                        </div>
                        <h4 class="fw-bold text-dark mb-3">Request Submitted Successfully!</h4>
                        <p class="text-secondary mb-4"><?php echo htmlspecialchars($display_msg); ?></p>
                        <button type="button" class="btn btn-primary btn-lg rounded-pill px-5" data-bs-dismiss="modal">
                            <i class="fas fa-check me-2"></i>OK
                        </button>
                    </div>
                </div>
            </div>
        `;
        <?php endif; ?>
        document.body.appendChild(successModal);

        // Show modal after page loads
        setTimeout(function () {
            var modal = new bootstrap.Modal(document.getElementById('successModal'));
            modal.show();

            // Remove modal from DOM after it's hidden
            document.getElementById('successModal').addEventListener('hidden.bs.modal', function () {
                successModal.remove();
            });
        }, 300);
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/partials/user_dashboard_footer.php'; ?>