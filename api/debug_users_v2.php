<?php
$host = 'localhost';
$db_name = 'qa_platform';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("SELECT id, name, email, role FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "--- USER LIST ---\n";
    foreach($users as $u) {
        echo sprintf("ID: %d | Name: %-15s | Role: %s\n", $u['id'], $u['name'], $u['role']);
    }
    echo "-----------------\n";

} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
