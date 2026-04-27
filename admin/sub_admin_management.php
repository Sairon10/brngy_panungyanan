<?php 
require_once __DIR__ . '/../config.php';
if (!is_admin() || $_SESSION['user_id'] != 1) redirect('../index.php');

$pdo = get_db_connection();
$info = '';
$error = '';

// Handle bulk deletion of sub-admin accounts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete_sub_admins') {
    if (csrf_validate()) {
        $selected_ids = $_POST['selected_ids'] ?? [];
        // Filter out self and System Admin (ID 1)
        $filtered_ids = array_filter($selected_ids, function($id) {
            return $id != $_SESSION['user_id'] && $id != 1;
        });

        if (!empty($filtered_ids)) {
            $placeholders = implode(',', array_fill(0, count($filtered_ids), '?'));
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders) AND role = 'admin'");
                $stmt->execute($filtered_ids);
                $info = 'Successfully deleted ' . count($filtered_ids) . ' sub-admin account(s).';
            } catch (Throwable $e) {
                $error = 'Error during bulk deletion: ' . $e->getMessage();
            }
        } else {
            $error = 'No valid accounts selected for deletion.';
        }
    }
}

// Handle deletion of sub-admin accounts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_sub_admin') {
    if (csrf_validate()) {
        $rid = (int)($_POST['record_id'] ?? 0);
        
        if ($rid > 0 && $rid != $_SESSION['user_id'] && $rid != 1) {
            try {
                $pdo->prepare('DELETE FROM users WHERE id = ? AND role = "admin"')->execute([$rid]);
                $info = 'Sub-admin account deleted successfully.';
            } catch (Throwable $e) {
                $error = 'Error deleting account: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Sub-admin Management';
$breadcrumb = [['title' => 'Sub-admin Management']];
require_once __DIR__ . '/header.php'; 

$search = trim($_GET['search'] ?? '');
$params = [];

$query = '
    SELECT u.*, r.address, r.phone 
    FROM users u 
    LEFT JOIN residents r ON r.user_id = u.id 
    WHERE u.role = "admin" AND u.id != 1 
';

if ($search !== '') {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR r.address LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= ' ORDER BY u.full_name ASC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$limit = 10;
$total_records = count($rows);
$total_pages = ceil($total_records / $limit);
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $limit;
$display_rows = array_slice($rows, $offset, $limit);
?>

<?php if ($info): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Success!',
                text: '<?php echo htmlspecialchars($info); ?>',
                icon: 'success',
                confirmButtonColor: '#14b8a6'
            });
        });
    </script>
<?php endif; ?>

<?php if ($error): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Error!',
                text: '<?php echo htmlspecialchars($error); ?>',
                icon: 'error',
                confirmButtonColor: '#14b8a6'
            });
        });
    </script>
<?php endif; ?>

<style>
    :root { --p-grad: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); --sys-teal: #14b8a6; }
    .staff-card { background: white; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: none; overflow: hidden; margin-top: 20px; }
    .table thead th { background: #f8fafc; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; font-weight: 700; color: #64748b; padding: 1.25rem 1rem; border-bottom: 1px solid #e2e8f0; }
    .table tbody td { padding: 1.25rem 1rem; vertical-align: middle; color: #475569; font-weight: 500; border-bottom: 1px solid #f1f5f9; }
    .action-btn { width: 35px; height: 35px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; transition: 0.3s; text-decoration: none; }
    .action-btn:hover { background: #f1f5f9; transform: translateY(-2px); }
    .search-box { border-radius: 12px; border: 1.5px solid #e2e8f0; padding: 0.6rem 1.2rem; transition: 0.3s; width: 250px; }
    .search-box:focus { border-color: #14b8a6; box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.1); outline: none; }
    .bg-teal { background: #14b8a6; }
    .fw-800 { font-weight: 800; }
</style>

<div class="mb-4">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h4 class="fw-800 mb-1" style="color: #1e293b;">Sub-admin Management</h4>
            <p class="text-muted small mb-0">View and manage administrative staff accounts</p>
        </div>
        <div class="col-md-6 text-md-end">
            <form method="GET" class="d-inline-flex gap-2 mt-3 mt-md-0">
                <input type="text" name="search" class="search-box small" placeholder="Search name or email..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn bg-teal text-white rounded-3 px-3"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>
</div>

<div class="card staff-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 40px;" class="ps-4">
                        <input type="checkbox" class="form-check-input" id="selectAllSubAdmins">
                    </th>
                    <th style="width: 50px;">#</th>
                    <th>Name</th>
                    <th>Address</th>
                    <th>Contact No.</th>
                    <th>Email</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" style="width: 140px;">
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            Actions
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light border-0 text-secondary p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Bulk Actions" style="width: 24px; height: 24px;">
                                    <i class="fas fa-ellipsis-v" style="font-size: 0.85rem;"></i>
                                </button>
                                <ul class="dropdown-menu shadow border-0 py-2 small">
                                    <li>
                                        <button type="button" class="dropdown-item py-2" onclick="bulkDeleteSubAdmins()">
                                            Delete
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($display_rows as $idx => $r): ?>
                <tr>
                    <td class="ps-4">
                        <?php if ($r['id'] != $_SESSION['user_id'] && $r['id'] != 1): ?>
                            <input type="checkbox" class="form-check-input sub-admin-checkbox" data-id="<?php echo $r['id']; ?>">
                        <?php else: ?>
                            <input type="checkbox" class="form-check-input" disabled>
                        <?php endif; ?>
                    </td>
                    <td class="font-monospace small text-muted"><?php echo $offset + $idx + 1; ?></td>
                    <td>
                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($r['full_name']); ?></div>
                        <?php if ($r['id'] == $_SESSION['user_id']): ?>
                            <span class="badge bg-info-subtle text-info px-2 py-0.5" style="font-size: 0.6rem;">YOU</span>
                        <?php endif; ?>
                    </td>
                    <td><div class="small text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($r['address'] ?: 'N/A'); ?></div></td>
                    <td><div class="small"><?php echo htmlspecialchars($r['phone'] ?: 'N/A'); ?></div></td>
                    <td><div class="small font-monospace"><?php echo htmlspecialchars($r['email']); ?></div></td>
                    <td class="text-center">
                        <?php if ($r['is_active']): ?>
                            <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-1" style="font-size: 0.75rem;">Active</span>
                        <?php else: ?>
                            <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-1" style="font-size: 0.75rem;">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <div class="d-flex justify-content-center align-items-center gap-3">
                            <a href="admin_info_view.php?id=<?php echo $r['id']; ?>" class="action-btn text-primary" title="View Profile">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="admin_info_view.php?id=<?php echo $r['id']; ?>&edit=1" class="action-btn text-warning" title="Edit Profile">
                                <i class="fas fa-edit"></i>
                            </a>
                            
                            <?php if ($r['id'] != $_SESSION['user_id'] && $r['id'] != 1): ?>
                            <form method="POST" class="m-0 delete-sub-admin-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_sub_admin">
                                <input type="hidden" name="record_id" value="<?php echo $r['id']; ?>">
                                <button type="button" class="action-btn border-0 bg-transparent text-danger btn-delete-sub-admin" 
                                        title="Delete Account"
                                        data-name="<?php echo htmlspecialchars($r['full_name']); ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="action-btn text-muted" title="Action restricted">
                                <i class="fas fa-user-lock"></i>
                            </span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($display_rows)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No sub-admin accounts found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="px-4 py-3 border-top bg-light d-flex justify-content-between align-items-center">
            <div class="text-secondary small font-monospace">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> records
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select All functionality
    const selectAll = document.getElementById('selectAllSubAdmins');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.sub-admin-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    }

    const deleteBtns = document.querySelectorAll('.btn-delete-sub-admin');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const form = this.closest('form');
            const name = this.dataset.name;
            
            Swal.fire({
                title: 'Delete Account?',
                text: `Are you sure you want to delete ${name}'s sub-admin account? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});

function bulkDeleteSubAdmins() {
    const selected = Array.from(document.querySelectorAll('.sub-admin-checkbox:checked')).map(cb => ({
        id: cb.dataset.id,
        name: cb.closest('tr').querySelector('.fw-bold').innerText
    }));

    if (selected.length === 0) {
        Swal.fire('No selection', 'Please select at least one sub-admin account.', 'warning');
        return;
    }

    Swal.fire({
        title: 'Bulk Delete',
        text: `Are you sure you want to delete ${selected.length} selected sub-admin account(s)? This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, delete all!'
    }).then((result) => {
        if (result.isConfirmed) {
            const bulkForm = document.createElement('form');
            bulkForm.method = 'POST';
            bulkForm.innerHTML = `
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="bulk_delete_sub_admins">
            `;
            selected.forEach(item => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_ids[]';
                input.value = item.id;
                bulkForm.appendChild(input);
            });
            document.body.appendChild(bulkForm);
            bulkForm.submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
