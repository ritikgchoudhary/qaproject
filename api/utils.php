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
    // DEPOSIT COMMISSIONS DISABLED - Only tree bonus is active
    // This function is kept for compatibility but does not distribute any commissions
    return;
    
    // OLD LOGIC (DISABLED):
    // 10% Agent Commission Logic - This has been disabled
    // Only tree bonus (₹30) is active now
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
    // Get current level
    $stmt = $pdo->prepare("SELECT level FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_level = (int)($stmt->fetchColumn() ?: 1);

    $new_level = $current_level;
    
    // Check for next level(s) based on referrals only
    // Level progression is based on referrals, quiz access is controlled separately
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

/**
 * NEW AGENT EARNING SYSTEM:
 * Agent ko unke direct user ko chodh kar, uske niche ke sabhi users ke FIRST deposit par 20% commission milta hai
 */
function distributeAgentFirstDepositCommission($pdo, $depositor_id, $deposit_amount) {
    // 1. Check if this is the user's FIRST deposit
    // Count all successful deposits for this user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM deposits WHERE user_id = ? AND status = 'success'");
    $stmt->execute([$depositor_id]);
    $total_deposit_count = $stmt->fetchColumn();
    
    // Only process if this is the first deposit (exactly 1 successful deposit)
    if ($total_deposit_count != 1) {
        return; // Not first deposit, skip
    }
    
    // 2. Get the depositing user's referral chain to find agents
    $current_user_id = $depositor_id;
    $agents_paid = []; // Track agents already paid to avoid duplicates
    
    // Go up the referral chain to find all agents
    for ($depth = 1; $depth <= 10; $depth++) { // Max 10 levels up
        // Get current user's referrer
        $stmt = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $referrer_code = $stmt->fetchColumn();
        
        if (!$referrer_code) break; // No more upline
        
        // Get referrer's details
        $stmt = $pdo->prepare("SELECT id, role, referral_code FROM users WHERE referral_code = ?");
        $stmt->execute([$referrer_code]);
        $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$referrer) break; // Referrer not found
        
        // 3. Check if referrer is an agent
        if ($referrer['role'] === 'agent') {
            $agent_id = $referrer['id'];
            
            // Skip if we already paid this agent
            if (in_array($agent_id, $agents_paid)) {
                $current_user_id = $referrer['id'];
                continue;
            }
            
            // 4. Find agent's direct users (users who directly referred by this agent)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE referred_by = ?");
            $stmt->execute([$referrer['referral_code']]);
            $direct_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // 5. Check if depositing user is a direct user or not
            $is_direct_user = in_array($depositor_id, $direct_users);
            
            if ($is_direct_user) {
                // Direct user - use agent's direct_commission_percentage (default 50%)
                $stmt = $pdo->prepare("SELECT COALESCE(direct_commission_percentage, 50.00) FROM users WHERE id = ?");
                $stmt->execute([$agent_id]);
                $direct_percentage = $stmt->fetchColumn();
                
                $commission_amount = $deposit_amount * ($direct_percentage / 100);
                $commission_type = 'direct_first_deposit';
                
                // 6. Record commission as PENDING (NOT added to wallet - admin will release)
                $stmt = $pdo->prepare("INSERT INTO agent_commissions (agent_id, from_user_id, amount, level, commission_type, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$agent_id, $depositor_id, $commission_amount, $depth, $commission_type]);
                
                // DO NOT add to wallet - admin will release it manually
            } else {
                // Not direct user - use 20% commission (auto-approved, goes to wallet)
                $commission_amount = $deposit_amount * 0.20;
                $commission_type = 'first_deposit';
                
                // 6. Record commission as APPROVED (auto-add to wallet)
                $stmt = $pdo->prepare("INSERT INTO agent_commissions (agent_id, from_user_id, amount, level, commission_type, status) VALUES (?, ?, ?, ?, ?, 'approved')");
                $stmt->execute([$agent_id, $depositor_id, $commission_amount, $depth, $commission_type]);
                
                // 7. Add commission to agent's withdrawable balance (only for non-direct)
                $stmt = $pdo->prepare("INSERT INTO wallets (user_id, withdrawable_balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE withdrawable_balance = withdrawable_balance + ?");
                $stmt->execute([$agent_id, $commission_amount, $commission_amount]);
            }
            
            // Mark agent as paid
            $agents_paid[] = $agent_id;
        }
        
        // Move up the chain
        $current_user_id = $referrer['id'];
    }
}

// OLD TREE BONUS FUNCTION - DISABLED
function checkAndDistributeTreeBonus($pdo, $new_active_user_id) {
    // TREE BONUS SYSTEM HAS BEEN REMOVED
    // Replaced by distributeAgentFirstDepositCommission
    return;
}
?>

