<?php
$file = 'config.php';
$c = file_get_contents($file);

// Add default timezone
if (strpos($c, "date_default_timezone_set('Asia/Manila');") === false) {
    $c = preg_replace('/<\?php/', "<?php\ndate_default_timezone_set('Asia/Manila');", $c, 1);
}

// Add MySQL timezone init command
if (strpos($c, 'PDO::MYSQL_ATTR_INIT_COMMAND') === false) {
    $old_options = <<<EOD
        \$options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
EOD;
    $new_options = <<<EOD
        \$options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'"
        ];
EOD;
    $c = str_replace($old_options, $new_options, $c);
}

file_put_contents($file, $c);
echo "Updated config.php\n";
