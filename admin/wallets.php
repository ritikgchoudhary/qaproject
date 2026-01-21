<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
require 'db_connect.php';
require 'components/header.php';

// Handle Search
$search = $_GET['search'] ?? '';
$where_clause = "";
$params = [];
if ($search) {
    $where_clause = "WHERE u.name LIKE ? OR u.email LIKE ?";
    $params = ["%$search%", "%$search%"];
}

// Fetch Wallets with User Info
$limit = 10;
$offset = ($page - 1) * $limit;

$count_sql = "SELECT COUNT(*) FROM wallets w JOIN users u ON w.user_id = u.id $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$sql = "SELECT w.*, u.name, u.email, u.role FROM wallets w JOIN users u ON w.user_id = u.id $where_clause ORDER BY w.withdrawable_balance DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Management - Master Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-[#050505] text-white min-h-screen flex">

    <?php include 'components/sidebar.php'; ?>

    <main class="ml-64 flex-1 p-8">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold">Wallet Management</h1>
                <p class="text-gray-500 text-sm">Monitor and adjust user balances.</p>
            </div>
            
            <form class="relative">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name/email..." 
                    class="bg-[#111] border border-white/10 rounded-xl px-4 py-2 text-sm text-white w-64 focus:outline-none focus:border-yellow-500 transition-colors pl-10"
                >
                <button type="submit" class="absolute left-3 top-3 text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </button>
            </form>
        </header>

        <div class="bg-[#111] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white/5 border-b border-white/5 text-gray-400 text-xs uppercase tracking-wider">
                            <th class="p-4 font-bold">User</th>
                            <th class="p-4 font-bold">Role</th>
                            <th class="p-4 font-bold">Balance</th>
                            <th class="p-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-sm">
                        <?php foreach ($wallets as $w): ?>
                        <tr class="hover:bg-white/5 transition-colors group">
                            <td class="p-4">
                                <div class="font-bold text-white"><?= htmlspecialchars($w['name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($w['email']) ?></div>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-[10px] uppercase font-bold border <?= $w['role'] === 'agent' ? 'border-yellow-500/30 text-yellow-500 bg-yellow-500/10' : 'border-gray-700 text-gray-500' ?>">
                                    <?= ucfirst($w['role']) ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <span class="font-mono text-green-400 text-base font-bold">₹<?= number_format($w['withdrawable_balance'], 2) ?></span>
                            </td>
                            <td class="p-4 text-right">
                                <button onclick="openAdjustModal(<?= $w['user_id'] ?>, '<?= htmlspecialchars($w['name']) ?>', <?= $w['withdrawable_balance'] ?>)" class="bg-yellow-500 hover:bg-yellow-400 text-black px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-lg shadow-yellow-500/10">
                                    Adjust Funds
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($wallets) == 0): ?>
                <div class="p-12 text-center text-gray-500">No wallets found.</div>
            <?php endif; ?>
        </div>

        <?php renderPagination($total_pages, $page, 'wallets.php'); ?>
    </main>

    <!-- Adjust Balance Modal -->
    <div id="adjustModal" class="hidden fixed inset-0 z-[60] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-[#121212] border border-white/10 rounded-2xl w-full max-w-sm p-6 relative shadow-2xl">
            <h3 class="text-lg font-bold mb-1 text-white">Adjust Balance</h3>
            <p class="text-xs text-gray-400 mb-4">User: <span id="modalUser" class="text-white font-bold"></span></p>
            
            <form id="adjustForm" class="space-y-4">
                <input type="hidden" id="adjust_user_id">
                
                <div class="bg-white/5 p-3 rounded-lg border border-white/5 mb-4">
                     <p class="text-[10px] text-gray-500 uppercase font-bold">Current Balance</p>
                     <p class="text-xl font-mono text-white" id="modalBalance">₹0.00</p>
                </div>

                <div>
                     <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Operation</label>
                     <div class="grid grid-cols-2 gap-2">
                         <label class="cursor-pointer">
                             <input type="radio" name="operation" value="add" class="peer sr-only" checked>
                             <div class="bg-[#1a1a1a] border border-white/10 peer-checked:bg-green-500/20 peer-checked:border-green-500 peer-checked:text-green-500 text-gray-400 py-2 rounded-lg text-center text-sm font-bold transition-all">Add (+)</div>
                         </label>
                         <label class="cursor-pointer">
                             <input type="radio" name="operation" value="subtract" class="peer sr-only">
                             <div class="bg-[#1a1a1a] border border-white/10 peer-checked:bg-red-500/20 peer-checked:border-red-500 peer-checked:text-red-500 text-gray-400 py-2 rounded-lg text-center text-sm font-bold transition-all">Deduct (-)</div>
                         </label>
                     </div>
                </div>

                <div>
                     <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Amount</label>
                     <div class="relative">
                         <span class="absolute left-3 top-2.5 text-gray-500">₹</span>
                         <input type="number" id="adjust_amount" step="0.01" min="0" class="w-full bg-[#1a1a1a] border border-white/10 rounded-lg pl-8 pr-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-500 font-mono" placeholder="0.00" required>
                     </div>
                </div>

                <div class="flex gap-2 mt-4">
                    <button type="button" onclick="document.getElementById('adjustModal').classList.add('hidden')" class="flex-1 bg-gray-800 hover:bg-gray-700 text-white py-2 rounded-lg text-sm font-bold">Cancel</button>
                    <button type="submit" class="flex-1 bg-white text-black hover:bg-gray-200 py-2 rounded-lg text-sm font-bold">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openAdjustModal(id, name, balance) {
        document.getElementById('adjust_user_id').value = id;
        document.getElementById('modalUser').textContent = name;
        document.getElementById('modalBalance').textContent = '₹' + balance.toFixed(2);
        document.getElementById('adjustModal').classList.remove('hidden');
    }

    document.getElementById('adjustForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const operation = document.querySelector('input[name="operation"]:checked').value;
        const amount = document.getElementById('adjust_amount').value;
        
        fetch('api_action.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'adjust_wallet',
                user_id: document.getElementById('adjust_user_id').value,
                operation: operation,
                amount: amount
            })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) location.reload();
            else alert(data.error || 'Failed');
        });
    });
    </script>
</body>
</html>
