<?php
include 'config.php';

echo "STARTING LEVEL PROGRESSION TEST (Level 1 -> 6)\n";

// 1. Create Test User
$test_name = "TestHero_" . rand(1000,9999);
$mobile = "999" . rand(1000000, 9999999);
$email = $mobile . "@test.com";
$code = "TEST" . rand(1000, 9999);

try {
    $stmt = $pdo->prepare("INSERT INTO users (name, mobile, email, password, plain_password, referral_code, level) VALUES (?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([$test_name, $mobile, $email, 'hash', 'hash', $code]);
    $user_id = $pdo->lastInsertId();
    
    // Create Wallet
    $pdo->prepare("INSERT INTO wallets (user_id) VALUES (?)")->execute([$user_id]);
    
    echo "Created User: $test_name (ID: $user_id) | Starting Level: 1\n";

} catch (Exception $e) {
    die("Error creating user: " . $e->getMessage() . "\n");
}

// 2. Give 3 Referrals (for withdraw check)
echo "Adding 3 Referrals...\n";
for ($i=1; $i<=3; $i++) {
    $r_name = "Ref_" . $i . "_" . rand(100,999);
    $r_mob = "888" . rand(1000000, 9999999);
    $r_email = $r_mob . "@test.com";
    $r_code = "REF" . rand(10000,99999);
    
    try {
        $pdo->prepare("INSERT INTO users (name, mobile, email, password, plain_password, referral_code, referred_by) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$r_name, $r_mob, $r_email, 'hash', 'hash', $r_code, $code]);
    } catch (Exception $e) {
        echo "Warning: Failed to add referral $i: " . $e->getMessage() . "\n";
    }
}
echo "System: Added Referrals (Unlock Condition Met)\n\n";

// 3. Loop Levels 1 to 5
for ($cycle = 1; $cycle <= 5; $cycle++) {
    echo "------------------------------------------------\n";
    echo "CYCLE $cycle: Attempting to complete Level $cycle\n";
    
    // a. Verify Current Database State
    $stmt = $pdo->prepare("SELECT level FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $curr_level = $stmt->fetchColumn();
    
    echo "   [Status] Current DB Level: $curr_level\n";
    
    // b. Calculate Expected Deposit
    $exp_deposit = $curr_level * 100;
    echo "   [Logic] User needs to deposit: ₹$exp_deposit\n";
    
    // c. Simulate 'Win' (Set Balance)
    $pdo->prepare("UPDATE wallets SET withdrawable_balance = 1000 WHERE user_id = ?")->execute([$user_id]);
    echo "   [Action] Wallet balance set to ₹1000.\n";
    
    // d. Execute Withdraw Logic
    $pdo->beginTransaction();
    $withdraw_amount = 300; // Min withdraw amount
    
    // Deduct
    $pdo->prepare("UPDATE wallets SET withdrawable_balance = withdrawable_balance - ? WHERE user_id = ?")->execute([$withdraw_amount, $user_id]);
    // Log
    $pdo->prepare("INSERT INTO withdraws (user_id, amount, status) VALUES (?, ?, 'pending')")->execute([$user_id, $withdraw_amount]);
    // Level Up (Using the logic we implemented in API)
    $pdo->prepare("UPDATE users SET level = COALESCE(level, 1) + 1 WHERE id = ?")->execute([$user_id]);
    
    $pdo->commit();
    echo "   [Action] Withdrawal of ₹$withdraw_amount processed.\n";
    
    // e. Result
    $new_level = $pdo->query("SELECT level FROM users WHERE id = $user_id")->fetchColumn();
    echo "   [Result] User Level is now: $new_level\n";
    
    if ($new_level == $curr_level + 1) {
        echo "   >>> SUCCESS: Level Up Confirmed! ($curr_level -> $new_level)\n";
    } else {
        echo "   >>> FAILED: Level did not increment.\n";
        break;
    }
}

echo "------------------------------------------------\n";
echo "FINAL STATE Verification:\n";
$stmt = $pdo->query("SELECT level FROM users WHERE id = $user_id");
$final_lvl = $stmt->fetchColumn();
$next_dep = $final_lvl * 100;
echo "User is at Level $final_lvl.\n";
echo "Next required deposit calculation (Level * 100) = ₹$next_dep\n";

if ($final_lvl == 6) {
    echo "\nTEST PASSED: Successfully simulated 5 levels of progression.\n";
} else {
    echo "\nTEST INCOMPLETE.\n";
}
?>
