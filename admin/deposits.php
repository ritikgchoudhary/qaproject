<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
require 'db_connect.php';
require 'components/header.php';

// Pagination Removed (Infinite Scroll Active)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposits - Master Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-[#050505] text-white min-h-screen flex">

    <?php include 'components/sidebar.php'; ?>

    <main class="ml-64 flex-1 p-8">
        <header class="flex justify-between items-center mb-8">
             <div>
                <h1 class="text-2xl font-bold">Deposits</h1>
                <p class="text-gray-500 text-sm">Log of all system deposits.</p>
            </div>
        </header>

        <div class="bg-[#111] border border-white/5 rounded-2xl overflow-hidden shadow-xl" id="depositsContainer">
             <div class="overflow-x-auto">
                 <table class="w-full text-left border-collapse" id="depositsTable">
                    <thead>
                        <tr class="bg-white/5 border-b border-white/5 text-gray-400 text-xs uppercase tracking-wider sticky top-0 bg-[#0a0a0a] z-10">
                            <th class="p-4 font-bold">Ref ID</th>
                            <th class="p-4 font-bold">User</th>
                            <th class="p-4 font-bold">Order Number</th>
                            <th class="p-4 font-bold">Amount</th>
                            <th class="p-4 font-bold">Status</th>
                            <th class="p-4 font-bold">Actions</th>
                            <th class="p-4 font-bold text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-sm" id="depositList">
                        <!-- Content loaded via JS -->
                    </tbody>
                 </table>
             </div>
             
             <div id="loading" class="hidden p-8 text-center">
                <div class="inline-block w-6 h-6 border-2 border-yellow-500 border-t-transparent rounded-full animate-spin"></div>
             </div>
             <div id="noMore" class="hidden p-8 text-center text-gray-500 text-xs uppercase tracking-widest">End of list</div>
        </div>
    </main>

    <script>
    let page = 1;
    let loading = false;
    let finished = false;

    function loadDeposits() {
        if (loading || finished) return;
        loading = true;
        document.getElementById('loading').classList.remove('hidden');

        fetch(`api_action.php?action=get_deposits&page=${page}`)
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('depositList');
                
                if (data.deposits.length === 0) {
                    finished = true;
                    document.getElementById('noMore').classList.remove('hidden');
                } else {
                    data.deposits.forEach(d => {
                        const tr = document.createElement('tr');
                        tr.className = 'hover:bg-white/5 transition-colors';

                        const dateStr = new Date(d.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });

                        // Calculate time elapsed
                        const createdTime = new Date(d.created_at).getTime();
                        const currentTime = Date.now();
                        const minutesOld = (currentTime - createdTime) / (1000 * 60);
                        const hoursOld = minutesOld / 60;
                        const daysOld = hoursOld / 24;
                        const canApprove = d.status === 'pending' && minutesOld >= 10;
                        const canReapproveFailed = d.status === 'failed' && daysOld >= 1;

                        // Status badge color
                        let statusColor = 'text-gray-400 bg-gray-500/10 border-gray-500/20';
                        let statusText = d.status;
                        if (d.status === 'success') {
                            statusColor = 'text-green-500 bg-green-500/10 border-green-500/20';
                        } else if (d.status === 'failed') {
                            statusColor = 'text-red-500 bg-red-500/10 border-red-500/20';
                        } else if (d.status === 'pending') {
                            statusColor = 'text-yellow-500 bg-yellow-500/10 border-yellow-500/20';
                            // Show time info for pending deposits
                            if (minutesOld < 10) {
                                statusText = `Pending (${Math.ceil(10 - minutesOld)}m left)`;
                            } else if (hoursOld < 48) {
                                statusText = 'Pending';
                            }
                        }

                        // Action buttons
                        let actionButtons = '';
                        if (d.status === 'pending') {
                            if (canApprove) {
                                actionButtons = `
                                    <div class="flex gap-2">
                                        <button onclick="updateDeposit(${d.id}, 'success')" class="px-3 py-1.5 bg-green-500/20 hover:bg-green-500/30 text-green-400 text-xs font-bold rounded border border-green-500/30 transition-colors">
                                            ✓ Approve
                                        </button>
                                        <button onclick="updateDeposit(${d.id}, 'failed')" class="px-3 py-1.5 bg-red-500/20 hover:bg-red-500/30 text-red-400 text-xs font-bold rounded border border-red-500/30 transition-colors">
                                            ✗ Reject
                                        </button>
                                    </div>
                                `;
                            } else {
                                const minutesLeft = Math.ceil(10 - minutesOld);
                                actionButtons = `<span class="text-yellow-400 text-xs">Wait ${minutesLeft}m</span>`;
                            }
                        } else if (d.status === 'failed' && canReapproveFailed) {
                            // Show approve button for failed deposits older than 1 day
                            actionButtons = `
                                <div class="flex gap-2">
                                    <button onclick="updateDeposit(${d.id}, 'success')" class="px-3 py-1.5 bg-green-500/20 hover:bg-green-500/30 text-green-400 text-xs font-bold rounded border border-green-500/30 transition-colors">
                                        ✓ Approve
                                    </button>
                                </div>
                            `;
                        } else {
                            actionButtons = '<span class="text-gray-500 text-xs">-</span>';
                        }

                        tr.innerHTML = `
                            <td class="p-4 text-gray-500 font-mono">#${d.id}</td>
                            <td class="p-4">
                                <div class="font-bold text-white">${d.name}</div>
                                <div class="text-xs text-gray-500">${d.email}</div>
                            </td>
                            <td class="p-4">
                                ${d.order_id ? `<span class="font-mono text-blue-400 text-sm font-bold">${d.order_id}</span>` : '<span class="text-gray-500 text-xs">-</span>'}
                            </td>
                            <td class="p-4 text-green-400 font-bold">₹${Number(d.amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-[10px] uppercase font-bold ${statusColor} border">
                                    ${statusText}
                                </span>
                            </td>
                            <td class="p-4">${actionButtons}</td>
                            <td class="p-4 text-right text-gray-400 text-xs">${dateStr}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                    page++;
                }
            })
            .catch(err => console.error(err))
            .finally(() => {
                 loading = false;
                 document.getElementById('loading').classList.add('hidden');
            });
    }

    // Update Deposit Status
    function updateDeposit(id, status) {
        const action = status === 'success' ? 'approve' : 'reject';
        if (!confirm(`Are you sure you want to ${action} this deposit?`)) return;
        
        fetch('api_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_deposit', id: id, status: status })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Reload deposits
                page = 1;
                finished = false;
                document.getElementById('depositList').innerHTML = '';
                loadDeposits();
            } else {
                alert('Error: ' + (data.error || 'Failed to update deposit'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error updating deposit');
        });
    }

    // Initial Load
    loadDeposits();

    // Scroll Event
    window.addEventListener('scroll', () => {
        if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 200) {
            loadDeposits();
        }
    });
    </script>
</body>
</html>
