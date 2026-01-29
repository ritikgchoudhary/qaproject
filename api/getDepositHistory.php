<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if deposit_disputes table exists
$table_exists = false;
try {
    $pdo->query("SELECT 1 FROM deposit_disputes LIMIT 1");
    $table_exists = true;
} catch (PDOException $e) {
    // Table doesn't exist yet
}

// Get all deposits for the user
$stmt = $pdo->prepare("
    SELECT id, amount, status, created_at, order_id 
    FROM deposits 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate hours since creation for each deposit and check dispute status
foreach ($deposits as &$deposit) {
    $created_time = strtotime($deposit['created_at']);
    $current_time = time();
    $hours_old = ($current_time - $created_time) / 3600;
    $deposit['hours_old'] = round($hours_old, 1);
    $deposit['can_report'] = ($deposit['status'] === 'pending' && $hours_old >= 3);
    
    // Check if dispute exists for this deposit
    $deposit['has_dispute'] = false;
    $deposit['dispute_status'] = null;
    if ($table_exists) {
        $stmt_dispute = $pdo->prepare("SELECT status FROM deposit_disputes WHERE deposit_id = ? AND user_id = ? LIMIT 1");
        $stmt_dispute->execute([$deposit['id'], $user_id]);
        $dispute = $stmt_dispute->fetch(PDO::FETCH_ASSOC);
        if ($dispute) {
            $deposit['has_dispute'] = true;
            $deposit['dispute_status'] = $dispute['status'];
        }
    }
}

echo json_encode(["deposits" => $deposits]);
?>
