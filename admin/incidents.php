<?php
require_once __DIR__ . '/../config.php';
if (!is_admin())
    redirect('../index.php');

$pdo = get_db_connection();
$info = $_SESSION['info'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['info'], $_SESSION['error']);
?>

<style>
    .action-btn {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background-color: #f8f9fa;
        color: #495057;
        transition: all 0.2s ease;
        text-decoration: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        background-color: #ffffff;
    }

    .btn-view {
        color: #0d9488 !important;
    }

    /* Teal/Green */
    .btn-review {
        color: #0284c7 !important;
    }

    /* Blue */
    .btn-resolve {
        color: #16a34a !important;
    }

    /* Green */
    .btn-reject {
        color: #dc2626 !important;
    }

    /* Red */
    .btn-undo {
        color: #f59e0b !important;
    }

    /* Amber/Orange */

    .action-container form {
        display: inline-block;
        margin: 0;
        padding: 0;
    }

    .bg-teal-gradient {
        background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
    }
</style>

<?php
// 1. Process POST before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_validate()) {
        $id = (int) ($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';

        if ($action === 'update_status') {
            $status = $_POST['status'] ?? 'submitted';
            $notes = trim($_POST['rejection_notes'] ?? '');

            try {
                $pdo->prepare('UPDATE incidents SET status=? WHERE id=?')->execute([$status, $id]);

                // Fetch info for notifications
                $res_stmt = $pdo->prepare('
                    SELECT i.*, u.full_name as resident_name, u.email as resident_email, r.phone as resident_phone
                    FROM incidents i
                    JOIN users u ON u.id = i.user_id
                    LEFT JOIN residents r ON r.user_id = u.id
                    WHERE i.id = ?
                ');
                $res_stmt->execute([$id]);
                $incidentData = $res_stmt->fetch();

                if ($incidentData) {
                    $incidentData['notes'] = $notes;
                    $displayStatus = ($status === 'submitted' ? 'Pending' : ($status === 'closed' ? 'Rejected' : ucfirst(str_replace('_', ' ', $status))));

                    // Notifications
                    $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_incident_id) VALUES (?, "incident_response", "Incident Status Update", ?, ?)')
                        ->execute([$incidentData['user_id'], "Status: " . $displayStatus . ($notes ? ". Reason: $notes" : ""), $id]);

                    if (!empty($incidentData['resident_email'])) {
                        try {
                            send_incident_status_email($incidentData['resident_email'], $status, $incidentData);
                        } catch (Throwable $e) {
                        }
                    }
                    if (!empty($incidentData['resident_phone'])) {
                        try {
                            send_incident_status_sms($incidentData['resident_phone'], $status, $incidentData);
                        } catch (Throwable $e) {
                        }
                    }

                    if ($notes !== '') {
                        $pdo->prepare('INSERT INTO incident_messages (incident_id, user_id, message) VALUES (?, ?, ?)')
                            ->execute([$id, $_SESSION['user_id'], "Status updated to " . $displayStatus . ". Note: " . $notes]);
                        $pdo->prepare('UPDATE incidents SET admin_response=?, admin_response_by=?, admin_response_at=NOW() WHERE id=?')
                            ->execute([$notes, $_SESSION['user_id'], $id]);
                    }
                }
                $_SESSION['info'] = 'Incident status updated successfully.';
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            } catch (Throwable $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        } elseif ($action === 'respond') {
            $response = trim($_POST['admin_response'] ?? '');
            if ($response !== '') {
                try {
                    $pdo->prepare('INSERT INTO incident_messages (incident_id, user_id, message) VALUES (?, ?, ?)')
                        ->execute([$id, $_SESSION['user_id'], $response]);
                    $pdo->prepare('UPDATE incidents SET admin_response=?, admin_response_by=?, admin_response_at=NOW() WHERE id=?')
                        ->execute([$response, $_SESSION['user_id'], $id]);

                    $user_stmt = $pdo->prepare('SELECT user_id FROM incidents WHERE id=?');
                    $user_stmt->execute([$id]);
                    $user_id = $user_stmt->fetchColumn();
                    if ($user_id) {
                        $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_incident_id) VALUES (?, "incident_response", "Admin Response", ?, ?)')
                            ->execute([$user_id, $response, $id]);
                    }
                    $_SESSION['info'] = 'Response sent to resident successfully.';
                    header("Location: " . $_SERVER['REQUEST_URI']);
                    exit;
                } catch (Throwable $e) {
                    $error = 'Error sending response: ' . $e->getMessage();
                }
            } else {
                $error = 'Please provide a response message.';
            }
        } elseif (!empty($_POST['bulk_action']) && isset($_POST['selected']) && is_array($_POST['selected'])) {
            $bulk_action = $_POST['bulk_action'];
            $selected_ids = array_map('intval', $_POST['selected']);
            $bulk_notes = trim($_POST['bulk_notes'] ?? '');

            $target_status = null;
            $allowed_statuses = [];
            $action_label = "";

            switch ($bulk_action) {
                case 'bulk_review':
                    $target_status = 'in_review';
                    $allowed_statuses = ['submitted'];
                    $action_label = "Under Review";
                    break;
                case 'bulk_resolve':
                    $target_status = 'resolved';
                    $allowed_statuses = ['in_review'];
                    $action_label = "Resolved";
                    break;
                case 'bulk_reject':
                    $target_status = 'closed';
                    $allowed_statuses = ['submitted', 'in_review'];
                    $action_label = "Rejected";
                    break;
                case 'bulk_undo_resolve':
                    $target_status = 'in_review';
                    $allowed_statuses = ['resolved'];
                    $action_label = "Undo Resolved";
                    break;
                case 'bulk_undo_reject':
                    $target_status = 'in_review';
                    $allowed_statuses = ['closed'];
                    $action_label = "Undo Rejected";
                    break;
            }

            if ($target_status) {
                if ($target_status === 'closed' && empty($bulk_notes)) {
                    $_SESSION['error'] = 'Please provide a reason for bulk rejection.';
                } else {
                    $ok_count = 0;
                    $skipped_items = [];

                    // Fetch current status for all selected IDs to validate
                    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                    $stmt = $pdo->prepare("SELECT i.id, i.status, u.full_name FROM incidents i JOIN users u ON u.id = i.user_id WHERE i.id IN ($placeholders)");
                    $stmt->execute($selected_ids);
                    $incidents_to_process = $stmt->fetchAll();

                    foreach ($incidents_to_process as $item) {
                        $sid = (int) $item['id'];
                        $current_status = $item['status'];
                        $res_name = $item['full_name'];

                        if (!in_array($current_status, $allowed_statuses)) {
                            $status_text = ($current_status === 'submitted' ? 'Pending' : ($current_status === 'closed' ? 'Rejected' : ucfirst(str_replace('_', ' ', $current_status))));
                            $skipped_items[] = "#$sid ($res_name) - Status is $status_text";
                            continue;
                        }

                        try {
                            $pdo->prepare('UPDATE incidents SET status=? WHERE id=?')->execute([$target_status, $sid]);
                            $res_stmt = $pdo->prepare('
                                SELECT i.*, u.full_name as resident_name, u.email as resident_email, r.phone as resident_phone
                                FROM incidents i
                                JOIN users u ON u.id = i.user_id
                                LEFT JOIN residents r ON r.user_id = u.id
                                WHERE i.id = ?
                            ');
                            $res_stmt->execute([$sid]);
                            $incidentData = $res_stmt->fetch();

                            if ($incidentData) {
                                $incidentData['notes'] = $bulk_notes;
                                $displayStatus = ($target_status === 'submitted' ? 'Pending' : ($target_status === 'closed' ? 'Rejected' : ucfirst(str_replace('_', ' ', $target_status))));
                                $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_incident_id) VALUES (?, "incident_response", "Incident Status Update", ?, ?)')
                                    ->execute([$incidentData['user_id'], "Status: " . $displayStatus . ($bulk_notes ? ". Reason: $bulk_notes" : ""), $sid]);

                                if (!empty($incidentData['resident_email'])) {
                                    try {
                                        send_incident_status_email($incidentData['resident_email'], $target_status, $incidentData);
                                    } catch (Throwable $e) {
                                    }
                                }
                                if (!empty($incidentData['resident_phone'])) {
                                    try {
                                        send_incident_status_sms($incidentData['resident_phone'], $target_status, $incidentData);
                                    } catch (Throwable $e) {
                                    }
                                }

                                if ($bulk_notes !== '') {
                                    $pdo->prepare('INSERT INTO incident_messages (incident_id, user_id, message) VALUES (?, ?, ?)')
                                        ->execute([$sid, $_SESSION['user_id'], "Status updated to " . $displayStatus . ". Note: " . $bulk_notes]);
                                    $pdo->prepare('UPDATE incidents SET admin_response=?, admin_response_by=?, admin_response_at=NOW() WHERE id=?')
                                        ->execute([$bulk_notes, $_SESSION['user_id'], $sid]);
                                }
                            }
                            $ok_count++;
                        } catch (Throwable $e) {
                        }
                    }

                    if ($ok_count > 0) {
                        $msg = "$ok_count incidents updated to $action_label.";
                        if (!empty($skipped_items)) {
                            $msg .= "\\n\\nSkipped items due to invalid status:\\n" . implode("\\n", $skipped_items);
                        }
                        $_SESSION['info'] = $msg;
                    } else {
                        $msg = "No incidents were updated.";
                        if (!empty($skipped_items)) {
                            $msg .= "\\n\\nSkipped items:\\n" . implode("\\n", $skipped_items);
                        }
                        $_SESSION['error'] = $msg;
                    }

                    header("Location: " . $_SERVER['REQUEST_URI']);
                    exit;
                }
            }
        }
    }
}

// 2. Prepare View Data
$page_title = 'Incidents Management';
$breadcrumb = [['title' => 'Incidents']];

// 3. Include Header (Starts Output)
require_once __DIR__ . '/header.php';

$limit = 10;
$current_page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($current_page - 1) * $limit;

$where_clause = " WHERE u.role != 'admin'";
$params = [];
if (isset($admin_inc_status_filter) && $admin_inc_status_filter !== '') {
    $where_clause .= ' AND i.status = ?';
    $params[] = $admin_inc_status_filter;
}

$total_stmt = $pdo->prepare('SELECT COUNT(*) FROM incidents i JOIN users u ON u.id = i.user_id' . $where_clause);
$total_stmt->execute($params);
$total_records = $total_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$sql = 'SELECT i.*, u.full_name, u.email, u.created_at as user_created_at 
        FROM incidents i 
        JOIN users u ON u.id = i.user_id ' . $where_clause . '
        ORDER BY i.id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<div class="container-fluid">
    <?php if ($info): ?>
        <script>Swal.fire({ title: 'Success', text: '<?php echo $info; ?>', icon: 'success', confirmButtonColor: '#0d9488' });</script>
    <?php endif; ?>
    <?php if ($error): ?>
        <script>Swal.fire({ title: 'Error', text: '<?php echo $error; ?>', icon: 'error', confirmButtonColor: '#0d9488' });</script>
    <?php endif; ?>

    <div class="row pt-4">
        <div class="col-12">
            <div class="admin-table bg-white rounded-4 shadow-sm border-0">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-teal-gradient p-2 rounded-3 text-white shadow-sm">
                            <i class="fas fa-exclamation-triangle fa-fw"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">Recent Incidents</h5>
                            <p class="text-muted small mb-0">Manage and track barangay incident reports</p>
                        </div>
                    </div>
                    <div class="input-group" style="max-width: 300px;">
                        <span class="input-group-text bg-light border-end-0"><i
                                class="fas fa-search text-muted opacity-50"></i></span>
                        <input type="text" id="incidentsSearch" class="form-control border-start-0"
                            placeholder="Search incidents...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <?php $show_bulk_actions = ($admin_inc_status_filter !== 'canceled'); ?>
                            <tr>
                                <?php if ($show_bulk_actions): ?>
                                    <th class="ps-4" style="width: 40px;">
                                        <input type="checkbox" class="form-check-input" id="checkAll">
                                    </th>
                                <?php endif; ?>
                                <th>#</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th class="py-3 pe-3 text-center" style="width: 110px;">
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        Action
                                        <?php if ($show_bulk_actions): ?>
                                            <div class="dropdown">
                                                <button
                                                    class="btn btn-sm btn-light border-0 text-secondary p-0 incidents-drop-btn"
                                                    type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                                    data-bs-boundary="viewport" title="Bulk Actions"
                                                    style="width: 28px; height: 28px;">
                                                    <i class="fas fa-ellipsis-v" style="font-size: 0.85rem;"></i>
                                                </button>
                                                <ul class="dropdown-menu shadow border-0 py-2 small">
                                                    <?php if ($admin_inc_status_filter === ''): ?>
                                                        <li><button type="button" class="dropdown-item py-2"
                                                                onclick="handleBulkAction('bulk_review')">Review</button></li>
                                                        <li><button type="button" class="dropdown-item py-2"
                                                                onclick="handleBulkAction('bulk_resolve')">Resolved</button>
                                                        </li>
                                                        <li><button type="button" class="dropdown-item py-2"
                                                                onclick="handleBulkAction('bulk_undo_resolve')">Undo
                                                                Resolved</button></li>
                                                        <li><button type="button" class="dropdown-item py-2"
                                                                onclick="handleBulkAction('bulk_reject')">Rejected</button></li>
                                                        <li><button type="button" class="dropdown-item py-2"
                                                                onclick="handleBulkAction('bulk_undo_reject')">Undo
                                                                Rejected</button></li>
                                                    <?php elseif ($admin_inc_status_filter === 'submitted'): ?>
                                                        <li><button type="button" class="dropdown-item py-2"
                                                                onclick="handleBulkAction('bulk_review')">Review</button></li>
                                                        <li><button type="button" class="dropdown-item py-2"
                                                                onclick="handleBulkAction('bulk_reject')">Rejected</button></li>
                                                    <?php elseif ($admin_inc_status_filter === 'in_review'): ?>
                                                        <li><button type="button" class="dropdown-item py-2"
                                                                onclick="handleBulkAction('bulk_resolve')">Resolved</button>
                                                        </li>
                                                        <li><button type="button" class="dropdown-item py-2"
                                                                onclick="handleBulkAction('bulk_reject')">Rejected</button></li>
                                                    <?php elseif ($admin_inc_status_filter === 'resolved'): ?>
                                                        <li><button type="button" class="dropdown-item py-2"
                                                                onclick="handleBulkAction('bulk_undo_resolve')">Undo
                                                                Resolved</button></li>
                                                    <?php elseif ($admin_inc_status_filter === 'closed'): ?>
                                                        <li><button type="button" class="dropdown-item py-2"
                                                                onclick="handleBulkAction('bulk_undo_reject')">Undo
                                                                Rejected</button></li>
                                                    <?php else: ?>
                                                        <li><button type="button" class="dropdown-item py-2"
                                                                onclick="handleBulkAction('bulk_review')">Review</button></li>
                                                        <li><button type="button" class="dropdown-item py-2"
                                                                onclick="handleBulkAction('bulk_resolve')">Resolved</button>
                                                        </li>
                                                        <li><button type="button" class="dropdown-item py-2"
                                                                onclick="handleBulkAction('bulk_reject')">Rejected</button></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr>
                                    <td colspan="<?php echo $show_bulk_actions ? 5 : 4; ?>"
                                        class="text-center py-5 text-muted">
                                        <i class="fas fa-folder-open d-block mb-2 fs-2 opacity-25"></i>
                                        No incidents found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php
                                $counter = $offset + 1;
                                foreach ($rows as $r): ?>
                                    <tr>
                                        <?php if ($show_bulk_actions): ?>
                                            <td class="ps-4">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input row-checkbox" name="selected[]"
                                                        value="<?php echo (int) $r['id']; ?>"
                                                        data-status="<?php echo $r['status']; ?>">
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="text-secondary fw-bold small"><?php echo $counter++; ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark">
                                                <?php echo htmlspecialchars($r['full_name'] ?? ''); ?>
                                            </div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($r['email'] ?? ''); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = match ($r['status']) {
                                                'submitted' => 'bg-warning-subtle text-warning-emphasis',
                                                'in_review' => 'bg-info-subtle text-info-emphasis',
                                                'resolved' => 'bg-success-subtle text-success-emphasis',
                                                'closed' => 'bg-danger-subtle text-danger-emphasis',
                                                'canceled' => 'bg-secondary-subtle text-secondary-emphasis',
                                                default => 'bg-dark-subtle text-dark-emphasis'
                                            };
                                            ?>
                                            <span class="badge rounded-pill <?php echo $statusClass; ?> fw-medium px-3">
                                                <i class="fas fa-circle small me-1 opacity-50"></i>
                                                <?php echo $r['status'] === 'submitted' ? 'Pending' : ($r['status'] === 'closed' ? 'Rejected' : ucfirst(str_replace('_', ' ', $r['status']))); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="text-dark small">
                                                <?php echo date('M j, Y', strtotime($r['created_at'])); ?>
                                            </div>
                                            <div class="text-muted small" style="font-size: 0.75rem;">
                                                <?php echo date('g:i A', strtotime($r['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td class="text-center pe-3">
                                            <div class="d-flex justify-content-center gap-2 action-container">
                                                <!-- View Details Eye Icon -->
                                                <a href="incident_details.php?id=<?php echo (int) $r['id']; ?>"
                                                    class="action-btn btn-view" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>

                                                <?php if ($r['status'] === 'submitted'): ?>
                                                    <!-- Move to Under Review -->
                                                    <button type="button" class="action-btn btn-review border-0"
                                                        title="Under Review"
                                                        onclick="confirmStatusUpdate(<?php echo (int) $r['id']; ?>, 'in_review')">
                                                        <i class="fas fa-search"></i>
                                                    </button>
                                                    <!-- Reject -->
                                                    <button type="button" class="action-btn btn-reject border-0"
                                                        title="Reject/Close"
                                                        onclick="confirmStatusUpdate(<?php echo (int) $r['id']; ?>, 'closed')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php elseif ($r['status'] === 'in_review'): ?>
                                                    <!-- Mark Resolved -->
                                                    <button type="button" class="action-btn btn-resolve border-0"
                                                        title="Mark Resolved"
                                                        onclick="confirmStatusUpdate(<?php echo (int) $r['id']; ?>, 'resolved')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php elseif ($r['status'] === 'resolved'): ?>
                                                    <!-- Undo Resolved -->
                                                    <button type="button" class="action-btn btn-undo border-0" title="Undo Resolved"
                                                        onclick="confirmStatusUpdate(<?php echo (int) $r['id']; ?>, 'in_review')">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                <?php elseif ($r['status'] === 'closed'): ?>
                                                    <!-- Undo Rejected -->
                                                    <button type="button" class="action-btn btn-undo border-0" title="Undo Rejected"
                                                        onclick="confirmStatusUpdate(<?php echo (int) $r['id']; ?>, 'in_review')">
                                                        <i class="fas fa-undo-alt"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="p-3 border-top bg-light bg-opacity-10">
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm justify-content-center mb-0 gap-1">
                                <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link border-0 rounded-3 shadow-sm"
                                        href="<?php echo basename($_SERVER['PHP_SELF']); ?>?page=<?php echo $current_page - 1; ?><?php echo isset($_GET['status']) ? '&status=' . htmlspecialchars($_GET['status']) : ''; ?>">&laquo;</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                                        <a class="page-link border-0 rounded-3 shadow-sm <?php echo $current_page == $i ? 'bg-primary text-white' : 'bg-white text-dark'; ?>"
                                            href="<?php echo basename($_SERVER['PHP_SELF']); ?>?page=<?php echo $i; ?><?php echo isset($_GET['status']) ? '&status=' . htmlspecialchars($_GET['status']) : ''; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link border-0 rounded-3 shadow-sm"
                                        href="<?php echo basename($_SERVER['PHP_SELF']); ?>?page=<?php echo $current_page + 1; ?><?php echo isset($_GET['status']) ? '&status=' . htmlspecialchars($_GET['status']) : ''; ?>">&raquo;</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<form id="bulkActionForm" method="POST" style="display:none;">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="bulk_action" id="bulkActionInput">
    <input type="hidden" name="bulk_notes" id="bulkNotesInput">
    <div id="selectedContainer"></div>
</form>

<form id="statusUpdateForm" method="POST" style="display:none;">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="id" id="updateId">
    <input type="hidden" name="status" id="updateStatus">
    <input type="hidden" name="rejection_notes" id="updateNotes">
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Search: Incidents table
        const searchInput = document.getElementById('incidentsSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const query = this.value.toLowerCase();
                const container = searchInput.closest('.admin-table');
                if (!container) return;
                container.querySelectorAll('tbody tr').forEach(function (row) {
                    const text = row.cells[2] ? row.cells[2].textContent.toLowerCase() : ''; // Report column
                    const resident = row.cells[1] ? row.cells[1].textContent.toLowerCase() : '';
                    row.style.display = (text.includes(query) || resident.includes(query)) ? '' : 'none';
                });
            });
        }

        // Check All functionality
        const checkAll = document.getElementById('checkAll');
        if (checkAll) {
            checkAll.addEventListener('change', function () {
                const checkboxes = document.querySelectorAll('.row-checkbox');
                checkboxes.forEach(cb => cb.checked = this.checked);
            });
        }
    });

    function openBulkActionMenu() {
        const selected = document.querySelectorAll('.row-checkbox:checked');
        if (selected.length === 0) {
            Swal.fire({
                title: 'No Selection',
                text: 'Please select at least one incident using the checkboxes first.',
                icon: 'warning',
                confirmButtonColor: '#0d9488'
            });
            return;
        }

        Swal.fire({
            title: 'Bulk Actions',
            text: `Manage ${selected.length} selected incident(s)`,
            icon: 'info',
            showCloseButton: true,
            showCancelButton: true,
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#0d9488',
            showConfirmButton: false,
            html: `
            <div class="d-grid gap-2 mt-3 text-start">
                <button class="btn btn-outline-primary py-2 d-flex align-items-center" onclick="Swal.close(); handleBulkAction('bulk_review')">
                    <i class="fas fa-search me-3"></i>
                    <div class="fw-bold text-dark">Review</div>
                </button>
                <button class="btn btn-outline-success py-2 d-flex align-items-center" onclick="Swal.close(); handleBulkAction('bulk_resolve')">
                    <i class="fas fa-check-circle me-3"></i>
                    <div class="fw-bold text-dark">Resolved</div>
                </button>
                <button class="btn btn-outline-warning py-2 d-flex align-items-center" onclick="Swal.close(); handleBulkAction('bulk_undo_resolve')">
                    <i class="fas fa-undo me-3"></i>
                    <div class="fw-bold text-dark">Undo Resolved</div>
                </button>
                <button class="btn btn-outline-danger py-2 d-flex align-items-center" onclick="Swal.close(); handleBulkAction('bulk_reject')">
                    <i class="fas fa-times-circle me-3"></i>
                    <div class="fw-bold text-dark">Rejected</div>
                </button>
                <button class="btn btn-outline-secondary py-2 d-flex align-items-center" onclick="Swal.close(); handleBulkAction('bulk_undo_reject')">
                    <i class="fas fa-undo-alt me-3"></i>
                    <div class="fw-bold text-dark">Undo Rejected</div>
                </button>
            </div>
        `
        });
    }

    function handleBulkAction(action) {
        const selected = document.querySelectorAll('.row-checkbox:checked');
        if (selected.length === 0) {
            Swal.fire({
                title: 'No Selection',
                text: 'Please select at least one incident.',
                icon: 'warning',
                confirmButtonColor: '#0d9488'
            });
            return;
        }

        let title = "Update Status?";
        let text = "Update " + selected.length + " selected incident(s)?";
        let icon = "question";
        let confirmBtnText = "Yes, Update";
        let confirmBtnColor = "#0d9488";

        if (action === 'bulk_reject') {
            title = "Reject Selected?";
            text = "Please provide a reason for rejecting these incidents:";
            icon = "warning";
            confirmBtnColor = "#dc2626";
            confirmBtnText = "Reject Selected";

            Swal.fire({
                title: title,
                text: text,
                icon: icon,
                input: 'textarea',
                inputPlaceholder: 'Type reason here...',
                showCancelButton: true,
                confirmButtonColor: confirmBtnColor,
                cancelButtonColor: '#aaa',
                confirmButtonText: confirmBtnText,
                inputValidator: (value) => {
                    if (!value) return 'You need to provide a reason for rejection!'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    submitBulk(action, result.value);
                }
            });
            return;
        }

        Swal.fire({
            title: title,
            text: text,
            icon: icon,
            showCancelButton: true,
            confirmButtonColor: confirmBtnColor,
            cancelButtonColor: '#aaa',
            confirmButtonText: confirmBtnText
        }).then((result) => {
            if (result.isConfirmed) {
                submitBulk(action, '');
            }
        });
    }

    function submitBulk(action, notes) {
        const form = document.getElementById('bulkActionForm');
        const selected = document.querySelectorAll('.row-checkbox:checked');
        const container = document.getElementById('selectedContainer');
        container.innerHTML = '';

        selected.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected[]';
            input.value = cb.value;
            container.appendChild(input);
        });

        document.getElementById('bulkActionInput').value = action;
        document.getElementById('bulkNotesInput').value = notes;
        form.action = window.location.pathname + window.location.search;
        form.submit();
    }

    function confirmStatusUpdate(id, status) {
        let title = "Update Status?";
        let text = "Are you sure you want to change the status of this incident?";
        let icon = "question";
        let confirmBtnText = "Yes, update it";
        let confirmBtnColor = "#3085d6";

        if (status === 'closed') {
            title = "Reject Incident?";
            text = "Please provide a reason for rejecting this incident report:";
            icon = "warning";
            confirmBtnColor = "#d33";
            confirmBtnText = "Reject Incident";

            Swal.fire({
                title: title,
                text: text,
                icon: icon,
                input: 'textarea',
                inputPlaceholder: 'Type the reason here...',
                showCancelButton: true,
                confirmButtonColor: confirmBtnColor,
                cancelButtonColor: '#aaa',
                confirmButtonText: confirmBtnText,
                inputValidator: (value) => {
                    if (!value) {
                        return 'You need to provide a reason for rejection!'
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('updateId').value = id;
                    document.getElementById('updateStatus').value = status;
                    document.getElementById('updateNotes').value = result.value;
                    const form = document.getElementById('statusUpdateForm');
                    form.action = window.location.pathname + window.location.search;
                    form.submit();
                }
            });
            return;
        }

        Swal.fire({
            title: title,
            text: text,
            icon: icon,
            showCancelButton: true,
            confirmButtonColor: confirmBtnColor,
            cancelButtonColor: '#aaa',
            confirmButtonText: confirmBtnText
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('updateId').value = id;
                document.getElementById('updateStatus').value = status;
                document.getElementById('updateNotes').value = '';
                const form = document.getElementById('statusUpdateForm');
                form.action = window.location.pathname + window.location.search;
                form.submit();
            }
        });
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
