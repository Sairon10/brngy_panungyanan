<?php
require_once __DIR__ . '/../config.php';
if (!is_admin()) {
	redirect('../index.php');
}

if (!isset($admin_requests_page_status)) {
	if (basename($_SERVER['PHP_SELF']) === 'requests.php' && isset($_GET['status_filter']) && (string) $_GET['status_filter'] !== '') {
		$admin_requests_page_status = trim((string) $_GET['status_filter']);
	} else {
		$admin_requests_page_status = null;
	}
}
if ($admin_requests_page_status === '') {
	$admin_requests_page_status = null;
}

$pdo = get_db_connection();
$email_status_message = '';
$sms_status_message = '';

/**
 * Update one request; optionally require row status to match $expected_current_status (bulk safety).
 *
 * @return array{ok:bool, reason?:string, requestData?:array|null, userEmail?:?string, userPhone?:?string, status?:string, should_redirect_pdf?:bool, pdf_document_id?:int}
 */
function admin_requests_apply_status(
	PDO $pdo,
	int $id,
	string $request_type,
	string $status,
	string $notes,
	int $admin_user_id,
	?string $expected_current_status = null
): array {
	if ($request_type === 'clearance') {
		$stmt = $pdo->prepare('
			SELECT bc.*, u.full_name, u.email, bc.purpose, bc.clearance_number, r.phone
			FROM barangay_clearances bc
			JOIN users u ON u.id = bc.user_id
			LEFT JOIN residents r ON r.user_id = u.id
			WHERE bc.id = ?
		');
		$stmt->execute([$id]);
		$clearance = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$clearance) {
			return ['ok' => false, 'reason' => 'not_found'];
		}
		if ($expected_current_status !== null && $clearance['status'] !== $expected_current_status) {
			return ['ok' => false, 'reason' => 'status_mismatch'];
		}
		$userEmail = $clearance['email'];
		$userName = $clearance['full_name'];
		$userPhone = $clearance['phone'] ?? null;
		$price = 0.00;
		$price_stmt = $pdo->prepare('SELECT price FROM document_types WHERE name = ?');
		$price_stmt->execute(['Barangay Clearance']);
		$price_result = $price_stmt->fetch(PDO::FETCH_ASSOC);
		if ($price_result) {
			$price = (float) $price_result['price'];
		}
		$requestData = [
			'type' => 'clearance',
			'number' => $clearance['clearance_number'],
			'purpose' => $clearance['purpose'],
			'doc_type' => 'Barangay Clearance',
			'notes' => $notes,
			'resident_name' => $userName,
			'price' => $price,
		];
		if ($status === 'approved' || $status === 'released') {
			$pdo->prepare('UPDATE barangay_clearances SET status=?, notes=?, approved_by=?, approved_at=NOW() WHERE id=?')
				->execute([$status, $notes, $admin_user_id, $id]);
		} else {
			$pdo->prepare('UPDATE barangay_clearances SET status=?, notes=? WHERE id=?')
				->execute([$status, $notes, $id]);
		}
		$status_label = ucfirst($status);
		$notif_msg = "Your Barangay Clearance ({$clearance['clearance_number']}) status has been updated to: {$status_label}.";
		if (!empty($notes)) {
			$notif_msg .= " Note: {$notes}";
		}
		$pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_request_id) VALUES (?, "request_update", "Clearance Status Updated", ?, ?)')
			->execute([$clearance['user_id'], $notif_msg, $id]);

		return [
			'ok' => true,
			'requestData' => $requestData,
			'userEmail' => $userEmail,
			'userPhone' => $userPhone,
			'status' => $status,
			'should_redirect_pdf' => false,
		];
	}

	if ($request_type !== 'document') {
		return ['ok' => false, 'reason' => 'bad_type'];
	}

	$stmt = $pdo->prepare('
		SELECT dr.*, u.full_name, u.email, r.phone
		FROM document_requests dr
		JOIN users u ON u.id = dr.user_id
		LEFT JOIN residents r ON r.user_id = u.id
		WHERE dr.id = ?
	');
	$stmt->execute([$id]);
	$document = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$document) {
		return ['ok' => false, 'reason' => 'not_found'];
	}
	if ($expected_current_status !== null && $document['status'] !== $expected_current_status) {
		return ['ok' => false, 'reason' => 'status_mismatch'];
	}
	$userEmail = $document['email'];
	$userPhone = $document['phone'] ?? null;
	$userName = $document['full_name'];
	$price = 0.00;
	$price_stmt = $pdo->prepare('SELECT price FROM document_types WHERE name = ?');
	$price_stmt->execute([$document['doc_type']]);
	$price_result = $price_stmt->fetch(PDO::FETCH_ASSOC);
	if ($price_result) {
		$price = (float) $price_result['price'];
	}
	$requestData = [
		'type' => 'document',
		'number' => '#' . $document['id'],
		'purpose' => $document['purpose'] ?? '',
		'doc_type' => $document['doc_type'],
		'notes' => $notes,
		'resident_name' => $userName,
		'price' => $price,
	];
	$pdo->prepare('UPDATE document_requests SET status=?, notes=? WHERE id=?')
		->execute([$status, $notes, $id]);

	$status_label = ucfirst($status);
	$notif_msg = "Your {$document['doc_type']} request (#{$id}) has been updated to: {$status_label}.";
	if (!empty($notes)) {
		$notif_msg .= " Note: {$notes}";
	}
	$pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_request_id) VALUES (?, "request_update", "Request Status Updated", ?, ?)')
		->execute([$document['user_id'], $notif_msg, $id]);

	$is_indigency_doc = stripos($document['doc_type'], 'Indigency') !== false;
	$should_redirect_pdf = $is_indigency_doc && $status === 'released';

	return [
		'ok' => true,
		'requestData' => $requestData,
		'userEmail' => $userEmail,
		'userPhone' => $userPhone,
		'status' => $status,
		'should_redirect_pdf' => $should_redirect_pdf,
		'pdf_document_id' => $id,
	];
}

function admin_requests_notification_alerts(?string $userEmail, $userPhone, string $status, ?array $requestData): array {
	$results = ['email' => null, 'sms' => null];
	if (!$requestData) return $results;
	
	if ($userEmail && !empty($userEmail)) {
		$emailResult = send_request_status_email($userEmail, $status, $requestData);
		$results['email'] = $emailResult['success'];
	}
	if (isset($userPhone) && !empty($userPhone)) {
		$smsResult = send_request_status_sms($userPhone, $status, $requestData);
		$results['sms'] = $smsResult['success'];
		if (!$smsResult['success']) {
			$results['sms_error'] = $smsResult['message'];
		}
	}
	return $results;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (csrf_validate()) {
		$admin_uid = (int) ($_SESSION['user_id'] ?? 0);

		if (!empty($_POST['bulk_action']) && isset($_POST['selected']) && is_array($_POST['selected'])) {
			$bulk_action = $_POST['bulk_action'];
			$selected = $_POST['selected'];
			$bulk_notes = trim($_POST['bulk_notes'] ?? '');
			$page_st = $admin_requests_page_status;

			if ($page_st !== null && !in_array($page_st, ['pending', 'approved', 'released', 'rejected'], true)) {
				$email_status_message = '<div class="alert alert-warning alert-dismissible fade show" role="alert">Bulk actions are only available on specific status pages or the All Request page.</div>';
			} else {
				$target_status = null;
				if ($bulk_action === 'mark_ready') {
					if ($page_st !== 'pending' && $page_st !== null) {
						$target_status = null;
					} else {
						$target_status = 'approved';
					}
				} elseif ($bulk_action === 'mark_released') {
					if ($page_st !== 'approved' && $page_st !== null) {
						$target_status = null;
					} else {
						$target_status = 'released';
					}
				} elseif ($bulk_action === 'reject') {
					if ($bulk_notes === '') {
						$email_status_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Please provide a reason for rejection.</div>';
						$target_status = false;
					} else {
						$target_status = 'rejected';
					}
				} elseif ($bulk_action === 'undo_release') {
					if ($page_st !== 'released' && $page_st !== null) {
						$target_status = null;
					} else {
						$target_status = 'approved';
					}
				} elseif ($bulk_action === 'undo_reject') {
					if ($page_st !== 'rejected' && $page_st !== null) {
						$target_status = null;
					} else {
						$target_status = 'pending';
					}
				}
				if ($target_status === null && !isset($email_status_message)) {
					$email_status_message = '<div class="alert alert-warning alert-dismissible fade show" role="alert">That bulk action is not valid for this page.</div>';
				} elseif ($target_status !== false && $target_status !== null) {
					$expected = $page_st;
					// Safety: if on 'All' page, only target sensible current statuses
					if ($page_st === null) {
						if ($bulk_action === 'mark_ready') $expected = 'pending';
						elseif ($bulk_action === 'mark_released') $expected = 'approved';
						elseif ($bulk_action === 'undo_release') $expected = 'released';
						elseif ($bulk_action === 'undo_reject') $expected = 'rejected';
					}
					$ok_count = 0;
					$skip_count = 0;
					$selected = array_values(array_filter($selected, static function ($t) {
						return is_string($t) && $t !== '';
					}));
					if (count($selected) === 0) {
						$email_status_message = '<div class="alert alert-warning alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-circle me-2"></i>Select at least one request using the checkboxes.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
					} else {
					$skipped_details = [];
					foreach ($selected as $token) {
						if (!is_string($token) || !preg_match('/^(clearance|document)_(\d+)$/', $token, $m)) {
							$skip_count++;
							$skipped_details[] = ['id' => $token, 'reason' => 'Invalid ID format'];
							continue;
						}
						$rtype = $m[1];
						$rid = (int) $m[2];
						$res = admin_requests_apply_status($pdo, $rid, $rtype, $target_status, $bulk_notes, $admin_uid, $expected);
						if (!$res['ok']) {
							$skip_count++;
							$info_label = ($rtype === 'clearance' ? 'Clearance #' : 'Request #') . $rid;
							// Try to get more info for the skip report
							if ($rtype === 'clearance') {
								$st = $pdo->prepare("SELECT u.full_name, status FROM barangay_clearances bc JOIN users u ON u.id=bc.user_id WHERE bc.id=?");
							} else {
								$st = $pdo->prepare("SELECT u.full_name, status FROM document_requests dr JOIN users u ON u.id=dr.user_id WHERE dr.id=?");
							}
							$st->execute([$rid]);
							$row = $st->fetch(PDO::FETCH_ASSOC);
							$reason_msg = ($res['reason'] === 'status_mismatch') ? 'Current status (' . ($row['status'] ?? 'unknown') . ') does not match required status' : 'Request not found';
							$skipped_details[] = [
								'name' => $row['full_name'] ?? 'Unknown',
								'label' => $info_label,
								'reason' => $reason_msg
							];
							continue;
						}
						$ok_count++;
						if (!empty($res['requestData'])) {
							$n = admin_requests_notification_alerts($res['userEmail'] ?? null, $res['userPhone'] ?? null, $res['status'], $res['requestData']);
						}
					}
					$_SESSION['bulk_result'] = [
						'ok' => (int) $ok_count,
						'skip' => (int) $skip_count,
						'skipped_items' => $skipped_details
					];
					$email_status_message = '';
					}
				}
			}
		} else {
			$id = (int) ($_POST['id'] ?? 0);
			$request_type = $_POST['request_type'] ?? '';
			$status = $_POST['status'] ?? 'pending';
			$notes = trim($_POST['notes'] ?? '');

			$res = admin_requests_apply_status($pdo, $id, $request_type, $status, $notes, $admin_uid, null);
			if ($res['ok'] && !empty($res['requestData'])) {
				$notification_res = admin_requests_notification_alerts($res['userEmail'] ?? null, $res['userPhone'] ?? null, $res['status'], $res['requestData']);
				$_SESSION['action_success'] = [
					'title' => 'Updated Successfully',
					'text' => 'The request status has been updated and notifications have been triggered.',
					'sms_error' => $notification_res['sms_error'] ?? null
				];
			}
			if (!empty($res['should_redirect_pdf']) && !empty($res['pdf_document_id'])) {
				redirect('../generate_indigency_cert.php?id=' . (int) $res['pdf_document_id']);
			}
		}
	}
}

// Get all clearances
$clearances = $pdo->query('
    SELECT bc.*, u.full_name, u.email, r.address, r.phone,
           fm.full_name AS fm_name, fm.is_pwd AS fm_is_pwd, fm.is_senior AS fm_is_senior, fm.civil_status AS fm_civil_status, fm.id_photo_path AS fm_id_photo_path, fm.birthdate AS fm_birthdate
    FROM barangay_clearances bc 
    JOIN users u ON u.id = bc.user_id 
    LEFT JOIN residents r ON r.user_id = u.id 
    LEFT JOIN family_members fm ON bc.family_member_id = fm.id
    ORDER BY bc.created_at DESC
')->fetchAll();

// Get all document requests
$documents = $pdo->query('
    SELECT dr.*, u.full_name, u.email, dt.pdf_template_path,
           fm.full_name AS fm_name, fm.is_pwd AS fm_is_pwd, fm.is_senior AS fm_is_senior, fm.civil_status AS fm_civil_status, fm.id_photo_path AS fm_id_photo_path, fm.birthdate AS fm_birthdate
    FROM document_requests dr 
    JOIN users u ON u.id = dr.user_id 
    LEFT JOIN document_types dt ON dt.name = dr.doc_type
    LEFT JOIN family_members fm ON dr.family_member_id = fm.id
    ORDER BY dr.id DESC
')->fetchAll();

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

$requests_view_meta = [
	'all' => [
		'page_title' => 'Document Requests',
		'heading' => 'All Document Requests',
		'sub' => 'Review and manage all document and clearance requests',
	],
	'pending' => [
		'page_title' => 'Pending Requests',
		'heading' => 'Pending Document Requests',
		'sub' => 'Requests waiting for review',
	],
	'approved' => [
		'page_title' => 'Ready to Pick Up',
		'heading' => 'Ready to Pick Up',
		'sub' => 'Approved requests ready for resident pickup',
	],
	'released' => [
		'page_title' => 'Released Requests',
		'heading' => 'Released Document Requests',
		'sub' => 'Completed and released requests',
	],
	'rejected' => [
		'page_title' => 'Rejected Requests',
		'heading' => 'Rejected Document Requests',
		'sub' => 'Requests that were rejected',
	],
];
$requests_view_key = $admin_requests_page_status === null ? 'all' : $admin_requests_page_status;
if ($admin_requests_page_status !== null && !array_key_exists($admin_requests_page_status, $requests_view_meta)) {
	$rv_meta = [
		'page_title' => 'Document Requests',
		'heading' => 'Document Requests',
		'sub' => 'Filtered list',
	];
} else {
	$rv_meta = $requests_view_meta[$requests_view_key];
}
$page_title = $rv_meta['page_title'];
$breadcrumb = [
	['title' => $rv_meta['heading']],
];

require_once __DIR__ . '/header.php';
?>

<style>
.btn-action {
	width: 32px;
	height: 32px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	padding: 0;
	font-size: 0.8rem;
	border-radius: 50%;
}
.admin-table .dropdown-toggle-actions::after { display: none; }
.admin-table td .dropdown-menu { z-index: 1055; min-width: 12rem; }
.badge[role="button"] { transition: all 0.2s ease; border: 1px solid transparent !important; }
.badge[role="button"]:hover { opacity: 0.85; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-color: rgba(0,0,0,0.1) !important; }
</style>

<div class="admin-table">
	<div class="p-3 border-bottom d-flex justify-content-between align-items-start flex-wrap gap-2">
		<div>
			<h5 class="mb-0"><i class="fas fa-file-alt me-2"></i><?php echo htmlspecialchars($rv_meta['heading']); ?></h5>
			<p class="text-muted mb-0"><?php echo htmlspecialchars($rv_meta['sub']); ?></p>
		</div>
		<form action="" method="GET" class="input-group" style="max-width: 300px;">
			<?php if (isset($_GET['status_filter'])): ?>
				<input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($_GET['status_filter']); ?>">
			<?php endif; ?>
			<span class="input-group-text"><i class="fas fa-search"></i></span>
			<input type="text" name="search" class="form-control" placeholder="Search requests..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
		</form>
	</div>
	
	<?php $show_request_checkboxes = ($admin_requests_page_status !== 'canceled'); ?>
	<form method="post" id="bulkActionForm" class="d-none" action="">
		<?php echo csrf_field(); ?>
		<input type="hidden" name="bulk_action" id="bulkActionField" value="">
		<input type="hidden" name="bulk_notes" id="bulkNotesField" value="">
		<div id="bulkSelectedFields"></div>
	</form>

	<div class="p-3">
		<div class="table-responsive">
			<table class="table table-hover align-middle">
				<thead class="bg-light text-uppercase">
					<tr>
						<?php if ($show_request_checkboxes): ?>
						<th class="py-3 ps-3" style="width: 40px;">
							<input type="checkbox" class="form-check-input" id="selectAllRequests" onclick="toggleSelectAll(this)">
						</th>
						<?php endif; ?>

						<th class="py-3 <?php echo $show_request_checkboxes ? '' : 'ps-3'; ?>" style="width: 50px;">#</th>
						<th class="py-3">Name</th>
						<th class="py-3">Status</th>
						<th class="py-3">Date</th>
						<th class="py-3 pe-3 text-center">
							<div class="d-flex align-items-center justify-content-center gap-2">
								Action
								<?php if ($show_request_checkboxes): ?>
								<div class="dropdown">
									<button class="btn btn-sm btn-light border-0 text-secondary p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" style="width: 24px; height: 24px;">
										<i class="fas fa-ellipsis-v" style="font-size: 0.85rem;"></i>
									</button>
									<ul class="dropdown-menu shadow border-0 py-2 small text-none">
										<?php if ($admin_requests_page_status === 'pending'): ?>
											<li>
												<button type="button" class="dropdown-item rounded-0 py-2" onclick="adminBulkSubmit('mark_ready');">
													Ready to pick up
												</button>
											</li>
											<li>
												<button type="button" class="dropdown-item rounded-0 py-2" onclick="adminBulkRejectOpen();">
													Rejected
												</button>
											</li>
										<?php elseif ($admin_requests_page_status === 'approved'): ?>
											<li>
												<button type="button" class="dropdown-item rounded-0 py-2" onclick="adminBulkSubmit('mark_released');">
													Released
												</button>
											</li>
											<li>
												<button type="button" class="dropdown-item rounded-0 py-2" onclick="adminBulkRejectOpen();">
													Rejected
												</button>
											</li>
										<?php elseif ($admin_requests_page_status === 'released'): ?>
											<li>
												<button type="button" class="dropdown-item rounded-0 py-2" onclick="adminBulkSubmit('undo_release');">
													Undo Release
												</button>
											</li>
										<?php elseif ($admin_requests_page_status === 'rejected'): ?>
											<li>
												<button type="button" class="dropdown-item rounded-0 py-2" onclick="adminBulkSubmit('undo_reject');">
													Undo Reject
												</button>
											</li>
										<?php elseif ($admin_requests_page_status === null): ?>
											<li>
												<button type="button" class="dropdown-item rounded-0 py-2" onclick="adminBulkSubmit('mark_ready');">
													Ready to pick up
												</button>
											</li>
											<li>
												<button type="button" class="dropdown-item rounded-0 py-2" onclick="adminBulkSubmit('mark_released');">
													Released
												</button>
											</li>
											<li>
												<button type="button" class="dropdown-item rounded-0 py-2" onclick="adminBulkRejectOpen();">
													Rejected
												</button>
											</li>
											<li><hr class="dropdown-divider"></li>
											<li>
												<button type="button" class="dropdown-item rounded-0 py-2" onclick="adminBulkSubmit('undo_release');">
													Undo Release
												</button>
											</li>
											<li>
												<button type="button" class="dropdown-item rounded-0 py-2" onclick="adminBulkSubmit('undo_reject');">
													Undo Reject
												</button>
											</li>
										<?php else: ?>
											<li class="px-3 py-2 text-muted small">Select items to manage</li>
										<?php endif; ?>
									</ul>
								</div>
								<?php endif; ?>
							</div>
						</th>
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
							'user_id' => date('Y') . '-' . str_pad($c['user_id'], 4, '0', STR_PAD_LEFT),
							'doc_type' => 'Barangay Clearance',
							'resident' => $c['full_name'],
							'email' => $c['email'],
							'address' => $c['address'] ?? 'N/A',
							'details' => $c['purpose'],
							'status' => $c['status'],
							'date' => $c['created_at'],
							'validity' => $c['validity_days'],
							'notes' => $c['notes'] ?? null,
							'pdf_generated_at' => $c['pdf_generated_at'] ?? null,
							'fm_name' => $c['fm_name'] ?? null,
							'fm_is_pwd' => $c['fm_is_pwd'] ?? 0,
							'fm_is_senior' => $c['fm_is_senior'] ?? 0,
							'fm_civil_status' => $c['fm_civil_status'] ?? null,
							'fm_id_photo_path' => $c['fm_id_photo_path'] ?? null,
							'fm_birthdate' => $c['fm_birthdate'] ?? null
						];
					}
					foreach ($documents as $d) {
						$stored_purpose = '';
						if (!empty($d['indigency_purposes'])) {
							$decoded = json_decode($d['indigency_purposes'], true);
							if (is_array($decoded) && !empty($decoded)) {
								$stored_purpose = $decoded[0];
							} else if (is_string($decoded)) {
								$stored_purpose = $decoded;
							} else {
								$stored_purpose = $d['indigency_purposes'];
							}
						}
						
						$all_requests[] = [
							'type' => 'document',
							'id' => $d['id'],
							'number' => '#' . $d['id'],
							'user_id' => date('Y') . '-' . str_pad($d['user_id'], 4, '0', STR_PAD_LEFT),
							'resident' => $d['full_name'],
							'email' => $d['email'],
							'details' => $d['doc_type'] . ($d['purpose'] ? ': ' . $d['purpose'] : ''),
							'status' => $d['status'],
							'date' => $d['created_at'],
							'notes' => $d['notes'] ?? null,
							'doc_type' => $d['doc_type'],
							'indigency_purpose' => $stored_purpose,
							'pdf_template_path' => $d['pdf_template_path'] ?? null,
							'fm_name' => $d['fm_name'] ?? null,
							'fm_is_pwd' => $d['fm_is_pwd'] ?? 0,
							'fm_is_senior' => $d['fm_is_senior'] ?? 0,
							'fm_civil_status' => $d['fm_civil_status'] ?? null,
							'fm_id_photo_path' => $d['fm_id_photo_path'] ?? null,
							'fm_birthdate' => $d['fm_birthdate'] ?? null
						];
					}
					usort($all_requests, function($a, $b) {
						return strtotime($b['date']) - strtotime($a['date']);
					});
					// Apply status filter if set (dedicated page or legacy ?status_filter= on requests.php)
					$status_filter = $admin_requests_page_status ?? '';
					if (!empty($status_filter)) {
						$all_requests = array_filter($all_requests, function($r) use ($status_filter) {
							return $r['status'] === $status_filter;
						});
					}

					// Search filter
					$search = trim($_GET['search'] ?? '');
					if ($search !== '') {
						$all_requests = array_filter($all_requests, function($r) use ($search) {
							$s = strtolower($search);
							return stripos($r['resident'], $s) !== false || 
							       stripos($r['details'], $s) !== false || 
							       stripos($r['doc_type'], $s) !== false ||
							       stripos($r['number'], $s) !== false;
						});
					}

					// Pagination logic
					$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
					if ($page < 1) $page = 1;

					$limit = 10;
					$total_requests = count($all_requests);
					$total_pages = ceil($total_requests / $limit);

					if ($total_pages > 0 && $page > $total_pages) $page = $total_pages;
					
					$offset = ($page - 1) * $limit;
					$paginated_requests = array_slice($all_requests, $offset, $limit);

					$row_number = $offset + 1;
					?>
					<?php if (empty($paginated_requests)): ?>
						<tr>
							<td colspan="<?php echo $show_request_checkboxes ? '6' : '5'; ?>" class="text-center py-5">
								<p class="text-muted mb-0">No requests found.</p>
							</td>
						</tr>
					<?php else: ?>
						<?php foreach ($paginated_requests as $req): ?>
							<?php
							$statusClass = ''; $statusLabel = ''; $statusIcon = '';
							switch($req['status']) {
								case 'pending':
									$statusClass = 'bg-amber-50 text-amber-600';
									$statusLabel = 'Pending';
									$statusIcon = 'fa-clock';
									break;
								case 'approved':
									$statusClass = 'bg-teal-50 text-teal-600';
									$statusLabel = 'Ready to Pick Up';
									$statusIcon = 'fa-box-open';
									break;
								case 'released':
									$statusClass = 'bg-blue-50 text-blue-600';
									$statusLabel = 'Released';
									$statusIcon = 'fa-check-double';
									break;
								case 'rejected':
									$statusClass = 'bg-rose-50 text-rose-600';
									$statusLabel = 'Rejected';
									$statusIcon = 'fa-times-circle';
									if (!empty($req['notes'])) $statusIcon = 'fa-info-circle';
									break;
								case 'canceled':
									$statusClass = 'bg-secondary bg-opacity-10 text-secondary';
									$statusLabel = 'Cancelled';
									$statusIcon = 'fa-ban';
									break;
								default:
									$statusClass = 'bg-secondary bg-opacity-10 text-secondary';
									$statusLabel = ucfirst($req['status']);
									$statusIcon = 'fa-circle';
							}
							
							// Determine PDF link
							$pdf_link = '';
							$is_indigency = false; $is_good_moral = false; $is_resident_id = false; $is_cohabitation = false;
							if ($req['type'] === 'document' && isset($req['doc_type'])) {
								$is_indigency = (stripos($req['doc_type'], 'Indigency') !== false);
								$is_good_moral = (stripos($req['doc_type'], 'Good Moral') !== false);
								$is_resident_id = (stripos($req['doc_type'], 'Resident ID') !== false);
								$is_cohabitation = (stripos($req['doc_type'], 'Cohabitation') !== false);
							}
							if ($req['type'] === 'clearance') {
								$pdf_link = '../generate_clearance_pdf.php?id=' . (int)$req['id'];
							} elseif ($is_indigency) {
								$pdf_link = '../generate_indigency_cert.php?id=' . (int)$req['id'];
							} elseif ($is_good_moral) {
								$pdf_link = '../generate_good_moral_cert.php?id=' . (int)$req['id'];
							} elseif ($is_cohabitation) {
								$pdf_link = '../generate_cohabitation_cert.php?id=' . (int)$req['id'];
							} elseif ($is_resident_id) {
								$pdf_link = '../generate_resident_id_card.php?id=' . (int)$req['id'];
							} elseif (!empty($req['pdf_template_path'])) {
								$pdf_link = '../' . $req['pdf_template_path'];
							}

							$is_fm = !empty($req['fm_name']);
							$requesterName = $req['resident'] ?? '';
							?>
							<tr>
								<?php if ($show_request_checkboxes): ?>
								<td class="ps-3">
									<input type="checkbox" class="form-check-input row-checkbox" value="<?php echo $req['type'] . '_' . $req['id']; ?>">
								</td>
								<?php endif; ?>
								<!-- # -->
								<td class="<?php echo $show_request_checkboxes ? '' : 'ps-3 '; ?>text-secondary fw-semibold"><?php echo $row_number++; ?></td>
								<!-- Name -->
								<td>
									<?php if ($is_fm): ?>
										<div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($req['fm_name']); ?></div>
									<?php else: ?>
										<div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($requesterName); ?></div>
									<?php endif; ?>
								</td>
								<!-- Status -->
								<td>
									<div role="button" class="badge <?php echo $statusClass; ?> rounded-pill px-3 py-2 btn-admin-view-detail" 
										style="cursor: pointer;"
										data-doc="<?php echo htmlspecialchars($req['doc_type'] ?? 'Barangay Clearance', ENT_QUOTES); ?>"
										data-requester="<?php echo $is_fm ? htmlspecialchars($req['fm_name'], ENT_QUOTES) : htmlspecialchars($requesterName, ENT_QUOTES); ?>"
										data-requester-type="<?php echo $is_fm ? 'Family Member' : 'Owner'; ?>"
										data-family="<?php echo $is_fm ? htmlspecialchars($requesterName, ENT_QUOTES) : ''; ?>"
										data-purpose="<?php echo htmlspecialchars($req['details'] ?? 'N/A', ENT_QUOTES); ?>"
										data-status-label="<?php echo htmlspecialchars($statusLabel, ENT_QUOTES); ?>"
										data-status-class="<?php echo htmlspecialchars($statusClass, ENT_QUOTES); ?>"
										data-icon="<?php echo htmlspecialchars($statusIcon, ENT_QUOTES); ?>"
										data-date="<?php echo date('F d, Y', strtotime($req['date'])); ?>"
										data-notes="<?php echo htmlspecialchars($req['notes'] ?? '', ENT_QUOTES); ?>">
										<i class="fas <?php echo $statusIcon; ?> me-1"></i>
										<?php echo $statusLabel; ?>
									</div>
								</td>
								<!-- Date -->
								<td class="text-secondary small">
									<i class="far fa-calendar-alt me-1 opacity-50"></i>
									<?php echo date('M d, Y', strtotime($req['date'])); ?>
								</td>
								<!-- Action: Direct Icons -->
								<td class="pe-3 text-center">
									<div class="d-flex justify-content-center gap-1">
										<button type="button" class="btn btn-action btn-light text-success btn-admin-view-detail"
											data-doc="<?php echo htmlspecialchars($req['doc_type'] ?? 'Barangay Clearance', ENT_QUOTES); ?>"
											data-requester="<?php echo $is_fm ? htmlspecialchars($req['fm_name'], ENT_QUOTES) : htmlspecialchars($requesterName, ENT_QUOTES); ?>"
											data-requester-type="<?php echo $is_fm ? 'Family Member' : 'Owner'; ?>"
											data-family="<?php echo $is_fm ? htmlspecialchars($requesterName, ENT_QUOTES) : ''; ?>"
											data-purpose="<?php echo htmlspecialchars($req['details'] ?? 'N/A', ENT_QUOTES); ?>"
											data-status-label="<?php echo htmlspecialchars($statusLabel, ENT_QUOTES); ?>"
											data-status-class="<?php echo htmlspecialchars($statusClass, ENT_QUOTES); ?>"
											data-icon="<?php echo htmlspecialchars($statusIcon, ENT_QUOTES); ?>"
											data-date="<?php echo date('F d, Y', strtotime($req['date'])); ?>"
											data-notes="<?php echo htmlspecialchars($req['notes'] ?? '', ENT_QUOTES); ?>"
											title="View Details">
											<i class="fas fa-eye"></i>
										</button>

										<?php if ($req['status'] === 'pending'): ?>
											<form method="post" class="d-inline admin-confirm-form" data-action-name="Ready to pick up">
												<?php echo csrf_field(); ?>
												<input type="hidden" name="id" value="<?php echo (int)$req['id']; ?>">
												<input type="hidden" name="request_type" value="<?php echo htmlspecialchars($req['type']); ?>">
												<input type="hidden" name="status" value="approved">
												<button type="button" class="btn btn-action btn-light text-success btn-confirm-submit" title="Ready to pick up">
													<i class="fas fa-check"></i>
												</button>
											</form>
											<button type="button" class="btn btn-action btn-light text-danger btn-admin-reject"
												data-id="<?php echo (int)$req['id']; ?>"
												data-type="<?php echo htmlspecialchars($req['type']); ?>"
												title="Reject">
												<i class="fas fa-times"></i>
											</button>
										<?php elseif ($req['status'] === 'approved'): ?>
											<?php if (!empty($pdf_link)): ?>
												<a class="btn btn-action btn-light text-info btn-confirm-print" href="<?php echo htmlspecialchars($pdf_link); ?>" target="_blank" rel="noopener" title="Print/Download">
													<i class="fas fa-print"></i>
												</a>
											<?php endif; ?>
											<form method="post" class="d-inline admin-confirm-form" data-action-name="Released">
												<?php echo csrf_field(); ?>
												<input type="hidden" name="id" value="<?php echo (int)$req['id']; ?>">
												<input type="hidden" name="request_type" value="<?php echo htmlspecialchars($req['type']); ?>">
												<input type="hidden" name="status" value="released">
												<button type="button" class="btn btn-action btn-light text-success btn-confirm-submit" title="Mark as Released">
													<i class="fas fa-hand-holding"></i>
												</button>
											</form>
											<button type="button" class="btn btn-action btn-light text-danger btn-admin-reject"
												data-id="<?php echo (int)$req['id']; ?>"
												data-type="<?php echo htmlspecialchars($req['type']); ?>"
												title="Reject">
												<i class="fas fa-times"></i>
											</button>
										<?php elseif ($req['status'] === 'released'): ?>
											<?php if (!empty($pdf_link)): ?>
												<a class="btn btn-action btn-light text-info btn-confirm-print" href="<?php echo htmlspecialchars($pdf_link); ?>" target="_blank" rel="noopener" title="Print/Download">
													<i class="fas fa-print"></i>
												</a>
											<?php endif; ?>
											<form method="post" class="d-inline admin-confirm-form" data-action-name="Undo Release">
												<?php echo csrf_field(); ?>
												<input type="hidden" name="id" value="<?php echo (int)$req['id']; ?>">
												<input type="hidden" name="request_type" value="<?php echo htmlspecialchars($req['type']); ?>">
												<input type="hidden" name="status" value="approved">
												<button type="button" class="btn btn-action btn-light text-warning btn-confirm-submit" title="Undo Release">
													<i class="fas fa-undo"></i>
												</button>
											</form>
										<?php elseif ($req['status'] === 'rejected'): ?>
											<form method="post" class="d-inline admin-confirm-form" data-action-name="Undo Reject">
												<?php echo csrf_field(); ?>
												<input type="hidden" name="id" value="<?php echo (int)$req['id']; ?>">
												<input type="hidden" name="request_type" value="<?php echo htmlspecialchars($req['type']); ?>">
												<input type="hidden" name="status" value="pending">
												<button type="button" class="btn btn-action btn-light text-warning btn-confirm-submit" title="Undo Reject">
													<i class="fas fa-undo"></i>
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

		<!-- Pagination UI -->
		<?php if ($total_pages > 1): ?>
		<div class="d-flex justify-content-between align-items-center p-3 border-top bg-light-subtle">
			<div class="text-muted small">
				Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_requests); ?> of <?php echo $total_requests; ?> entries
			</div>
			<nav>
				<ul class="pagination pagination-sm mb-0">
					<?php 
					$params = $_GET;
					unset($params['page']);
					$query_string = http_build_query($params);
					$base_url = '?' . ($query_string ? $query_string . '&' : '');
					?>
					
					<li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
						<a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $page - 1; ?>">Previous</a>
					</li>
					
					<?php
					$start_loop = max(1, $page - 2);
					$end_loop = min($total_pages, $page + 2);
					
					if ($start_loop > 1) {
						echo '<li class="page-item"><a class="page-link" href="' . $base_url . 'page=1">1</a></li>';
						if ($start_loop > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
					}
					
					for($i = $start_loop; $i <= $end_loop; $i++): ?>
						<li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
							<a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
						</li>
					<?php endfor; ?>
					
					<?php if ($end_loop < $total_pages): ?>
						<?php if ($end_loop < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
						<li class="page-item"><a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a></li>
					<?php endif; ?>
					
					<li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
						<a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $page + 1; ?>">Next</a>
					</li>
				</ul>
			</nav>
		</div>
		<?php endif; ?>
	</div>
</div>

<!-- Admin View Detail Modal -->
<div class="modal fade" id="adminViewDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="rounded-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fas fa-file-alt fa-lg"></i>
                    </div>
                    <h5 class="fw-bold mb-0 text-dark">Request Details</h5>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <table class="table table-borderless mb-0">
                    <tr><td class="text-secondary fw-semibold small" style="width: 130px;">Document</td><td class="fw-bold" id="admin_detail_doc"></td></tr>
                    <tr><td class="text-secondary fw-semibold small">Requester</td><td id="admin_detail_requester"></td></tr>
                    <tr id="admin_detail_family_row" style="display: none;"><td class="text-secondary fw-semibold small">Family of</td><td id="admin_detail_family"></td></tr>
                    <tr><td class="text-secondary fw-semibold small">Purpose</td><td id="admin_detail_purpose"></td></tr>
                    <tr><td class="text-secondary fw-semibold small">Status</td><td id="admin_detail_status"></td></tr>
                    <tr><td class="text-secondary fw-semibold small" style="width: 130px;">Date Filed</td><td id="admin_detail_date"></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Admin Reject Modal -->
<div class="modal fade" id="adminRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body text-center p-5">
                <div class="mb-4">
                    <div class="rounded-circle bg-danger bg-opacity-10 d-inline-flex align-items-center justify-content-center mx-auto" style="width: 80px; height: 80px;">
                        <i class="fas fa-times-circle fa-2x text-danger"></i>
                    </div>
                </div>
                <h5 class="fw-bold text-dark mb-2">Reject Request?</h5>
                <p class="text-secondary mb-3">Please provide a reason for rejecting this request.</p>
                <form method="post" id="adminRejectForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" id="reject_req_id">
                    <input type="hidden" name="request_type" id="reject_req_type">
                    <input type="hidden" name="status" value="rejected">
                    <div class="mb-4 text-start">
                        <textarea name="notes" id="rejectReasonInput" class="form-control bg-light border-0" rows="3" placeholder="State reason for rejection..." required></textarea>
                        <div id="rejectReasonError" class="text-danger small mt-1" style="display: none;">Please provide a reason.</div>
                    </div>
                    <div class="d-flex gap-3 justify-content-center">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">
                            <i class="fas fa-arrow-left me-2"></i>Go Back
                        </button>
                        <button type="submit" class="btn btn-danger rounded-pill px-4">
                            <i class="fas fa-times me-2"></i>Yes, Reject It
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bulk reject (applies reason to all selected rows) -->
<div class="modal fade" id="adminBulkRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body text-center p-5">
                <div class="mb-4">
                    <div class="rounded-circle bg-danger bg-opacity-10 d-inline-flex align-items-center justify-content-center mx-auto" style="width: 80px; height: 80px;">
                        <i class="fas fa-times-circle fa-2x text-danger"></i>
                    </div>
                </div>
                <h5 class="fw-bold text-dark mb-2">Reject selected requests?</h5>
                <p class="text-secondary mb-3">This reason will be saved for every selected request.</p>
                <div class="mb-4 text-start">
                    <textarea id="bulkRejectReasonInput" class="form-control bg-light border-0" rows="3" placeholder="State reason for rejection..." required></textarea>
                    <div id="bulkRejectReasonError" class="text-danger small mt-1" style="display: none;">Please provide a reason.</div>
                </div>
                <div class="d-flex gap-3 justify-content-center">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">
                        <i class="fas fa-arrow-left me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-danger rounded-pill px-4" id="adminBulkRejectConfirmBtn">
                        <i class="fas fa-times me-2"></i>Reject selected
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Search JS (Ignored because search was moved to server-side)
/*
(function() {
    const searchInput = document.getElementById('requestsSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const table = searchInput.closest('.admin-table');
            if (!table) return;
            table.querySelectorAll('tbody tr').forEach(function(row) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }
})();
*/

function toggleSelectAll(master) {
    document.querySelectorAll('.row-checkbox').forEach(function(cb) {
        cb.checked = master.checked;
    });
}

function adminBulkGetSelectedValues() {
    return Array.prototype.map.call(document.querySelectorAll('.row-checkbox:checked'), function(cb) { return cb.value; });
}

function adminBulkSubmit(action) {
    var vals = adminBulkGetSelectedValues();
    if (!vals.length) {
        Swal.fire({
            icon: 'warning',
            title: 'No Selection',
            text: 'Please select at least one request using the checkboxes.',
            confirmButtonColor: '#0f766e'
        });
        return;
    }

    // Determine readable action name for confirmation
    var actionName = '';
    var confirmText = 'Yes, Proceed';
    var confirmColor = '#0f766e';

    if (action === 'mark_ready') { actionName = 'Ready to Pick Up'; }
    else if (action === 'mark_released') { actionName = 'Mark as Released'; }
    else if (action === 'undo_release') { actionName = 'Undo Release'; confirmColor = '#d97706'; }
    else if (action === 'undo_reject') { actionName = 'Undo Reject'; confirmColor = '#d97706'; }
    else if (action === 'reject') { actionName = 'Reject'; confirmColor = '#dc3545'; }

    Swal.fire({
        title: 'Confirm',
        text: 'Are you sure you want to perform "' + actionName + '" on ' + vals.length + ' selected item(s)?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#6c757d',
        confirmButtonText: confirmText
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('bulkActionField').value = action;
            if (action !== 'reject') {
                document.getElementById('bulkNotesField').value = '';
            }
            var wrap = document.getElementById('bulkSelectedFields');
            wrap.innerHTML = '';
            vals.forEach(function(v) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'selected[]';
                inp.value = v;
                wrap.appendChild(inp);
            });
            document.getElementById('bulkActionForm').submit();
        }
    });
}

function adminBulkRejectOpen() {
    if (!adminBulkGetSelectedValues().length) {
        Swal.fire({
            icon: 'warning',
            title: 'No Selection',
            text: 'Please select at least one request using the checkboxes.',
            confirmButtonColor: '#0f766e'
        });
        return;
    }
    document.getElementById('bulkRejectReasonInput').value = '';
    document.getElementById('bulkRejectReasonError').style.display = 'none';
    var el = document.getElementById('adminBulkRejectModal');
    new bootstrap.Modal(el).show();
}

(function() {
    var bulkConfirm = document.getElementById('adminBulkRejectConfirmBtn');
    if (!bulkConfirm) return;
    bulkConfirm.addEventListener('click', function() {
        var reason = document.getElementById('bulkRejectReasonInput').value.trim();
        if (!reason) {
            document.getElementById('bulkRejectReasonError').style.display = 'block';
            document.getElementById('bulkRejectReasonInput').focus();
            return;
        }
        document.getElementById('bulkRejectReasonError').style.display = 'none';
        document.getElementById('bulkNotesField').value = reason;
        var modalEl = document.getElementById('adminBulkRejectModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        adminBulkSubmit('reject');
    });
})();

// View Detail Modal populator and opener
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-admin-view-detail');
    if (!btn) return;
    
    document.getElementById('admin_detail_doc').textContent = btn.dataset.doc;
    document.getElementById('admin_detail_requester').innerHTML = btn.dataset.requester + ' <span class="badge bg-light text-secondary">' + btn.dataset.requesterType + '</span>';
    document.getElementById('admin_detail_purpose').textContent = btn.dataset.purpose;
    const statusLower = btn.dataset.statusLabel.toLowerCase();
    const isReasonable = (statusLower === 'rejected' || statusLower === 'cancelled' || statusLower === 'canceled');
    document.getElementById('admin_detail_status').innerHTML = '<span class="badge ' + btn.dataset.statusClass + ' rounded-pill px-3 py-2"><i class="fas ' + btn.dataset.icon + ' me-1"></i>' + btn.dataset.statusLabel + '</span>' + 
        (isReasonable && btn.dataset.notes && btn.dataset.notes.trim() !== '' ? 
        ' <a href="javascript:void(0)" class="text-primary ms-2 small fw-bold btn-show-reason" title="View Reason"><i class="fas fa-eye"></i> View Details</a>' : '');
    document.getElementById('admin_detail_date').textContent = btn.dataset.date;
    
    // Store notes for the "show reason" link
    var reasonLink = document.querySelector('#adminViewDetailModal .btn-show-reason');
    if (reasonLink) {
        reasonLink.onclick = function() { showRejectionReason(btn.dataset.notes, btn.dataset.statusLabel); };
    }
    
    // Show family of row if it's a family member
    var familyRow = document.getElementById('admin_detail_family_row');
    if (btn.dataset.family && btn.dataset.family.trim() !== '') {
        document.getElementById('admin_detail_family').textContent = btn.dataset.family;
        familyRow.style.display = '';
    } else {
        if (familyRow) familyRow.style.display = 'none';
    }

    // Initial modal setup
    var mainModalEl = document.getElementById('adminViewDetailModal');
    var modal = bootstrap.Modal.getOrCreateInstance(mainModalEl);
    modal.show();
});

function showRejectionReason(notes, status) {
    const statusLower = (status || '').toLowerCase();
    const isCancellation = statusLower === 'cancelled' || statusLower === 'canceled';
    const titleText = isCancellation ? 'Reason for Cancellation' : 'Reason for Rejection';
    const titleColor = isCancellation ? 'text-secondary' : 'text-rose-600';
    const borderColor = isCancellation ? 'border-secondary' : 'border-rose-500';
    const btnColor = isCancellation ? '#6c757d' : '#e11d48';

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

// Reject Modal logic
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-admin-reject');
    if (!btn) return;
    
    document.getElementById('reject_req_id').value = btn.dataset.id;
    document.getElementById('reject_req_type').value = btn.dataset.type;
    document.getElementById('rejectReasonInput').value = '';
    document.getElementById('rejectReasonError').style.display = 'none';
    
    var modal = new bootstrap.Modal(document.getElementById('adminRejectModal'));
    modal.show();
});

// Reject form validation
document.getElementById('adminRejectForm').addEventListener('submit', function(e) {
    var reason = document.getElementById('rejectReasonInput').value.trim();
    if (!reason) {
        e.preventDefault();
        document.getElementById('rejectReasonError').style.display = 'block';
        document.getElementById('rejectReasonInput').focus();
    }
});
</script>

<?php if (isset($_SESSION['bulk_result'])): ?>
<script>
(function() {
    var okCount = <?php echo (int)$_SESSION['bulk_result']['ok']; ?>;
    var skipCount = <?php echo (int)$_SESSION['bulk_result']['skip']; ?>;
    var skippedItems = <?php echo json_encode($_SESSION['bulk_result']['skipped_items'] ?? []); ?>;
    
    var htmlContent = '<strong>' + okCount + '</strong> request(s) successfully updated.';
    if (skipCount > 0) {
        htmlContent += '<br><div class="mt-2 small text-muted">(' + skipCount + ' skipped — wrong status or not found.)</div>';
        htmlContent += '<div class="mt-3"><a href="javascript:void(0)" id="viewSkipDetails" class="text-primary fw-bold" style="text-decoration: none;"><i class="fas fa-info-circle me-1"></i>View Details</a></div>';
    }

    Swal.fire({
        title: 'Update Complete',
        html: htmlContent,
        icon: 'success',
        confirmButtonColor: '#0f766e',
        confirmButtonText: 'Great!',
        didOpen: () => {
            const btn = document.getElementById('viewSkipDetails');
            if (btn) {
                btn.onclick = () => {
                    let detailsHtml = '<div class="text-start small" style="max-height: 300px; overflow-y: auto;">';
                    detailsHtml += '<table class="table table-sm table-bordered">';
                    detailsHtml += '<thead class="bg-light"><tr><th>ID/Type</th><th>Resident</th><th>Reason</th></tr></thead><tbody>';
                    skippedItems.forEach(item => {
                        detailsHtml += '<tr>';
                        detailsHtml += '<td>' + (item.label || item.id) + '</td>';
                        detailsHtml += '<td>' + (item.name || 'N/A') + '</td>';
                        detailsHtml += '<td class="text-danger">' + item.reason + '</td>';
                        detailsHtml += '</tr>';
                    });
                    detailsHtml += '</tbody></table></div>';
                    
                    Swal.fire({
                        title: 'Skipped Requests Details',
                        html: detailsHtml,
                        icon: 'info',
                        width: '600px',
                        confirmButtonColor: '#0f766e'
                    });
                };
            }
        }
    });
})();
</script>
<?php unset($_SESSION['bulk_result']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['action_success'])): ?>
<script>
(function() {
    var title = <?php echo json_encode($_SESSION['action_success']['title']); ?>;
    var text = <?php echo json_encode($_SESSION['action_success']['text']); ?>;
    var smsError = <?php echo json_encode($_SESSION['action_success']['sms_error'] ?? null); ?>;
    
    Swal.fire({
        title: title,
        text: text,
        icon: 'success',
        footer: smsError ? '<div class="text-danger small w-100 text-center"><i class="fas fa-exclamation-triangle me-1"></i> SMS Not Sent: ' + smsError + '</div>' : null,
        confirmButtonColor: '#0f766e'
    });
})();
</script>
<?php unset($_SESSION['action_success']); ?>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
