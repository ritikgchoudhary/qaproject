<?php
include 'config.php';
$stmt = $pdo->query("SELECT id, name, email, referral_code FROM users ORDER BY id DESC LIMIT 5");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($users);
?>
