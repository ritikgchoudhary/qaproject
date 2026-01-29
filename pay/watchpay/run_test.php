<?php
/**
 * Run Test Callback - Command Line Script
 */
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
    die("DB Error: " . $e->getMessage() . "\n");
}

echo "=== WatchPay Callback Test ===\n\n";

// Find a pending deposit
$stmt = $pdo->query("SELECT id, user_id, amount, status, order_id, created_at FROM deposits WHERE status = 'pending' ORDER BY created_at DESC LIMIT 1");
$deposit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$deposit) {
    echo "❌ No pending deposits found.\n";
    echo "Please create a deposit first.\n";
    exit(1);
}

echo "✅ Found Pending Deposit:\n";
echo "   ID: {$deposit['id']}\n";
echo "   Order ID: " . ($deposit['order_id'] ?? 'N/A') . "\n";
echo "   User ID: {$deposit['user_id']}\n";
echo "   Amount: ₹{$deposit['amount']}\n";
echo "   Status: {$deposit['status']}\n";
echo "   Created: {$deposit['created_at']}\n\n";

// Check if order_id exists
if (empty($deposit['order_id'])) {
    echo "⚠️  Warning: order_id is empty. Generating test order_id...\n";
    $order_id = "TEST" . time() . $deposit['id'];
    // Update deposit with order_id
    $stmt = $pdo->prepare("UPDATE deposits SET order_id = ? WHERE id = ?");
    $stmt->execute([$order_id, $deposit['id']]);
    echo "   Generated Order ID: $order_id\n\n";
} else {
    $order_id = $deposit['order_id'];
}

// Simulate WatchPay callback parameters
$merchantKey = "49fd706f0a924b679df02131a3df8794";

$callback_params = [
    "tradeResult" => "1", // Success
    "mchId" => "100225567",
    "mchOrderNo" => $order_id,
    "oriAmount" => (string)$deposit['amount'],
    "amount" => (string)$deposit['amount'],
    "orderDate" => date("Y-m-d H:i:s"),
    "orderNo" => "WP" . time() . rand(1000, 9999),
    "merRetMsg" => "Test callback",
    "signType" => "MD5"
];

// Generate signature
$filtered = [];
foreach ($callback_params as $k => $v) {
    if ($v !== "" && $v !== null && $k != "sign" && $k != "signType") {
        $filtered[$k] = $v;
    }
}
ksort($filtered);
$queryString = "";
foreach ($filtered as $k => $v) {
    $queryString .= $k . "=" . $v . "&";
}
$queryString .= "key=" . $merchantKey;
$sign = strtolower(md5($queryString));
$callback_params["sign"] = $sign;

echo "📤 Sending Callback Request...\n";
echo "   Order ID: $order_id\n";
echo "   Amount: ₹{$deposit['amount']}\n";
echo "   Signature: $sign\n\n";

// Call callback endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://iquizz.in/pay/watchpay/deposit_callback.php");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($callback_params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "📥 Callback Response:\n";
echo "   HTTP Code: $http_code\n";
echo "   Response: " . trim($response) . "\n";
if ($curl_error) {
    echo "   ❌ cURL Error: $curl_error\n";
}
echo "\n";

// Wait a moment for processing
sleep(2);

// Check deposit status after callback
$stmt = $pdo->prepare("SELECT id, user_id, amount, status, order_id FROM deposits WHERE id = ?");
$stmt->execute([$deposit['id']]);
$deposit_after = $stmt->fetch(PDO::FETCH_ASSOC);

echo "📊 Deposit Status After Callback:\n";
echo "   ID: {$deposit_after['id']}\n";
echo "   Status: {$deposit_after['status']}\n";
echo "   Amount: ₹{$deposit_after['amount']}\n\n";

if ($deposit_after['status'] == 'success') {
    echo "✅ SUCCESS! Deposit auto-approved!\n\n";
    
    // Check wallet balance
    $stmt = $pdo->prepare("SELECT withdrawable_balance FROM wallets WHERE user_id = ?");
    $stmt->execute([$deposit_after['user_id']]);
    $balance = $stmt->fetchColumn();
    echo "💰 User Wallet Balance: ₹" . number_format($balance, 2) . "\n";
    
    // Check if user marked as deposited
    $stmt = $pdo->prepare("SELECT has_deposited FROM users WHERE id = ?");
    $stmt->execute([$deposit_after['user_id']]);
    $has_deposited = $stmt->fetchColumn();
    echo "   User has_deposited: " . ($has_deposited ? "Yes ✅" : "No ❌") . "\n";
    
    exit(0);
} else {
    echo "❌ FAILED! Deposit status is still '{$deposit_after['status']}'\n";
    echo "   Expected: 'success'\n";
    echo "   Actual: '{$deposit_after['status']}'\n\n";
    echo "Check callback_log.txt for errors.\n";
    exit(1);
}
?>
