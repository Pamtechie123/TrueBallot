<?php
// Usage: php database\change_admin_email.php 
[$script, $host, $port, $password, $email] = array_pad($argv, 5, null);
if ($email === null) {
    exit("Usage: php database\\change_admin_email.php HOST PORT PASSWORD new-email\n");
}
$email = strtolower(trim($email));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("That is not a valid email address.\n");
}

$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
$ca = __DIR__ . '/../config/ca.pem';
if (is_file($ca)) {
    $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=defaultdb;charset=utf8mb4", 'avnadmin', $password, $options);
    $change = $pdo->prepare("UPDATE users SET email = ? WHERE role = 'admin' ORDER BY id LIMIT 1");
    $change->execute([$email]);
    echo $change->rowCount() === 1 ? "Done. The admin email is now $email\n" : "Nothing changed.\n";
    foreach ($pdo->query("SELECT id, email, role FROM users WHERE role = 'admin'") as $row) {
        echo "Admin in the database: {$row['email']}\n";
    }
} catch (PDOException $e) {
    exit('Failed: ' . $e->getMessage() . "\n");
}