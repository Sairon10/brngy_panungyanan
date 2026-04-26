<?php
// Local-only admin password reset helper
require_once __DIR__ . '/../config.php';

// Allow only from localhost
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'])) {
	http_response_code(403);
	echo 'Forbidden';
	exit;
}

$email = 'admin@panungyanan.local';
$newPassword = 'Admin@1234';

try {
	$pdo = get_db_connection();
	$hash = password_hash($newPassword, PASSWORD_BCRYPT);
	// Create admin if missing, otherwise update password
	$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
	$stmt->execute([$email]);
	$user = $stmt->fetch();
	if ($user) {
		$pdo->prepare("UPDATE users SET password_hash = ?, role = 'admin' WHERE email = ?")
			->execute([$hash, $email]);
		echo 'Updated existing admin password. You can now login.';
	} else {
		$pdo->prepare("INSERT INTO users (email, password_hash, full_name, role) VALUES (?,?,?,'admin')")
			->execute([$email, $hash, 'System Administrator']);
		echo 'Created admin user and set password. You can now login.';
	}
} catch (Exception $e) {
	echo 'Error: ' . htmlspecialchars($e->getMessage());
}

echo "\n\nIMPORTANT: Delete this file after use: /barangay_system/scripts/reset_admin.php";
?>


