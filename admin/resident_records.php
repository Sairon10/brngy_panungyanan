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
        res.verification_status,
        res.is_senior,
        res.is_pwd,
        res.is_solo_parent
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

                if (empty($row['is_senior']))
                    $row['is_senior'] = $rr['is_senior'];
                if (empty($row['is_pwd']))
                    $row['is_pwd'] = $rr['is_pwd'];
                if (empty($row['is_solo_parent']))
                    $row['is_solo_parent'] = $rr['is_solo_parent'];
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
                'verification_status' => 'verified',
                'is_senior' => $fm['is_senior'],
                'is_pwd' => $fm['is_pwd'],
                'is_solo_parent' => $fm['is_solo_parent']
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

// Category Quick Filter
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';
if ($filter !== 'all') {
    $unified_records = array_filter($unified_records, function ($r) use ($filter) {
        if ($filter === 'senior') return !empty($r['is_senior']);
        if ($filter === 'pwd') return !empty($r['is_pwd']);
        if ($filter === 'solo_parent') return !empty($r['is_solo_parent']);
        return true;
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
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <div class="input-group shadow-sm">
                <input type="text" name="search" class="form-control border-0" placeholder="Search name or address..."
                    value="<?php echo htmlspecialchars($search); ?>" style="background: #f8f9fa;">
                <button class="btn btn-white border-0 text-primary" type="submit" style="background: #f8f9fa;"><i
                        class="fas fa-search"></i></button>
            </div>
        </form>
    </div>

    <!-- Quick Category Filters -->
    <div class="px-4 py-2.5 border-bottom d-flex align-items-center flex-wrap gap-2" style="background-color: #fafbfc;">
        <span class="text-muted small fw-semibold me-2"><i class="fas fa-filter me-1" style="color: #14b8a6;"></i>Quick Filter:</span>
        <a href="?filter=all&search=<?php echo urlencode($search); ?>" 
           class="btn btn-sm rounded-pill px-3 <?php echo $filter === 'all' ? 'text-white border-0 shadow-sm' : 'bg-white text-secondary border border-light-subtle'; ?>" 
           style="<?php echo $filter === 'all' ? 'background-color: #14b8a6 !important; box-shadow: 0 2px 6px rgba(20, 184, 166, 0.15);' : ''; ?> font-size: 0.8rem; transition: all 0.2s;">
            All
        </a>
        <a href="?filter=senior&search=<?php echo urlencode($search); ?>" 
           class="btn btn-sm rounded-pill px-3 <?php echo $filter === 'senior' ? 'bg-warning text-dark border-warning shadow-sm fw-bold' : 'bg-white text-secondary border border-light-subtle'; ?>" 
           style="font-size: 0.8rem; transition: all 0.2s;">
            👴 Senior Citizen
        </a>
        <a href="?filter=pwd&search=<?php echo urlencode($search); ?>" 
           class="btn btn-sm rounded-pill px-3 <?php echo $filter === 'pwd' ? 'bg-info text-dark border-info shadow-sm fw-bold' : 'bg-white text-secondary border border-light-subtle'; ?>" 
           style="font-size: 0.8rem; transition: all 0.2s;">
            ♿ PWD
        </a>
        <a href="?filter=solo_parent&search=<?php echo urlencode($search); ?>" 
           class="btn btn-sm rounded-pill px-3 <?php echo $filter === 'solo_parent' ? 'bg-secondary text-white border-secondary shadow-sm fw-bold' : 'bg-white text-secondary border border-light-subtle'; ?>" 
           style="font-size: 0.8rem; transition: all 0.2s;">
            👪 Solo Parent
        </a>
    </div>

    <div class="table-card">
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
                <?php if (!empty($display_records)): ?>
                    <?php
                    $counter = $offset + 1;
                    foreach ($display_records as $row):
                        ?>
                        <tr class="align-middle">
                            <td class="ps-4">
                                <input type="checkbox" class="form-check-input resident-checkbox"
                                    data-id="<?php echo $row['id']; ?>" data-user-id="<?php echo $row['user_id'] ?? 0; ?>"
                                    data-type="<?php echo $row['resident_type']; ?>"
                                    data-fm-id="<?php echo $row['fm_id'] ?? 0; ?>">
                            </td>
                            <td class="text-muted font-monospace small"><?php echo $counter++; ?></td>
                            <td>
                                <div class="d-flex align-items-center flex-wrap gap-1">
                                    <span class="fw-bold text-dark me-1"><?php echo htmlspecialchars($row['full_name']); ?></span>
                                    <?php if (!empty($row['is_senior'])): ?>
                                        <a href="?filter=senior&search=<?php echo urlencode($search); ?>" 
                                           class="badge bg-warning text-dark border border-warning text-decoration-none" 
                                           style="font-size: 0.65rem; padding: 2px 6px;">SENIOR</a>
                                    <?php endif; ?>
                                    <?php if (!empty($row['is_pwd'])): ?>
                                        <a href="?filter=pwd&search=<?php echo urlencode($search); ?>" 
                                           class="badge bg-info text-dark border border-info text-decoration-none" 
                                           style="font-size: 0.65rem; padding: 2px 6px;">PWD</a>
                                    <?php endif; ?>
                                    <?php if (!empty($row['is_solo_parent'])): ?>
                                        <a href="?filter=solo_parent&search=<?php echo urlencode($search); ?>" 
                                           class="badge bg-secondary text-white border border-secondary text-decoration-none" 
                                           style="font-size: 0.65rem; padding: 2px 6px;">SOLO PARENT</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($row['resident_type'] === 'OWNER'): ?>
                                    <span class="badge bg-primary">OWNER</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">MEMBER</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?php echo htmlspecialchars($row['address'] ?? 'N/A'); ?></td>
                            <td class="font-monospace small"><?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if (($row['verification_status'] ?? '') === 'verified'): ?>
                                    <span class="badge bg-success">Verified</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Unverified</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <a href="resident_record_view.php?id=<?php echo $row['id']; ?>&user_id=<?php echo $row['user_id'] ?? 0; ?><?php echo $row['resident_type'] === 'MEMBER' ? '&fm_id=' . $row['fm_id'] : ''; ?>"
                                        class="btn btn-sm btn-outline-info" title="View details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="resident_record_view.php?id=<?php echo $row['id']; ?>&user_id=<?php echo $row['user_id'] ?? 0; ?>&edit=1<?php echo $row['resident_type'] === 'MEMBER' ? '&fm_id=' . $row['fm_id'] : ''; ?>"
                                        class="btn btn-sm btn-outline-primary" title="Edit details">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" class="d-inline delete-record-form">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="record_id"
                                            value="<?php echo $row['resident_type'] === 'OWNER' ? $row['id'] : $row['fm_id']; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $row['user_id'] ?? 0; ?>">
                                        <input type="hidden" name="type" value="<?php echo $row['resident_type']; ?>">
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-record"
                                            data-name="<?php echo htmlspecialchars($row['full_name']); ?>" title="Delete record">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">No resident records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Links & Info Bar inside table-card -->
    <?php if ($total_pages > 1): ?>
        <div class="table-info-bar">
            <div>
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_records); ?></strong> of <strong><?php echo $total_records; ?></strong> records
            </div>
        </div>
        <nav class="table-pagination">
            <ul class="pagination">
                <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>"><i class="fas fa-chevron-left" style="font-size:.65rem;"></i> Prev</a>
                </li>
                <?php
                $max_visible = 5;
                $start = max(1, $current_page - floor($max_visible / 2));
                $end = min($total_pages, $start + $max_visible - 1);
                if ($end - $start + 1 < $max_visible) {
                    $start = max(1, $end - $max_visible + 1);
                }

                if ($start > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">1</a>
                    </li>
                    <?php if ($start > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>"><?php echo $total_pages; ?></a>
                    </li>
                <?php endif; ?>

                <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">Next <i class="fas fa-chevron-right" style="font-size:.65rem;"></i></a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
    </div>
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