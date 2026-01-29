<?php
include 'config.php';
include 'utils.php';

echo "--- STARTING REFERRAL & WITHDRAWAL TEST ---\n";

// 1. Create Main User
$email = "testrunner_" . time() . "@example.com";
$password = password_hash("123456", PASSWORD_DEFAULT);
$main_ref_code = "MAIN" . rand(100,999);

$stmt = $pdo->prepare("INSERT INTO users (name, email, password, referral_code) VALUES (?, ?, ?, ?)");
if ($stmt->execute(["Main Tester", $email, $password, $main_ref_code])) {
    $user_id = $pdo->lastInsertId();
    echo "[PASS] Created Main User (ID: $user_id, Code: $main_ref_code)\n";
} else {
    die("[FAIL] Could not create main user\n");
}

// 2. Create Wallet and Add Locked Balance (Simulate Quiz Earnings)
$stmt = $pdo->prepare("INSERT INTO wallets (user_id, locked_balance, withdrawable_balance) VALUES (?, ?, ?)");
$stmt->execute([$user_id, 1000, 0]); // 1000 locked
echo "[INFO] Added Wallet with 1000 Locked Balance\n";

// 3. Simulate Deposit (Required condition: >= 100)
$stmt = $pdo->prepare("INSERT INTO deposits (user_id, amount, status) VALUES (?, ?, 'success')");
$stmt->execute([$user_id, 150]);
echo "[INFO] Simulated Deposit of 150 (Requirement met)\n";

// 4. Verify Balance BEFORE Referrals
$stmt = $pdo->prepare("SELECT locked_balance, withdrawable_balance FROM wallets WHERE user_id = ?");
$stmt->execute([$user_id]);
$wallet = $stmt->fetch(PDO::FETCH_ASSOC);
echo "[STATE] Before Referrals - Locked: {$wallet['locked_balance']}, Withdrawable: {$wallet['withdrawable_balance']}\n";

if ($wallet['withdrawable_balance'] > 0) {
    echo "[FAIL] Withdrawable balance should be 0 initially!\n";
}

// 5. Create 3 Referrals
echo "[INFO] Creating 3 Referred Users...\n";
for ($i = 1; $i <= 3; $i++) {
    $ref_email = "ref{$i}_" . time() . "@example.com";
    $ref_pass = password_hash("123", PASSWORD_DEFAULT);
    $new_code = "REF" . rand(1000,9999);
    
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, referral_code, referred_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(["Ref User $i", $ref_email, $ref_pass, $new_code, $main_ref_code]);
    
    // Create wallet for referred user so the potential triggers don't fail (though register.php usually handles this)
    $new_id = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO wallets (user_id) VALUES (?)")->execute([$new_id]);

    echo "   -> Created Referral $i (ID: $new_id)\n";

    // Trigger check (Simulating what register.php does at line 63)
    // In register.php: checkAndUnlockParams($pdo, $referrer_id);
    $unlocked = checkAndUnlockParams($pdo, $user_id);
    if ($unlocked) {
        echo "   [EVENT] Balance Unlocked Triggered at Referral $i!\n";
    }
}

// 6. Verify Balance AFTER Referrals
$stmt = $pdo->prepare("SELECT locked_balance, withdrawable_balance FROM wallets WHERE user_id = ?");
$stmt->execute([$user_id]);
$wallet = $stmt->fetch(PDO::FETCH_ASSOC);
echo "[STATE] After Referrals - Locked: {$wallet['locked_balance']}, Withdrawable: {$wallet['withdrawable_balance']}\n";

if ($wallet['withdrawable_balance'] >= 1000) {
    echo "[SUCCESS] Balance successfully moved to Withdrawable!\n";
} else {
    echo "[FAIL] Balance did NOT move. Check conditions.\n";
}

echo "--- TEST COMPLETE ---\n";
?>
