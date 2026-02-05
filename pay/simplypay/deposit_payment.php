<?php
require_once __DIR__ . '/../../api/config.php';

$deposit_id = $_GET['deposit_id'] ?? 0;

if (!$deposit_id) {
    die("Invalid deposit ID");
}

// Get deposit details
$stmt = $pdo->prepare("SELECT d.*, u.name, u.email, u.mobile FROM deposits d JOIN users u ON d.user_id = u.id WHERE d.id = ?");
$stmt->execute([$deposit_id]);
$deposit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$deposit) {
    die("Deposit not found");
}

// Check if deposit is already processed
if ($deposit['status'] === 'success') {
    header("Location: " . getBaseUrl() . "/deposit?status=success");
    exit();
}

$baseUrl = getBaseUrl();
$notifyUrl = $baseUrl . "/pay/simplypay/deposit_callback.php";
$returnUrl = $baseUrl . "/deposit";

// SimplyPay Configuration
$appId = "5c85b1437ec187bceaddc751583f0268";
$appSecret = "8777e7d8544e71b29839e469d87de876";

// Validate appId format
if (empty($appId) || strlen($appId) < 16) {
    die("Payment gateway error: Invalid appId configuration. Please check SimplyPay credentials.");
}

// Prepare payment data according to new API v2
// Make merOrderNo unique by adding timestamp to avoid duplicates
$merOrderNo = 'DEP' . $deposit_id . '_' . time();
$amount = number_format((float)$deposit['amount'], 2, '.', ''); // Ensure 2 decimal places

// Ensure mobile is not empty
$mobile = $deposit['mobile'] ?? '';
if (empty($mobile)) {
    // Try to extract from email if it's in mobile format
    $email = $deposit['email'] ?? '';
    if (preg_match('/^(\d+)@/', $email, $matches)) {
        $mobile = $matches[1];
    }
}
if (empty($mobile)) {
    $mobile = '0000000000'; // Fallback
}

$paymentData = [
    'appId' => $appId,
    'merOrderNo' => $merOrderNo,
    'currency' => 'INR',
    'amount' => $amount,
    'notifyUrl' => $notifyUrl,
    'returnUrl' => $returnUrl,
    'attach' => 'Deposit Payment',
    'extra' => [
        'name' => $deposit['name'] ?? 'User',
        'email' => $deposit['email'] ?? '',
        'mobile' => $mobile
    ]
];

// Generate signature - Match callback verification method
// IMPORTANT: Generate signature BEFORE adding sign to paymentData
function generateSimplyPaySign($params, $appSecret) {
    // Exclude 'sign' from signature calculation
    $signParams = $params;
    unset($signParams['sign']);
    
    ksort($signParams);
    $parts = [];
    foreach ($signParams as $key => $value) {
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

$signature = generateSimplyPaySign($paymentData, $appSecret);
$paymentData['sign'] = $signature;

// SimplyPay API endpoint v2
$apiUrl = "https://api.paysimply.net/api/v2/payment/order/create";

// Make API request
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Logging (only in error log, not displayed to user)
error_log("SimplyPay API Request - URL: " . $apiUrl);
error_log("SimplyPay API Request - Deposit ID: " . $deposit_id);
error_log("SimplyPay API Response - HTTP Code: " . $httpCode);
if ($curlError) {
    error_log("SimplyPay API Response - CURL Error: " . $curlError);
}

// Handle response
if ($curlError) {
    error_log("SimplyPay Payment Error - CURL: " . $curlError . " | Response: " . $response);
    header("Location: " . getBaseUrl() . "/deposit?error=payment_gateway_error");
    exit();
}

$responseData = json_decode($response, true);

if ($httpCode !== 200 || !$responseData) {
    error_log("SimplyPay Payment Error - Invalid Response | HTTP: " . $httpCode . " | Response: " . $response);
    header("Location: " . getBaseUrl() . "/deposit?error=payment_gateway_error");
    exit();
}

if ($responseData['code'] !== 0) {
    $errorMsg = $responseData['msg'] ?? ($responseData['error'] ?? 'Unknown error');
    $errorCode = $responseData['code'] ?? 'N/A';
    error_log("SimplyPay Payment Error - Code: " . $errorCode . " | Message: " . $errorMsg . " | Full Response: " . json_encode($responseData));
    header("Location: " . getBaseUrl() . "/deposit?error=payment_failed&code=" . urlencode($errorCode));
    exit();
}

// Extract payment link from response
$paymentLink = $responseData['data']['params']['paymentLink'] ?? null;

if (!$paymentLink) {
    die("Payment gateway error: Payment link not received");
}

// Update deposit with merOrderNo (callback will search by this)
// IMPORTANT: Callback searches by merOrderNo in order_id field
$stmt = $pdo->prepare("UPDATE deposits SET order_id = ? WHERE id = ?");
$stmt->execute([$merOrderNo, $deposit_id]);

// Optionally save SimplyPay's orderNo in utr field for reference
$orderNo = $responseData['data']['orderNo'] ?? null;
if ($orderNo) {
    $stmt = $pdo->prepare("UPDATE deposits SET utr = ? WHERE id = ?");
    $stmt->execute([$orderNo, $deposit_id]);
}

// Redirect to payment link
header("Location: " . $paymentLink);
exit();
?>
