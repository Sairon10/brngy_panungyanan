<?php
require 'config.php';
$stmt = get_db_connection()->query("SELECT NOW(), @@session.time_zone, @@global.time_zone");
print_r($stmt->fetchAll());
