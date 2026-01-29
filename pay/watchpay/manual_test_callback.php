<?php
/**
 * Manual Test Script for WatchPay Callback
 * This simulates a payment gateway callback to test auto-approval
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
    die("DB Error: " . $e->getMessage());
}

echo "<h2>WatchPay Manual Callback Test</h2>";

// Get pending deposit to test
$test_deposit_id = $_GET['deposit_id'] ?? '';

if (!$test_deposit_id) {
    // Show pending deposits
    $stmt = $pdo->query("SELECT id, user_id, amount, status, order_id, created_at FROM deposits WHERE status = 'pending' ORDER BY created_at DESC LIMIT 10");
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pending)) {
        echo "<p style='color: orange;'>⚠️ No pending deposits found.</p>";
        echo "<p>Create a deposit first, then test the callback.</p>";
        exit;
    }
    
    echo "<h3>Select a Pending Deposit to Test:</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #333; color: white;'><th>ID</th><th>Order ID</th><th>User ID</th><th>Amount</th><th>Status</th><th>Created</th><th>Action</th></tr>";
    foreach ($pending as $p) {
        $test_url = "?deposit_id=" . $p['id'];
        echo "<tr>";
        echo "<td>{$p['id']}</td>";
        echo "<td>" . ($p['order_id'] ?? 'N/A') . "</td>";
        echo "<td>{$p['user_id']}</td>";
        echo "<td>₹{$p['amount']}</td>";
        echo "<td>{$p['status']}</td>";
        echo "<td>{$p['created_at']}</td>";
        echo "<td><a href='$test_url' style='background: #fbbf24; color: black; padding: 5px 10px; text-decoration: none; border-radius: 5px;'>Test Callback</a></td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
}

// Get deposit details
$stmt = $pdo->prepare("SELECT id, user_id, amount, status, order_id, created_at FROM deposits WHERE id = ?");
$stmt->execute([$test_deposit_id]);
$deposit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$deposit) {
    die("Deposit not found!");
}

if ($deposit['status'] != 'pending') {
    die("Deposit status is '{$deposit['status']}', not 'pending'. Cannot test.");
}

echo "<h3>Testing Deposit ID: {$deposit['id']}</h3>";
echo "<pre>";
print_r($deposit);
echo "</pre>";

// Simulate WatchPay callback parameters
$merchantKey = "49fd706f0a924b679df02131a3df8794";
$order_id = $deposit['order_id'] ?? "TEST" . time();

// Build callback parameters (simulating WatchPay response)
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

echo "<h3>Simulated Callback Parameters:</h3>";
echo "<pre>";
print_r($callback_params);
echo "</pre>";

// Now call the actual callback
echo "<h3>Calling Callback...</h3>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://iquizz.in/pay/watchpay/deposit_callback.php");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($callback_params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "<div style='background: #1a1a1a; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<p><strong>HTTP Code:</strong> $http_code</p>";
echo "<p><strong>Response:</strong> " . htmlspecialchars($response) . "</p>";
if ($curl_error) {
    echo "<p style='color: red;'><strong>cURL Error:</strong> $curl_error</p>";
}
echo "</div>";

// Check deposit status after callback
sleep(1); // Wait a moment
$stmt = $pdo->prepare("SELECT id, user_id, amount, status, order_id FROM deposits WHERE id = ?");
$stmt->execute([$test_deposit_id]);
$deposit_after = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Deposit Status After Callback:</h3>";
echo "<pre>";
print_r($deposit_after);
echo "</pre>";

if ($deposit_after['status'] == 'success') {
    echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✅ SUCCESS! Deposit auto-approved!</p>";
    
    // Check wallet balance
    $stmt = $pdo->prepare("SELECT withdrawable_balance FROM wallets WHERE user_id = ?");
    $stmt->execute([$deposit_after['user_id']]);
    $balance = $stmt->fetchColumn();
    echo "<p><strong>User Wallet Balance:</strong> ₹" . number_format($balance, 2) . "</p>";
} else {
    echo "<p style='color: red; font-size: 18px; font-weight: bold;'>❌ FAILED! Deposit status is still '{$deposit_after['status']}'</p>";
    echo "<p>Check the callback log for errors.</p>";
}

// Show recent logs
$log_file = __DIR__ . "/callback_log.txt";
if (file_exists($log_file)) {
    echo "<h3>Recent Callback Logs:</h3>";
    $lines = file($log_file);
    $recent_lines = array_slice($lines, -30);
    echo "<pre style='background: #1a1a1a; color: #0f0; padding: 10px; max-height: 400px; overflow: auto; font-size: 12px;'>";
    echo htmlspecialchars(implode('', $recent_lines));
    echo "</pre>";
}

echo "<br><a href='?' style='background: #333; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Back to List</a>";
?>
