<?php
// Creates the TrueBallot tables on Aiven (or any MySQL) by running database/schema.sql.
//   C:\xampp\php\php.exe database\install.php HOST PORT PASSWORD
// It uses user "avnadmin", database "defaultdb" and config/ca.pem for the secure connection.

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}

[$script, $host, $port, $password] = array_pad($argv, 4, null);
if ($host === null || $port === null || $password === null) {
    exit("Usage: php database\\install.php HOST PORT PASSWORD\n");
}

$ca = __DIR__ . '/../config/ca.pem';
if (!is_file($ca)) {
    exit("config/ca.pem not found. Download the CA certificate from Aiven first.\n");
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=defaultdb;charset=utf8mb4", 'avnadmin', $password, [
        PDO::ATTR_ERRMODE      => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => $ca,
    ]);
} catch (PDOException $e) {
    exit('Could not connect: ' . $e->getMessage() . "\n");
}

// Remove comment lines, then run each statement (they end with ";").
$sql = preg_replace('/^\s*--.*$/m', '', file_get_contents(__DIR__ . '/schema.sql'));
$count = 0;

foreach (array_filter(array_map('trim', explode(";\n", str_replace("\r\n", "\n", $sql)))) as $statement) {
    try {
        $pdo->exec($statement);
        $count++;
    } catch (PDOException $e) {
        echo 'Skipped: ' . $e->getMessage() . "\n";
    }
}

echo "Done. $count statements ran. Tables now: "
    . implode(', ', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)) . "\n";
