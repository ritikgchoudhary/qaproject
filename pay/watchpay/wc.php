<?php
include("samparka.php");
// Always log incoming request for debugging
$rawData = file_get_contents("php://input");
file_put_contents("callback_log.txt", date("Y-m-d H:i:s") . " => " . $rawData . "\n", FILE_APPEND);

// Parse POST/GET data
$data = $_POST;
if (empty($data) && !empty($_GET)) {
    $data = $_GET;
}
if (empty($data) && !empty($rawData)) {
    parse_str($rawData, $data);
}

// Merchant key
$merchantKey = "CJ7BUKZLQQ11I1ON0QZERKFUSQRCWBYC";

// Required params check
$required = ['tradeResult','merTransferId','merNo','tradeNo','transferAmount','applyDate','version','respCode','sign'];
foreach ($required as $r) {
    if (!isset($data[$r])) {
        http_response_code(400);
        exit("❌ Missing param: $r");
    }
}

// Extract & remove sign
$sign = $data['sign'];
unset($data['sign'], $data['signType']);

// Sort params
ksort($data);

// Build signing string
$queryString = "";
foreach ($data as $key => $value) {
    $queryString .= $key . "=" . $value . "&";
}
$queryString .= "key=" . $merchantKey;

// Expected sign
$expectedSign = strtolower(md5($queryString));

// Verify
if ($sign !== $expectedSign) {
    http_response_code(400);
    exit("❌ Invalid signature");
}

// ✅ Signature OK → process
$orderId = $data['merTransferId']; 
$status  = $data['tradeResult'];   // 1=success, 2=failure
$tradeNo = $data['tradeNo'];       
$amount  = $data['transferAmount'];

// DB connect
include "db.php";

// Update payout table
if ($status === "1") {
     $update = mysqli_query($conn, "UPDATE hintegedukolli SET sthiti = '1' WHERE dharavahi = '".$orderId."'");
} else {
          // Step 1: Get user ID and amount from withdrawal record
$getData = mysqli_query($conn, "
    SELECT hintegedukolli.motta, hintegedukolli.balakedara 
    FROM hintegedukolli 
    WHERE dharavahi = '$orderId'
    LIMIT 1
");

if (!$getData || mysqli_num_rows($getData) == 0) {
    die("❌ Withdrawal record not found.");
}

$row = mysqli_fetch_assoc($getData);
$amount = floatval($row['motta']);
$userid = intval($row['balakedara']);

// Step 2: Return money to wallet
$sqlwallet = mysqli_query($conn, "
    UPDATE shonu_kaichila 
    SET motta = ROUND(motta + $amount, 2) 
    WHERE balakedara = '$userid'
");

if (!$sqlwallet) {
    die("❌ Error updating wallet: " . mysqli_error($conn));
}


        $sql = "UPDATE hintegedukolli SET sthiti = '2' WHERE dharavahi = '$orderId'";
        
        
   
}

if (!mysqli_query($conn, $sql)) {
    file_put_contents("callback_log.txt", date("Y-m-d H:i:s") . " => ❌ DB Error: " . mysqli_error($conn) . "\n", FILE_APPEND);
    http_response_code(500);
    exit("DB error");
}

// ✅ Important: Return plain text "success"
echo "success";
