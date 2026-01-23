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
                            <th class="p-4 font-bold">Amount</th>
                            <th class="p-4 font-bold">Status</th>
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

                        tr.innerHTML = `
                            <td class="p-4 text-gray-500 font-mono">#${d.id}</td>
                            <td class="p-4">
                                <div class="font-bold text-white">${d.name}</div>
                                <div class="text-xs text-gray-500">${d.email}</div>
                            </td>
                            <td class="p-4 text-green-400 font-bold">₹${Number(d.amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-[10px] uppercase font-bold text-green-500 bg-green-500/10 border border-green-500/20">
                                    ${d.status}
                                </span>
                            </td>
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
