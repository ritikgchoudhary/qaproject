<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = '72.60.96.75';
$db_name = 'qa_platform';
$username = 'qa_platform';
$password = 'qa_platform';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("WatchPay Callback DB Error: " . $e->getMessage());
    http_response_code(500);
    exit("DB error");
}

// Log function
function logToFile($msg) {
    $logFile = __DIR__ . "/callback_log.txt";
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - " . $msg . "\n", FILE_APPEND);
}

// Log initial callback hit
logToFile("=== CALLBACK HIT ===");
logToFile("Request Method: " . $_SERVER['REQUEST_METHOD']);
logToFile("POST Data: " . json_encode($_POST));
logToFile("GET Data: " . json_encode($_GET));
logToFile("Raw Input: " . file_get_contents('php://input'));

// Collect parameters (try both POST and GET - some gateways use GET)
$input = array_merge($_GET, $_POST);
$tradeResult = $input['tradeResult'] ?? '';
$mchId = $input['mchId'] ?? '';
$mchOrderNo = $input['mchOrderNo'] ?? '';
$oriAmount = $input['oriAmount'] ?? ($input['originalAmount'] ?? '');
$amount = $input['amount'] ?? '';
$orderDate = $input['orderDate'] ?? '';
$orderNo = $input['orderNo'] ?? '';
$merRetMsg = $input['merRetMsg'] ?? '';
$signType = $input['signType'] ?? '';
$sign = $input['sign'] ?? '';

logToFile("Parsed - Order: $mchOrderNo, Result: $tradeResult, Amount: $amount, Sign: $sign");

// Validate Signature
$merchantKey = "49fd706f0a924b679df02131a3df8794";

// Build sign string (exclude sign & signType & empty)
// Use merged input (GET + POST)
$params = $input;
unset($params['sign'], $params['signType']);
$params = array_filter($params, function($v) { return $v !== '' && $v !== null; });

logToFile("Params for signature: " . json_encode($params));

// Sort by ASCII order
ksort($params);

// Query string
$queryString = "";
foreach ($params as $k => $v) {
    $queryString .= $k . "=" . $v . "&";
}
$queryString .= "key=" . $merchantKey;

// MD5 lower
$checkSign = strtolower(md5($queryString));

if ($checkSign !== $sign) {
    logToFile("❌ Invalid signature. Received: $sign, Expected: $checkSign");
    http_response_code(400);
    exit("sign error");
}

logToFile("✅ Signature verified");

// Check if success
if ($tradeResult != "1") {
    logToFile("❌ Payment failed. tradeResult=$tradeResult for Order=$mchOrderNo");
    
    // Update deposit status to failed
    try {
        $stmt = $pdo->prepare("UPDATE deposits SET status = 'failed' WHERE order_id = ?");
        $stmt->execute([$mchOrderNo]);
    } catch (Exception $e) {
        logToFile("❌ Failed to update deposit status: " . $e->getMessage());
    }
    
    http_response_code(200);
    exit("fail");
}

// Payment Success - Process order
logToFile("✅ Payment successful for Order: $mchOrderNo, Amount: $amount");

try {
    $pdo->beginTransaction();
    
    // Find deposit record
    $stmt = $pdo->prepare("SELECT id, user_id, amount, status FROM deposits WHERE order_id = ? AND status = 'pending'");
    $stmt->execute([$mchOrderNo]);
    $deposit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$deposit) {
        // Try to find by any status (maybe already processed)
        $stmt2 = $pdo->prepare("SELECT id, user_id, amount, status FROM deposits WHERE order_id = ?");
        $stmt2->execute([$mchOrderNo]);
        $deposit_any = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($deposit_any) {
            logToFile("⚠️ Deposit found but status is '{$deposit_any['status']}' (not pending) for Order: $mchOrderNo");
        } else {
            logToFile("⚠️ Deposit not found in database for Order: $mchOrderNo");
            // List recent deposits for debugging
            $stmt3 = $pdo->query("SELECT id, order_id, user_id, amount, status, created_at FROM deposits ORDER BY created_at DESC LIMIT 5");
            $recent = $stmt3->fetchAll(PDO::FETCH_ASSOC);
            logToFile("Recent deposits: " . json_encode($recent));
        }
        
        $pdo->rollBack();
        http_response_code(200);
        echo "success"; // Already processed, still reply success
        exit;
    }
    
    $deposit_id = $deposit['id'];
    $user_id = $deposit['user_id'];
    $deposit_amount = $deposit['amount'];
    
    logToFile("💰 Processing deposit - User: $user_id, Amount: $deposit_amount, Deposit ID: $deposit_id");
    
    // Update deposit status to success
    $stmt = $pdo->prepare("UPDATE deposits SET status = 'success' WHERE id = ?");
    $stmt->execute([$deposit_id]);
    
    // Add to withdrawable balance (merged wallet)
    $stmt = $pdo->prepare("
        INSERT INTO wallets (user_id, withdrawable_balance) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE withdrawable_balance = withdrawable_balance + ?
    ");
    $stmt->execute([$user_id, $deposit_amount, $deposit_amount]);
    
    // Mark user as deposited
    $stmt = $pdo->prepare("UPDATE users SET has_deposited = 1 WHERE id = ?");
    $stmt->execute([$user_id]);
    
    // Log transaction
    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'deposit', ?, 'Deposit via WatchPay - Order: $mchOrderNo')");
    $stmt->execute([$user_id, $deposit_amount]);
    
    // Include utils for commission functions
    include __DIR__ . '/../../api/utils.php';
    
    // NEW: Distribute 20% commission to agents on first deposit from downline (excluding direct users)
    distributeAgentFirstDepositCommission($pdo, $user_id, $deposit_amount);
    
    // OLD: Tree bonus system disabled
    // checkAndDistributeTreeBonus($pdo, $user_id);
    
    // Auto level up
    autoLevelUp($pdo, $user_id);
    
    $pdo->commit();
    
    logToFile("✅ Deposit processed successfully - User: $user_id, Amount: $deposit_amount");
    
    // Important: Tell gateway "success"
    http_response_code(200);
    echo "success";
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    logToFile("❌ Error processing deposit: " . $e->getMessage());
    http_response_code(500);
    echo "error";
    exit;
}
?>
