<?php
require 'config.php';
$stmt = get_db_connection()->query('SHOW COLUMNS FROM family_members');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
