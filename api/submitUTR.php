<?php
header('Content-Type: application/json');
include 'config.php';
include 'utils.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['deposit_id']) || !isset($data['utr'])) {
    echo json_encode(["error" => "Deposit ID and UTR are required"]);
    exit();
}

$deposit_id = (int)$data['deposit_id'];
$utr = trim($data['utr']);

if (empty($utr)) {
    echo json_encode(["error" => "UTR cannot be empty"]);
    exit();
}

try {
    // Verify deposit belongs to user and is pending
    $stmt = $pdo->prepare("SELECT id, user_id, status, payment_method FROM deposits WHERE id = ? AND user_id = ?");
    $stmt->execute([$deposit_id, $user_id]);
    $deposit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$deposit) {
        echo json_encode(["error" => "Deposit not found"]);
        exit();
    }

    if ($deposit['status'] !== 'pending') {
        echo json_encode(["error" => "Deposit is not pending. Cannot submit UTR."]);
        exit();
    }

    if ($deposit['payment_method'] !== 'CUSTOM_QR') {
        echo json_encode(["error" => "This deposit is not a custom QR payment"]);
        exit();
    }

    // Update deposit with UTR
    $stmt = $pdo->prepare("UPDATE deposits SET utr = ? WHERE id = ?");
    $stmt->execute([$utr, $deposit_id]);

    echo json_encode([
        "success" => true,
        "message" => "UTR submitted successfully. Admin will verify and approve your payment."
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
