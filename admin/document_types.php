<?php 
require_once __DIR__ . '/../config.php';
if (!is_admin()) redirect('../index.php');
$page_title = 'Document Types';
$breadcrumb = [
	['title' => 'Document Types']
];
require_once __DIR__ . '/header.php'; 
?>

<?php
$pdo = get_db_connection();
$message = '';
$message_type = 'success';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_validate()) {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $requires_validity = isset($_POST['requires_validity']) ? 1 : 0;
            $requires_special_handling = 0; // Hidden from UI, always 0 for new docs
            $display_order = (int)($_POST['display_order'] ?? 0);
            $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
            
            // Handle PDF Upload
            $pdf_template_path = null;
            if (isset($_FILES['pdf_template']) && $_FILES['pdf_template']['error'] === UPLOAD_ERR_OK) {
                // Ensure directory exists
                $uploadDir = __DIR__ . '/../uploads/document_templates/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $extension = pathinfo($_FILES['pdf_template']['name'], PATHINFO_EXTENSION);
                if (strtolower($extension) === 'pdf') {
                    $filename = 'template_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
                    $uploadPath = $uploadDir . $filename;
                    if (move_uploaded_file($_FILES['pdf_template']['tmp_name'], $uploadPath)) {
                        $pdf_template_path = 'uploads/document_templates/' . $filename;
                    }
                }
            }
            
            if ($name !== '') {
                try {
                    $stmt = $pdo->prepare('
                        INSERT INTO document_types (name, pdf_template_path, requires_validity, requires_special_handling, display_order, price, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, 1)
                    ');
                    $stmt->execute([$name, $pdf_template_path, $requires_validity, $requires_special_handling, $display_order, $price]);
                    $message = 'Document type created successfully!';
                } catch (PDOException $e) {
                    $message = 'Error: ' . ($e->getCode() == 23000 ? 'Document type already exists' : $e->getMessage());
                    $message_type = 'danger';
                }
            } else {
                $message = 'Document type name is required.';
                $message_type = 'danger';
            }
        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $requires_validity = isset($_POST['requires_validity']) ? 1 : 0;
            $display_order = (int)($_POST['display_order'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
            
            // Get existing PDF path first
            $stmt = $pdo->prepare('SELECT pdf_template_path FROM document_types WHERE id = ?');
            $stmt->execute([$id]);
            $existing_record = $stmt->fetch();
            $pdf_template_path = $existing_record['pdf_template_path'] ?? null;
            
            // Handle PDF Upload if a new file is uploaded
            if (isset($_FILES['pdf_template']) && $_FILES['pdf_template']['error'] === UPLOAD_ERR_OK) {
                // Ensure directory exists
                $uploadDir = __DIR__ . '/../uploads/document_templates/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $extension = pathinfo($_FILES['pdf_template']['name'], PATHINFO_EXTENSION);
                if (strtolower($extension) === 'pdf') {
                    // Delete old file if exists
                    if ($pdf_template_path && file_exists(__DIR__ . '/../' . $pdf_template_path)) {
                        unlink(__DIR__ . '/../' . $pdf_template_path);
                    }
                    
                    $filename = 'template_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
                    $uploadPath = $uploadDir . $filename;
                    if (move_uploaded_file($_FILES['pdf_template']['tmp_name'], $uploadPath)) {
                        $pdf_template_path = 'uploads/document_templates/' . $filename;
                    }
                }
            }
            
            // Handle PDF removal checkbox
            if (isset($_POST['remove_pdf_template']) && $_POST['remove_pdf_template'] == '1') {
                if ($pdf_template_path && file_exists(__DIR__ . '/../' . $pdf_template_path)) {
                    unlink(__DIR__ . '/../' . $pdf_template_path);
                }
                $pdf_template_path = null;
            }
            
            if ($id > 0 && $name !== '') {
                try {
                    $stmt = $pdo->prepare('
                        UPDATE document_types 
                        SET name = ?, pdf_template_path = ?, requires_validity = ?, 
                            display_order = ?, price = ?, is_active = ?
                        WHERE id = ?
                    ');
                    $stmt->execute([$name, $pdf_template_path, $requires_validity, $display_order, $price, $is_active, $id]);
                    $message = 'Document type updated successfully!';
                } catch (PDOException $e) {
                    $message = 'Error: ' . ($e->getCode() == 23000 ? 'Document type name already exists' : $e->getMessage());
                    $message_type = 'danger';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare('DELETE FROM document_types WHERE id = ?');
                $stmt->execute([$id]);
                $message = 'Document type deleted successfully!';
            }
        }
    } else {
        $message = 'Invalid session. Please reload and try again.';
        $message_type = 'danger';
    }
}

// Get all document types
$document_types = $pdo->query('
    SELECT * FROM document_types 
    ORDER BY display_order ASC, name ASC
')->fetchAll();

// Get document type for editing (if ID is provided)
$edit_type = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM document_types WHERE id = ?');
    $stmt->execute([$edit_id]);
    $edit_type = $stmt->fetch();
}
?>

<div class="admin-table">
	<div class="p-3 border-bottom">
		<div class="d-flex justify-content-between align-items-center">
			<div>
				<h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Document Types Management</h5>
				<p class="text-muted mb-0">Manage available document types for residents to request</p>
			</div>
			<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
				<i class="fas fa-plus me-2"></i>Add New Type
			</button>
		</div>
	</div>

	<?php if ($message): ?>
		<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show m-3" role="alert">
			<?php echo htmlspecialchars($message); ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	<?php endif; ?>

	<div class="p-3">
		<div class="table-responsive">
			<table class="table table-striped align-middle">
				<thead>
					<tr>
						<th>Name</th>
						<th>Price</th>
						<th>Requires Validity</th>
						<th>Status</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($document_types)): ?>
						<tr>
							<td colspan="7" class="text-center py-5">
								<p class="text-muted mb-0">No document types found. Create one to get started.</p>
							</td>
						</tr>
					<?php else: ?>
						<?php foreach ($document_types as $type): ?>
							<tr>
								<td>
									<strong><?php echo htmlspecialchars($type['name']); ?></strong>
								</td>
								<td>
									<strong class="text-success">₱<?php echo number_format($type['price'] ?? 0, 2); ?></strong>
								</td>
								<td>
									<?php if ($type['requires_validity']): ?>
										<span class="badge bg-info">Yes</span>
									<?php else: ?>
										<span class="badge bg-secondary">No</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ($type['is_active']): ?>
										<span class="badge bg-success">Active</span>
									<?php else: ?>
										<span class="badge bg-danger">Inactive</span>
									<?php endif; ?>
								</td>
								<td>
									<div class="d-flex gap-2">
										<a href="?edit=<?php echo $type['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
											<i class="fas fa-edit"></i>
										</a>
										<form method="post" class="d-inline admin-confirm-form" data-action-name="Delete Document Type">
											<?php echo csrf_field(); ?>
											<input type="hidden" name="action" value="delete">
											<input type="hidden" name="id" value="<?php echo $type['id']; ?>">
											<button type="button" class="btn btn-sm btn-outline-danger btn-confirm-submit" title="Delete">
												<i class="fas fa-trash"></i>
											</button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- Create/Edit Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">
					<?php echo $edit_type ? 'Edit Document Type' : 'Add New Document Type'; ?>
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<form method="post" enctype="multipart/form-data">
				<?php echo csrf_field(); ?>
				<input type="hidden" name="action" value="<?php echo $edit_type ? 'update' : 'create'; ?>">
				<?php if ($edit_type): ?>
					<input type="hidden" name="id" value="<?php echo $edit_type['id']; ?>">
				<?php endif; ?>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label">Document Type Name <span class="text-danger">*</span></label>
						<input type="text" name="name" class="form-control" 
							value="<?php echo htmlspecialchars($edit_type['name'] ?? ''); ?>" required>
						<small class="text-muted">This is the name that will appear in the dropdown</small>
					</div>
					<div class="mb-3">
						<label class="form-label">Price (₱) <span class="text-danger">*</span></label>
						<div class="input-group">
							<span class="input-group-text">₱</span>
							<input type="number" name="price" class="form-control" 
								value="<?php echo htmlspecialchars($edit_type['price'] ?? '0.00'); ?>" 
								step="0.01" min="0" required>
						</div>
						<small class="text-muted">Set the price for this document type</small>
					</div>
					<div class="mb-3">
						<div class="form-check">
							<input type="checkbox" name="requires_validity" class="form-check-input" id="requires_validity"
								<?php echo ($edit_type['requires_validity'] ?? 0) ? 'checked' : ''; ?>>
							<label class="form-check-label" for="requires_validity">
								Requires Validity Period
							</label>
							<small class="d-block text-muted">If checked, users will be asked to select a validity period (e.g., 30, 60, 90 days)</small>
						</div>
					</div>
					<div class="mb-3 border-top pt-3">
						<label class="form-label fw-bold">Blank PDF Upload (Optional)</label>
						<input type="file" name="pdf_template" class="form-control" accept=".pdf">
						<small class="d-block text-muted">Upload a blank PDF file if you want to provide a static form that administrators can download to fill out manually.</small>
						<?php if (!empty($edit_type['pdf_template_path'])): ?>
							<div class="mt-2 text-info small">
								<i class="fas fa-file-pdf me-1"></i> Current file: <?php echo basename($edit_type['pdf_template_path']); ?>
								<br>
								<div class="form-check mt-1">
									<input class="form-check-input" type="checkbox" name="remove_pdf_template" value="1" id="removePdf">
									<label class="form-check-label text-danger" for="removePdf">Delete this PDF</label>
								</div>
							</div>
						<?php endif; ?>
					</div>
					<?php if ($edit_type): ?>
						<div class="mb-3">
							<div class="form-check">
								<input type="checkbox" name="is_active" class="form-check-input" id="is_active"
									<?php echo ($edit_type['is_active'] ?? 1) ? 'checked' : ''; ?>>
								<label class="form-check-label" for="is_active">
									Active
								</label>
								<small class="d-block text-muted">Inactive types won't appear in the request form</small>
							</div>
						</div>
					<?php endif; ?>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">
						<?php echo $edit_type ? 'Update' : 'Create'; ?>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<?php if ($edit_type): ?>
<script>
	// Auto-open modal if editing
	document.addEventListener('DOMContentLoaded', function() {
		var modal = new bootstrap.Modal(document.getElementById('createModal'));
		modal.show();
	});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

