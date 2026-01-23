<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id, name, email, referral_code, referred_by, level, role, bank_account_number, bank_ifsc_code, bank_holder_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $user['has_deposited'] = false;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM deposits WHERE user_id = ? AND status = 'success'");
    $stmt->execute([$user_id]);
    if ($stmt->fetchColumn() > 0) {
        $user['has_deposited'] = true;
    }

    // Get wallet info too
    $stmt = $pdo->prepare("SELECT locked_balance, withdrawable_balance, total_withdrawn FROM wallets WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate Next Deposit Amount based on Level
    // Level 1: 100
    // Level 2: 200 (Increases by 1X/100)
    // Level 3: 300
    $user['current_level'] = $user['level'] ?? 1;
    $user['next_deposit_required'] = $user['current_level'] * 100;

    echo json_encode(["user" => $user, "wallet" => $wallet]);
} else {
    echo json_encode(["error" => "User not found"]);
}
?>
