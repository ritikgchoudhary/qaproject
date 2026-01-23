<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->amount) || !is_numeric($data->amount) || $data->amount <= 0) {
    echo json_encode(["error" => "Invalid amount"]);
    exit();
}

if ($data->amount < 200) {
    echo json_encode(["error" => "Minimum withdrawal amount is ₹200"]);
    exit();
}

$amount = $data->amount;

$pdo->beginTransaction();
try {
    // --- LEVEL BASED WITHDRAWAL LOGIC ---
    // Fetch user level
    $stmt = $pdo->prepare("SELECT level FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_level = $stmt->fetchColumn() ?: 1;

    // Helper to get active directs
    function getActiveDirects($pdo, $uid) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE referred_by = (SELECT referral_code FROM users WHERE id = ?) AND has_deposited = 1");
        $stmt->execute([$uid]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Recursive Structure Check
    function checkTeamStructure($pdo, $uid, $target_depth, $current_depth) {
        $directs = getActiveDirects($pdo, $uid);
        
        // Requirement: At least 3 active directs at THIS node
        if (count($directs) < 3) {
            return false;
        }

        // If we reached the target depth, we are good for this branch
        if ($current_depth >= $target_depth) {
            return true;
        }

        // Otherwise, check all children
        foreach ($directs as $child_id) {
            if (!checkTeamStructure($pdo, $child_id, $target_depth, $current_depth + 1)) {
                return false;
            }
        }
        return true;
    }

    // Level 1 Requirement: Depth 1 (3 Directs)
    // Level 2 Requirement: Depth 2 (3 Directs -> Each has 3 Directs)
    // ...
    // Verify structure for CURRENT level
    if (!checkTeamStructure($pdo, $user_id, $user_level, 1)) {
        $pdo->rollBack();
        echo json_encode(["error" => "Withdrawal Locked! Level $user_level requirements not met. You need a complete team structure of 3 members x $user_level levels deep."]);
        exit();
    }
    // ------------------------------------

    // Check Bank/Crypto Details
    if (isset($data->usdt_address)) {
        $stmt = $pdo->prepare("UPDATE users SET usdt_address = ? WHERE id = ?");
        $stmt->execute([$data->usdt_address, $user_id]);
    } else if (isset($data->account_number)) {
        $stmt = $pdo->prepare("UPDATE users SET bank_account_number = ?, bank_ifsc_code = ?, bank_holder_name = ? WHERE id = ?");
        $stmt->execute([$data->account_number, $data->ifsc, $data->holder_name, $user_id]);
    }

    $stmt = $pdo->prepare("SELECT withdrawable_balance FROM wallets WHERE user_id = ? FOR UPDATE");
    $stmt->execute([$user_id]);
    $balance = $stmt->fetchColumn();

    if ($balance >= $amount) {
        // Deduct balance
        $stmt = $pdo->prepare("UPDATE wallets SET withdrawable_balance = withdrawable_balance - ? WHERE user_id = ?");
        $stmt->execute([$amount, $user_id]);

        // Create Withdraw Request
        $stmt = $pdo->prepare("INSERT INTO withdraws (user_id, amount, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$user_id, $amount]);

        // LEVEL UP: "Jab User First Withdraw kar le tab Uske Limit Of Depost 1X Se bahegi"
        // Meaning: After withdrawing, they move to next level.
        // LEVEL UP: Increment level. Handle NULL by treating it as 1.
        $stmt = $pdo->prepare("UPDATE users SET level = COALESCE(level, 1) + 1 WHERE id = ?");
        $stmt->execute([$user_id]);

        // Also lock them out again until they deposit the NEW amount?
        // Actually, prompt says "Limit of Deposit ... badhegi".
        // It implies for the NEXT cycle.
        // But if they have money left, can they play? 
        // Logic: "New User Ko deposit karne ke bad questions aaye".
        // If they withdraw, maybe they are empty?
        // Let's assume the flow is Deposit -> Win -> Withdraw -> (Loop).
        // So yes, after withdraw, they will naturally need to deposit again if balance is 0.
        // But specifically, the question check depends on 'has_deposited'.
        // We probably need to reset 'has_deposited' technically, OR check if (deposits_count == level).
        // Let's simplify: 
        // We track 'level'.
        // Deposit amount required = level * 100.
        // We probably DO NOT need to force reset 'has_deposited' flag if we just check balance, 
        // BUT the prompt implies a Cycle. "Deposit Limit increases".
        // So presumably they MUST deposit the higher amount to play again.
        // So we should strictly enforce: Total Deposits >= Level * 100? No.
        // Let's just track Level.
        
        $pdo->commit();
        echo json_encode(["message" => "Withdrawal request submitted", "success" => true]);
    } else {
        $pdo->rollBack();
        echo json_encode(["error" => "Insufficient withdrawable balance"]);
    }
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["error" => "Withdraw failed"]);
}
?>
