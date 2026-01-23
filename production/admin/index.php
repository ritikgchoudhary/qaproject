<?php
session_start();
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-950 flex items-center justify-center h-screen text-white">

    <div class="w-full max-w-sm p-8 bg-gray-900 border border-gray-800 rounded-2xl shadow-2xl relative overflow-hidden">
        <!-- Decoration -->
        <div class="absolute top-0 right-0 w-32 h-32 bg-yellow-500/10 rounded-full blur-3xl -mr-16 -mt-16 pointer-events-none"></div>
        
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-black tracking-tighter text-white">Master<span class="text-yellow-500">Admin</span></h1>
            <p class="text-gray-500 text-sm mt-2">Secure Access Control</p>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-500 text-xs p-3 rounded-lg mb-4 text-center">
                <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <form action="auth.php" method="POST" class="space-y-5">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5">Username</label>
                <input type="text" name="username" required 
                    class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-yellow-500 focus:ring-1 focus:ring-yellow-500/50 transition-all placeholder-gray-700" 
                    placeholder="Enter admin username"
                >
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5">Password</label>
                <input type="password" name="password" required 
                    class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-yellow-500 focus:ring-1 focus:ring-yellow-500/50 transition-all placeholder-gray-700" 
                    placeholder="••••••••"
                >
            </div>

            <button type="submit" 
                class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-400 hover:to-yellow-500 text-black font-bold py-3.5 rounded-xl shadow-lg shadow-yellow-500/20 transition-all active:scale-95 text-sm uppercase tracking-wide"
            >
                Login to Console
            </button>
        </form>
        
        <div class="mt-8 text-center">
             <p class="text-[10px] text-gray-600">Restricted Area. Unauthorized access is monitored.</p>
        </div>
    </div>

</body>
</html>
