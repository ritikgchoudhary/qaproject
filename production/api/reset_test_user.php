<?php
include 'config.php';
$password = password_hash('test1234', PASSWORD_DEFAULT);
// Create if not exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute(['test@example.com']);
if (!$stmt->fetch()) {
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, referral_code) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Test User', 'test@example.com', $password, 'TEST12']);
    echo "User created: test@example.com / test1234";
} else {
    // Update password to be sure
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$password, 'test@example.com']);
    echo "User password updated: test@example.com / test1234";
}
?>
