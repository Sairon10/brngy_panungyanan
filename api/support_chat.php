<?php
/**
 * Support Chat API
 * Handles chat creation, messaging, and admin operations
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$pdo = get_db_connection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Check authentication for admin-only actions
$adminOnlyActions = ['get_chats', 'assign_chat', 'update_activity', 'delete_chat', 'close_chat'];
if (in_array($action, $adminOnlyActions) && !is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    switch ($action) {
        case 'create_chat':
            // User creates a new support chat
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $userId = null;
            $guestName = null;
            $guestContact = null;
            
            if (is_logged_in()) {
                // Authenticated user
                $userId = $_SESSION['user_id'];
                
                // Check if user has an open chat
                $existing = $pdo->prepare("
                    SELECT id FROM support_chats 
                    WHERE user_id = ? AND status IN ('open', 'waiting', 'active')
                    ORDER BY created_at DESC LIMIT 1
                ");
                $existing->execute([$userId]);
                $existingChat = $existing->fetch();
                
                if ($existingChat) {
                    echo json_encode([
                        'success' => true,
                        'chat_id' => $existingChat['id'],
                        'message' => 'Existing chat found'
                    ]);
                    exit;
                }
            } else {
                // Guest user - require name and contact
                $guestName = trim($_POST['guest_name'] ?? '');
                $guestContact = trim($_POST['guest_contact'] ?? '');
                
                if (empty($guestName) || empty($guestContact)) {
                    throw new Exception('Name and contact information (phone or email) are required');
                }
                
                // Validate contact format (basic check)
                if (!filter_var($guestContact, FILTER_VALIDATE_EMAIL) && !preg_match('/^[0-9+\-\s()]+$/', $guestContact)) {
                    throw new Exception('Please provide a valid email address or phone number');
                }
                
                // Check if guest has an open chat with same contact
                $existing = $pdo->prepare("
                    SELECT id FROM support_chats 
                    WHERE guest_contact = ? AND status IN ('open', 'waiting', 'active')
                    ORDER BY created_at DESC LIMIT 1
                ");
                $existing->execute([$guestContact]);
                $existingChat = $existing->fetch();
                
                if ($existingChat) {
                    echo json_encode([
                        'success' => true,
                        'chat_id' => $existingChat['id'],
                        'message' => 'Existing chat found'
                    ]);
                    exit;
                }
            }
            
            // Find available admin
            $admin = $pdo->prepare("
                SELECT u.id FROM users u
                LEFT JOIN admin_activity aa ON u.id = aa.admin_id
                WHERE u.role = 'admin'
                AND (aa.is_online = 1 OR aa.is_online IS NULL)
                AND (aa.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE) OR aa.last_activity IS NULL)
                ORDER BY (
                    SELECT COUNT(*) FROM support_chats sc 
                    WHERE sc.assigned_admin_id = u.id AND sc.status IN ('open', 'active')
                ) ASC
                LIMIT 1
            ");
            $admin->execute();
            $availableAdmin = $admin->fetch();
            
            $assignedAdminId = $availableAdmin ? $availableAdmin['id'] : null;
            $status = $assignedAdminId ? 'active' : 'waiting';
            
            // Create chat
            $stmt = $pdo->prepare("
                INSERT INTO support_chats (user_id, guest_name, guest_contact, status, assigned_admin_id, last_message_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $guestName, $guestContact, $status, $assignedAdminId]);
            $chatId = $pdo->lastInsertId();
            
            // Add welcome message (use guest name or system for sender)
            $welcomeMsg = $assignedAdminId 
                ? "Hello" . ($guestName ? " {$guestName}" : "") . "! You've been connected to a support agent. How can we help you today?"
                : "Hello" . ($guestName ? " {$guestName}" : "") . "! Your support request has been received. An admin will be with you shortly.";
            
            // For guest users, we need to create a system message
            // Use NULL for guest users (system/admin messages)
            $senderId = $userId ?? null;
            
            $msgStmt = $pdo->prepare("
                INSERT INTO support_messages (chat_id, sender_id, sender_type, message)
                VALUES (?, ?, 'admin', ?)
            ");
            $msgStmt->execute([$chatId, $senderId, $welcomeMsg]);
            
            echo json_encode([
                'success' => true,
                'chat_id' => $chatId,
                'status' => $status,
                'assigned_admin_id' => $assignedAdminId
            ]);
            break;
            
        case 'send_message':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $chatId = $_POST['chat_id'] ?? 0;
            $message = trim($_POST['message'] ?? '');
            
            if (empty($message)) {
                throw new Exception('Message cannot be empty');
            }
            
            // Check if chat is closed
            $chatCheck = $pdo->prepare("SELECT status, user_id, guest_contact, assigned_admin_id, guest_name FROM support_chats WHERE id = ?");
            $chatCheck->execute([$chatId]);
            $chat = $chatCheck->fetch();
            
            if (!$chat) {
                throw new Exception('Chat not found');
            }
            
            if ($chat['status'] === 'closed' && !is_admin()) {
                throw new Exception('This chat has been closed. You cannot send messages to a closed chat.');
            }
            
            // Verify chat access (if not admin)
            if (!is_admin()) {
                if (is_logged_in()) {
                    // Authenticated user - check user_id
                    if ($chat['user_id'] != $_SESSION['user_id']) {
                        throw new Exception('Chat not found');
                    }
                } else {
                    // Guest user - check guest_contact matches session or provided contact
                    $guestContact = $_POST['guest_contact'] ?? '';
                    if (empty($guestContact) || $chat['guest_contact'] !== $guestContact) {
                        throw new Exception('Chat not found or access denied');
                    }
                }
            }
            
            $senderType = is_admin() ? 'admin' : 'user';
            $senderId = is_logged_in() ? $_SESSION['user_id'] : null; // NULL for guest users
            
            // Insert message
            $stmt = $pdo->prepare("
                INSERT INTO support_messages (chat_id, sender_id, sender_type, message)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$chatId, $senderId, $senderType, $message]);
            
            // Update chat status and activity
            if ($senderType === 'admin' && $chat['status'] === 'waiting') {
                $pdo->prepare("UPDATE support_chats SET last_message_at = NOW(), status = 'active' WHERE id = ?")
                    ->execute([$chatId]);
            } else {
                $pdo->prepare("UPDATE support_chats SET last_message_at = NOW() WHERE id = ?")
                    ->execute([$chatId]);
            }
            
            // Send In-App Notification
            if ($senderType === 'user') {
                // User sent a message -> Notify admins
                $notifMsg = "New message from " . ($chat['guest_name'] ?? 'User') . " in support chat.";
                if (!empty($chat['assigned_admin_id'])) {
                    // Notify assigned admin
                    $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_chat_id) VALUES (?, "chat_update", "New Chat Message", ?, ?)')
                        ->execute([$chat['assigned_admin_id'], $notifMsg, $chatId]);
                } else {
                    // Notify all admins since unassigned
                    $admin_stmt = $pdo->query('SELECT id FROM users WHERE role = "admin"');
                    foreach ($admin_stmt->fetchAll() as $admin) {
                        $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_chat_id) VALUES (?, "chat_update", "New Chat Message", ?, ?)')
                            ->execute([$admin['id'], $notifMsg, $chatId]);
                    }
                }
            } else {
                // Admin sent a message -> Notify user (if they are a registered user)
                if (!empty($chat['user_id'])) {
                    $notifMsg = "You received a new message from Support.";
                    $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_chat_id) VALUES (?, "chat_update", "Support Message", ?, ?)')
                        ->execute([$chat['user_id'], $notifMsg, $chatId]);
                }
            }
            
            // Mark messages as read for sender
            if ($senderId !== null) {
                $pdo->prepare("
                    UPDATE support_messages 
                    SET is_read = 1 
                    WHERE chat_id = ? AND sender_id = ?
                ")->execute([$chatId, $senderId]);
            } else {
                // For guest users, mark their own messages as read
                $pdo->prepare("
                    UPDATE support_messages 
                    SET is_read = 1 
                    WHERE chat_id = ? AND sender_id IS NULL AND sender_type = 'user'
                ")->execute([$chatId]);
            }
            
            echo json_encode(['success' => true, 'message_id' => $pdo->lastInsertId()]);
            break;
            
        case 'get_messages':
            $chatId = $_GET['chat_id'] ?? 0;
            $lastMessageId = $_GET['last_message_id'] ?? 0;
            
            // Verify chat access
            if (is_admin()) {
                $verify = $pdo->prepare("SELECT id FROM support_chats WHERE id = ?");
                $verify->execute([$chatId]);
            } else if (is_logged_in()) {
                $verify = $pdo->prepare("SELECT id FROM support_chats WHERE id = ? AND user_id = ?");
                $verify->execute([$chatId, $_SESSION['user_id']]);
            } else {
                // Guest user - check guest_contact
                $guestContact = $_GET['guest_contact'] ?? '';
                if (empty($guestContact)) {
                    throw new Exception('Contact information required');
                }
                $verify = $pdo->prepare("SELECT id FROM support_chats WHERE id = ? AND guest_contact = ?");
                $verify->execute([$chatId, $guestContact]);
            }
            
            if (!$verify->fetch()) {
                throw new Exception('Chat not found');
            }
            
            // Get chat info for guest name
            $chatInfo = $pdo->prepare("SELECT guest_name FROM support_chats WHERE id = ?");
            $chatInfo->execute([$chatId]);
            $chat = $chatInfo->fetch();
            
            // Get messages
            $stmt = $pdo->prepare("
                SELECT sm.*, 
                       COALESCE(u.full_name, ?) as full_name,
                       u.role
                FROM support_messages sm
                LEFT JOIN users u ON sm.sender_id = u.id AND sm.sender_id IS NOT NULL
                WHERE sm.chat_id = ? AND sm.id > ?
                ORDER BY sm.created_at ASC
            ");
            $guestName = $chat['guest_name'] ?? 'Guest';
            $stmt->execute([$guestName, $chatId, $lastMessageId]);
            $messages = $stmt->fetchAll();
            
            // Update guest names for messages sent by guest (sender_id IS NULL)
            foreach ($messages as &$msg) {
                if ($msg['sender_id'] === null && $msg['sender_type'] === 'user') {
                    $msg['full_name'] = $guestName;
                }
            }
            
            // Mark messages as read for current user
            if (!empty($messages)) {
                $currentUserId = is_logged_in() ? $_SESSION['user_id'] : null;
                if ($currentUserId !== null) {
                    $pdo->prepare("
                        UPDATE support_messages 
                        SET is_read = 1 
                        WHERE chat_id = ? AND sender_id != ? AND is_read = 0
                    ")->execute([$chatId, $currentUserId]);
                } else {
                    // For guest users, mark admin messages as read
                    $pdo->prepare("
                        UPDATE support_messages 
                        SET is_read = 1 
                        WHERE chat_id = ? AND sender_type = 'admin' AND is_read = 0
                    ")->execute([$chatId]);
                }
            }
            
            echo json_encode(['success' => true, 'messages' => $messages]);
            break;
            
        case 'get_chats':
            // Admin only - get all chats
            if (!is_admin()) {
                throw new Exception('Admin access required');
            }
            
            $status = $_GET['status'] ?? 'all';
            $where = $status !== 'all' ? "WHERE sc.status = ?" : "";
            $params = $status !== 'all' ? [$status] : [];
            
            $stmt = $pdo->prepare("
                SELECT sc.*, 
                       COALESCE(u.full_name, sc.guest_name) as user_name,
                       COALESCE(u.email, sc.guest_contact) as user_email,
                       (SELECT COUNT(*) FROM support_messages sm 
                        WHERE sm.chat_id = sc.id AND sm.sender_type = 'user' AND sm.is_read = 0) as unread_count,
                       (SELECT message FROM support_messages sm 
                        WHERE sm.chat_id = sc.id 
                        ORDER BY sm.created_at DESC LIMIT 1) as last_message,
                       sc.closed_at
                FROM support_chats sc
                LEFT JOIN users u ON sc.user_id = u.id
                $where
                ORDER BY sc.last_message_at DESC
            ");
            $stmt->execute($params);
            $chats = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'chats' => $chats]);
            break;
            
        case 'assign_chat':
            // Admin assigns themselves to a chat
            if (!is_admin()) {
                throw new Exception('Admin access required');
            }
            
            $chatId = $_POST['chat_id'] ?? 0;
            
            $stmt = $pdo->prepare("
                UPDATE support_chats 
                SET assigned_admin_id = ?, status = 'active'
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $chatId]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'close_chat':
            $chatId = $_POST['chat_id'] ?? 0;
            
            // Verify access (only admins can close chats)
            if (!is_admin()) {
                throw new Exception('Only administrators can close chats');
            }
            
            $verify = $pdo->prepare("SELECT id FROM support_chats WHERE id = ?");
            $verify->execute([$chatId]);
            
            if (!$verify->fetch()) {
                throw new Exception('Chat not found');
            }
            
            $stmt = $pdo->prepare("
                UPDATE support_chats 
                SET status = 'closed', closed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$chatId]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'delete_chat':
            $chatId = $_POST['chat_id'] ?? 0;
            
            // Verify access (only admins can delete chats)
            if (!is_admin()) {
                throw new Exception('Only administrators can delete chats');
            }
            
            // Delete messages first (if no CASCADE)
            $pdo->prepare("DELETE FROM support_messages WHERE chat_id = ?")->execute([$chatId]);
            
            // Delete chat
            $stmt = $pdo->prepare("DELETE FROM support_chats WHERE id = ?");
            $stmt->execute([$chatId]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'get_chat_status':
            // Get chat status
            $chatId = $_GET['chat_id'] ?? 0;
            
            // Verify chat access
            if (is_admin()) {
                $verify = $pdo->prepare("SELECT status, closed_at FROM support_chats WHERE id = ?");
                $verify->execute([$chatId]);
            } else if (is_logged_in()) {
                $verify = $pdo->prepare("SELECT status, closed_at FROM support_chats WHERE id = ? AND user_id = ?");
                $verify->execute([$chatId, $_SESSION['user_id']]);
            } else {
                $guestContact = $_GET['guest_contact'] ?? '';
                if (empty($guestContact)) {
                    throw new Exception('Contact information required');
                }
                $verify = $pdo->prepare("SELECT status, closed_at FROM support_chats WHERE id = ? AND guest_contact = ?");
                $verify->execute([$chatId, $guestContact]);
            }
            
            $chat = $verify->fetch();
            if (!$chat) {
                throw new Exception('Chat not found');
            }
            
            echo json_encode(['success' => true, 'status' => $chat['status'], 'closed_at' => $chat['closed_at']]);
            break;
            
        case 'update_activity':
            // Update admin activity (called periodically)
            if (!is_admin()) {
                throw new Exception('Admin access required');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO admin_activity (admin_id, is_online, last_activity)
                VALUES (?, 1, NOW())
                ON DUPLICATE KEY UPDATE 
                    is_online = 1,
                    last_activity = NOW()
            ");
            $stmt->execute([$_SESSION['user_id']]);
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

