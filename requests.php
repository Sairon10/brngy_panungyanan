<?php 
require_once __DIR__ . '/config.php';
if (!is_logged_in()) redirect('login.php'); 

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
    $is_account_active = (bool)($user_data['is_active'] ?? true);
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

// Fetch user's active family members for "Request For" selector
$fm_stmt = $pdo->prepare('SELECT * FROM family_members WHERE user_id = ? AND is_active = 1 ORDER BY full_name ASC');
$fm_stmt->execute([$_SESSION['user_id']]);
$family_members = $fm_stmt->fetchAll();

// Handle form submission
$was_cancel = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $message = 'Invalid session. Please reload and try again.';
    } else if (isset($_POST['action']) && $_POST['action'] === 'cancel') {
        $req_id = (int)($_POST['req_id'] ?? 0);
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
            $family_member_id = ($requestor_type === 'family_member') ? (int)($_POST['family_member_id'] ?? 0) : null;

            // Critical security check: Is the family member active and belongs to the user?
            if ($family_member_id) {
                $fm_check = $pdo->prepare('SELECT id FROM family_members WHERE id = ? AND user_id = ? AND is_active = 1 LIMIT 1');
                $fm_check->execute([$family_member_id, $_SESSION['user_id']]);
                if (!$fm_check->fetch()) {
                    $message = 'Error: Selected family member is inactive or unauthorized.';
                    goto skip_request;
                }
            }

            if ($requires_special_handling) {
                // Handle special document types (like Barangay Clearance)
                $validity_days = $requires_validity ? (int)($_POST['validity_days'] ?? 30) : 30;
                
                // Generate unique clearance number
                $year = date('Y');
                $user_id_padded = str_pad((string)$_SESSION['user_id'], 6, '0', STR_PAD_LEFT);
                
                // Get count of existing clearances for this user this year
                $count_stmt = $pdo->prepare('
                    SELECT COUNT(*) as count 
                    FROM barangay_clearances 
                    WHERE user_id = ? AND YEAR(created_at) = ?
                ');
                $count_stmt->execute([$_SESSION['user_id'], $year]);
                $count_result = $count_stmt->fetch();
                $sequence = (int)$count_result['count'] + 1;
                
                // Generate clearance number with sequence
                $clearance_number = 'BC-' . $year . '-' . $user_id_padded . '-' . str_pad((string)$sequence, 3, '0', STR_PAD_LEFT);
                
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
                    $clearance_number = 'BC-' . $year . '-' . $user_id_padded . '-' . str_pad((string)$sequence, 3, '0', STR_PAD_LEFT);
                }

                $stmt = $pdo->prepare('INSERT INTO barangay_clearances (user_id, clearance_number, purpose, validity_days, status) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$_SESSION['user_id'], $clearance_number, $purpose, $validity_days, 'pending']);
                
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
                    $pdo->prepare('INSERT INTO document_requests (user_id, doc_type, purpose, indigency_purposes, family_member_id, requestor_type) VALUES (?,?,?,?,?,?)')
                        ->execute([$_SESSION['user_id'], $doc_type, $purpose, $indigency_purpose, $family_member_id ?: null, $family_member_id ? 'family_member' : 'self']);
                } else {
                    // Save with text purpose
                    $pdo->prepare('INSERT INTO document_requests (user_id, doc_type, purpose, family_member_id, requestor_type) VALUES (?,?,?,?,?)')
                        ->execute([$_SESSION['user_id'], $doc_type, $purpose, $family_member_id ?: null, $family_member_id ? 'family_member' : 'self']);
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
<div class="modal fade" id="requestDocumentModal" tabindex="-1" aria-labelledby="requestDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="width-12 height-12 rounded-3 bg-teal-50 text-teal-600 d-flex align-items-center justify-content-center">
                        <i class="fas fa-file-signature fa-lg"></i>
                    </div>
                    <h4 class="fw-bold mb-0 text-dark">Request Document</h4>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form method="post" id="requestForm" onsubmit="return validatePurpose()">
                    <?php echo csrf_field(); ?>
                    
                    <?php if (!$is_account_active): ?>
                        <div class="alert alert-danger border-0 bg-rose-50 text-rose-600 rounded-3 mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Account Inactive:</strong> Your account is currently deactivated. You cannot submit new requests at this time.
                        </div>
                    <?php endif; ?>
                    <!-- Request For selector (first field) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small text-uppercase">Request For</label>
                        <input type="hidden" name="requestor_type" id="requestor_type_hidden" value="self">
                        <input type="hidden" name="family_member_id" id="family_member_id_hidden" value="">
                        <select id="request_for_select" class="form-select form-select-lg bg-light border-0" required onchange="handleRequestForChange(this)">
                            <option value="self"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Account Owner'); ?> — Owner</option>
                            <?php if (!empty($family_members)): ?>
                                <?php foreach ($family_members as $fm): ?>
                                    <option value="<?php echo $fm['id']; ?>">
                                        <?php echo htmlspecialchars($fm['full_name']); ?> — Family Member
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <option value="add_new_fm" class="text-primary fw-bold">+ Add Family Member</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small text-uppercase">Document Type</label>
                        <select name="doc_type" id="doc_type" class="form-select form-select-lg bg-light border-0" required>
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
                        <label class="form-label fw-semibold text-secondary small text-uppercase">Price</label>
                        <div class="p-3 bg-light rounded text-success fs-5 fw-bold border" id="document_price_display">
                            Free
                        </div>
                    </div>

                    <!-- Purpose Selection for Indigency -->
                    <div class="mb-3" id="indigency_purpose_field" style="display: none;">
                        <label class="form-label fw-semibold text-secondary small text-uppercase">Select Purpose:</label>
                        <div class="border rounded p-3 bg-light">
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($indigency_purposes_list as $purpose): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" 
                                            name="indigency_purpose" 
                                            value="<?php echo htmlspecialchars($purpose); ?>" 
                                            id="indigency_purpose_<?php echo md5($purpose); ?>">
                                        <label class="form-check-label" for="indigency_purpose_<?php echo md5($purpose); ?>">
                                            <?php echo htmlspecialchars($purpose); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Purpose Selection for Clearance -->
                    <div class="mb-3" id="clearance_purpose_field" style="display: none;">
                        <label class="form-label fw-semibold text-secondary small text-uppercase">Select Purpose:</label>
                        <div class="border rounded p-3 bg-light">
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($clearance_purposes_list as $purpose): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" 
                                            name="clearance_purpose" 
                                            value="<?php echo htmlspecialchars($purpose); ?>" 
                                            id="clearance_purpose_<?php echo md5($purpose); ?>">
                                        <label class="form-check-label" for="clearance_purpose_<?php echo md5($purpose); ?>">
                                            <?php echo htmlspecialchars($purpose); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4" id="purpose_text_field">
                        <label class="form-label fw-semibold text-secondary small text-uppercase">Purpose</label>
                        <textarea name="purpose" class="form-control bg-light border-0" rows="4"
                            placeholder="State your purpose..." id="purpose_textarea"></textarea>
                    </div>
                    <div class="d-grid">
                        <button class="btn btn-primary btn-lg rounded-pill" type="submit" <?php echo !$is_account_active ? 'disabled' : ''; ?>>
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
                    <div class="width-12 height-12 rounded-3 bg-amber-50 text-amber-600 d-flex align-items-center justify-content-center">
                        <i class="fas fa-history fa-lg"></i>
                    </div>
                    <h4 class="fw-bold mb-0 text-dark">Request History</h4>
                    <div class="ms-auto flex-shrink-0 d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#requestDocumentModal">
                            <i class="fas fa-plus me-2"></i>Request Documents
                        </button>
                        <form method="GET" class="m-0">
                            <select name="status_filter" class="form-select border-0 bg-transparent fw-semibold text-primary shadow-none ps-0 font-monospace" style="outline: none; cursor: pointer; text-align: right;" onchange="this.form.submit()">
                                <option value="all" <?php echo empty($_GET['status_filter']) || $_GET['status_filter'] === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="pending" <?php echo isset($_GET['status_filter']) && $_GET['status_filter'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo isset($_GET['status_filter']) && $_GET['status_filter'] === 'approved' ? 'selected' : ''; ?>>Ready to Pick Up</option>
                                <option value="released" <?php echo isset($_GET['status_filter']) && $_GET['status_filter'] === 'released' ? 'selected' : ''; ?>>Released</option>
                                <option value="canceled" <?php echo isset($_GET['status_filter']) && $_GET['status_filter'] === 'canceled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </form>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-3 ps-4 rounded-start" style="width: 50px;">#</th>
                                <th class="py-3">Name</th>
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
                                    'user_name' => $c['user_name'] ?? ''
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
                                    'user_name' => $d['user_name'] ?? ''
                                ];
                            }
                            
                            // Filter by status wrapper BEFORE pagination
                            $status_filter = $_GET['status_filter'] ?? 'all';
                            if ($status_filter !== 'all') {
                                $all_requests = array_filter($all_requests, function($r) use ($status_filter) {
                                    return strtolower($r['status']) === strtolower($status_filter);
                                });
                            }
                            // Sort by date descending
                            usort($all_requests, function($a, $b) {
                                return strtotime($b['date']) - strtotime($a['date']);
                            });
                            
                            // Pagination logic
                            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
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
                                    <td colspan="5" class="text-center py-5">
                                        <div class="text-secondary opacity-50 mb-2">
                                            <i class="fas fa-folder-open fa-3x"></i>
                                        </div>
                                        <p class="text-secondary mb-0">No requests found.</p>
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
                                    
                                    switch($req['status']) {
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
                                        <td class="ps-4 text-secondary fw-semibold"><?php echo $row_number++; ?></td>
                                        <!-- Name -->
                                        <td>
                                            <div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($requesterName); ?></div>
                                        </td>
                                        <!-- Status -->
                                        <td>
                                            <div role="button" class="badge <?php echo $statusClass; ?> rounded-pill px-3 py-2 border border-0 btn-view-detail mx-auto" 
                                                style="cursor: pointer; display: inline-block;"
                                                data-doc="<?php echo htmlspecialchars($displayDocType, ENT_QUOTES); ?>"
                                                data-requester="<?php echo htmlspecialchars($requesterName, ENT_QUOTES); ?>"
                                                data-requester-type="<?php echo $is_fm ? 'Family Member' : 'Owner'; ?>"
                                                data-purpose="<?php echo htmlspecialchars($req['purpose'] ?? 'N/A', ENT_QUOTES); ?>"
                                                data-status-label="<?php echo htmlspecialchars($statusLabel, ENT_QUOTES); ?>"
                                                data-status-class="<?php echo htmlspecialchars($statusClass, ENT_QUOTES); ?>"
                                                data-icon="<?php echo htmlspecialchars($icon, ENT_QUOTES); ?>"
                                                data-date="<?php echo date('F d, Y', strtotime($req['date'])); ?>"
                                                data-notes="<?php echo htmlspecialchars($req['notes'] ?? '', ENT_QUOTES); ?>">
                                                <i class="fas <?php echo $icon; ?> me-1"></i>
                                                <?php echo $statusLabel; ?>
                                            </div>
                                        </td>
                                        <!-- Date -->
                                        <td class="text-secondary small">
                                            <i class="far fa-calendar-alt me-1 opacity-50"></i>
                                            <?php echo date('M d, Y', strtotime($req['date'])); ?>
                                        </td>
                                        <!-- Action -->
                                        <td class="pe-4 text-center">
                                            <div class="d-flex align-items-center justify-content-center gap-2">
                                                <!-- View Details -->
                                                <button type="button" class="btn btn-sm btn-outline-primary rounded-circle d-flex align-items-center justify-content-center btn-view-detail" style="width: 32px; height: 32px;" title="View Details"
                                                    data-doc="<?php echo htmlspecialchars($displayDocType, ENT_QUOTES); ?>"
                                                    data-requester="<?php echo htmlspecialchars($requesterName, ENT_QUOTES); ?>"
                                                    data-requester-type="<?php echo $is_fm ? 'Family Member' : 'Owner'; ?>"
                                                    data-purpose="<?php echo htmlspecialchars($req['purpose'] ?? 'N/A', ENT_QUOTES); ?>"
                                                    data-status-label="<?php echo htmlspecialchars($statusLabel, ENT_QUOTES); ?>"
                                                    data-status-class="<?php echo htmlspecialchars($statusClass, ENT_QUOTES); ?>"
                                                    data-icon="<?php echo htmlspecialchars($icon, ENT_QUOTES); ?>"
                                                    data-date="<?php echo date('F d, Y', strtotime($req['date'])); ?>"
                                                    data-notes="<?php echo htmlspecialchars($req['notes'] ?? '', ENT_QUOTES); ?>">
                                                    <i class="fas fa-eye" style="font-size: 0.8rem;"></i>
                                                </button>
                                                <!-- Cancel (only for pending) -->
                                                <?php if ($req['status'] === 'pending'): ?>
                                                    <form method="POST" class="d-inline cancel-req-form">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action" value="cancel">
                                                        <input type="hidden" name="req_id" value="<?php echo $req['id']; ?>">
                                                        <input type="hidden" name="req_type" value="<?php echo htmlspecialchars($req['type']); ?>">
                                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" onclick="showCancelModal(this.closest('form'))" title="Cancel Request">
                                                            <i class="fas fa-times" style="font-size: 0.8rem;"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (!empty($all_requests) && $total_pages > 1): ?>
                <div class="px-4 py-3 border-top bg-light mt-auto rounded-bottom-4">
                    <nav aria-label="Request history pagination" class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <span class="text-secondary small">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_requests); ?> of <?php echo $total_requests; ?> requests
                        </span>
                        <?php 
                            $q_status = isset($_GET['status_filter']) ? '&status_filter=' . urlencode($_GET['status_filter']) : '';
                        ?>
                        <ul class="pagination pagination-sm mx-0 mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link shadow-sm border-0" href="?page=<?php echo $page - 1; ?><?php echo $q_status; ?>" <?php echo $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                    <a class="page-link <?php echo $page === $i ? 'bg-primary text-white border-primary shadow' : 'text-primary shadow-sm border-0'; ?>" href="?page=<?php echo $i; ?><?php echo $q_status; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link shadow-sm border-0" href="?page=<?php echo $page + 1; ?><?php echo $q_status; ?>" <?php echo $page >= $total_pages ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
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
                    <div class="width-12 height-12 rounded-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center">
                        <i class="fas fa-file-alt fa-lg"></i>
                    </div>
                    <h5 class="fw-bold mb-0 text-dark">Request Details</h5>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-secondary fw-semibold small" style="width: 130px;">Document</td>
                        <td class="fw-bold" id="detail_document"></td>
                    </tr>
                    <tr>
                        <td class="text-secondary fw-semibold small">Requester</td>
                        <td id="detail_requester"></td>
                    </tr>
                    <tr>
                        <td class="text-secondary fw-semibold small">Purpose</td>
                        <td id="detail_purpose"></td>
                    </tr>
                    <tr>
                        <td class="text-secondary fw-semibold small">Status</td>
                        <td id="detail_status"></td>
                    </tr>
                    <tr>
                        <td class="text-secondary fw-semibold small">Date Filed</td>
                        <td id="detail_date"></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Confirmation Modal -->
<div class="modal fade" id="cancelConfirmModal" tabindex="-1" aria-labelledby="cancelConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body text-center p-5">
                <div class="mb-4">
                    <div class="rounded-circle bg-danger bg-opacity-10 d-inline-flex align-items-center justify-content-center mx-auto" style="width: 80px; height: 80px;">
                        <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                    </div>
                </div>
                <h5 class="fw-bold text-dark mb-2">Cancel Request?</h5>
                <p class="text-secondary mb-3">Please provide a reason for cancelling this request.</p>
                <div class="mb-4 text-start">
                    <textarea id="cancelReasonInput" class="form-control bg-light border-0" rows="3" placeholder="State your reason for cancellation..." required></textarea>
                    <div id="cancelReasonError" class="text-danger small mt-1" style="display: none;">Please provide a reason.</div>
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

<script>
// Show detail modal via data attributes
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-view-detail');
    if (!btn) return;
    
    const statusLabel = btn.dataset.statusLabel || '';
    const statusLower = statusLabel.toLowerCase();
    const notes = btn.dataset.notes || '';
    const hasNotes = notes.trim() !== '';
    
    // Show "View Details" link for both Rejected and Cancelled statuses if they have notes/reasons
    const showReasonLink = (statusLower === 'rejected' || statusLower === 'cancelled' || statusLower === 'canceled') && hasNotes;

    document.getElementById('detail_document').textContent = btn.dataset.doc;
    document.getElementById('detail_requester').innerHTML = btn.dataset.requester + ' <span class="badge bg-light text-secondary">' + btn.dataset.requesterType + '</span>';
    document.getElementById('detail_purpose').textContent = btn.dataset.purpose;
    document.getElementById('detail_status').innerHTML = '<span class="badge ' + btn.dataset.statusClass + ' rounded-pill px-3 py-2"><i class="fas ' + btn.dataset.icon + ' me-1"></i>' + statusLabel + '</span>' + 
        (showReasonLink ? ' <a href="javascript:void(0)" class="text-primary ms-2 small btn-show-reason" title="View Reason"><i class="fas fa-eye"></i> View Details</a>' : '');
    document.getElementById('detail_date').textContent = btn.dataset.date;
    
    // Store notes in a way that's easy to access for the "show reason" link
    var reasonLink = document.querySelector('#viewDetailModal .btn-show-reason');
    if (reasonLink) {
        reasonLink.onclick = function() { showRequestReason(notes, statusLabel); };
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

let _cancelForm = null;

function showCancelModal(form) {
    _cancelForm = form;
    document.getElementById('cancelReasonInput').value = '';
    document.getElementById('cancelReasonError').style.display = 'none';
    var modal = new bootstrap.Modal(document.getElementById('cancelConfirmModal'));
    modal.show();
}

document.getElementById('confirmCancelBtn').addEventListener('click', function() {
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
    if (select.value === 'self') {
        hiddenType.value = 'self';
        hiddenFmId.value = '';
    } else {
        hiddenType.value = 'family_member';
        hiddenFmId.value = select.value;
    }
}

// Form validation for purpose selection
function validatePurpose() {
    const docType = document.getElementById('doc_type').value.toLowerCase();
    const indigencyPurposeField = document.getElementById('indigency_purpose_field');
    const clearancePurposeField = document.getElementById('clearance_purpose_field');
    
    if (docType.includes('indigency')) {
        const selectedIndigency = document.querySelector('input[name="indigency_purpose"]:checked');
        if (!selectedIndigency) {
            alert('Please select a purpose for Indigency certificate.');
            return false;
        }
    } else if (docType.includes('clearance') || document.getElementById('doc_type').value === 'Barangay Clearance') {
        const selectedClearance = document.querySelector('input[name="clearance_purpose"]:checked');
        if (!selectedClearance) {
            alert('Please select a purpose for Barangay Clearance.');
            return false;
        }
    }
    return true;
}

// Show/hide purpose selection based on document type
document.getElementById('doc_type').addEventListener('change', function() {
    const indigencyPurposeField = document.getElementById('indigency_purpose_field');
    const clearancePurposeField = document.getElementById('clearance_purpose_field');
    const purposeTextField = document.getElementById('purpose_text_field');
    const purposeTextarea = document.getElementById('purpose_textarea');
    const selectedOption = this.options[this.selectedIndex];
    const docTypeValue = this.value.toLowerCase();
    
    // Price display logic
    const priceContainer = document.getElementById('document_price_container');
    const priceDisplay = document.getElementById('document_price_display');
    if (selectedOption && selectedOption.value !== "") {
        const price = parseFloat(selectedOption.getAttribute('data-price') || "0");
        if (price > 0) {
            priceDisplay.textContent = '₱ ' + price.toFixed(2);
        } else {
            priceDisplay.textContent = 'Free';
        }
        priceContainer.style.display = 'block';
    } else {
        priceContainer.style.display = 'none';
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
});

    <?php 
    $display_msg = $message ?: ($_SESSION['info'] ?? '');
    $is_cancel = $was_cancel || ($_SESSION['was_cancel'] ?? false);
    if (isset($_SESSION['was_cancel'])) unset($_SESSION['was_cancel']);
    if (isset($_SESSION['info'])) unset($_SESSION['info']);

    if ($display_msg): ?>
        // Create and show success modal
        var successModal = document.createElement('div');
        successModal.className = 'modal fade';
        successModal.id = 'successModal';
        successModal.setAttribute('tabindex', '-1');
        successModal.setAttribute('data-bs-backdrop', 'static');
        successModal.setAttribute('data-bs-keyboard', 'false');
        <?php if ($is_cancel): ?>
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
        setTimeout(function() {
            var modal = new bootstrap.Modal(document.getElementById('successModal'));
            modal.show();
            
            // Remove modal from DOM after it's hidden
            document.getElementById('successModal').addEventListener('hidden.bs.modal', function() {
                successModal.remove();
            });
        }, 300);
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/partials/user_dashboard_footer.php'; ?>
