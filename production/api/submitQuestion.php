<?php
file_put_contents('c:/xampp/htdocs_jkd/qa-platform/api/debug_ENTRY.txt', "Entered submitQuestion.php at " . date("Y-m-d H:i:s") . "\nInput: " . file_get_contents("php://input") . "\n", FILE_APPEND);
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

// TRACE 1
file_put_contents('c:/xampp/htdocs_jkd/qa-platform/api/trace.txt', "1. Session check passed. User: {$_SESSION['user_id']}\n", FILE_APPEND);

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->question_id) || !isset($data->answer)) {
    // TRACE EXIT
    file_put_contents('c:/xampp/htdocs_jkd/qa-platform/api/trace.txt', "EXIT: Incomplete data\n", FILE_APPEND);
    echo json_encode(["message" => "Incomplete data", "success" => false]);
    exit();
}

$question_id = $data->question_id;
$user_answer = trim($data->answer);

// TRACE 2
file_put_contents('c:/xampp/htdocs_jkd/qa-platform/api/trace.txt', "2. Data parsed. QID: $question_id, Ans: $user_answer\n", FILE_APPEND);

// Check if already answered THIS question correctly
$stmt = $pdo->prepare("SELECT id FROM answers WHERE user_id = ? AND question_id = ? AND is_correct = 1");
$stmt->execute([$user_id, $question_id]);
if ($stmt->rowCount() > 0) {
    // TRACE EXIT
    file_put_contents('c:/xampp/htdocs_jkd/qa-platform/api/trace.txt', "EXIT: Already answered correctly\n", FILE_APPEND);
    echo json_encode(["message" => "You have already answered this question.", "success" => false]);
    exit();
}

// Fetch Level
$stmt = $pdo->prepare("SELECT level FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$level = $stmt->fetchColumn() ?: 1;
$stake_amount = 100 * pow(2, $level - 1);

// Check LOCKED Balance (Must play with deposited funds)
$stmt = $pdo->prepare("SELECT locked_balance FROM wallets WHERE user_id = ?");
$stmt->execute([$user_id]);
$balance = $stmt->fetchColumn();

// TRACE 3
file_put_contents('c:/xampp/htdocs_jkd/qa-platform/api/trace.txt', "3. Balance checked. LockedBal: $balance, Stake: $stake_amount\n", FILE_APPEND);

if ($balance < $stake_amount) {
    // TRACE EXIT
    file_put_contents('c:/xampp/htdocs_jkd/qa-platform/api/trace.txt', "EXIT: Insufficient locked balance\n", FILE_APPEND);
    echo json_encode(["message" => "Please deposit to play. Required: ₹$stake_amount", "insufficient_balance" => true, "success" => false]);
    exit();
}

// Check answer
$stmt = $pdo->prepare("SELECT answer FROM questions WHERE id = ?");
$stmt->execute([$question_id]);
$correct_answer = $stmt->fetchColumn();

// Use mb_strtolower for UTF-8 support
$user_clean = mb_strtolower(trim($user_answer), 'UTF-8');
$db_clean = mb_strtolower(trim($correct_answer), 'UTF-8');

if ($correct_answer && $user_clean === $db_clean) {
    // Correct 
    file_put_contents('c:/xampp/htdocs_jkd/qa-platform/api/trace.txt', "4. WIN\n", FILE_APPEND);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO answers (user_id, question_id, is_correct) VALUES (?, ?, 1)");
        $stmt->execute([$user_id, $question_id]);

        // UNLOCK FUNDS: Locked -> Withdrawable
        $stmt = $pdo->prepare("UPDATE wallets SET locked_balance = locked_balance - ?, withdrawable_balance = withdrawable_balance + ? WHERE user_id = ?");
        $stmt->execute([$stake_amount, $stake_amount, $user_id]);

        // Log Transaction (UNLOCK)
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'unlock', ?, 'Deposit Unlocked: Question #$question_id')");
        $stmt->execute([$user_id, $stake_amount]);

        $pdo->commit();
        echo json_encode(["message" => "Correct! ₹$stake_amount unlocked to your wallet.", "success" => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(["error" => "Error processing win."]);
    }

} else {
    // Incorrect 
    file_put_contents('c:/xampp/htdocs_jkd/qa-platform/api/trace.txt', "4. LOSS.\n", FILE_APPEND);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO answers (user_id, question_id, is_correct) VALUES (?, ?, 0)");
        $stmt->execute([$user_id, $question_id]);

        // Deduct Locked Stake
        $stmt = $pdo->prepare("UPDATE wallets SET locked_balance = locked_balance - ? WHERE user_id = ?");
        $stmt->execute([$stake_amount, $user_id]);
        
        // Log Transaction (LOSS)
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'loss', ?, 'Quiz Loss: Question #$question_id')");
        $stmt->execute([$user_id, $stake_amount]);

        $pdo->commit();
        echo json_encode(["message" => "Incorrect! You lost ₹$stake_amount from locked balance.", "success" => false]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(["error" => "Error processing loss."]);
    }
}
?>
