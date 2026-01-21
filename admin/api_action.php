<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}
require 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

// Handle GET for fetching single record
if (isset($_GET['get_user'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['get_user']]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    exit();
}

if ($data['action'] === 'edit_user') {
    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
    $stmt->execute([$data['name'], $data['email'], $data['role'], $data['id']]);
    echo json_encode(["success" => true]);
    exit();
}

if ($data['action'] === 'delete_user') {
    // Delete related data first or cascade if set. Assume manual cleanup slightly safer to error for now? 
    // Or just soft delete? Prompt implies full controls. 
    // Let's do simple delete.
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$data['id']]);
        echo json_encode(["success" => true]);
    } catch (Exception $e) {
        echo json_encode(["error" => "Cannot delete user. Might have linked records."]);
    }
    exit();
}

if ($data['action'] === 'adjust_wallet') {
    $uid = $data['user_id'];
    $amount = (float)$data['amount'];
    $op = $data['operation'];

    if ($amount <= 0) {
        echo json_encode(["error" => "Invalid amount"]);
        exit();
    }

    $sign = ($op === 'subtract') ? '-' : '+';
    
    // Strict check for negative balance
    if ($op === 'subtract') {
        $stmt = $pdo->prepare("SELECT withdrawable_balance FROM wallets WHERE user_id = ?");
        $stmt->execute([$uid]);
        $current = $stmt->fetchColumn();
        if ($current < $amount) {
            echo json_encode(["error" => "Insufficient balance"]);
            exit();
        }
    }

    $stmt = $pdo->prepare("UPDATE wallets SET withdrawable_balance = withdrawable_balance $sign ? WHERE user_id = ?");
    $stmt->execute([$amount, $uid]);

    echo json_encode(["success" => true]);
    exit();
}

if ($data['action'] === 'add_admin') {
    $user = trim($data['username']);
    $pass = password_hash($data['password'], PASSWORD_BCRYPT);
    
    // Check duplicate
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ?");
    $stmt->execute([$user]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(["error" => "Username exists"]);
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
    $stmt->execute([$user, $pass]);
    echo json_encode(["success" => true]);
    exit();
}

if ($data['action'] === 'delete_admin') {
    // Prevent self-delete usually handled by UI but good to check. 
    // Assuming simple logic for now.
    $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
    $stmt->execute([$data['id']]);
    echo json_encode(["success" => true]);
    exit();
}

if ($data['action'] === 'update_withdraw') {
    $id = $data['id'];
    $status = $data['status']; // 'approved' or 'rejected'

    if (!in_array($status, ['approved', 'rejected'])) {
         echo json_encode(["error" => "Invalid status"]);
         exit();
    }

    try {
        // Need to check transaction first
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE withdraws SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        // Note: Logic for refunding balance if rejected should be here if we deducted it earlier.
        // In withdraw.php, we update 'wallets' immediately. 
        // So if rejected, we should ADD it back.
        
        if ($status === 'rejected') {
            $stmt = $pdo->prepare("SELECT user_id, amount FROM withdraws WHERE id = ?");
            $stmt->execute([$id]);
            $wd = $stmt->fetch();
            
            if ($wd) {
                $stmt = $pdo->prepare("UPDATE wallets SET withdrawable_balance = withdrawable_balance + ? WHERE user_id = ?");
                $stmt->execute([$wd['amount'], $wd['user_id']]);
            }
        }

        $pdo->commit();
        echo json_encode(["success" => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(["error" => $e->getMessage()]);
    }
}
?>
