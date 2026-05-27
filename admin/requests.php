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

function admin_requests_notification_alerts(?string $userEmail, $userPhone, string $status, ?array $requestData): array
{
	$results = ['email' => null, 'sms' => null];
	if (!$requestData)
		return $results;

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

		// ── Walk-in Request ──────────────────────────────────────────
		if (!empty($_POST['walkin_action'])) {
			$wi_resident_id = (int) ($_POST['wi_resident_id'] ?? 0);
			$wi_requestor_name = trim($_POST['wi_requestor_name'] ?? '');
			$wi_civil_status = trim($_POST['wi_civil_status'] ?? '');
			$wi_purok = trim($_POST['wi_purok'] ?? '');
			$wi_doc_type = trim($_POST['wi_doc_type'] ?? '');
			$wi_purpose = trim($_POST['wi_purpose'] ?? '');
			
			$wi_payment_method = trim($_POST['wi_payment_method'] ?? 'Cash');
			$wi_payment_ref = trim($_POST['wi_payment_ref'] ?? '');

			if ($wi_doc_type && ($wi_resident_id || $wi_requestor_name)) {
				$wi_user_id = $admin_uid; // default to admin
				$final_purpose = $wi_purpose;

				if ($wi_resident_id) {
					// Get resident's user_id
					$res_stmt = $pdo->prepare('SELECT user_id FROM residents WHERE id = ? LIMIT 1');
					$res_stmt->execute([$wi_resident_id]);
					$res_row = $res_stmt->fetch(PDO::FETCH_ASSOC);
					if ($res_row) {
						$wi_user_id = (int) $res_row['user_id'];
					}
				} else {
					// Unregistered walk-in requestor
					$tags = '[Walk-in Requestor: ' . $wi_requestor_name . ']';
					if ($wi_civil_status) $tags .= '[CS: ' . $wi_civil_status . ']';
					if ($wi_purok) $tags .= '[Purok: ' . $wi_purok . ']';
					$final_purpose = $tags . ' ' . $wi_purpose;
				}

				$db_ref = ($wi_payment_method === 'GCash') ? $wi_payment_ref : null;
				$payment_receipt_path = null;
				if ($wi_payment_method === 'GCash' && isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] === UPLOAD_ERR_OK) {
					$file = $_FILES['payment_receipt'];
					$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
					if (in_array($file['type'], $allowedTypes)) {
						$uploadDir = __DIR__ . '/../uploads/receipts/';
						if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
						$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
						$filename = 'receipt_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
						if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
							$payment_receipt_path = 'uploads/receipts/' . $filename;
						}
					}
				}

				if ($wi_doc_type === 'Barangay Clearance') {
					$cn = 'BC-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6));
					$pdo->prepare('INSERT INTO barangay_clearances (user_id, clearance_number, purpose, status, payment_reference_no, payment_receipt, created_at) VALUES (?, ?, ?, "pending", ?, ?, NOW())')
						->execute([$wi_user_id, $cn, $final_purpose, $db_ref, $payment_receipt_path]);
				} else {
					$pdo->prepare('INSERT INTO document_requests (user_id, doc_type, purpose, status, payment_reference_no, payment_receipt, created_at) VALUES (?, ?, ?, "pending", ?, ?, NOW())')
						->execute([$wi_user_id, $wi_doc_type, $final_purpose, $db_ref, $payment_receipt_path]);
				}
				$_SESSION['action_success'] = [
					'title' => 'Walk-in Request Added',
					'text' => $wi_doc_type . ' request for ' . ($wi_requestor_name ? $wi_requestor_name : 'the selected resident') . ' has been created and is now pending.',
				];
				redirect('requests.php');
			} else {
				$_SESSION['action_error'] = [
					'title' => 'Incomplete Form',
					'text' => 'Please provide a Requestor Name and select a Document Type.'
				];
				redirect('requests.php');
			}
		}
		// ── End Walk-in ───────────────────────────────────────────────

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
						if ($bulk_action === 'mark_ready')
							$expected = 'pending';
						elseif ($bulk_action === 'mark_released')
							$expected = 'approved';
						elseif ($bulk_action === 'undo_release')
							$expected = 'released';
						elseif ($bulk_action === 'undo_reject')
							$expected = 'rejected';
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

// Walk-in: fetch all active residents + document types for the modal
$wi_residents = $pdo->query("
    SELECT r.id, r.user_id, u.full_name, r.address
    FROM residents r
    JOIN users u ON u.id = r.user_id
    WHERE u.role = 'resident'
    ORDER BY u.full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$wi_doc_types = $pdo->query('SELECT name, price FROM document_types ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

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

	.admin-table .dropdown-toggle-actions::after {
		display: none;
	}

	.admin-table td .dropdown-menu {
		z-index: 1055;
		min-width: 12rem;
	}

	.badge[role="button"] {
		transition: all 0.2s ease;
		border: 1px solid transparent !important;
	}

	.badge[role="button"]:hover {
		opacity: 0.85;
		transform: translateY(-1px);
		box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
		border-color: rgba(0, 0, 0, 0.1) !important;
	}
</style>

<div class="admin-table">
	<div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
		<div>
			<h5 class="mb-0"><i class="fas fa-file-signature me-2"></i><?php echo htmlspecialchars($rv_meta['heading']); ?>
			</h5>
			<p class="text-muted mb-0"><?php echo htmlspecialchars($rv_meta['sub']); ?></p>
		</div>
		<div class="d-flex align-items-center gap-2 flex-nowrap">
			<button type="button" class="btn btn-primary btn-sm rounded-pill px-3 text-nowrap" data-bs-toggle="modal"
				data-bs-target="#walkinRequestModal">
				<i class="fas fa-plus me-1"></i> Walk-in Request
			</button>
			<form action="" method="GET" class="input-group" style="max-width: 260px; min-width: 180px;">
				<?php if (isset($_GET['status_filter'])): ?>
					<input type="hidden" name="status_filter"
						value="<?php echo htmlspecialchars($_GET['status_filter']); ?>">
				<?php endif; ?>
				<span class="input-group-text"><i class="fas fa-search"></i></span>
				<input type="text" name="search" class="form-control" placeholder="Search requests..."
					value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
			</form>
		</div>
	</div>

	<?php $show_request_checkboxes = ($admin_requests_page_status !== 'canceled'); ?>
	<form method="post" id="bulkActionForm" class="d-none" action="">
		<?php echo csrf_field(); ?>
		<input type="hidden" name="bulk_action" id="bulkActionField" value="">
		<input type="hidden" name="bulk_notes" id="bulkNotesField" value="">
		<div id="bulkSelectedFields"></div>
	</form>

	<div class="table-card">
		<div class="table-responsive">
			<table class="table table-hover align-middle">
				<thead class="bg-light text-uppercase">
					<tr>
						<?php if ($show_request_checkboxes): ?>
							<th class="py-3 ps-3" style="width: 40px;">
								<input type="checkbox" class="form-check-input" id="selectAllRequests"
									onclick="toggleSelectAll(this)">
							</th>
						<?php endif; ?>

						<th class="py-3 <?php echo $show_request_checkboxes ? '' : 'ps-3'; ?>" style="width: 50px;">#
						</th>
						<th class="py-3">Name</th>
						<th class="py-3">Type</th>
						<th class="py-3">Status</th>
						<th class="py-3">Date</th>
						<th class="py-3 pe-3 text-center">
							<div class="d-flex align-items-center justify-content-center gap-2">
								Action
								<?php if ($show_request_checkboxes): ?>
									<div class="dropdown">
										<button class="btn btn-sm btn-light border-0 text-secondary p-0" type="button"
											data-bs-toggle="dropdown" aria-expanded="false" title="Actions"
											style="width: 24px; height: 24px;">
											<i class="fas fa-ellipsis-v" style="font-size: 0.85rem;"></i>
										</button>
										<ul class="dropdown-menu shadow border-0 py-2 small text-none">
											<?php if ($admin_requests_page_status === 'pending'): ?>
												<li>
													<button type="button" class="dropdown-item rounded-0 py-2"
														onclick="adminBulkSubmit('mark_ready');">
														Ready to pick up
													</button>
												</li>
												<li>
													<button type="button" class="dropdown-item rounded-0 py-2"
														onclick="adminBulkRejectOpen();">
														Rejected
													</button>
												</li>
											<?php elseif ($admin_requests_page_status === 'approved'): ?>
												<li>
													<button type="button" class="dropdown-item rounded-0 py-2"
														onclick="adminBulkSubmit('mark_released');">
														Released
													</button>
												</li>
												<li>
													<button type="button" class="dropdown-item rounded-0 py-2"
														onclick="adminBulkRejectOpen();">
														Rejected
													</button>
												</li>
											<?php elseif ($admin_requests_page_status === 'released'): ?>
												<li>
													<button type="button" class="dropdown-item rounded-0 py-2"
														onclick="adminBulkSubmit('undo_release');">
														Undo Release
													</button>
												</li>
											<?php elseif ($admin_requests_page_status === 'rejected'): ?>
												<li>
													<button type="button" class="dropdown-item rounded-0 py-2"
														onclick="adminBulkSubmit('undo_reject');">
														Undo Reject
													</button>
												</li>
											<?php elseif ($admin_requests_page_status === null): ?>
												<li>
													<button type="button" class="dropdown-item rounded-0 py-2"
														onclick="adminBulkSubmit('mark_ready');">
														Ready to pick up
													</button>
												</li>
												<li>
													<button type="button" class="dropdown-item rounded-0 py-2"
														onclick="adminBulkSubmit('mark_released');">
														Released
													</button>
												</li>
												<li>
													<button type="button" class="dropdown-item rounded-0 py-2"
														onclick="adminBulkRejectOpen();">
														Rejected
													</button>
												</li>
												<li>
													<hr class="dropdown-divider">
												</li>
												<li>
													<button type="button" class="dropdown-item rounded-0 py-2"
														onclick="adminBulkSubmit('undo_release');">
														Undo Release
													</button>
												</li>
												<li>
													<button type="button" class="dropdown-item rounded-0 py-2"
														onclick="adminBulkSubmit('undo_reject');">
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
					usort($all_requests, function ($a, $b) {
						return strtotime($b['date']) - strtotime($a['date']);
					});
					// Apply status filter if set (dedicated page or legacy ?status_filter= on requests.php)
					$status_filter = $admin_requests_page_status ?? '';
					if (!empty($status_filter)) {
						$all_requests = array_filter($all_requests, function ($r) use ($status_filter) {
							return $r['status'] === $status_filter;
						});
					}

					// Search filter
					$search = trim($_GET['search'] ?? '');
					if ($search !== '') {
						$all_requests = array_filter($all_requests, function ($r) use ($search) {
							$s = strtolower($search);
							return stripos($r['resident'], $s) !== false ||
								stripos($r['details'], $s) !== false ||
								stripos($r['doc_type'], $s) !== false ||
								stripos($r['number'], $s) !== false;
						});
					}

					// Pagination logic
					$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
					if ($page < 1)
						$page = 1;

					$limit = 10;
					$total_requests = count($all_requests);
					$total_pages = ceil($total_requests / $limit);

					if ($total_pages > 0 && $page > $total_pages)
						$page = $total_pages;

					$offset = ($page - 1) * $limit;
					$paginated_requests = array_slice($all_requests, $offset, $limit);

					$row_number = $offset + 1;
					?>
					<?php if (empty($paginated_requests)): ?>
						<tr>
							<td colspan="<?php echo $show_request_checkboxes ? '7' : '6'; ?>" class="text-center py-5">
								<p class="text-muted mb-0">No requests found.</p>
							</td>
						</tr>
					<?php else: ?>
						<?php foreach ($paginated_requests as $req): ?>
							<?php
							$statusClass = '';
							$statusLabel = '';
							$statusIcon = '';
							switch ($req['status']) {
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
									if (!empty($req['notes']))
										$statusIcon = 'fa-info-circle';
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
							$is_indigency = false;
							$is_good_moral = false;
							$is_resident_id = false;
							$is_cohabitation = false;
							if ($req['type'] === 'document' && isset($req['doc_type'])) {
								$is_indigency = (stripos($req['doc_type'], 'Indigency') !== false);
								$is_good_moral = (stripos($req['doc_type'], 'Good Moral') !== false);
								$is_resident_id = (stripos($req['doc_type'], 'Resident ID') !== false);
								$is_cohabitation = (stripos($req['doc_type'], 'Cohabitation') !== false);
							}
							if ($req['type'] === 'clearance') {
								$pdf_link = '../generate_clearance_pdf.php?id=' . (int) $req['id'];
							} elseif ($is_indigency) {
								$pdf_link = '../generate_indigency_cert.php?id=' . (int) $req['id'];
							} elseif ($is_good_moral) {
								$pdf_link = '../generate_good_moral_cert.php?id=' . (int) $req['id'];
							} elseif ($is_cohabitation) {
								$pdf_link = '../generate_cohabitation_cert.php?id=' . (int) $req['id'];
							} elseif ($is_resident_id) {
								$pdf_link = '../generate_resident_id_card.php?id=' . (int) $req['id'];
							} elseif (!empty($req['pdf_template_path'])) {
								$pdf_link = '../' . $req['pdf_template_path'];
							}

							$is_fm = !empty($req['fm_name']);
							$requesterName = $req['resident'] ?? '';
							$purposeText = $req['details'] ?? 'N/A';

							$tagStart = strpos($purposeText, '[Walk-in Requestor: ');
							if ($tagStart !== false) {
								$endBracket = strpos($purposeText, ']', $tagStart);
								if ($endBracket !== false) {
									$requesterName = trim(substr($purposeText, $tagStart + 20, $endBracket - ($tagStart + 20)));
									$purposeText = trim(substr_replace($purposeText, '', $tagStart, $endBracket - $tagStart + 1));

									// Clean up any double colons or spaces left over
									$purposeText = trim(str_replace(':  ', ': ', $purposeText));
									if (str_ends_with($purposeText, ':')) {
										$purposeText = trim(substr($purposeText, 0, -1));
									}
								}
							}
							?>
							<tr>
								<?php if ($show_request_checkboxes): ?>
									<td class="ps-3">
										<input type="checkbox" class="form-check-input row-checkbox"
											value="<?php echo $req['type'] . '_' . $req['id']; ?>">
									</td>
								<?php endif; ?>
								<!-- # -->
								<td class="<?php echo $show_request_checkboxes ? '' : 'ps-3 '; ?>text-dark fw-semibold">
									<?php echo $row_number++; ?></td>
								<!-- Name -->
								<td>
									<?php if ($is_fm): ?>
										<div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($req['fm_name']); ?></div>
									<?php else: ?>
										<div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($requesterName); ?></div>
									<?php endif; ?>
								</td>
								<!-- Type -->
								<td>
									<div class="text-dark"><?php echo htmlspecialchars($req['doc_type']); ?></div>
								</td>
								<!-- Status -->
								<td>
									<div role="button"
										class="badge <?php echo $statusClass; ?> rounded-pill px-3 py-2 btn-admin-view-detail"
										style="cursor: pointer;"
										data-doc="<?php echo htmlspecialchars($req['doc_type'] ?? 'Barangay Clearance', ENT_QUOTES); ?>"
										data-requester="<?php echo $is_fm ? htmlspecialchars($req['fm_name'], ENT_QUOTES) : htmlspecialchars($requesterName, ENT_QUOTES); ?>"
										data-requester-type="<?php echo $is_fm ? 'Family Member' : 'Owner'; ?>"
										data-family="<?php echo $is_fm ? htmlspecialchars($requesterName, ENT_QUOTES) : ''; ?>"
										data-purpose="<?php echo htmlspecialchars($purposeText, ENT_QUOTES); ?>"
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
								<td class="text-muted small">
									<i class="far fa-calendar-alt me-1 opacity-50"></i>
									<?php echo date('M d, Y', strtotime($req['date'])); ?>
								</td>
								<!-- Action: Direct Icons -->
								<td class="pe-3 text-center">
									<div class="d-flex justify-content-center gap-1">
										<button type="button"
											class="btn btn-action btn-light text-success btn-admin-view-detail"
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
											<form method="post" class="d-inline admin-confirm-form"
												data-action-name="Ready to pick up">
												<?php echo csrf_field(); ?>
												<input type="hidden" name="id" value="<?php echo (int) $req['id']; ?>">
												<input type="hidden" name="request_type"
													value="<?php echo htmlspecialchars($req['type']); ?>">
												<input type="hidden" name="status" value="approved">
												<button type="button"
													class="btn btn-action btn-light text-success btn-confirm-submit"
													title="Ready to pick up">
													<i class="fas fa-check"></i>
												</button>
											</form>
											<button type="button" class="btn btn-action btn-light text-danger btn-admin-reject"
												data-id="<?php echo (int) $req['id']; ?>"
												data-type="<?php echo htmlspecialchars($req['type']); ?>" title="Reject">
												<i class="fas fa-times"></i>
											</button>
										<?php elseif ($req['status'] === 'approved'): ?>
											<?php if (!empty($pdf_link)): ?>
												<a class="btn btn-action btn-light text-info btn-confirm-print"
													href="<?php echo htmlspecialchars($pdf_link); ?>" target="_blank" rel="noopener"
													title="Print/Download">
													<i class="fas fa-print"></i>
												</a>
											<?php endif; ?>
											<form method="post" class="d-inline admin-confirm-form" data-action-name="Released">
												<?php echo csrf_field(); ?>
												<input type="hidden" name="id" value="<?php echo (int) $req['id']; ?>">
												<input type="hidden" name="request_type"
													value="<?php echo htmlspecialchars($req['type']); ?>">
												<input type="hidden" name="status" value="released">
												<button type="button"
													class="btn btn-action btn-light text-success btn-confirm-submit"
													title="Mark as Released">
													<i class="fas fa-hand-holding"></i>
												</button>
											</form>
											<button type="button" class="btn btn-action btn-light text-danger btn-admin-reject"
												data-id="<?php echo (int) $req['id']; ?>"
												data-type="<?php echo htmlspecialchars($req['type']); ?>" title="Reject">
												<i class="fas fa-times"></i>
											</button>
										<?php elseif ($req['status'] === 'released'): ?>
											<?php if (!empty($pdf_link)): ?>
												<a class="btn btn-action btn-light text-info btn-confirm-print"
													href="<?php echo htmlspecialchars($pdf_link); ?>" target="_blank" rel="noopener"
													title="Print/Download">
													<i class="fas fa-print"></i>
												</a>
											<?php endif; ?>
											<form method="post" class="d-inline admin-confirm-form" data-action-name="Undo Release">
												<?php echo csrf_field(); ?>
												<input type="hidden" name="id" value="<?php echo (int) $req['id']; ?>">
												<input type="hidden" name="request_type"
													value="<?php echo htmlspecialchars($req['type']); ?>">
												<input type="hidden" name="status" value="approved">
												<button type="button"
													class="btn btn-action btn-light text-warning btn-confirm-submit"
													title="Undo Release">
													<i class="fas fa-undo"></i>
												</button>
											</form>
										<?php elseif ($req['status'] === 'rejected'): ?>
											<form method="post" class="d-inline admin-confirm-form" data-action-name="Undo Reject">
												<?php echo csrf_field(); ?>
												<input type="hidden" name="id" value="<?php echo (int) $req['id']; ?>">
												<input type="hidden" name="request_type"
													value="<?php echo htmlspecialchars($req['type']); ?>">
												<input type="hidden" name="status" value="pending">
												<button type="button"
													class="btn btn-action btn-light text-warning btn-confirm-submit"
													title="Undo Reject">
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

		<!-- Pagination UI & Info Bar inside table-card -->
		<?php if ($total_pages > 1): ?>
			<div class="table-info-bar">
				<div>
					Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_requests); ?></strong> of <strong><?php echo $total_requests; ?></strong> entries
				</div>
			</div>
			<nav class="table-pagination">
				<ul class="pagination">
					<?php
					$params = $_GET;
					unset($params['page']);
					$query_string = http_build_query($params);
					$base_url = '?' . ($query_string ? $query_string . '&' : '');
					?>

					<li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
						<a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $page - 1; ?>"><i class="fas fa-chevron-left" style="font-size:.65rem;"></i> Prev</a>
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
							<a class="page-link" href="<?php echo $base_url; ?>page=1">1</a>
						</li>
						<?php if ($start > 2): ?>
							<li class="page-item disabled"><span class="page-link">...</span></li>
						<?php endif; ?>
					<?php endif; ?>

					<?php for ($i = $start; $i <= $end; $i++): ?>
						<li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
							<a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
						</li>
					<?php endfor; ?>

					<?php if ($end < $total_pages): ?>
						<?php if ($end < $total_pages - 1): ?>
							<li class="page-item disabled"><span class="page-link">...</span></li>
						<?php endif; ?>
						<li class="page-item"><a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a></li>
					<?php endif; ?>

					<li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
						<a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $page + 1; ?>">Next <i class="fas fa-chevron-right" style="font-size:.65rem;"></i></a>
					</li>
				</ul>
			</nav>
		<?php endif; ?>
	</div>
</div>

<!-- Walk-in Request Modal -->
<div class="modal fade" id="walkinRequestModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-md">
		<div class="modal-content border-0 shadow-lg rounded-4">
			<div class="modal-header border-0 pb-0 px-4 pt-4">
				<div class="d-flex align-items-center gap-3">
					<div class="rounded-3 d-flex align-items-center justify-content-center"
						style="width:44px;height:44px; background-color: #f0fdfa; color: #0d9488;">
						<i class="fas fa-file-signature fa-lg"></i>
					</div>
					<div>
						<h5 class="fw-bold mb-0">Walk-in Request</h5>
						<p class="text-muted small mb-0">Add a request for a walk-in resident</p>
					</div>
				</div>
				<button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body px-4 py-3">
				<form method="post" id="walkinForm" action="" enctype="multipart/form-data">
					<?php echo csrf_field(); ?>
					<input type="hidden" name="walkin_action" value="1">

					<div id="wi_form_step_1">
						<!-- Resident Search -->
						<div class="mb-3">
							<label class="form-label fw-semibold small text-uppercase text-secondary">Requestor Name</label>
							<input type="text" id="wiResidentSearch" name="wi_requestor_name" class="form-control"
								placeholder="Search resident or enter full name..." autocomplete="off">
							<div id="wiResidentDropdown" class="list-group mt-1 shadow-sm"
								style="display:none; max-height:180px; overflow-y:auto; position:absolute; z-index:9999; width:calc(100% - 3rem);">
							</div>
							<input type="hidden" name="wi_resident_id" id="wiResidentId">
							<div id="wiResidentSelected" class="mt-2 small text-success fw-semibold" style="display:none;">
							</div>
						</div>

						<!-- Walk-in Extra Fields (For Unregistered) -->
						<div class="mb-3" id="wiExtraFields">
							<div class="row">
								<div class="col-md-6">
									<label class="form-label fw-semibold small text-uppercase text-secondary">Civil Status</label>
									<select name="wi_civil_status" id="wiCivilStatus" class="form-select">
										<option value="">-- Select --</option>
										<option value="Single">Single</option>
										<option value="Married">Married</option>
										<option value="Widow/er">Widow/er</option>
									</select>
								</div>
								<div class="col-md-6">
									<label class="form-label fw-semibold small text-uppercase text-secondary">Purok</label>
									<select name="wi_purok" id="wiPurok" class="form-select">
										<option value="">-- Select --</option>
										<option value="1">1</option>
										<option value="2">2</option>
										<option value="3">3</option>
										<option value="4">4</option>
										<option value="5">5</option>
										<option value="6">6</option>
										<option value="7">7</option>
									</select>
								</div>
							</div>
						</div>

						<!-- Document Type -->
						<div class="mb-3">
							<label class="form-label fw-semibold small text-uppercase text-secondary">Document Type</label>
							<select name="wi_doc_type" id="wiDocType" class="form-select" onchange="wiUpdatePurpose()">
								<option value="">-- Select document --</option>
								<?php foreach ($wi_doc_types as $dt): ?>
									<option value="<?php echo htmlspecialchars($dt['name']); ?>" data-price="<?php echo htmlspecialchars($dt['price']); ?>"><?php echo htmlspecialchars($dt['name']); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="mb-3" id="wi_document_price_container" style="display: none;">
							<label class="form-label fw-semibold text-dark opacity-50 small text-uppercase">Price</label>
							<div class="p-3 bg-light rounded text-success fs-5 fw-bold border" id="wi_document_price_display">
								Free
							</div>
						</div>

						<!-- Purpose -->
						<div class="mb-3" id="wiPurposeWrap">
							<label class="form-label fw-semibold small text-uppercase text-secondary">Purpose</label>
							<select name="wi_purpose" id="wiPurposeSelect" class="form-select" style="display:none;">
								<option value="">-- Select purpose --</option>
							</select>
							<input type="text" name="wi_purpose_text" id="wiPurposeText" class="form-control"
								placeholder="State purpose...">
						</div>
						
						<!-- Payment Method -->
						<div class="mb-3">
							<label class="form-label fw-semibold small text-uppercase text-secondary">Payment Method</label>
							<select name="wi_payment_method" id="wiPaymentMethod" class="form-select" onchange="updateWalkinNav()">
								<option value="Cash">Cash</option>
								<option value="GCash">GCash / E-Wallet</option>
							</select>
						</div>

						<div class="d-flex gap-2 justify-content-end pt-2">
							<button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
							<button type="button" class="btn btn-primary rounded-pill px-4" id="btn_wi_next" style="display:none;">Next <i class="fas fa-arrow-right ms-2"></i></button>
							<button type="submit" class="btn btn-primary rounded-pill px-4" id="btn_wi_submit_step1"><i class="fas fa-plus me-1"></i> Add Request</button>
						</div>
					</div>

					<div id="wi_form_step_2" style="display:none;">
						<div class="fw-bold text-dark opacity-75 mb-1 fs-5">E-Wallet Payment</div>
						<p class="text-secondary small mb-3">Scan QR code and upload receipt to complete payment</p>
						
						<div class="row g-3 mb-3">
							<!-- Amount Due Box -->
							<div class="col-6">
								<div class="p-3 bg-light rounded-3 border text-center h-100 d-flex flex-column justify-content-center">
									<span class="text-secondary small fw-semibold text-uppercase d-block mb-1">Amount Due</span>
									<span id="amount_due_display_admin" class="fw-bold text-dark fs-6">₱ 0.00</span>
								</div>
							</div>
							<!-- Amount Paid Box -->
							<div class="col-6">
								<div class="p-3 bg-light rounded-3 border text-center h-100 d-flex flex-column justify-content-center">
									<span class="text-secondary small fw-semibold text-uppercase d-block mb-1">Amount Paid</span>
									<span id="amount_paid_display_admin" class="fw-bold text-teal-600 fs-5">PHP 0.00</span>
								</div>
							</div>
						</div>
						
						<!-- Scan QR Code Button -->
						<div class="text-center mb-3">
							<button type="button" class="btn btn-link text-teal-600 text-decoration-none fw-semibold small d-inline-flex align-items-center gap-1 shadow-none p-0" id="btn_toggle_qr_admin">
								<i class="fas fa-qrcode"></i> Scan QR Code
							</button>
						</div>

						<!-- Upload Receipt Box -->
						<div class="mb-3">
							<label class="form-label fw-semibold text-dark opacity-50 small text-uppercase">Upload Receipt</label>
							<div class="border border-dashed border-2 rounded-3 p-3 text-center position-relative bg-light" style="border-color: #0d9488 !important; cursor: pointer; border-style: dashed !important;" id="receipt_dropzone_admin" onclick="document.getElementById('wi_payment_receipt').click();">
								<div class="d-flex flex-column align-items-center justify-content-center gap-2">
									<div class="rounded-circle bg-teal-50 text-teal-600 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
										<i class="fas fa-upload"></i>
									</div>
									<div class="fw-semibold text-dark" id="upload_status_admin" style="font-size: 0.9rem;">Upload Receipt</div>
									<div class="text-secondary" style="font-size: 0.75rem;">PNG, JPG, WEBP - Max size: 5MB</div>
								</div>
								<input type="file" name="payment_receipt" id="wi_payment_receipt" class="d-none" accept="image/png, image/jpeg, image/jpg, image/webp" onchange="handleAdminReceiptSelected(this)">
							</div>
						</div>

						<!-- OCR Scanning Status -->
						<div id="ocr_scan_status_admin" class="d-none mb-3">
							<div class="d-flex align-items-center gap-2 p-2 rounded-3 bg-light border">
								<div class="spinner-border spinner-border-sm text-teal-600" id="ocr_spinner_admin"></div>
								<span class="small text-secondary" id="ocr_status_text_admin">Scanning receipt for reference number...</span>
							</div>
						</div>

						<!-- Reference Number Field -->
						<div class="mb-3">
							<label class="form-label fw-semibold text-dark opacity-50 small text-uppercase d-flex align-items-center gap-2">
								Reference No.
							</label>
							<div class="input-group input-group-sm">
								<span class="input-group-text bg-light border-end-0">
									<i class="fas fa-hashtag text-teal-600"></i>
								</span>
								<input type="text" name="wi_payment_ref" id="payment_reference_no_admin"
									class="form-control border-start-0 ps-1 rounded-end-3 bg-white"
									placeholder="Enter Reference No." readonly>
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
							<div class="input-group input-group-sm">
								<span class="input-group-text bg-light border-end-0">₱</span>
								<input type="text" name="payment_amount_paid" id="payment_amount_paid_admin"
									class="form-control border-start-0 ps-1 rounded-end-3 bg-white"
									placeholder="0.00" readonly>
							</div>
							<div class="text-muted mt-1" style="font-size:.75rem;">
								<i class="fas fa-shield-alt me-1 text-teal-600"></i>
								Strictly extracted from your uploaded receipt for security.
							</div>
						</div>

						<div class="d-flex gap-2 justify-content-end pt-2">
							<button type="button" class="btn btn-light rounded-pill px-4" id="btn_wi_back"><i class="fas fa-arrow-left me-2"></i> Back</button>
							<button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-plus me-1"></i> Add Request</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Admin View Detail Modal -->
<div class="modal fade" id="adminViewDetailModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content border-0 shadow-lg rounded-4">
			<div class="modal-body p-4">
				<div class="d-flex align-items-center gap-3 mb-4">
					<div class="rounded-3 d-flex align-items-center justify-content-center"
						style="width: 48px; height: 48px; background-color: #f0fdfa; color: #0d9488;">
						<i class="fas fa-file-signature fa-lg"></i>
					</div>
					<h5 class="fw-bold mb-0 text-dark">Request Details</h5>
					<button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<table class="table table-borderless mb-0">
					<tr>
						<td class="text-secondary fw-semibold small" style="width: 130px;">Document</td>
						<td class="fw-bold" id="admin_detail_doc"></td>
					</tr>
					<tr>
						<td class="text-secondary fw-semibold small">Requester</td>
						<td id="admin_detail_requester"></td>
					</tr>
					<tr id="admin_detail_family_row" style="display: none;">
						<td class="text-secondary fw-semibold small">Family of</td>
						<td id="admin_detail_family"></td>
					</tr>
					<tr>
						<td class="text-secondary fw-semibold small">Purpose</td>
						<td id="admin_detail_purpose"></td>
					</tr>
					<tr>
						<td class="text-secondary fw-semibold small">Status</td>
						<td id="admin_detail_status"></td>
					</tr>
					<tr>
						<td class="text-secondary fw-semibold small" style="width: 130px;">Date Filed</td>
						<td id="admin_detail_date"></td>
					</tr>
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
					<div class="rounded-circle bg-danger bg-opacity-10 d-inline-flex align-items-center justify-content-center mx-auto"
						style="width: 80px; height: 80px;">
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
						<textarea name="notes" id="rejectReasonInput" class="form-control bg-light border-0" rows="3"
							placeholder="State reason for rejection..." required></textarea>
						<div id="rejectReasonError" class="text-danger small mt-1" style="display: none;">Please provide
							a reason.</div>
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
					<div class="rounded-circle bg-danger bg-opacity-10 d-inline-flex align-items-center justify-content-center mx-auto"
						style="width: 80px; height: 80px;">
						<i class="fas fa-times-circle fa-2x text-danger"></i>
					</div>
				</div>
				<h5 class="fw-bold text-dark mb-2">Reject selected requests?</h5>
				<p class="text-secondary mb-3">This reason will be saved for every selected request.</p>
				<div class="mb-4 text-start">
					<textarea id="bulkRejectReasonInput" class="form-control bg-light border-0" rows="3"
						placeholder="State reason for rejection..." required></textarea>
					<div id="bulkRejectReasonError" class="text-danger small mt-1" style="display: none;">Please provide
						a reason.</div>
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

<!-- Tesseract.js for OCR -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>

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
		document.querySelectorAll('.row-checkbox').forEach(function (cb) {
			cb.checked = master.checked;
		});
	}

	function adminBulkGetSelectedValues() {
		return Array.prototype.map.call(document.querySelectorAll('.row-checkbox:checked'), function (cb) { return cb.value; });
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
				vals.forEach(function (v) {
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

	(function () {
		var bulkConfirm = document.getElementById('adminBulkRejectConfirmBtn');
		if (!bulkConfirm) return;
		bulkConfirm.addEventListener('click', function () {
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
	document.addEventListener('click', function (e) {
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
			reasonLink.onclick = function () { showRejectionReason(btn.dataset.notes, btn.dataset.statusLabel); };
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
	document.addEventListener('click', function (e) {
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
	document.getElementById('adminRejectForm').addEventListener('submit', function (e) {
		var reason = document.getElementById('rejectReasonInput').value.trim();
		if (!reason) {
			e.preventDefault();
			document.getElementById('rejectReasonError').style.display = 'block';
			document.getElementById('rejectReasonInput').focus();
		}
	});

	// Walk-in Receipt Selection
	window.handleAdminReceiptSelected = async function(input) {
		const statusDiv = document.getElementById('upload_status_admin');
		const ocrStatus = document.getElementById('ocr_scan_status_admin');
		const refInput = document.getElementById('payment_reference_no_admin');
		const spinner = document.getElementById('ocr_spinner_admin');
		const statusText = document.getElementById('ocr_status_text_admin');

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
				const lines = text.split(/\n/);
				let foundRef = '';
				
				for (const line of lines) {
					const refLineMatch = line.match(/(?:ref(?:erence)?\.?\s*(?:no\.?|num(?:ber)?|id)?|trans(?:action)?\.?\s*(?:no\.?|id)?)\s*[:.-]?\s*([\d\sA-Za-z]+)/i);
					if (refLineMatch) {
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
				
				if (!foundRef) {
					for (const line of lines) {
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
                                // Skip if it looks like a PH phone number (09... or 639...)
                                if ((clean.startsWith('09') && clean.length === 11) || 
                                    (clean.startsWith('639') && clean.length === 12)) {
                                    continue; // Skip phone number, keep looking for reference
                                }
                                foundRef = clean;
                                break;
                            }
                        }
					}
				}
				
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
					const fallbackMatch = text.match(/\b([0-9,]+\.\d{2})\b/);
					if (fallbackMatch) {
						foundAmount = fallbackMatch[1].replace(/,/g, '');
					}
				}

				spinner.classList.add('d-none');
				const amountInput = document.getElementById('payment_amount_paid_admin');
				
				if (foundRef || foundAmount) {
					if (foundRef) {
						refInput.value = foundRef;
					}

					if (foundAmount && amountInput) {
						amountInput.value = foundAmount;
						document.getElementById('amount_paid_display_admin').textContent = 'PHP ' + parseFloat(foundAmount).toFixed(2);

						// ── Insufficient Payment Check ──────────────────
						const docTypeSelect = document.getElementById('wiDocType');
						const selectedOption = docTypeSelect.options[docTypeSelect.selectedIndex];
						const amountDue = selectedOption && selectedOption.value !== "" ? parseFloat(selectedOption.getAttribute('data-price') || 0) : 0;
						const numericFoundAmount = parseFloat(foundAmount.replace(/,/g, ''));

						if (amountDue > 0 && numericFoundAmount < amountDue) {
							statusText.innerHTML = `<span class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> Insufficient payment!</span> Amount paid (₱ ${numericFoundAmount.toFixed(2)}) is less than the required amount (₱ ${amountDue.toFixed(2)}).`;
							const submitBtn = document.getElementById('btn_wi_add_request');
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
			const amountInput = document.getElementById('payment_amount_paid_admin');
			if (amountInput) amountInput.value = '';
		}
	};

	// Walk-in QR Code Scanner Modal
	document.getElementById('btn_toggle_qr_admin')?.addEventListener('click', function(e) {
		e.preventDefault();
		Swal.fire({
			title: 'Scan QR Code',
			html: `
				<div class="text-center p-2">
					<div class="d-inline-block p-3 bg-white rounded-3 border shadow-sm mb-3">
						<img src="/public/img/gcash_qr.png" alt="InstaPay QR Code" class="img-fluid" style="max-width: 280px; width: 100%; height: auto;">
					</div>
					<div class="fw-bold text-teal-600 fs-6">Barangay Panungyanan Payment Portal</div>
				</div>
			`,
			showCloseButton: true,
			confirmButtonText: 'Done Scanning',
			confirmButtonColor: '#0d9488',
			customClass: {
				confirmButton: 'rounded-pill px-4'
			}
		});
	});

	function updateWalkinNav() {
		const method = document.getElementById('wiPaymentMethod').value;
		if (method === 'GCash') {
			document.getElementById('btn_wi_next').style.display = 'inline-block';
			document.getElementById('btn_wi_submit_step1').style.display = 'none';
		} else {
			document.getElementById('btn_wi_next').style.display = 'none';
			document.getElementById('btn_wi_submit_step1').style.display = 'inline-block';
		}
	}

	document.getElementById('btn_wi_next')?.addEventListener('click', function() {
		// Basic validation before next
		const name = document.getElementById('wiResidentSearch').value;
		const docTypeSelect = document.getElementById('wiDocType');
		const docType = docTypeSelect.value;
		if (!name || !docType) {
			Swal.fire('Required Fields', 'Please select a requestor and document type.', 'warning');
			return;
		}

		// Update Price Display
		const selectedOption = docTypeSelect.options[docTypeSelect.selectedIndex];
		const price = selectedOption ? parseFloat(selectedOption.getAttribute('data-price') || 0) : 0;
		document.getElementById('amount_due_display_admin').textContent = '₱ ' + price.toFixed(2);

		document.getElementById('wi_form_step_1').style.display = 'none';
		document.getElementById('wi_form_step_2').style.display = 'block';
	});

	document.getElementById('btn_wi_back')?.addEventListener('click', function() {
		document.getElementById('wi_form_step_2').style.display = 'none';
		document.getElementById('wi_form_step_1').style.display = 'block';
	});

	// ── Walk-in Request Modal JS ──────────────────────────────────────────────
	(function () {
		var residents = <?php echo json_encode(array_values($wi_residents)); ?>;

		var purposeMap = {
			'Barangay Clearance': ['Local Employment', 'Postal ID Application', 'Medical/Financial Assistance', 'Bank Requirements', 'Scholarship Program', 'Water/Electric Connection', 'Educational Assistance', 'Other\'s'],
			'Barangay Indigency': ['Financial/Medical Assistance', 'Burial Assistance', 'Senior Citizen Social Pension', 'Vaccination Requirements', 'Educational Assistance', 'Other\'s'],
			'Barangay Indigency Certificate': ['Financial/Medical Assistance', 'Burial Assistance', 'Senior Citizen Social Pension', 'Vaccination Requirements', 'Educational Assistance', 'Other\'s'],
			'Certificate of Indigency': ['Financial/Medical Assistance', 'Burial Assistance', 'Senior Citizen Social Pension', 'Vaccination Requirements', 'Educational Assistance', 'Other\'s']
		};

		// Resident search
		var searchInput = document.getElementById('wiResidentSearch');
		var dropdown = document.getElementById('wiResidentDropdown');
		var hiddenId = document.getElementById('wiResidentId');
		var selected = document.getElementById('wiResidentSelected');

		if (searchInput) {
			searchInput.addEventListener('input', function () {
				var q = this.value.trim().toLowerCase();
				hiddenId.value = '';
				selected.style.display = 'none';
				if (!q) { dropdown.style.display = 'none'; return; }

				var matches = residents.filter(function (r) {
					return r.full_name.toLowerCase().includes(q);
				}).slice(0, 8);

				if (!matches.length) { dropdown.style.display = 'none'; return; }

				dropdown.innerHTML = '';
				matches.forEach(function (r) {
					var a = document.createElement('a');
					a.href = 'javascript:void(0)';
					a.className = 'list-group-item list-group-item-action py-2 px-3 small';
					a.innerHTML = '<strong>' + r.full_name + '</strong>' + (r.address ? '<span class="text-muted ms-2">' + r.address + '</span>' : '');
					a.addEventListener('click', function () {
						hiddenId.value = r.id;
						searchInput.value = r.full_name;
						selected.textContent = '\u2713 ' + r.full_name + ' selected';
						selected.style.display = '';
						dropdown.style.display = 'none';
					});
					dropdown.appendChild(a);
				});
				dropdown.style.display = '';
			});

			document.addEventListener('click', function (e) {
				if (!e.target.closest('#walkinRequestModal')) dropdown.style.display = 'none';
			});
		}

		// Purpose switcher
		window.wiUpdatePurpose = function () {
			var docTypeSelect = document.getElementById('wiDocType');
			var docType = docTypeSelect.value;
			var sel = document.getElementById('wiPurposeSelect');
			var txt = document.getElementById('wiPurposeText');
			var priceContainer = document.getElementById('wi_document_price_container');
			var priceDisplay = document.getElementById('wi_document_price_display');

			if (purposeMap[docType]) {
				sel.innerHTML = '<option value="">-- Select purpose --</option>';
				purposeMap[docType].forEach(function (p) {
					var o = document.createElement('option');
					o.value = p; o.textContent = p;
					sel.appendChild(o);
				});
				sel.style.display = '';
				txt.style.display = 'none';
				sel.name = 'wi_purpose';
				txt.name = '';
			} else {
				sel.style.display = 'none';
				txt.style.display = '';
				sel.name = '';
				txt.name = 'wi_purpose';
			}

			// Update price display
			const selectedOption = docTypeSelect.options[docTypeSelect.selectedIndex];
			if (selectedOption && selectedOption.value !== "") {
				const price = parseFloat(selectedOption.getAttribute('data-price') || 0);
				priceContainer.style.display = 'block';
				if (price > 0) {
					priceDisplay.textContent = '₱ ' + price.toFixed(2);
					priceDisplay.className = 'p-3 bg-light rounded text-dark fs-5 fw-bold border';
				} else {
					priceDisplay.textContent = 'Free';
					priceDisplay.className = 'p-3 bg-light rounded text-success fs-5 fw-bold border';
				}
			} else {
				priceContainer.style.display = 'none';
			}
		};
	})();
</script>

<?php if (isset($_SESSION['bulk_result'])): ?>
	<script>
		(function () {
			var okCount = <?php echo (int) $_SESSION['bulk_result']['ok']; ?>;
			var skipCount = <?php echo (int) $_SESSION['bulk_result']['skip']; ?>;
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
		(function () {
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

<?php if (isset($_SESSION['action_error'])): ?>
	<script>
		(function () {
			var title = <?php echo json_encode($_SESSION['action_error']['title']); ?>;
			var text = <?php echo json_encode($_SESSION['action_error']['text']); ?>;

			Swal.fire({
				title: title,
				text: text,
				icon: 'error',
				confirmButtonColor: '#e11d48'
			});
		})();
	</script>
	<?php unset($_SESSION['action_error']); ?>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>