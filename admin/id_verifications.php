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
        $resident_id = (int) ($_POST['resident_id'] ?? 0);
        $action = $_POST['action'];

        if ($resident_id <= 0) {
            $errors[] = 'Invalid resident ID';
        } else {
            try {
                // Get resident and user information
                $resident_stmt = $pdo->prepare('
                    SELECT r.*, u.full_name, u.email, r.phone, r.user_id 
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

                        // In-app notification
                        $pdo->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "verification_update", "ID Verification Approved", "Your ID verification has been approved!")')
                            ->execute([$resident_data['user_id']]);

                        if (function_exists('send_id_verification_email')) {
                            send_id_verification_email($resident_data['email'], 'verified', ['full_name' => $resident_data['full_name']]);
                        }
                    } elseif ($action === 'reject') {
                        $notes = trim($_POST['rejection_notes'] ?? '');
                        if ($notes === '') {
                            $errors[] = 'Rejection notes are required';
                        } else {
                            $stmt = $pdo->prepare('UPDATE residents SET verification_status = \'rejected\', verification_notes = ?, verified_at = NOW(), verified_by = ? WHERE id = ?');
                            $stmt->execute([$notes, $_SESSION['user_id'], $resident_id]);
                            $success = 'Resident verification rejected';

                            $pdo->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "verification_update", "ID Verification Rejected", ?)')
                                ->execute([$resident_data['user_id'], 'Your ID verification was rejected. Reason: ' . $notes]);
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
$query = "SELECT r.*, u.full_name, u.email, u.role FROM residents r JOIN users u ON r.user_id = u.id WHERE u.role = 'resident'";
$params = [];

if ($status_filter !== 'all') {
    $query .= ' AND r.verification_status = ?';
    $params[] = $status_filter;
}
$query .= ' ORDER BY r.id DESC';

$residents = $pdo->prepare($query);
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
                <a href="?status=all" class="btn btn-sm px-3 <?php echo $status_filter === 'all' ? 'btn-primary shadow-sm' : 'btn-light border-0'; ?>">All</a>
                <a href="?status=pending" class="btn btn-sm px-3 <?php echo $status_filter === 'pending' ? 'btn-primary shadow-sm' : 'btn-light border-0'; ?>">Pending</a>
                <a href="?status=verified" class="btn btn-sm px-3 <?php echo $status_filter === 'verified' ? 'btn-primary shadow-sm' : 'btn-light border-0'; ?>">Verified</a>
                <a href="?status=rejected" class="btn btn-sm px-3 <?php echo $status_filter === 'rejected' ? 'btn-primary shadow-sm' : 'btn-light border-0'; ?>">Rejected</a>
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
            <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark">Resident List</h5>
        <div class="input-group" style="max-width: 250px;">
            <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted small"></i></span>
            <input type="text" id="tableSearch" class="form-control bg-light border-0 small" placeholder="Search residents...">
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
                    <th class="text-uppercase small fw-bold text-muted text-center" style="font-size: 0.7rem;">Contact Number</th>
                    <th class="text-uppercase small fw-bold text-muted text-center" style="font-size: 0.7rem;">Status</th>
                    <th class="text-uppercase small fw-bold text-muted text-center pe-4" style="font-size: 0.7rem;">Action</th>
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
                                <input class="form-check-input row-checkbox" type="checkbox" value="<?php echo $res['id']; ?>">
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div>
                                    <h6 class="mb-0 text-dark small"><?php echo htmlspecialchars($res['full_name']); ?></h6>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="d-flex flex-column gap-1 align-items-center">
                                <span class="badge bg-light text-dark border-0 fw-medium px-2 py-1" style="font-size: 0.7rem; background-color: #f3f4f6 !important;">
                                    <?php echo htmlspecialchars($res['id_type'] ?? 'Unknown ID'); ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="text-truncate text-muted small" style="max-width: 200px;" title="<?php echo htmlspecialchars($res['address']); ?>">
                                <?php echo htmlspecialchars($res['address']); ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="text-dark fw-medium small"><?php echo htmlspecialchars($res['phone'] ?: '---'); ?></span>
                        </td>
                        <td class="text-center">
                            <?php
                            $status = $res['verification_status'];
                            $badge_class = 'bg-warning-subtle text-warning border-warning-subtle';
                            if ($status === 'verified') $badge_class = 'bg-success-subtle text-success border-success-subtle';
                            if ($status === 'rejected') $badge_class = 'bg-danger-subtle text-danger border-danger-subtle';
                            ?>
                            <span class="badge rounded-pill border px-3 py-1 <?php echo $badge_class; ?>" style="font-size: 0.7rem;">
                                <?php echo ucfirst($status); ?>
                            </span>
                        </td>
                        <td class="text-center pe-4">
                            <div class="d-flex gap-2 justify-content-center align-items-center">
                                <button type="button" class="btn btn-action btn-outline-primary" 
                                        onclick="viewID(<?php echo htmlspecialchars(json_encode([
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
                                        <input type="hidden" name="resident_id" value="<?php echo $res['id']; ?>">
                                        <button type="submit" class="btn btn-action btn-outline-success" title="Approve" onclick="return confirm('Verify this resident?')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>

                                    <button type="button" class="btn btn-action btn-outline-danger" 
                                            onclick="rejectID(<?php echo $res['id']; ?>)" title="Reject">
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
</div>

<!-- View ID Modal -->
<div class="modal fade" id="viewIDModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-id-card me-2 text-primary"></i>ID Verification Details</h5>
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
                <h5 class="modal-title fw-bold text-danger"><i class="fas fa-times-circle me-2"></i>Reject Verification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="resident_id" id="rejectResidentID">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea name="rejection_notes" class="form-control rounded-3 bg-light border-0 p-3" rows="4" placeholder="Why is this being rejected?" required></textarea>
                        <div class="form-text small mt-2">The resident will see this message.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
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

function rejectID(id) {
    document.getElementById('rejectResidentID').value = id;
    new bootstrap.Modal(document.getElementById('rejectIDModal')).show();
}

// Select All
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
});

// Search
document.getElementById('tableSearch')?.addEventListener('keyup', function() {
    const value = this.value.toLowerCase();
    const rows = document.querySelectorAll('#idVerificationsTable tbody tr');
    rows.forEach(row => {
        if (row.cells.length > 1) {
            row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
        }
    });
});
</script>

<style>
.cursor-pointer { cursor: pointer; }
.object-fit-contain { object-fit: contain; }
.avatar-sm { background-color: #f0f9ff; }
.table thead th { vertical-align: middle; }
.shadow-sm { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important; }

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

.btn-outline-primary { color: #2563eb; border-color: #e5e7eb; background: white; }
.btn-outline-primary:hover { background: #2563eb; color: white; border-color: #2563eb; }

.btn-outline-success { color: #16a34a; border-color: #e5e7eb; background: white; }
.btn-outline-success:hover { background: #16a34a; color: white; border-color: #16a34a; }

.btn-outline-danger { color: #dc2626; border-color: #e5e7eb; background: white; }
.btn-outline-danger:hover { background: #dc2626; color: white; border-color: #dc2626; }
</style>

<?php require_once __DIR__ . '/footer.php'; ?>
