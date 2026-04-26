-- MySQL schema for Barangay System
CREATE DATABASE IF NOT EXISTS barangay_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE barangay_system;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(190) NOT NULL,
    first_name VARCHAR(100) DEFAULT NULL,
    last_name VARCHAR(100) DEFAULT NULL,
    middle_name VARCHAR(100) DEFAULT NULL,
    suffix VARCHAR(20) DEFAULT NULL,
    role ENUM('resident', 'admin') NOT NULL DEFAULT 'resident',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS residents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    address VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    birthdate DATE DEFAULT NULL,
    citizenship VARCHAR(100) DEFAULT NULL,
    civil_status ENUM('Single', 'Married', 'Widowed', 'Divorced', 'Separated') DEFAULT NULL,
    sex ENUM('Male', 'Female', 'Other') DEFAULT NULL,
    household_id VARCHAR(64) DEFAULT NULL,
    barangay_id VARCHAR(64) DEFAULT NULL,
    purok VARCHAR(100) DEFAULT NULL,
    verification_status ENUM(
        'pending',
        'verified',
        'rejected'
    ) DEFAULT 'pending',
    id_document_path VARCHAR(255) DEFAULT NULL,
    verification_notes TEXT DEFAULT NULL,
    verified_at TIMESTAMP NULL DEFAULT NULL,
    verified_by INT DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS document_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    doc_type VARCHAR(100) NOT NULL,
    purpose VARCHAR(255) DEFAULT NULL,
    status ENUM(
        'pending',
        'approved',
        'rejected',
        'released',
        'canceled'
    ) DEFAULT 'pending',
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    description TEXT NOT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    status ENUM(
        'submitted',
        'in_review',
        'resolved'
    ) DEFAULT 'submitted',
    admin_response TEXT DEFAULT NULL,
    admin_response_by INT DEFAULT NULL,
    admin_response_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (admin_response_by) REFERENCES users (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM(
        'incident_response',
        'incident_update',
        'general'
    ) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    related_incident_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (related_incident_id) REFERENCES incidents (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(128) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS resident_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NULL,
    full_name VARCHAR(190) NOT NULL,
    first_name VARCHAR(100) DEFAULT NULL,
    last_name VARCHAR(100) DEFAULT NULL,
    middle_name VARCHAR(100) DEFAULT NULL,
    suffix VARCHAR(20) DEFAULT NULL,
    address VARCHAR(255) NOT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    birthdate DATE DEFAULT NULL,
    sex ENUM('Male', 'Female', 'Other') DEFAULT NULL,
    citizenship VARCHAR(100) DEFAULT NULL,
    civil_status ENUM('Single', 'Married', 'Widowed', 'Divorced', 'Separated') DEFAULT NULL,
    household_id VARCHAR(64) DEFAULT NULL,
    barangay_id VARCHAR(64) DEFAULT NULL,
    purok VARCHAR(100) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `document_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `pdf_template_path` varchar(255) DEFAULT NULL,
  `requires_validity` tinyint(1) DEFAULT 0,
  `requires_special_handling` tinyint(1) DEFAULT 0,
  `display_order` int(11) DEFAULT 0,
  `price` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS barangay_clearances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    clearance_number VARCHAR(50) NOT NULL UNIQUE,
    purpose TEXT NOT NULL,
    validity_days INT NOT NULL DEFAULT 30,
    status ENUM(
        'pending',
        'approved',
        'rejected',
        'released',
        'canceled'
    ) DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    approved_by INT DEFAULT NULL,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    pdf_generated_at TIMESTAMP NULL DEFAULT NULL,
    pdf_generated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL,
    FOREIGN KEY (pdf_generated_by) REFERENCES users (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS family_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(190) NOT NULL,
    relationship VARCHAR(100) NOT NULL,
    birthdate DATE DEFAULT NULL,
    sex ENUM('Male', 'Female') DEFAULT NULL,
    is_pwd TINYINT(1) NOT NULL DEFAULT 0,
    is_senior TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Note: document_requests and barangay_clearances also have:
--   family_member_id INT DEFAULT NULL (FK to family_members.id ON DELETE SET NULL)
--   requestor_type ENUM('self','family_member') DEFAULT 'self'

CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    media_path VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Seed admin account (email: admin@panungyanan.local, password: Admin@1234)
INSERT INTO
    users (
        email,
        password_hash,
        full_name,
        role
    )
VALUES (
        'admin@panungyanan.local',
        '$2y$12$DGMaKGzi5beDp4wa3HeKY.7wLpgAUZtOYbVasu10RMB8BB6/F9NHS',
        'System Administrator',
        'admin'
    )
ON DUPLICATE KEY UPDATE
    email = email;