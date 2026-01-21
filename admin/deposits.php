<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
require 'db_connect.php';
require 'components/header.php';

// Pagination
$limit = 10;
$offset = ($page - 1) * $limit;

$count_sql = "SELECT COUNT(*) FROM deposits";
$total_records = $pdo->query($count_sql)->fetchColumn();
$total_pages = ceil($total_records / $limit);

$sql = "SELECT d.*, u.name, u.email FROM deposits d JOIN users u ON d.user_id = u.id ORDER BY d.created_at DESC LIMIT $limit OFFSET $offset";
$deposits = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

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

        <div class="bg-[#111] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
             <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white/5 border-b border-white/5 text-gray-400 text-xs uppercase tracking-wider">
                        <th class="p-4 font-bold">Ref ID</th>
                        <th class="p-4 font-bold">User</th>
                        <th class="p-4 font-bold">Amount</th>
                        <th class="p-4 font-bold">Status</th>
                        <th class="p-4 font-bold text-right">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-sm">
                    <?php foreach ($deposits as $d): ?>
                    <tr class="hover:bg-white/5 transition-colors">
                        <td class="p-4 text-gray-500 font-mono">#<?= $d['id'] ?></td>
                        <td class="p-4">
                            <div class="font-bold text-white"><?= htmlspecialchars($d['name']) ?></div>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($d['email']) ?></div>
                        </td>
                        <td class="p-4 text-green-400 font-bold">₹<?= number_format($d['amount'], 2) ?></td>
                        <td class="p-4">
                            <span class="px-2 py-1 rounded text-[10px] uppercase font-bold text-green-500 bg-green-500/10 border border-green-500/20">
                                <?= $d['status'] ?>
                            </span>
                        </td>
                        <td class="p-4 text-right text-gray-400 text-xs"><?= date('M j, Y H:i', strtotime($d['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
             </table>
             <?php if (count($deposits) == 0): ?>
                <div class="p-12 text-center text-gray-500">No deposits found.</div>
            <?php endif; ?>
        </div>

        <?php renderPagination($total_pages, $page, 'deposits.php'); ?>
    </main>
</body>
</html>
