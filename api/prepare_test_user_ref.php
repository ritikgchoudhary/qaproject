<?php
include 'config.php';

$ref_code = 'REF638287';

echo "Searching for user with Referral Code: $ref_code\n";

$stmt = $pdo->prepare("SELECT * FROM users WHERE referral_code = ?");
$stmt->execute([$ref_code]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "User Found!\n";
    echo "ID: " . $user['id'] . "\n";
    echo "Name: " . $user['name'] . "\n";
    echo "Mobile: " . $user['mobile'] . "\n";
    echo "Role: " . $user['role'] . "\n";
    
    // Reset Password for testing
    $new_pass = 'password';
    $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashed, $user['id']]);
    echo "Password reset to '$new_pass' for testing.\n";
    
    // Check Wallet
    $stmt = $pdo->prepare("SELECT * FROM wallets WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($wallet) {
         echo "Wallet Balance: " . $wallet['withdrawable_balance'] . "\n";
    } else {
         echo "No wallet found. Creating one...\n";
         $pdo->prepare("INSERT INTO wallets (user_id, withdrawable_balance) VALUES (?, 100)")->execute([$user['id']]);
         echo "Wallet created with 100.\n";
    }

    // Write details to file
    $details = "Mobile: " . $user['mobile'] . "\n";
    $details .= "ID: " . $user['id'] . "\n";
    file_put_contents('user_details.txt', $details);
    echo "Saved details to user_details.txt\n";

} else {
    echo "User NOT found.\n";
}
?>
