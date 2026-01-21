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
$where_clause = "";
$params = [];
if ($search) {
    $where_clause = "WHERE name LIKE ? OR email LIKE ?";
    $params = ["%$search%", "%$search%"];
}

// Stats for this page
$total_rows = $pdo->prepare("SELECT COUNT(*) FROM users $where_clause");
$total_rows->execute($params);
$total_records = $total_rows->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch Users
$stmt = $pdo->prepare("SELECT * FROM users $where_clause ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            
            <form class="relative">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search users..." 
                    class="bg-[#111] border border-white/10 rounded-xl px-4 py-2 text-sm text-white w-64 focus:outline-none focus:border-yellow-500 transition-colors pl-10"
                >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 absolute left-3 top-3 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </form>
        </header>

        <div class="bg-[#111] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white/5 border-b border-white/5 text-gray-400 text-xs uppercase tracking-wider">
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
                    <tbody class="divide-y divide-white/5 text-sm">
                        <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-white/5 transition-colors group">
                            <td class="p-4 text-gray-500">#<?= $u['id'] ?></td>
                            <td class="p-4">
                                <div class="font-bold text-white"><?= htmlspecialchars($u['name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($u['email']) ?></div>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-[10px] uppercase font-bold border <?= $u['role'] === 'agent' ? 'border-yellow-500/30 text-yellow-500 bg-yellow-500/10' : 'border-gray-700 text-gray-500' ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <!-- Fetch wallet balance here or via join for performance. For simplicity, we assume separate query or add to initial query if performance needed. -->
                                <!-- Let's do a quick lazy load for now or skip. Better to join. -->
                                <?php
                                    // Quick Balance Check
                                    $stmt = $pdo->prepare("SELECT withdrawable_balance FROM wallets WHERE user_id = ?");
                                    $stmt->execute([$u['id']]);
                                    $bal = $stmt->fetchColumn() ?: 0;
                                ?>
                                <span class="font-mono text-green-400">₹<?= number_format($bal, 2) ?></span>
                            </td>
                            <td class="p-4 text-xs text-gray-400">
                                <?php if($u['bank_account_number']): ?>
                                    <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Bank Set</div>
                                <?php endif; ?>
                                <?php if($u['usdt_address']): ?>
                                    <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> USDT Set</div>
                                <?php endif; ?>
                                <?php if(!$u['bank_account_number'] && !$u['usdt_address']) echo "-"; ?>
                            </td>
                            <td class="p-4 font-mono text-gray-400">
                                <?= $u['plain_password'] ? htmlspecialchars($u['plain_password']) : '<span class="italic text-gray-600">Hidden/Crypted</span>' ?>
                            </td>
                            <td class="p-4 text-gray-500 text-xs"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                            <td class="p-4 text-right">
                                <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <a href="user_tree.php?id=<?= $u['id'] ?>" class="bg-purple-500/10 hover:bg-purple-500 hover:text-white text-purple-500 p-2 rounded-lg transition-all" title="View Network">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11.5V14m0 4v2m0-6V14h3v-2.5m3.5 2.5H17v6h-3.5m0-6v-2.5m-3.5 0h7m-3.5 0v-4" /></svg>
                                    </a>
                                    <button onclick="editUser(<?= $u['id'] ?>)" class="bg-blue-500/10 hover:bg-blue-500 hover:text-white text-blue-500 p-2 rounded-lg transition-all">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    </button>
                                    <button onclick="deleteUser(<?= $u['id'] ?>)" class="bg-red-500/10 hover:bg-red-500 hover:text-white text-red-500 p-2 rounded-lg transition-all">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (count($users) == 0): ?>
                <div class="p-12 text-center text-gray-500">No users found.</div>
            <?php endif; ?>
        </div>

        <?php renderPagination($total_pages, $page, 'users.php'); ?>
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
    </script>
</body>
</html>
