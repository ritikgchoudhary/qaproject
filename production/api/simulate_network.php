<?php
// api/simulate_network.php
include 'config.php';
include 'utils.php';

header('Content-Type: application/json');

// Prevent timeouts and allow long execution
set_time_limit(300);
ignore_user_abort(true);
ini_set('memory_limit', '512M');

// --- Configuration ---
$root_ref_code = isset($_GET['ref']) ? trim($_GET['ref']) : null;
$max_depth = isset($_GET['depth']) ? intval($_GET['depth']) : 2; // Default reduced to 2 for safety
$branch_width = isset($_GET['width']) ? intval($_GET['width']) : 3; // Default 3 to ensure unlock condition (3 refs)
$deposit_amount = isset($_GET['deposit']) ? floatval($_GET['deposit']) : 100.00;
// ---------------------

if (!$root_ref_code) {
    die(json_encode(["error" => "Missing 'ref' parameter. Please provide your referral code."]));
}

// Stats collector
$stats = [
    "total_users_created" => 0,
    "total_deposits_processed" => 0,
    "levels" => []
];

// Verify Root
$stmt = $pdo->prepare("SELECT id, name, referral_code FROM users WHERE referral_code = ?");
$stmt->execute([$root_ref_code]);
$root_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$root_user) {
    die(json_encode(["error" => "Root user with code '$root_ref_code' not found."]));
}

// Pre-calculate hash to save CPU
$global_pass_hash = password_hash("123456", PASSWORD_DEFAULT);

function create_downline($pdo, $parent_ref, $parent_id, $current_depth, $max_depth, $width, $amount, &$stats, $pass_hash) {
    if ($current_depth > $max_depth) return;

    for ($i = 0; $i < $width; $i++) {
        // 1. Create User
        $random_suffix = mt_rand(1000, 99999);
        $name = "Sim User L{$current_depth}-{$i}-" . $random_suffix;
        $mobile = "6" . $current_depth . mt_rand(10000000, 99999999); // Unique-ish mobile
        $email = $mobile . "@sim.com";
        
        // Loop to ensure unique ref code
        $new_ref_code = "SIM" . strtoupper(bin2hex(random_bytes(3)));
        while(true) {
            $check = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
            $check->execute([$new_ref_code]);
            if($check->rowCount() == 0) break;
            $new_ref_code = "SIM" . strtoupper(bin2hex(random_bytes(3)));
        }

        $new_user_id = null;

        try {
            $pdo->beginTransaction();

            // Insert User
            $stmt = $pdo->prepare("INSERT INTO users (name, mobile, email, password, plain_password, referral_code, referred_by, level) VALUES (?, ?, ?, ?, '123456', ?, ?, 1)");
            $stmt->execute([$name, $mobile, $email, $pass_hash, $new_ref_code, $parent_ref]);
            $new_user_id = $pdo->lastInsertId();

            // Create Wallet
            $stmt = $pdo->prepare("INSERT INTO wallets (user_id) VALUES (?)");
            $stmt->execute([$new_user_id]);

            // 2. Process Deposit (Base Record)
            if ($amount > 0) {
                 $stmt = $pdo->prepare("INSERT INTO deposits (user_id, amount, status) VALUES (?, ?, 'success')");
                 $stmt->execute([$new_user_id, $amount]);

                 $stmt = $pdo->prepare("UPDATE wallets SET withdrawable_balance = withdrawable_balance + ? WHERE user_id = ?");
                 $stmt->execute([$amount, $new_user_id]);
            }

            // COMMIT HERE to finish this user's existence in DB
            // This prevents nested transaction errors when calling utils functions
            $pdo->commit();
            
            $stats['total_users_created']++;
            $stats['levels'][$current_depth][] = $new_ref_code;
            if ($amount > 0) $stats['total_deposits_processed']++;

            // 3. Post-Creation Logic (Commissions & Unlocks)
            // Done outside transaction to prevent nesting issues with utils.php
            if ($amount > 0) {
                 // Trigger Commission Logic (New user deposited, Parent gets comm)
                 distributeAgentCommissions($pdo, $new_user_id, $amount);
                 
                 // Trigger Unlock Logic on PARENT (Parent gets a new active referral)
                 checkAndUnlockParams($pdo, $parent_id);
            }
            
            // 4. Recurse (Create children for this new user)
            create_downline($pdo, $new_ref_code, $new_user_id, $current_depth + 1, $max_depth, $width, $amount, $stats, $pass_hash);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // Log but remove noisy output if not needed
            error_log("Sim failed for $name: " . $e->getMessage());
        }
    }
}

// Start Simulation
create_downline($pdo, $root_ref_code, $root_user['id'], 1, $max_depth, $branch_width, $deposit_amount, $stats, $global_pass_hash);

echo json_encode([
    "success" => true,
    "message" => "Network simulation complete. Optimized for speed.",
    "stats" => $stats
]);
?>
