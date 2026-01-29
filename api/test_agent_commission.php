<?php
include 'config.php';
include 'utils.php';

echo "--- STARTING AGENT COMMISSION TEST ---\n";

// 1. Setup Agent User (Top of the chain)
$agent_email = "agent_" . time() . "@test.com";
$agent_code = "AGT" . rand(100,999);
$stmt = $pdo->prepare("INSERT INTO users (name, email, password, referral_code, role) VALUES (?, ?, ?, ?, 'agent')");
$stmt->execute(["Agent 007", $agent_email, "pass", $agent_code]);
$agent_id = $pdo->lastInsertId();
// Ensure wallet
$pdo->prepare("INSERT INTO wallets (user_id) VALUES (?)")->execute([$agent_id]);

echo "Created Agent (ID: $agent_id, Code: $agent_code)\n";

// 2. Setup Level 1 User (Referred by Agent)
$l1_code = "L1_" . rand(100,999);
$stmt = $pdo->prepare("INSERT INTO users (name, email, password, referral_code, referred_by) VALUES (?, ?, ?, ?, ?)");
$stmt->execute(["Level 1 User", "l1_".time()."@test.com", "pass", $l1_code, $agent_code]);
$l1_id = $pdo->lastInsertId();
echo "Created Level 1 User (ID: $l1_id) -> Referred by Agent\n";

// 3. Setup Level 2 User (Referred by L1)
$l2_code = "L2_" . rand(100,999);
$stmt = $pdo->prepare("INSERT INTO users (name, email, password, referral_code, referred_by) VALUES (?, ?, ?, ?, ?)");
$stmt->execute(["Level 2 User", "l2_".time()."@test.com", "pass", $l2_code, $l1_code]);
$l2_id = $pdo->lastInsertId();
echo "Created Level 2 User (ID: $l2_id) -> Referred by L1\n";

// 4. Setup Level 3 User (Referred by L2)
$l3_code = "L3_" . rand(100,999);
$stmt = $pdo->prepare("INSERT INTO users (name, email, password, referral_code, referred_by) VALUES (?, ?, ?, ?, ?)");
$stmt->execute(["Level 3 User", "l3_".time()."@test.com", "pass", $l3_code, $l2_code]);
$l3_id = $pdo->lastInsertId();
echo "Created Level 3 User (ID: $l3_id) -> Referred by L2\n";

// 5. Simulate Deposit for Level 3 User
$deposit_amount = 1000;
echo "\n[ACTION] Level 3 User deposits $deposit_amount...\n";

// Manually trigger what deposit.php does
$pdo->beginTransaction();
$pdo->prepare("INSERT INTO deposits (user_id, amount, status) VALUES (?, ?, 'success')")->execute([$l3_id, $deposit_amount]);
$pdo->prepare("UPDATE wallets SET withdrawable_balance = withdrawable_balance + ? WHERE user_id = ?")->execute([$deposit_amount, $l3_id]);

// Call our new function
distributeAgentCommissions($pdo, $l3_id, $deposit_amount);
$pdo->commit();

// 6. Verify Agent Earnings
$stmt = $pdo->prepare("SELECT withdrawable_balance FROM wallets WHERE user_id = ?");
$stmt->execute([$agent_id]);
$agent_bal = $stmt->fetchColumn();

// Expected: 10% of 1000 = 100
echo "\n[RESULT] Agent Balance: $agent_bal\n";

if ($agent_bal == 100) {
    echo "[PASS] Agent received correct 10% commission from Level 3!\n";
} else {
    echo "[FAIL] Agent balance incorrect. Expected 100.\n";
}

// 7. Verify Agent Commission Log
$stmt = $pdo->prepare("SELECT * FROM agent_commissions WHERE agent_id = ?");
$stmt->execute([$agent_id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\n[LOGS] Commission Entries:\n";
foreach ($logs as $log) {
    echo " - From User ID {$log['from_user_id']} (Level {$log['level']}): {$log['amount']}\n";
}

if (count($logs) == 1 && $logs[0]['level'] == 3) {
    echo "[PASS] Log entry correct.\n";
}

echo "--- TEST COMPLETE ---\n";
?>
