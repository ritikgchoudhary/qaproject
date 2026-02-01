<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}
require 'db_connect.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['user_id']) || !isset($data['amount']) || !isset($data['gateway'])) {
    echo json_encode(["error" => "Missing required parameters"]);
    exit();
}

$user_id = (int)$data['user_id'];
$amount = (float)$data['amount'];
$gateway = strtoupper($data['gateway']);

// Validate user exists
$stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(["error" => "User not found"]);
    exit();
}

// Generate unique order ID
do {
    $order_id = "TEST" . time() . rand(100000, 999999) . $user_id;
    if (strlen($order_id) > 64) {
        $order_id = substr($order_id, 0, 64);
    }
    $stmt = $pdo->prepare("SELECT id FROM deposits WHERE order_id = ?");
    $stmt->execute([$order_id]);
} while ($stmt->fetch());

// Create test deposit record
$payment_method = ($gateway === 'CUSTOM_QR') ? 'CUSTOM_QR' : null;
$stmt = $pdo->prepare("INSERT INTO deposits (user_id, amount, status, order_id, payment_method) VALUES (?, ?, 'pending', ?, ?)");
$stmt->execute([$user_id, $amount, $order_id, $payment_method]);
$deposit_id = $pdo->lastInsertId();

// Handle Custom QR
if ($gateway === 'CUSTOM_QR') {
    // Get QR code
    $stmt = $pdo->query("SELECT qr_image_path, is_enabled FROM master_qr_settings LIMIT 1");
    $qrSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$qrSettings || !$qrSettings['is_enabled']) {
        echo json_encode([
            "error" => "Custom QR is not enabled or not configured"
        ]);
        exit();
    }
    
    echo json_encode([
        "success" => true,
        "deposit_id" => $deposit_id,
        "order_id" => $order_id,
        "qr_image" => $qrSettings['qr_image_path'] ? 'https://iquizz.in/' . $qrSettings['qr_image_path'] : null,
        "gateway" => "CUSTOM_QR"
    ]);
    exit();
}

// Handle WatchPay, SilkPay, and SimplyPay - generate payment URL
if ($gateway === 'WATCHPAY') {
    $payment_url = "https://iquizz.in/pay/watchpay/deposit_payment.php";
} else if ($gateway === 'SILKPAY') {
    $payment_url = "https://iquizz.in/pay/silkpay/deposit_payment.php";
} else if ($gateway === 'SIMPLYPAY') {
    $payment_url = "https://iquizz.in/pay/simplypay/deposit_payment.php";
} else {
    echo json_encode(["error" => "Invalid gateway"]);
    exit();
}

$payment_url .= "?amount=" . urlencode($amount);
$payment_url .= "&uid=" . urlencode($user_id);
$payment_url .= "&deposit_id=" . urlencode($deposit_id);
$payment_url .= "&order_id=" . urlencode($order_id);

echo json_encode([
    "success" => true,
    "deposit_id" => $deposit_id,
    "order_id" => $order_id,
    "payment_url" => $payment_url,
    "gateway" => $gateway
]);
?>
