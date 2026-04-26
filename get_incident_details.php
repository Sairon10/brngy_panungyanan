<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

try {
    $pdo = get_db_connection();
    // Updated query to include resident_phone via residents table join
    $stmt = $pdo->prepare("SELECT i.*, u.full_name as resident_name, u.email as resident_email, r.phone as resident_phone 
                           FROM incidents i 
                           JOIN users u ON i.user_id = u.id 
                           LEFT JOIN residents r ON u.id = r.user_id
                           WHERE i.id = ? AND i.user_id = ? 
                           LIMIT 1");
    $stmt->execute([$id, $_SESSION['user_id']]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($incident) {
        // Format date
        $incident['formatted_date'] = date('M j, Y g:i A', strtotime($incident['created_at']));
        
        // Format status label and class
        $statusBadge = match($incident['status']) {
            'submitted' => 'bg-amber-100 text-amber-700',
            'in_review' => 'bg-blue-100 text-blue-700',
            'resolved' => 'bg-teal-100 text-teal-700',
            'closed' => 'bg-gray-100 text-gray-600',
            'canceled' => 'bg-rose-100 text-rose-700',
            default => 'bg-light text-dark'
        };
        $incident['status_label'] = $incident['status'] === 'submitted' ? 'Pending' : ucfirst($incident['status']);
        $incident['status_class'] = $statusBadge;

        // Fetch messages/conversation
        $msgStmt = $pdo->prepare("SELECT im.*, u.full_name, u.role 
                                FROM incident_messages im 
                                JOIN users u ON u.id = im.user_id 
                                WHERE im.incident_id = ? 
                                ORDER BY im.created_at ASC");
        $msgStmt->execute([$id]);
        $incident['messages'] = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format message dates
        foreach ($incident['messages'] as &$msg) {
            $msg['formatted_time'] = date('M j, Y g:i A', strtotime($msg['created_at']));
        }
        
        echo json_encode($incident);
    } else {
        echo json_encode(['error' => 'Incident not found']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
