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

$count_sql = "SELECT COUNT(*) FROM agent_commissions";
$total_records = $pdo->query($count_sql)->fetchColumn();
$total_pages = ceil($total_records / $limit);

$sql = "
    SELECT ac.*, 
           a.name as agent_name, a.email as agent_email,
           u.name as user_name, u.email as user_email
    FROM agent_commissions ac
    JOIN users a ON ac.agent_id = a.id
    JOIN users u ON ac.from_user_id = u.id
    ORDER BY ac.created_at DESC 
    LIMIT $limit OFFSET $offset
";
$commissions = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commissions - Master Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-[#050505] text-white min-h-screen flex">

    <?php include 'components/sidebar.php'; ?>

    <main class="ml-64 flex-1 p-8">
        <header class="flex justify-between items-center mb-8">
             <div>
                <h1 class="text-2xl font-bold">Commissions Log</h1>
                <p class="text-gray-500 text-sm">Track all agent commissions.</p>
            </div>
        </header>

        <div class="bg-[#111] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
             <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white/5 border-b border-white/5 text-gray-400 text-xs uppercase tracking-wider">
                        <th class="p-4 font-bold max-w-[50px]">ID</th>
                        <th class="p-4 font-bold">Agent (Receiver)</th>
                        <th class="p-4 font-bold">User (Source)</th>
                        <th class="p-4 font-bold">Amount</th>
                        <th class="p-4 font-bold">Level</th>
                        <th class="p-4 font-bold text-right">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-sm">
                    <?php foreach ($commissions as $c): ?>
                    <tr class="hover:bg-white/5 transition-colors">
                        <td class="p-4 text-gray-500">#<?= $c['id'] ?></td>
                        <td class="p-4">
                            <div class="font-bold text-yellow-500"><?= htmlspecialchars($c['agent_name']) ?></div>
                            <div class="text-[10px] text-gray-500"><?= htmlspecialchars($c['agent_email']) ?></div>
                        </td>
                        <td class="p-4">
                            <div class="font-bold text-white"><?= htmlspecialchars($c['user_name']) ?></div>
                        </td>
                        <td class="p-4 text-white font-bold">+₹<?= number_format($c['amount'], 2) ?></td>
                        <td class="p-4">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-[10px] font-bold border 
                            <?php 
                                if($c['level'] == 1) echo 'bg-yellow-500 text-black border-yellow-600';
                                elseif($c['level'] == 2) echo 'bg-blue-500 text-white border-blue-600';
                                else echo 'bg-purple-500 text-white border-purple-600';
                            ?>">
                                <?= $c['level'] ?>
                            </span>
                        </td>
                        <td class="p-4 text-right text-gray-400 text-xs"><?= date('M j, Y H:i', strtotime($c['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
             </table>
             <?php if (count($commissions) == 0): ?>
                <div class="p-12 text-center text-gray-500">No commissions found.</div>
            <?php endif; ?>
        </div>

        <?php renderPagination($total_pages, $page, 'commissions.php'); ?>
    </main>
</body>
</html>
