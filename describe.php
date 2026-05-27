<?php
require 'config.php';
$stmt = get_db_connection()->query('DESCRIBE residents');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
