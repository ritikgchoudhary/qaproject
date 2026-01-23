<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
require 'db_connect.php';

// Stats
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_deposits = $pdo->query("SELECT SUM(amount) FROM deposits WHERE status IN ('success', 'approved', 'completed')")->fetchColumn() ?: 0;
$total_withdrawals = $pdo->query("SELECT SUM(amount) FROM withdraws WHERE status = 'approved'")->fetchColumn() ?: 0;
$pending_withdrawals = $pdo->query("SELECT COUNT(*) FROM withdraws WHERE status = 'pending'")->fetchColumn();
$total_commissions = $pdo->query("SELECT SUM(amount) FROM agent_commissions")->fetchColumn() ?: 0;

$wallets = $pdo->query("SELECT SUM(withdrawable_balance) as withdrawable, SUM(locked_balance) as locked FROM wallets")->fetch();
$total_withdrawable = $wallets['withdrawable'] ?: 0;
$total_locked = $wallets['locked'] ?: 0;
$system_liability = $total_withdrawable + $total_locked;

// Additional Dynamic Stats
$total_questions = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
$total_answers = $pdo->query("SELECT COUNT(*) FROM answers")->fetchColumn();
$correct_answers = $pdo->query("SELECT COUNT(*) FROM answers WHERE is_correct = 1")->fetchColumn();
$failed_answers = $total_answers - $correct_answers;
$success_rate = $total_answers > 0 ? round(($correct_answers / $total_answers) * 100, 1) : 0;

// Referral Stats
$total_referrals = $pdo->query("SELECT COUNT(*) FROM users WHERE referred_by IS NOT NULL")->fetchColumn();

// Recent Pending Withdrawals
$stmt = $pdo->prepare("
    SELECT w.*, u.name, u.email, u.bank_account_number, u.usdt_address 
    FROM withdraws w 
    JOIN users u ON w.user_id = u.id 
    WHERE w.status = 'pending' 
    ORDER BY w.created_at ASC 
    LIMIT 10
");
$stmt->execute();
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent Users
$latest_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-[#050505] text-white min-h-screen flex">

    <?php include 'components/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="ml-64 flex-1 p-8">
        
        <header class="flex justify-between items-center mb-8">
             <div>
                <h1 class="text-2xl font-bold">Dashboard</h1>
                <p class="text-gray-500 text-sm">System Overview</p>
            </div>
            
            <div class="flex items-center gap-3">
                 <span class="text-sm text-gray-400">Welcome, <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
            </div>
        </header>
        
        <!-- Stats Grid: Financial -->
        <h2 class="text-xl font-bold mb-6 text-gray-400">Financial Overview</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-12">
            <!-- Card 2: Deposits -->
            <div class="bg-[#111] border border-white/5 p-6 rounded-2xl relative overflow-hidden group hover:border-white/10 transition-all">
                 <div class="absolute top-0 right-0 w-24 h-24 bg-green-500/10 rounded-full blur-2xl -mr-4 -mt-4 group-hover:bg-green-500/20 transition-all"></div>
                <p class="text-gray-500 text-xs font-bold uppercase tracking-widest mb-1">Total Deposits</p>
                <p class="text-3xl font-black text-green-400">₹<?= number_format($total_deposits, 2) ?></p>
            </div>

            <!-- Card 3: Withdrawals -->
            <div class="bg-[#111] border border-white/5 p-6 rounded-2xl relative overflow-hidden group hover:border-white/10 transition-all">
                 <div class="absolute top-0 right-0 w-24 h-24 bg-red-500/10 rounded-full blur-2xl -mr-4 -mt-4 group-hover:bg-red-500/20 transition-all"></div>
                <p class="text-gray-500 text-xs font-bold uppercase tracking-widest mb-1">Total Withdrawals</p>
                <div class="flex items-end justify-between">
                    <p class="text-3xl font-black text-red-400">₹<?= number_format($total_withdrawals, 2) ?></p>
                    <?php if($pending_withdrawals > 0): ?>
                        <a href="withdrawals.php?status=pending" class="px-2 py-1 bg-red-500 text-white text-[10px] font-bold rounded animate-pulse">
                            <?= $pending_withdrawals ?> Pending
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
             <!-- Card: Withdrawable -->
            <div class="bg-[#111] border border-white/5 p-6 rounded-2xl relative overflow-hidden group hover:border-white/10 transition-all">
                 <div class="absolute top-0 right-0 w-24 h-24 bg-blue-500/10 rounded-full blur-2xl -mr-4 -mt-4 group-hover:bg-blue-500/20 transition-all"></div>
                <p class="text-gray-500 text-xs font-bold uppercase tracking-widest mb-1">Withdrawable Funds</p>
                <p class="text-3xl font-black text-blue-400">₹<?= number_format($total_withdrawable, 2) ?></p>
            </div>

             <!-- Card 4: Locked -->
            <div class="bg-[#111] border border-white/5 p-6 rounded-2xl relative overflow-hidden group hover:border-white/10 transition-all">
                 <div class="absolute top-0 right-0 w-24 h-24 bg-yellow-500/10 rounded-full blur-2xl -mr-4 -mt-4 group-hover:bg-yellow-500/20 transition-all"></div>
                <p class="text-gray-500 text-xs font-bold uppercase tracking-widest mb-1">Locked Stakes</p>
                <p class="text-3xl font-black text-yellow-400">₹<?= number_format($total_locked, 2) ?></p>
            </div>
        </div>

        <!-- Stats Grid: Platform -->
        <h2 class="text-xl font-bold mb-6 text-gray-400">Platform Activity</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-12">
            <!-- Card: Users -->
            <div class="bg-[#111] border border-white/5 p-6 rounded-2xl relative overflow-hidden group hover:border-white/10 transition-all">
                <p class="text-gray-500 text-xs font-bold uppercase tracking-widest mb-1">Total Users</p>
                <p class="text-3xl font-black text-white"><?= number_format($total_users) ?></p>
            </div>
            
            <!-- Card: Questions -->
            <div class="bg-[#111] border border-white/5 p-6 rounded-2xl relative overflow-hidden group hover:border-white/10 transition-all">
                <p class="text-gray-500 text-xs font-bold uppercase tracking-widest mb-1">Questions</p>
                <p class="text-3xl font-black text-white"><?= number_format($total_questions) ?></p>
            </div>

            <!-- Card: Success Rate -->
            <div class="bg-[#111] border border-white/5 p-6 rounded-2xl relative overflow-hidden group hover:border-white/10 transition-all">
                <p class="text-gray-500 text-xs font-bold uppercase tracking-widest mb-1">Quiz Success Rate</p>
                <div class="flex items-end gap-2">
                    <p class="text-3xl font-black text-purple-400"><?= $success_rate ?>%</p>
                    <span class="text-[10px] text-gray-500 mb-1.5"><?= $total_answers ?> played</span>
                </div>
            </div>

            <!-- Card: Referrals -->
            <div class="bg-[#111] border border-white/5 p-6 rounded-2xl relative overflow-hidden group hover:border-white/10 transition-all">
                <p class="text-gray-500 text-xs font-bold uppercase tracking-widest mb-1">Total Referrals</p>
                <p class="text-3xl font-black text-orange-400"><?= number_format($total_referrals) ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Pending Withdrawals List -->
            <div class="bg-[#111] border border-white/5 rounded-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-white/5 flex justify-between items-center bg-[#151515]">
                    <h3 class="font-bold text-white">Pending Withdrawals</h3>
                    <a href="withdrawals.php?status=pending" class="text-xs text-yellow-500 font-bold hover:underline">View All</a>
                </div>
                <div class="divide-y divide-white/5">
                    <?php if (count($withdrawals) > 0): ?>
                        <?php foreach($withdrawals as $w): ?>
                        <div class="p-4 flex items-center justify-between hover:bg-white/5 transition-colors">
                            <div>
                                <p class="font-bold text-sm text-white"><?= htmlspecialchars($w['name']) ?></p>
                                <p class="text-[10px] text-gray-500"><?= htmlspecialchars($w['email']) ?></p>
                                <p class="text-[10px] text-gray-400 mt-1">
                                    Method: <?= $w['usdt_address'] ? '<span class="text-green-400">USDT</span>' : '<span class="text-blue-400">Bank</span>' ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-white text-lg">₹<?= number_format($w['amount'], 2) ?></p>
                                <div class="flex gap-2 mt-2">
                                    <button onclick="updateWithdrawal(<?= $w['id'] ?>, 'approved')" class="bg-green-500/10 text-green-500 hover:bg-green-500 hover:text-white px-2 py-1 rounded text-[10px] font-bold border border-green-500/20 transition-all">Approve</button>
                                    <button onclick="updateWithdrawal(<?= $w['id'] ?>, 'rejected')" class="bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white px-2 py-1 rounded text-[10px] font-bold border border-red-500/20 transition-all">Reject</button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500 text-sm">No pending withdrawals.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Users List -->
            <div class="bg-[#111] border border-white/5 rounded-2xl overflow-hidden h-fit">
                <div class="px-6 py-4 border-b border-white/5 bg-[#151515]">
                    <h3 class="font-bold text-white">New Users</h3>
                </div>
                <div class="divide-y divide-white/5">
                     <?php foreach($latest_users as $u): ?>
                        <div class="p-4 flex items-center justify-between hover:bg-white/5 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-gray-800 flex items-center justify-center font-bold text-xs">
                                    <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="font-bold text-sm text-white"><?= htmlspecialchars($u['name']) ?></p>
                                    <p class="text-[10px] text-gray-500"><?= htmlspecialchars($u['email']) ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] px-2 py-1 rounded border <?= $u['role'] === 'agent' ? 'border-yellow-500/30 text-yellow-500 bg-yellow-500/10' : 'border-gray-700 text-gray-500' ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </div>
                        </div>
                     <?php endforeach; ?>
                </div>
            </div>

        </div>
    </main>

    <!-- Actions Script -->
    <script>
    function updateWithdrawal(id, status) {
        if (!confirm('Are you sure you want to ' + status + ' this request?')) return;
        
        fetch('api_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_withdraw', id: id, status: status })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(err => alert('Network error'));
    }
    </script>
</body>
</html>
