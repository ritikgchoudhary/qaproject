<?php
include 'config.php';

header("Content-Type: application/json");

// Check Authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // 1. Get the current user's referrer code
    $stmt = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $referral_code = $stmt->fetchColumn();

    if (!$referral_code) {
        echo json_encode(["status" => "success", "telegram_link" => null, "message" => "No referrer found."]);
        exit();
    }

    // 2. Get the referrer's Telegram link using the referral code
    $stmt = $pdo->prepare("SELECT telegram_link FROM users WHERE referral_code = ?");
    $stmt->execute([$referral_code]);
    $telegram_link = $stmt->fetchColumn();

    echo json_encode([
        "status" => "success", 
        "telegram_link" => $telegram_link ? $telegram_link : null
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>
