<?php
include 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];

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

echo json_encode([
    "success" => true,
    "current_level" => $user_level,
    "l1_active" => $l1_count,
    "l1_required" => 3,
    "l2_active" => $l2_count,
    "l2_required" => 9,
    "structure_met" => ($l1_count >= 3 && ($user_level < 2 || $l2_count >= 9))
]);
?>
