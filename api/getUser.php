<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

require_once 'utils.php';

$user_id = $_SESSION['user_id'];

// Auto Level Up check whenever user profile is fetched
autoLevelUp($pdo, $user_id);

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
    $user['current_level'] = (int)($user['level'] ?? 1);
    // 2X Logic: Level 1: 100, Level 2: 200, Level 3: 400, Level 4: 800
    $user['next_deposit_required'] = 100 * pow(2, $user['current_level'] - 1);

    // Check if current level is already answered correctly
    $offset = (int)($user['current_level'] - 1);
    $stmt = $pdo->prepare("SELECT id FROM questions ORDER BY id ASC LIMIT 1 OFFSET :offset");
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $curr_q_id = $stmt->fetchColumn();
    
    $user['current_level_completed'] = false;
    if ($curr_q_id) {
        $stmt = $pdo->prepare("SELECT id FROM answers WHERE user_id = ? AND question_id = ? AND is_correct = 1");
        $stmt->execute([$user_id, $curr_q_id]);
        if ($stmt->fetchColumn()) {
            $user['current_level_completed'] = true;
        }
    }

    echo json_encode(["user" => $user, "wallet" => $wallet]);
} else {
    echo json_encode(["error" => "User not found"]);
}
?>
