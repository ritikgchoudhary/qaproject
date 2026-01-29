<?php
include 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

require_once 'utils.php';

$user_id = $_SESSION['user_id'];

// Sync level with current referrals
autoLevelUp($pdo, $user_id);

// Get User Level and Quiz Level Completed
$stmt = $pdo->prepare("SELECT level, quiz_level_completed FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$level = (int)($user_data['level'] ?: 1);
$quiz_level_completed = (int)($user_data['quiz_level_completed'] ?: 0);

// SIMPLE LOGIC: User jis level pe hai, usi level ka quiz aayega
$quiz_level = $level;

// Build level overview
$levels = [];

// Show completed levels (up to quiz_level_completed)
for ($i = 1; $i <= $quiz_level_completed; $i++) {
    $stake = 100 * pow(2, $i - 1);
    $win = $stake * 2;
    $levels[] = [
        "level" => $i,
        "status" => "completed",
        "stake" => $stake,
        "win" => $win,
        "label" => "Level $i"
    ];
}

// Show current accessible level (only if not already completed)
if ($quiz_level > $quiz_level_completed) {
    $stake = 100 * pow(2, $quiz_level - 1);
    $win = $stake * 2;
    $levels[] = [
        "level" => $quiz_level,
        "status" => "active",
        "stake" => $stake,
        "win" => $win,
        "label" => "Level $quiz_level"
    ];
}

// Show next locked levels up to 10 total levels (as per user request)
// Start from the next level after the highest shown level
$highest_shown_level = max($quiz_level_completed, $quiz_level);
$next_level = $highest_shown_level + 1;
$total_levels_to_show = 10;
$current_total = count($levels);

for ($i = 0; $i < ($total_levels_to_show - $current_total); $i++) {
    $lvl = $next_level + $i;
    $stake = 100 * pow(2, $lvl - 1);
    $win = $stake * 2;
    $levels[] = [
        "level" => $lvl,
        "status" => "locked",
        "stake" => $stake,
        "win" => $win,
        "label" => "Level $lvl"
    ];
}

// Calculate if there are more levels beyond 10
$highest_level = max($level, $quiz_level_completed);
$has_more_levels = ($highest_level > 10);

echo json_encode([
    "current_level" => $level,
    "quiz_level_completed" => $quiz_level_completed,
    "quiz_level" => $quiz_level,
    "levels" => $levels,
    "has_more" => $has_more_levels,
    "total_shown" => count($levels)
]);
?>
