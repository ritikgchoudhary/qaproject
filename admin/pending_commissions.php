<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
require 'db_connect.php';
require 'components/header.php';

// Get pending direct commissions
$sql = "
    SELECT ac.*, 
           a.name as agent_name, a.email as agent_email,
           u.name as user_name, u.email as user_email, u.mobile as user_mobile
    FROM agent_commissions ac
    JOIN users a ON ac.agent_id = a.id
    JOIN users u ON ac.from_user_id = u.id
    WHERE ac.status = 'pending' AND ac.commission_type = 'direct_first_deposit'
    ORDER BY ac.created_at DESC
";
$pending_commissions = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$total_pending = $pdo->query("SELECT COUNT(*) FROM agent_commissions WHERE status = 'pending' AND commission_type = 'direct_first_deposit'")->fetchColumn();
$total_pending_amount = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM agent_commissions WHERE status = 'pending' AND commission_type = 'direct_first_deposit'")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Direct Commissions - Master Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-[#050505] text-white min-h-screen flex">

    <?php include 'components/sidebar.php'; ?>

    <main class="ml-64 flex-1 p-8">
        <header class="flex justify-between items-center mb-8">
             <div>
                <h1 class="text-2xl font-bold">Pending Direct Commissions</h1>
                <p class="text-gray-500 text-sm">Manage and release direct user commissions to agents.</p>
            </div>
        </header>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="bg-[#111] border border-white/5 rounded-xl p-4">
                <div class="text-xs text-gray-500 uppercase mb-1">Total Pending</div>
                <div class="text-2xl font-bold text-yellow-400"><?= $total_pending ?></div>
            </div>
            <div class="bg-[#111] border border-white/5 rounded-xl p-4">
                <div class="text-xs text-gray-500 uppercase mb-1">Total Amount</div>
                <div class="text-2xl font-bold text-green-400">₹<?= number_format($total_pending_amount, 2) ?></div>
            </div>
        </div>

        <div class="bg-[#111] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
             <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white/5 border-b border-white/5 text-gray-400 text-xs uppercase tracking-wider">
                        <th class="p-4 font-bold">ID</th>
                        <th class="p-4 font-bold">Agent</th>
                        <th class="p-4 font-bold">User (Source)</th>
                        <th class="p-4 font-bold">Original Amount</th>
                        <th class="p-4 font-bold">Adjusted Amount</th>
                        <th class="p-4 font-bold">Created</th>
                        <th class="p-4 font-bold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-sm" id="commissionsTable">
                    <?php foreach ($pending_commissions as $c): ?>
                    <tr class="hover:bg-white/5 transition-colors" id="row_<?= $c['id'] ?>">
                        <td class="p-4 text-gray-500">#<?= $c['id'] ?></td>
                        <td class="p-4">
                            <div class="font-bold text-yellow-500"><?= htmlspecialchars($c['agent_name']) ?></div>
                            <div class="text-[10px] text-gray-500"><?= htmlspecialchars($c['agent_email']) ?></div>
                        </td>
                        <td class="p-4">
                            <div class="font-bold text-white"><?= htmlspecialchars($c['user_name']) ?></div>
                            <div class="text-[10px] text-gray-500"><?= htmlspecialchars($c['user_mobile'] ?? $c['user_email']) ?></div>
                        </td>
                        <td class="p-4 text-white font-bold">₹<?= number_format($c['amount'], 2) ?></td>
                        <td class="p-4">
                            <span class="font-bold <?= $c['adjusted_amount'] ? 'text-green-400' : 'text-gray-400' ?>">
                                ₹<?= number_format($c['adjusted_amount'] ?? $c['amount'], 2) ?>
                            </span>
                        </td>
                        <td class="p-4 text-gray-400 text-xs"><?= date('M j, Y H:i', strtotime($c['created_at'])) ?></td>
                        <td class="p-4 text-right">
                            <div class="flex justify-end gap-2">
                                <button onclick="openAdjustModal(<?= $c['id'] ?>, <?= $c['amount'] ?>, <?= $c['adjusted_amount'] ?? $c['amount'] ?>, '<?= htmlspecialchars($c['agent_name']) ?>', '<?= htmlspecialchars($c['user_name']) ?>')" 
                                    class="bg-blue-500/10 hover:bg-blue-500 hover:text-white text-blue-500 px-3 py-1.5 rounded-lg text-xs font-bold transition-all">
                                    Adjust
                                </button>
                                <button onclick="approveCommission(<?= $c['id'] ?>)" 
                                    class="bg-green-500/10 hover:bg-green-500 hover:text-white text-green-500 px-3 py-1.5 rounded-lg text-xs font-bold transition-all">
                                    Release
                                </button>
                                <button onclick="rejectCommission(<?= $c['id'] ?>)" 
                                    class="bg-red-500/10 hover:bg-red-500 hover:text-white text-red-500 px-3 py-1.5 rounded-lg text-xs font-bold transition-all">
                                    Reject
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
             </table>
             <?php if (count($pending_commissions) == 0): ?>
                <div class="p-12 text-center text-gray-500">No pending commissions found.</div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Adjust Amount Modal -->
    <div id="adjustModal" class="hidden fixed inset-0 z-[60] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-[#121212] border border-white/10 rounded-2xl w-full max-w-md p-6 relative shadow-2xl">
            <h3 class="text-xl font-bold mb-4 text-white">Adjust Commission Amount</h3>
            <div class="mb-4 text-sm text-gray-400">
                <div>Agent: <span id="adjust_agent_name" class="text-white font-bold"></span></div>
                <div>User: <span id="adjust_user_name" class="text-white font-bold"></span></div>
                <div class="mt-2">Original: <span id="adjust_original" class="text-yellow-400 font-bold"></span></div>
            </div>
            
            <form id="adjustForm" class="space-y-4">
                <input type="hidden" id="adjust_commission_id">
                <input type="hidden" id="adjust_original_amount">
                
                <div>
                     <label class="block text-xs font-bold text-gray-500 uppercase mb-1">New Amount (₹)</label>
                     <input type="number" step="0.01" min="0" id="adjust_new_amount" class="w-full bg-[#1a1a1a] border border-white/10 rounded-lg px-3 py-2 text-white text-sm" required>
                     <p class="text-xs text-gray-500 mt-1">Enter the adjusted commission amount</p>
                </div>

                <div>
                     <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Admin Notes (Optional)</label>
                     <textarea id="adjust_notes" rows="3" class="w-full bg-[#1a1a1a] border border-white/10 rounded-lg px-3 py-2 text-white text-sm" placeholder="Reason for adjustment..."></textarea>
                </div>

                <div class="flex gap-2 mt-4">
                    <button type="button" onclick="document.getElementById('adjustModal').classList.add('hidden')" class="flex-1 bg-gray-800 hover:bg-gray-700 text-white py-2 rounded-lg text-sm font-bold">Cancel</button>
                    <button type="submit" class="flex-1 bg-blue-500 hover:bg-blue-400 text-white py-2 rounded-lg text-sm font-bold">Save Adjustment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openAdjustModal(commissionId, originalAmount, currentAmount, agentName, userName) {
        document.getElementById('adjust_commission_id').value = commissionId;
        document.getElementById('adjust_original_amount').value = originalAmount;
        document.getElementById('adjust_new_amount').value = currentAmount;
        document.getElementById('adjust_agent_name').textContent = agentName;
        document.getElementById('adjust_user_name').textContent = userName;
        document.getElementById('adjust_original').textContent = '₹' + parseFloat(originalAmount).toFixed(2);
        document.getElementById('adjustModal').classList.remove('hidden');
    }

    document.getElementById('adjustForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const data = {
            action: 'adjust_commission',
            commission_id: document.getElementById('adjust_commission_id').value,
            new_amount: parseFloat(document.getElementById('adjust_new_amount').value),
            notes: document.getElementById('adjust_notes').value
        };
        
        fetch('api_action.php', {
            method: 'POST',
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                alert('Commission amount adjusted successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to adjust commission'));
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        });
    });

    function approveCommission(commissionId) {
        if(!confirm('Release this commission to agent\'s wallet?')) return;
        
        fetch('api_action.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'approve_commission',
                commission_id: commissionId
            })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                alert('Commission released successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to approve commission'));
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        });
    }

    function rejectCommission(commissionId) {
        if(!confirm('Reject this commission? This action cannot be undone.')) return;
        
        const notes = prompt('Enter rejection reason (optional):');
        
        fetch('api_action.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'reject_commission',
                commission_id: commissionId,
                notes: notes || ''
            })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                alert('Commission rejected successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to reject commission'));
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        });
    }
    </script>
</body>
</html>
