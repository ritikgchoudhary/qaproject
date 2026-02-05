<?php
require_once __DIR__ . '/../../api/config.php';

$deposit_id = $_GET['deposit_id'] ?? 0;

if (!$deposit_id) {
    die("Invalid deposit ID");
}

// Get deposit details
$stmt = $pdo->prepare("SELECT d.*, u.name, u.email FROM deposits d JOIN users u ON d.user_id = u.id WHERE d.id = ?");
$stmt->execute([$deposit_id]);
$deposit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$deposit) {
    die("Deposit not found");
}

$baseUrl = getBaseUrl();
$notifyUrl = $baseUrl . "/pay/watchpay/deposit_callback.php";
$pageUrl = $baseUrl . "/deposit";

// WatchPay Configuration
$merchantId = "F7092";
$secretKey = "89055L7zH3";

// Prepare payment data
$paymentData = [
    'merchant_id' => $merchantId,
    'amount' => $deposit['amount'],
    'order_id' => 'DEP' . $deposit_id,
    'notify_url' => $notifyUrl,
    'page_url' => $pageUrl,
    'extra' => [
        'deposit_id' => $deposit_id
    ]
];

// Generate signature
ksort($paymentData);
$signString = '';
foreach ($paymentData as $key => $value) {
    if ($key === 'extra' && is_array($value)) {
        ksort($value);
        foreach ($value as $ek => $ev) {
            $signString .= $ek . '=' . $ev . '&';
        }
    } else {
        $signString .= $key . '=' . $value . '&';
    }
}
$signString = rtrim($signString, '&') . $secretKey;
$signature = md5($signString);
$paymentData['sign'] = $signature;

// WatchPay API endpoint
$apiUrl = "https://api.watchpay.in/v1/payment/create";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Processing Payment - WatchPay</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .loading { margin: 20px 0; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Processing Payment...</h2>
        <div class="loading">Please wait while we redirect you to WatchPay...</div>
        <form id="paymentForm" method="POST" action="<?= $apiUrl ?>">
            <?php foreach ($paymentData as $key => $value): ?>
                <?php if (is_array($value)): ?>
                    <?php foreach ($value as $ek => $ev): ?>
                        <input type="hidden" name="<?= htmlspecialchars($key . '[' . $ek . ']') ?>" value="<?= htmlspecialchars($ev) ?>">
                    <?php endforeach; ?>
                <?php else: ?>
                    <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                <?php endif; ?>
            <?php endforeach; ?>
        </form>
        <a href="<?= $baseUrl ?>/deposit" class="btn">Cancel</a>
    </div>
    <script>
        document.getElementById('paymentForm').submit();
    </script>
</body>
</html>
