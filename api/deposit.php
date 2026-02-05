<?php
include 'config.php';
include 'utils.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

// Handle case where JSON decode fails or data is null
if ($data === null && $rawInput !== '') {
    $data = [];
}

// Get payment method - accept both 'payment_method' and 'channel' for compatibility
$payment_method = '';
if (isset($data['payment_method']) && !empty($data['payment_method'])) {
    $payment_method = $data['payment_method'];
} elseif (isset($data['channel']) && !empty($data['channel'])) {
    $payment_method = $data['channel'];
}

if (empty($payment_method)) {
    // Log debug info but don't expose to user
    error_log("Deposit API - Missing payment method. Data: " . json_encode($data));
    echo json_encode(["error" => "Payment method is required"]);
    exit();
}

// Fetch Level and Wallet to determine if deposit is needed
$stmt = $pdo->prepare("
    SELECT u.level, w.locked_balance 
    FROM users u 
    JOIN wallets w ON u.id = w.user_id 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$level = $row['level'] ?: 1;
$current_locked = $row['locked_balance'] ?: 0;

$required_amount = 100 * pow(2, $level - 1);

// Use amount from request if provided and valid, otherwise use required amount
if (isset($data['amount']) && is_numeric($data['amount']) && (float)$data['amount'] > 0) {
    $amount = (float)$data['amount'];
} else {
    $amount = $required_amount;
}

if ($amount <= 0) {
    // Log debug info but don't expose to user
    error_log("Deposit API - Invalid amount. Calculated: $amount, Required: $required_amount, Level: $level");
    echo json_encode(["error" => "Invalid amount"]);
    exit();
} 

// BLOCK DEPOSIT if they already have enough for this level
if ($current_locked >= $required_amount) {
    echo json_encode([
        "error" => "Already Deposited! You have ₹$current_locked in your wallet for Level $level. You can play directly.", 
        "already_deposited" => true
    ]);
    exit();
}

// Create deposit record with pending status
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("INSERT INTO deposits (user_id, amount, status, payment_method) VALUES (?, ?, 'pending', ?)");
    $stmt->execute([$user_id, $amount, $payment_method]);
    $deposit_id = $pdo->lastInsertId();
    
    $pdo->commit();
    
    // Redirect to payment gateway based on method
    $baseUrl = getBaseUrl();
    
    switch(strtoupper($payment_method)) {
        case 'SIMPLYPAY':
            $payment_url = $baseUrl . "/pay/simplypay/deposit_payment.php?deposit_id=" . $deposit_id;
            break;
        case 'SILKPAY':
            $payment_url = $baseUrl . "/pay/silkpay/deposit_payment.php?deposit_id=" . $deposit_id;
            break;
        case 'WATCHPAY':
            $payment_url = $baseUrl . "/pay/watchpay/deposit_payment.php?deposit_id=" . $deposit_id;
            break;
        default:
            echo json_encode(["error" => "Invalid payment method"]);
            exit();
    }
    
    echo json_encode([
        "success" => true,
        "payment_url" => $payment_url,
        "deposit_id" => $deposit_id
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Deposit API Error - User ID: $user_id | Error: " . $e->getMessage());
    echo json_encode(["error" => "Deposit failed. Please try again."]);
}
?>
