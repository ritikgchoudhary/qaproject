<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
require 'db_connect.php';
require 'components/header.php'; // For utils

$user_id = $_GET['user_id'] ?? null;
$user = null;
if ($user_id) {
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

// Fetch Transactions
$sql = "SELECT t.*, u.name as user_name 
        FROM transactions t 
        JOIN users u ON t.user_id = u.id 
        WHERE 1=1";
$params = [];

if ($user_id) {
    $sql .= " AND t.user_id = ?";
    $params[] = $user_id;
}

$sql .= " ORDER BY t.created_at DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - Master Admin</title>
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
                <a href="users.php" class="text-yellow-500 font-bold text-xs uppercase mb-2 block hover:underline">&larr; Back to Users</a>
                <h1 class="text-2xl font-bold">Transaction History</h1>
                <?php if ($user): ?>
                    <p class="text-gray-500 text-sm">Showing transactions for <span class="text-white font-bold"><?= htmlspecialchars($user['name']) ?></span></p>
                <?php else: ?>
                    <p class="text-gray-500 text-sm">Showing latest 100 transactions</p>
                <?php endif; ?>
            </div>
        </header>

        <div class="bg-[#111] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white/5 border-b border-white/5 text-gray-400 text-xs uppercase tracking-wider">
                            <th class="p-4 font-bold">ID</th>
                            <?php if (!$user_id): ?><th class="p-4 font-bold">User</th><?php endif; ?>
                            <th class="p-4 font-bold">Type</th>
                            <th class="p-4 font-bold">Amount</th>
                            <th class="p-4 font-bold">Description</th>
                            <th class="p-4 font-bold">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-sm">
                        <?php foreach ($transactions as $t): ?>
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="p-4 text-gray-500">#<?= $t['id'] ?></td>
                            <?php if (!$user_id): ?>
                                <td class="p-4 font-bold"><?= htmlspecialchars($t['user_name']) ?></td>
                            <?php endif; ?>
                            <td class="p-4">
                                <?php
                                $color = 'text-gray-400';
                                if ($t['type'] === 'win' || $t['type'] === 'deposit' || strpos($t['description'], 'Added') !== false) $color = 'text-green-500';
                                if ($t['type'] === 'loss' || $t['type'] === 'withdraw' || strpos($t['description'], 'Subtracted') !== false) $color = 'text-red-500';
                                ?>
                                <span class="capitalize font-bold <?= $color ?>"><?= $t['type'] ?></span>
                            </td>
                            <td class="p-4 font-mono font-bold <?= $color ?>">
                                <?= ($t['type'] === 'loss' || $t['type'] === 'withdraw' || strpos($t['description'], 'Subtracted') !== false) ? '-' : '+' ?>₹<?= number_format($t['amount'], 2) ?>
                            </td>
                            <td class="p-4 text-gray-400 max-w-xs truncate" title="<?= htmlspecialchars($t['description']) ?>">
                                <?= htmlspecialchars($t['description']) ?>
                            </td>
                            <td class="p-4 text-gray-500 text-xs text-right whitespace-nowrap">
                                <?= date('M j, Y h:i A', strtotime($t['created_at'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (count($transactions) === 0): ?>
                            <tr><td colspan="6" class="p-12 text-center text-gray-500">No transactions found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
