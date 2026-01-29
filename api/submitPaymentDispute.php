<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if deposit_disputes table exists, create if not
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS deposit_disputes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            deposit_id INT NOT NULL,
            user_id INT NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            screenshot_path VARCHAR(500),
            user_message TEXT,
            status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
            admin_response TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (deposit_id) REFERENCES deposits(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
} catch (PDOException $e) {
    // Table might already exist, continue
}

// Get deposit info
$deposit_id = isset($_POST['deposit_id']) ? (int)$_POST['deposit_id'] : 0;
$user_message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (!$deposit_id) {
    echo json_encode(["error" => "Deposit ID is required"]);
    exit();
}

// Verify deposit belongs to user
$stmt = $pdo->prepare("SELECT id, amount, status, created_at FROM deposits WHERE id = ? AND user_id = ?");
$stmt->execute([$deposit_id, $user_id]);
$deposit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$deposit) {
    echo json_encode(["error" => "Deposit not found"]);
    exit();
}

// Check if already submitted
$stmt = $pdo->prepare("SELECT id FROM deposit_disputes WHERE deposit_id = ? AND user_id = ?");
$stmt->execute([$deposit_id, $user_id]);
if ($stmt->fetch()) {
    echo json_encode(["error" => "Dispute already submitted for this deposit"]);
    exit();
}

// Handle file upload
$screenshot_path = null;
if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../uploads/payment_disputes/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0775, true)) {
            echo json_encode(["error" => "Failed to create upload directory. Please contact admin."]);
            exit();
        }
        // Try to set ownership if possible
        @chown($upload_dir, 'www');
        @chgrp($upload_dir, 'www');
    }
    
    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        @chmod($upload_dir, 0775);
        if (!is_writable($upload_dir)) {
            echo json_encode(["error" => "Upload directory is not writable. Please contact admin."]);
            exit();
        }
    }
    
    $file_extension = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        echo json_encode(["error" => "Invalid file type. Only images are allowed."]);
        exit();
    }
    
    $file_name = 'dispute_' . $deposit_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $file_name;
    
    if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $file_path)) {
        // Set file permissions
        @chmod($file_path, 0644);
        $screenshot_path = '/uploads/payment_disputes/' . $file_name;
    } else {
        $error_msg = "Failed to upload screenshot";
        if (function_exists('error_get_last')) {
            $last_error = error_get_last();
            if ($last_error) {
                $error_msg .= ": " . $last_error['message'];
            }
        }
        echo json_encode(["error" => $error_msg]);
        exit();
    }
}

// Insert dispute record
$stmt = $pdo->prepare("
    INSERT INTO deposit_disputes (deposit_id, user_id, amount, screenshot_path, user_message, status)
    VALUES (?, ?, ?, ?, ?, 'pending')
");
$stmt->execute([$deposit_id, $user_id, $deposit['amount'], $screenshot_path, $user_message]);

echo json_encode([
    "success" => true,
    "message" => "Dispute submitted successfully. Admin will review it soon."
]);
?>
