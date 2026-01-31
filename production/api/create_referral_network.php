<?php
// api/create_referral_network.php
include 'config.php';
include 'utils.php';

header('Content-Type: application/json');

// Check for admin/debug secret or just allow for localhost for now
// if ($_SERVER['REMOTE_ADDR'] !== '::1' && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
//     die(json_encode(["error" => "Localhost only"]));
// }

$parent_ref_code = isset($_GET['ref']) ? trim($_GET['ref']) : null;
$count = isset($_GET['count']) ? intval($_GET['count']) : 5;
$password_plain = "123456";
$password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

if (!$parent_ref_code) {
    echo json_encode(["error" => "Please provide a referral code using ?ref=CODE"]);
    exit();
}

// Verify parent exists
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE referral_code = ?");
$stmt->execute([$parent_ref_code]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
    echo json_encode(["error" => "Referral code '$parent_ref_code' not found."]);
    exit();
}

$created_users = [];

try {
    for ($i = 0; $i < $count; $i++) {
        $pdo->beginTransaction();

        $random_suffix = mt_rand(10000, 99999);
        $name = "Ref User " . $random_suffix;
        // Generate random 10 digit mobile
        $mobile = "9" . mt_rand(100000000, 999999999); 
        $email = $mobile . "@test.com";

        // Generate unique referral code
        $new_ref_code = "TEST" . strtoupper(bin2hex(random_bytes(4)));
        while(true) {
            $check = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
            $check->execute([$new_ref_code]);
            if($check->rowCount() == 0) break;
            $new_ref_code = "TEST" . strtoupper(bin2hex(random_bytes(4)));
        }

        $stmt = $pdo->prepare("INSERT INTO users (name, mobile, email, password, plain_password, referral_code, referred_by, level) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$name, $mobile, $email, $password_hash, $password_plain, $new_ref_code, $parent_ref_code]);
        $user_id = $pdo->lastInsertId();

        // Create Wallet
        $stmt = $pdo->prepare("INSERT INTO wallets (user_id) VALUES (?)");
        $stmt->execute([$user_id]);

        $pdo->commit();

        $created_users[] = [
            "id" => $user_id,
            "name" => $name,
            "mobile" => $mobile,
            "referral_code" => $new_ref_code,
            "referred_by" => $parent_ref_code
        ];
    }

    // Update parent stats once at the end (or per user if strictly following logic)
    // We call the utility function that checks logic
    checkAndUnlockParams($pdo, $parent['id']);

    echo json_encode([
        "success" => true,
        "parent" => $parent,
        "created_count" => count($created_users),
        "users" => $created_users
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["error" => $e->getMessage()]);
}
?>
