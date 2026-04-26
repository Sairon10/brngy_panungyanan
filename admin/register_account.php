<?php
require_once __DIR__ . '/../config.php';
if (!is_admin()) redirect('../index.php');

$pdo = get_db_connection();
$info = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'register_subadmin' || $action === 'register_resident') {
            $role = ($action === 'register_subadmin') ? 'admin' : 'resident';
            
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $middle_name = trim($_POST['middle_name'] ?? '');
            $suffix = trim($_POST['suffix'] ?? '');
            $birthdate = trim($_POST['birthdate'] ?? '');
            $citizenship = trim($_POST['citizenship'] ?? '');
            $civil_status = trim($_POST['civil_status'] ?? '');
            $sex = trim($_POST['sex'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $province = trim($_POST['province'] ?? '');
            $municipality = trim($_POST['municipality'] ?? '');
            $barangay = trim($_POST['barangay'] ?? '');
            $purok = trim($_POST['purok'] ?? '');
            $street = trim($_POST['street'] ?? '');
            $password = $_POST['password'] ?? '';
            
            $is_solo_parent = isset($_POST['is_solo_parent']) ? 1 : 0;
            $is_pwd = isset($_POST['is_pwd']) ? 1 : 0;
            $is_senior = isset($_POST['is_senior']) ? 1 : 0;

            $full_name = implode(' ', array_filter([$first_name, $middle_name, $last_name, $suffix]));
            $address = implode(', ', array_filter([$street, $barangay, $municipality, $province]));

            if (!$first_name || !$last_name || !$email || !$password) {
                $error = 'First name, last name, email, and password are required.';
            } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password)) {
                $error = 'Password must be at least 8 characters long and contain at least one uppercase letter, one number, and one special character.';
            } else {
                try {
                    // 1. Check Email Uniqueness (Both users and resident_records)
                    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) { $error = 'This email address is already registered in the system.'; }

                    if (!$error && $phone) {
                        if (!preg_match('/^[0-9]{11}$/', $phone)) {
                            $error = 'Phone number must be exactly 11 digits and contain only numbers.';
                        } else {
                            // 2. Check Phone Uniqueness (Both residents and resident_records)
                            $stmt = $pdo->prepare('SELECT id FROM residents WHERE phone = ? LIMIT 1');
                            $stmt->execute([$phone]);
                            if ($stmt->fetch()) { $error = 'This phone number is already registered.'; }
                        }
                    }

                    if (!$error) {
                        // 3. Check Name Uniqueness (Both users and resident_records)
                        $stmt = $pdo->prepare('SELECT id FROM users WHERE full_name = ? LIMIT 1');
                        $stmt->execute([$full_name]);
                        if ($stmt->fetch()) { $error = 'An account with this name already exists.'; }
                        
                        if ($stmt->fetch()) { $error = 'A resident with this name already exists in official records.'; }
                    }

                    if (!$error) {
                        // 4. Age Validation (18+)
                        $birth_date_obj = new DateTime($birthdate);
                        $today = new DateTime();
                        $age = $today->diff($birth_date_obj)->y;
                        if ($age < 18) {
                            $error = 'Resident must be at least 18 years old to have an account.';
                        }
                    }

                    if (!$error) {
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, full_name, first_name, last_name, middle_name, suffix, role, is_active) VALUES (?,?,?,?,?,?,?,?, 1)');
                        $stmt->execute([$email, $hash, $full_name, $first_name, $last_name, $middle_name ?: null, $suffix ?: null, $role]);

                        $user_id = $pdo->lastInsertId();
                        $stmt = $pdo->prepare('INSERT INTO residents (user_id, address, phone, birthdate, citizenship, civil_status, sex, purok, verification_status, is_solo_parent, is_pwd, is_senior) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'verified\', ?, ?, ?)');
                        $stmt->execute([ $user_id, $address, $phone ?: null, $birthdate ?: null, $citizenship ?: null, $civil_status ?: null, $sex ?: null, $purok ?: null, $is_solo_parent, $is_pwd, $is_senior ]);

                        if ($role === 'resident') {
                           $pdo->prepare('INSERT INTO resident_records (first_name, last_name, middle_name, suffix, full_name, email, address, phone, birthdate, sex, citizenship, civil_status, purok, is_solo_parent, is_pwd, is_senior, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                               ->execute([$first_name, $last_name, $middle_name, $suffix, $full_name, $email, $address, $phone, $birthdate, $sex, $citizenship, $civil_status, $purok, $is_solo_parent, $is_pwd, $is_senior, $_SESSION['user_id']]);
                        }

                        $info = ($role === 'admin' ? 'Sub-admin' : 'Resident') . ' account and record created successfully.';
                    }
                } catch (Throwable $e) { $error = 'Error: ' . $e->getMessage(); }
            }
        }
    }
}

$page_title = 'Register Account';
$breadcrumb = [['title' => 'Register Account']];
require_once __DIR__ . '/header.php';
?>

<style>
    :root { --p-grad: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); --sys-teal: #14b8a6; }
    .glass-card { background: #fff; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; max-width: 1100px; margin: 20px auto; }
    .card-header-custom { background: var(--p-grad); color: white; padding: 2.5rem; text-align: center; }
    .form-pane { padding: 3rem; display: none; animation: fadeIn 0.3s ease; }
    .form-pane.active { display: block; }
    @keyframes fadeIn { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:translateY(0); } }
    .section-title { font-size: 0.85rem; font-weight: 800; color: var(--sys-teal); letter-spacing: 1.2px; border-bottom: 2px solid #f3f4f6; padding-bottom: 12px; margin-bottom: 25px; text-transform: uppercase; display: flex; align-items: center; gap: 10px; }
    .form-label { font-weight: 700; color: #4b5563; font-size: 0.8rem; margin-bottom: 6px; }
    .form-control, .form-select { border-radius: 12px; border: 1.5px solid #e5e7eb; padding: 0.75rem 1.2rem; font-size: 0.95rem; }
    .form-control:focus { border-color: var(--sys-teal); box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.1); }
    .nav-pills-custom { background: rgba(255,255,255,0.2); padding: 6px; border-radius: 14px; display: inline-flex; }
    .nav-pills-custom .btn { color: white; border: none; padding: 10px 25px; border-radius: 12px; font-weight: 700; transition: 0.2s; }
    .nav-pills-custom .btn.active { background: white; color: var(--sys-teal); box-shadow: 0 4px 15px rgba(0,0,0,0.15); }
    .btn-submit { background: var(--p-grad); color: white; border: none; padding: 1rem; border-radius: 15px; font-weight: 800; letter-spacing: 0.5px; transition: all 0.2s; }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(20, 184, 166, 0.3); color: white; }
</style>

<div class="glass-card">
    <div class="card-header-custom">
        <h2 class="fw-bold mb-2">Register New Account</h2>
        <p class="opacity-75 mb-4">Official Barangay Registration & Deployment Portal</p>
        
        <div class="nav-pills-custom">
            <button class="btn active" onclick="showForm('resident', this)">Resident Profile</button>
            <button class="btn" onclick="showForm('subadmin', this)">Sub-Admin Profile</button>
        </div>
    </div>

    <!-- FORM TEMPLATE (Hidden, used by JS) -->
    <div id="form-container">
        <!-- Forms are dynamically populated or static but identical -->
        <?php foreach (['resident', 'subadmin'] as $type): ?>
        <div id="<?php echo $type; ?>-pane" class="form-pane <?php echo $type === 'resident' ? 'active' : ''; ?>">
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="register_<?php echo $type; ?>">
                
                <div class="section-title"><i class="fas fa-user-circle"></i> Personal Information</div>
                <div class="row g-4 mb-5">
                    <div class="col-md-3"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" placeholder="Juan" required></div>
                    <div class="col-md-3"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="form-control" placeholder="M."></div>
                    <div class="col-md-3"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control" placeholder="Dela Cruz" required></div>
                    <div class="col-md-3"><label class="form-label">Suffix</label><input type="text" name="suffix" class="form-control" placeholder="Jr."></div>
                    
                    <div class="col-md-4"><label class="form-label">Birthdate</label><input type="date" name="birthdate" class="form-control" max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Sex</label>
                        <select name="sex" class="form-select" required>
                            <option value="">Select...</option><option value="Male">Male</option><option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Civil Status</label>
                        <select name="civil_status" class="form-select" required>
                            <option value="">Select...</option><option value="Single">Single</option><option value="Married">Married</option><option value="Widowed">Widowed</option><option value="Separated">Separated</option>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Citizenship</label><input type="text" name="citizenship" class="form-control" value="Filipino" required></div>
                    <div class="col-md-6 d-flex align-items-center gap-4 pt-3">
                        <div class="form-check"><input type="checkbox" name="is_solo_parent" class="form-check-input"> <label class="form-label mb-0">Solo Parent</label></div>
                        <div class="form-check"><input type="checkbox" name="is_pwd" class="form-check-input"> <label class="form-label mb-0">PWD</label></div>
                        <div class="form-check"><input type="checkbox" name="is_senior" class="form-check-input"> <label class="form-label mb-0">Senior Citizen</label></div>
                    </div>
                </div>

                <div class="section-title"><i class="fas fa-map-marked-alt"></i> Address Details</div>
                <div class="row g-4 mb-5">
                    <div class="col-md-8"><label class="form-label">Street / House No.</label><input type="text" name="street" class="form-control" placeholder="123 Example St." required></div>
                    <div class="col-md-4"><label class="form-label">Purok</label><input type="text" name="purok" class="form-control" placeholder="Purok 1" required></div>
                    <div class="col-md-4"><label class="form-label">Barangay</label><input type="text" name="barangay" class="form-control" value="Panungyanan" readonly></div>
                    <div class="col-md-4"><label class="form-label">Municipality</label><input type="text" name="municipality" class="form-control" value="General Trias" readonly></div>
                    <div class="col-md-4"><label class="form-label">Province</label><input type="text" name="province" class="form-control" value="Cavite" readonly></div>
                </div>

                <div class="section-title"><i class="fas fa-shield-check"></i> Security & Access</div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4"><label class="form-label">Registration Email</label><input type="email" name="email" class="form-control" placeholder="account@email.com" required></div>
                    <div class="col-md-4"><label class="form-label">Primary Phone (Contact)</label><input type="text" name="phone" class="form-control" placeholder="09xxxxxxxxx" maxlength="11" pattern="[0-9]{11}" title="Please enter exactly 11 digits" required></div>
                    <div class="col-md-4">
                        <label class="form-label">Login Password</label>
                        <div class="input-group">
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                            <button class="btn btn-outline-secondary border-1 border-start-0 px-3" type="button" onclick="togglePass(this)" style="border-radius: 0 12px 12px 0; border: 1.5px solid #e5e7eb;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="alert <?php echo $type === 'resident' ? 'alert-info' : 'alert-warning'; ?> border-0 rounded-4 d-flex align-items-center gap-3">
                    <i class="fas <?php echo $type === 'resident' ? 'fa-info-circle' : 'fa-exclamation-triangle'; ?> fa-lg"></i>
                    <div class="small">
                        <strong>Important:</strong> You are registering an account for a <strong><?php echo strtoupper($type); ?></strong>. 
                        This account will be verified automatically upon creation.
                    </div>
                </div>

                <button type="button" class="btn btn-submit w-100 mt-4" onclick="confirmRegister(this)">
                    <i class="fas fa-check-circle me-2"></i> COMPLETE <?php echo strtoupper($type); ?> REGISTRATION
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    function showForm(type, btn) {
        document.querySelectorAll('.form-pane').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.nav-pills-custom .btn').forEach(b => b.classList.remove('active'));
        document.getElementById(type + '-pane').classList.add('active');
        btn.classList.add('active');
    }

    function confirmRegister(btn) {
        const form = btn.closest('form');
        const role = form.querySelector('input[name="action"]').value.includes('subadmin') ? 'Sub-Admin' : 'Resident';
        const name = form.querySelector('input[name="first_name"]')?.value || form.querySelector('input[name="full_name"]')?.value;

        Swal.fire({
            title: 'Confirm Registration',
            text: `Are you sure you want to register ${name} as a ${role}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#14b8a6',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Register Now!',
            cancelButtonText: 'Review Details'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }

    function togglePass(btn) {
        const input = btn.parentElement.querySelector('input');
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
</script>

<?php if ($info): ?><script>Swal.fire({ title: 'Success!', text: '<?php echo htmlspecialchars($info); ?>', icon: 'success' });</script><?php endif; ?>
<?php if ($error): ?><script>Swal.fire({ title: 'Security Alert', text: '<?php echo htmlspecialchars($error); ?>', icon: 'error' });</script><?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
