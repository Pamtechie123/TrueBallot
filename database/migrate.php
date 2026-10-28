<?php
// Brings the database up to date. Run it after you change the code that needs new tables or columns.
// It runs every file in database/migrations that has not run before (in number order) and remembers which ones it did.
//
//   Live (Aiven):   C:\xampp\php\php.exe database\migrate.php HOST PORT PASSWORD
//   XAMPP (local):  C:\xampp\php\php.exe database\migrate.php local
//
// A fresh database: import database/schema.sql first, then run this.

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}

$args = array_slice($argv, 1);
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

if (($args[0] ?? '') === 'local') {
    $dsn  = 'mysql:host=' . (getenv('TB_DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('TB_DB_PORT') ?: '3306') . ';dbname=' . (getenv('TB_DB_NAME') ?: 'trueballot') . ';charset=utf8mb4';
    $user = getenv('TB_DB_USER') ?: 'root';
    $pass = getenv('TB_DB_PASS') ?: '';
} elseif (count($args) >= 3) {
    [$host, $port, $pass] = $args;
    $dsn  = "mysql:host=$host;port=$port;dbname=defaultdb;charset=utf8mb4";
    $user = 'avnadmin';
    $ca   = __DIR__ . '/../config/ca.pem';
    if (is_file($ca)) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
    }
} else {
    exit("Usage:\n  php database\\migrate.php HOST PORT PASSWORD   (live, Aiven)\n  php database\\migrate.php local               (XAMPP)\n");
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    exit('Could not connect: ' . $e->getMessage() . "\n");
}

$pdo->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(190) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB');
$done = $pdo->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

$files = glob(__DIR__ . '/migrations/*.sql');
sort($files);
$ran = 0;

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $done, true)) {
        echo "already done  $name\n";
        continue;
    }

    $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($file));
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            exit("FAILED in $name: " . $e->getMessage() . "\nNothing after this file was run. Fix the problem and run the command again.\n");
        }
    }

    $pdo->prepare('INSERT INTO migrations (name, applied_at) VALUES (?, NOW())')->execute([$name]);
    echo "applied       $name\n";
    $ran++;
}

echo $ran === 0 ? "The database is already up to date.\n" : "Done. $ran update(s) applied.\n";