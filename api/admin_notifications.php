<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!is_admin()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = get_db_connection();
$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per_page = 5;
    $offset = ($page - 1) * $per_page;

    // Total count
    $total_stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ?");
    $total_stmt->execute([$_SESSION['user_id']]);
    $total = (int)($total_stmt->fetch()['c'] ?? 0);

    // Unread count
    $unread_stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0");
    $unread_stmt->execute([$_SESSION['user_id']]);
    $unread = (int)($unread_stmt->fetch()['c'] ?? 0);

    // Fetch notifications
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$_SESSION['user_id'], $per_page, $offset]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build link for each notification
    foreach ($notifications as &$n) {
        $n['link'] = build_notification_link($n);
        $n['created_at_formatted'] = date('M j, Y g:i A', strtotime($n['created_at']));
        $n['time_ago'] = time_ago(strtotime($n['created_at']));
    }

    echo json_encode([
        'notifications' => $notifications,
        'total' => $total,
        'per_page' => $per_page,
        'page' => $page,
        'total_pages' => ceil($total / $per_page),
        'unread_count' => $unread,
    ]);

} elseif ($action === 'mark_read' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);

    // Return the link so JS can redirect after marking read
    $notif_stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ?");
    $notif_stmt->execute([$id, $_SESSION['user_id']]);
    $notif = $notif_stmt->fetch(PDO::FETCH_ASSOC);
    $link = $notif ? build_notification_link($notif) : 'index.php';

    echo json_encode(['success' => true, 'link' => $link]);

} elseif ($action === 'mark_all_read') {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['success' => true]);

} elseif ($action === 'unread_count') {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['unread_count' => (int)($stmt->fetch()['c'] ?? 0)]);
} elseif ($action === 'delete_read') {
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['success' => true]);
}

function build_notification_link(array $n): string {
    $type = $n['type'] ?? 'general';
    $incident_id = $n['related_incident_id'] ?? null;
    $request_id  = $n['related_request_id']  ?? null;
    $chat_id     = $n['related_chat_id']     ?? null;
    $title       = strtolower($n['title'] ?? '');
    $message     = strtolower($n['message'] ?? '');

    if ($type === 'incident_update' || $type === 'incident_response') {
        return $incident_id ? "incident_details.php?id={$incident_id}" : 'incidents.php';
    }
    if ($type === 'request_update') {
        return $request_id ? "requests.php?highlight={$request_id}" : 'requests.php';
    }
    if ($type === 'chat_update') {
        return $chat_id ? "support_chats.php?chat={$chat_id}" : 'support_chats.php';
    }
    if ($type === 'verification_update') {
        return 'id_verifications.php';
    }

    // Infer from title/message for 'general' type
    if (str_contains($title, 'incident') || str_contains($message, 'incident')) {
        return $incident_id ? "incident_details.php?id={$incident_id}" : 'incidents.php';
    }
    if (str_contains($title, 'request') || str_contains($message, 'request') || str_contains($title, 'clearance') || str_contains($message, 'clearance')) {
        return 'requests.php';
    }
    if (str_contains($title, 'chat') || str_contains($message, 'chat') || str_contains($title, 'support') || str_contains($message, 'support')) {
        return 'support_chats.php';
    }
    if (str_contains($title, 'verif') || str_contains($message, 'verif')) {
        return 'id_verifications.php';
    }

    return 'index.php';
}

function time_ago(int $timestamp): string {
    $diff = time() - $timestamp;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', $timestamp);
}
