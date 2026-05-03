<?php
require_once __DIR__ . '/../config.php';
if (!is_admin())
    redirect('/index.php');

$pdo = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents(__DIR__ . '/request_log_view.txt', date('Y-m-d H:i:s') . " - " . $_SERVER['REQUEST_URI'] . "\nPOST: " . print_r($_POST, true) . "\n", FILE_APPEND);
}
$record_id = (int) ($_GET['id'] ?? 0);
$user_id = (int) ($_GET['user_id'] ?? 0);

if ($record_id <= 0 && $user_id <= 0) {
    redirect('resident_records.php');
}

$page_title = 'Resident Record Details';

// Check if we came from Account Management to adjust breadcrumbs
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$from_account = (strpos($referer, 'account_management.php') !== false);
$back_url = $from_account ? 'account_management.php' : 'resident_records.php';
$back_label = $from_account ? 'Account Management' : 'Resident Records';

$breadcrumb = [
    ['title' => $back_label, 'url' => $back_url],
    ['title' => 'View Details']
];

$errors = [];
$success = '';

// Handle verify / reject BEFORE header output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['verify', 'reject'])) {
    if (!csrf_validate()) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $resident_id = (int) ($_POST['resident_id'] ?? 0);
        if ($resident_id > 0) {
            try {
                $rs = $pdo->prepare('SELECT r.*, u.full_name, u.email, r.phone FROM residents r JOIN users u ON r.user_id = u.id WHERE r.id = ?');
                $rs->execute([$resident_id]);
                $rd = $rs->fetch();
                if ($rd) {
                    if ($_POST['action'] === 'verify') {
                        $pdo->prepare('UPDATE residents SET verification_status = \'verified\', verified_at = NOW(), verified_by = ? WHERE id = ?')->execute([$_SESSION['user_id'], $resident_id]);
                        if (!empty($rd['email']))
                            send_id_verification_email($rd['email'], 'verified', ['full_name' => $rd['full_name'], 'verification_notes' => null]);
                        $redirect_url = 'resident_record_view.php?id=' . $record_id;
                        if ($record_id <= 0 && $user_id > 0) {
                            $redirect_url .= '&user_id=' . $user_id;
                        }
                        header('Location: ' . $redirect_url . '&msg=' . urlencode('Resident has been verified successfully.'));
                        exit;
                    } elseif ($_POST['action'] === 'reject') {
                        $notes = trim($_POST['rejection_notes'] ?? '');
                        if ($notes === '') {
                            $errors[] = 'Rejection reason is required.';
                        } else {
                            $pdo->prepare('UPDATE residents SET verification_status = \'rejected\', verification_notes = ?, verified_at = NOW(), verified_by = ? WHERE id = ?')->execute([$notes, $_SESSION['user_id'], $resident_id]);
                            if (!empty($rd['email']))
                                send_id_verification_email($rd['email'], 'rejected', ['full_name' => $rd['full_name'], 'verification_notes' => $notes]);
                            
                            $redirect_url = 'resident_record_view.php?id=' . $record_id;
                            if ($record_id <= 0 && $user_id > 0) {
                                $redirect_url .= '&user_id=' . $user_id;
                            }
                            header('Location: ' . $redirect_url . '&msg=' . urlencode('Resident verification has been rejected.'));
                            exit;
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = 'Server error. Please try again.';
            }
        }
    }
}

// Handle family member update from admin side
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_family_member') {
    if (!csrf_validate()) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $fm_id = (int)($_POST['fm_id'] ?? 0);
        $fname = trim($_POST['fm_first_name'] ?? '');
        $mname = trim($_POST['fm_middle_name'] ?? '');
        $lname = trim($_POST['fm_last_name'] ?? '');
        $suffix = trim($_POST['fm_suffix'] ?? '');
        $fullname = implode(' ', array_filter([$fname, $mname, $lname, $suffix]));
        
        $relationship = trim($_POST['fm_relationship'] ?? '');
        $philsys = trim($_POST['fm_philsys_card_no'] ?? '');
        $citizenship = trim($_POST['fm_citizenship'] ?? 'FILIPINO');
        
        $birthdate = $_POST['fm_birthdate'] ?? null;
        $sex = $_POST['fm_sex'] ?? null;
        $civil_status = $_POST['fm_civil_status'] ?? 'Single';
        $religion = trim($_POST['fm_religion'] ?? '');
        $birth_place = trim($_POST['fm_birth_place'] ?? '');
        $occupation = trim($_POST['fm_occupation'] ?? '');
        
        $edu = $_POST['fm_educational_attainment'] ?? '';
        $edu_status = $_POST['fm_educational_status'] ?? 'N/A';
        
        // Boolean classifications
        $is_pwd = isset($_POST['fm_is_pwd']) ? 1 : 0;
        $is_senior = isset($_POST['fm_is_senior']) ? 1 : 0;
        $is_minor = isset($_POST['fm_is_minor']) ? 1 : 0;
        $is_solo_parent = isset($_POST['fm_is_solo_parent']) ? 1 : 0;

        $classifications = [];
        if ($is_pwd) $classifications[] = 'PWD';
        if ($is_senior) $classifications[] = 'Senior';
        if ($is_solo_parent) $classifications[] = 'Solo Parent';
        $classification_json = json_encode($classifications);

        if ($fm_id > 0 && !empty($fname) && !empty($lname)) {
            try {
                $stmt = $pdo->prepare('UPDATE family_members SET 
                    first_name=?, middle_name=?, last_name=?, suffix=?, full_name=?, 
                    relationship=?, philsys_card_no=?, citizenship=?, 
                    birthdate=?, sex=?, civil_status=?, religion=?, birth_place=?, occupation=?, 
                    educational_attainment=?, educational_status=?, 
                    is_pwd=?, is_senior=?, is_minor=?, is_solo_parent=?, classification=? 
                    WHERE id=?');
                $stmt->execute([
                    $fname, $mname, $lname, $suffix, $fullname,
                    $relationship, $philsys, $citizenship,
                    $birthdate ?: null, $sex ?: null, $civil_status, $religion, $birth_place, $occupation,
                    $edu, $edu_status,
                    $is_pwd, $is_senior, $is_minor, $is_solo_parent, $classification_json,
                    $fm_id
                ]);
                $success = 'Family member details updated successfully.';
            } catch (Exception $e) {
                $errors[] = 'Error updating family member: ' . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['msg']))
    $success = htmlspecialchars($_GET['msg']);

require_once __DIR__ . '/header.php';
?>

<?php

// Get resident record
$record = null;
if ($record_id > 0) {
    $stmt = $pdo->prepare('SELECT rr.*, u.full_name as created_by_name FROM resident_records rr LEFT JOIN users u ON rr.created_by = u.id WHERE rr.id = ?');
    $stmt->execute([$record_id]);
    $record = $stmt->fetch();
}

if (!$record && $user_id > 0) {
    // Fallback to user account if no official record exists yet
    $stmt = $pdo->prepare('SELECT u.id as user_id, u.email, u.full_name, u.first_name, u.middle_name, u.last_name, u.suffix, u.role, u.is_active, r.* FROM users u LEFT JOIN residents r ON r.user_id = u.id WHERE u.id = ? AND u.role = \'resident\' LIMIT 1');
    $stmt->execute([$user_id]);
    $record = $stmt->fetch();
    if ($record) {
        $record['id'] = 0;
        $record['created_by_name'] = 'Self Registered';
        if (!isset($record['created_at'])) $record['created_at'] = date('Y-m-d H:i:s');
        if (!isset($record['updated_at'])) $record['updated_at'] = null;
    }
}

if (!$record) {
    redirect('resident_records.php');
}

$fm_id = (int)($_GET['fm_id'] ?? 0);
$view_fm = null;
if ($fm_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM family_members WHERE id = ?');
    $stmt->execute([$fm_id]);
    $view_fm = $stmt->fetch();
}

// Parse full_name into parts if individual name fields are empty
// This helps display name parts even if they weren't stored separately
if ((empty($record['first_name']) || empty($record['last_name'])) && !empty($record['full_name'])) {
    $name_parts = array_filter(explode(' ', trim($record['full_name'])));
    if (count($name_parts) >= 2) {
        // Common suffixes to check
        $suffixes = ['Jr', 'Sr', 'II', 'III', 'IV', 'V', 'Jr.', 'Sr.'];
        $last_part = end($name_parts);

        // Check if last part is a suffix
        if (in_array($last_part, $suffixes) && count($name_parts) >= 3) {
            $record['suffix'] = $last_part;
            $record['last_name'] = $name_parts[count($name_parts) - 2];
            $record['first_name'] = $name_parts[0];
            if (count($name_parts) > 3) {
                $record['middle_name'] = implode(' ', array_slice($name_parts, 1, -2));
            }
        } else {
            $record['first_name'] = $name_parts[0];
            $record['last_name'] = end($name_parts);
            if (count($name_parts) > 2) {
                $record['middle_name'] = implode(' ', array_slice($name_parts, 1, -1));
            }
        }
    }
}

// Try to find matching user/resident to get profile picture and account information
// First try by email, then by full_name
$avatar = null;
$linked_user = null;
$linked_resident = null;

if (!empty($record['email'])) {
    $stmt = $pdo->prepare('SELECT u.id as user_id, u.email, u.full_name, u.first_name, u.middle_name, u.last_name, u.suffix, u.role, u.created_at as user_created_at, r.* FROM users u LEFT JOIN residents r ON r.user_id = u.id WHERE u.email = ? AND u.role = \'resident\' LIMIT 1');
    $stmt->execute([$record['email']]);
    $linked_user = $stmt->fetch();
    if ($linked_user) {
        $linked_resident = $linked_user;
        if (!empty($linked_user['avatar'])) {
            $avatar = $linked_user['avatar'];
        }
    }
}

// If not found by email, try by full_name
if (!$linked_user && !empty($record['full_name'])) {
    $stmt = $pdo->prepare('SELECT u.id as user_id, u.email, u.full_name, u.first_name, u.middle_name, u.last_name, u.suffix, u.role, u.created_at as user_created_at, r.* FROM users u LEFT JOIN residents r ON r.user_id = u.id WHERE u.full_name = ? AND u.role = \'resident\' LIMIT 1');
    $stmt->execute([$record['full_name']]);
    $linked_user = $stmt->fetch();
    if ($linked_user) {
        $linked_resident = $linked_user;
        if (!empty($linked_user['avatar'])) {
            $avatar = $linked_user['avatar'];
        }
    }
}

// Get document requests and clearances for this resident
$all_requests = [];
if ($linked_user) {
    // Regular document requests
    $stmt = $pdo->prepare('SELECT dr.*, dt.name as doc_type_name FROM document_requests dr LEFT JOIN document_types dt ON dr.doc_type = dt.name WHERE dr.user_id = ? ORDER BY dr.created_at DESC LIMIT 10');
    $stmt->execute([$linked_user['user_id']]);
    $docs = $stmt->fetchAll();
    foreach ($docs as $doc) {
        $all_requests[] = [
            'type' => $doc['doc_type'] ?? 'Unknown Document',
            'detail' => '',
            'purpose' => $doc['purpose'],
            'status' => $doc['status'],
            'created_at' => $doc['created_at']
        ];
    }

    // Barangay clearances
    $stmt = $pdo->prepare('SELECT * FROM barangay_clearances WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
    $stmt->execute([$linked_user['user_id']]);
    $clearances = $stmt->fetchAll();
    foreach ($clearances as $clear) {
        $all_requests[] = [
            'type' => 'Barangay Clearance',
            'detail' => '',
            'purpose' => $clear['purpose'],
            'status' => $clear['status'],
            'created_at' => $clear['created_at']
        ];
    }

    // Sort by created_at DESC
    usort($all_requests, function ($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    // Limit to overall 10 latest
    $all_requests = array_slice($all_requests, 0, 10);
}

// Get incidents for this resident (if they have an account)
$incidents = [];
$family_members = [];
if ($linked_user) {
    $stmt = $pdo->prepare('SELECT * FROM incidents WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
    $stmt->execute([$linked_user['user_id']]);
    $incidents = $stmt->fetchAll();

    // Get family members
    $stmt = $pdo->prepare('SELECT * FROM family_members WHERE user_id = ? ORDER BY full_name');
    $stmt->execute([$linked_user['user_id']]);
    $family_members = $stmt->fetchAll();
}

// Prepare merged data for the Edit Modal so it contains the complete information
// Always prefer the resident's own profile (residents table) over admin-entered data
$edit_data = $record;
if ($linked_resident) {
    // Always override with user profile data if it exists (not just when empty)
    if (!empty($linked_resident['phone']))               $edit_data['phone']                  = $linked_resident['phone'];
    if (!empty($linked_resident['email']))               $edit_data['email']                  = $linked_resident['email'];
    if (!empty($linked_resident['birthdate']))           $edit_data['birthdate']               = $linked_resident['birthdate'];
    if (!empty($linked_resident['sex']))                 $edit_data['sex']                    = $linked_resident['sex'];
    if (!empty($linked_resident['citizenship']))         $edit_data['citizenship']             = $linked_resident['citizenship'];
    if (!empty($linked_resident['civil_status']))        $edit_data['civil_status']            = $linked_resident['civil_status'];
    if (!empty($linked_resident['purok']))               $edit_data['purok']                   = $linked_resident['purok'];
    if (!empty($linked_resident['address']))             $edit_data['address']                 = $linked_resident['address'];
    if (!empty($linked_resident['religion']))            $edit_data['religion']                = $linked_resident['religion'];
    if (!empty($linked_resident['occupation']))          $edit_data['occupation']              = $linked_resident['occupation'];
    if (!empty($linked_resident['educational_attainment'])) $edit_data['educational_attainment'] = $linked_resident['educational_attainment'];
    if (!empty($linked_resident['classification']))      $edit_data['classification']          = $linked_resident['classification'];
    if (!empty($linked_resident['birth_place']))         $edit_data['birth_place']             = $linked_resident['birth_place'];
    if (!empty($linked_resident['philsys_card_no']))     $edit_data['philsys_card_no']         = $linked_resident['philsys_card_no'];
    if (!empty($linked_resident['barangay_id']))         $edit_data['barangay_id']             = $linked_resident['barangay_id'];
    // Name fields from users table
    if (!empty($linked_resident['first_name']))          $edit_data['first_name']              = $linked_resident['first_name'];
    if (!empty($linked_resident['middle_name']))         $edit_data['middle_name']             = $linked_resident['middle_name'];
    if (!empty($linked_resident['last_name']))           $edit_data['last_name']               = $linked_resident['last_name'];
    if (!empty($linked_resident['suffix']))              $edit_data['suffix']                  = $linked_resident['suffix'];
    if (!empty($linked_resident['full_name']))           $edit_data['full_name']               = $linked_resident['full_name'];
}

?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $e): ?>
                <li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Toast Notification -->
<?php if ($success): ?>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
        <div id="actionToast" class="toast align-items-center text-white border-0 show"
            style="background: linear-gradient(135deg, #11998e, #38ef7d); min-width: 280px;" role="alert"
            aria-live="assertive" data-bs-autohide="true" data-bs-delay="4000">
            <div class="d-flex">
                <div class="toast-body fw-semibold">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['updated']) && $_GET['updated'] == '1'): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'success',
        title: 'Record Updated!',
        text: 'The resident record has been successfully updated.',
        confirmButtonColor: '#0f766e',
        confirmButtonText: 'OK',
        timer: 4000,
        timerProgressBar: true
    });
});
</script>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
            <?php
            $display_data = $edit_data;
            $is_family_member = false;
            if ($view_fm) {
                $display_data = $view_fm;
                $is_family_member = true;
            }
            ?>
            <!-- Profile Header/Cover -->
            <div class="bg-gradient-primary p-5 text-center position-relative"
                style="background: linear-gradient(135deg, var(--primary), var(--secondary));">
                <div class="position-absolute top-0 start-0 w-100 h-100 bg-pattern opacity-10"></div>

                <?php if (!$is_family_member && $avatar && file_exists(__DIR__ . '/../' . $avatar)): ?>
                    <img src="/<?php echo htmlspecialchars($avatar); ?>" alt="Profile Picture"
                        class="rounded-circle mx-auto mb-3 shadow-lg"
                        style="width: 120px; height: 120px; object-fit: cover; border: 4px solid white; cursor: pointer; position: relative; z-index: 10;"
                        onclick="previewProfileImage(this.src)">
                <?php else: ?>
                    <div class="avatar-circle bg-white text-primary fw-bold mx-auto mb-3 shadow-lg fs-2 d-flex align-items-center justify-content-center"
                        style="width: 120px; height: 120px; border-radius: 50%;">
                        <?php echo strtoupper(substr($display_data['full_name'] ?? 'U', 0, 1)); ?>
                    </div>
                <?php endif; ?>

                <h3 class="text-white fw-bold mb-1 position-relative z-1">
                    <?php echo htmlspecialchars($display_data['full_name']); ?></h3>
                <p class="text-white-50 mb-0 position-relative z-1">
                    <?php
                    $is_verified = ($is_family_member || ($record['id'] ?? 0) > 0 || (($linked_resident['verification_status'] ?? '') === 'verified'));
                    ?>
                    <span class="badge <?php echo $is_verified ? 'bg-success' : 'bg-danger'; ?>">
                        <?php echo $is_verified ? 'Verified' : 'Unverified'; ?>
                    </span>
                </p>
            </div>

            <div class="card-body p-5">
                <div class="row g-4">
                    <h6 class="text-uppercase text-muted fw-bold small mb-3 col-12">
                        Personal Information <?php echo $is_family_member ? '<span class="badge bg-secondary ms-2">Family Member</span>' : ''; ?>
                    </h6>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">First Name</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php echo htmlspecialchars(empty($display_data['first_name']) ? '-' : $display_data['first_name']); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Last Name</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php echo htmlspecialchars(empty($display_data['last_name']) ? '-' : $display_data['last_name']); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Middle Name</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php echo htmlspecialchars(empty($display_data['middle_name']) ? '-' : $display_data['middle_name']); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Suffix</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php echo htmlspecialchars(empty($display_data['suffix']) ? '-' : $display_data['suffix']); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Full Name</label>
                        <div class="form-control-plaintext border-bottom pb-2 fw-bold">
                            <?php echo htmlspecialchars($display_data['full_name'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Email Address</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php echo htmlspecialchars(empty($display_data['email']) ? '-' : $display_data['email']); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Phone Number</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php echo htmlspecialchars(empty($display_data['phone']) ? '-' : $display_data['phone']); ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted small">Birth Date</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php echo empty($display_data['birthdate']) ? '-' : date('F j, Y', strtotime($display_data['birthdate'])); ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted small">Gender</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php echo htmlspecialchars(empty($display_data['sex']) ? '-' : $display_data['sex']); ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted small">Citizenship</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php echo htmlspecialchars(empty($display_data['citizenship']) ? '-' : $display_data['citizenship']); ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Civil Status</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php echo htmlspecialchars(empty($display_data['civil_status']) ? '-' : $display_data['civil_status']); ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Religion</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php
                            $religion = !empty($display_data['religion']) ? $display_data['religion'] : ($is_family_member ? null : ($linked_resident['religion'] ?? null));
                            echo $religion ? htmlspecialchars($religion) : '-';
                            ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Profession / Occupation</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php
                            $occupation = !empty($display_data['occupation']) ? $display_data['occupation'] : ($is_family_member ? null : ($linked_resident['occupation'] ?? null));
                            echo $occupation ? htmlspecialchars($occupation) : '-';
                            ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Educational Attainment</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php
                            $edu = !empty($display_data['educational_attainment']) ? $display_data['educational_attainment'] : ($is_family_member ? null : ($linked_resident['educational_attainment'] ?? null));
                            echo $edu ? htmlspecialchars($edu) : '-';
                            ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">PhilSys Card No.</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php
                            $philsys = !empty($display_data['philsys_card_no']) ? $display_data['philsys_card_no'] : ($is_family_member ? null : ($linked_resident['philsys_card_no'] ?? null));
                            echo $philsys ? htmlspecialchars($philsys) : '-';
                            ?>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold text-muted small">Special Classification</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php
                            // Try JSON classification first
                            $cls_json = !empty($display_data['classification']) ? $display_data['classification'] : ($is_family_member ? null : ($linked_resident['classification'] ?? null));
                            $classes = [];
                            if ($cls_json) {
                                $decoded = json_decode($cls_json, true);
                                if (is_array($decoded)) $classes = $decoded;
                            }
                            // Fallback to legacy columns
                            if (empty($classes)) {
                                if (!empty($display_data['is_solo_parent'])) $classes[] = 'Solo Parent';
                                if (!empty($display_data['is_pwd'])) $classes[] = 'PWD';
                                if (!empty($display_data['is_senior'])) $classes[] = 'Senior Citizen';
                            }
                            $badge_colors = [
                                'PWD' => 'bg-primary', 'Solo Parent' => 'bg-info',
                                'Senior Citizen' => 'bg-warning text-dark', 'OFW' => 'bg-success',
                                'Out of School Youth (OSY)' => 'bg-danger', 'Out of School Children (OSC)' => 'bg-danger',
                                'Labor/Employed' => 'bg-teal', 'Unemployed' => 'bg-secondary',
                                'Indigenous People' => 'bg-dark',
                            ];
                            if (empty($classes)) {
                                echo '<span class="text-muted">None</span>';
                            } else {
                                foreach ($classes as $cls) {
                                    $color = $badge_colors[$cls] ?? 'bg-secondary';
                                    echo '<span class="badge ' . $color . ' me-1 mb-1">' . htmlspecialchars($cls) . '</span>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold text-muted small">Complete Address</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php
                            // Prioritize the user's profile address over the resident record
                            $address = !empty($linked_resident['address']) ? $linked_resident['address'] : ($record['address'] ?? null);
                            echo $address ? htmlspecialchars($address) : '-';
                            ?>
                        </div>
                    </div>

                    <h6 class="text-uppercase text-muted fw-bold small mb-3 col-12 mt-4">Barangay Records</h6>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Barangay ID</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php
                            $barangay_id = $record['barangay_id'] ?? ($linked_resident['barangay_id'] ?? null);
                            echo $barangay_id ? htmlspecialchars($barangay_id) : '-';
                            ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Purok</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php
                            $purok = $record['purok'] ?? ($linked_resident['purok'] ?? null);
                            echo $purok ? htmlspecialchars($purok) : '-';
                            ?>
                        </div>
                    </div>

                    <h6 class="text-uppercase text-muted fw-bold small mb-3 col-12 mt-4">Account Information</h6>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Account Status</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php if ($linked_user): ?>
                                <span class="badge bg-success"><i class="fas fa-check-circle"></i> Registered Account</span>
                                <?php
                                $vs = $linked_resident['verification_status'] ?? 'pending';
                                $vs_map = [
                                    'verified' => ['badge' => 'bg-success', 'icon' => 'fa-id-badge', 'label' => 'Verified'],
                                    'pending' => ['badge' => 'bg-warning text-dark', 'icon' => 'fa-clock', 'label' => 'ID Pending'],
                                    'rejected' => ['badge' => 'bg-danger', 'icon' => 'fa-times-circle', 'label' => 'ID Rejected'],
                                ];
                                $vsm = $vs_map[$vs] ?? $vs_map['pending'];
                                ?>
                                <span class="badge <?php echo $vsm['badge']; ?> ms-1">
                                    <i class="fas <?php echo $vsm['icon']; ?> me-1"></i><?php echo $vsm['label']; ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning"><i class="fas fa-exclamation-triangle"></i> No Account</span>
                                <span class="ms-2 text-muted small">This resident has not registered an account yet.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($linked_user): ?>
                        <div class="col-12 mt-2">
                            <?php
                            $verification_status = $linked_resident['verification_status'] ?? 'pending';
                            $res_id_for_action = $linked_resident['id'] ?? 0;
                            $vs_colors = [
                                'verified' => ['bg' => '#d1fae5', 'border' => '#10b981', 'text' => '#065f46', 'badge' => 'bg-success'],
                                'pending' => ['bg' => '#fef3c7', 'border' => '#f59e0b', 'text' => '#92400e', 'badge' => 'bg-warning text-dark'],
                                'rejected' => ['bg' => '#fee2e2', 'border' => '#ef4444', 'text' => '#7f1d1d', 'badge' => 'bg-danger'],
                            ];
                            $vs_icons = ['verified' => 'fa-check-circle', 'pending' => 'fa-clock', 'rejected' => 'fa-times-circle'];
                            $vc = $vs_colors[$verification_status] ?? $vs_colors['pending'];
                            $vi = $vs_icons[$verification_status] ?? 'fa-question-circle';
                            $vs_labels = ['verified' => 'This resident\'s ID has been verified.', 'pending' => 'Waiting for admin review of uploaded ID.', 'rejected' => 'This resident\'s ID verification was rejected.'];
                            ?>
                            <div class="rounded-3 p-3"
                                style="background:<?php echo $vc['bg']; ?>; border: 1.5px solid <?php echo $vc['border']; ?>;">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                    <!-- Status Info -->
                                    <div class="d-flex align-items-center gap-3">
                                        <div style="font-size:2rem; color:<?php echo $vc['border']; ?>;">
                                            <i class="fas <?php echo $vi; ?>"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold" style="color:<?php echo $vc['text']; ?>; font-size:1rem;">
                                                ID Verification — <?php echo ucfirst($verification_status); ?>
                                            </div>
                                            <div class="small" style="color:<?php echo $vc['text']; ?>; opacity:0.8;">
                                                <?php echo $vs_labels[$verification_status]; ?>
                                            </div>
                                            <?php if (!empty($linked_resident['verified_at'])): ?>
                                                <div class="small mt-1" style="color:<?php echo $vc['text']; ?>; opacity:0.7;">
                                                    <i class="fas fa-calendar-check me-1"></i>
                                                    Last updated:
                                                    <?php echo date('F j, Y g:i A', strtotime($linked_resident['verified_at'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($verification_status === 'rejected' && !empty($linked_resident['verification_notes'])): ?>
                                                <div class="small mt-1 fw-semibold" style="color:<?php echo $vc['text']; ?>;">
                                                    <i class="fas fa-comment-alt me-1"></i>Reason:
                                                    <?php echo htmlspecialchars($linked_resident['verification_notes']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <!-- Action Buttons -->
                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <?php if (!empty($linked_resident['id_front_path']) || !empty($linked_resident['id_back_path']) || !empty($linked_resident['id_document_path'])): ?>
                                            <div class="mb-2 w-100">
                                                <button type="button" class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#viewIDModal">
                                                    <i class="fas fa-eye me-1"></i> View ID Details
                                                </button>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($res_id_for_action > 0): ?>
                                            <div class="w-100 d-flex gap-2">
                                                <?php if ($verification_status !== 'verified'): ?>
                                                    <form method="post" class="d-inline admin-confirm-form"
                                                        data-action-name="Verify Resident">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action" value="verify">
                                                        <input type="hidden" name="resident_id"
                                                            value="<?php echo $res_id_for_action; ?>">
                                                        <button type="button" class="btn btn-success btn-sm btn-confirm-submit"
                                                            title="Verify">
                                                            <i class="fas fa-check me-1"></i>
                                                            <?php echo $verification_status === 'rejected' ? 'Re-verify' : 'Verify'; ?>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-danger btn-sm"
                                                    onclick="document.getElementById('reject_resident_id').value=<?php echo $res_id_for_action; ?>;new bootstrap.Modal(document.getElementById('rejectModal')).show()">
                                                    <i class="fas fa-times me-1"></i>
                                                    <?php echo $verification_status === 'rejected' ? 'Re-reject' : 'Reject'; ?>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mt-2">
                            <label class="form-label fw-semibold text-muted small">Account Created</label>
                            <div class="form-control-plaintext border-bottom pb-2">
                                <?php echo date('F j, Y g:i A', strtotime($linked_user['user_created_at'])); ?></div>
                        </div>
                    <?php endif; ?>

                    <h6 class="text-uppercase text-muted fw-bold small mb-3 col-12 mt-4">System Information</h6>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Record Status</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php
                            $is_verified = ($is_family_member || ($record['id'] ?? 0) > 0 || (($linked_resident['verification_status'] ?? '') === 'verified'));
                            ?>
                            <span class="badge <?php echo $is_verified ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $is_verified ? 'Verified' : 'Unverified'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Created By</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php echo htmlspecialchars($record['created_by_name'] ?? 'Unknown'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Created At</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php echo date('F j, Y g:i A', strtotime($record['created_at'])); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Updated At</label>
                        <div class="form-control-plaintext border-bottom pb-2">
                            <?php echo $record['updated_at'] ? date('F j, Y g:i A', strtotime($record['updated_at'])) : '-'; ?>
                        </div>
                    </div>
                </div>

                <?php if ($linked_user && !$is_family_member): ?>
                    <!-- Related Information Section -->
                    <div class="row g-4 mt-2">
                        <?php if (!empty($all_requests)): ?>
                            <div class="col-12">
                                <h6 class="text-uppercase text-muted fw-bold small mb-3">Document Requests
                                    (<?php echo count($all_requests); ?>)</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Type</th>
                                                <th>Purpose</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($all_requests as $req): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo htmlspecialchars($req['type']); ?>
                                                        <?php if ($req['detail']): ?>
                                                            <div class="text-muted small">
                                                                <?php echo htmlspecialchars($req['detail']); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($req['purpose'] ?? '-'); ?></td>
                                                    <td>
                                                        <span class="badge <?php
                                                        $status = $req['status'] ?? 'pending';
                                                        echo ($status === 'approved') ? 'bg-success' : (($status === 'rejected') ? 'bg-danger' : (($status === 'released') ? 'bg-info' : 'bg-warning'));
                                                        ?>">
                                                            <?php echo ucfirst($status); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($req['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($incidents)): ?>
                            <div class="col-12">
                                <h6 class="text-uppercase text-muted fw-bold small mb-3">Incident Reports
                                    (<?php echo count($incidents); ?>)</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Description</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($incidents as $inc): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars(substr($inc['description'], 0, 50)) . (strlen($inc['description']) > 50 ? '...' : ''); ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?php
                                                        $status = $inc['status'] ?? 'submitted';
                                                        echo ($status === 'resolved') ? 'bg-success' : (($status === 'in_review') ? 'bg-info' : 'bg-warning');
                                                        ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $inc['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($inc['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($family_members)): ?>
                            <div class="col-12 mt-2">
                                <h6 class="text-uppercase text-muted fw-bold small mb-3">
                                    <i class="fas fa-users me-1"></i>Family Members (<?php echo count($family_members); ?>)
                                </h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Full Name</th>
                                                <th>Relationship</th>
                                                <th>Birthdate / Age</th>
                                                <th>Sex</th>
                                                <th>Civil Status</th>
                                                <th>Classification</th>
                                                <th>Proof of ID</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($family_members as $fm): ?>
                                                <?php
                                                $fm_age = null;
                                                $fm_is_minor = false;
                                                if (!empty($fm['birthdate'])) {
                                                    $fm_age = (new DateTime($fm['birthdate']))->diff(new DateTime())->y;
                                                }
                                                $fm_is_minor = ($fm['is_minor'] == 1 || ($fm_age !== null && $fm_age < 18));
                                                ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($fm['full_name']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($fm['relationship']); ?></td>
                                                    <td>
                                                        <?php if ($fm['birthdate']): ?>
                                                            <?php echo date('M d, Y', strtotime($fm['birthdate'])); ?><br>
                                                            <small class="text-muted">
                                                                (<?php echo $fm_age; ?>
                                                                yrs<?php if ($fm_is_minor)
                                                                    echo ' &mdash; <span class="text-danger fw-bold">Minor</span>'; ?>)
                                                            </small>
                                                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($fm['sex'] ?? '—'); ?></td>
                                                    <td><?php echo htmlspecialchars($fm['civil_status'] ?? '—'); ?></td>
                                                    <td>
                                                        <?php if ($fm_is_minor): ?><span
                                                                class="badge bg-danger me-1">Minor</span><?php endif; ?>
                                                        <?php if ($fm['is_pwd']): ?><span
                                                                class="badge bg-info me-1">PWD</span><?php endif; ?>
                                                        <?php if ($fm['is_senior']): ?><span
                                                                class="badge bg-warning text-dark me-1">Senior</span><?php endif; ?>
                                                        <?php if ($fm['is_solo_parent']): ?><span
                                                                class="badge bg-purple me-1">Solo Parent</span><?php endif; ?>
                                                        <?php if (!$fm_is_minor && !$fm['is_pwd'] && !$fm['is_senior'] && !$fm['is_solo_parent']): ?><span
                                                                class="text-muted">—</span><?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($fm['id_photo_path'])): ?>
                                                            <a href="../<?php echo htmlspecialchars($fm['id_photo_path']); ?>"
                                                                target="_blank" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-id-card me-1"></i>View ID
                                                            </a>
                                                        <?php else: ?><span class="text-muted small">None</span><?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <button class="btn btn-sm btn-outline-primary shadow-sm" title="Edit Member" onclick='editFamilyMember(<?php echo htmlspecialchars(json_encode($fm), ENT_QUOTES, "UTF-8"); ?>)'>
                                                            <i class="fas fa-pen"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($is_family_member): ?>
                <div class="mt-4 pt-4 border-top">
                    <h6 class="text-uppercase text-muted fw-bold small mb-3">
                        <i class="fas fa-home me-2"></i>Household Head Information
                    </h6>
                    <div class="bg-light p-4 rounded-3 border">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-muted small mb-1">Full Name</label>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($edit_data['full_name'] ?? '-'); ?></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-muted small mb-1">Phone Number</label>
                                <div class="text-dark"><?php echo htmlspecialchars(empty($edit_data['phone']) ? '-' : $edit_data['phone']); ?></div>
                            </div>
                            <div class="col-md-4 d-flex align-items-center">
                                <a href="resident_record_view.php?id=<?php echo $record_id; ?>&user_id=<?php echo $user_id; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i>View Household Profile
                                </a>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold text-muted small mb-1">Complete Address</label>
                                <div class="text-dark">
                                    <?php 
                                    $head_address = !empty($linked_resident['address']) ? $linked_resident['address'] : ($record['address'] ?? null);
                                    echo htmlspecialchars($head_address ?? '-'); 
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between mt-5 pt-4 border-top">
                    <a href="<?php echo $back_url; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to <?php echo $back_label; ?>
                    </a>
                    <?php if ($is_family_member): ?>
                        <button class="btn btn-primary" onclick='editFamilyMember(<?php echo htmlspecialchars(json_encode($view_fm), ENT_QUOTES, "UTF-8"); ?>)'>
                            <i class="fas fa-user-edit me-2"></i> Edit Family Member
                        </button>
                    <?php else: ?>
                        <button class="btn btn-primary"
                            onclick='editRecord(<?php echo htmlspecialchars(json_encode($edit_data), ENT_QUOTES, "UTF-8"); ?>)'>
                            <i class="fas fa-edit me-2"></i> Edit Record
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>Reject Verification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="resident_id" id="reject_resident_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason *</label>
                        <textarea name="rejection_notes" class="form-control" rows="4"
                            placeholder="Please provide a reason for rejection..." required></textarea>
                        <div class="form-text">This message will be sent to the resident by email.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times me-1"></i>Reject
                        Verification</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal (same as in resident_records.php) -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Resident Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="resident_records?redirect=view&id=<?php echo $record_id; ?>&user_id=<?php echo $user_id; ?>" id="editForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="record_id" id="edit_record_id">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <h6 class="text-uppercase text-muted fw-bold small mb-3 col-12">Personal Information</h6>

                        <div class="col-md-6">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middle_name" id="edit_middle_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Suffix</label>
                            <input type="text" name="suffix" id="edit_suffix" class="form-control"
                                placeholder="e.g. Jr., Sr., III">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" id="edit_phone" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Birth Date</label>
                            <input type="date" name="birthdate" id="edit_birthdate" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Gender</label>
                            <select name="sex" id="edit_sex" class="form-control">
                                <option value="">Select...</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Citizenship</label>
                            <input type="text" name="citizenship" id="edit_citizenship" class="form-control"
                                placeholder="e.g. Filipino">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Civil Status</label>
                            <select name="civil_status" id="edit_civil_status" class="form-control">
                                <option value="">Select...</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Widowed">Widowed</option>
                                <option value="Divorced">Divorced</option>
                                <option value="Separated">Separated</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Religion</label>
                            <input type="text" name="religion" id="edit_religion" class="form-control" placeholder="e.g. Roman Catholic">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Profession / Occupation</label>
                            <input type="text" name="occupation" id="edit_occupation" class="form-control" placeholder="e.g. Teacher, Farmer">
                        </div>
                        <div class="col-12 mt-2">
                            <div class="row g-3">
                                <div class="col-md-6 border-end pe-4">
                                    <label class="form-label text-uppercase small fw-bold text-muted">Highest Educational Attainment</label>
                                    <div class="d-flex flex-column gap-2" id="edit_edu_base_options">
                                        <?php
                                        $edu_opts = ['Elementary', 'High School', 'College', 'Post Grad', 'Vocational'];
                                        foreach ($edu_opts as $opt):
                                            ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="educational_attainment"
                                                    value="<?php echo $opt; ?>" id="edit_edu_<?php echo str_replace(' ','_',$opt); ?>">
                                                <label class="form-check-label"
                                                    for="edit_edu_<?php echo str_replace(' ','_',$opt); ?>"><?php echo $opt; ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-6 ps-4">
                                    <label class="form-label text-uppercase small fw-bold text-muted">Please Specify</label>
                                    <div class="d-flex flex-column gap-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="edu_status"
                                                value="Graduate" id="edit_gradY">
                                            <label class="form-check-label" for="edit_gradY">Graduate</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="edu_status"
                                                value="Under Graduate" id="edit_gradN">
                                            <label class="form-check-label" for="edit_gradN">Under Graduate</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-uppercase small fw-bold text-muted">Complete Address *</label>
                            <input type="text" name="address" id="edit_address" class="form-control" required 
                                placeholder="e.g. 106, Panungyanan, City of General Trias, Cavite">
                            <div class="form-text small">Please include House No., Street, Barangay, etc.</div>
                        </div>

                        <h6 class="text-uppercase text-muted fw-bold small mb-3 col-12 mt-3">Barangay Records</h6>

                        <div class="col-md-6">
                            <label class="form-label">Barangay ID</label>
                            <input type="text" name="barangay_id" id="edit_barangay_id" class="form-control" placeholder="Resident ID Number" readonly style="background-color: #f0f0f0; cursor: not-allowed;">
                            <div class="form-text small" style="font-size: 0.7rem;">System-generated ID. Cannot be modified.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Purok</label>
                            <input type="text" name="purok" id="edit_purok" class="form-control" placeholder="e.g. Purok 1, Purok 2">
                        </div>

                        <h6 class="text-uppercase text-muted fw-bold small mb-3 col-12 mt-3">Special Classification (Multiple Selection)</h6>
                        <div class="col-12">
                            <div class="row g-2">
                                <div class="col-md-4"><div class="form-check p-2 border rounded-2 h-100 d-flex align-items-center"><input class="form-check-input ms-0 me-2 edit-cls-check" type="checkbox" name="classifications[]" id="ecls_0" value="Labor/Employed"><label class="form-check-label small fw-semibold" for="ecls_0">Labor/Employed</label></div></div>
                                <div class="col-md-4"><div class="form-check p-2 border rounded-2 h-100 d-flex align-items-center"><input class="form-check-input ms-0 me-2 edit-cls-check" type="checkbox" name="classifications[]" id="ecls_1" value="Unemployed"><label class="form-check-label small fw-semibold" for="ecls_1">Unemployed</label></div></div>
                                <div class="col-md-4"><div class="form-check p-2 border rounded-2 h-100 d-flex align-items-center"><input class="form-check-input ms-0 me-2 edit-cls-check" type="checkbox" name="classifications[]" id="ecls_2" value="PWD"><label class="form-check-label small fw-semibold" for="ecls_2">PWD</label></div></div>
                                <div class="col-md-4"><div class="form-check p-2 border rounded-2 h-100 d-flex align-items-center"><input class="form-check-input ms-0 me-2 edit-cls-check" type="checkbox" name="classifications[]" id="ecls_3" value="OFW"><label class="form-check-label small fw-semibold" for="ecls_3">OFW</label></div></div>
                                <div class="col-md-4"><div class="form-check p-2 border rounded-2 h-100 d-flex align-items-center"><input class="form-check-input ms-0 me-2 edit-cls-check" type="checkbox" name="classifications[]" id="ecls_4" value="Solo Parent"><label class="form-check-label small fw-semibold" for="ecls_4">Solo Parent</label></div></div>
                                <div class="col-md-4"><div class="form-check p-2 border rounded-2 h-100 d-flex align-items-center"><input class="form-check-input ms-0 me-2 edit-cls-check" type="checkbox" name="classifications[]" id="ecls_5" value="Out of School Youth (OSY)"><label class="form-check-label small fw-semibold" for="ecls_5">OSY</label></div></div>
                                <div class="col-md-4"><div class="form-check p-2 border rounded-2 h-100 d-flex align-items-center"><input class="form-check-input ms-0 me-2 edit-cls-check" type="checkbox" name="classifications[]" id="ecls_6" value="Out of School Children (OSC)"><label class="form-check-label small fw-semibold" for="ecls_6">OSC</label></div></div>
                                <div class="col-md-4"><div class="form-check p-2 border rounded-2 h-100 d-flex align-items-center"><input class="form-check-input ms-0 me-2 edit-cls-check" type="checkbox" name="classifications[]" id="ecls_7" value="Indigenous People"><label class="form-check-label small fw-semibold" for="ecls_7">Indigenous</label></div></div>
                                <div class="col-md-4"><div class="form-check p-2 border rounded-2 h-100 d-flex align-items-center"><input class="form-check-input ms-0 me-2 edit-cls-check" type="checkbox" name="classifications[]" id="ecls_8" value="Senior Citizen"><label class="form-check-label small fw-semibold" for="ecls_8">Senior Citizen</label></div></div>
                            </div>
                        </div>

                        <div class="col-12 mt-2">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" id="edit_is_active" class="form-check-input" value="1">
                                <label class="form-check-label fw-bold" for="edit_is_active">Record Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<!-- Detailed Family Member Edit Modal -->
<div class="modal fade" id="familyMemberEditModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="fmEditModalTitle"><i class="fas fa-user-edit me-2 text-primary"></i>Update Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="adminFamilyEditForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit_family_member">
                <input type="hidden" name="fm_id" id="fm_edit_id">
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <!-- Personal Info Section -->
                        <div class="col-lg-12">
                            <h6 class="text-teal-700 fw-bold border-bottom pb-2 mb-3 small text-uppercase letter-spacing-1"><i class="fas fa-id-card me-2"></i>Personal Identification</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label rbi-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="fm_first_name" id="fm_edit_first_name" class="form-control rbi-input" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label rbi-label">Middle Name</label>
                                    <input type="text" name="fm_middle_name" id="fm_edit_middle_name" class="form-control rbi-input">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label rbi-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" name="fm_last_name" id="fm_edit_last_name" class="form-control rbi-input" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label rbi-label">Suffix (Jr, III, etc)</label>
                                    <input type="text" name="fm_suffix" id="fm_edit_suffix" class="form-control rbi-input">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label rbi-label">Relationship to Head <span class="text-danger">*</span></label>
                                    <select name="fm_relationship" id="fm_edit_relationship" class="form-select rbi-input" required>
                                        <option value="Spouse">Spouse</option>
                                        <option value="Child">Child</option>
                                        <option value="Parent">Parent</option>
                                        <option value="Sibling">Sibling</option>
                                        <option value="Grandparent">Grandparent</option>
                                        <option value="Grandchild">Grandchild</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label rbi-label">PhilSys Card Number</label>
                                    <input type="text" name="fm_philsys_card_no" id="fm_edit_philsys" class="form-control rbi-input" placeholder="####-####-####-####">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label rbi-label">Citizenship</label>
                                    <input type="text" name="fm_citizenship" id="fm_edit_citizenship" class="form-control rbi-input">
                                </div>
                            </div>
                        </div>

                        <!-- Demographics Section -->
                        <div class="col-lg-12">
                            <h6 class="text-teal-700 fw-bold border-bottom pb-2 mb-3 small text-uppercase letter-spacing-1"><i class="fas fa-map-marker-alt me-2"></i>Demographics & Status</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label rbi-label">Birthdate <span class="text-danger">*</span></label>
                                    <input type="date" name="fm_birthdate" id="fm_edit_birthdate" class="form-control rbi-input" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label rbi-label">Sex <span class="text-danger">*</span></label>
                                    <select name="fm_sex" id="fm_edit_sex" class="form-select rbi-input" required>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label rbi-label">Civil Status <span class="text-danger">*</span></label>
                                    <select name="fm_civil_status" id="fm_edit_civil_status" class="form-select rbi-input" required>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Widowed">Widowed</option>
                                        <option value="Separated">Separated</option>
                                        <option value="Annulled">Annulled</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label rbi-label">Religion</label>
                                    <input type="text" name="fm_religion" id="fm_edit_religion" class="form-control rbi-input">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label rbi-label">Place of Birth <span class="text-danger">*</span></label>
                                    <input type="text" name="fm_birth_place" id="fm_edit_birth_place" class="form-control rbi-input" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label rbi-label">Occupation</label>
                                    <input type="text" name="fm_occupation" id="fm_edit_occupation" class="form-control rbi-input">
                                </div>
                            </div>
                        </div>

                        <!-- Education Section -->
                        <div class="col-lg-12">
                            <h6 class="text-teal-700 fw-bold border-bottom pb-2 mb-3 small text-uppercase letter-spacing-1"><i class="fas fa-graduation-cap me-2"></i>Educational Background</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label rbi-label">Highest Educational Attainment</label>
                                    <select name="fm_educational_attainment" id="fm_edit_educational_attainment" class="form-select rbi-input">
                                        <option value="">-- SELECT --</option>
                                        <option value="No Formal Education">No Formal Education</option>
                                        <option value="Elementary Level">Elementary Level</option>
                                        <option value="Elementary Graduate">Elementary Graduate</option>
                                        <option value="High School Level">High School Level</option>
                                        <option value="High School Graduate">High School Graduate</option>
                                        <option value="Vocational">Vocational</option>
                                        <option value="College Level">College Level</option>
                                        <option value="College Graduate">College Graduate</option>
                                        <option value="Post Graduate">Post Graduate</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label rbi-label">Current Enrollment Status</label>
                                    <select name="fm_educational_status" id="fm_edit_educational_status" class="form-select rbi-input">
                                        <option value="N/A">N/A</option>
                                        <option value="Enrolled">Currently Enrolled</option>
                                        <option value="Not Enrolled">Not Enrolled</option>
                                        <option value="Graduated">Graduated/Finished</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Classifications Section -->
                        <div class="col-lg-12">
                            <h6 class="text-teal-700 fw-bold border-bottom pb-2 mb-3 small text-uppercase letter-spacing-1"><i class="fas fa-tags me-2"></i>Special Classifications</h6>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <div class="form-check border p-2 rounded">
                                        <input class="form-check-input ms-0 me-2" type="checkbox" name="fm_is_pwd" id="fm_edit_is_pwd">
                                        <label class="form-check-label fw-bold small" for="fm_edit_is_pwd">PWD</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check border p-2 rounded">
                                        <input class="form-check-input ms-0 me-2" type="checkbox" name="fm_is_senior" id="fm_edit_is_senior">
                                        <label class="form-check-label fw-bold small" for="fm_edit_is_senior">Senior</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check border p-2 rounded">
                                        <input class="form-check-input ms-0 me-2" type="checkbox" name="fm_is_minor" id="fm_edit_is_minor">
                                        <label class="form-check-label fw-bold small" for="fm_edit_is_minor">Minor</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check border p-2 rounded">
                                        <input class="form-check-input ms-0 me-2" type="checkbox" name="fm_is_solo_parent" id="fm_edit_is_solo_parent">
                                        <label class="form-check-label fw-bold small" for="fm_edit_is_solo_parent">Solo Parent</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 p-4 bg-light-subtle rounded-bottom-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Discard</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 shadow-sm fw-bold">Save Member Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.rbi-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 5px; }
.rbi-input { border: 1px solid #e2e8f0; font-size: 0.9rem; padding: 0.6rem 0.75rem; border-radius: 8px; transition: all 0.2s; }
</style>

<script>
    const PSGC_API = 'https://psgc.gitlab.io/api';

    function setupAddressDropdowns(provSelectId, munSelectId, brgySelectId) {
        const provSelect = document.getElementById(provSelectId);
        const munSelect = document.getElementById(munSelectId);
        const brgySelect = document.getElementById(brgySelectId);

        // Load provinces
        fetch(`${PSGC_API}/provinces/`)
            .then(res => res.json())
            .then(data => {
                data.sort((a, b) => a.name.localeCompare(b.name));
                provSelect.innerHTML = '<option value="">Select Province</option>';
                data.forEach(prov => {
                    const opt = document.createElement('option');
                    opt.value = prov.name;
                    opt.textContent = prov.name;
                    opt.dataset.code = prov.code;
                    provSelect.appendChild(opt);
                });
                if (provSelect.dataset.selected) {
                    provSelect.value = provSelect.dataset.selected;
                    provSelect.dispatchEvent(new Event('change'));
                }
            });

        provSelect.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            if (!selected || !selected.dataset.code) {
                munSelect.innerHTML = '<option value="">Select Municipality</option>';
                munSelect.disabled = true;
                brgySelect.innerHTML = '<option value="">Select Barangay</option>';
                brgySelect.disabled = true;
                return;
            }

            const code = selected.dataset.code;
            munSelect.innerHTML = '<option value="">Loading...</option>';
            munSelect.disabled = true;
            brgySelect.innerHTML = '<option value="">Select Barangay</option>';
            brgySelect.disabled = true;

            fetch(`${PSGC_API}/provinces/${code}/cities-municipalities/`)
                .then(res => res.json())
                .then(data => {
                    munSelect.innerHTML = '<option value="">Select Municipality</option>';
                    data.sort((a, b) => a.name.localeCompare(b.name));
                    data.forEach(mun => {
                        const opt = document.createElement('option');
                        opt.value = mun.name;
                        opt.textContent = mun.name;
                        opt.dataset.code = mun.code;
                        munSelect.appendChild(opt);
                    });
                    munSelect.disabled = false;

                    if (munSelect.dataset.selected) {
                        munSelect.value = munSelect.dataset.selected;
                        munSelect.dispatchEvent(new Event('change'));
                    }
                });
        });

        munSelect.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            if (!selected || !selected.dataset.code) {
                brgySelect.innerHTML = '<option value="">Select Barangay</option>';
                brgySelect.disabled = true;
                return;
            }

            const code = selected.dataset.code;
            brgySelect.innerHTML = '<option value="">Loading...</option>';
            brgySelect.disabled = true;

            fetch(`${PSGC_API}/cities-municipalities/${code}/barangays/`)
                .then(res => res.json())
                .then(data => {
                    brgySelect.innerHTML = '<option value="">Select Barangay</option>';
                    data.sort((a, b) => a.name.localeCompare(b.name));
                    data.forEach(brgy => {
                        const opt = document.createElement('option');
                        opt.value = brgy.name;
                        opt.textContent = brgy.name;
                        brgySelect.appendChild(opt);
                    });
                    brgySelect.disabled = false;

                    if (brgySelect.dataset.selected) {
                        brgySelect.value = brgySelect.dataset.selected;
                    }
                });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        setupAddressDropdowns('edit_province', 'edit_municipality', 'edit_barangay');
    });

    function editRecord(record) {
        document.getElementById('edit_record_id').value = record.id || 0;
        document.getElementById('edit_user_id').value = record.user_id || 0;
        document.getElementById('edit_first_name').value = record.first_name || '';
        document.getElementById('edit_last_name').value = record.last_name || '';
        document.getElementById('edit_middle_name').value = record.middle_name || '';
        document.getElementById('edit_suffix').value = record.suffix || '';
        document.getElementById('edit_email').value = record.email || '';

        document.getElementById('edit_address').value = record.address || '';

        document.getElementById('edit_phone').value = record.phone || '';
        document.getElementById('edit_birthdate').value = record.birthdate || '';
        document.getElementById('edit_sex').value = record.sex || '';
        document.getElementById('edit_citizenship').value = record.citizenship || '';
        document.getElementById('edit_civil_status').value = record.civil_status || '';
        document.getElementById('edit_religion').value = record.religion || '';
        document.getElementById('edit_occupation').value = record.occupation || '';
        const eduStr = record.educational_attainment || '';
        const baseEdu = eduStr.split(' (')[0].trim();
        const isGrad = eduStr.toLowerCase().includes('(graduate)');
        const isUnder = eduStr.toLowerCase().includes('(under graduate)');

        const eduRadios = document.querySelectorAll('input[name="educational_attainment"]');
        eduRadios.forEach(radio => {
            radio.checked = (radio.value === baseEdu);
        });

        document.getElementById('edit_gradY').checked = isGrad;
        document.getElementById('edit_gradN').checked = isUnder;
        document.getElementById('edit_barangay_id').value = record.barangay_id || '';
        document.getElementById('edit_purok').value = record.purok || '';
        document.getElementById('edit_is_active').checked = record.is_active == 1;

        // Reset all classification checkboxes
        document.querySelectorAll('.edit-cls-check').forEach(cb => cb.checked = false);

        // Check from JSON classification field
        if (record.classification) {
            try {
                const classes = JSON.parse(record.classification);
                if (Array.isArray(classes)) {
                    document.querySelectorAll('.edit-cls-check').forEach(cb => {
                        if (classes.includes(cb.value)) cb.checked = true;
                    });
                }
            } catch(e) {}
        }

        // Fallback: sync with legacy individual columns
        if (record.is_solo_parent == 1) { const el = document.querySelector('.edit-cls-check[value="Solo Parent"]'); if(el) el.checked = true; }
        if (record.is_pwd == 1) { const el = document.querySelector('.edit-cls-check[value="PWD"]'); if(el) el.checked = true; }
        if (record.is_senior == 1) { const el = document.querySelector('.edit-cls-check[value="Senior Citizen"]'); if(el) el.checked = true; }

        new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    function editFamilyMember(fm) {
        document.getElementById('fm_edit_id').value = fm.id;
        document.getElementById('fm_edit_first_name').value = fm.first_name || '';
        document.getElementById('fm_edit_middle_name').value = fm.middle_name || '';
        document.getElementById('fm_edit_last_name').value = fm.last_name || '';
        document.getElementById('fm_edit_suffix').value = fm.suffix || '';
        
        document.getElementById('fm_edit_relationship').value = fm.relationship || 'Child';
        document.getElementById('fm_edit_philsys').value = fm.philsys_card_no || '';
        document.getElementById('fm_edit_citizenship').value = fm.citizenship || 'FILIPINO';
        
        document.getElementById('fm_edit_birthdate').value = fm.birthdate || '';
        document.getElementById('fm_edit_sex').value = fm.sex || 'Male';
        document.getElementById('fm_edit_civil_status').value = fm.civil_status || 'Single';
        document.getElementById('fm_edit_religion').value = fm.religion || '';
        document.getElementById('fm_edit_birth_place').value = fm.birth_place || '';
        document.getElementById('fm_edit_occupation').value = fm.occupation || '';
        
        document.getElementById('fm_edit_educational_attainment').value = fm.educational_attainment || '';
        document.getElementById('fm_edit_educational_status').value = fm.educational_status || 'N/A';
        document.getElementById('fm_edit_is_pwd').checked = fm.is_pwd == 1;
        document.getElementById('fm_edit_is_senior').checked = fm.is_senior == 1;
        document.getElementById('fm_edit_is_minor').checked = fm.is_minor == 1;
        document.getElementById('fm_edit_is_solo_parent').checked = fm.is_solo_parent == 1;
        
        new bootstrap.Modal(document.getElementById('familyMemberEditModal')).show();
    }
</script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const editForm = document.getElementById('editForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                Swal.fire({
                    title: 'Saving...',
                    text: 'Please wait while your changes are saved.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            });
        }

        const urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.has('updated')) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Resident record updated successfully',
                confirmButtonColor: '#198754'
            }).then(() => {
                urlParams.delete('updated');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, '', newUrl);
            });
        } else if (urlParams.has('msg')) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: urlParams.get('msg'),
                confirmButtonColor: '#0f766e',
                timer: 5000,
                timerProgressBar: true
            }).then(() => {
                urlParams.delete('msg');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, '', newUrl);
            });
        }

        if (urlParams.has('edit')) {
            const fmId = urlParams.get('fm_id');
            if (fmId) {
                const familyMembers = <?php echo json_encode($family_members); ?>;
                const fm = familyMembers.find(f => f.id == fmId);
                if (fm) editFamilyMember(fm);
            } else {
                editRecord(<?php echo json_encode($edit_data); ?>);
            }
        }
    });

    function previewProfileImage(src) {
        Swal.fire({
            html: `<img src="${src}" class="rounded-circle shadow" style="width: 300px; height: 300px; object-fit: cover; border: 5px solid white;">`,
            showConfirmButton: false,
            showCloseButton: true,
            background: 'transparent',
            customClass: {
                popup: 'border-0 shadow-none'
            }
        });
    }
</script>

<!-- Modal for Viewing ID Details -->
<div class="modal fade" id="viewIDModal" tabindex="-1" aria-labelledby="viewIDModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="viewIDModalLabel"><i class="fas fa-id-card me-2 text-primary"></i>Uploaded ID Documents</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4 justify-content-center">
                    <div class="col-12 mb-2">
                        <label class="form-label fw-bold small text-uppercase text-muted mb-1"><i class="fas fa-map-marker-alt me-1"></i> Address as written on ID:</label>
                        <div class="p-3 bg-light rounded-3 border fw-semibold text-dark" style="font-size: 0.95rem;">
                            <?php echo !empty($linked_resident['address_on_id']) ? htmlspecialchars($linked_resident['address_on_id']) : '<i class="text-muted fw-normal">No address provided during upload.</i>'; ?>
                        </div>
                    </div>
                    <?php if (!empty($linked_resident['id_front_path'])): ?>
                        <div class="col-md-6 text-center">
                            <label class="form-label fw-bold small text-uppercase text-muted mb-2">Front ID View</label>
                            <img src="../uploads/id_documents/<?php echo htmlspecialchars($linked_resident['id_front_path']); ?>" 
                                 class="img-fluid rounded border shadow-sm" style="max-height: 400px; cursor: pointer;"
                                 onclick="window.open(this.src, '_blank')">
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($linked_resident['id_back_path'])): ?>
                        <div class="col-md-6 text-center">
                            <label class="form-label fw-bold small text-uppercase text-muted mb-2">Back ID View</label>
                            <img src="../uploads/id_documents/<?php echo htmlspecialchars($linked_resident['id_back_path']); ?>" 
                                 class="img-fluid rounded border shadow-sm" style="max-height: 400px; cursor: pointer;"
                                 onclick="window.open(this.src, '_blank')">
                        </div>
                    <?php endif; ?>
                    <?php if (empty($linked_resident['id_front_path']) && empty($linked_resident['id_back_path']) && !empty($linked_resident['id_document_path'])): ?>
                        <div class="col-12 text-center">
                            <label class="form-label fw-bold small text-uppercase text-muted mb-2">ID Document</label>
                            <img src="../uploads/id_documents/<?php echo htmlspecialchars($linked_resident['id_document_path']); ?>" 
                                 class="img-fluid rounded border shadow-sm" style="max-height: 400px; cursor: pointer;"
                                 onclick="window.open(this.src, '_blank')">
                        </div>
                    <?php endif; ?>
                    <div class="col-12 mt-3 text-center">
                        <div class="alert alert-info border-0 rounded-3 small py-2 mb-0">
                            <i class="fas fa-info-circle me-1"></i> Tip: Click on an image to view it in full size.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
