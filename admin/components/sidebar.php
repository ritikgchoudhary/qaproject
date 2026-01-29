<?php
// sidebar.php
require_once __DIR__ . '/../../api/config.php';
$stmt_side = $pdo->query("SELECT setting_key, setting_value FROM global_settings");
$sidebar_settings = $stmt_side->fetchAll(PDO::FETCH_KEY_PAIR);
$logo = $sidebar_settings['site_logo'] ?? '';
$name = $sidebar_settings['site_name'] ?? 'MasterAdmin';
?>
<aside class="w-64 bg-[#0a0a0a] border-r border-white/5 h-screen fixed left-0 top-0 flex flex-col z-50">
    <div class="h-16 flex items-center px-6 border-b border-white/5">
        <?php if($logo): ?>
            <img src="<?= htmlspecialchars($logo) ?>" class="w-8 h-8 object-contain mr-3">
        <?php else: ?>
            <div class="w-8 h-8 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-lg flex items-center justify-center font-black text-black mr-3 shadow-lg shadow-yellow-500/20"><?= substr($name, 0, 1) ?></div>
        <?php endif; ?>
        <span class="font-bold text-lg tracking-tight text-white"><?= htmlspecialchars($name) ?></span>
    </div>

    <nav class="flex-1 overflow-y-auto py-6 px-3 space-y-1">
        <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-yellow-500/10 text-yellow-500' : 'text-gray-400 hover:text-white hover:bg-white/5' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
            Dashboard
        </a>

        <div class="pt-4 pb-2 px-3 text-[10px] font-bold text-gray-600 uppercase tracking-wider">Management</div>
        
        <a href="users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'bg-yellow-500/10 text-yellow-500' : 'text-gray-400 hover:text-white hover:bg-white/5' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
            Users
        </a>



        <a href="deposits.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= basename($_SERVER['PHP_SELF']) == 'deposits.php' ? 'bg-yellow-500/10 text-yellow-500' : 'text-gray-400 hover:text-white hover:bg-white/5' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
            Deposits
        </a>

        <a href="withdrawals.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= basename($_SERVER['PHP_SELF']) == 'withdrawals.php' ? 'bg-yellow-500/10 text-yellow-500' : 'text-gray-400 hover:text-white hover:bg-white/5' ?>">
             <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
            Withdrawals
        </a>

        <a href="payment_disputes.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= basename($_SERVER['PHP_SELF']) == 'payment_disputes.php' ? 'bg-yellow-500/10 text-yellow-500' : 'text-gray-400 hover:text-white hover:bg-white/5' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            Payment Disputes
        </a>

        <a href="questions.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= basename($_SERVER['PHP_SELF']) == 'questions.php' ? 'bg-yellow-500/10 text-yellow-500' : 'text-gray-400 hover:text-white hover:bg-white/5' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            Questions
        </a>

        <a href="settings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'bg-yellow-500/10 text-yellow-500' : 'text-gray-400 hover:text-white hover:bg-white/5' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            Settings
        </a>

        <div class="pt-4 pb-2 px-3 text-[10px] font-bold text-gray-600 uppercase tracking-wider">System</div>

        <a href="video_settings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= basename($_SERVER['PHP_SELF']) == 'video_settings.php' ? 'bg-yellow-500/10 text-yellow-500' : 'text-gray-400 hover:text-white hover:bg-white/5' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
            Tutorial Video
        </a>

        <a href="commissions.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= basename($_SERVER['PHP_SELF']) == 'commissions.php' ? 'bg-yellow-500/10 text-yellow-500' : 'text-gray-400 hover:text-white hover:bg-white/5' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
            Commissions
        </a>

        <a href="pending_commissions.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= basename($_SERVER['PHP_SELF']) == 'pending_commissions.php' ? 'bg-yellow-500/10 text-yellow-500' : 'text-gray-400 hover:text-white hover:bg-white/5' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            Pending Commissions
        </a>

        <a href="admins.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= basename($_SERVER['PHP_SELF']) == 'admins.php' ? 'bg-yellow-500/10 text-yellow-500' : 'text-gray-400 hover:text-white hover:bg-white/5' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
            Admins
        </a>
    </nav>
    
    <div class="p-4 border-t border-white/5">
        <a href="logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-red-400 hover:text-red-300 hover:bg-red-500/10 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
            Logout
        </a>
    </div>
</aside>
