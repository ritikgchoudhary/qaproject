<?php
include 'config.php';
include 'utils.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch Level to determine amount
$stmt = $pdo->prepare("SELECT level FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$level = $stmt->fetchColumn() ?: 1;

$amount = 100 * pow(2, $level - 1); // Level 1: 100, Level 2: 200, Level 3: 400...

// Use dummy logic: Auto success
$status = 'success';

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("INSERT INTO deposits (user_id, amount, status) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $amount, $status]);

    // Add to LOCKED BALANCE (Waiting for quiz)
    $stmt = $pdo->prepare("UPDATE wallets SET locked_balance = locked_balance + ? WHERE user_id = ?");
    $stmt->execute([$amount, $user_id]);

    // Update has_deposited flag
    $stmt = $pdo->prepare("UPDATE users SET has_deposited = 1 WHERE id = ?");
    $stmt->execute([$user_id]);

    // Distribute Agent Commissions
    distributeAgentCommissions($pdo, $user_id, $amount);
    
    
    $pdo->commit();

    // Check unlock
    checkAndUnlockParams($pdo, $user_id);
    
    echo json_encode(["message" => "Deposit successful (Dummy)", "success" => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["error" => "Deposit failed"]);
}
?>
