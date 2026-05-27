<?php
$file = 'config.php';
$c = file_get_contents($file);

$func = <<<EOD
function get_db_connection(): PDO
{
    global \$db_host, \$db_name, \$db_user, \$db_pass, \$db_charset;

    try {
        \$dsn = "mysql:host={\$db_host};dbname={\$db_name};charset={\$db_charset}";
        \$options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'"
        ];
        return new PDO(\$dsn, \$db_user, \$db_pass, \$options);
    } catch (PDOException \$e) {
        // For production, you might want to log this and show a generic message
        die("Database connection failed: " . \$e->getMessage());
    }
}
EOD;

$c = str_replace("// PDO connection\n}\n\n", "// PDO connection\n" . $func . "\n\n", $c);
file_put_contents($file, $c);
echo "Fixed config.php\n";
