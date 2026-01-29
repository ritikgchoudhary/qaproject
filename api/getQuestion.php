<?php
include 'config.php';
error_reporting(0); // Suppress warnings for clean JSON output

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

require_once 'utils.php';

$user_id = $_SESSION['user_id'];

// Sync level with current referrals before serving question
autoLevelUp($pdo, $user_id);

// Get User Level and Quiz Level Completed
$stmt = $pdo->prepare("SELECT level, quiz_level_completed FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$level = (int)($user_data['level'] ?: 1);
$quiz_level_completed = (int)($user_data['quiz_level_completed'] ?: 0);

// SIMPLE LOGIC: User jis level pe hai, usi level ka quiz aayega
// - Level 1 user → Level 1 quiz
// - Level 2 user → Level 2 quiz
// - Level 3 user → Level 3 quiz
$quiz_level = $level;

// IMPORTANT: If user has already completed this quiz level, don't show questions for it
if ($quiz_level_completed >= $quiz_level) {
    // User has completed this quiz level
    echo json_encode([
        "message" => "Level $quiz_level Completed! Complete your referrals to unlock Level " . ($level + 1) . " quiz.", 
        "level_completed" => true
    ]);
    exit();
}

// Now get question for the quiz_level
// Level 1: Random question from level 1 that user hasn't answered correctly
// Level 2+: Random question from that level that user hasn't answered correctly
$stmt = $pdo->prepare("
    SELECT q.* FROM questions q
    WHERE q.level = ?
    AND q.id NOT IN (
        SELECT question_id FROM answers 
        WHERE user_id = ? AND is_correct = 1
    )
    ORDER BY RAND()
    LIMIT 1
");
$stmt->execute([$quiz_level, $user_id]);
$question = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$question) {
    // User has completed all questions for this level
    // If quiz_level_completed >= quiz_level, they've completed this quiz level
    if ($quiz_level_completed >= $quiz_level) {
        if ($level > $quiz_level_completed) {
            // User has referrals for next level but needs to complete current quiz
            echo json_encode([
                "message" => "Level $quiz_level Completed! You can now play Level " . ($quiz_level_completed + 1) . " quiz.", 
                "level_completed" => true,
                "next_level_available" => true
            ]);
        } else {
            // User needs more referrals
            echo json_encode([
                "message" => "Level $quiz_level Completed! Complete your referrals to unlock Level " . ($quiz_level + 1) . ".", 
                "level_completed" => true
            ]);
        }
    } else {
        echo json_encode([
            "message" => "No more questions available for Level $quiz_level! You have completed all questions.", 
            "all_completed" => true
        ]);
    }
    exit();
}

// Check if ALREADY Answered CORRECTLY THE CURRENT QUESTION
$stmt = $pdo->prepare("SELECT id FROM answers WHERE user_id = ? AND question_id = ? AND is_correct = 1");
$stmt->execute([$user_id, $question['id']]);
$answered = $stmt->fetchColumn();

if ($answered) {
    // User already answered this question correctly
    echo json_encode([
        "message" => "Level $quiz_level Completed! Complete your referrals to unlock Level " . ($quiz_level + 1) . ".", 
        "level_completed" => true
    ]);
} else {
    // Check if they have enough DEPOSIT for THIS level's stake (merged wallet)
    $stake_amount = 100 * pow(2, $quiz_level - 1);
    $stmt = $pdo->prepare("SELECT withdrawable_balance FROM wallets WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $balance = $stmt->fetchColumn() ?: 0;

    if ($balance < $stake_amount) {
        echo json_encode([
            "message" => "Deposit Check: Level $quiz_level requires ₹$stake_amount deposit to play.",
            "deposit_required" => true,
            "required_amount" => $stake_amount
        ]);
        exit();
    }

    // Return Question
    echo json_encode([
        "id" => $question['id'],
        "question" => $question['question'],
        "image_url" => $question['image_url'],
        "options" => [
            $question['option_a'], 
            $question['option_b'], 
            $question['option_c'], 
            $question['option_d']
        ],
        "level" => $quiz_level,
        "stake" => $stake_amount
    ]);
}
?>
