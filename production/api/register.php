<?php
include 'config.php';
include 'utils.php';

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->name) || !isset($data->mobile) || !isset($data->password)) {
    echo json_encode(["error" => "Incomplete data"]);
    exit();
}

$name = htmlspecialchars(strip_tags($data->name));
$mobile = htmlspecialchars(strip_tags($data->mobile));
$password = password_hash($data->password, PASSWORD_DEFAULT);
$ref_code_input = isset($data->referral_code) ? trim($data->referral_code) : null;

// Basic Mobile Validation (Optional, can be stricter)
if (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
     echo json_encode(["error" => "Invalid mobile number format"]);
     exit();
}

// Check existing mobile
$stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ?");
$stmt->execute([$mobile]);
if ($stmt->rowCount() > 0) {
    echo json_encode(["error" => "Mobile number already registered"]);
    exit();
}

// Generate new referral code
$new_ref_code = "REF" . strtoupper(bin2hex(random_bytes(3)));
while(true) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
    $stmt->execute([$new_ref_code]);
    if($stmt->rowCount() == 0) break;
    $new_ref_code = "REF" . strtoupper(bin2hex(random_bytes(3)));
}

// Handle referral
$referred_by_code = null;
$referrer_id = null;

if ($ref_code_input) {
    $stmt = $pdo->prepare("SELECT id, referral_code FROM users WHERE referral_code = ?");
    $stmt->execute([$ref_code_input]);
    $referrer_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($referrer_data) {
        $referred_by_code = $referrer_data['referral_code'];
        $referrer_id = $referrer_data['id'];
    }
}

try {
    $pdo->beginTransaction();

    // Using Dummy Email if column is required in your original schema, or leave null if nullable.
    // Assuming 'email' might still be NOT NULL in some older schemas, we can set a dummy or handle it.
    // But since we are replacing it, ideally we insert into 'mobile'.
    // If 'email' is required by DB, we can put "mobile@placeholder.com"
    $dummy_email = $mobile . "@mobile.com";

    // INSERT into mobile column with Level 1
    $stmt = $pdo->prepare("INSERT INTO users (name, mobile, email, password, plain_password, referral_code, referred_by, level) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    if ($stmt->execute([$name, $mobile, $dummy_email, $password, $data->password, $new_ref_code, $referred_by_code])) {
        $user_id = $pdo->lastInsertId();
        
        // Create Wallet
        $stmt = $pdo->prepare("INSERT INTO wallets (user_id) VALUES (?)");
        $stmt->execute([$user_id]);

        $pdo->commit();

        // Check referrer unlock condition
        if ($referrer_id) {
            checkAndUnlockParams($pdo, $referrer_id);
        }

        echo json_encode(["message" => "Registration successful", "success" => true]);
    } else {
        $pdo->rollBack();
        echo json_encode(["error" => "Registration failed"]);
    }
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["error" => "Error: " . $e->getMessage()]);
}
?>
