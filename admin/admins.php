<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
require 'db_connect.php';
require 'components/header.php';

// Fetch Admins
$admins = $pdo->query("SELECT * FROM admins ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins - Master Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-[#050505] text-white min-h-screen flex">

    <?php include 'components/sidebar.php'; ?>

    <main class="ml-64 flex-1 p-8">
        <header class="flex justify-between items-center mb-8">
             <div>
                <h1 class="text-2xl font-bold">Admin Management</h1>
                <p class="text-gray-500 text-sm">Manage system administrators.</p>
            </div>
            
            <button onclick="document.getElementById('addAdminModal').classList.remove('hidden')" class="bg-white text-black hover:bg-gray-200 px-4 py-2 rounded-xl text-sm font-bold transition-colors shadow-lg shadow-white/10 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4" /></svg>
                Add Admin
            </button>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($admins as $a): ?>
            <div class="bg-[#111] border border-white/5 rounded-2xl p-6 relative group overflow-hidden">
                <div class="absolute top-0 right-0 w-20 h-20 bg-yellow-500/5 rounded-full blur-2xl group-hover:bg-yellow-500/10 transition-all"></div>
                
                <div class="flex items-center justify-between mb-4 relative z-10">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-gray-800 to-black border border-white/10 flex items-center justify-center font-bold text-lg text-gray-300">
                        <?= strtoupper(substr($a['username'], 0, 1)) ?>
                    </div>
                    <?php if ($a['username'] !== 'admin'): // Prevent deleting master admin ?>
                        <button onclick="deleteAdmin(<?= $a['id'] ?>)" class="text-gray-600 hover:text-red-500 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    <?php endif; ?>
                </div>
                
                <h3 class="text-lg font-bold text-white mb-1"><?= htmlspecialchars($a['username']) ?></h3>
                <p class="text-xs text-gray-500">Created: <?= date('M j, Y', strtotime($a['created_at'])) ?></p>
                
                <?php if ($a['username'] === 'admin'): ?>
                    <span class="absolute bottom-6 right-6 px-2 py-1 bg-yellow-500/10 text-yellow-500 text-[10px] font-bold rounded border border-yellow-500/20">MASTER</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Add Admin Modal -->
    <div id="addAdminModal" class="hidden fixed inset-0 z-[60] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-[#121212] border border-white/10 rounded-2xl w-full max-w-sm p-6 relative shadow-2xl">
            <h3 class="text-lg font-bold mb-4 text-white">New Administrator</h3>
            <form id="addAdminForm" class="space-y-4">
                <div>
                     <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Username</label>
                     <input type="text" id="new_username" class="w-full bg-[#1a1a1a] border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:border-yellow-500 focus:outline-none" required>
                </div>
                <div>
                     <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Password</label>
                     <input type="password" id="new_password" class="w-full bg-[#1a1a1a] border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:border-yellow-500 focus:outline-none" required>
                </div>

                <div class="flex gap-2 mt-4">
                    <button type="button" onclick="document.getElementById('addAdminModal').classList.add('hidden')" class="flex-1 bg-gray-800 hover:bg-gray-700 text-white py-2 rounded-lg text-sm font-bold">Cancel</button>
                    <button type="submit" class="flex-1 bg-white text-black hover:bg-gray-200 py-2 rounded-lg text-sm font-bold">Create</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function deleteAdmin(id) {
        if(!confirm('Delete this admin?')) return;
        fetch('api_action.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'delete_admin', id: id })
        }).then(() => location.reload());
    }

    document.getElementById('addAdminForm').addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('api_action.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'add_admin',
                username: document.getElementById('new_username').value,
                password: document.getElementById('new_password').value
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
