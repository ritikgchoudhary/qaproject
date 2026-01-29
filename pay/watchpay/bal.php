<?php
$merchantId = "100225567";
$merchantKey = "CJ7BUKZLQQ11I1ON0QZERKFUSQRCWBYC";
$apiUrl = "https://api.watchglb.com/query/balance";

$params = [
    "mch_id"    => $merchantId,
    "sign_type" => "MD5", // as per API docs
];

// Step 1: Generate sign (exclude 'sign' and 'sign_type')
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

// Lowercase MD5 as per API example
$sign = strtolower(md5($queryString));

// Add sign to params
$params["sign"] = $sign;

// Step 2: Make CURL request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

$response = curl_exec($ch);

// Handle error
if ($response === false) {
    echo "❌ CURL Error: " . curl_error($ch) . "\n";
}

curl_close($ch);
// Parse the JSON response
$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ Invalid JSON response: $response\n";
    exit;
}

// Check if response is SUCCESS
if (isset($data['respCode']) && $data['respCode'] === 'SUCCESS') {
    echo  $data['availableAmount'];
} else {
    echo "❌ API Error: " . ($data['errorMsg'] ?? 'Unknown error') . "\n";
}

