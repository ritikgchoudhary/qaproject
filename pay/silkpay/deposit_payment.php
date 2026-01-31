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
      <p>Initiating Payment via SilkPay...</p>
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

// SilkPay Configuration
$merchantId = "F7092";
$secretKey = "89055L7zH3";
$apiUrl = "https://api.silkpay.ai/transaction/payin/v2";
$notifyUrl = "https://iquizz.in/pay/silkpay/deposit_callback.php";
$returnUrl = "https://iquizz.in/deposit?status=success";

// Generate timestamp (milliseconds)
$timestamp = round(microtime(true) * 1000);

// Prepare request data
$requestData = [
    "amount" => $amount,
    "mId" => $merchantId,
    "mOrderId" => $order_id,
    "timestamp" => $timestamp,
    "notifyUrl" => $notifyUrl,
    "returnUrl" => $returnUrl
];

// Generate signature: md5(mId+mOrderId+amount+timestamp+secret)
$signString = $merchantId . $order_id . $amount . $timestamp . $secretKey;
$sign = strtolower(md5($signString));
$requestData["sign"] = $sign;

// Log request for debugging
error_log("SilkPay Request - Order: $order_id, Amount: $amount, Sign String: $signString, Sign: $sign");

// Call SilkPay API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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

// Check if response is valid
if (!$data) {
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
    error_log("SilkPay Invalid Response - Order: $order_id, HTTP Code: $httpCode, Response: " . substr($response, 0, 500));
    exit;
}

// Check for success
if (!isset($data['status']) || $data['status'] !== "200" || !isset($data['data']['paymentUrl'])) {
    $stmt = $pdo->prepare("UPDATE deposits SET status = 'failed' WHERE id = ?");
    $stmt->execute([$deposit_id]);
    
    $errorMsg = $data['message'] ?? 'Unknown error';
    
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
        <p>Error Message: ' . htmlspecialchars($errorMsg) . '</p>
        <a href="https://iquizz.in/deposit" class="btn">Try Again</a>
    </div>
</body>
</html>';
    
    error_log("SilkPay Payment Gateway Error - Order: $order_id, Message: $errorMsg, Response: " . json_encode($data));
    exit;
}

// Success - Redirect to Payment Page
$payLink = $data['data']['paymentUrl'];

// Log success
error_log("SilkPay Success - Order: $order_id, Payment URL: $payLink");

// Redirect to Payment Page
echo '<script type="text/javascript">
    console.log("Redirecting to SilkPay gateway:", "' . htmlspecialchars($payLink, ENT_QUOTES) . '");
    window.location.href = "' . htmlspecialchars($payLink, ENT_QUOTES) . '";
</script>';
echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($payLink, ENT_QUOTES) . '"></noscript>';
?>
