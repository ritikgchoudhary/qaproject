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
$stake_amount = $level * 100;

// Check Balance First
$stmt = $pdo->prepare("SELECT withdrawable_balance FROM wallets WHERE user_id = ?");
$stmt->execute([$user_id]);
$balance = $stmt->fetchColumn();

// TRACE 3
file_put_contents('c:/xampp/htdocs_jkd/qa-platform/api/trace.txt', "3. Balance checked. Bal: $balance, Stake: $stake_amount\n", FILE_APPEND);

if ($balance < $stake_amount) {
    // TRACE EXIT
    file_put_contents('c:/xampp/htdocs_jkd/qa-platform/api/trace.txt', "EXIT: Insufficient balance\n", FILE_APPEND);
    echo json_encode(["message" => "Insufficient balance. Deposit ₹$stake_amount to play.", "insufficient_balance" => true, "success" => false]);
    exit();
}

// Check answer
$stmt = $pdo->prepare("SELECT answer FROM questions WHERE id = ?");
$stmt->execute([$question_id]);
$correct_answer = $stmt->fetchColumn();

// Use mb_strtolower for UTF-8 support
$user_clean = mb_strtolower(trim($user_answer), 'UTF-8');
$db_clean = mb_strtolower(trim($correct_answer), 'UTF-8');

// --- DEBUG LOGGING ---
$log  = "Timestamp: " . date("Y-m-d H:i:s") . "\n";
$log .= "QID: $question_id\n";
$log .= "UserRaw (Hex): " . bin2hex($user_answer) . "\n";
$log .= "DBRaw (Hex):   " . bin2hex($correct_answer) . "\n";
$log .= "UserClean (Hex): " . bin2hex($user_clean) . "\n";
$log .= "DBClean (Hex):   " . bin2hex($db_clean) . "\n";
$log .= "Strict Match: " . (($user_clean === $db_clean) ? "YES" : "NO") . "\n";
$log .= "Loose Match (==): " . (($user_clean == $db_clean) ? "YES" : "NO") . "\n";
file_put_contents('c:/xampp/htdocs_jkd/qa-platform/api/debug_submit_full.txt', $log, FILE_APPEND);
// ---------------------

if ($correct_answer && $user_clean === $db_clean) {
    // Correct 
    file_put_contents('c:/xampp/htdocs_jkd/qa-platform/api/trace.txt', "4. WIN\n", FILE_APPEND);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO answers (user_id, question_id, is_correct) VALUES (?, ?, 1)");
        $stmt->execute([$user_id, $question_id]);

        $stmt = $pdo->prepare("UPDATE wallets SET withdrawable_balance = withdrawable_balance + ? WHERE user_id = ?");
        $stmt->execute([$stake_amount, $user_id]);

        $pdo->commit();
        echo json_encode(["message" => "शानदार! आपका पैसा 2X हो गया है (₹$stake_amount जोड़े गए)!", "success" => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(["error" => "Error processing win."]);
    }

} else {
    // Incorrect 
    file_put_contents('c:/xampp/htdocs_jkd/qa-platform/api/trace.txt', "4. LOSS. Expected: $db_clean, Got: $user_clean\n", FILE_APPEND);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO answers (user_id, question_id, is_correct) VALUES (?, ?, 0)");
        $stmt->execute([$user_id, $question_id]);

        // Deduct Stake
        $stmt = $pdo->prepare("UPDATE wallets SET withdrawable_balance = withdrawable_balance - ? WHERE user_id = ?");
        $stmt->execute([$stake_amount, $user_id]);

        $pdo->commit();
        echo json_encode(["message" => "ओह नहीं! गलत जवाब। आपके ₹$stake_amount कम हो गए हैं।", "success" => false]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(["error" => "Error processing loss."]);
    }
}
?>
