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

// Fetch Level and Wallet to determine if deposit is needed
$stmt = $pdo->prepare("
    SELECT u.level, w.withdrawable_balance
    FROM users u
    JOIN wallets w ON u.id = w.user_id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$level = $row['level'] ?: 1;
$withdrawable_balance = $row['withdrawable_balance'] ?: 0;

$required_amount = 100 * pow(2, $level - 1); 

// FEATURE 1: BLOCK DEPOSIT if previous level funds already cover next level deposit (level 2+)
if ($level > 1 && $withdrawable_balance >= $required_amount) {
    echo json_encode([
        "error" => "You already have enough funds for this level. Withdraw your previous level balance to proceed.",
        "withdraw_required" => true
    ]);
    exit();
}

// FEATURE 2: BLOCK DEPOSIT if they already have enough for this level
$stmt = $pdo->prepare("SELECT MAX(created_at) FROM deposits WHERE user_id = ? AND amount = ? AND status = 'success'");
$stmt->execute([$user_id, $required_amount]);
$last_deposit_time = $stmt->fetchColumn();

$last_loss_time = null;
if ($last_deposit_time) {
    $stmt = $pdo->prepare("SELECT MAX(created_at) FROM transactions WHERE user_id = ? AND type = 'loss' AND amount = ?");
    $stmt->execute([$user_id, $required_amount]);
    $last_loss_time = $stmt->fetchColumn();
}

$blocked_by_level = $last_deposit_time && (!$last_loss_time || $last_loss_time <= $last_deposit_time);

if ($withdrawable_balance >= $required_amount || $blocked_by_level) {
    echo json_encode([
        "error" => "Already Deposited! You have activated Level $level. You can play directly.",
        "already_deposited" => true
    ]);
    exit();
}

// FEATURE 3: Level-wise deposit amounts (100, 200, 400, 800...)
$amount = $required_amount; 

// FEATURE 4: Generate Unique Order ID (completely unique format)
do {
    $order_id = "DEP" . time() . rand(100000, 999999) . $user_id;
    if (strlen($order_id) > 64) {
        $order_id = substr($order_id, 0, 64);
    }
    $stmt = $pdo->prepare("SELECT id FROM deposits WHERE order_id = ?");
    $stmt->execute([$order_id]);
} while ($stmt->fetch());

// Get payment channel from request (default: WATCHPAY for backward compatibility)
$data = json_decode(file_get_contents("php://input"), true);
$channel = isset($data['channel']) ? strtoupper($data['channel']) : 'WATCHPAY';

// Check if payment method is enabled
if (in_array($channel, ['SIMPLYPAY', 'WATCHPAY', 'SILKPAY', 'CUSTOM_QR'])) {
    $settingKey = strtolower($channel) . '_enabled';
    $stmt = $pdo->prepare("SELECT setting_value FROM global_settings WHERE setting_key = ?");
    $stmt->execute([$settingKey]);
    $enabled = $stmt->fetchColumn();
    
    // Default to enabled if not set, but if explicitly set to 0, disable
    if ($enabled === '0') {
        http_response_code(400);
        $methodName = ($channel === 'CUSTOM_QR') ? 'Custom QR' : ucfirst($channel);
        echo json_encode([
            "error" => $methodName . " payment method is currently disabled. Please try another payment method.",
            "method_disabled" => true
        ]);
        exit();
    }
}

// Create pending deposit record
$pdo->beginTransaction();
try {
    // Set payment_method for Custom QR
    $payment_method = ($channel === 'CUSTOM_QR') ? 'CUSTOM_QR' : null;
    
    $stmt = $pdo->prepare("INSERT INTO deposits (user_id, amount, status, order_id, payment_method) VALUES (?, ?, 'pending', ?, ?)");
    $stmt->execute([$user_id, $amount, $order_id, $payment_method]);
    $deposit_id = $pdo->lastInsertId();
    
    $pdo->commit();
    
    // Handle Custom QR channel - just return success with deposit_id
    if ($channel === 'CUSTOM_QR') {
        echo json_encode([
            "success" => true,
            "message" => "Deposit record created. Please scan QR code and complete payment.",
            "deposit_id" => $deposit_id,
            "order_id" => $order_id,
            "requires_utr" => true
        ]);
        exit();
    }
    
    // Validate channel
    if (!in_array($channel, ['WATCHPAY', 'SILKPAY', 'SIMPLYPAY'])) {
        $channel = 'WATCHPAY'; // Default to WatchPay if invalid
    }
    
    // Set payment gateway URL based on channel
    if ($channel === 'SILKPAY') {
        $base_url = "https://iquizz.in/pay/silkpay/deposit_payment.php";
    } else if ($channel === 'SIMPLYPAY') {
        $base_url = "https://iquizz.in/pay/simplypay/deposit_payment.php";
    } else {
        $base_url = "https://iquizz.in/pay/watchpay/deposit_payment.php";
    }
    
    // Add GET parameters
    $payment_url = $base_url;
    $payment_url .= "?amount=" . urlencode($amount);
    $payment_url .= "&uid=" . urlencode($user_id);
    $payment_url .= "&deposit_id=" . urlencode($deposit_id);
    $payment_url .= "&order_id=" . urlencode($order_id);
    
    // Log for debugging
    error_log("Deposit API - User: $user_id, Amount: $amount, Channel: $channel, Payment URL: $payment_url");
    
    echo json_encode([
        "success" => true,
        "redirect" => true,
        "payment_url" => $payment_url,
        "channel" => $channel,
        "message" => "Redirecting to payment gateway...",
        "debug" => [
            "user_id" => $user_id,
            "amount" => $amount,
            "deposit_id" => $deposit_id,
            "order_id" => $order_id
        ]
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
