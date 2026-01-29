<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
require 'db_connect.php';
require 'components/header.php';

// Filter
$status_filter = $_GET['status'] ?? '';
$where = "";
$params = [];
if ($status_filter) {
    $where = "WHERE w.status = ?";
    $params[] = $status_filter;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$count_sql = "SELECT COUNT(*) FROM withdraws w $where";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$sql = "SELECT w.*, u.name, u.email, u.bank_account_number, u.bank_holder_name, u.bank_ifsc_code, u.usdt_address FROM withdraws w JOIN users u ON w.user_id = u.id $where ORDER BY w.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawals - Master Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-[#050505] text-white min-h-screen flex">

    <?php include 'components/sidebar.php'; ?>

    <main class="ml-64 flex-1 p-8">
        <header class="flex justify-between items-center mb-8">
             <div>
                <h1 class="text-2xl font-bold">Withdrawals</h1>
                <p class="text-gray-500 text-sm">Manage user withdrawal requests.</p>
            </div>
            
            <div class="bg-[#111] p-1 rounded-lg border border-white/10 flex">
                <a href="withdrawals.php" class="px-3 py-1.5 text-xs font-bold rounded-md transition-all <?= !$status_filter ? 'bg-white text-black' : 'text-gray-400 hover:text-white' ?>">All</a>
                <a href="withdrawals.php?status=pending" class="px-3 py-1.5 text-xs font-bold rounded-md transition-all <?= $status_filter == 'pending' ? 'bg-yellow-500 text-black' : 'text-gray-400 hover:text-white' ?>">Pending</a>
                <a href="withdrawals.php?status=approved" class="px-3 py-1.5 text-xs font-bold rounded-md transition-all <?= $status_filter == 'approved' ? 'bg-green-500 text-black' : 'text-gray-400 hover:text-white' ?>">Approved</a>
                <a href="withdrawals.php?status=rejected" class="px-3 py-1.5 text-xs font-bold rounded-md transition-all <?= $status_filter == 'rejected' ? 'bg-red-500 text-black' : 'text-gray-400 hover:text-white' ?>">Rejected</a>
            </div>
        </header>

        <div class="bg-[#111] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
             <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white/5 border-b border-white/5 text-gray-400 text-xs uppercase tracking-wider">
                        <th class="p-4 font-bold">W-ID</th>
                        <th class="p-4 font-bold">User</th>
                        <th class="p-4 font-bold">Amount</th>
                        <th class="p-4 font-bold">Method</th>
                        <th class="p-4 font-bold">Details</th>
                        <th class="p-4 font-bold">Status</th>
                        <th class="p-4 font-bold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-sm">
                    <?php foreach ($withdrawals as $w): ?>
                    <tr class="hover:bg-white/5 transition-colors">
                        <td class="p-4 text-gray-500 font-mono">#<?= $w['id'] ?></td>
                        <td class="p-4">
                            <div class="font-bold text-white"><?= htmlspecialchars($w['name']) ?></div>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($w['email']) ?></div>
                            <div class="text-xs text-gray-400 mt-1">ID: #<?= $w['user_id'] ?></div>
                        </td>
                        <td class="p-4 text-white font-bold">₹<?= number_format($w['amount'], 2) ?></td>
                        <td class="p-4 text-xs font-bold text-gray-400">
                             <?= $w['usdt_address'] ? '<span class="text-green-400">Crypto (USDT)</span>' : '<span class="text-blue-400">Bank Transfer</span>' ?>
                        </td>
                         <td class="p-4 text-[10px] text-gray-400 max-w-xs">
                             <?php if ($w['usdt_address']): ?>
                                 <div class="space-y-1">
                                     <div><span class="text-gray-500">USDT:</span> <?= htmlspecialchars($w['usdt_address']) ?></div>
                                 </div>
                             <?php else: ?>
                                 <div class="space-y-1">
                                     <?php if ($w['bank_holder_name']): ?>
                                         <div><span class="text-gray-500">Name:</span> <?= htmlspecialchars($w['bank_holder_name']) ?></div>
                                     <?php endif; ?>
                                     <?php if ($w['bank_account_number']): ?>
                                         <div><span class="text-gray-500">Account:</span> <?= htmlspecialchars($w['bank_account_number']) ?></div>
                                     <?php endif; ?>
                                     <?php if ($w['bank_ifsc_code']): ?>
                                         <div><span class="text-gray-500">IFSC:</span> <span class="text-yellow-400 font-bold"><?= htmlspecialchars($w['bank_ifsc_code']) ?></span></div>
                                     <?php endif; ?>
                                 </div>
                             <?php endif; ?>
                        </td>
                        <td class="p-4">
                            <span class="px-2 py-1 rounded text-[10px] uppercase font-bold border 
                            <?php 
                                if($w['status'] == 'pending') echo 'text-yellow-500 bg-yellow-500/10 border-yellow-500/20';
                                elseif($w['status'] == 'approved') echo 'text-green-500 bg-green-500/10 border-green-500/20';
                                else echo 'text-red-500 bg-red-500/10 border-red-500/20';
                            ?>">
                                <?= $w['status'] ?>
                            </span>
                        </td>
                        <td class="p-4 text-right">
                            <?php if ($w['status'] == 'pending'): ?>
                                <button onclick="updateWithdrawal(<?= $w['id'] ?>, 'approved')" class="bg-green-500/10 text-green-500 hover:bg-green-500 hover:text-white px-2 py-1 rounded text-[10px] font-bold border border-green-500/20 transition-all mr-1">Approve</button>
                                <button onclick="updateWithdrawal(<?= $w['id'] ?>, 'rejected')" class="bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white px-2 py-1 rounded text-[10px] font-bold border border-red-500/20 transition-all">Reject</button>
                            <?php else: ?>
                                <span class="text-gray-600 text-xs italic">Processed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
             </table>
             <?php if (count($withdrawals) == 0): ?>
                <div class="p-12 text-center text-gray-500">No withdrawals found.</div>
            <?php endif; ?>
        </div>

        <?php renderPagination($total_pages, $page, 'withdrawals.php'); ?>
    </main>
    
    <script>
    function updateWithdrawal(id, status) {
        if (!confirm('Are you sure you want to ' + status + ' this request?')) return;
        
        fetch('api_action.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'update_withdraw', id: id, status: status })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) location.reload();
            else alert('Error: ' + data.error);
        });
    }
    </script>
</body>
</html>
