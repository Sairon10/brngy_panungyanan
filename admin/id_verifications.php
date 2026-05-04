<?php
require_once __DIR__ . '/../config.php';
if (!is_admin())
    redirect('../index.php');

$page_title = 'ID Verification Management';
require_once __DIR__ . '/header.php';

$pdo = get_db_connection();
$errors = [];
$success = '';

// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_validate()) {
        $errors[] = 'Invalid CSRF token. Please refresh the page and try again.';
    } else {
        $target_id = (int) ($_POST['target_id'] ?? 0);
        $target_type = $_POST['target_type'] ?? 'resident';
        $action = $_POST['action'];

        if ($target_id <= 0) {
            $errors[] = 'Invalid ID';
        } else {
            try {
                if ($target_type === 'resident') {
                    $resident_stmt = $pdo->prepare('SELECT r.*, u.full_name, u.email, r.phone, r.user_id FROM residents r JOIN users u ON r.user_id = u.id WHERE r.id = ?');
                    $resident_stmt->execute([$target_id]);
                    $data = $resident_stmt->fetch();
                    $table = 'residents';
                } else {
                    $fm_stmt = $pdo->prepare('SELECT fm.*, u.email, u.full_name as head_name, fm.user_id, r.phone FROM family_members fm JOIN users u ON fm.user_id = u.id LEFT JOIN residents r ON r.user_id = u.id WHERE fm.id = ?');
                    $fm_stmt->execute([$target_id]);
                    $data = $fm_stmt->fetch();
                    $table = 'family_members';
                }

                if (!$data) {
                    $errors[] = 'Record not found';
                } else {
                    if ($action === 'verify') {
                        $stmt = $pdo->prepare("UPDATE {$table} SET verification_status = 'verified', verified_at = NOW(), verified_by = ? WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id'], $target_id]);
                        $success = 'Verification approved successfully';

                        // In-app notification
                        $notif_msg = ($target_type === 'resident') ? "Your ID verification has been approved!" : "The ID verification for family member " . $data['full_name'] . " has been approved!";
                        $pdo->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "verification_update", "ID Verification Approved", ?)')
                            ->execute([$data['user_id'], $notif_msg]);

                        if (!empty($data['email']) && function_exists('send_id_verification_email')) {
                            send_id_verification_email($data['email'], 'verified', ['full_name' => $data['full_name']]);
                        }

                        if (!empty($data['phone']) && function_exists('send_id_verification_sms')) {
                            $sms_result = send_id_verification_sms($data['phone'], 'verified', [
                                'full_name' => $data['full_name'],
                                'verification_notes' => ''
                            ]);
                            if (!$sms_result['success']) {
                                $success .= " (Email sent, but SMS failed: " . $sms_result['message'] . ")";
                            } else {
                                $success .= " (Email and SMS sent)";
                            }
                        } else {
                            $success .= " (Email sent, no phone number for SMS)";
                        }
                    } elseif ($action === 'reject') {
                        $notes = trim($_POST['rejection_notes'] ?? '');
                        if ($notes === '') {
                            $errors[] = 'Rejection notes are required';
                        } else {
                            $stmt = $pdo->prepare("UPDATE {$table} SET verification_status = 'rejected', verification_notes = ?, verified_at = NOW(), verified_by = ? WHERE id = ?");
                            $stmt->execute([$notes, $_SESSION['user_id'], $target_id]);
                            $success = 'Verification rejected';

                            $notif_msg = ($target_type === 'resident') ? "Your ID verification was rejected. Reason: " . $notes : "The ID verification for family member " . $data['full_name'] . " was rejected. Reason: " . $notes;
                            $pdo->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "verification_update", "ID Verification Rejected", ?)')
                                ->execute([$data['user_id'], $notif_msg]);

                            if (!empty($data['email']) && function_exists('send_id_verification_email')) {
                                send_id_verification_email($data['email'], 'rejected', [
                                    'full_name' => $data['full_name'],
                                    'rejection_notes' => $notes
                                ]);
                            }

                            if (!empty($data['phone']) && function_exists('send_id_verification_sms')) {
                                $sms_result = send_id_verification_sms($data['phone'], 'rejected', [
                                    'full_name' => $data['full_name'],
                                    'verification_notes' => $notes
                                ]);
                                if (!$sms_result['success']) {
                                    $success .= " (Email sent, but SMS failed: " . $sms_result['message'] . ")";
                                } else {
                                    $success .= " (Email and SMS sent)";
                                }
                            } else {
                                $success .= " (Email sent, no phone number for SMS)";
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = 'Server error: ' . $e->getMessage();
            }
        }
    }
}

// Get filter status
$status_filter = $_GET['status'] ?? 'all';

// Pagination logic
$limit = 10;
$page = (int) ($_GET['page'] ?? 1);
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

// Base query for data
// Base query for data using UNION
$base_query = "
    SELECT 
        'resident' as target_type,
        r.id, 
        u.full_name, 
        u.email, 
        r.phone, 
        r.address,
        r.verification_status,
        r.id_front_path,
        r.id_back_path,
        r.id_document_path,
        r.address_on_id,
        r.id_type,
        r.user_id,
        u.created_at
    FROM residents r 
    JOIN users u ON r.user_id = u.id 
    WHERE u.role = 'resident'

    UNION ALL

    SELECT 
        'family_member' as target_type,
        fm.id,
        fm.full_name,
        u.email as email,
        r_head.phone as phone,
        r_head.address as address,
        fm.verification_status,
        fm.id_front_path,
        fm.id_back_path,
        NULL as id_document_path,
        '' as address_on_id,
        fm.id_type as id_type,
        fm.user_id,
        fm.created_at
    FROM family_members fm
    JOIN users u ON fm.user_id = u.id
    LEFT JOIN residents r_head ON fm.user_id = r_head.user_id
";

$params = [];
$filter_sql = "";
if ($status_filter !== 'all') {
    $filter_sql = " WHERE verification_status = ? ";
    $params[] = $status_filter;
}

$count_query = "SELECT COUNT(*) FROM ({$base_query}) as combined {$filter_sql}";
$total_records = $pdo->prepare($count_query);
$total_records->execute($params);
$total_records = $total_records->fetchColumn();

$total_pages = ceil($total_records / $limit);

$final_query = "SELECT * FROM ({$base_query}) as combined {$filter_sql} ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$residents = $pdo->prepare($final_query);
$residents->execute($params);
$residents_data = $residents->fetchAll();
?>

<!-- Content Area -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold text-dark mb-1">ID Verification</h4>
                <p class="text-muted small mb-0">Manage and review resident identity documents</p>
            </div>
            <div class="btn-group shadow-sm bg-white rounded-3 p-1">
                <a href="?status=all"
                    class="btn btn-sm px-3 <?php echo $status_filter === 'all' ? 'btn-primary shadow-sm' : 'btn-light border-0'; ?>">All</a>
                <a href="?status=pending"
                    class="btn btn-sm px-3 <?php echo $status_filter === 'pending' ? 'btn-primary shadow-sm' : 'btn-light border-0'; ?>">Pending</a>
                <a href="?status=verified"
                    class="btn btn-sm px-3 <?php echo $status_filter === 'verified' ? 'btn-primary shadow-sm' : 'btn-light border-0'; ?>">Verified</a>
                <a href="?status=rejected"
                    class="btn btn-sm px-3 <?php echo $status_filter === 'rejected' ? 'btn-primary shadow-sm' : 'btn-light border-0'; ?>">Rejected</a>
            </div>
        </div>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4 animate__animated animate__fadeIn">
        <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4">
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
                <li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark">Resident List</h5>
        <div class="input-group" style="max-width: 250px;">
            <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted small"></i></span>
            <input type="text" id="tableSearch" class="form-control bg-light border-0 small"
                placeholder="Search residents...">
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="idVerificationsTable">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4" style="width: 50px;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAll">
                        </div>
                    </th>
                    <th class="text-uppercase small fw-bold text-muted" style="font-size: 0.7rem;">Name</th>
                    <th class="text-uppercase small fw-bold text-muted text-center" style="font-size: 0.7rem;">Type</th>
                    <th class="text-uppercase small fw-bold text-muted" style="font-size: 0.7rem;">Address</th>
                    <th class="text-uppercase small fw-bold text-muted text-center" style="font-size: 0.7rem;">Contact
                        Number</th>
                    <th class="text-uppercase small fw-bold text-muted text-center" style="font-size: 0.7rem;">Status
                    </th>
                    <th class="text-uppercase small fw-bold text-muted text-center pe-4" style="font-size: 0.7rem;">
                        Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($residents_data)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fas fa-user-slash fs-1 mb-3 d-block opacity-25"></i>
                            No residents found matching the criteria.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($residents_data as $res): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="form-check">
                                <input class="form-check-input row-checkbox" type="checkbox"
                                    value="<?php echo $res['id']; ?>">
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div>
                                    <h6 class="mb-0 text-dark small"><?php echo htmlspecialchars($res['full_name']); ?></h6>
                                    <span class="text-muted" style="font-size: 0.65rem;">
                                        <?php echo $res['target_type'] === 'resident' ? 'Account Owner' : 'Family Member'; ?>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="d-flex flex-column gap-1 align-items-center">
                                <span class="badge bg-light text-dark border-0 fw-medium px-2 py-1"
                                    style="font-size: 0.7rem; background-color: #f3f4f6 !important;">
                                    <?php echo htmlspecialchars($res['id_type'] ?? 'Unknown ID'); ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="text-truncate text-muted small" style="max-width: 200px;"
                                title="<?php echo htmlspecialchars($res['address']); ?>">
                                <?php echo htmlspecialchars($res['address']); ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <span
                                class="text-dark fw-medium small"><?php echo htmlspecialchars($res['phone'] ?: '---'); ?></span>
                        </td>
                        <td class="text-center">
                            <?php
                            $status = $res['verification_status'];
                            $badge_class = 'bg-warning-subtle text-warning border-warning-subtle';
                            if ($status === 'verified')
                                $badge_class = 'bg-success-subtle text-success border-success-subtle';
                            if ($status === 'rejected')
                                $badge_class = 'bg-danger-subtle text-danger border-danger-subtle';
                            ?>
                            <span class="badge rounded-pill border px-3 py-1 <?php echo $badge_class; ?>"
                                style="font-size: 0.7rem;">
                                <?php echo ucfirst($status); ?>
                            </span>
                        </td>
                        <td class="text-center pe-4">
                            <div class="d-flex gap-2 justify-content-center align-items-center">
                                <button type="button" class="btn btn-action btn-outline-primary" onclick="viewID(<?php echo htmlspecialchars(json_encode([
                                    'name' => $res['full_name'],
                                    'front' => ($res['id_front_path'] ?? '') ? '../uploads/id_documents/' . $res['id_front_path'] : null,
                                    'back' => ($res['id_back_path'] ?? '') ? '../uploads/id_documents/' . $res['id_back_path'] : null,
                                    'legacy' => ($res['id_document_path'] ?? '') ? '../uploads/id_documents/' . $res['id_document_path'] : null,
                                    'address_on_id' => $res['address_on_id'] ?? ''
                                ])); ?>)" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>

                                <?php if ($status === 'pending'): ?>
                                    <form method="post" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="verify">
                                        <input type="hidden" name="target_id" value="<?php echo $res['id']; ?>">
                                        <input type="hidden" name="target_type" value="<?php echo $res['target_type']; ?>">
                                        <button type="button" class="btn btn-action btn-outline-success" title="Approve"
                                            onclick="confirmVerification(this.form)">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>

                                    <button type="button" class="btn btn-action btn-outline-danger"
                                        onclick="rejectID(<?php echo $res['id']; ?>, '<?php echo $res['target_type']; ?>')"
                                        title="Reject">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <!-- Pagination Footer -->
    <div class="card-footer bg-white border-0 py-3 px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div class="text-muted small">
                Showing <?php echo count($residents_data); ?> of <?php echo $total_records; ?> residents
            </div>
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0 gap-1">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link border-0 rounded-circle shadow-sm"
                                href="?status=<?php echo $status_filter; ?>&page=<?php echo $page - 1; ?>"><i
                                    class="fas fa-chevron-left"></i></a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link border-0 rounded-circle shadow-sm px-3"
                                    href="?status=<?php echo $status_filter; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link border-0 rounded-circle shadow-sm"
                                href="?status=<?php echo $status_filter; ?>&page=<?php echo $page + 1; ?>"><i
                                    class="fas fa-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View ID Modal -->
<div class="modal fade" id="viewIDModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-id-card me-2 text-primary"></i>ID
                    Verification Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-muted mb-1">Resident Name</label>
                        <div id="modalResidentName" class="h6 fw-bold text-dark mb-3"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-muted mb-1">Address on ID</label>
                        <div id="modalAddressOnID" class="p-2 bg-light rounded-3 border small fw-medium"></div>
                    </div>
                </div>
                <hr class="my-4 opacity-50">
                <div class="row g-3" id="modalImagesContainer">
                    <!-- Images will be injected here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectIDModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold text-danger"><i class="fas fa-times-circle me-2"></i>Reject Verification
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="rejectForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="target_id" id="rejectTargetID">
                <input type="hidden" name="target_type" id="rejectTargetType">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Reason for Rejection <span
                                class="text-danger">*</span></label>
                        <textarea name="rejection_notes" id="rejectionNotes"
                            class="form-control rounded-3 bg-light border-0 p-3" rows="4"
                            placeholder="Why is this being rejected?" required></textarea>
                        <div class="form-text small mt-2">The resident will see this message.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger rounded-pill px-4 fw-bold"
                        onclick="confirmRejection()">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function confirmVerification(form) {
        Swal.fire({
            title: 'Verify Resident?',
            text: "Are you sure you want to verify this resident's identity?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Verify',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }

    function confirmRejection() {
        const notes = document.getElementById('rejectionNotes').value;
        if (notes.trim() === '') {
            Swal.fire('Error', 'Rejection notes are required', 'error');
            return;
        }
        document.getElementById('rejectForm').submit();
    }

    function viewID(data) {
        document.getElementById('modalResidentName').textContent = data.name;
        document.getElementById('modalAddressOnID').textContent = data.address_on_id || 'Not provided';

        const container = document.getElementById('modalImagesContainer');
        container.innerHTML = '';

        if (data.front) addImage(data.front, 'Front ID View');
        if (data.back) addImage(data.back, 'Back ID View');
        if (!data.front && !data.back && data.legacy) addImage(data.legacy, 'Uploaded ID Document');

        if (container.innerHTML === '') {
            container.innerHTML = '<div class="col-12 text-center py-4 text-muted">No document images found.</div>';
        }

        new bootstrap.Modal(document.getElementById('viewIDModal')).show();
    }

    function addImage(path, label) {
        const col = document.createElement('div');
        col.className = 'col-md-6';
        col.innerHTML = `
        <div class="bg-light p-2 rounded-3 border h-100 text-center">
            <label class="small fw-bold text-uppercase text-muted mb-2 d-block" style="font-size: 0.65rem;">${label}</label>
            <div class="position-relative overflow-hidden rounded-2" style="height: 200px;">
                <img src="${path}" class="w-100 h-100 object-fit-contain cursor-pointer" 
                     onclick="window.open('${path}', '_blank')">
            </div>
            <a href="${path}" download class="btn btn-link btn-sm text-decoration-none mt-2 small"><i class="fas fa-download me-1"></i>Download</a>
        </div>
    `;
        document.getElementById('modalImagesContainer').appendChild(col);
    }

    function rejectID(id, type) {
        document.getElementById('rejectTargetID').value = id;
        document.getElementById('rejectTargetType').value = type;
        new bootstrap.Modal(document.getElementById('rejectIDModal')).show();
    }

    // Select All
    document.getElementById('selectAll')?.addEventListener('change', function () {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
    });

    // Search
    document.getElementById('tableSearch')?.addEventListener('keyup', function () {
        const value = this.value.toLowerCase();
        const rows = document.querySelectorAll('#idVerificationsTable tbody tr');
        rows.forEach(row => {
            if (row.cells.length > 1) {
                row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
            }
        });
    });

    <?php if ($success): ?>
        Swal.fire({
            title: 'Success!',
            text: '<?php echo htmlspecialchars($success); ?>',
            icon: 'success',
            confirmButtonColor: '#0f766e',
            borderRadius: '15px'
        });
    <?php endif; ?>

    <?php if ($errors): ?>
        Swal.fire({
            title: 'Wait!',
            html: '<ul class="text-start small mb-0"><?php foreach ($errors as $e)
                echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>',
            icon: 'error',
            confirmButtonColor: '#0f766e',
            borderRadius: '15px'
        });
    <?php endif; ?>
</script>

<style>
    .cursor-pointer {
        cursor: pointer;
    }

    .object-fit-contain {
        object-fit: contain;
    }

    .avatar-sm {
        background-color: #f0f9ff;
    }

    .table thead th {
        vertical-align: middle;
    }

    .shadow-sm {
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
    }

    .btn-action {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.2s ease;
        border: 1px solid #e5e7eb;
    }

    .btn-action i {
        font-size: 0.85rem;
    }

    .btn-action:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .btn-outline-primary {
        color: #2563eb;
        border-color: #e5e7eb;
        background: white;
    }

    .btn-outline-primary:hover {
        background: #2563eb;
        color: white;
        border-color: #2563eb;
    }

    .btn-outline-success {
        color: #16a34a;
        border-color: #e5e7eb;
        background: white;
    }

    .btn-outline-success:hover {
        background: #16a34a;
        color: white;
        border-color: #16a34a;
    }

    .btn-outline-danger {
        color: #dc2626;
        border-color: #e5e7eb;
        background: white;
    }

    .btn-outline-danger:hover {
        background: #dc2626;
        color: white;
        border-color: #dc2626;
    }

    /* Pagination styling */
    .pagination .page-link {
        color: #4b5563;
        font-weight: 500;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 2px;
    }

    .pagination .page-item.active .page-link {
        background-color: #0f766e;
        color: white;
    }

    .pagination .page-item.disabled .page-link {
        background-color: #f9fafb;
        color: #9ca3af;
    }
</style>

<?php require_once __DIR__ . '/footer.php'; ?>