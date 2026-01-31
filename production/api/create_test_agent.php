<?php
include 'config.php';

$email = "agent@test.com";
$password = "password123";
$name = "Master Agent";
$ref_code = "AGENT001";
$role = "agent";

// Check if exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    // Update to be sure
    $stmt = $pdo->prepare("UPDATE users SET role = 'agent', password = ? WHERE email = ?");
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $email]);
    echo "Updated existing user $email to Agent role.\n";
} else {
    // Create new
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, referral_code, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $ref_code, $role]);
    $id = $pdo->lastInsertId();
    
    // Create wallet
    $pdo->prepare("INSERT INTO wallets (user_id) VALUES (?)")->execute([$id]);
    
    echo "Created new Agent user: $email / $password\n";
}
?>
