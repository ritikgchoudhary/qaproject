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

// Handle GET for fetching agent statistics
if (isset($_GET['action']) && $_GET['action'] === 'get_agent_stats') {
    try {
        $agent_id = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
        
        if (!$agent_id) {
            echo json_encode(['error' => 'Agent ID is required']);
            exit();
        }
        
        // Verify agent role
        $stmt = $pdo->prepare("SELECT id, name, referral_code, role FROM users WHERE id = ?");
        $stmt->execute([$agent_id]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$agent || $agent['role'] !== 'agent') {
            echo json_encode(['error' => 'User is not an agent']);
            exit();
        }
        
        // Time ranges
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');
        $yesterday_start = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $yesterday_end = date('Y-m-d 23:59:59', strtotime('-1 day'));
        $month_start = date('Y-m-01 00:00:00');
        $month_end = date('Y-m-t 23:59:59');
        
        $stats = [
            'agent_info' => [
                'id' => $agent['id'],
                'name' => $agent['name'],
                'referral_code' => $agent['referral_code']
            ],
            'users_joined' => [
                'today' => 0,
                'yesterday' => 0,
                'this_month' => 0,
                'all' => 0
            ],
            'deposits' => [
                'today' => 0,
                'yesterday' => 0,
                'this_month' => 0,
                'all' => 0
            ],
            'commissions' => [
                'today' => 0,
                'yesterday' => 0,
                'this_month' => 0,
                'all' => 0
            ],
            'wallet' => [
                'withdrawable' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0
            ]
        ];
        
        // Get users joined (users referred by this agent)
        // Today
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ? AND created_at >= ? AND created_at <= ?");
        $stmt->execute([$agent['referral_code'], $today_start, $today_end]);
        $stats['users_joined']['today'] = (int)$stmt->fetchColumn();
        
        // Yesterday
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ? AND created_at >= ? AND created_at <= ?");
        $stmt->execute([$agent['referral_code'], $yesterday_start, $yesterday_end]);
        $stats['users_joined']['yesterday'] = (int)$stmt->fetchColumn();
        
        // This month
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ? AND created_at >= ? AND created_at <= ?");
        $stmt->execute([$agent['referral_code'], $month_start, $month_end]);
        $stats['users_joined']['this_month'] = (int)$stmt->fetchColumn();
        
        // All
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
        $stmt->execute([$agent['referral_code']]);
        $stats['users_joined']['all'] = (int)$stmt->fetchColumn();
        
        // Get deposits from users referred by this agent
        // Today
        $stmt = $pdo->prepare("
            SELECT SUM(d.amount) 
            FROM deposits d
            JOIN users u ON d.user_id = u.id
            WHERE u.referred_by = ? AND d.status IN ('success', 'approved', 'completed') 
            AND d.created_at >= ? AND d.created_at <= ?
        ");
        $stmt->execute([$agent['referral_code'], $today_start, $today_end]);
        $stats['deposits']['today'] = (float)($stmt->fetchColumn() ?: 0);
        
        // Yesterday
        $stmt = $pdo->prepare("
            SELECT SUM(d.amount) 
            FROM deposits d
            JOIN users u ON d.user_id = u.id
            WHERE u.referred_by = ? AND d.status IN ('success', 'approved', 'completed') 
            AND d.created_at >= ? AND d.created_at <= ?
        ");
        $stmt->execute([$agent['referral_code'], $yesterday_start, $yesterday_end]);
        $stats['deposits']['yesterday'] = (float)($stmt->fetchColumn() ?: 0);
        
        // This month
        $stmt = $pdo->prepare("
            SELECT SUM(d.amount) 
            FROM deposits d
            JOIN users u ON d.user_id = u.id
            WHERE u.referred_by = ? AND d.status IN ('success', 'approved', 'completed') 
            AND d.created_at >= ? AND d.created_at <= ?
        ");
        $stmt->execute([$agent['referral_code'], $month_start, $month_end]);
        $stats['deposits']['this_month'] = (float)($stmt->fetchColumn() ?: 0);
        
        // All
        $stmt = $pdo->prepare("
            SELECT SUM(d.amount) 
            FROM deposits d
            JOIN users u ON d.user_id = u.id
            WHERE u.referred_by = ? AND d.status IN ('success', 'approved', 'completed')
        ");
        $stmt->execute([$agent['referral_code']]);
        $stats['deposits']['all'] = (float)($stmt->fetchColumn() ?: 0);
        
        // Get commissions (tree bonus)
        // Today
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM agent_commissions WHERE agent_id = ? AND created_at >= ? AND created_at <= ?");
        $stmt->execute([$agent_id, $today_start, $today_end]);
        $stats['commissions']['today'] = (float)($stmt->fetchColumn() ?: 0);
        
        // Yesterday
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM agent_commissions WHERE agent_id = ? AND created_at >= ? AND created_at <= ?");
        $stmt->execute([$agent_id, $yesterday_start, $yesterday_end]);
        $stats['commissions']['yesterday'] = (float)($stmt->fetchColumn() ?: 0);
        
        // This month
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM agent_commissions WHERE agent_id = ? AND created_at >= ? AND created_at <= ?");
        $stmt->execute([$agent_id, $month_start, $month_end]);
        $stats['commissions']['this_month'] = (float)($stmt->fetchColumn() ?: 0);
        
        // All
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM agent_commissions WHERE agent_id = ?");
        $stmt->execute([$agent_id]);
        $stats['commissions']['all'] = (float)($stmt->fetchColumn() ?: 0);
        
        // Get wallet info
        $stmt = $pdo->prepare("SELECT withdrawable_balance, total_withdrawn FROM wallets WHERE user_id = ?");
        $stmt->execute([$agent_id]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($wallet) {
            $stats['wallet']['withdrawable'] = (float)($wallet['withdrawable_balance'] ?: 0);
            $stats['wallet']['total_withdrawn'] = (float)($wallet['total_withdrawn'] ?: 0);
        }
        $stats['wallet']['total_earned'] = $stats['commissions']['all'];
        
        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// Handle GET for fetching users list (Infinite Scroll)
if (isset($_GET['action']) && $_GET['action'] === 'get_users') {
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $search = $_GET['search'] ?? '';
        $role = $_GET['role'] ?? '';

        $where_clause = "";
        $params = [];
        $conditions = [];
        
        if ($search) {
            $conditions[] = "(users.name LIKE ? OR users.email LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($role) {
            $conditions[] = "users.role = ?";
            $params[] = $role;
        }
        
        if (!empty($conditions)) {
            $where_clause = "WHERE " . implode(" AND ", $conditions);
        }

        // Fix ambition column names by adding table prefixes
        $sql = "SELECT users.*, wallets.withdrawable_balance as wallet_balance 
                FROM users 
                LEFT JOIN wallets ON users.id = wallets.user_id 
                $where_clause 
                ORDER BY users.id DESC 
                LIMIT $limit OFFSET $offset";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['users' => $users]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// Handle GET for fetching deposits list (Infinite Scroll)
if (isset($_GET['action']) && $_GET['action'] === 'get_deposits') {
    try {
        // Auto-fail deposits older than 2 days (48 hours)
        $two_days_ago = date('Y-m-d H:i:s', strtotime('-2 days'));
        $stmt = $pdo->prepare("UPDATE deposits SET status = 'failed' WHERE status = 'pending' AND created_at < ?");
        $stmt->execute([$two_days_ago]);
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $sql = "SELECT d.*, u.name, u.email 
                FROM deposits d 
                JOIN users u ON d.user_id = u.id 
                ORDER BY d.created_at DESC 
                LIMIT $limit OFFSET $offset";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate time elapsed for each deposit
        foreach ($deposits as &$deposit) {
            $created_time = strtotime($deposit['created_at']);
            $current_time = time();
            $minutes_old = ($current_time - $created_time) / 60;
            $hours_old = $minutes_old / 60;
            
            $deposit['minutes_old'] = round($minutes_old, 1);
            $deposit['hours_old'] = round($hours_old, 1);
            $deposit['can_approve'] = ($deposit['status'] === 'pending' && $minutes_old >= 10);
        }

        echo json_encode(['deposits' => $deposits]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

if ($data['action'] === 'edit_user') {
    // Get current role to check if we need to set default commission
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$data['id']]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If changing to agent and doesn't have commission set, set default 50%
    if ($data['role'] === 'agent' && (!$current_user || $current_user['role'] !== 'agent')) {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, direct_commission_percentage = COALESCE(direct_commission_percentage, 50.00) WHERE id = ?");
        $stmt->execute([$data['name'], $data['email'], $data['role'], $data['id']]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
        $stmt->execute([$data['name'], $data['email'], $data['role'], $data['id']]);
    }
    echo json_encode(["success" => true]);
    exit();
}

if ($data['action'] === 'update_direct_commission') {
    $agent_id = (int)$data['agent_id'];
    $percentage = (float)$data['percentage'];
    
    // Validate percentage (0-100)
    if ($percentage < 0 || $percentage > 100) {
        echo json_encode(["error" => "Percentage must be between 0 and 100"]);
        exit();
    }
    
    // Verify user is an agent
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$agent_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || $user['role'] !== 'agent') {
        echo json_encode(["error" => "User is not an agent"]);
        exit();
    }
    
    // Update commission percentage
    $stmt = $pdo->prepare("UPDATE users SET direct_commission_percentage = ? WHERE id = ?");
    $stmt->execute([$percentage, $agent_id]);
    
    echo json_encode(["success" => true, "message" => "Direct commission percentage updated successfully"]);
    exit();
}

if ($data['action'] === 'approve_commission') {
    $commission_id = (int)$data['commission_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Get commission details
        $stmt = $pdo->prepare("SELECT agent_id, COALESCE(adjusted_amount, amount) as final_amount FROM agent_commissions WHERE id = ? AND status = 'pending'");
        $stmt->execute([$commission_id]);
        $commission = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$commission) {
            throw new Exception("Commission not found or already processed");
        }
        
        $agent_id = $commission['agent_id'];
        $final_amount = $commission['final_amount'];
        
        // Update commission status to approved
        $stmt = $pdo->prepare("UPDATE agent_commissions SET status = 'approved' WHERE id = ?");
        $stmt->execute([$commission_id]);
        
        // Add to agent's withdrawable balance
        $stmt = $pdo->prepare("INSERT INTO wallets (user_id, withdrawable_balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE withdrawable_balance = withdrawable_balance + ?");
        $stmt->execute([$agent_id, $final_amount, $final_amount]);
        
        $pdo->commit();
        echo json_encode(["success" => true, "message" => "Commission approved and released to agent wallet"]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(["error" => $e->getMessage()]);
    }
    exit();
}

if ($data['action'] === 'reject_commission') {
    $commission_id = (int)$data['commission_id'];
    $notes = isset($data['notes']) ? trim($data['notes']) : '';
    
    try {
        // Update commission status to rejected
        $stmt = $pdo->prepare("UPDATE agent_commissions SET status = 'rejected', admin_notes = ? WHERE id = ? AND status = 'pending'");
        $stmt->execute([$notes, $commission_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Commission not found or already processed");
        }
        
        echo json_encode(["success" => true, "message" => "Commission rejected successfully"]);
    } catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage()]);
    }
    exit();
}

if ($data['action'] === 'adjust_commission') {
    $commission_id = (int)$data['commission_id'];
    $new_amount = (float)$data['new_amount'];
    $notes = isset($data['notes']) ? trim($data['notes']) : '';
    
    // Validate amount
    if ($new_amount < 0) {
        echo json_encode(["error" => "Amount cannot be negative"]);
        exit();
    }
    
    try {
        // Check if commission exists and is pending
        $stmt = $pdo->prepare("SELECT id FROM agent_commissions WHERE id = ? AND status = 'pending'");
        $stmt->execute([$commission_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Commission not found or already processed");
        }
        
        // Update adjusted amount and notes
        $stmt = $pdo->prepare("UPDATE agent_commissions SET adjusted_amount = ?, admin_notes = ? WHERE id = ?");
        $stmt->execute([$new_amount, $notes, $commission_id]);
        
        echo json_encode(["success" => true, "message" => "Commission amount adjusted successfully"]);
    } catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage()]);
    }
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

    // Log Transaction
    $desc = "Manual Adjustment by Admin: " . ucfirst($op) . "ed $amount";
    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'admin_adjustment', ?, ?)");
    $stmt->execute([$uid, $amount, $desc]);

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

if ($data['action'] === 'update_dispute_status') {
    $id = $data['id'];
    $status = $data['status']; // 'pending', 'reviewed', or 'resolved'

    if (!in_array($status, ['pending', 'reviewed', 'resolved'])) {
         echo json_encode(["error" => "Invalid status"]);
         exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE deposit_disputes SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(["success" => true]);
    } catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage()]);
    }
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

// Handle Deposit Approve/Reject
if ($data['action'] === 'update_deposit') {
    $id = $data['id'];
    $status = $data['status']; // 'success' or 'failed'

    if (!in_array($status, ['success', 'failed'])) {
        echo json_encode(["error" => "Invalid status"]);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Get deposit details
        $stmt = $pdo->prepare("SELECT id, user_id, amount, status FROM deposits WHERE id = ?");
        $stmt->execute([$id]);
        $deposit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$deposit) {
            throw new Exception("Deposit not found");
        }

        if ($deposit['status'] !== 'pending') {
            throw new Exception("Deposit already processed");
        }

        // Update deposit status
        $stmt = $pdo->prepare("UPDATE deposits SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        if ($status === 'success') {
            // Add to withdrawable balance (merged wallet)
            $stmt = $pdo->prepare("
                INSERT INTO wallets (user_id, withdrawable_balance) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE withdrawable_balance = withdrawable_balance + ?
            ");
            $stmt->execute([$deposit['user_id'], $deposit['amount'], $deposit['amount']]);

            // Mark user as deposited
            $stmt = $pdo->prepare("UPDATE users SET has_deposited = 1 WHERE id = ?");
            $stmt->execute([$deposit['user_id']]);

            // Log transaction
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'deposit', ?, 'Deposit manually approved by admin - Deposit ID: $id')");
            $stmt->execute([$deposit['user_id'], $deposit['amount']]);

            // Distribute agent commission on first deposit (from utils.php)
            require_once __DIR__ . '/../api/utils.php';
            distributeAgentFirstDepositCommission($pdo, $deposit['user_id'], $deposit['amount']);
            
            // Auto level up
            autoLevelUp($pdo, $deposit['user_id']);
        } else {
            // Log rejection transaction
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'deposit', ?, 'Deposit rejected by admin - Deposit ID: $id')");
            $stmt->execute([$deposit['user_id'], $deposit['amount']]);
        }

        $pdo->commit();
        echo json_encode(["success" => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(["error" => $e->getMessage()]);
    }
}
?>
