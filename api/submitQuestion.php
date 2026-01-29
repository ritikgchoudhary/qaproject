<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->question_id) || !isset($data->answer)) {
    echo json_encode(["message" => "Incomplete data", "success" => false]);
    exit();
}

$question_id = $data->question_id;
$user_answer = trim($data->answer);

// Check if already answered THIS question correctly
$stmt = $pdo->prepare("SELECT id FROM answers WHERE user_id = ? AND question_id = ? AND is_correct = 1");
$stmt->execute([$user_id, $question_id]);
if ($stmt->rowCount() > 0) {
    echo json_encode(["message" => "You have already answered this question.", "success" => false]);
    exit();
}

// Fetch Level and Question Level
$stmt = $pdo->prepare("SELECT level, quiz_level_completed FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$level = (int)($user_data['level'] ?: 1);
$quiz_level_completed = (int)($user_data['quiz_level_completed'] ?: 0);

// Get question level
$stmt = $pdo->prepare("SELECT level FROM questions WHERE id = ?");
$stmt->execute([$question_id]);
$question_level = (int)($stmt->fetchColumn() ?: 1);

// Determine which quiz level user is playing
$quiz_level = $quiz_level_completed + 1;
$stake_amount = 100 * pow(2, $quiz_level - 1);

// Check withdrawable balance (merged wallet)
$stmt = $pdo->prepare("SELECT withdrawable_balance FROM wallets WHERE user_id = ?");
$stmt->execute([$user_id]);
$balance = $stmt->fetchColumn() ?: 0;

if ($balance < $stake_amount) {
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
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO answers (user_id, question_id, is_correct) VALUES (?, ?, 1)");
        $stmt->execute([$user_id, $question_id]);

        // WIN: Add 2X total (stake + profit) to withdrawable balance (merged wallet)
        $total_win = $stake_amount * 2;
        $stmt = $pdo->prepare("UPDATE wallets SET withdrawable_balance = withdrawable_balance - ? + ? WHERE user_id = ?");
        // Net: -stake + (stake*2) = +stake (stake returned + profit)
        $stmt->execute([$stake_amount, $total_win, $user_id]);

        // Mark this quiz level as completed
        // If user wins a question from the current quiz level, mark that level as completed
        if ($question_level == $quiz_level && $quiz_level_completed < $question_level) {
            $stmt = $pdo->prepare("UPDATE users SET quiz_level_completed = ? WHERE id = ?");
            $stmt->execute([$question_level, $user_id]);
        }

        // Log Transaction (WIN)
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'win', ?, 'Quiz Win: Question #$question_id (Level $quiz_level, Stake: ₹$stake_amount, Win: ₹$total_win)')");
        $stmt->execute([$user_id, $total_win]);

        $pdo->commit();
        // Return win amount in response for frontend to use directly
        echo json_encode([
            "message" => "Correct! ₹$total_win added to your wallet (Stake + Profit).", 
            "success" => true,
            "win_amount" => $total_win,
            "stake_amount" => $stake_amount,
            "level_completed" => ($quiz_level == $question_level && $quiz_level_completed < $quiz_level)
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(["error" => "Error processing win."]);
    }

} else {
    // Incorrect 
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO answers (user_id, question_id, is_correct) VALUES (?, ?, 0)");
        $stmt->execute([$user_id, $question_id]);

        // Deduct Stake (merged wallet)
        $stmt = $pdo->prepare("UPDATE wallets SET withdrawable_balance = withdrawable_balance - ? WHERE user_id = ?");
        $stmt->execute([$stake_amount, $user_id]);
        
        // Log Transaction (LOSS)
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'loss', ?, 'Quiz Loss: Question #$question_id')");
        $stmt->execute([$user_id, $stake_amount]);

        $pdo->commit();
        echo json_encode(["message" => "Incorrect! You lost ₹$stake_amount.", "success" => false]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(["error" => "Error processing loss."]);
    }
}
?>
