<?php
require_once __DIR__ . '/config.php';
if (!is_logged_in())
    redirect('login.php');

$pdo = get_db_connection();
$user_id = $_SESSION['user_id'];

// Check if already completed (Safely)
$check_col = $pdo->query("SHOW COLUMNS FROM residents LIKE 'is_rbi_completed'");
if ($check_col->rowCount() > 0) {
    $stmt = $pdo->prepare("SELECT is_rbi_completed FROM residents WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $res = $stmt->fetch();
    if ($res && isset($res['is_rbi_completed']) && $res['is_rbi_completed']) {
        redirect('dashboard.php');
    }
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_validate()) {
        try {
            $philsys = trim($_POST['philsys_card_no'] ?? '');
            $is_head = (int) ($_POST['is_family_head'] ?? 0);

            $first_name = trim($_POST['first_name'] ?? '');
            $middle_name = trim($_POST['middle_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $suffix = trim($_POST['suffix'] ?? '');
            $full_name = implode(' ', array_filter([$first_name, $middle_name, $last_name, $suffix]));

            $birth_place = trim($_POST['birth_place'] ?? '');
            $religion = trim($_POST['religion'] ?? '');
            $occupation = trim($_POST['occupation'] ?? '');
            $education = trim($_POST['educational_attainment'] ?? '');
            $edu_status = trim($_POST['edu_status'] ?? '');
            $combined_edu = $education . ($edu_status ? " ($edu_status)" : "");

            // Handle multiple classifications
            $classifications = isset($_POST['classifications']) ? $_POST['classifications'] : [];
            $class_json = json_encode($classifications);

            // Update Users table (names)
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?, full_name = ? WHERE id = ?");
            $stmt->execute([$first_name, $middle_name, $last_name, $suffix, $full_name, $user_id]);

            // Update Residents table
            $sql = "UPDATE residents SET
                philsys_card_no = ?,
                is_family_head = ?,
                birth_place = ?,
                religion = ?,
                occupation = ?,
                educational_attainment = ?,
                classification = ?,
                is_rbi_completed = 1,
                phone = ?,
                civil_status = ?,
                sex = ?,
                birthdate = ?,
                purok = ?,
                address = ?,
                citizenship = ?
                WHERE user_id = ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $philsys,
                $is_head,
                $birth_place,
                $religion,
                $occupation,
                $combined_edu,
                $class_json,
                $_POST['phone'] ?? null,
                $_POST['civil_status'] ?? null,
                $_POST['sex'] ?? null,
                $_POST['birthdate'] ?? null,
                $_POST['purok'] ?? null,
                $_POST['address'] ?? null,
                $_POST['citizenship'] ?? 'Filipino',
                $user_id
            ]);

            $success = 'RBI Profile completed successfully! Welcome to the Barangay System.';
        } catch (Exception $e) {
            $error = 'Error saving profile: ' . $e->getMessage();
        }
    }
}

// Get resident data to pre-fill basic info
$stmt = $pdo->prepare("SELECT u.full_name, u.first_name, u.last_name, u.middle_name, u.suffix, r.* FROM users u JOIN residents r ON r.user_id = u.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$resident = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalize Your Profile - Barangay Panungyanan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Inter', sans-serif;
        }

        .rbi-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
            margin-top: 30px;
            margin-bottom: 50px;
        }

        .rbi-header {
            background: linear-gradient(135deg, #0f766e, #14b8a6);
            color: white;
            padding: 15px 25px;
            border-radius: 12px 12px 0 0;
        }

        .section-title {
            color: #2d3748;
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 700;
            font-size: 0.75rem;
            color: #2d3748;
            margin-bottom: 8px;
            text-transform: none;
        }

        .form-control,
        .form-select {
            border-radius: 4px;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            font-size: 0.85rem;
            color: #4a5568;
            background-color: #fff;
        }

        .form-control::placeholder {
            color: #a0aec0;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .form-control:focus,
        .form-select:focus {
            box-shadow: none;
            border-color: #0f766e;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0f766e, #14b8a6);
            border: none;
            padding: 12px 40px;
            border-radius: 5px;
            font-weight: 700;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #0d6460, #0f766e);
        }

        .form-check-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #4a5568;
            margin-left: 5px;
        }

        .form-check-input:checked {
            background-color: #0f766e;
            border-color: #0f766e;
        }
    </style>
</head>

<body>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-xl-11">
                <div class="card rbi-card">
                    <div class="rbi-header">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-user-edit me-2"></i>Finalize Your Resident Profile</h6>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <div class="alert alert-info border-0 bg-info bg-opacity-10 text-info small mb-4">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle fs-5 me-3"></i>
                                <div>
                                    <strong>Almost there!</strong> Your account is created, but we need a few more details for the <strong>Registry of Barangay Inhabitants (RBI)</strong> to fully verify your residency.
                                </div>
                            </div>
                        </div>
                        
                        <h5 class="section-title text-uppercase border-bottom pb-2 mb-4">Personal Information</h5>
                        <?php if ($error): ?>
                            <div class="alert alert-danger rounded-3 small"><i
                                    class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" id="rbiForm">
                            <?php echo csrf_field(); ?>

                            <!-- PhilSys Section -->
                            <div class="row g-4 mb-4">
                                <div class="col-md-5">
                                    <label class="form-label">PhilSys Card No.</label>
                                    <input type="text" name="philsys_card_no" class="form-control"
                                        placeholder="PHILSYS CARD NO.">
                                </div>
                                <input type="hidden" name="is_family_head" value="0">
                            </div>

                            <!-- Name Section -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <label class="form-label">First name</label>
                                    <input type="text" name="first_name"
                                        value="<?php echo htmlspecialchars($resident['first_name']); ?>"
                                        class="form-control text-uppercase" placeholder="JERIC" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Middle name</label>
                                    <input type="text" name="middle_name"
                                        value="<?php echo htmlspecialchars($resident['middle_name']); ?>"
                                        class="form-control text-uppercase" placeholder="MIDDLE NAME">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Last name</label>
                                    <input type="text" name="last_name"
                                        value="<?php echo htmlspecialchars($resident['last_name']); ?>"
                                        class="form-control text-uppercase" placeholder="VICEDO" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Suffix (Jr, II, III, etc.)</label>
                                    <input type="text" name="suffix"
                                        value="<?php echo htmlspecialchars($resident['suffix']); ?>"
                                        class="form-control text-uppercase" placeholder="SUFFIX">
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">Contact number</label>
                                    <div class="input-group">
                                        <input type="text" name="phone"
                                            value="<?php echo htmlspecialchars($resident['phone']); ?>"
                                            class="form-control" placeholder="09497739669" required>
                                        <span class="input-group-text bg-light"><i
                                                class="fas fa-phone small text-muted"></i></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Civil Status</label>
                                    <select name="civil_status" class="form-select" required>
                                        <option value="" disabled <?php echo empty($resident['civil_status']) ? 'selected' : ''; ?>>Select Civil Status</option>
                                        <option value="Single" <?php echo ($resident['civil_status'] ?? '') == 'Single' ? 'selected' : ''; ?>>Single</option>
                                        <option value="Married" <?php echo ($resident['civil_status'] ?? '') == 'Married' ? 'selected' : ''; ?>>Married</option>
                                        <option value="Widowed" <?php echo ($resident['civil_status'] ?? '') == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                        <option value="Separated" <?php echo ($resident['civil_status'] ?? '') == 'Separated' ? 'selected' : ''; ?>>Separated</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label d-block mb-2">Sex</label>
                                    <div class="d-flex gap-4 pt-1">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="sex" value="Male"
                                                id="sexM" <?php echo ($resident['sex'] ?? '') == 'Male' ? 'checked' : ''; ?> required>
                                            <label class="form-check-label" for="sexM">Male</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="sex" value="Female"
                                                id="sexF" <?php echo ($resident['sex'] ?? '') == 'Female' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="sexF">Female</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <label class="form-label">Birthday</label>
                                    <div class="input-group">
                                        <input type="date" name="birthdate"
                                            value="<?php echo $resident['birthdate'] ?? ''; ?>" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <label class="form-label">Birth Place</label>
                                    <input type="text" name="birth_place" class="form-control" placeholder="BIRTH PLACE"
                                        required>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <label class="form-label">Purok</label>
                                    <select name="purok" class="form-select" required>
                                        <option value="">Select Purok</option>
                                        <?php for ($i = 1; $i <= 7; $i++): ?>
                                            <option value="Purok <?php echo $i; ?>" <?php echo ($resident['purok'] ?? '') == "Purok $i" ? 'selected' : ''; ?>>Purok <?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-9">
                                    <label class="form-label">Complete Address</label>
                                    <input type="text" name="address"
                                        value="<?php echo htmlspecialchars($resident['address'] ?? ''); ?>"
                                        class="form-control" placeholder="12456" required>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">Religion</label>
                                    <input type="text" name="religion" class="form-control text-uppercase"
                                        placeholder="RELIGION" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Citizenship</label>
                                    <input type="text" name="citizenship" value="Filipino"
                                        class="form-control text-uppercase" placeholder="CITIZENSHIP" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Profession / Occupation</label>
                                    <input type="text" name="occupation" class="form-control text-uppercase"
                                        placeholder="PROFESSION / OCCUPATION" required>
                                </div>
                            </div>

                            <!-- Education Section -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6 border-end pe-4">
                                    <label class="form-label d-block mb-3">HIGHEST EDUCATIONAL ATTAINMENT</label>
                                    <div class="d-flex flex-column gap-2">
                                        <?php
                                        $edu_opts = ['Elementary', 'High School', 'College', 'Post Grad', 'Vocational'];
                                        foreach ($edu_opts as $opt):
                                            ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="educational_attainment"
                                                    value="<?php echo $opt; ?>" id="edu_<?php echo str_replace(' ','_',$opt); ?>" required>
                                                <label class="form-check-label"
                                                    for="edu_<?php echo str_replace(' ','_',$opt); ?>"><?php echo $opt; ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-6 ps-4">
                                    <label class="form-label d-block mb-3">PLEASE SPECIFY</label>
                                    <div class="d-flex flex-column gap-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="edu_status"
                                                value="Graduate" id="gradY">
                                            <label class="form-check-label" for="gradY">Graduate</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="edu_status"
                                                value="Under Graduate" id="gradN">
                                            <label class="form-check-label" for="gradN">Under Graduate</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Indicated Section -->
                            <label class="form-label d-block mb-3">Indicate if you are (you may select
                                multiple):</label>
                            <div class="row g-2 mb-5">
                                <?php
                                $classes = [
                                    'labor' => 'Labor/Employed',
                                    'unemployed' => 'Unemployed',
                                    'pwd' => 'PWD',
                                    'ofw' => 'OFW',
                                    'solo_parent' => 'Solo Parent',
                                    'osy' => 'Out of School Youth (OSY)',
                                    'osc' => 'Out of School Children (OSC)',
                                    'indigenous' => 'Indigenous People'
                                ];
                                foreach ($classes as $key => $val):
                                    ?>
                                    <div class="col-12">
                                        <div class="form-check d-flex align-items-center">
                                            <input class="form-check-input" type="checkbox" name="classifications[]"
                                                value="<?php echo $val; ?>" id="class_<?php echo $key; ?>">
                                            <label class="form-check-label"
                                                for="class_<?php echo $key; ?>"><?php echo $val; ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="text-center pt-4 border-top">
                                <button type="submit" class="btn btn-primary shadow-sm text-uppercase">
                                    Save Profile and Continue
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if ($success): ?>
        <script>
            Swal.fire({
                title: 'Profile Completed!',
                text: '<?php echo addslashes($success); ?>',
                icon: 'success',
                confirmButtonColor: '#0f766e',
                confirmButtonText: 'Go to Dashboard'
            }).then(() => {
                window.location.href = 'index.php';
            });
        </script>
    <?php endif; ?>
</body>

</html>
