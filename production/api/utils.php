<?php
function checkAndUnlockParams($pdo, $user_id) {
    // 1. Check Referrals
    // We need to find the user's referral code first to find their referrals
    $stmt = $pdo->prepare("SELECT referral_code FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $ref_code = $stmt->fetchColumn();
    
    if (!$ref_code) return false;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
    $stmt->execute([$ref_code]);
    $referral_count = $stmt->fetchColumn();

    // 2. Check Deposits
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM deposits WHERE user_id = ? AND status = 'success'");
    $stmt->execute([$user_id]);
    $total_deposit = $stmt->fetchColumn();
    if (!$total_deposit) $total_deposit = 0;

    // 3. Unlock if conditions met
    if ($referral_count >= 3 && $total_deposit >= 100) {
        // Move locked to withdrawable
        $stmt = $pdo->prepare("SELECT locked_balance FROM wallets WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $locked = $stmt->fetchColumn();

        if ($locked > 0) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE wallets SET withdrawable_balance = withdrawable_balance + ?, locked_balance = 0 WHERE user_id = ?");
                $stmt->execute([$locked, $user_id]);
                $pdo->commit();
                return true;
            } catch (Exception $e) {
                $pdo->rollBack();
                return false;
            }
        }
    }
    return false;
}



function distributeAgentCommissions($pdo, $depositor_id, $amount) {
    // 10% Agent Commission Logic
    $commission_rate = 0.10;
    $commission = $amount * $commission_rate;
    
    if ($commission <= 0) return;

    $child_id = $depositor_id;
    
    for ($level = 1; $level <= 3; $level++) {
        // Find parent's referral code from the current child
        $stmt = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
        $stmt->execute([$child_id]);
        $parent_code = $stmt->fetchColumn();
        
        if (!$parent_code) break; // No parent
        
        // Find parent user details
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE referral_code = ?");
        $stmt->execute([$parent_code]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$parent) break; // Parent not found
        
        // Check if parent is an agent
        if (isset($parent['role']) && $parent['role'] === 'agent') {
            // Give commission
            $stmt = $pdo->prepare("INSERT INTO agent_commissions (agent_id, from_user_id, amount, level) VALUES (?, ?, ?, ?)");
            $stmt->execute([$parent['id'], $depositor_id, $commission, $level]);
            
            // Update wallet - Directly to withdrawable balance
            // Ensure wallet exists first (it should, but safety first)
            $stmt = $pdo->prepare("INSERT INTO wallets (user_id, withdrawable_balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE withdrawable_balance = withdrawable_balance + ?");
            $stmt->execute([$parent['id'], $commission, $commission]);
        }
        
        // Move up the chain
        $child_id = $parent['id'];
    }
}
?>

