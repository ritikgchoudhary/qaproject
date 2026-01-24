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

// --- AUTO LEVEL UP LOGIC --- //

function getActiveDirects($pdo, $uid) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE referred_by = (SELECT referral_code FROM users WHERE id = ?) AND has_deposited = 1");
    $stmt->execute([$uid]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function checkTeamStructure($pdo, $uid, $target_depth, $current_depth) {
    $directs = getActiveDirects($pdo, $uid);
    if (count($directs) < 3) return false;
    if ($current_depth >= $target_depth) return true;
    foreach ($directs as $child_id) {
        if (!checkTeamStructure($pdo, $child_id, $target_depth, $current_depth + 1)) return false;
    }
    return true;
}

function autoLevelUp($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT level FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_level = (int)($stmt->fetchColumn() ?: 1);

    $new_level = $current_level;
    
    // Check for next level(s)
    // We check up to level 5 for now (or loop until fail)
    for ($check_lvl = $current_level; $check_lvl <= 5; $check_lvl++) {
        if (checkTeamStructure($pdo, $user_id, $check_lvl, 1)) {
            $new_level = $check_lvl + 1;
        } else {
            break;
        }
    }

    if ($new_level > $current_level) {
        $stmt = $pdo->prepare("UPDATE users SET level = ? WHERE id = ?");
        $stmt->execute([$new_level, $user_id]);
        return $new_level;
    }
    return $current_level;
}

function checkAndDistributeTreeBonus($pdo, $new_active_user_id) {
    // 1. Get the parent of the user who just deposited
    $stmt = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
    $stmt->execute([$new_active_user_id]);
    $parent_code = $stmt->fetchColumn();
    
    if (!$parent_code) return;

    $stmt = $pdo->prepare("SELECT id, tree_bonus_distributed FROM users WHERE referral_code = ?");
    $stmt->execute([$parent_code]);
    $parent = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$parent || $parent['tree_bonus_distributed']) return;

    // 2. Check if this parent now has 3 active directs
    $directs = getActiveDirects($pdo, $parent['id']);
    if (count($directs) >= 3) {
        // TREE IS COMPLETE!
        // 3. Mark as distributed so we don't pay again for this node
        $stmt = $pdo->prepare("UPDATE users SET tree_bonus_distributed = 1 WHERE id = ?");
        $stmt->execute([$parent['id']]);

        // 4. Go up and pay AGENTS in the chain
        $commission_amount = 30.00;
        $child_id = $parent['id'];
        
        // Go up 5 levels for tree bonus
        for ($depth = 1; $depth <= 5; $depth++) {
            $stmt = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
            $stmt->execute([$child_id]);
            $upline_code = $stmt->fetchColumn();
            if (!$upline_code) break;

            $stmt = $pdo->prepare("SELECT id, role FROM users WHERE referral_code = ?");
            $stmt->execute([$upline_code]);
            $upline = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$upline) break;

            if ($upline['role'] === 'agent') {
                // Give ₹30 Bonus
                $stmt = $pdo->prepare("INSERT INTO agent_commissions (agent_id, from_user_id, amount, level, commission_type) VALUES (?, ?, ?, ?, 'tree_bonus')");
                $stmt->execute([$upline['id'], $parent['id'], $commission_amount, $depth]);

                $stmt = $pdo->prepare("INSERT INTO wallets (user_id, withdrawable_balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE withdrawable_balance = withdrawable_balance + ?");
                $stmt->execute([$upline['id'], $commission_amount, $commission_amount]);
            }
            $child_id = $upline['id'];
        }
    }
}
?>

