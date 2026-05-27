<?php
require 'config.php';
$stmt = get_db_connection()->query("SELECT full_name, first_name, last_name, middle_name FROM users WHERE role = 'resident' LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
