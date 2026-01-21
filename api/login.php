<?php
include 'config.php';
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->mobile) || !isset($data->password)) {
    echo json_encode(["error" => "Incomplete data"]);
    exit();
}

$mobile = $data->mobile;
$password = $data->password;

// Check mobile column
$stmt = $pdo->prepare("SELECT id, name, mobile, password, referral_code, referred_by, role FROM users WHERE mobile = ?");
$stmt->execute([$mobile]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    unset($user['password']); // Don't return password
    echo json_encode(["message" => "Login successful", "success" => true, "user" => $user]);
} else {
    // Debug logging
    $log = date('Y-m-d H:i:s') . " - Login Failed for: $mobile. User found: " . ($user ? 'Yes' : 'No') . "\n";
    file_put_contents('debug_login.txt', $log, FILE_APPEND);

    echo json_encode(["error" => "Invalid credentials", "success" => false]);
}
?>
