<?php
include 'config.php';
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}
$user_id = $_SESSION['user_id'];

// Get deposits
$stmt = $pdo->prepare("SELECT id, amount, status, created_at, 'deposit' as type FROM deposits WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get withdraws
$stmt = $pdo->prepare("SELECT id, amount, status, created_at, 'withdraw' as type FROM withdraws WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$withdraws = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Merge and sort
$transactions = array_merge($deposits, $withdraws);
usort($transactions, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

echo json_encode(["transactions" => $transactions]);
?>
