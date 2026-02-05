<?php
// Production settings - disable error display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Database connection
$host = '72.60.96.75';
$db_name = 'qa_platform';
$username = 'qa_platform';
$password = 'qa_platform';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("SimplyPay Callback DB Error: " . $e->getMessage());
    http_response_code(500);
    echo "Database error";
    exit;
}

// Get POST data
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

// Log callback
error_log("SimplyPay Callback - Raw Input: " . $rawInput);

if (!$data) {
    error_log("SimplyPay Callback - Invalid JSON");
    http_response_code(400);
    echo "Invalid data";
    exit;
}

// Verify signature
$appSecret = "8777e7d8544e71b29839e469d87de876";
$receivedSign = $data['sign'] ?? '';
unset($data['sign']);

function verifySimplyPaySign($params, $appSecret) {
    ksort($params);
    $parts = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        if ($key === 'extra' && is_array($value)) {
            $extraParts = [];
            ksort($value);
            foreach ($value as $ek => $ev) {
                if ($ev !== null && $ev !== '') {
                    $extraParts[] = $ek . '=' . $ev;
                }
            }
            $parts[] = $key . '=' . implode('&', $extraParts);
        } else {
            $parts[] = $key . '=' . $value;
        }
    }
    $signString = implode('&', $parts);
    $signString .= '&key=' . $appSecret;
    return hash('sha256', $signString);
}

$calculatedSign = verifySimplyPaySign($data, $appSecret);

if ($receivedSign !== $calculatedSign) {
    error_log("SimplyPay Callback - Signature mismatch. Received: $receivedSign, Calculated: $calculatedSign");
    http_response_code(400);
    echo "Signature verification failed";
    exit;
}

// Get order details
$merOrderNo = $data['merOrderNo'] ?? '';
$orderStatus = $data['orderStatus'] ?? -1;
$orderNo = $data['orderNo'] ?? '';

// Find deposit by order_id
$stmt = $pdo->prepare("SELECT id, user_id, amount, status FROM deposits WHERE order_id = ?");
$stmt->execute([$merOrderNo]);
$deposit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$deposit) {
    error_log("SimplyPay Callback - Deposit not found for order: $merOrderNo");
    http_response_code(404);
    echo "Order not found";
    exit;
}

// Check if already processed
if ($deposit['status'] === 'success') {
    error_log("SimplyPay Callback - Deposit already processed: " . $deposit['id']);
    echo "success";
    exit;
}

// Process based on order status
// pending: 0,1,-4
// success: 2,3
// failed: -1,-2
// refunded: -3

$pdo->beginTransaction();
try {
    if (in_array($orderStatus, [2, 3])) {
        // Success
        $stmt = $pdo->prepare("UPDATE deposits SET status = 'success' WHERE id = ?");
        $stmt->execute([$deposit['id']]);
        
        // Add to locked_balance (deposits are locked until quiz completion)
        $stmt = $pdo->prepare("
            INSERT INTO wallets (user_id, locked_balance) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE locked_balance = locked_balance + ?
        ");
        $stmt->execute([$deposit['user_id'], $deposit['amount'], $deposit['amount']]);
        
        // Mark user as deposited
        $stmt = $pdo->prepare("UPDATE users SET has_deposited = 1 WHERE id = ?");
        $stmt->execute([$deposit['user_id']]);
        
        // Log transaction
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'deposit', ?, 'Deposit via SimplyPay - Order: $merOrderNo')");
        $stmt->execute([$deposit['user_id'], $deposit['amount']]);
        
        // Distribute agent commission
        require_once __DIR__ . '/../../api/utils.php';
        distributeAgentFirstDepositCommission($pdo, $deposit['user_id'], $deposit['amount']);
        autoLevelUp($pdo, $deposit['user_id']);
        
        error_log("SimplyPay Callback - Deposit approved: " . $deposit['id']);
    } else if (in_array($orderStatus, [-1, -2])) {
        // Failed
        $stmt = $pdo->prepare("UPDATE deposits SET status = 'failed' WHERE id = ?");
        $stmt->execute([$deposit['id']]);
        error_log("SimplyPay Callback - Deposit failed: " . $deposit['id']);
    }
    
    $pdo->commit();
    echo "success";
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("SimplyPay Callback DB Error: " . $e->getMessage());
    http_response_code(500);
    echo "Processing error";
}
?>
