<?php
include 'config.php';
$stmt = $pdo->query("SELECT id, name, email, role, level FROM users LIMIT 10");
echo "Current Users:\n";
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($users as $u) {
    echo "ID: " . $u['id'] . " | Name: " . $u['name'] . " | Role: " . $u['role'] . " | Level: " . ($u['level'] ?? 'NULL') . "\n";
}
?>
