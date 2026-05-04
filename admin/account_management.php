<?php
require_once __DIR__ . '/../config.php';
if (!is_admin() || $_SESSION['user_id'] != 1)
	redirect('../index.php');

$pdo = get_db_connection();
$info = '';
$error = '';

// Handle Status Toggle (Works for both Owners and Members)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_validate()) {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'bulk_delete_accounts') {
            $selected_ids = $_POST['selected_ids'] ?? [];
            $selected_record_ids = $_POST['selected_record_ids'] ?? [];
            
            if (!empty($selected_ids) || !empty($selected_record_ids)) {
                try {
                    $pdo->beginTransaction();
                    if (!empty($selected_ids)) {
                        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
                        $stmt->execute($selected_ids);
                    }
                    if (!empty($selected_record_ids)) {
                        $placeholders = implode(',', array_fill(0, count($selected_record_ids), '?'));
                        $stmt = $pdo->prepare("DELETE FROM resident_records WHERE id IN ($placeholders)");
                        $stmt->execute($selected_record_ids);
                    }
                    $pdo->commit();
                    $info = "Successfully removed selected records.";
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $error = "Error: " . $e->getMessage();
                }
            }
        } else {
			$rid = (int) ($_POST['record_id'] ?? 0);
			$type = $_POST['resident_type'] ?? 'primary';

			if ($action === 'toggle_status') {
			$new_status = (int) ($_POST['new_status'] ?? 1);
			try {
				if ($type === 'primary') {
					$pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$new_status, $rid]);
				} else {
					$pdo->prepare('UPDATE family_members SET is_active = ? WHERE id = ?')->execute([$new_status, $rid]);
				}
				$info = 'Resident status updated successfully.';
			} catch (Throwable $e) {
				$error = 'Error updating status: ' . $e->getMessage();
			}
		} elseif ($action === 'delete_account') {
			$uid = (int) ($_POST['user_id'] ?? 0);
			$rid = (int) ($_POST['record_id'] ?? 0);
			
			if ($uid > 0 || $rid > 0) {
				try {
					$pdo->beginTransaction();
					if ($uid > 0) {
						$pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
					}
					if ($rid > 0) {
						$pdo->prepare('DELETE FROM resident_records WHERE id = ?')->execute([$rid]);
					}
					$pdo->commit();
					$info = 'Resident data removed successfully.';
				} catch (Throwable $e) {
					$pdo->rollBack();
					$error = 'Error deleting data: ' . $e->getMessage();
				}
			}
		}
		}
	}
}

$page_title = 'Resident account';


require_once __DIR__ . '/header.php';
?>

<style>
    .action-btn { width: 35px; height: 35px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; transition: 0.3s; text-decoration: none; }
    .action-btn:hover { background: #f1f5f9; transform: translateY(-2px); }
</style>

<?php
$search = trim($_GET['search'] ?? '');
$params = [];

// Base query for Primary Users (Owners)
$final_query = '
    SELECT 
        "primary" as resident_type,
        u.id as record_id, 
        u.id as user_id,
        u.full_name, 
        u.email, 
        u.is_active,
        r.address, 
        r.phone,
        u.id as household_group,
        COALESCE(r.verification_status, "pending") as verification_status
    FROM users u 
    LEFT JOIN residents r ON r.user_id = u.id 
    WHERE u.role = "resident" 
';


if ($search !== '') {
	$final_query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR r.address LIKE ?)";
	$searchTerm = "%$search%";
	$params = [$searchTerm, $searchTerm, $searchTerm];
}
$final_query .= ' ORDER BY u.full_name ASC';

$stmt = $pdo->prepare($final_query);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Fetch all official records for matching (with address and phone fallback)
$all_records = $pdo->query("SELECT id, full_name, email, address, phone FROM resident_records")->fetchAll();

// Match in PHP for maximum reliability
function clean_str($str)
{
	if (!$str)
		return "";
	$str = preg_replace('/[\p{Z}\s]+/u', ' ', $str);
	return strtolower(trim($str));
}

foreach ($rows as &$r) {
	$r['official_id'] = 0;
	$curr_name = clean_str($r['full_name']);
	$curr_email = clean_str($r['email']);
	$curr_name_parts = array_filter(explode(' ', $curr_name));
	$found = false;

	// 1. Try to match by email
	if ($curr_email !== "") {
		foreach ($all_records as $rec) {
			if (clean_str($rec['email'] ?? '') === $curr_email) {
				$r['official_id'] = $rec['id'];
				if (empty($r['address']) || $r['address'] == 'N/A')
					$r['address'] = $rec['address'];
				if (empty($r['phone']) || $r['phone'] == 'N/A')
					$r['phone'] = $rec['phone'];
				$found = true;
				break;
			}
		}
	}

	// 2. Try to match by name (Smart Match)
	if (!$found) {
		foreach ($all_records as $rec) {
			$rec_name = clean_str($rec['full_name'] ?? '');

			// Check if all parts of the account name exist in the record name
			$match_parts = true;
			foreach ($curr_name_parts as $part) {
				if (strpos($rec_name, $part) === false) {
					$match_parts = false;
					break;
				}
			}
			if ($match_parts && count($curr_name_parts) >= 2) {
				$r['official_id'] = $rec['id'];
				if (empty($r['address']) || $r['address'] == 'N/A')
					$r['address'] = $rec['address'];
				if (empty($r['phone']) || $r['phone'] == 'N/A')
					$r['phone'] = $rec['phone'];
				$found = true;
				break;
			}
		}
	}

	// 3. Fallback to Household Head
	if (!$found) {
		$head_name = "";
		$head_email = "";
		// Re-search in original $rows for head
		foreach ($rows as $head_candidate) {
			if ($head_candidate['user_id'] == $r['user_id'] && $head_candidate['resident_type'] == 'primary') {
				$head_name = clean_str($head_candidate['full_name']);
				$head_email = clean_str($head_candidate['email']);
				break;
			}
		}

		if ($head_name !== "") {
			foreach ($all_records as $rec) {
				if ((clean_str($rec['email'] ?? '') === $head_email && $head_email !== "") || clean_str($rec['full_name'] ?? '') === $head_name) {
					$r['official_id'] = $rec['id'];
					if (empty($r['address']) || $r['address'] == 'N/A') $r['address'] = $rec['address'];
					if (empty($r['phone']) || $r['phone'] == 'N/A') $r['phone'] = $rec['phone'];
					$found = true;
					break;
				}
			}
		}
	}
}
unset($r);

// One last pass to include ONLY resident_records that aren't matched to any user/member
$matched_ids = array_column($rows, 'official_id');
foreach ($all_records as $rec) {
	if (!in_array($rec['id'], $matched_ids)) {
		$rows[] = [
			'resident_type' => 'record',
			'record_id' => $rec['id'],
			'user_id' => 0,
			'full_name' => $rec['full_name'],
			'email' => $rec['email'] ?? '',
			'is_active' => 1,
			'address' => $rec['address'],
			'phone' => $rec['phone'],
			'household_group' => 0,
			'verification_status' => 'verified', // Admin-entered records are verified by default
			'official_id' => $rec['id']
		];
	}
}

// Pagination Logic
$limit = 10;
$total_records = count($rows);
$total_pages = ceil($total_records / $limit);
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($current_page < 1)
	$current_page = 1;
if ($current_page > $total_pages && $total_pages > 0)
	$current_page = $total_pages;
$offset = ($current_page - 1) * $limit;

// Data to display on current page
$display_rows = array_slice($rows, $offset, $limit);

?>

<?php if ($info || $error): ?>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			Swal.fire({
				title: '<?php echo $info ? "Success!" : "Error!"; ?>',
				text: '<?php echo htmlspecialchars($info ?: $error); ?>',
				icon: '<?php echo $info ? "success" : "error"; ?>',
				confirmButtonColor: '#3085d6'
			});
		});
	</script>
<?php endif; ?>

<div class="row align-items-center mb-4">
	<div class="col-md-6">
		<h4 class="fw-bold mb-1"><i class="fas fa-users-cog me-2 text-primary"></i>Resident account</h4>
		<p class="text-muted mb-0 small">Manage account verification and user status</p>
	</div>
	<div class="col-md-6 text-md-end">
		<form method="GET" class="d-inline-block" style="max-width: 300px;">
			<div class="input-group">
				<input type="text" name="search" class="form-control" placeholder="Search name..."
					value="<?php echo htmlspecialchars($search); ?>">
				<button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
			</div>
		</form>
	</div>
</div>



<div class="table-responsive">
	<table class="table table-hover align-middle">
		<thead class="table-light">
			<tr>
				<th style="width: 40px;" class="ps-3">
					<input type="checkbox" class="form-check-input" id="selectAllResidents">
				</th>
				<th style="width: 50px;">#</th>
				<th>Name</th>
				<th>Address</th>
				<th>Contact No.</th>
				<th>Email</th>
				<th>Status</th>
				<th class="text-center" style="width: 140px;">
					<div class="d-flex align-items-center justify-content-center gap-2">
						Actions
						<div class="dropdown">
							<button class="btn btn-sm btn-light border-0 text-secondary p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" style="width: 24px; height: 24px;">
								<i class="fas fa-ellipsis-v" style="font-size: 0.85rem;"></i>
							</button>
							<ul class="dropdown-menu shadow border-0 py-2 small">
								<li>
									<button type="button" class="dropdown-item py-2" onclick="bulkDeleteAccounts()">
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
			<?php
			$counter = $offset + 1;
			foreach ($display_rows as $r):
				?>
				<tr>
					<td class="ps-3"><input type="checkbox" class="form-check-input resident-checkbox"></td>
					<td class="text-muted small fw-bold"><?php echo $counter++; ?></td>
					<td class="fw-bold text-dark"><?php echo htmlspecialchars($r['full_name']); ?></td>
					<td class="small text-muted"><?php echo htmlspecialchars($r['address'] ?? 'N/A'); ?></td>
					<td class="small text-muted"><?php echo htmlspecialchars($r['phone'] ?? 'N/A'); ?></td>
					<td class="small text-muted"><?php echo htmlspecialchars($r['email'] ?? 'N/A'); ?></td>
					<td>
						<?php if ($r['verification_status'] === 'verified'): ?>
							<span class="text-success small fw-bold"><i class="fas fa-check-circle me-1"></i>Verified</span>
						<?php else: ?>
							<span class="text-secondary small fw-bold">Pending</span>
						<?php endif; ?>
					</td>
					<td class="text-center">
						<div class="d-flex justify-content-center align-items-center gap-2">
							<a href="resident_record_view.php?id=<?php echo $r['official_id']; ?>&user_id=<?php echo $r['user_id']; ?>" class="action-btn text-primary"
								title="View Details">
								<i class="fas fa-eye"></i>
							</a>
							<a href="resident_record_view.php?id=<?php echo $r['official_id']; ?>&user_id=<?php echo $r['user_id']; ?>&edit=1"
								class="action-btn text-warning" title="Edit Record">
								<i class="fas fa-edit"></i>
							</a>
							<form method="POST" class="m-0 delete-account-form">
								<?php echo csrf_field(); ?>
								<input type="hidden" name="action" value="delete_account">
								<input type="hidden" name="user_id" value="<?php echo $r['user_id']; ?>">
								<input type="hidden" name="record_id" value="<?php echo $r['official_id']; ?>">
								<button type="button" class="action-btn border-0 bg-transparent text-danger btn-delete-account"
									title="Delete Account"
									data-name="<?php echo htmlspecialchars($r['full_name']); ?>">
									<i class="fas fa-trash"></i>
								</button>
							</form>
						</div>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($rows)): ?>
				<tr>
					<td colspan="7" class="text-center py-5 text-muted">No resident accounts found.</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<!-- Pagination Links -->
<?php if ($total_pages > 1): ?>
	<div class="px-4 py-3 border-top bg-light d-flex justify-content-between align-items-center">
		<div class="text-muted small font-monospace">
			Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of
			<?php echo $total_records; ?> accounts
		</div>
		<nav>
			<ul class="pagination pagination-sm mb-0">
				<li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
					<a class="page-link"
						href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
				</li>
				<?php for ($i = 1; $i <= $total_pages; $i++): ?>
					<li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
						<a class="page-link"
							href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
					</li>
				<?php endfor; ?>
				<li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
					<a class="page-link"
						href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
				</li>
			</ul>
		</nav>
	</div>
<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
	document.getElementById('selectAllResidents').addEventListener('change', function() {
		const checkboxes = document.querySelectorAll('.resident-checkbox');
		checkboxes.forEach(cb => cb.checked = this.checked);
	});

	function bulkDeleteAccounts() {
		const selected = Array.from(document.querySelectorAll('.resident-checkbox:checked')).map(cb => {
			const row = cb.closest('tr');
			const form = row.querySelector('.delete-account-form');
			return form ? { 
                id: form.querySelector('[name="user_id"]').value, 
                record_id: form.querySelector('[name="record_id"]').value,
                name: row.querySelector('.fw-bold.text-dark').innerText 
            } : null;
		}).filter(item => item !== null);

		if (selected.length === 0) {
			Swal.fire('No selection', 'Please select at least one account using the checkboxes.', 'warning');
			return;
		}

		Swal.fire({
			title: 'Bulk Delete',
			text: `Are you sure you want to delete ${selected.length} selected account(s)? This action cannot be undone.`,
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
					<input type="hidden" name="action" value="bulk_delete_accounts">
				`;
				selected.forEach(item => {
					if (item.id > 0) {
						const input = document.createElement('input');
						input.type = 'hidden';
						input.name = 'selected_ids[]';
						input.value = item.id;
						bulkForm.appendChild(input);
					}
					if (item.record_id > 0) {
						const input = document.createElement('input');
						input.type = 'hidden';
						input.name = 'selected_record_ids[]';
						input.value = item.record_id;
						bulkForm.appendChild(input);
					}
				});
				document.body.appendChild(bulkForm);
				bulkForm.submit();
			}
		});
	}

	document.querySelectorAll('.btn-delete-account').forEach(button => {
		button.addEventListener('click', function (e) {
			const form = this.closest('form');
			const name = this.dataset.name;

			Swal.fire({
				title: 'Delete Account',
				text: `Are you sure you want to delete ${name}'s user account? This action cannot be undone.`,
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#dc3545',
				cancelButtonColor: '#6c757d',
				confirmButtonText: 'Yes, delete it!',
				cancelButtonText: 'Cancel'
			}).then((result) => {
				if (result.isConfirmed) {
					form.submit();
				}
			});
		});
	});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
