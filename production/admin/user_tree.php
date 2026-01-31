<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
require 'db_connect.php';
require 'components/header.php';

$root_user_id = $_GET['id'] ?? null;
if (!$root_user_id) {
    die("User ID required");
}

// Fetch Root User
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$root_user_id]);
$root_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$root_user) {
    die("User not found");
}

// Recursive function to build tree
function getReferrals($pdo, $referral_code, $level = 1) {
    if ($level > 3) return []; // Limit to 3 levels for display sanity

    $stmt = $pdo->prepare("SELECT * FROM users WHERE referred_by = ?");
    $stmt->execute([$referral_code]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as &$user) {
        $user['children'] = getReferrals($pdo, $user['referral_code'], $level + 1);
        
        // Get Stats
        $stmt2 = $pdo->prepare("SELECT withdrawable_balance FROM wallets WHERE user_id = ?");
        $stmt2->execute([$user['id']]);
        $user['balance'] = $stmt2->fetchColumn() ?: 0;

        $stmt3 = $pdo->prepare("SELECT SUM(amount) FROM deposits WHERE user_id = ? AND status = 'success'");
        $stmt3->execute([$user['id']]);
        $user['total_deposit'] = $stmt3->fetchColumn() ?: 0;
    }
    return $users;
}

// Build Tree Data
// We need to fetch the root stats first
$stmt = $pdo->prepare("SELECT withdrawable_balance FROM wallets WHERE user_id = ?");
$stmt->execute([$root_user['id']]);
$root_user['balance'] = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT SUM(amount) FROM deposits WHERE user_id = ? AND status = 'success'");
$stmt->execute([$root_user['id']]);
$root_user['total_deposit'] = $stmt->fetchColumn() ?: 0;

$root_user['children'] = getReferrals($pdo, $root_user['referral_code'], 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Tree - Master Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .tree ul {
            padding-top: 20px; position: relative;
            transition: all 0.5s;
            display: flex; justify-content: center;
        }
        .tree li {
            float: left; text-align: center;
            list-style-type: none;
            position: relative;
            padding: 20px 5px 0 5px;
            transition: all 0.5s;
        }
        /* Connectors */
        .tree li::before, .tree li::after {
            content: '';
            position: absolute; top: 0; right: 50%;
            border-top: 1px solid rgba(255,255,255,0.2);
            width: 50%; height: 20px;
        }
        .tree li::after {
            right: auto; left: 50%;
            border-left: 1px solid rgba(255,255,255,0.2);
        }
        .tree li:only-child::after, .tree li:only-child::before {
            display: none;
        }
        .tree li:only-child { padding-top: 0; }
        .tree li:first-child::before, .tree li:last-child::after {
            border: 0 none;
        }
        .tree li:last-child::before{
            border-right: 1px solid rgba(255,255,255,0.2);
            border-radius: 0 5px 0 0;
        }
        .tree li:first-child::after{
            border-radius: 5px 0 0 0;
        }
        .tree ul ul::before{
            content: '';
            position: absolute; top: 0; left: 50%;
            border-left: 1px solid rgba(255,255,255,0.2);
            width: 0; height: 20px;
        }
        
        /* Card Styles */
        .node-card {
            background: #111;
            border: 1px solid rgba(255,255,255,0.1);
            padding: 10px;
            border-radius: 8px;
            display: inline-block;
            min-width: 120px;
            position: relative;
            z-index: 10;
        }
        .node-card:hover { 
            border-color: #fbbf24;
            transform: scale(1.05);
            transition: 0.2s;
        }
        .agent-badge {
            background: rgba(251, 191, 36, 0.1);
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.2);
        }
    </style>
</head>
<body class="bg-[#050505] text-white min-h-screen flex flex-col">

    <!-- Header -->
    <header class="h-16 border-b border-white/5 bg-[#0a0a0a] px-6 flex items-center justify-between sticky top-0 z-50">
        <div class="flex items-center gap-4">
            <a href="users.php" class="w-8 h-8 flex items-center justify-center bg-white/5 rounded-full hover:bg-white/10 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <h1 class="font-bold text-lg">Network Tree: <span class="text-yellow-500"><?= htmlspecialchars($root_user['name']) ?></span></h1>
        </div>
        <div class="text-xs text-gray-500">
            Levels shown: 3
        </div>
    </header>

    <div class="flex-1 overflow-auto p-10 flex justify-center">
        <div class="tree">
            <ul>
                <?php
                function renderTree($user) {
                    ?>
                    <li>
                        <div class="node-card">
                            <div class="flex flex-col items-center gap-1">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs <?= $user['role'] === 'agent' ? 'bg-yellow-500 text-black' : 'bg-gray-800 text-gray-400' ?>">
                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                </div>
                                
                                <p class="text-[10px] font-bold mt-1 max-w-[100px] truncate"><?= htmlspecialchars($user['name']) ?></p>
                                
                                <div class="text-[9px] text-gray-500 flex flex-col gap-0.5 mt-1">
                                    <span>Dep: <span class="text-green-400 font-bold">₹<?= number_format($user['total_deposit']) ?></span></span>
                                    <span>Bal: ₹<?= number_format($user['balance']) ?></span>
                                </div>

                                <div class="mt-1">
                                    <span class="text-[9px] px-1.5 py-0.5 rounded border <?= $user['role'] === 'agent' ? 'agent-badge' : 'border-gray-800 text-gray-600' ?>">
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($user['children'])): ?>
                            <ul>
                                <?php foreach ($user['children'] as $child): ?>
                                    <?php renderTree($child); ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                    <?php
                }

                renderTree($root_user);
                ?>
            </ul>
        </div>
    </div>

</body>
</html>
