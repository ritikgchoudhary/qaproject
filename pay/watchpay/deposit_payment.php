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

$amount = number_format((float)$_GET['amount'], 2, '.', '');
$uid = (int)$_GET['uid'];
$deposit_id = (int)$_GET['deposit_id'];
$order_id = $_GET['order_id'];

// Verify User Exists
$stmt = $pdo->prepare("SELECT id, email, name FROM users WHERE id = ?");
$stmt->execute([$uid]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    die("User not found.");
}

// Payment Gateway Config
$merchantKey = "49fd706f0a924b679df02131a3df8794";
$merchantId = "100225567";
$requestUrl = "https://api.watchglb.com/pay/web";

// Prepare Gateway Parameters
$params = [
    "version"       => "1.0",
    "mch_id"        => $merchantId,
    "notify_url"    => "https://iquizz.in/pay/watchpay/deposit_callback.php",
    "page_url"      => "https://iquizz.in/deposit?status=success",
    "mch_order_no"  => $order_id,
    "pay_type"      => '101', // UPI/QR Code
    "trade_amount"  => $amount,
    "order_date"    => date("Y-m-d H:i:s"),
    "goods_name"    => "Quiz Deposit",
    "mch_return_msg" => "Deposit for User ID: $uid",
    "sign_type"     => "MD5"
];

// Generate Signature
$filtered = [];
foreach ($params as $k => $v) {
    if ($v !== "" && $v !== null && $k != "sign" && $k != "sign_type") {
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
$params["sign"] = $sign;

// Call Payment API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $requestUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

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

// Check if response is valid and successful
if (!$data) {
    // Invalid JSON response
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
        <p>Invalid response from payment gateway</p>
        <p style="font-size:0.8rem;">Response: ' . htmlspecialchars(substr($response, 0, 200)) . '</p>
        <a href="https://iquizz.in/deposit" class="btn">Try Again</a>
    </div>
</body>
</html>';
    error_log("WatchPay Invalid Response - Order: $order_id, Response: " . substr($response, 0, 500));
    exit;
}

// Check for success - respCode should be "SUCCESS" and payInfo should exist
if (!isset($data['respCode']) || $data['respCode'] !== "SUCCESS" || !isset($data['payInfo'])) {
    // Handle Error - Update deposit status to failed
    $stmt = $pdo->prepare("UPDATE deposits SET status = 'failed' WHERE id = ?");
    $stmt->execute([$deposit_id]);
    
    $errorMsg = $data['errorMsg'] ?? ($data['tradeMsg'] ?? 'Unknown error');
    $errorCode = $data['respCode'] ?? 'Unknown';
    
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
        <a href="https://iquizz.in/deposit" class="btn">Try Again</a>
    </div>
</body>
</html>';
    
    // Log error
    error_log("WatchPay Payment Gateway Error - Order: $order_id, Code: $errorCode, Message: $errorMsg");
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
