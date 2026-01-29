<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("samparka.php");

// Log function (optional)
function logToFile($msg) {
    file_put_contents("callback_log.txt", date("Y-m-d H:i:s") . " - " . $msg . "\n", FILE_APPEND);
}

// ==========================
// Collect POST parameters
// ==========================
$tradeResult = $_POST['tradeResult'] ?? '';
$mchId       = $_POST['mchId'] ?? '';
$mchOrderNo  = $_POST['mchOrderNo'] ?? '';
$oriAmount   = $_POST['oriAmount'] ?? ($_POST['originalAmount'] ?? ''); // कभी originalAmount आता है
$amount      = $_POST['amount'] ?? '';
$orderDate   = $_POST['orderDate'] ?? '';
$orderNo     = $_POST['orderNo'] ?? '';
$merRetMsg   = $_POST['merRetMsg'] ?? '';
$signType    = $_POST['signType'] ?? '';
$sign        = $_POST['sign'] ?? '';

// ==========================
// Step 1: Validate Sign
// ==========================
$merchantKey = "49fd706f0a924b679df02131a3df8794"; // apna merchant key daalna

// build sign string (exclude sign & signType & empty)
$params = $_POST;
unset($params['sign'], $params['signType']);
$params = array_filter($params, function($v) { return $v !== ''; });

// sort by ASCII order
ksort($params);

// query string
$queryString = "";
foreach ($params as $k => $v) {
    $queryString .= $k . "=" . $v . "&";
}
$queryString .= "key=" . $merchantKey;

// md5 lower
$checkSign = strtolower(md5($queryString));

if ($checkSign !== $sign) {
    logToFile("❌ Invalid signature. Received: $sign, Expected: $checkSign");
    http_response_code(400);
    exit("sign error");
}

// ==========================
// Step 2: Check if success
// ==========================
if ($tradeResult != "1") {
    logToFile("❌ Payment failed. tradeResult=$tradeResult for Order=$mchOrderNo");
    http_response_code(200);
    exit("fail");
}

// ==========================
// Step 3: Process order in DB
// ==========================
$outTradeNo = $mchOrderNo; // merchant order no used as dharavahi

$sqlCheck = "SELECT motta, balakedara FROM thevani WHERE ullekha = '$outTradeNo' AND sthiti = '0'";
$checkamt = mysqli_query($conn, $sqlCheck);

if (!$checkamt) {
    logToFile("❌ SQL Error: " . mysqli_error($conn));
    http_response_code(500);
    exit("SQL error");
}

if (mysqli_num_rows($checkamt) >= 1) {
    $row     = mysqli_fetch_assoc($checkamt);
    $motta   = $row['motta'];
    $shonuid = $row['balakedara'];

    logToFile("💰 Processing payment for user: $shonuid, Amount: $motta, Order=$outTradeNo");

    // Update Balance
    $sqlBalance = "UPDATE shonu_kaichila SET motta = ROUND(motta + $motta, 2) WHERE balakedara = '$shonuid'";
    if (!mysqli_query($conn, $sqlBalance)) {
        logToFile("❌ Balance update failed: " . mysqli_error($conn));
        http_response_code(500);
        exit("Balance update error");
    }

    // Update Order Status
    $sqlUpdate = "UPDATE thevani SET sthiti = '1' WHERE ullekha = '$outTradeNo'";
    if (!mysqli_query($conn, $sqlUpdate)) {
        logToFile("❌ Order update failed: " . mysqli_error($conn));
        http_response_code(500);
        exit("Order update error");
    }
  // ==========================
    // Step 4: Referral Bonus (First Recharge Check)
    // ==========================
    // Check if this user ka pehla recharge hai
    $sqlFirst = "SELECT COUNT(*) as cnt FROM thevani WHERE balakedara = '$shonuid' AND sthiti = '1'";
    $resFirst = mysqli_query($conn, $sqlFirst);
    $firstRow = mysqli_fetch_assoc($resFirst);

    if ($firstRow && $firstRow['cnt'] == 1) {
        // User ka pehla recharge hai
        logToFile("🎉 First recharge for user $shonuid");

        // Find referrer user from shonu_subjects (owncode match hoga user ke code me)
        $sqlUser = "SELECT id, code, owncode FROM shonu_subjects WHERE id = '$shonuid' LIMIT 1";
        $resUser = mysqli_query($conn, $sqlUser);
        $userRow = mysqli_fetch_assoc($resUser);

        if ($userRow && !empty($userRow['code'])) {
            $refCode = $userRow['code'];

            // Find referrer by owncode
            $sqlRef = "SELECT id FROM shonu_subjects WHERE owncode = '$refCode' LIMIT 1";
            $resRef = mysqli_query($conn, $sqlRef);
            $refRow = mysqli_fetch_assoc($resRef);

            if ($refRow) {
                $refUid = $refRow['id'];
                $reward = round($motta * 0.10, 2);

                // Update referrer balance
                $sqlReward = "UPDATE shonu_kaichila SET motta = ROUND(motta + $reward, 2) WHERE balakedara = '$refUid'";
                if (mysqli_query($conn, $sqlReward)) {
                    logToFile("🎁 Referral reward of $reward given to user $refUid (Referrer of $shonuid)");

                    // Insert into reward log table
                    $sqlLog = "INSERT INTO reward_log (user_id, ref_user_id, order_no, amount, reward, created_at) 
                               VALUES ('$shonuid', '$refUid', '$outTradeNo', '$motta', '$reward', NOW())";
                    mysqli_query($conn, $sqlLog);
                } else {
                    logToFile("❌ Failed to give referral reward: " . mysqli_error($conn));
                }
            }
        }
    }
    logToFile("✅ Payment processed successfully for order $outTradeNo");

    // Very important → tell gateway "success"
    echo "success";
    exit;
} else {
    logToFile("⚠️ Order not found or already processed: $outTradeNo");
    echo "success"; // Already processed, still reply success
    exit;
}
?>
