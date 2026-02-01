<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection (use our project's config)
$host = '72.60.96.75';
$db_name = 'qa_platform';
$username = 'qa_platform';
$password = 'qa_platform';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Validate Request
if (!isset($_GET['amount'], $_GET['uid'], $_GET['deposit_id'], $_GET['order_id'])) {
    http_response_code(400);
    echo json_encode([
        'code' => 400,
        'message' => 'Missing required parameters (amount, uid, deposit_id, order_id)',
    ]);
    exit;
}

// Show Loading...
echo '<!DOCTYPE html>
<html>
<head>
  <title>Processing Payment...</title>
  <style>
    body { font-family: "Inter", sans-serif; display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100vh; background: #050505; color: #e2e8f0; margin: 0; padding: 20px; box-sizing: border-box; text-align: center; }
    .loader-container { background: #1a1a1a; padding: 30px 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); }
    .loader { border: 5px solid rgba(251, 191, 36, 0.2); border-top: 5px solid #fbbf24; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto 20px auto; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    p { font-size: 1.1rem; font-weight: 600; color: #94a3b8; }
    .error-box { background: rgba(239, 68, 68, 0.1); border: 2px solid rgba(239, 68, 68, 0.5); padding: 2rem; border-radius: 10px; max-width: 400px; margin-top: 20px; }
    .error-box h2 { color: #ef4444; font-size: 1.5rem; margin-bottom: 1rem; }
    .error-box p { color: #f87171; font-size: 0.9rem; margin-bottom: 0.5rem; }
    .btn { display: inline-block; margin-top: 1rem; padding: 0.75rem 2rem; background: #ef4444; color: white; text-decoration: none; border-radius: 5px; font-weight: 600; transition: background-color 0.3s; }
    .btn:hover { background-color: #dc2626; }
  </style>
</head>
<body>
  <div class="loader-container">
      <div class="loader"></div>
      <p>Initiating Payment via WatchPay...</p>
      <p style="font-size:0.8rem; margin-top:10px;">Please do not close this window.</p>
  </div>
</body>
</html>';

// Format amount as string with 2 decimal places (as per documentation)
$amount = number_format((float)$_GET['amount'], 2, '.', '');
// Keep as string with 2 decimals (documentation shows amounts like "100.00")
// Don't remove trailing zeros - gateway might expect exact format
$uid = (int)$_GET['uid'];
$deposit_id = (int)$_GET['deposit_id'];
$original_order_id = trim($_GET['order_id']);

// Validate order_id (should not be empty)
if (empty($original_order_id)) {
    die("Invalid order ID.");
}

// Check if this order_id was already used with gateway (to avoid ORDER_REPEATED error)
// WatchPay gateway requires unique order_id for each transaction attempt
// If deposit is still pending and order_id was already sent to gateway, generate new one
$stmt = $pdo->prepare("SELECT status, order_id FROM deposits WHERE id = ?");
$stmt->execute([$deposit_id]);
$deposit_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$deposit_data) {
    die("Deposit not found.");
}

$deposit_status = $deposit_data['status'];
$current_order_id = $deposit_data['order_id'];

// If deposit is pending and order_id matches, it means this is a retry
// Generate a new unique order_id to avoid ORDER_REPEATED error
$order_id = $current_order_id;
if ($deposit_status === 'pending' && $current_order_id === $original_order_id) {
    // Generate new unique order_id for retry
    // Format: DEP + timestamp + random + user_id + retry suffix
    do {
        $order_id = "DEP" . time() . rand(100000, 999999) . $uid . "_R" . rand(100, 999);
        if (strlen($order_id) > 64) {
            $order_id = substr($order_id, 0, 64);
        }
        // Check if this order_id already exists
        $stmt = $pdo->prepare("SELECT id FROM deposits WHERE order_id = ?");
        $stmt->execute([$order_id]);
    } while ($stmt->fetch());
    
    // Update deposit with new order_id
    $stmt = $pdo->prepare("UPDATE deposits SET order_id = ? WHERE id = ?");
    $stmt->execute([$order_id, $deposit_id]);
    
    error_log("WatchPay - Generated new order_id for retry: $order_id (Original: $original_order_id, Deposit ID: $deposit_id)");
}

// Verify User Exists
$stmt = $pdo->prepare("SELECT id, email, name FROM users WHERE id = ?");
$stmt->execute([$uid]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    die("User not found.");
}

// Payment Gateway Config
$merchantKey = "R3BEQZSVERC1GEBJXCBJLNV3DJQNH5KE";
$merchantId = "100225567";
$requestUrl = "https://api.watchglb.com/pay/web";

// Prepare Gateway Parameters (according to documentation)
$orderDate = date("Y-m-d H:i:s");
// Note: notify_url and page_url cannot include parameters according to docs
$notifyUrl = "https://iquizz.in/pay/watchpay/deposit_callback.php";
$pageUrl = "https://iquizz.in/deposit"; // Removed query parameters as per documentation
$goodsName = "Quiz Deposit";
$mchReturnMsg = "Deposit for User ID: $uid";

// Ensure goods_name is not more than 50 bytes
if (strlen($goodsName) > 50) {
    $goodsName = substr($goodsName, 0, 50);
}

// Ensure mch_return_msg is not more than 200 bytes
if (strlen($mchReturnMsg) > 200) {
    $mchReturnMsg = substr($mchReturnMsg, 0, 200);
}

// Build parameters array (excluding sign and sign_type from signature)
$params = [
    "version"       => "1.0",
    "mch_id"        => $merchantId,
    "notify_url"    => $notifyUrl,
    "page_url"      => $pageUrl,
    "mch_order_no"  => $order_id,
    "pay_type"      => "101", // UPI/QR Code
    "trade_amount"  => $amount,
    "order_date"    => $orderDate,
    "goods_name"    => $goodsName,
    "mch_return_msg" => $mchReturnMsg,
    "sign_type"     => "MD5"
];

// Generate Signature - according to documentation format
// Signature string: goods_name=test&mch_id=977977111&mch_order_no=...&mch_return_msg=test&notify_url=...&order_date=...&page_url=...&pay_type=101&trade_amount=100&version=1.0&key=xxx
// Exclude sign and sign_type from signature calculation
$signParams = [];
foreach ($params as $k => $v) {
    // Only include non-empty values, exclude sign and sign_type
    if ($v !== "" && $v !== null && $k !== "sign" && $k !== "sign_type") {
        $signParams[$k] = $v;
    }
}

// Sort parameters alphabetically by key
ksort($signParams);

// Build signature string
$signString = "";
foreach ($signParams as $k => $v) {
    $signString .= $k . "=" . $v . "&";
}
$signString .= "key=" . $merchantKey;

// Generate MD5 signature (lowercase)
$sign = strtolower(md5($signString));

// Add sign to params
$params["sign"] = $sign;

// Log for debugging
error_log("WatchPay Signature String: " . $signString);
error_log("WatchPay Signature: " . $sign);
error_log("WatchPay Params: " . json_encode($params));

// Call Payment API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $requestUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Log request details
error_log("WatchPay Request - URL: $requestUrl, HTTP Code: $httpCode");
error_log("WatchPay Request Params: " . http_build_query($params));
error_log("WatchPay Response: " . substr($response, 0, 500));

if ($curlError) {
    // Update deposit status to failed
    $stmt = $pdo->prepare("UPDATE deposits SET status = 'failed' WHERE id = ?");
    $stmt->execute([$deposit_id]);
    
    echo '<!DOCTYPE html>
<html>
<head>
    <title>Payment Failed</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #050505; color: white; text-align: center; }
        .error-box { background: rgba(239, 68, 68, 0.1); border: 2px solid rgba(239, 68, 68, 0.5); padding: 2rem; border-radius: 10px; max-width: 400px; }
        .btn { display: inline-block; margin-top: 1rem; padding: 0.75rem 2rem; background: #ef4444; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="error-box">
        <h2>Payment Initiation Failed</h2>
        <p>cURL Error: ' . htmlspecialchars($curlError) . '</p>
        <a href="https://iquizz.in/deposit" class="btn">Try Again</a>
    </div>
</body>
</html>';
    exit;
}

// Parse Response
$data = json_decode($response, true);

// Check if response is valid
if (!$data) {
    // Invalid JSON response - might be HTML or other format
    $stmt = $pdo->prepare("UPDATE deposits SET status = 'failed' WHERE id = ?");
    $stmt->execute([$deposit_id]);
    
    $responsePreview = substr($response, 0, 500);
    error_log("WatchPay Invalid JSON Response - Order: $order_id, HTTP Code: $httpCode, Response: $responsePreview");
    
    echo '<!DOCTYPE html>
<html>
<head>
    <title>Payment Failed</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #050505; color: white; text-align: center; }
        .error-box { background: rgba(239, 68, 68, 0.1); border: 2px solid rgba(239, 68, 68, 0.5); padding: 2rem; border-radius: 10px; max-width: 400px; }
        .btn { display: inline-block; margin-top: 1rem; padding: 0.75rem 2rem; background: #ef4444; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="error-box">
        <h2>Payment Initiation Failed</h2>
        <p>Invalid response from payment gateway</p>
        <p style="font-size:0.8rem;">HTTP Code: ' . $httpCode . '</p>
        <p style="font-size:0.8rem;">Response: ' . htmlspecialchars($responsePreview) . '</p>
        <a href="https://iquizz.in/deposit" class="btn">Try Again</a>
    </div>
</body>
</html>';
    exit;
}

// Check for success - respCode should be "SUCCESS" and payInfo should exist
if (!isset($data['respCode']) || $data['respCode'] !== "SUCCESS" || !isset($data['payInfo'])) {
    // Handle Error - Update deposit status to failed
    $stmt = $pdo->prepare("UPDATE deposits SET status = 'failed' WHERE id = ?");
    $stmt->execute([$deposit_id]);
    
    $errorMsg = $data['errorMsg'] ?? ($data['tradeMsg'] ?? 'Unknown error');
    $errorCode = $data['respCode'] ?? 'Unknown';
    
    // Log full response for debugging
    error_log("WatchPay Payment Gateway Error - Order: $order_id, HTTP Code: $httpCode, Response Code: $errorCode, Message: $errorMsg");
    error_log("WatchPay Full Response: " . json_encode($data));
    
    echo '<!DOCTYPE html>
<html>
<head>
    <title>Payment Failed</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #050505; color: white; text-align: center; }
        .error-box { background: rgba(239, 68, 68, 0.1); border: 2px solid rgba(239, 68, 68, 0.5); padding: 2rem; border-radius: 10px; max-width: 400px; }
        .btn { display: inline-block; margin-top: 1rem; padding: 0.75rem 2rem; background: #ef4444; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="error-box">
        <h2>Payment Initiation Failed</h2>
        <p>Error Code: ' . htmlspecialchars($errorCode) . '</p>
        <p>Error Message: ' . htmlspecialchars($errorMsg) . '</p>
        <p style="font-size:0.8rem; margin-top:1rem; color:#94a3b8;">Please try again or contact support if the issue persists.</p>
        <a href="https://iquizz.in/deposit" class="btn">Try Again</a>
    </div>
</body>
</html>';
    exit;
}

// Success - Redirect to Payment Page
$payLink = $data['payInfo'];
$gatewayOrderNo = $data['orderNo'] ?? $order_id;

// Update deposit with gateway order number (store in order_id or just log it)
// Note: We keep the original order_id, gateway order is in callback

// Redirect to Payment Page (in same tab - this file is already opened in new tab by frontend)
echo '<script type="text/javascript">
    console.log("Redirecting to WatchPay gateway:", "' . htmlspecialchars($payLink, ENT_QUOTES) . '");
    // Use window.location.href instead of replace to allow back button
    window.location.href = "' . htmlspecialchars($payLink, ENT_QUOTES) . '";
</script>';
// Fallback: Meta refresh in case JavaScript is disabled
echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($payLink, ENT_QUOTES) . '"></noscript>';
?>
