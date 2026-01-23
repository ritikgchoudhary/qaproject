<?php
include 'config.php';
include 'utils.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch Level and Wallet to determine if deposit is needed
$stmt = $pdo->prepare("
    SELECT u.level, w.locked_balance 
    FROM users u 
    JOIN wallets w ON u.id = w.user_id 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$level = $row['level'] ?: 1;
$current_locked = $row['locked_balance'] ?: 0;

$required_amount = 100 * pow(2, $level - 1); 

// BLOCK DEPOSIT if they already have enough for this level
if ($current_locked >= $required_amount) {
    echo json_encode([
        "error" => "Already Deposited! You have ₹$current_locked in your wallet for Level $level. You can play directly.", 
        "already_deposited" => true
    ]);
    exit();
}

// User can only deposit if they lost money or haven't deposited yet
$amount = $required_amount; 

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

    // NEW: Trigger level-up check for upline (parent)
    // Since this deposit makes the current user "active", the parent's level might increase.
    $stmt = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $parent_code = $stmt->fetchColumn();
    if ($parent_code) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->execute([$parent_code]);
        $parent_id = $stmt->fetchColumn();
        if ($parent_id) {
            autoLevelUp($pdo, $parent_id);
            
            // Also check L2 Parent
            $stmt = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
            $stmt->execute([$parent_id]);
            $l2_parent_code = $stmt->fetchColumn();
            if ($l2_parent_code) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
                $stmt->execute([$l2_parent_code]);
                $l2_parent_id = $stmt->fetchColumn();
                if ($l2_parent_id) {
                    autoLevelUp($pdo, $l2_parent_id);
                }
            }
        }
    }
    
    echo json_encode(["message" => "Deposit successful (Dummy)", "success" => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["error" => "Deposit failed"]);
}
?>
