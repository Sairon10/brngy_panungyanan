<?php 
$page_title = 'Announcements';
$breadcrumb = [
	['title' => 'Announcements']
];
require_once __DIR__ . '/../config.php';
if (!is_admin()) redirect('/index.php');

$pdo = get_db_connection();
$errors = [];
$success = '';
$upload_dir = __DIR__ . '/../uploads/announcements/';

// Helper: handle media upload
function handleMediaUpload($file, $upload_dir) {
	if ($file['error'] !== UPLOAD_ERR_OK) return null;
	
	$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
	
	if (!in_array($file['type'], $allowed)) return 'invalid_type';
	if ($file['size'] > 10 * 1024 * 1024) return 'too_large'; // 10MB max
	
	$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
	$filename = uniqid('ann_') . '.' . strtolower($ext);
	$dest = $upload_dir . $filename;
	
	if (move_uploaded_file($file['tmp_name'], $dest)) {
		return 'uploads/announcements/' . $filename;
	}
	return null;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate()) {
	$action = $_POST['action'] ?? '';

	if ($action === 'create') {
		$title = trim($_POST['title'] ?? '');
		$content = trim($_POST['content'] ?? '');
		if ($title === '' || $content === '') {
			$errors[] = 'Title and content are required.';
		} else {
			$media_path = null;
			if (isset($_FILES['media']) && $_FILES['media']['error'] !== UPLOAD_ERR_NO_FILE) {
				$result = handleMediaUpload($_FILES['media'], $upload_dir);
				if ($result === 'invalid_type') {
					$errors[] = 'Invalid file type. Allowed: JPG, PNG, GIF, WebP.';
				} elseif ($result === 'too_large') {
					$errors[] = 'File is too large. Maximum size is 10MB.';
				} else {
					$media_path = $result;
				}
			}
			if (!$errors) {
				$stmt = $pdo->prepare('INSERT INTO announcements (title, content, media_path, created_by) VALUES (?, ?, ?, ?)');
				$stmt->execute([$title, $content, $media_path, $_SESSION['user_id']]);
				$success = 'Announcement created successfully.';
			}
		}
	}

	if ($action === 'edit') {
		$id = (int)($_POST['id'] ?? 0);
		$title = trim($_POST['title'] ?? '');
		$content = trim($_POST['content'] ?? '');
		if ($title === '' || $content === '') {
			$errors[] = 'Title and content are required.';
		} else {
			// Check if new media is uploaded
			$media_path = null;
			$update_media = false;
			if (isset($_FILES['media']) && $_FILES['media']['error'] !== UPLOAD_ERR_NO_FILE) {
				$result = handleMediaUpload($_FILES['media'], $upload_dir);
				if ($result === 'invalid_type') {
					$errors[] = 'Invalid file type. Allowed: JPG, PNG, GIF, WebP, MP4, WebM, OGG.';
				} elseif ($result === 'too_large') {
					$errors[] = 'File is too large. Maximum size is 50MB.';
				} else {
					$media_path = $result;
					$update_media = true;
					// Delete old media
					$old = $pdo->prepare('SELECT media_path FROM announcements WHERE id = ?');
					$old->execute([$id]);
					$old_row = $old->fetch();
					if ($old_row && $old_row['media_path'] && file_exists(__DIR__ . '/../' . $old_row['media_path'])) {
						unlink(__DIR__ . '/../' . $old_row['media_path']);
					}
				}
			}

			// Check if admin wants to remove media
			if (isset($_POST['remove_media']) && $_POST['remove_media'] === '1') {
				$old = $pdo->prepare('SELECT media_path FROM announcements WHERE id = ?');
				$old->execute([$id]);
				$old_row = $old->fetch();
				if ($old_row && $old_row['media_path'] && file_exists(__DIR__ . '/../' . $old_row['media_path'])) {
					unlink(__DIR__ . '/../' . $old_row['media_path']);
				}
				$media_path = null;
				$update_media = true;
			}

			if (!$errors) {
				if ($update_media) {
					$stmt = $pdo->prepare('UPDATE announcements SET title = ?, content = ?, media_path = ? WHERE id = ?');
					$stmt->execute([$title, $content, $media_path, $id]);
				} else {
					$stmt = $pdo->prepare('UPDATE announcements SET title = ?, content = ? WHERE id = ?');
					$stmt->execute([$title, $content, $id]);
				}
				$success = 'Announcement updated successfully.';
			}
		}
	}

	if ($action === 'toggle') {
		$id = (int)($_POST['id'] ?? 0);
		$stmt = $pdo->prepare('UPDATE announcements SET is_active = NOT is_active WHERE id = ?');
		$stmt->execute([$id]);
		$success = 'Announcement status updated.';
	}

	if ($action === 'delete') {
		$id = (int)($_POST['id'] ?? 0);
		// Delete media file
		$old = $pdo->prepare('SELECT media_path FROM announcements WHERE id = ?');
		$old->execute([$id]);
		$old_row = $old->fetch();
		if ($old_row && $old_row['media_path'] && file_exists(__DIR__ . '/../' . $old_row['media_path'])) {
			unlink(__DIR__ . '/../' . $old_row['media_path']);
		}
		$stmt = $pdo->prepare('DELETE FROM announcements WHERE id = ?');
		$stmt->execute([$id]);
		$success = 'Announcement deleted.';
	}
}

// Fetch all announcements
$announcements = $pdo->query('SELECT a.*, u.full_name AS author FROM announcements a LEFT JOIN users u ON a.created_by = u.id ORDER BY a.created_at DESC')->fetchAll();

require_once __DIR__ . '/header.php'; 
?>

<?php if ($errors): ?>
	<div class="alert alert-danger alert-dismissible fade show">
		<ul class="mb-0">
			<?php foreach ($errors as $e): ?>
				<li><?php echo htmlspecialchars($e); ?></li>
			<?php endforeach; ?>
		</ul>
		<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
	</div>
<?php endif; ?>

<?php if ($success): ?>
	<div class="alert alert-success alert-dismissible fade show">
		<?php echo htmlspecialchars($success); ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
	</div>
<?php endif; ?>

<!-- Action Bar -->
<div class="d-flex justify-content-between align-items-center mb-4">
	<p class="text-muted mb-0">Manage barangay announcements visible to all residents.</p>
	<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
		<i class="fas fa-plus me-2"></i>New Announcement
	</button>
</div>

<!-- Announcements Table -->
<div class="admin-table">
	<table class="table table-hover align-middle">
		<thead>
			<tr>
				<th>Title</th>
				<th>Content</th>
				<th>Media</th>
				<th>Author</th>
				<th>Status</th>
				<th>Date</th>
				<th class="text-end">Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php if (empty($announcements)): ?>
				<tr>
					<td colspan="7" class="text-center text-muted py-5">
						<i class="fas fa-bullhorn fa-3x mb-3 d-block opacity-25"></i>
						No announcements yet. Click "New Announcement" to create one.
					</td>
				</tr>
			<?php else: ?>
				<?php foreach ($announcements as $a): ?>
					<tr>
						<td class="fw-semibold"><?php echo htmlspecialchars($a['title']); ?></td>
						<td>
							<span class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($a['content'], 0, 60, '...')); ?></span>
						</td>
						<td>
							<?php if ($a['media_path']): ?>
								<img src="../<?php echo htmlspecialchars($a['media_path']); ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
							<?php else: ?>
								<span class="text-muted small">—</span>
							<?php endif; ?>
						</td>
						<td><?php echo htmlspecialchars($a['author'] ?? 'Unknown'); ?></td>
						<td>
							<?php if ($a['is_active']): ?>
								<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Active</span>
							<?php else: ?>
								<span class="badge bg-secondary"><i class="fas fa-eye-slash me-1"></i>Inactive</span>
							<?php endif; ?>
						</td>
						<td class="text-muted small"><?php echo date('M j, Y g:i A', strtotime($a['created_at'])); ?></td>
						<td class="text-end">
							<div class="btn-group btn-group-sm">
								<!-- Toggle Active -->
								<form method="post" class="d-inline">
									<?php echo csrf_field(); ?>
									<input type="hidden" name="action" value="toggle">
									<input type="hidden" name="id" value="<?php echo $a['id']; ?>">
									<button type="submit" class="btn btn-outline-<?php echo $a['is_active'] ? 'warning' : 'success'; ?>" title="<?php echo $a['is_active'] ? 'Deactivate' : 'Activate'; ?>">
										<i class="fas fa-<?php echo $a['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
									</button>
								</form>
								<!-- Edit -->
								<button class="btn btn-outline-primary" title="Edit"
									data-bs-toggle="modal" data-bs-target="#editModal"
									data-id="<?php echo $a['id']; ?>"
									data-title="<?php echo htmlspecialchars($a['title'], ENT_QUOTES); ?>"
									data-content="<?php echo htmlspecialchars($a['content'], ENT_QUOTES); ?>"
									data-media="<?php echo htmlspecialchars($a['media_path'] ?? '', ENT_QUOTES); ?>">
									<i class="fas fa-edit"></i>
								</button>
								<!-- Delete -->
								<form method="post" class="d-inline admin-confirm-form" data-action-name="Delete Announcement">
									<?php echo csrf_field(); ?>
									<input type="hidden" name="action" value="delete">
									<input type="hidden" name="id" value="<?php echo $a['id']; ?>">
									<button type="button" class="btn btn-outline-danger btn-confirm-submit" title="Delete">
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

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<form method="post" enctype="multipart/form-data">
				<?php echo csrf_field(); ?>
				<input type="hidden" name="action" value="create">
				<div class="modal-header">
					<h5 class="modal-title"><i class="fas fa-bullhorn me-2 text-primary"></i>New Announcement</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
						<input type="text" name="title" class="form-control form-control-lg" placeholder="e.g. Barangay Clean-Up Drive" required>
					</div>
					<div class="mb-3">
						<label class="form-label fw-semibold">Content <span class="text-danger">*</span></label>
						<textarea name="content" class="form-control" rows="5" placeholder="Write your announcement here..." required></textarea>
					</div>
					<div class="mb-3">
						<label class="form-label fw-semibold"><i class="fas fa-image me-1"></i> Image or Video <span class="text-muted fw-normal">(optional)</span></label>
						<input type="file" name="media" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" id="createMediaInput">
						<div class="form-text">Supported: JPG, PNG, GIF, WebP (max 10MB)</div>
						<div id="createMediaPreview" class="mt-2"></div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Publish</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<form method="post" enctype="multipart/form-data">
				<?php echo csrf_field(); ?>
				<input type="hidden" name="action" value="edit">
				<input type="hidden" name="id" id="editId">
				<input type="hidden" name="remove_media" id="editRemoveMedia" value="0">
				<div class="modal-header">
					<h5 class="modal-title"><i class="fas fa-edit me-2 text-primary"></i>Edit Announcement</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
						<input type="text" name="title" id="editTitle" class="form-control form-control-lg" required>
					</div>
					<div class="mb-3">
						<label class="form-label fw-semibold">Content <span class="text-danger">*</span></label>
						<textarea name="content" id="editContent" class="form-control" rows="5" required></textarea>
					</div>
					<div class="mb-3">
						<label class="form-label fw-semibold"><i class="fas fa-image me-1"></i> Image or Video <span class="text-muted fw-normal">(optional)</span></label>
						<!-- Current media preview -->
						<div id="editCurrentMedia" class="mb-2"></div>
						<input type="file" name="media" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" id="editMediaInput">
						<div class="form-text">Upload a new file to replace the current one, or leave empty to keep it.</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
	// Preview media on file select (Create modal)
	document.getElementById('createMediaInput').addEventListener('change', function () {
		const preview = document.getElementById('createMediaPreview');
		preview.innerHTML = '';
		if (this.files && this.files[0]) {
			const file = this.files[0];
			if (file.type.startsWith('image/')) {
				const img = document.createElement('img');
				img.src = URL.createObjectURL(file);
				img.style.cssText = 'max-width: 200px; max-height: 150px; border-radius: 8px; object-fit: cover;';
				preview.appendChild(img);
			}
		}
	});

	// Populate Edit Modal with data
	document.getElementById('editModal').addEventListener('show.bs.modal', function (event) {
		const button = event.relatedTarget;
		document.getElementById('editId').value = button.getAttribute('data-id');
		document.getElementById('editTitle').value = button.getAttribute('data-title');
		document.getElementById('editContent').value = button.getAttribute('data-content');
		document.getElementById('editRemoveMedia').value = '0';
		document.getElementById('editMediaInput').value = '';

		const mediaPath = button.getAttribute('data-media');
		const currentMedia = document.getElementById('editCurrentMedia');
		currentMedia.innerHTML = '';

		if (mediaPath) {
			const wrapper = document.createElement('div');
			wrapper.className = 'd-flex align-items-center gap-2 p-2 bg-light rounded';
			wrapper.innerHTML = '<img src="../' + mediaPath + '" style="width:60px;height:60px;object-fit:cover;border-radius:8px;">';

			const removeBtn = document.createElement('button');
			removeBtn.type = 'button';
			removeBtn.className = 'btn btn-sm btn-outline-danger ms-auto';
			removeBtn.innerHTML = '<i class="fas fa-times me-1"></i>Remove';
			removeBtn.addEventListener('click', function () {
				document.getElementById('editRemoveMedia').value = '1';
				currentMedia.innerHTML = '<span class="text-muted small"><i class="fas fa-info-circle me-1"></i>Media will be removed on save.</span>';
			});

			wrapper.appendChild(removeBtn);
			currentMedia.appendChild(wrapper);
		}
	});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
