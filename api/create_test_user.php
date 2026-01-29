<?php
include 'config.php';

$mobile = "1234567890";
$password = "password";
$name = "Test User";
$ref_code = "TEST001";
$role = "user";

// Check if exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ?");
$stmt->execute([$mobile]);
$user = $stmt->fetch();

if ($user) {
    // Update password just in case
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE mobile = ?");
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $mobile]);
    
    // Ensure wallet has balance
    $stmt = $pdo->prepare("UPDATE wallets SET withdrawable_balance = 1000 WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    
    // Reset any answers for Q 27 so we can test again
    $stmt = $pdo->prepare("DELETE FROM answers WHERE user_id = ? AND question_id = 27");
    $stmt->execute([$user['id']]);
    
    echo "Updated user $mobile. Login with password '$password'";
} else {
    // Create new
    $email = $mobile . "@test.com";
    $stmt = $pdo->prepare("INSERT INTO users (name, mobile, email, password, referral_code, role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $mobile, $email, password_hash($password, PASSWORD_DEFAULT), $ref_code, $role]);
    $id = $pdo->lastInsertId();
    
    // Create wallet with balance
    $stmt = $pdo->prepare("INSERT INTO wallets (user_id, withdrawable_balance) VALUES (?, 1000)");
    $stmt->execute([$id]);
     
    echo "Created user $mobile. Login with password '$password'";
}
?>
