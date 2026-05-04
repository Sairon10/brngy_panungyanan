<?php
require_once __DIR__ . '/../config.php';
if (!is_admin())
    redirect('../index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents(__DIR__ . '/request_log.txt', date('Y-m-d H:i:s') . " - " . $_SERVER['REQUEST_URI'] . "\nPOST: " . print_r($_POST, true) . "\n", FILE_APPEND);
}

$page_title = 'All resident';
require_once __DIR__ . '/header.php';
?>

<style>
    .action-btn {
        width: 35px;
        height: 35px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: 0.3s;
        text-decoration: none;
    }

    .action-btn:hover {
        background: #f1f5f9;
        transform: translateY(-2px);
    }
</style>

<?php
$pdo = get_db_connection();

$errors = [];
$success = '';

// Handle form submission for adding new resident record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!csrf_validate()) {
        $errors[] = 'Invalid CSRF token. Please refresh the page and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $municipality = trim($_POST['municipality'] ?? '');
        $barangay = trim($_POST['barangay'] ?? '');
        $address = implode(', ', array_filter([$barangay, $municipality, $province]));
        $phone = trim($_POST['phone'] ?? '');
        $birthdate = $_POST['birthdate'] ?? '';
        $sex = $_POST['sex'] ?? '';
        $citizenship = trim($_POST['citizenship'] ?? '');
        $civil_status = trim($_POST['civil_status'] ?? '');
        $purok = trim($_POST['purok'] ?? '');
        $is_solo_parent = isset($_POST['is_solo_parent']) ? 1 : 0;
        $is_pwd = isset($_POST['is_pwd']) ? 1 : 0;
        $is_senior = isset($_POST['is_senior']) ? 1 : 0;
        $barangay_id = trim($_POST['barangay_id'] ?? '');

        // Build full_name from parts
        $name_parts = array_filter([$first_name, $middle_name, $last_name, $suffix]);
        $full_name = implode(' ', $name_parts);

        if ($first_name === '')
            $errors[] = 'First name is required';
        if ($last_name === '')
            $errors[] = 'Last name is required';
        if ($address === '')
            $errors[] = 'Address is required';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Valid email is required';

        if (!$errors) {
            try {
                // Check if full name already exists
                $stmt = $pdo->prepare('SELECT id FROM resident_records WHERE full_name = ? LIMIT 1');
                $stmt->execute([$full_name]);
                if ($stmt->fetch()) {
                    $errors[] = 'Resident with this full name already exists';
                } else {
                    // Check email uniqueness only if provided
                    $emailExists = false;
                    if ($email !== '') {
                        $stmt = $pdo->prepare('SELECT id FROM resident_records WHERE email = ? LIMIT 1');
                        $stmt->execute([$email]);
                        if ($stmt->fetch())
                            $emailExists = true;
                    }

                    if ($emailExists) {
                        $errors[] = 'Email already exists in resident records';
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO resident_records (email, first_name, last_name, middle_name, suffix, full_name, address, phone, birthdate, sex, citizenship, civil_status, purok, is_solo_parent, is_pwd, is_senior, barangay_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                        $stmt->execute([$email ?: null, $first_name, $last_name, $middle_name ?: null, $suffix ?: null, $full_name, $address, $phone ?: null, $birthdate ?: null, $sex ?: null, $citizenship ?: null, $civil_status ?: null, $purok ?: null, $is_solo_parent, $is_pwd, $is_senior, $barangay_id ?: null, $_SESSION['user_id']]);
                        $success = 'Resident record added successfully';
                    }
                }
            } catch (Exception $e) {
                $errors[] = 'Server error: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// Handle form submission for updating resident record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!csrf_validate()) {
        $errors[] = 'Invalid CSRF token. Please refresh the page and try again.';
    } else {
        $record_id = (int) ($_POST['record_id'] ?? 0);
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $email = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $municipality = trim($_POST['municipality'] ?? '');
        $barangay = trim($_POST['barangay'] ?? '');
        $address_parts = array_filter([$barangay, $municipality, $province]);
        $address = !empty($address_parts) ? implode(', ', $address_parts) : trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $birthdate = $_POST['birthdate'] ?? '';
        $sex = $_POST['sex'] ?? '';
        $citizenship = trim($_POST['citizenship'] ?? '');
        $civil_status = trim($_POST['civil_status'] ?? '');
        $purok = trim($_POST['purok'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_solo_parent = isset($_POST['is_solo_parent']) ? 1 : 0;
        $is_pwd = isset($_POST['is_pwd']) ? 1 : 0;
        $is_senior = isset($_POST['is_senior']) ? 1 : 0;
        $barangay_id = trim($_POST['barangay_id'] ?? '');

        // Additional demographic fields
        $religion = trim($_POST['religion'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');

        $edu_base = trim($_POST['educational_attainment'] ?? '');
        $edu_status = trim($_POST['edu_status'] ?? '');
        $educational_attainment = $edu_base . ($edu_status ? " ($edu_status)" : "");
        if (empty(trim($educational_attainment))) {
            $educational_attainment = trim($_POST['educational_attainment_text'] ?? ''); // Fallback
        }

        $philsys_card_no = trim($_POST['philsys_card_no'] ?? '');
        $is_family_head = isset($_POST['is_family_head']) ? 1 : 0;
        $classifications = $_POST['classifications'] ?? [];
        $classification_json = json_encode($classifications);

        // Build full_name from parts
        $name_parts = array_filter([$first_name, $middle_name, $last_name, $suffix]);
        $full_name = implode(' ', $name_parts);

        if ($record_id <= 0 && $user_id <= 0)
            $errors[] = 'Invalid record ID';
        if ($first_name === '')
            $errors[] = 'First name is required';
        if ($last_name === '')
            $errors[] = 'Last name is required';
        if ($address === '')
            $errors[] = 'Address is required';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Valid email is required';

        if (!$errors) {
            try {
                // Check Full Name uniqueness (exclude current record)
                $stmt = $pdo->prepare('SELECT id FROM resident_records WHERE full_name = ? AND id != ? LIMIT 1');
                $stmt->execute([$full_name, $record_id]);
                if ($stmt->fetch()) {
                    $errors[] = 'Another resident record already has this full name';
                } else {
                    // Check Email uniqueness (exclude current record) only if provided
                    $emailExists = false;
                    if ($email !== '') {
                        $stmt = $pdo->prepare('SELECT id FROM resident_records WHERE email = ? AND id != ? LIMIT 1');
                        $stmt->execute([$email, $record_id]);
                        if ($stmt->fetch())
                            $emailExists = true;
                    }

                    if ($emailExists) {
                        $errors[] = 'Email already exists in another resident record';
                    } else {
                        $log_msg = "Starting update for Record ID: $record_id, User ID: $user_id\n";

                        if ($record_id > 0) {
                            $log_msg .= "Updating resident_records table...\n";
                            $stmt = $pdo->prepare('UPDATE resident_records SET email = ?, first_name = ?, last_name = ?, middle_name = ?, suffix = ?, full_name = ?, address = ?, phone = ?, birthdate = ?, sex = ?, citizenship = ?, civil_status = ?, purok = ?, is_active = ?, is_solo_parent = ?, is_pwd = ?, is_senior = ?, barangay_id = ? WHERE id = ?');
                            $stmt->execute([$email ?: null, $first_name, $last_name, $middle_name ?: null, $suffix ?: null, $full_name, $address, $phone ?: null, $birthdate ?: null, $sex ?: null, $citizenship ?: null, $civil_status ?: null, $purok ?: null, $is_active, $is_solo_parent, $is_pwd, $is_senior, $barangay_id ?: null, $record_id]);
                            $success = 'Resident record updated successfully';
                        } else {
                            $log_msg .= "No resident_records ID found, skipping that table.\n";
                            $success = 'Resident account updated successfully';
                        }

                        // Sync back to residents/users tables if a linked account exists
                        $linked_user_id = $user_id > 0 ? $user_id : null;
                        if (!$linked_user_id && $email) {
                            $chk = $pdo->prepare('SELECT u.id FROM users u WHERE u.email = ? AND u.role = "resident" LIMIT 1');
                            $chk->execute([$email]);
                            $lu = $chk->fetch();
                            if ($lu)
                                $linked_user_id = $lu['id'];
                        }
                        if (!$linked_user_id && $full_name) {
                            $chk = $pdo->prepare('SELECT u.id FROM users u WHERE u.full_name = ? AND u.role = "resident" LIMIT 1');
                            $chk->execute([$full_name]);
                            $lu = $chk->fetch();
                            if ($lu)
                                $linked_user_id = $lu['id'];
                        }

                        if ($linked_user_id) {
                            $log_msg .= "Found linked user ID: $linked_user_id. Updating users and residents tables...\n";
                            // Update users table
                            $pdo->prepare('UPDATE users SET first_name=?, last_name=?, middle_name=?, suffix=?, full_name=? WHERE id=?')
                                ->execute([$first_name, $last_name, $middle_name ?: null, $suffix ?: null, $full_name, $linked_user_id]);
                            // Update residents table
                            $pdo->prepare('UPDATE residents SET address=?, phone=?, birthdate=?, sex=?, citizenship=?, civil_status=?, purok=?, is_solo_parent=?, is_pwd=?, is_senior=?, religion=?, occupation=?, educational_attainment=?, educational_status=?, classification=?, barangay_id=? WHERE user_id=?')
                                ->execute([$address, $phone ?: null, $birthdate ?: null, $sex ?: null, $citizenship ?: null, $civil_status ?: null, $purok ?: null, $is_solo_parent, $is_pwd, $is_senior, $religion ?: null, $occupation ?: null, $edu_base, $edu_status, $classification_json, $barangay_id, $linked_user_id]);
                        } else {
                            $log_msg .= "No linked user account found to sync.\n";
                        }

                        file_put_contents(__DIR__ . '/update_log.txt', date('Y-m-d H:i:s') . " - " . $log_msg . "\n", FILE_APPEND);

                        // Redirect to view page if requested
                        if (isset($_GET['redirect']) && $_GET['redirect'] === 'view') {
                            $redir_id = (int) ($_GET['id'] ?? 0);
                            $redir_user_id = (int) ($_GET['user_id'] ?? 0);
                            redirect('resident_record_view.php?id=' . $redir_id . '&user_id=' . $redir_user_id . '&updated=1');
                        }
                    }
                }
            } catch (Exception $e) {
                file_put_contents(__DIR__ . '/debug_errors.txt', date('Y-m-d H:i:s') . " - EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
                $errors[] = 'Server error: ' . htmlspecialchars($e->getMessage());
            }
        }

        if ($errors) {
            file_put_contents(__DIR__ . '/debug_errors.txt', date('Y-m-d H:i:s') . "\nPOST: " . print_r($_POST, true) . "\nERRORS: " . print_r($errors, true) . "\n\n", FILE_APPEND);
        }
    }
}

// Handle bulk deletion of resident records
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    if (csrf_validate()) {
        $selected_items = $_POST['selected_items'] ?? [];
        $deleted_count = 0;
        foreach ($selected_items as $item) {
            $parts = explode(':', $item);
            if (count($parts) !== 3)
                continue;
            list($type, $record_id, $user_id) = $parts;
            $record_id = (int) $record_id;
            $user_id = (int) $user_id;

            try {
                if ($type === 'OWNER') {
                    if ($record_id > 0) {
                        $pdo->prepare('DELETE FROM resident_records WHERE id = ?')->execute([$record_id]);
                    }
                    if ($user_id > 0) {
                        $pdo->prepare('DELETE FROM residents WHERE user_id = ?')->execute([$user_id]);
                        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user_id]);
                    }
                    if ($record_id > 0 || $user_id > 0) {
                        $deleted_count++;
                    }
                } else if ($type === 'MEMBER') {
                    $pdo->prepare('DELETE FROM family_members WHERE id = ?')->execute([$record_id]);
                    $deleted_count++;
                }
            } catch (Exception $e) {
            }
        }
        if ($deleted_count > 0) {
            $success = "Successfully deleted $deleted_count record(s).";
        }
    }
}

// Handle form submission for deleting resident record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!csrf_validate()) {
        $errors[] = 'Invalid CSRF token. Please refresh the page and try again.';
    } else {
        $record_id = (int) ($_POST['record_id'] ?? 0);
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $type = $_POST['type'] ?? 'OWNER';

        if ($record_id <= 0 && $user_id <= 0) {
            $errors[] = 'Invalid record ID';
        } else {
            try {
                if ($type === 'OWNER') {
                    if ($record_id > 0) {
                        // Delete the official record
                        $stmt = $pdo->prepare('DELETE FROM resident_records WHERE id = ?');
                        $stmt->execute([$record_id]);
                    }
                    if ($user_id > 0) {
                        // Delete the user account and associated resident profile
                        $stmt = $pdo->prepare('DELETE FROM residents WHERE user_id = ?');
                        $stmt->execute([$user_id]);
                        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                        $stmt->execute([$user_id]);
                    }
                    if ($record_id > 0 || $user_id > 0) {
                        $success = 'Resident record deleted successfully';
                    }
                } else {
                    // Delete family member
                    $stmt = $pdo->prepare('DELETE FROM family_members WHERE id = ?');
                    $stmt->execute([$record_id]);
                    $success = 'Family member deleted successfully';
                }
            } catch (Exception $e) {
                $errors[] = 'Server error: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// Handle toggle status for resident records and family members
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    if (!csrf_validate()) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $record_id = (int) ($_POST['record_id'] ?? 0);
        $type = $_POST['type'] ?? '';
        $new_status = (int) ($_POST['status'] ?? 0);

        if ($record_id <= 0) {
            $errors[] = 'Invalid ID';
        } else {
            try {
                if ($type === 'OWNER') {
                    $pdo->prepare('UPDATE resident_records SET is_active = ? WHERE id = ?')->execute([$new_status, $record_id]);
                    $success = 'Resident status updated successfully';
                } elseif ($type === 'MEMBER') {
                    $pdo->prepare('UPDATE family_members SET is_active = ? WHERE id = ?')->execute([$new_status, $record_id]);
                    $success = 'Family member status updated successfully';
                }
            } catch (Exception $e) {
                $errors[] = 'Server error. Please try again.';
            }
        }
    }
}

// Get pending verifications
$pending_residents = $pdo->query('
    SELECT r.*, u.full_name, u.email, u.created_at as user_created_at
    FROM residents r JOIN users u ON r.user_id = u.id
    WHERE r.verification_status = \'pending\'
    ORDER BY r.id DESC
')->fetchAll();

$search = trim($_GET['search'] ?? '');
$params = [];
// Base query for Primary Users (Owners)
$query_primary = '
    SELECT 
        "OWNER" as resident_type,
        u.id as user_id,
        u.full_name, 
        u.email, 
        u.is_active,
        res.address, 
        res.phone,
        res.birthdate,
        res.sex,
        res.citizenship,
        res.civil_status,
        res.purok,
        res.religion,
        res.occupation,
        res.educational_attainment,
        res.classification,
        res.verification_status
    FROM users u 
    LEFT JOIN residents res ON res.user_id = u.id 
    WHERE u.role = "resident" 
';

$final_query = "SELECT cr.* FROM ($query_primary) as cr";

if ($search !== '') {
    $final_query .= ' WHERE cr.full_name LIKE ? OR cr.email LIKE ? OR cr.address LIKE ?';
    $search_term = "%$search%";
    $params = [$search_term, $search_term, $search_term];
}
$final_query .= ' ORDER BY cr.full_name ASC';
$stmt = $pdo->prepare($final_query);
$stmt->execute($params);
$user_owners = $stmt->fetchAll();

// Fetch all official records for matching
$all_resident_records = $pdo->query("SELECT rr.*, u.full_name as created_by_name FROM resident_records rr LEFT JOIN users u ON rr.created_by = u.id")->fetchAll();

function clean_str($str)
{
    if (!$str)
        return "";
    $str = preg_replace('/[\p{Z}\s]+/u', ' ', $str);
    return strtolower(trim($str));
}

// Build Unified List
$unified_records = [];
$matched_record_ids = [];

if ($user_owners) {
    foreach ($user_owners as $u) {
        $matched_record_id = 0;
        $row = $u;
        $row['created_at'] = $u['created_at'] ?? date('Y-m-d H:i:s');

        // Attempt to match with resident_records to get created_by and ID reference
        foreach ($all_resident_records as $rr) {
            if ((!empty($u['email']) && $u['email'] === $rr['email']) || clean_str($u['full_name']) === clean_str($rr['full_name'])) {
                $matched_record_id = $rr['id'];
                $matched_record_ids[] = $rr['id'];
                $row['id'] = $rr['id'];
                $row['created_by_name'] = $rr['created_by_name'];
                $row['created_at'] = $rr['created_at'];

                if (empty($row['address']))
                    $row['address'] = $rr['address'];
                if (empty($row['phone']))
                    $row['phone'] = $rr['phone'];
                break;
            }
        }

        if (!$matched_record_id) {
            $row['id'] = 0;
            $row['created_by_name'] = 'Self Registered';
        }

        $unified_records[] = $row;

        // Add family members
        $fm_stmt = $pdo->prepare('SELECT * FROM family_members WHERE user_id = ? ORDER BY full_name');
        $fm_stmt->execute([$u['user_id']]);
        $fms = $fm_stmt->fetchAll();
        foreach ($fms as $fm) {
            $unified_records[] = [
                'id' => $row['id'] ?? 0,
                'fm_id' => $fm['id'],
                'resident_type' => 'MEMBER',
                'full_name' => $fm['full_name'],
                'address' => $row['address'] ?? 'N/A',
                'phone' => $row['phone'] ?? '',
                'is_active' => $fm['is_active'],
                'created_by_name' => $row['full_name'] ?? 'N/A',
                'user_id' => $u['user_id'],
                'created_at' => $fm['created_at'] ?? ($row['created_at'] ?? date('Y-m-d H:i:s')),
                'verification_status' => 'verified'
            ];
        }
    }
}

// Add remaining resident_records that don't have accounts
foreach ($all_resident_records as $rr) {
    if (!in_array($rr['id'], $matched_record_ids)) {
        $rr['resident_type'] = 'OWNER';
        $rr['user_id'] = 0;
        $rr['verification_status'] = 'verified';
        $unified_records[] = $rr;
    }
}

// Re-apply search if it was a record-only match
if ($search !== '') {
    $unified_records = array_filter($unified_records, function ($r) use ($search) {
        $s = strtolower($search);
        return strpos(strtolower($r['full_name'] ?? ''), $s) !== false ||
            strpos(strtolower($r['email'] ?? ''), $s) !== false ||
            strpos(strtolower($r['address'] ?? ''), $s) !== false;
    });
}

// Final alphabetical sort
usort($unified_records, function ($a, $b) {
    return strcasecmp($a['full_name'] ?? '', $b['full_name'] ?? '');
});

// Pagination Logic
$limit = 10;
$total_records = count($unified_records);
$total_pages = ceil($total_records / $limit);
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($current_page < 1)
    $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0)
    $current_page = $total_pages;
$offset = ($current_page - 1) * $limit;

// Data to display on current page
$display_records = array_slice($unified_records, $offset, $limit);
?>

<?php if ($success): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            Swal.fire({
                title: 'Success!',
                text: '<?php echo htmlspecialchars($success); ?>',
                icon: 'success',
                confirmButtonColor: '#3085d6'
            });
        });
    </script>
<?php endif; ?>

<div class="admin-table">
    <div class="p-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-layer-group me-2 text-primary"></i>Master Resident List</h4>
            <p class="text-muted mb-0 small">Unified view of all residents and family members</p>
        </div>
        <form method="GET" class="d-inline-block" style="max-width: 350px;">
            <div class="input-group shadow-sm">
                <input type="text" name="search" class="form-control border-0" placeholder="Search name or address..."
                    value="<?php echo htmlspecialchars($search); ?>" style="background: #f8f9fa;">
                <button class="btn btn-white border-0 text-primary" type="submit" style="background: #f8f9fa;"><i
                        class="fas fa-search"></i></button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4" style="width: 40px;">
                        <input type="checkbox" class="form-check-input" id="selectAllResidents">
                    </th>
                    <th style="width: 60px;">#</th>
                    <th>NAME</th>
                    <th>TYPE</th>
                    <th>ADDRESS</th>
                    <th>CONTACT NO.</th>
                    <th>STATUS</th>
                    <th class="text-center" style="width: 140px;">
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            ACTION
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light border-0 text-secondary p-0" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false" title="Bulk Actions"
                                    style="width: 24px; height: 24px;">
                                    <i class="fas fa-ellipsis-v" style="font-size: 0.85rem;"></i>
                                </button>
                                <ul class="dropdown-menu shadow border-0 py-2 small">
                                    <li>
                                        <button type="button" class="dropdown-item py-2"
                                            onclick="bulkDeleteResidents()">
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
                foreach ($display_records as $row):
                    ?>
                    <tr>
                        <td class="ps-4">
                            <input type="checkbox" class="form-check-input resident-checkbox"
                                data-id="<?php echo $row['id']; ?>" data-user-id="<?php echo $row['user_id'] ?? 0; ?>"
                                data-type="<?php echo $row['resident_type']; ?>"
                                data-fm-id="<?php echo $row['fm_id'] ?? 0; ?>">
                        </td>
                        <td class="text-muted small fw-bold"><?php echo $counter++; ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['full_name']); ?></div>
                        </td>
                        <td>
                            <?php if ($row['resident_type'] === 'OWNER'): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1"
                                    style="font-size: 0.7rem;">OWNER</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1"
                                    style="font-size: 0.7rem;">MEMBER</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?php echo htmlspecialchars($row['address'] ?? 'N/A'); ?></td>
                        <td class="small text-muted"><?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?></td>
                        <td>
                            <?php if (($row['verification_status'] ?? '') === 'verified'): ?>
                                <span
                                    class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-1"
                                    style="font-size: 0.75rem;">Verified</span>
                            <?php else: ?>
                                <span
                                    class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-1"
                                    style="font-size: 0.75rem;">Unverified</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center align-items-center gap-3">
                                <a href="resident_record_view.php?id=<?php echo $row['id']; ?>&user_id=<?php echo $row['user_id'] ?? 0; ?><?php echo $row['resident_type'] === 'MEMBER' ? '&fm_id=' . $row['fm_id'] : ''; ?>"
                                    class="action-btn text-primary" title="View Profile">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="resident_record_view.php?id=<?php echo $row['id']; ?>&user_id=<?php echo $row['user_id'] ?? 0; ?>&edit=1<?php echo $row['resident_type'] === 'MEMBER' ? '&fm_id=' . $row['fm_id'] : ''; ?>"
                                    class="action-btn text-warning" title="Edit Record">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" class="d-inline delete-record-form">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="record_id"
                                        value="<?php echo $row['resident_type'] === 'OWNER' ? $row['id'] : $row['fm_id']; ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $row['user_id'] ?? 0; ?>">
                                    <input type="hidden" name="type" value="<?php echo $row['resident_type']; ?>">
                                    <button type="button"
                                        class="action-btn border-0 bg-transparent text-danger btn-delete-record"
                                        title="Delete Record"
                                        data-name="<?php echo htmlspecialchars($row['full_name']); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($unified_records)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">No resident records found.</td>
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
                <?php echo $total_records; ?> records
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
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('selectAllResidents');
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                const checkboxes = document.querySelectorAll('.resident-checkbox');
                checkboxes.forEach(cb => cb.checked = this.checked);
            });
        }

        document.querySelectorAll('.btn-delete-record').forEach(button => {
            button.addEventListener('click', function (e) {
                const form = this.closest('form');
                const name = this.dataset.name;

                Swal.fire({
                    title: 'Are you sure?',
                    text: `You are about to delete the record of ${name}. This action cannot be undone!`,
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
    });

    function bulkDeleteResidents() {
        const selected = Array.from(document.querySelectorAll('.resident-checkbox:checked')).map(cb => ({
            id: cb.dataset.id,
            userId: cb.dataset.userId,
            type: cb.dataset.type,
            fmId: cb.dataset.fmId,
            name: cb.closest('tr').querySelector('.fw-bold').innerText
        }));

        if (selected.length === 0) {
            Swal.fire('No selection', 'Please select at least one resident record.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Bulk Delete',
            text: `Are you sure you want to delete ${selected.length} selected record(s)? This action cannot be undone.`,
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
                    <input type="hidden" name="action" value="bulk_delete">
                `;
                selected.forEach(item => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_items[]';
                    const recId = item.type === 'MEMBER' ? item.fmId : item.id;
                    input.value = `${item.type}:${recId}:${item.userId}`;
                    bulkForm.appendChild(input);
                });
                document.body.appendChild(bulkForm);
                bulkForm.submit();
            }
        });
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>