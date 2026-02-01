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
    .loader { border: 5px solid rgba(59, 130, 246, 0.2); border-top: 5px solid #3b82f6; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto 20px auto; }
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
      <p>Initiating Payment via SimplyPay...</p>
      <p style="font-size:0.8rem; margin-top:10px;">Please do not close this window.</p>
  </div>
</body>
</html>';

$amount = number_format((float)$_GET['amount'], 2, '.', '');
$uid = (int)$_GET['uid'];
$deposit_id = (int)$_GET['deposit_id'];
$order_id = trim($_GET['order_id']);

// Verify User Exists
$stmt = $pdo->prepare("SELECT id, email, name, mobile FROM users WHERE id = ?");
$stmt->execute([$uid]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    die("User not found.");
}

// SimplyPay Configuration
$appId = "5c85b1437ec187bceaddc751583f0268";
$appSecret = "006c05166329204714c6d06544f9f43a";
$apiUrl = "https://api.paysimply.net/api/v2/payment/order/create";
$notifyUrl = "https://iquizz.in/pay/simplypay/deposit_callback.php";
$returnUrl = "https://iquizz.in/deposit";

// Prepare request parameters
$merOrderNo = $order_id;
$currency = "INR";
$attach = "Quiz Deposit Payment";

// Get user mobile, email, name
$userName = $userData['name'] ?? 'User';
$userEmail = $userData['email'] ?? 'user@example.com';
$userMobile = $userData['mobile'] ?? '9999999999';

// Build request data
$requestData = [
    "appId" => $appId,
    "merOrderNo" => $merOrderNo,
    "currency" => $currency,
    "amount" => $amount,
    "notifyUrl" => $notifyUrl,
    "returnUrl" => $returnUrl,
    "attach" => $attach,
    "extra" => [
        "name" => $userName,
        "email" => $userEmail,
        "mobile" => $userMobile
    ]
];

// Generate signature
function generateSimplyPaySign($params, $appSecret) {
    // Remove sign if exists
    if (isset($params['sign'])) {
        unset($params['sign']);
    }
    
    // Sort by key (Unicode order)
    ksort($params);
    
    $parts = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        
        // Handle extra field specially
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

$sign = generateSimplyPaySign($requestData, $appSecret);
$requestData['sign'] = $sign;

// Log request
error_log("SimplyPay Request - Order: $order_id, Amount: $amount, Sign: $sign");

// Call SimplyPay API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json;charset=utf-8",
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlError) {
    error_log("SimplyPay cURL Error - Order: $order_id, Error: $curlError");
    
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

error_log("SimplyPay Response - Order: $order_id, HTTP Code: $httpCode, Response: " . substr($response, 0, 500));

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
    exit;
}

// Check for success (code should be 0)
if (!isset($data['code']) || $data['code'] !== 0 || !isset($data['data']['params']['paymentLink'])) {
    $stmt = $pdo->prepare("UPDATE deposits SET status = 'failed' WHERE id = ?");
    $stmt->execute([$deposit_id]);
    
    $errorMsg = $data['msg'] ?? ($data['error'] ?? 'Unknown error');
    
    error_log("SimplyPay Payment Gateway Error - Order: $order_id, Code: " . ($data['code'] ?? 'N/A') . ", Message: $errorMsg");
    
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
        <p>Error Code: ' . htmlspecialchars($data['code'] ?? 'N/A') . '</p>
        <p>Error Message: ' . htmlspecialchars($errorMsg) . '</p>
        <a href="https://iquizz.in/deposit" class="btn">Try Again</a>
    </div>
</body>
</html>';
    exit;
}

// Success - Redirect to Payment Page
$payLink = $data['data']['params']['paymentLink'];
$gatewayOrderNo = $data['data']['orderNo'] ?? $order_id;

error_log("SimplyPay Success - Order: $order_id, Payment URL: $payLink");

// Redirect to Payment Page
echo '<script type="text/javascript">
    console.log("Redirecting to SimplyPay gateway:", "' . htmlspecialchars($payLink, ENT_QUOTES) . '");
    window.location.href = "' . htmlspecialchars($payLink, ENT_QUOTES) . '";
</script>';
echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($payLink, ENT_QUOTES) . '"></noscript>';
?>
