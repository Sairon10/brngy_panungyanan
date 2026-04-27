<?php
require_once __DIR__ . '/../config.php';
if (!is_admin()) {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = get_db_connection();
$type = $_GET['type'] ?? '';
$start = ($_GET['start'] ?? date('Y-m-01')) . ' 00:00:00';
$end = ($_GET['end'] ?? date('Y-m-t')) . ' 23:59:59';

header('Content-Type: application/json');

switch ($type) {
    case 'male':
        $stmt = $pdo->query("
            SELECT 'Resident' as source, u.full_name, r.birthdate, r.sex, r.civil_status, r.address, r.phone, '' as owner_name
            FROM residents r JOIN users u ON u.id = r.user_id
            WHERE r.sex = 'Male' AND r.verification_status = 'verified'
            UNION ALL
            SELECT 'Family Member' as source, fm.full_name, fm.birthdate, fm.sex, fm.civil_status, '' as address, '' as phone, u2.full_name as owner_name
            FROM family_members fm
            JOIN users u2 ON fm.user_id = u2.id
            WHERE fm.sex = 'Male'
            ORDER BY full_name
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'female':
        $stmt = $pdo->query("
            SELECT 'Resident' as source, u.full_name, r.birthdate, r.sex, r.civil_status, r.address, r.phone, '' as owner_name
            FROM residents r JOIN users u ON u.id = r.user_id
            WHERE r.sex = 'Female' AND r.verification_status = 'verified'
            UNION ALL
            SELECT 'Family Member' as source, fm.full_name, fm.birthdate, fm.sex, fm.civil_status, '' as address, '' as phone, u2.full_name as owner_name
            FROM family_members fm
            JOIN users u2 ON fm.user_id = u2.id
            WHERE fm.sex = 'Female'
            ORDER BY full_name
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'seniors':
        $stmt = $pdo->query("
            SELECT 'Resident' as source, u.full_name, r.birthdate, r.sex, r.address, r.phone, '' as owner_name
            FROM residents r JOIN users u ON u.id = r.user_id
            WHERE r.is_senior = 1 AND r.verification_status = 'verified'
            UNION ALL
            SELECT 'Family Member' as source, fm.full_name, fm.birthdate, fm.sex, '' as address, '' as phone, u2.full_name as owner_name
            FROM family_members fm
            JOIN users u2 ON fm.user_id = u2.id
            WHERE fm.is_senior = 1
            ORDER BY full_name
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'pwds':
        $stmt = $pdo->query("
            SELECT 'Resident' as source, u.full_name, r.birthdate, r.sex, r.address, r.phone, '' as owner_name
            FROM residents r JOIN users u ON u.id = r.user_id
            WHERE r.is_pwd = 1 AND r.verification_status = 'verified'
            UNION ALL
            SELECT 'Family Member' as source, fm.full_name, fm.birthdate, fm.sex, '' as address, '' as phone, u2.full_name as owner_name
            FROM family_members fm
            JOIN users u2 ON fm.user_id = u2.id
            WHERE fm.is_pwd = 1
            ORDER BY full_name
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'solo_parents':
        $stmt = $pdo->query("
            SELECT u.full_name, r.birthdate, r.sex, r.address, r.phone
            FROM residents r JOIN users u ON u.id = r.user_id
            WHERE r.is_solo_parent = 1 AND r.verification_status = 'verified'
            ORDER BY u.full_name
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'total_residents':
        $stmt = $pdo->query("SELECT r.*, u.full_name, u.email FROM residents r JOIN users u ON u.id = r.user_id WHERE r.verification_status = 'verified' ORDER BY u.full_name");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'doc_requests':
        $stmt = $pdo->prepare("
            SELECT dr.id, dr.user_id, dr.family_member_id, dr.doc_type, dr.purpose, dr.status, dr.created_at,
                   COALESCE(fm.full_name, u.full_name) as display_name,
                   u.full_name as requester_name
            FROM document_requests dr
            JOIN users u ON dr.user_id = u.id
            LEFT JOIN family_members fm ON dr.family_member_id = fm.id
            WHERE dr.created_at BETWEEN ? AND ?
            
            UNION ALL
            
            SELECT bc.id, bc.user_id, NULL as family_member_id, 'Barangay Clearance' as doc_type, bc.purpose, bc.status, bc.created_at,
                   u.full_name as display_name,
                   u.full_name as requester_name
            FROM barangay_clearances bc
            JOIN users u ON bc.user_id = u.id
            WHERE bc.created_at BETWEEN ? AND ?
            
            ORDER BY created_at DESC
        ");
        $stmt->execute([$start, $end, $start, $end]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            if (!empty($row['purpose']) && preg_match('/\[Walk-in Requestor: (.*?)\]/', $row['purpose'], $m)) {
                $row['display_name'] = trim($m[1]);
                $row['requester_name'] = trim($m[1]);
                $row['requestor_type'] = 'walkin';
                // Strip the tag from the purpose for cleaner display if needed
                $row['purpose'] = trim(str_replace($m[0], '', $row['purpose']));
            }
        }
        
        echo json_encode($results);
        break;

    case 'households':
        // Fetch all verified residents (Heads) and all family members (Members)
        // We fetch all so we can print complete households, but we will filter in buildTable to show only Heads
        $stmt = $pdo->query("
            SELECT 'Head' as role, r.user_id, u.first_name, u.middle_name, u.last_name, u.suffix, u.full_name, u.email, r.birthdate, r.sex, r.civil_status, r.address, r.purok, r.phone, r.birth_place, r.occupation, r.classification, r.religion, r.educational_attainment, r.educational_status, r.citizenship
            FROM residents r JOIN users u ON u.id = r.user_id
            WHERE r.verification_status = 'verified' AND u.role = 'resident'
            UNION ALL
            SELECT 'Member' as role, fm.user_id, fm.first_name, fm.middle_name, fm.last_name, fm.suffix, fm.full_name, '' as email, fm.birthdate, fm.sex, fm.civil_status, r2.address, r2.purok, r2.phone, fm.birth_place, fm.occupation, fm.classification, fm.religion, fm.educational_attainment, fm.educational_status, fm.citizenship
            FROM family_members fm
            JOIN residents r2 ON fm.user_id = r2.user_id
            JOIN users u2 ON r2.user_id = u2.id
            WHERE u2.role = 'resident'
            ORDER BY address, role DESC, full_name
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'summary':
        // Comprehensive stats for RBI Form C
        $residents = $pdo->query("SELECT r.*, u.full_name FROM residents r JOIN users u ON u.id = r.user_id WHERE r.verification_status = 'verified'")->fetchAll(PDO::FETCH_ASSOC);
        $family = $pdo->query("SELECT fm.*, r.address, r.purok FROM family_members fm JOIN residents r ON fm.user_id = r.user_id")->fetchAll(PDO::FETCH_ASSOC);
        
        $all = array_merge($residents, $family);
        
        $stats = [
            'total_inhabitants' => count($all),
            'total_households' => $pdo->query("SELECT COUNT(DISTINCT address) FROM residents WHERE verification_status = 'verified'")->fetchColumn(),
            'age_brackets' => [],
            'sectors' => [
                'Employed' => ['M' => 0, 'F' => 0],
                'Unemployed' => ['M' => 0, 'F' => 0],
                'PWD' => ['M' => 0, 'F' => 0],
                'OFW' => ['M' => 0, 'F' => 0],
                'Solo Parent' => ['M' => 0, 'F' => 0],
                'OSY' => ['M' => 0, 'F' => 0],
                'OSC' => ['M' => 0, 'F' => 0],
                'Indigenous' => ['M' => 0, 'F' => 0],
                'Single' => ['M' => 0, 'F' => 0],
                'Married' => ['M' => 0, 'F' => 0],
                'Widowed' => ['M' => 0, 'F' => 0],
                'Filipino' => ['M' => 0, 'F' => 0],
                'Foreigner' => ['M' => 0, 'F' => 0]
            ]
        ];

        // Initialize Age Brackets
        $brackets = ["0-4","5-9","10-14","15-19","20-24","25-29","30-34","35-39","40-44","45-49","50-54","55-59","60-64","65-69","70-74","75-79","80+"];
        foreach($brackets as $b) $stats['age_brackets'][$b] = ['M' => 0, 'F' => 0];

        foreach($all as $p) {
            $sex = (isset($p['sex']) && strtolower($p['sex']) === 'female') ? 'F' : 'M';
            
            // Age Bracket
            if (!empty($p['birthdate'])) {
                $age = date_diff(date_create($p['birthdate']), date_create('now'))->y;
                $found = false;
                if ($age >= 80) { $stats['age_brackets']['80+'][$sex]++; }
                else {
                    foreach($brackets as $b) {
                        if (strpos($b, '-') === false) continue;
                        list($low, $high) = explode('-', $b);
                        if ($age >= $low && $age <= $high) {
                            $stats['age_brackets'][$b][$sex]++;
                            break;
                        }
                    }
                }
            }

            // Sectors & Status
            $occ = strtolower(trim($p['occupation'] ?? ''));
            $class = strtolower($p['classification'] ?? '');
            
            $is_unemployed = ($occ === 'unemployed' || $occ === 'none' || $occ === 'n/a' || $occ === 'student' || strpos($class, 'unemployed') !== false);
            $is_employed = (!$is_unemployed && $occ !== '') || strpos($class, 'employed') !== false || strpos($class, 'labor') !== false;

            if ($is_employed && !$is_unemployed) {
                $stats['sectors']['Employed'][$sex]++;
            } elseif ($is_unemployed) {
                $stats['sectors']['Unemployed'][$sex]++;
            }
            
            if (!empty($p['is_pwd'])) $stats['sectors']['PWD'][$sex]++;
            if (!empty($p['is_solo_parent'])) $stats['sectors']['Solo Parent'][$sex]++;
            
            if (strpos($class, 'ofw') !== false) $stats['sectors']['OFW'][$sex]++;
            if (strpos($class, 'osy') !== false) $stats['sectors']['OSY'][$sex]++;
            if (strpos($class, 'osc') !== false) $stats['sectors']['OSC'][$sex]++;
            if (strpos($class, 'indigenous') !== false) $stats['sectors']['Indigenous'][$sex]++;

            $status = $p['civil_status'] ?? 'Single';
            if (isset($stats['sectors'][$status])) $stats['sectors'][$status][$sex]++;

            $cite = strtolower($p['citizenship'] ?? 'filipino');
            if (strpos($cite, 'filipino') !== false) $stats['sectors']['Filipino'][$sex]++;
            else $stats['sectors']['Foreigner'][$sex]++;
        }

        echo json_encode($stats);
        break;

    case 'incidents':
        $stmt = $pdo->prepare("
            SELECT i.*, u.full_name
            FROM incidents i
            JOIN users u ON i.user_id = u.id
            WHERE i.created_at BETWEEN ? AND ?
            ORDER BY i.created_at DESC
        ");
        $stmt->execute([$start, $end]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    default:
        echo json_encode([]);
}
