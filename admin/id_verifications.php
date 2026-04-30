<?php 
require_once __DIR__ . '/../config.php';
if (!is_admin()) redirect('../index.php');
require_once __DIR__ . '/header.php'; 
?>

<?php
$page_title = 'ID Verification Management';
$breadcrumb = [
	['title' => 'ID Verifications']
];

$pdo = get_db_connection();
$errors = [];
$success = '';

// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_validate()) {
        $errors[] = 'Invalid CSRF token. Please refresh the page and try again.';
    } else {
        $resident_id = (int)($_POST['resident_id'] ?? 0);
        $action = $_POST['action'];
        
        if ($resident_id <= 0) {
            $errors[] = 'Invalid resident ID';
        } else {
            try {
                // Get resident and user information before updating
                $resident_stmt = $pdo->prepare('
                    SELECT r.*, u.full_name, u.first_name, u.last_name, u.middle_name, u.suffix, u.email, r.phone 
                    FROM residents r 
                    JOIN users u ON r.user_id = u.id 
                    WHERE r.id = ?
                ');
                $resident_stmt->execute([$resident_id]);
                $resident_data = $resident_stmt->fetch();
                
                if (!$resident_data) {
                    $errors[] = 'Resident not found';
                } else {
                    if ($action === 'verify') {
                        $stmt = $pdo->prepare('UPDATE residents SET verification_status = \'verified\', verified_at = NOW(), verified_by = ? WHERE id = ?');
                        $stmt->execute([$_SESSION['user_id'], $resident_id]);
                        $success = 'Resident verified successfully';

                        // In-app notification to resident
                        $pdo->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "verification_update", "ID Verification Approved", ?)')
                            ->execute([$resident_data['user_id'], 'Your ID verification has been approved! You can now access all barangay services.']);
                        if (!empty($resident_data['email'])) {
                            $emailResult = send_id_verification_email(
                                $resident_data['email'],
                                'verified',
                                [
                                    'full_name' => $resident_data['full_name'],
                                    'verification_notes' => null
                                ]
                            );
                            if (!$emailResult['success']) {
                                // Log email error but don't fail the verification
                                error_log('Failed to send verification email: ' . $emailResult['message']);
                            }
                        }
                        
                        // Send verification SMS
                        if (!empty($resident_data['phone'])) {
                            $smsResult = send_id_verification_sms(
                                $resident_data['phone'],
                                'verified',
                                [
                                    'full_name' => $resident_data['full_name'],
                                    'verification_notes' => null
                                ]
                            );
                            if (!$smsResult['success']) {
                                // Log SMS error but don't fail the verification
                                error_log('Failed to send verification SMS: ' . $smsResult['message']);
                            }
                        }

                        // Auto-create or sync with Resident Records (census)
                        $check_sql = 'SELECT id FROM resident_records WHERE ';
                        $check_params = [];
                        if (!empty($resident_data['email'])) {
                            $check_sql .= 'email = ? OR ';
                            $check_params[] = $resident_data['email'];
                        }
                        $check_sql .= 'full_name = ? LIMIT 1';
                        $check_params[] = $resident_data['full_name'];
                        
                        $check_stmt = $pdo->prepare($check_sql);
                        $check_stmt->execute($check_params);
                        if (!$check_stmt->fetch()) {
                            // Doesn't exist in census, create it
                            $insert_records_stmt = $pdo->prepare('
                                INSERT INTO resident_records (
                                    email, first_name, last_name, middle_name, suffix, full_name, 
                                    address, phone, birthdate, sex, citizenship, civil_status, 
                                    purok, is_active, created_by, is_solo_parent, is_pwd, is_senior
                                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?,?)
                            ');
                            $insert_records_stmt->execute([
                                $resident_data['email'] ?: null,
                                $resident_data['first_name'],
                                $resident_data['last_name'],
                                $resident_data['middle_name'] ?: null,
                                $resident_data['suffix'] ?: null,
                                $resident_data['full_name'],
                                $resident_data['address'],
                                $resident_data['phone'] ?: null,
                                $resident_data['birthdate'] ?: null,
                                $resident_data['sex'] ?: null,
                                $resident_data['citizenship'] ?: null,
                                $resident_data['civil_status'] ?: null,
                                $resident_data['purok'] ?: null,
                                $_SESSION['user_id'],
                                $resident_data['is_solo_parent'] ?? 0,
                                $resident_data['is_pwd'] ?? 0,
                                $resident_data['is_senior'] ?? 0
                            ]);
                        }
                    } elseif ($action === 'reject') {
                        $notes = trim($_POST['rejection_notes'] ?? '');
                        if ($notes === '') {
                            $errors[] = 'Rejection notes are required';
                        } else {
                            $stmt = $pdo->prepare('UPDATE residents SET verification_status = \'rejected\', verification_notes = ?, verified_at = NOW(), verified_by = ? WHERE id = ?');
                            $stmt->execute([$notes, $_SESSION['user_id'], $resident_id]);
                            $success = 'Resident verification rejected';

                        // In-app notification to resident
                        $pdo->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "verification_update", "ID Verification Rejected", ?)')
                            ->execute([$resident_data['user_id'], 'Your ID verification was rejected. Reason: ' . $notes . '. Please resubmit with correct documents.']);
                            if (!empty($resident_data['email'])) {
                                $emailResult = send_id_verification_email(
                                    $resident_data['email'],
                                    'rejected',
                                    [
                                        'full_name' => $resident_data['full_name'],
                                        'verification_notes' => $notes
                                    ]
                                );
                                if (!$emailResult['success']) {
                                    // Log email error but don't fail the rejection
                                    error_log('Failed to send rejection email: ' . $emailResult['message']);
                                }
                            }
                            
                            // Send rejection SMS
                            if (!empty($resident_data['phone'])) {
                                $smsResult = send_id_verification_sms(
                                    $resident_data['phone'],
                                    'rejected',
                                    [
                                        'full_name' => $resident_data['full_name'],
                                        'verification_notes' => $notes
                                    ]
                                );
                                if (!$smsResult['success']) {
                                    // Log SMS error but don't fail the rejection
                                    error_log('Failed to send rejection SMS: ' . $smsResult['message']);
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = 'Server error. Please try again.';
                error_log('ID verification error: ' . $e->getMessage());
            }
        }
    }
}

// Get residents with pending verification
$pending_residents = $pdo->query('
    SELECT r.*, u.full_name, u.email, u.created_at as user_created_at
    FROM residents r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.verification_status = \'pending\' 
    ORDER BY r.id DESC
')->fetchAll();

// Get all residents for overview
$all_residents = $pdo->query('
    SELECT r.*, u.full_name, u.email, u.created_at as user_created_at,
           admin.full_name as verified_by_name
    FROM residents r 
    JOIN users u ON r.user_id = u.id 
    LEFT JOIN users admin ON r.verified_by = admin.id
    ORDER BY r.id DESC
')->fetchAll();
?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<!-- Pending Verifications -->
<?php if (!empty($pending_residents)): ?>
<div class="admin-table mb-4">
    <div class="p-3 border-bottom d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Verifications (<?php echo count($pending_residents); ?>)</h5>
            <p class="text-muted mb-0">Review and verify uploaded ID documents from residents</p>
        </div>
        <div class="input-group" style="max-width: 300px;">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" id="pendingSearch" class="form-control" placeholder="Search pending...">
        </div>
    </div>
    <div class="p-3">
        <div class="row">
            <?php foreach ($pending_residents as $resident): ?>
                <div class="col-md-6 mb-4">
                    <div class="card border-warning h-100">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($resident['full_name'] ?? ''); ?></h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Email:</strong> <?php echo htmlspecialchars($resident['email'] ?? ''); ?>
                            </div>
                            <div class="mb-3">
                                <strong>Address:</strong> <?php echo htmlspecialchars($resident['address'] ?? ''); ?>
                            </div>
                            <div class="mb-3">
                                <strong>Registered:</strong> <?php echo date('M j, Y', strtotime($resident['user_created_at'])); ?>
                            </div>
                            
                            <?php if ($resident['id_front_path'] || $resident['id_back_path']): ?>
                                <div class="mb-3">
                                    <strong>Uploaded ID:</strong><br>
                                    <div class="d-flex gap-2 mt-1">
                                        <?php if ($resident['id_front_path']): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="showIdModal('front', '../uploads/id_documents/<?php echo htmlspecialchars($resident['id_front_path']); ?>', '<?php echo htmlspecialchars($resident['full_name'] ?? ''); ?>', 'Front ID')">
                                                <i class="fas fa-eye"></i> Front
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($resident['id_back_path']): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="showIdModal('back', '../uploads/id_documents/<?php echo htmlspecialchars($resident['id_back_path']); ?>', '<?php echo htmlspecialchars($resident['full_name'] ?? ''); ?>', 'Back ID')">
                                                <i class="fas fa-eye"></i> Back
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php elseif ($resident['id_document_path']): ?>
                                <div class="mb-3">
                                    <strong>Uploaded Document:</strong><br>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="showIdModal('front', '../uploads/id_documents/<?php echo htmlspecialchars($resident['id_document_path'] ?? ''); ?>', '<?php echo htmlspecialchars($resident['full_name'] ?? ''); ?>', 'ID Document')">
                                        <i class="fas fa-eye"></i> View Document
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex gap-2">
                                <form method="post" class="d-inline admin-confirm-form" data-action-name="Verify Resident">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="verify">
                                    <input type="hidden" name="resident_id" value="<?php echo $resident['id']; ?>">
                                    <button type="button" class="btn btn-success btn-sm btn-confirm-submit" title="Verify">
                                        <i class="fas fa-check"></i> Verify
                                    </button>
                                </form>
                                
                                <button type="button" class="btn btn-danger btn-sm" 
                                        onclick="showRejectModal(<?php echo $resident['id']; ?>)">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> No pending verifications at this time.
</div>
<?php endif; ?>

<!-- All Residents Overview -->
<div class="admin-table">
    <div class="p-3 border-bottom d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Residents Verification Status</h5>
            <p class="text-muted mb-0">Complete overview of all resident verification statuses</p>
        </div>
        <div class="input-group" style="max-width: 300px;">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" id="allResidentsSearch" class="form-control" placeholder="Search residents...">
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Address</th>
                    <th>Status</th>
                    <th>Verified By</th>
                    <th>Verified At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_residents as $resident): ?>
                    <tr>
                        <td><span class="badge bg-secondary">#<?php echo $resident['id']; ?></span></td>
                        <td><?php echo htmlspecialchars($resident['full_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($resident['email'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($resident['address'] ?? ''); ?></td>
                        <td>
                            <span class="badge 
                                <?php 
                                switch($resident['verification_status']) {
                                    case 'verified': echo 'bg-success'; break;
                                    case 'rejected': echo 'bg-danger'; break;
                                    default: echo 'bg-warning';
                                }
                                ?>">
                                <?php echo ucfirst($resident['verification_status']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($resident['verified_by_name'] ?? 'N/A'); ?></td>
                        <td>
                            <?php if ($resident['verified_at']): ?>
                                <?php echo date('M j, Y g:i A', strtotime($resident['verified_at'])); ?>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if ($resident['id_front_path']): ?>
                                    <button type="button" class="btn btn-xs btn-outline-primary" title="View Front"
                                            onclick="showIdModal('front', '../uploads/id_documents/<?php echo htmlspecialchars($resident['id_front_path']); ?>', '<?php echo htmlspecialchars($resident['full_name'] ?? ''); ?>', 'Front ID')">
                                        F
                                    </button>
                                <?php endif; ?>
                                <?php if ($resident['id_back_path']): ?>
                                    <button type="button" class="btn btn-xs btn-outline-primary" title="View Back"
                                            onclick="showIdModal('back', '../uploads/id_documents/<?php echo htmlspecialchars($resident['id_back_path']); ?>', '<?php echo htmlspecialchars($resident['full_name'] ?? ''); ?>', 'Back ID')">
                                        B
                                    </button>
                                <?php endif; ?>
                                <?php if (!$resident['id_front_path'] && !$resident['id_back_path'] && $resident['id_document_path']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="showIdModal('front', '../uploads/id_documents/<?php echo htmlspecialchars($resident['id_document_path']); ?>', '<?php echo htmlspecialchars($resident['full_name'] ?? ''); ?>', 'ID Document')">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                            
                            <?php if ($resident['verification_status'] === 'pending'): ?>
                                <form method="post" class="d-inline admin-confirm-form" data-action-name="Verify Resident">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="verify">
                                    <input type="hidden" name="resident_id" value="<?php echo $resident['id']; ?>">
                                    <button type="button" class="btn btn-sm btn-success btn-confirm-submit" title="Verify">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="showRejectModal(<?php echo $resident['id']; ?>)">
                                    <i class="fas fa-times"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ID Document Modal -->
<div class="modal fade" id="idModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-id-card me-2"></i>ID Document - <span id="idModalResidentName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="idModalImage" src="" alt="ID Document" class="img-fluid" style="max-height: 70vh; border: 1px solid #dee2e6; border-radius: 4px;">
            </div>
            <div class="modal-footer">
                <a id="idModalDownloadLink" href="" download class="btn btn-outline-primary">
                    <i class="fas fa-download"></i> Download
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>Reject Verification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="rejectForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="resident_id" id="reject_resident_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason *</label>
                        <textarea name="rejection_notes" class="form-control" rows="4" 
                                  placeholder="Please provide a reason for rejection..." required></textarea>
                        <div class="form-text">This message will be shown to the resident.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Verification</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showIdModal(side, imagePath, residentName, sideTitle) {
    document.getElementById('idModalImage').src = imagePath;
    document.getElementById('idModalResidentName').textContent = residentName + ' (' + sideTitle + ')';
    document.getElementById('idModalDownloadLink').href = imagePath;
    new bootstrap.Modal(document.getElementById('idModal')).show();
}

function showRejectModal(residentId) {
    document.getElementById('reject_resident_id').value = residentId;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

// Search: Pending Verifications (cards)
(function() {
    const pendingInput = document.getElementById('pendingSearch');
    if (pendingInput) {
        pendingInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const container = pendingInput.closest('.admin-table');
            if (!container) return;
            container.querySelectorAll('.col-md-6').forEach(function(card) {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }
})();

// Search: All Residents table
(function() {
    const tableInput = document.getElementById('allResidentsSearch');
    if (tableInput) {
        tableInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const container = tableInput.closest('.admin-table');
            if (!container) return;
            container.querySelectorAll('tbody tr').forEach(function(row) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
