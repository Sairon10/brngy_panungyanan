<?php
/**
 * Migration: Create incident_messages table for conversation threads
 * Run this once to add support for multiple messages in incident conversations
 */

require_once __DIR__ . '/../config.php';

$pdo = get_db_connection();

try {
    // Create incident_messages table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS incident_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            incident_id INT NOT NULL,
            user_id INT NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (incident_id) REFERENCES incidents (id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            INDEX idx_incident_id (incident_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Migrate existing admin_response to messages
    $stmt = $pdo->query("SELECT id, user_id, description, admin_response, admin_response_by, admin_response_at, created_at FROM incidents WHERE admin_response IS NOT NULL");
    $incidents = $stmt->fetchAll();
    
    foreach ($incidents as $incident) {
        // Insert original incident description as first message
        $pdo->prepare("INSERT INTO incident_messages (incident_id, user_id, message, created_at) VALUES (?, ?, ?, ?)")
            ->execute([$incident['id'], $incident['user_id'], $incident['description'], $incident['created_at']]);
        
        // Insert admin response as second message
        if ($incident['admin_response'] && $incident['admin_response_by']) {
            $pdo->prepare("INSERT INTO incident_messages (incident_id, user_id, message, created_at) VALUES (?, ?, ?, ?)")
                ->execute([$incident['id'], $incident['admin_response_by'], $incident['admin_response'], $incident['admin_response_at']]);
        }
    }
    
    echo "Migration completed successfully!\n";
    echo "Created incident_messages table and migrated existing data.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

