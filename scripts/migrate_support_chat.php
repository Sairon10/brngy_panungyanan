<?php
/**
 * Migration: Add support chat tables
 * Run this once to create the support chat system tables
 */

require_once __DIR__ . '/../config.php';

try {
    $pdo = get_db_connection();
    
    // Create support_chats table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS support_chats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            guest_name VARCHAR(255) DEFAULT NULL,
            guest_contact VARCHAR(255) DEFAULT NULL,
            status ENUM('open', 'waiting', 'active', 'closed') DEFAULT 'open',
            assigned_admin_id INT DEFAULT NULL,
            last_message_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            closed_at TIMESTAMP NULL DEFAULT NULL,
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_admin_id) REFERENCES users (id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_user_id (user_id),
            INDEX idx_assigned_admin (assigned_admin_id),
            CHECK ((user_id IS NOT NULL) OR (guest_name IS NOT NULL AND guest_contact IS NOT NULL))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Add guest columns if table already exists
    try {
        $pdo->exec("ALTER TABLE support_chats ADD COLUMN guest_name VARCHAR(255) DEFAULT NULL");
    } catch (Exception $e) {
        // Column might already exist
    }
    
    try {
        $pdo->exec("ALTER TABLE support_chats ADD COLUMN guest_contact VARCHAR(255) DEFAULT NULL");
    } catch (Exception $e) {
        // Column might already exist
    }
    
    try {
        $pdo->exec("ALTER TABLE support_chats MODIFY COLUMN user_id INT DEFAULT NULL");
    } catch (Exception $e) {
        // Might fail if already nullable
    }
    
    // Create support_messages table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS support_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chat_id INT NOT NULL,
            sender_id INT DEFAULT NULL,
            sender_type ENUM('user', 'admin') NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (chat_id) REFERENCES support_chats (id) ON DELETE CASCADE,
            INDEX idx_chat_id (chat_id),
            INDEX idx_created_at (created_at),
            INDEX idx_is_read (is_read),
            INDEX idx_sender_id (sender_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Update existing table if needed
    try {
        $pdo->exec("ALTER TABLE support_messages MODIFY COLUMN sender_id INT DEFAULT NULL");
    } catch (Exception $e) {
        // Column might already be updated
    }
    
    // Remove foreign key constraint if it exists (for guest users with sender_id = NULL)
    // Try different constraint names that might exist
    $constraintNames = ['support_messages_ibfk_2', '2', 'CONSTRAINT 2'];
    foreach ($constraintNames as $constraintName) {
        try {
            $pdo->exec("ALTER TABLE support_messages DROP FOREIGN KEY `{$constraintName}`");
            echo "✅ Removed foreign key constraint: {$constraintName}\n";
            break; // Stop after successfully removing one
        } catch (Exception $e) {
            // Try next constraint name
            continue;
        }
    }
    
    // Create admin_activity table to track online admins
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_activity (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_online TINYINT(1) DEFAULT 1,
            FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE CASCADE,
            UNIQUE KEY unique_admin (admin_id),
            INDEX idx_is_online (is_online),
            INDEX idx_last_activity (last_activity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✅ Support chat tables created successfully!\n";
    echo "Tables created: support_chats, support_messages, admin_activity\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

