<?php
require_once __DIR__ . '/../config.php';
if (!is_admin()) redirect('../index.php');

$pdo = get_db_connection();
$info = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_official') {
    if (csrf_validate()) {
        $id = (int)$_POST['id'];
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
        $position = filter_input(INPUT_POST, 'position', FILTER_SANITIZE_SPECIAL_CHARS);
        
        $image_path = $_POST['current_image'] ?? null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../public/uploads/officials/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $new_filename = 'official_' . $id . '_' . time() . '.' . $file_ext;
            $target_file = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_path = 'public/uploads/officials/' . $new_filename;
            }
        }

        try {
            $stmt = $pdo->prepare('UPDATE barangay_officials SET name = ?, position = ?, image_path = ? WHERE id = ?');
            $stmt->execute([$name, $position, $image_path, $id]);
            $info = 'Official updated successfully.';
        } catch (Exception $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    }
}

$officials = $pdo->query('SELECT * FROM barangay_officials ORDER BY rank ASC')->fetchAll();

$page_title = 'Manage Barangay Officials';
require_once __DIR__ . '/header.php';
?>

<style>
    .official-card { border-radius: 15px; border: none; transition: 0.3s; background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .official-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    .official-img-container { width: 100px; height: 100px; margin: 0 auto; overflow: hidden; border-radius: 50%; border: 3px solid #14b8a6; padding: 3px; }
    .official-img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
    .btn-edit-official { background: rgba(20, 184, 166, 0.1); color: #14b8a6; border: none; padding: 5px 15px; border-radius: 20px; font-weight: 700; font-size: 0.8rem; }
    .btn-edit-official:hover { background: #14b8a6; color: #fff; }
</style>

<div class="row mb-4 pt-2">
    <div class="col-12">
        <h4 class="fw-800 mb-1 d-flex align-items-center gap-2">
            <div class="bg-teal text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="fas fa-users-cog fs-6"></i>
            </div>
            Barangay Officials
        </h4>
        <p class="text-muted small ms-5">Update names and photos of the current Barangay leadership</p>
    </div>
</div>



<div class="row g-4">
    <?php foreach ($officials as $o): ?>
        <div class="col-xxl-3 col-xl-4 col-md-6">
            <div class="card official-card h-100 p-4">
                    <div class="text-center">
                        <div class="official-img-container mb-3">
                            <?php if ($o['image_path']): ?>
                                <img src="../<?php echo $o['image_path']; ?>" class="official-img" alt="Official">
                            <?php else: ?>
                                <img src="../public/img/barangaylogo.png" class="official-img opacity-50" alt="Official">
                            <?php endif; ?>
                        </div>
                        <h6 class="fw-800 mb-1"><?php echo htmlspecialchars($o['name']); ?></h6>
                        <span class="badge bg-teal-soft text-teal mb-3 px-3 py-2 rounded-pill" style="font-size: 0.65rem;"><?php echo htmlspecialchars($o['position']); ?></span>
                        
                        <div class="mt-2">
                            <button class="btn-edit-official" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $o['id']; ?>">
                                <i class="fas fa-edit me-1"></i> UPDATE
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Edit Modal -->
                <div class="modal fade" id="editModal<?php echo $o['id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow rounded-4">
                            <div class="modal-header border-0 pb-0">
                                <h5 class="modal-title fw-800">Edit Official</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" enctype="multipart/form-data">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="update_official">
                                <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                                <input type="hidden" name="current_image" value="<?php echo $o['image_path']; ?>">
                                
                                <div class="modal-body py-4">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-muted">POSITION</label>
                                        <input type="text" name="position" class="form-control rounded-3 fw-bold" value="<?php echo htmlspecialchars($o['position']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-muted">FULL NAME</label>
                                        <input type="text" name="name" class="form-control rounded-3" value="<?php echo htmlspecialchars($o['name']); ?>" required>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label small fw-bold text-muted">UPDATE PHOTO</label>
                                        <input type="file" name="image" class="form-control rounded-3" accept="image/*">
                                    </div>
                                </div>
                                <div class="modal-footer border-0 pt-0 pb-4 justify-content-center">
                                    <button type="submit" class="btn btn-primary px-4 py-2 rounded-pill fw-800">SAVE CHANGES</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
    .bg-teal-soft { background: rgba(20, 184, 166, 0.1); }
    .text-teal { color: #14b8a6; }
    .fw-800 { font-weight: 800; }
</style>

<?php if ($info): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?php echo $info; ?>',
        timer: 3000,
        showConfirmButton: false,
        iconColor: '#14b8a6',
        customClass: {
            popup: 'rounded-4 border-0 shadow-lg'
        }
    });
</script>
<?php endif; ?>

<?php if ($error): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: '<?php echo $error; ?>',
        confirmButtonColor: '#14b8a6'
    });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
