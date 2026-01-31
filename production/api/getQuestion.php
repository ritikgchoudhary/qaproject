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

// Get User Level
$stmt = $pdo->prepare("SELECT level FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$level = $stmt->fetchColumn() ?: 1;

// Get The n-th Question (One question per level)
// We order by ID to ensure sequence
// LIMIT 1 OFFSET (level - 1)
$offset = $level - 1;
$stmt = $pdo->prepare("
    SELECT * FROM questions 
    ORDER BY id ASC 
    LIMIT 1 OFFSET :offset
");
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$question = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$question) {
    echo json_encode(["message" => "No more questions available! You have completed all levels.", "all_completed" => true]);
    exit();
}

// Check if ALREADY Answered CORRECTLY THE CURRENT QUESTION
$stmt = $pdo->prepare("SELECT id FROM answers WHERE user_id = ? AND question_id = ? AND is_correct = 1");
$stmt->execute([$user_id, $question['id']]);
$answered = $stmt->fetchColumn();

if ($answered) {
    // User answered the question for this level.
    // They must complete matrix to move to next (or if they reached max, they are done)
    echo json_encode([
        "message" => "Level $level Completed! Complete your 3x3 squad matrix to unlock the next level.", 
        "level_completed" => true
    ]);
} else {
    // Check if they have enough DEPOSIT for THIS level's stake
    $stake_amount = 100 * pow(2, $level - 1);
    $stmt = $pdo->prepare("SELECT locked_balance FROM wallets WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $balance = $stmt->fetchColumn() ?: 0;

    if ($balance < $stake_amount) {
        echo json_encode([
            "message" => "Deposit Check: Level $level requires ₹$stake_amount deposit to play.",
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
        "level" => $level,
        "stake" => $stake_amount
    ]);
}
?>
