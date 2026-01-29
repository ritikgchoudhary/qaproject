<?php
include 'config.php';

$name = "Agent Demo";
$email = "agent@demo.com";
$password = "123456";
$hashed = password_hash($password, PASSWORD_DEFAULT);
$ref_code = "AG" . rand(1000,9999);

// Check if exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
$exists = $stmt->fetch();

if ($exists) {
    // Update to agent
    $stmt = $pdo->prepare("UPDATE users SET role = 'agent', password = ?, plain_password = ? WHERE email = ?");
    $stmt->execute([$hashed, $password, $email]);
    echo "Updated existing user agent@demo.com to Agent role.\n";
} else {
    // Create new
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, plain_password, role, referral_code) VALUES (?, ?, ?, ?, 'agent', ?)");
    $stmt->execute([$name, $email, $hashed, $password, $ref_code]);
    echo "Created new Agent: agent@demo.com / 123456\n";
    
    // Wallet
    $uid = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO wallets (user_id) VALUES (?)")->execute([$uid]);
}
?>
