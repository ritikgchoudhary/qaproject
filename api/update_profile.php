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

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);
$telegram_link = $data['telegram_link'] ?? '';

if (empty($telegram_link)) {
    echo json_encode(["status" => "error", "message" => "Telegram link cannot be empty."]);
    exit();
}

// Basic validation (optional)
if (!filter_var($telegram_link, FILTER_VALIDATE_URL)) {
    echo json_encode(["status" => "error", "message" => "Invalid URL format."]);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE users SET telegram_link = ? WHERE id = ?");
    $stmt->execute([$telegram_link, $user_id]);

    echo json_encode(["status" => "success", "message" => "Telegram link updated successfully."]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>
