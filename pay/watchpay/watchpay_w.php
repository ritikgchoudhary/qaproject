<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("samparka.php");

// ==========================
// CONFIGURATION
// ==========================
$correctPassword = "Mango@2003";  // URL access password
$merchantKey     = "CJ7BUKZLQQ11I1ON0QZERKFUSQRCWBYC";
$merchantId      = "100225567";
$requestUrl      = "https://api.watchglb.com/pay/transfer";
$withdrawPass    = "Mango@2003";

// ==========================
// Bank List Allowed
// ==========================
$banks = [
    "Canara Bank" => "IDPT0001",
    "DCB Bank" => "IDPT0002",
    "Federal Bank" => "IDPT0003",
    "HDFC Bank" => "IDPT0004",
    "Punjab National Bank" => "IDPT0005",
    "Indian Bank" => "IDPT0006",
    "ICICI Bank" => "IDPT0007",
    "Syndicate Bank" => "IDPT0008",
    "Karur Vysya Bank" => "IDPT0009",
    "Union Bank of India" => "IDPT0010",
    "Kotak Mahindra Bank" => "IDPT0011",
    "IDFC First Bank" => "IDPT0012",
    "Andhra Bank" => "IDPT0013",
    "Karnataka Bank" => "IDPT0014",
    "ICICI Corporate Bank" => "IDPT0015",
    "Axis Bank" => "IDPT0016",
    "UCO Bank" => "IDPT0017",
    "South Indian Bank" => "IDPT0018",
    "Yes Bank" => "IDPT0019",
    "Standard Chartered Bank" => "IDPT0020",
    "State Bank of India" => "IDPT0021",
    "Indian Overseas Bank" => "IDPT0022",
    "Bandhan Bank" => "IDPT0023",
    "Central Bank of India" => "IDPT0024",
    "Bank of Baroda" => "IDPT0025",
];

// ==========================
// SESSION VERIFICATION
// ==========================
if (isset($_GET['pass']) && !isset($_SESSION['is_verified'])) {
    if ($_GET['pass'] === $correctPassword) {
        $_SESSION['is_verified'] = true;
        echo "✅ Access granted. You can now use the URL parameters.<br>";
    } else {
        echo "❌ Invalid access password in URL.<br>";
    }
}

// Check if verified
if (!isset($_SESSION['is_verified']) || $_SESSION['is_verified'] !== true) {
    echo "❌ Access Denied. Password required.<br>";
}

// ==========================
// GET PARAMETERS
// ==========================
$order_id = $_GET['order_id'] ?? null;
$name     = $_GET['name'] ?? null;
$account  = $_GET['account'] ?? null;
$bankName = $_GET['bank'] ?? null;
$amount   = $_GET['amount'] ?? null;
$password = $_GET['pass'] ?? null;
$remark   = $_GET['ifsc'] ?? $bankName;

// Collect missing parameters
$required = ['name', 'account', 'bank', 'amount', 'order_id', 'pass'];
$missing  = [];
foreach ($required as $param) {
    if (!isset($_GET[$param]) || empty($_GET[$param])) {
        $missing[] = $param;
    }
}

if ($missing) {
    echo "❌ Missing required parameters: " . implode(", ", $missing) . "<br>";
} else {
    // ==========================
    // Withdraw password check
    // ==========================
    if ($password !== $withdrawPass) {
        echo "❌ Invalid withdraw password!<br>";
    } 
    // ==========================
    // Bank validation
    // ==========================
    elseif (!array_key_exists($bankName, $banks)) {
        echo "❌ Invalid Bank Name! Allowed Banks: " . implode(", ", array_keys($banks)) . "<br>";
    } 
    else {
        $ifsc = $banks[$bankName]; // IFSC code from bank list

        // ==========================
        // Prepare cURL parameters
        // ==========================
       $params = [
            "sign_type"       => "MD5",
            "mch_id"          => $merchantId,
            "mch_transferId"  => $order_id,
            "transfer_amount" => $amount,
            "apply_date"      => date("Y-m-d H:i:s"),
            "bank_code"       => $ifsc,
            "receive_name"    => $name,
            "receive_account" => $account,
            "remark"          => $remark,
            "back_url"        => url_for('pay/watchpay/wc.php')
        ];
        
        // Build signature
        $signParams = $params;
        unset($signParams['sign'], $signParams['sign_type']);
        ksort($signParams);

        $queryString = "";
        foreach ($signParams as $key => $value) {
            $queryString .= $key . "=" . $value . "&";
        }
        $queryString .= "key=" . $merchantKey;

        $params["sign"] = strtolower(md5($queryString));

        // cURL Request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);

        $response = curl_exec($ch);
        curl_close($ch);

        // Response Handling
        $data = json_decode($response, true);
        if ($data && isset($data['respCode'])) {
            if ($data['respCode'] === 'SUCCESS') {
                echo "✅ Transfer Successful!<br>";
                echo "Trade No: " . htmlspecialchars($data['tradeNo']) . "<br>";
                echo "Amount: " . htmlspecialchars($data['transferAmount']) . "<br>";
                // Optional: DB update
                if (isset($conn)) {
                    mysqli_query($conn, "UPDATE hintegedukolli SET sthiti='3' WHERE dharavahi='".$order_id."'");
                }
            } else {
                echo "❌ Transfer Failed: " . htmlspecialchars($data['errorMsg']) . "<br>";
            }
        } else {
            echo "❌ Invalid JSON Response<br>";
            echo "<pre>" . htmlspecialchars($response) . "</pre>";
        }
    }
}

// ==========================
// Show current URL (for debugging)
// ==========================
echo "<hr>Current URL: " . htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]") . "<br>";
?>
