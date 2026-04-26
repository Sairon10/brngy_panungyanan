<?php
$page_title = 'Family Members';
require_once __DIR__ . '/partials/user_dashboard_header.php';

if (!is_logged_in()) redirect('login.php');

$pdo = get_db_connection();
$user_id = $_SESSION['user_id'];

// Handle family member actions
$family_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['family_action'])) {
    if (!csrf_validate()) {
        $family_msg = 'Invalid session. Please reload and try again.';
    } else {
        $family_action = $_POST['family_action'];

        if ($family_action === 'add_family_member' || $family_action === 'edit_family_member') {
            $fname = trim($_POST['fm_first_name'] ?? '');
            $mname = trim($_POST['fm_middle_name'] ?? '');
            $lname = trim($_POST['fm_last_name'] ?? '');
            $fmsuffix = trim($_POST['fm_suffix'] ?? '');
            $fm_name = implode(' ', array_filter([$fname, $mname, $lname, $fmsuffix]));
            $fm_relationship = trim($_POST['fm_relationship'] ?? '');
            $fm_birthdate = $_POST['fm_birthdate'] ?? null;
            $fm_sex = $_POST['fm_sex'] ?? null;
            $fm_civil_status = $_POST['fm_civil_status'] ?? 'Single';
            $fm_birth_place = trim($_POST['fm_birth_place'] ?? '');
            $fm_citizenship = trim($_POST['fm_citizenship'] ?? 'Filipino');
            $fm_philsys = trim($_POST['fm_philsys_card_no'] ?? '');
            $fm_religion = trim($_POST['fm_religion'] ?? '');
            $fm_occupation = trim($_POST['fm_occupation'] ?? '');
            $fm_edu = trim($_POST['fm_educational_attainment'] ?? '');
            $fm_edu_status = trim($_POST['fm_educational_status'] ?? '');
            
            // Boolean flags
            $fm_is_pwd = isset($_POST['fm_is_pwd']) ? 1 : 0;
            $fm_is_senior = isset($_POST['fm_is_senior']) ? 1 : 0;
            $fm_is_solo_parent = isset($_POST['fm_is_solo_parent']) ? 1 : 0;
            $fm_is_others = isset($_POST['fm_is_others']) ? 1 : 0;

            // Classifications as JSON (for RBI compatibility)
            $classifications = [];
            if ($fm_is_pwd) $classifications[] = 'PWD';
            if ($fm_is_senior) $classifications[] = 'Senior';
            if ($fm_is_solo_parent) $classifications[] = 'Solo Parent';
            if ($fm_is_others) $classifications[] = 'Others';
            $classification_json = json_encode($classifications);

            if ($fm_name === '' || $fm_relationship === '') {
                $family_msg = 'First name, last name, and relationship are required.';
            } else {
                if ($family_action === 'add_family_member') {
                    $id_photo_path = null;
                    if (isset($_FILES['fm_id_photo']) && $_FILES['fm_id_photo']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = __DIR__ . '/uploads/ids/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        $ext = pathinfo($_FILES['fm_id_photo']['name'], PATHINFO_EXTENSION);
                        if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'pdf'])) {
                            $filename = 'fm_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                            if (move_uploaded_file($_FILES['fm_id_photo']['tmp_name'], $uploadDir . $filename)) {
                                $id_photo_path = 'uploads/ids/' . $filename;
                            }
                        }
                    }

                    $pdo->prepare('INSERT INTO family_members (user_id, first_name, middle_name, last_name, suffix, full_name, relationship, birthdate, sex, civil_status, birth_place, citizenship, philsys_card_no, religion, occupation, educational_attainment, educational_status, classification, id_photo_path, is_pwd, is_senior, is_solo_parent, is_others) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$user_id, $fname, $mname, $lname, $fmsuffix, $fm_name, $fm_relationship, $fm_birthdate ?: null, $fm_sex ?: null, $fm_civil_status, $fm_birth_place, $fm_citizenship, $fm_philsys, $fm_religion, $fm_occupation, $fm_edu, $fm_edu_status, $classification_json, $id_photo_path, $fm_is_pwd, $fm_is_senior, $fm_is_solo_parent, $fm_is_others]);
                    $family_msg = 'Family member added successfully.';
                } else {
                    $fm_id = (int)($_POST['fm_id'] ?? 0);
                    $id_photo_update = "";
                    $params = [$fname, $mname, $lname, $fmsuffix, $fm_name, $fm_relationship, $fm_birthdate ?: null, $fm_sex ?: null, $fm_civil_status, $fm_birth_place, $fm_citizenship, $fm_philsys, $fm_religion, $fm_occupation, $fm_edu, $fm_edu_status, $classification_json, $fm_is_pwd, $fm_is_senior, $fm_is_solo_parent, $fm_is_others];

                    if (isset($_FILES['fm_id_photo']) && $_FILES['fm_id_photo']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = __DIR__ . '/uploads/ids/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        $ext = pathinfo($_FILES['fm_id_photo']['name'], PATHINFO_EXTENSION);
                        if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'pdf'])) {
                            $filename = 'fm_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                            if (move_uploaded_file($_FILES['fm_id_photo']['tmp_name'], $uploadDir . $filename)) {
                                $id_photo_update = ", id_photo_path=?";
                                $params[] = 'uploads/ids/' . $filename;
                            }
                        }
                    }

                    $params[] = $fm_id;
                    $params[] = $user_id;

                    $pdo->prepare("UPDATE family_members SET first_name=?, middle_name=?, last_name=?, suffix=?, full_name=?, relationship=?, birthdate=?, sex=?, civil_status=?, birth_place=?, citizenship=?, philsys_card_no=?, religion=?, occupation=?, educational_attainment=?, educational_status=?, classification=?, is_pwd=?, is_senior=?, is_solo_parent=?, is_others=? {$id_photo_update} WHERE id=? AND user_id=?")
                        ->execute($params);
                    $family_msg = 'Family member updated successfully.';
                }
            }
        } elseif ($family_action === 'delete_family_member') {
            $fm_id = (int)($_POST['fm_id'] ?? 0);
            $pdo->prepare('DELETE FROM family_members WHERE id=? AND user_id=?')->execute([$fm_id, $user_id]);
            $family_msg = 'Family member removed.';
        }
    }
}

// Fetch family members
$fm_stmt = $pdo->prepare('SELECT * FROM family_members WHERE user_id = ? ORDER BY created_at ASC');
$fm_stmt->execute([$user_id]);
$family_members = $fm_stmt->fetchAll();

$relationship_options = ['Spouse', 'Child', 'Parent', 'Sibling', 'Grandparent', 'Grandchild'];
$civil_status_options = ['Single', 'Married', 'Widowed', 'Separated', 'Annulled'];
$edu_options = ['No Formal Education', 'Elementary Level', 'Elementary Graduate', 'High School Level', 'High School Graduate', 'Vocational', 'College Level', 'College Graduate', 'Post Graduate'];
?>

<div class="row justify-content-center animate__animated animate__fadeInUp">
    <div class="col-lg-11">
        <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
            <div class="bg-gradient-primary p-4 text-white" style="background: linear-gradient(135deg, #0f766e, #14b8a6);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="fw-bold mb-1"><i class="fas fa-users me-2"></i>Household Members</h3>
                    </div>
                    <button type="button" class="btn btn-white rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#familyModal" onclick="prepareAddModal()">
                        <i class="fas fa-plus me-1 text-primary"></i> Add Member
                    </button>
                </div>
            </div>

            <div class="card-body p-4 bg-light-subtle">
                <?php if ($family_msg): ?>
                <script>
                    Swal.fire({
                        icon: '<?php echo (strpos($family_msg, "successfully") !== false || strpos($family_msg, "removed") !== false) ? "success" : "error"; ?>',
                        title: 'Notice',
                        text: '<?php echo $family_msg; ?>',
                        confirmButtonColor: '#0f766e'
                    });
                </script>
                <?php endif; ?>

                <?php if (empty($family_members)): ?>
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-id-card fa-4x text-teal-100"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Registry is Empty</h5>
                    <p class="text-muted mb-4 max-w-md mx-auto">Please add your family members to complete your household profile and enable faster document requests.</p>
                    <button type="button" class="btn btn-primary rounded-pill px-5 shadow" data-bs-toggle="modal" data-bs-target="#familyModal" onclick="prepareAddModal()">
                        <i class="fas fa-plus me-2"></i>Register New Member
                    </button>
                </div>
                <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($family_members as $fm): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="card border-0 shadow-sm rounded-4 h-100 transition-all hover-translate-y-2 overflow-hidden">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-circle bg-teal-50 text-teal-600 flex-shrink-0" style="width: 52px; height: 52px;">
                                            <i class="fas fa-user-friends"></i>
                                        </div>
                                        <div class="overflow-hidden">
                                            <h6 class="fw-bold text-dark mb-0 text-truncate"><?php echo htmlspecialchars($fm['full_name']); ?></h6>
                                            <div class="d-flex align-items-center gap-1 mt-1">
                                                <span class="badge bg-teal-100 text-teal-700 rounded-pill small"><?php echo htmlspecialchars($fm['relationship']); ?></span>
                                                <?php if ($fm['is_active']): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill" style="font-size: 0.6rem; padding: 2px 8px;">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill" style="font-size: 0.6rem; padding: 2px 8px;">Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-icon btn-light rounded-circle" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3">
                                            <li><a class="dropdown-item py-2" href="#" onclick='prepareEditModal(<?php echo json_encode($fm); ?>)'><i class="fas fa-pen me-2 text-muted small"></i> Update Details</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="post" onsubmit="return confirmDelete()">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="family_action" value="delete_family_member">
                                                    <input type="hidden" name="fm_id" value="<?php echo $fm['id']; ?>">
                                                    <button type="submit" class="dropdown-item py-2 text-danger"><i class="fas fa-trash-alt me-2 small"></i> Unregister Member</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-3 mt-4 text-xs font-medium">
                                    <div class="bg-light p-2 rounded-3 border border-light">
                                        <div class="text-muted text-uppercase letter-spacing-1 mb-1" style="font-size: 0.6rem;">PhilSys No</div>
                                        <div class="text-dark"><?php echo $fm['philsys_card_no'] ?: '<span class="opacity-30">N/A</span>'; ?></div>
                                    </div>
                                    <div class="bg-light p-2 rounded-3 border border-light">
                                        <div class="text-muted text-uppercase letter-spacing-1 mb-1" style="font-size: 0.6rem;">Status</div>
                                        <div class="text-dark"><?php echo $fm['civil_status'] ?: 'Single'; ?></div>
                                    </div>
                                </div>

                                <div class="mt-4 pt-3 border-top">
                                    <div class="row g-2 text-secondary small mb-3">
                                        <div class="col-6">
                                            <i class="fas fa-birthday-cake me-2 opacity-50"></i><?php echo $fm['birthdate'] ? date('M j, Y', strtotime($fm['birthdate'])) : '---'; ?>
                                        </div>
                                        <div class="col-6">
                                            <i class="fas fa-venus-mars me-2 opacity-50"></i><?php echo $fm['sex'] ?: 'Unknown'; ?>
                                        </div>
                                        <div class="col-12 mt-2">
                                            <i class="fas fa-graduation-cap me-2 opacity-50"></i><?php echo $fm['educational_attainment'] ?: 'No Record'; ?>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2">
                                        <?php 
                                        $classes = json_decode($fm['classification'] ?? '[]', true);
                                        foreach ($classes as $c): 
                                            $c_color = 'bg-gray-100 text-gray-700';
                                            if($c == 'PWD') $c_color = 'bg-blue-100 text-blue-700';
                                            if($c == 'Senior') $c_color = 'bg-orange-100 text-orange-700';
                                            if($c == 'Others') $c_color = 'bg-red-100 text-red-700';
                                            if($c == 'Solo Parent') $c_color = 'bg-purple-100 text-purple-700';
                                        ?>
                                            <span class="badge <?php echo $c_color; ?> border-0 rounded-pill px-2 py-1" style="font-size: 0.65rem;"><?php echo $c; ?></span>
                                        <?php endforeach; ?>
                                        <?php if(empty($classes)): ?>
                                            <span class="badge bg-light text-muted border-0 rounded-pill px-2 py-1" style="font-size: 0.65rem;">General</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Unified Family Member Modal -->
<div class="modal fade" id="familyModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="modalTitle"><i class="fas fa-user-plus me-2 text-primary"></i>Member Registration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" enctype="multipart/form-data" id="familyForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="family_action" id="modalAction" value="add_family_member">
                <input type="hidden" name="fm_id" id="fm_id">
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <!-- Personal Info Section -->
                        <div class="col-lg-12">
                            <h6 class="text-teal-700 fw-bold border-bottom pb-2 mb-3 small text-uppercase letter-spacing-1"><i class="fas fa-id-card me-2"></i>Personal Identification</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label rbi-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="fm_first_name" id="fm_first_name" class="form-control rbi-input" placeholder="JUAN" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label rbi-label">Middle Name</label>
                                    <input type="text" name="fm_middle_name" id="fm_middle_name" class="form-control rbi-input" placeholder="SANTOS">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label rbi-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" name="fm_last_name" id="fm_last_name" class="form-control rbi-input" placeholder="DELA CRUZ" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label rbi-label">Suffix (Jr, III, etc)</label>
                                    <input type="text" name="fm_suffix" id="fm_suffix" class="form-control rbi-input">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label rbi-label">Relationship to Head <span class="text-danger">*</span></label>
                                    <select name="fm_relationship" id="fm_relationship" class="form-select rbi-input" required>
                                        <option value="">-- SELECT --</option>
                                        <?php foreach ($relationship_options as $rel): ?>
                                            <option value="<?php echo $rel; ?>"><?php echo $rel; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label rbi-label">PhilSys Card Number</label>
                                    <input type="text" name="fm_philsys_card_no" id="fm_philsys_card_no" class="form-control rbi-input" placeholder="####-####-####-####">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label rbi-label">Citizenship</label>
                                    <input type="text" name="fm_citizenship" id="fm_citizenship" class="form-control rbi-input" value="FILIPINO">
                                </div>
                            </div>
                        </div>

                        <!-- Demographics Section -->
                        <div class="col-lg-12 mt-5">
                            <h6 class="text-teal-700 fw-bold border-bottom pb-2 mb-3 small text-uppercase letter-spacing-1"><i class="fas fa-map-marker-alt me-2"></i>Demographics & Status</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label rbi-label">Birthdate <span class="text-danger">*</span></label>
                                    <input type="date" name="fm_birthdate" id="fm_birthdate" class="form-control rbi-input" max="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label rbi-label">Sex <span class="text-danger">*</span></label>
                                    <select name="fm_sex" id="fm_sex" class="form-select rbi-input" required>
                                        <option value="">-- SELECT --</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label rbi-label">Civil Status <span class="text-danger">*</span></label>
                                    <select name="fm_civil_status" id="fm_civil_status" class="form-select rbi-input" required>
                                        <?php foreach ($civil_status_options as $status): ?>
                                            <option value="<?php echo $status; ?>"><?php echo $status; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label rbi-label">Religion</label>
                                    <input type="text" name="fm_religion" id="fm_religion" class="form-control rbi-input" placeholder="e.g. ROMAN CATHOLIC">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label rbi-label">Place of Birth <span class="text-danger">*</span></label>
                                    <input type="text" name="fm_birth_place" id="fm_birth_place" class="form-control rbi-input" placeholder="CITY/PROVINCE" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label rbi-label">Occupation</label>
                                    <input type="text" name="fm_occupation" id="fm_occupation" class="form-control rbi-input" placeholder="e.g. STUDENT, HOUSEWIFE, PRIVATE">
                                </div>
                            </div>
                        </div>

                        <!-- Education Section -->
                        <div class="col-lg-12 mt-5">
                            <h6 class="text-teal-700 fw-bold border-bottom pb-2 mb-3 small text-uppercase letter-spacing-1"><i class="fas fa-graduation-cap me-2"></i>Educational Background</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label rbi-label">Highest Educational Attainment</label>
                                    <select name="fm_educational_attainment" id="fm_educational_attainment" class="form-select rbi-input">
                                        <option value="">-- SELECT --</option>
                                        <?php foreach ($edu_options as $edu): ?>
                                            <option value="<?php echo $edu; ?>"><?php echo $edu; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label rbi-label">Current Enrollment Status</label>
                                    <select name="fm_educational_status" id="fm_educational_status" class="form-select rbi-input">
                                        <option value="">N/A</option>
                                        <option value="Enrolled">Currently Enrolled</option>
                                        <option value="Not Enrolled">Not Enrolled</option>
                                        <option value="Graduated">Graduated/Finished</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Classifications Section -->
                        <div class="col-lg-12 mt-5">
                            <h6 class="text-teal-700 fw-bold border-bottom pb-2 mb-3 small text-uppercase letter-spacing-1"><i class="fas fa-tags me-2"></i>Special Classifications & Identity</h6>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <div class="row g-2">
                                        <div class="col-md-3">
                                            <div class="classification-box rounded-3 border p-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="fm_is_pwd" id="fm_is_pwd" value="1">
                                                    <label class="form-check-label fw-bold" for="fm_is_pwd">
                                                        <i class="fas fa-wheelchair text-blue-500 me-2"></i>PWD
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="classification-box rounded-3 border p-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="fm_is_senior" id="fm_is_senior" value="1">
                                                    <label class="form-check-label fw-bold" for="fm_is_senior">
                                                        <i class="fas fa-user-clock text-orange-500 me-2"></i>Senior Citizen
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="classification-box rounded-3 border p-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="fm_is_solo_parent" id="fm_is_solo_parent" value="1">
                                                    <label class="form-check-label fw-bold" for="fm_is_solo_parent">
                                                        <i class="fas fa-user-friends text-purple-500 me-2"></i>Solo Parent
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="classification-box rounded-3 border p-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="fm_is_others" id="fm_is_others" value="1">
                                                    <label class="form-check-label fw-bold" for="fm_is_others">
                                                        <i class="fas fa-ellipsis-h text-red-500 me-2"></i>Others
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-12 mt-3">
                                    <label class="form-label rbi-label">Attach Identification (Required for Special Classes)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-camera text-muted"></i></span>
                                        <input type="file" name="fm_id_photo" class="form-control rbi-input" accept="image/*,.pdf">
                                    </div>
                                    <small class="text-muted mt-1 d-block"><i class="fas fa-info-circle me-1"></i> Max size 2MB. Format: JPG, PNG, or PDF.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 p-4 bg-light-subtle rounded-bottom-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Discard</button>
                    <button type="submit" class="btn btn-teal-600 text-white rounded-pill px-5 shadow-sm fw-bold">Save Member Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
:root {
    --teal-50: #f0fdfa;
    --teal-100: #ccfbf1;
    --teal-600: #0d9488;
    --teal-700: #0f766e;
    --teal-800: #115e59;
}
.bg-teal-50 { background-color: var(--teal-50); }
.bg-teal-100 { background-color: var(--teal-100); }
.text-teal-600 { color: var(--teal-600); }
.text-teal-700 { color: var(--teal-700); }
.btn-teal-600 { background-color: var(--teal-600); border-color: var(--teal-600); }
.btn-teal-600:hover { background-color: var(--teal-700); border-color: var(--teal-700); color: white; }

.rbi-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 5px; }
.rbi-input { border: 1px solid #e2e8f0; font-size: 0.9rem; padding: 0.6rem 0.75rem; border-radius: 8px; transition: all 0.2s; }
.rbi-input:focus { border-color: var(--teal-600); box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1); }
.classification-box { transition: all 0.2s; background: #fff; cursor: pointer; }
.classification-box:hover { border-color: var(--teal-600); background: var(--teal-50); }
.classification-box:has(.form-check-input:checked) { border-color: var(--teal-600); background: var(--teal-50); }

.avatar-circle { border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.hover-translate-y-2:hover { transform: translateY(-5px); }
.letter-spacing-1 { letter-spacing: 1px; }

.btn-icon { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; padding: 0; }
</style>

<script>
function prepareAddModal() {
    document.getElementById('familyForm').reset();
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus me-2 text-primary"></i>Member Registration';
    document.getElementById('modalAction').value = 'add_family_member';
    document.getElementById('fm_id').value = '';
}

function prepareEditModal(fm) {
    document.getElementById('familyForm').reset();
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit me-2 text-primary"></i>Update Record';
    document.getElementById('modalAction').value = 'edit_family_member';
    document.getElementById('fm_id').value = fm.id;
    
    // Fill fields
    document.getElementById('fm_first_name').value = fm.first_name || '';
    document.getElementById('fm_middle_name').value = fm.middle_name || '';
    document.getElementById('fm_last_name').value = fm.last_name || '';
    document.getElementById('fm_suffix').value = fm.suffix || '';
    document.getElementById('fm_relationship').value = fm.relationship || '';
    document.getElementById('fm_philsys_card_no').value = fm.philsys_card_no || '';
    document.getElementById('fm_citizenship').value = fm.citizenship || 'FILIPINO';
    document.getElementById('fm_birthdate').value = fm.birthdate || '';
    document.getElementById('fm_sex').value = fm.sex || '';
    document.getElementById('fm_civil_status').value = fm.civil_status || 'Single';
    document.getElementById('fm_religion').value = fm.religion || '';
    document.getElementById('fm_birth_place').value = fm.birth_place || '';
    document.getElementById('fm_occupation').value = fm.occupation || '';
    document.getElementById('fm_educational_attainment').value = fm.educational_attainment || '';
    document.getElementById('fm_educational_status').value = fm.educational_status || '';
    
    // Checkboxes
    document.getElementById('fm_is_pwd').checked = fm.is_pwd == 1;
    document.getElementById('fm_is_senior').checked = fm.is_senior == 1;
    document.getElementById('fm_is_solo_parent').checked = fm.is_solo_parent == 1;
    document.getElementById('fm_is_others').checked = fm.is_others == 1;
    
    new bootstrap.Modal(document.getElementById('familyModal')).show();
}

function confirmDelete() {
    return confirm("Are you sure you want to unregister this member? This action will remove them from your household and RBI records.");
}
</script>

<?php require_once __DIR__ . '/partials/user_dashboard_footer.php'; ?>
