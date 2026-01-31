<?php
include 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];
require_once 'utils.php';

// Sync level with current team structure
autoLevelUp($pdo, $user_id);

// Fetch user level
$stmt = $pdo->prepare("SELECT level, referral_code FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_row = $stmt->fetch(PDO::FETCH_ASSOC);
$user_level = $user_row['level'] ?: 1;
$my_code = $user_row['referral_code'];

function getActiveChildCount($pdo, $referral_codes) {
    if (empty($referral_codes)) return [];
    $placeholders = implode(',', array_fill(0, count($referral_codes), '?'));
    $stmt = $pdo->prepare("SELECT referred_by, COUNT(*) as count FROM users WHERE referred_by IN ($placeholders) AND has_deposited = 1 GROUP BY referred_by");
    $stmt->execute($referral_codes);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [referred_by => count]
}

// Level 1: Directs
$stmt = $pdo->prepare("SELECT id, referral_code FROM users WHERE referred_by = ? AND has_deposited = 1");
$stmt->execute([$my_code]);
$l1_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
$l1_count = count($l1_members);

// Level 2: Indirects
$l2_count = 0;
$l1_codes = array_column($l1_members, 'referral_code');
if (!empty($l1_codes)) {
    $placeholders = implode(',', array_fill(0, count($l1_codes), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by IN ($placeholders) AND has_deposited = 1");
    $stmt->execute($l1_codes);
    $l2_count = $stmt->fetchColumn();
}

// NEW REQUIREMENT: ALL levels need exactly 3 active direct referrals who have deposited
$structure_met = ($l1_count >= 3);

// Calculate maximum withdrawal amount based on PREVIOUS completed level
// Level 1: ₹200 (Level 1), Level 2: ₹200 (Level 1 completed), Level 3: ₹400 (Level 2 completed), Level 4: ₹800 (Level 3 completed)
if ($user_level == 1) {
    $max_withdraw_amount = 200; // Level 1 amount
} else {
    $max_withdraw_amount = 200 * pow(2, $user_level - 2); // Previous level amount
}

echo json_encode([
    "success" => true,
    "current_level" => $user_level,
    "l1_active" => $l1_count,
    "l1_required" => 3, // All levels need 3 active directs
    "l2_active" => $l2_count,
    "l2_required" => 0, // Not required for withdrawal
    "structure_met" => $structure_met,
    "max_withdraw_amount" => $max_withdraw_amount
]);
?>
