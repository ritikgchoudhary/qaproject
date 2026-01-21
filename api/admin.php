<?php
include 'config.php';

$action = $_GET['action'] ?? '';

if ($action == 'getUsers') {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}
elseif ($action == 'getDeposits') {
    $stmt = $pdo->query("SELECT * FROM deposits ORDER BY created_at DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}
elseif ($action == 'getWithdraws') {
    $stmt = $pdo->query("SELECT * FROM withdraws ORDER BY created_at DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}
elseif ($action == 'approveWithdraw' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    $id = $data->id;
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE withdraws SET status = 'approved' WHERE id = ?");
        $stmt->execute([$id]);
        
        $stmt = $pdo->prepare("SELECT user_id, amount FROM withdraws WHERE id = ?");
        $stmt->execute([$id]);
        $w = $stmt->fetch();
        
        // Update total withdrawn
        $stmt = $pdo->prepare("UPDATE wallets SET total_withdrawn = total_withdrawn + ? WHERE user_id = ?");
        $stmt->execute([$w['amount'], $w['user_id']]);
        
        $pdo->commit();
        echo json_encode(["success" => true]);
    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode(["error" => "Failed"]);
    }
}
elseif ($action == 'rejectWithdraw' && $_SERVER['REQUEST_METHOD'] == 'POST') {
     $data = json_decode(file_get_contents("php://input"));
    $id = $data->id;
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE withdraws SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$id]);
        
        $stmt = $pdo->prepare("SELECT user_id, amount FROM withdraws WHERE id = ?");
        $stmt->execute([$id]);
        $w = $stmt->fetch();
        
        // Refund balance
        $stmt = $pdo->prepare("UPDATE wallets SET withdrawable_balance = withdrawable_balance + ? WHERE user_id = ?");
        $stmt->execute([$w['amount'], $w['user_id']]);
        
        $pdo->commit();
        echo json_encode(["success" => true]);
    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode(["error" => "Failed"]);
    }
}
?>
