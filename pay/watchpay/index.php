<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("samparka.php");

// ============================
// Security Check
// ============================
if (!isset($_GET['amount'], $_GET['tyid'], $_GET['uid'], $_GET['sign'])) {
    http_response_code(200);
    echo json_encode([
        'code' => 405,
        'message' => 'Illegal access!',
    ]);
    exit;
}

// ============================
// Collect Request Params
// ============================
$ramt = (string) $_GET['amount'] . ".00";
$tyid = $_GET['tyid'];
$uid  = $_GET['uid'];
$sign = $_GET['sign'];

$date      = date("Ymd");
$time      = time();
$serial    = $date . $time . rand(100000, 999900);
$createdate = date("Y-m-d H:i:s");
$payName   = 'PASS PAY';

// ============================
// Check if UID in demo table
// ============================
$demoQuery = "SELECT 1 FROM demo WHERE balakedara = '$uid'";
$demoResult = $conn->query($demoQuery);

if ($demoResult && $demoResult->num_rows > 0) {
    $insertQuery = "
        INSERT INTO `thevani` (`balakedara`, `motta`, `dharavahi`, `mula`, `ullekha`, `duravani`, `ekikrtapavati`, `dinankavannuracisi`, `madari`, `pavatiaidi`, `sthiti`)
        VALUES ('$uid', '$ramt', '$serial', '$payName', 'N/A', 'N/A', 'N/A', '$createdate', '1005', '2', '1')
    ";
    $conn->query($insertQuery);

    $updateQuery = "UPDATE `shonu_kaichila` SET `motta` = `motta` + $ramt WHERE `balakedara` = '$uid'";
    $conn->query($updateQuery);

    require_once __DIR__ . '/../../config_loader.php';
    header('Location: ' . url_for('#/wallet/RechargeHistory'));
    exit;
}

// ============================
// User info check
// ============================
$numQuery = "SELECT mobile, codechorkamukala, createdate FROM shonu_subjects WHERE id = '$uid'";
$numResult = $conn->query($numQuery);
$numData   = $numResult->fetch_assoc();

if (!$numData) {
    echo json_encode(['code' => 404, 'message' => 'User not found']);
    exit;
}

// ============================
// Step: Call Payment API
// ============================
$merchantKey = "49fd706f0a924b679df02131a3df8794"; // apna merchant key daalna
$requestUrl  = "https://api.watchglb.com/pay/web"; // असली gateway URL डालो

$params = [
    "version"       => "1.0",
    "mch_id"        => "100225567",
    "notify_url"    => url_for('pay/watchpay/callback.php'),
    "page_url"      => url_for('#/wallet/RechargeHistory'),
    "mch_order_no"  => date("YmdHis"),
    "pay_type"      => '101',
    "trade_amount"  => $_GET['amount'],
    "order_date"    => date("Y-m-d H:i:s"),
    "goods_name"    => "Recharge",
    "mch_return_msg"=> "test",
    "sign_type"     => "MD5"
];

// 1. sign generate
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

// 2. call API using curl
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $requestUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo "cURL Error: " . curl_error($ch);
    exit;
}
curl_close($ch);

// 3. decode json response
$data = json_decode($response, true);

if (!$data || !isset($data['respCode']) || $data['respCode'] !== "SUCCESS") {
    echo "Error: Payment API Failed<br>Response: " . htmlspecialchars($response);
    exit;
}

$payLink    = $data['payInfo'];
$orderNo    = $data['orderNo'];
$mchOrderNo = $data['mchOrderNo'];
$upi        = 'WATCH PAY';
$createdate = date("Y-m-d H:i:s");

// ============================
// Step: Insert order in DB
// ============================
$insertPayment = "
    INSERT INTO `thevani`
    (`payid`, `balakedara`, `motta`, `dharavahi`, `mula`, `ullekha`, `duravani`, `ekikrtapavati`, `dinankavannuracisi`, `madari`, `pavatiaidi`, `sthiti`)
    VALUES ('2', '$uid', '$ramt', '$orderNo', '$upi', '$mchOrderNo', '$uid', '$upi', '$createdate', '1005', '2', '0')
";
$conn->query($insertPayment);

// ============================
// Redirect to Payment Page
// ============================
header("Location: $payLink");
exit;
?>
