<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in callback

// Database connection
$host = '72.60.96.75';
$db_name = 'qa_platform';
$username = 'qa_platform';
$password = 'qa_platform';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("SilkPay Callback DB Error: " . $e->getMessage());
    echo "OK"; // Return OK even on DB error to prevent retries
    exit;
}

// SilkPay Configuration
$merchantId = "F7092";
$secretKey = "89055L7zH3";

// Read JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log all incoming data
error_log("SilkPay Callback Received - Raw Input: " . $input);
error_log("SilkPay Callback Received - Parsed Data: " . json_encode($data));

// Validate required fields
if (!$data || !isset($data['mOrderId'], $data['amount'], $data['mId'], $data['timestamp'], $data['sign'], $data['status'])) {
    error_log("SilkPay Callback - Missing required fields");
    echo "OK";
    exit;
}

$mOrderId = $data['mOrderId'];
$amount = $data['amount'];
$mId = $data['mId'];
$timestamp = $data['timestamp'];
$receivedSign = $data['sign'];
$status = (int)$data['status'];
$payOrderId = $data['payOrderId'] ?? '';
$utr = $data['utr'] ?? '';

// Verify merchant ID
if ($mId !== $merchantId) {
    error_log("SilkPay Callback - Invalid Merchant ID: $mId");
    echo "OK";
    exit;
}

// Verify signature: md5(amount+mId+mOrderId+timestamp+secret)
$signString = $amount . $mId . $mOrderId . $timestamp . $secretKey;
$calculatedSign = strtolower(md5($signString));

error_log("SilkPay Callback - Sign String: $signString");
error_log("SilkPay Callback - Calculated Sign: $calculatedSign");
error_log("SilkPay Callback - Received Sign: $receivedSign");

if ($calculatedSign !== strtolower($receivedSign)) {
    error_log("SilkPay Callback - Signature mismatch for Order: $mOrderId");
    echo "OK";
    exit;
}

// Find deposit by order_id
$stmt = $pdo->prepare("SELECT id, user_id, amount, status FROM deposits WHERE order_id = ?");
$stmt->execute([$mOrderId]);
$deposit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$deposit) {
    error_log("SilkPay Callback - Deposit not found for Order: $mOrderId");
    echo "OK";
    exit;
}

// Check if already processed
if ($deposit['status'] === 'success') {
    error_log("SilkPay Callback - Deposit already processed: " . $deposit['id']);
    echo "OK";
    exit;
}

// Verify amount matches
$expectedAmount = number_format((float)$deposit['amount'], 2, '.', '');
$receivedAmount = number_format((float)$amount, 2, '.', '');

if ($expectedAmount !== $receivedAmount) {
    error_log("SilkPay Callback - Amount mismatch for Order: $mOrderId. Expected: $expectedAmount, Received: $receivedAmount");
    echo "OK";
    exit;
}

// Process payment status
// Status: 1 = success, 2 = failed
$newStatus = ($status === 1) ? 'success' : 'failed';

try {
    $pdo->beginTransaction();
    
    // Update deposit status
    $stmt = $pdo->prepare("UPDATE deposits SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newStatus, $deposit['id']]);
    
    if ($newStatus === 'success') {
        // Update user wallet
        $stmt = $pdo->prepare("
            UPDATE wallets 
            SET withdrawable_balance = withdrawable_balance + ?,
                total_deposited = total_deposited + ?,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([$deposit['amount'], $deposit['amount'], $deposit['user_id']]);
        
        // Log transaction
        $stmt = $pdo->prepare("
            INSERT INTO transactions (user_id, type, amount, description, created_at)
            VALUES (?, 'deposit', ?, ?, NOW())
        ");
        $stmt->execute([
            $deposit['user_id'],
            $deposit['amount'],
            "Deposit via SilkPay - Order: $mOrderId"
        ]);
        
        error_log("SilkPay Callback - Deposit successful: " . $deposit['id'] . ", Amount: " . $deposit['amount']);
    } else {
        error_log("SilkPay Callback - Deposit failed: " . $deposit['id']);
    }
    
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("SilkPay Callback - Database Error: " . $e->getMessage());
    echo "OK";
    exit;
}

// Return "OK" as required by SilkPay (must be string, not JSON)
echo "OK";
?>
