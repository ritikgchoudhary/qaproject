<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
require 'db_connect.php';
require 'components/header.php'; // For utils

// Handle Search
$search = $_GET['search'] ?? '';

// We don't fetch users here anymore. Done via AJAX.


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - Master Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-[#050505] text-white min-h-screen flex">

    <!-- Sidebar -->
    <?php include 'components/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="ml-64 flex-1 p-8">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold">User Management</h1>
                <p class="text-gray-500 text-sm">View, edit, and manage all registered users.</p>
            </div>
            
            <div class="flex gap-4">
                <select id="roleFilter" class="bg-[#111] border border-white/10 rounded-xl px-4 py-2 text-sm text-gray-400 focus:outline-none focus:border-yellow-500 transition-colors">
                    <option value="">All Roles</option>
                    <option value="user">User</option>
                    <option value="agent">Agent</option>
                </select>

                <div class="relative">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search users..." 
                        class="bg-[#111] border border-white/10 rounded-xl px-4 py-2 text-sm text-white w-64 focus:outline-none focus:border-yellow-500 transition-colors pl-10"
                    >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 absolute left-3 top-3 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
        </header>

        <div class="bg-[#111] border border-white/5 rounded-2xl overflow-hidden shadow-xl" id="usersContainer">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="usersTable">
                    <thead>
                        <tr class="bg-white/5 border-b border-white/5 text-gray-400 text-xs uppercase tracking-wider sticky top-0 bg-[#0a0a0a] z-10">
                            <th class="p-4 font-bold">ID</th>
                            <th class="p-4 font-bold">User</th>
                            <th class="p-4 font-bold">Role</th>
                            <th class="p-4 font-bold">Wallet</th>
                            <th class="p-4 font-bold">Bank Info</th>
                            <th class="p-4 font-bold">Password</th>
                            <th class="p-4 font-bold">Joined</th>
                            <th class="p-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-sm" id="userList">
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

    <!-- Edit User Modal (Hidden) -->
    <div id="editModal" class="hidden fixed inset-0 z-[60] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-[#121212] border border-white/10 rounded-2xl w-full max-w-md p-6 relative shadow-2xl">
            <h3 class="text-xl font-bold mb-4 text-white">Edit User</h3>
            <form id="editForm" class="space-y-4">
                <input type="hidden" id="edit_id">
                <div>
                     <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Name</label>
                     <input type="text" id="edit_name" class="w-full bg-[#1a1a1a] border border-white/10 rounded-lg px-3 py-2 text-white text-sm">
                </div>
                <div>
                     <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Email</label>
                     <input type="email" id="edit_email" class="w-full bg-[#1a1a1a] border border-white/10 rounded-lg px-3 py-2 text-white text-sm">
                </div>
                <div>
                     <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Role</label>
                     <select id="edit_role" class="w-full bg-[#1a1a1a] border border-white/10 rounded-lg px-3 py-2 text-white text-sm">
                         <option value="user">User</option>
                         <option value="agent">Agent</option>
                     </select>
                </div>
                <div class="flex gap-2 mt-4">
                    <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="flex-1 bg-gray-800 hover:bg-gray-700 text-white py-2 rounded-lg text-sm font-bold">Cancel</button>
                    <button type="submit" class="flex-1 bg-yellow-500 hover:bg-yellow-400 text-black py-2 rounded-lg text-sm font-bold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Wallet Modal (Hidden) -->
    <div id="walletModal" class="hidden fixed inset-0 z-[60] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-[#121212] border border-white/10 rounded-2xl w-full max-w-md p-6 relative shadow-2xl">
            <h3 class="text-xl font-bold mb-4 text-white">Adjust Balance</h3>
            <p class="text-sm text-gray-500 mb-6">User: <span id="wallet_user_name" class="text-white font-bold"></span></p>
            
            <form id="walletForm" class="space-y-4">
                <input type="hidden" id="wallet_id">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                         <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Operation</label>
                         <select id="wallet_operation" class="w-full bg-[#1a1a1a] border border-white/10 rounded-lg px-3 py-2 text-white text-sm">
                             <option value="add">Add (+)</option>
                             <option value="subtract">Subtract (-)</option>
                         </select>
                    </div>
                    <div>
                         <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Amount</label>
                         <input type="number" step="0.01" id="wallet_amount" class="w-full bg-[#1a1a1a] border border-white/10 rounded-lg px-3 py-2 text-white text-sm" placeholder="0.00" required>
                    </div>
                </div>

                <div class="flex gap-2 mt-4">
                    <button type="button" onclick="document.getElementById('walletModal').classList.add('hidden')" class="flex-1 bg-gray-800 hover:bg-gray-700 text-white py-2 rounded-lg text-sm font-bold">Cancel</button>
                    <button type="submit" class="flex-1 bg-green-500 hover:bg-green-400 text-black py-2 rounded-lg text-sm font-bold">Update Wallet</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function deleteUser(id) {
        if(!confirm('Delete this user? This cannot be undone.')) return;
        fetch('api_action.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'delete_user', id: id })
        }).then(() => location.reload());
    }

    function editUser(id) {
        // Fetch user details first
        fetch('api_action.php?get_user=' + id)
            .then(res => res.json())
            .then(data => {
                document.getElementById('edit_id').value = data.id;
                document.getElementById('edit_name').value = data.name;
                document.getElementById('edit_email').value = data.email;
                document.getElementById('edit_role').value = data.role;
                document.getElementById('editModal').classList.remove('hidden');
            });
    }

    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const data = {
            action: 'edit_user',
            id: document.getElementById('edit_id').value,
            name: document.getElementById('edit_name').value,
            email: document.getElementById('edit_email').value,
            role: document.getElementById('edit_role').value
        };
        
        fetch('api_action.php', {
            method: 'POST',
            body: JSON.stringify(data)
        }).then(() => location.reload());
    });

    // Wallet Logic
    function adjustWallet(id, name) {
        document.getElementById('wallet_id').value = id;
        document.getElementById('wallet_user_name').innerText = name;
        document.getElementById('walletModal').classList.remove('hidden');
    }

    document.getElementById('walletForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const data = {
            action: 'adjust_wallet',
            user_id: document.getElementById('wallet_id').value,
            amount: document.getElementById('wallet_amount').value,
            operation: document.getElementById('wallet_operation').value
        };
        
        fetch('api_action.php', {
            method: 'POST',
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                alert('Wallet updated successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        });
    });

    // Infinite Scroll Logic
    let page = 1;
    let loading = false;
    let finished = false;
    
    // Initial State from URL (if any)
    const searchParams = new URLSearchParams(window.location.search);
    let currentSearch = searchParams.get('search') || '';
    let currentRole = '';

    function loadUsers(reset = false) {
        if (reset) {
            page = 1;
            finished = false;
            document.getElementById('userList').innerHTML = '';
            document.getElementById('noMore').classList.add('hidden');
        }
        
        if (loading || finished) return;
        loading = true;
        document.getElementById('loading').classList.remove('hidden');

        fetch(`api_action.php?action=get_users&page=${page}&search=${encodeURIComponent(currentSearch)}&role=${encodeURIComponent(currentRole)}`)
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('userList');
                
                if (data.users.length === 0) {
                    finished = true;
                    document.getElementById('noMore').classList.remove('hidden');
                } else {
                    data.users.forEach(u => {
                        const tr = document.createElement('tr');
                        tr.className = 'hover:bg-white/5 transition-colors group';
                        
                        const roleClass = u.role === 'agent' 
                            ? 'border-yellow-500/30 text-yellow-500 bg-yellow-500/10' 
                            : 'border-gray-700 text-gray-500';
                            
                        const bankHtml = u.bank_account_number ? `<div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Bank Set</div>` : '';
                        const usdtHtml = u.usdt_address ? `<div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> USDT Set</div>` : '';
                        const emptyHtml = (!u.bank_account_number && !u.usdt_address) ? '-' : '';
                        const passHtml = u.plain_password || '<span class="italic text-gray-600">Hidden/Crypted</span>';
                        const dateStr = new Date(u.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

                        tr.innerHTML = `
                            <td class="p-4 text-gray-500">#${u.id}</td>
                            <td class="p-4">
                                <div class="font-bold text-white">${u.name}</div>
                                <div class="text-xs text-gray-500">${u.email}</div>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-[10px] uppercase font-bold border ${roleClass}">
                                    ${u.role.charAt(0).toUpperCase() + u.role.slice(1)}
                                </span>
                            </td>
                            <td class="p-4">
                                <span class="font-mono text-green-400">₹${Number(u.wallet_balance || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                            </td>
                            <td class="p-4 text-xs text-gray-400">
                                ${bankHtml} ${usdtHtml} ${emptyHtml}
                            </td>
                            <td class="p-4 font-mono text-gray-400">
                                ${passHtml}
                            </td>
                            <td class="p-4 text-gray-500 text-xs">${dateStr}</td>
                            <td class="p-4 text-right">
                                <div class="flex justify-end gap-2 text-right">
                                    <button onclick="adjustWallet(${u.id}, '${u.name.replace(/'/g, "\\'")}')" class="bg-green-500/10 hover:bg-green-500 hover:text-white text-green-500 p-2 rounded-lg transition-all" title="Manage Wallet">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </button>
                                    <a href="transactions.php?user_id=${u.id}" class="bg-yellow-500/10 hover:bg-yellow-500 hover:text-black text-yellow-500 p-2 rounded-lg transition-all" title="View Transactions">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                                    </a>
                                    <a href="user_tree.php?id=${u.id}" class="bg-purple-500/10 hover:bg-purple-500 hover:text-white text-purple-500 p-2 rounded-lg transition-all" title="View Network">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11.5V14m0 4v2m0-6V14h3v-2.5m3.5 2.5H17v6h-3.5m0-6v-2.5m-3.5 0h7m-3.5 0v-4" /></svg>
                                    </a>
                                    <button onclick="editUser(${u.id})" class="bg-blue-500/10 hover:bg-blue-500 hover:text-white text-blue-500 p-2 rounded-lg transition-all" title="Edit User">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    </button>
                                    <button onclick="deleteUser(${u.id})" class="bg-red-500/10 hover:bg-red-500 hover:text-white text-red-500 p-2 rounded-lg transition-all" title="Delete User">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            </td>
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
    loadUsers();

    // Scroll Event
    window.addEventListener('scroll', () => {
        if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 200) {
            loadUsers();
        }
    });

    // Filtering Logic
    let timeout;
    document.querySelector('input[name="search"]').addEventListener('input', (e) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            currentSearch = e.target.value;
            loadUsers(true);
        }, 500); // Debounce
    });

    document.getElementById('roleFilter').addEventListener('change', (e) => {
        currentRole = e.target.value;
        loadUsers(true);
    });
    </script>
</body>
</html>
