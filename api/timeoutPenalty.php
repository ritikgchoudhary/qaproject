<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->question_id)) {
    echo json_encode(["message" => "Incomplete data", "success" => false]);
    exit();
}

$question_id = $data->question_id;

// Check if already answered THIS question correctly
$stmt = $pdo->prepare("SELECT id FROM answers WHERE user_id = ? AND question_id = ? AND is_correct = 1");
$stmt->execute([$user_id, $question_id]);
if ($stmt->rowCount() > 0) {
    echo json_encode(["message" => "You have already answered this question.", "success" => false]);
    exit();
}

// Fetch Level & Stake
$stmt = $pdo->prepare("SELECT level FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$level = $stmt->fetchColumn() ?: 1;
$stake_amount = 100 * pow(2, $level - 1);

// Check withdrawable balance (merged wallet)
$stmt = $pdo->prepare("SELECT withdrawable_balance FROM wallets WHERE user_id = ?");
$stmt->execute([$user_id]);
$balance = $stmt->fetchColumn() ?: 0;

if ($balance < $stake_amount) {
    echo json_encode(["message" => "Insufficient balance.", "success" => false]);
    exit();
}

// TIMEOUT LOSS
$pdo->beginTransaction();
try {
    // Record as incorrect answer (Timeout)
    $stmt = $pdo->prepare("INSERT INTO answers (user_id, question_id, is_correct) VALUES (?, ?, 0)");
    $stmt->execute([$user_id, $question_id]);

    // Deduct Stake (merged wallet)
    $stmt = $pdo->prepare("UPDATE wallets SET withdrawable_balance = withdrawable_balance - ? WHERE user_id = ?");
    $stmt->execute([$stake_amount, $user_id]);
    
    // Log Transaction
    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'loss', ?, 'Timeout Loss: Question #$question_id')");
    $stmt->execute([$user_id, $stake_amount]);

    $pdo->commit();
    echo json_encode(["message" => "Timeout! ₹$stake_amount deducted.", "success" => false]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["error" => "Error processing timeout."]);
}
?>
