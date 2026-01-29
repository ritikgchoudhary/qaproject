<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
require 'db_connect.php';

// Get all disputes
$stmt = $pdo->query("
    SELECT dd.*, u.name, u.email, u.referral_code, d.order_id, d.created_at as deposit_date
    FROM deposit_disputes dd
    JOIN users u ON dd.user_id = u.id
    JOIN deposits d ON dd.deposit_id = d.id
    ORDER BY dd.created_at DESC
");
$disputes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Disputes - Master Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-[#050505] text-white min-h-screen flex">

    <?php include 'components/sidebar.php'; ?>

    <main class="ml-64 flex-1 p-8">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold">Payment Disputes</h1>
                <p class="text-gray-500 text-sm">User payment issue reports</p>
            </div>
        </header>

        <div class="bg-[#111] border border-white/5 rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white/5 border-b border-white/5 text-gray-400 text-xs uppercase tracking-wider">
                            <th class="p-4 font-bold">ID</th>
                            <th class="p-4 font-bold">User</th>
                            <th class="p-4 font-bold">Amount</th>
                            <th class="p-4 font-bold">Order Number</th>
                            <th class="p-4 font-bold">Deposit Date</th>
                            <th class="p-4 font-bold">Screenshot</th>
                            <th class="p-4 font-bold">Message</th>
                            <th class="p-4 font-bold">Status</th>
                            <th class="p-4 font-bold">Submitted</th>
                            <th class="p-4 font-bold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-sm">
                        <?php if (count($disputes) > 0): ?>
                            <?php foreach($disputes as $dispute): ?>
                            <tr class="hover:bg-white/5 transition-colors">
                                <td class="p-4">#<?= $dispute['id'] ?></td>
                                <td class="p-4">
                                    <div>
                                        <p class="font-bold text-white"><?= htmlspecialchars($dispute['name']) ?></p>
                                        <p class="text-xs text-gray-400"><?= htmlspecialchars($dispute['email']) ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($dispute['referral_code']) ?></p>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span class="font-bold text-green-400">₹<?= number_format($dispute['amount'], 2) ?></span>
                                </td>
                                <td class="p-4">
                                    <p class="font-mono text-sm text-blue-400 font-bold">
                                        <?= htmlspecialchars($dispute['order_id'] ?? 'N/A') ?>
                                    </p>
                                </td>
                                <td class="p-4">
                                    <p class="text-xs"><?= date('d M Y, h:i A', strtotime($dispute['deposit_date'])) ?></p>
                                </td>
                                <td class="p-4">
                                    <?php if ($dispute['screenshot_path']): ?>
                                        <?php 
                                            // Ensure path starts with / for web access
                                            $screenshot_url = $dispute['screenshot_path'];
                                            if (substr($screenshot_url, 0, 4) !== 'http' && substr($screenshot_url, 0, 1) !== '/') {
                                                $screenshot_url = '/' . $screenshot_url;
                                            }
                                            // Check if file exists - admin folder is one level down from project root
                                            // __DIR__ = /www/wwwroot/iquizz.in/admin
                                            // So project root = __DIR__ . '/..'
                                            $project_root = realpath(__DIR__ . '/..');
                                            $file_path = $project_root . $screenshot_url;
                                            $file_exists = file_exists($file_path);
                                            // Get file name for download
                                            $file_name = basename($screenshot_url);
                                        ?>
                                        <div class="flex flex-col gap-2">
                                            <button 
                                                onclick="viewScreenshot('<?= htmlspecialchars($screenshot_url) ?>')"
                                                class="px-3 py-1 bg-blue-500/10 text-blue-400 hover:bg-blue-500 hover:text-white rounded text-xs font-bold border border-blue-500/20 transition-all"
                                            >
                                                📷 View Screenshot
                                            </button>
                                            <a 
                                                href="download_screenshot.php?id=<?= $dispute['id'] ?>"
                                                class="px-3 py-1 bg-green-500/10 text-green-400 hover:bg-green-500 hover:text-white rounded text-xs font-bold border border-green-500/20 transition-all inline-block text-center"
                                            >
                                                ⬇️ Download
                                            </a>
                                        </div>
                                        <?php if (!$file_exists): ?>
                                            <p class="text-xs text-red-400 mt-1">⚠️ File not found</p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-500 text-xs">No screenshot</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <p class="text-xs text-gray-300 max-w-xs truncate">
                                        <?= htmlspecialchars($dispute['user_message'] ?: 'No message') ?>
                                    </p>
                                </td>
                                <td class="p-4">
                                    <span class="px-2 py-1 rounded text-xs font-bold 
                                        <?= $dispute['status'] === 'pending' ? 'bg-yellow-500/20 text-yellow-400' : 
                                            ($dispute['status'] === 'reviewed' ? 'bg-blue-500/20 text-blue-400' : 'bg-green-500/20 text-green-400') ?>">
                                        <?= ucfirst($dispute['status']) ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <p class="text-xs"><?= date('d M Y, h:i A', strtotime($dispute['created_at'])) ?></p>
                                </td>
                                <td class="p-4">
                                    <div class="flex gap-2">
                                        <button 
                                            onclick="updateDisputeStatus(<?= $dispute['id'] ?>, 'reviewed')"
                                            class="px-3 py-1 bg-blue-500/10 text-blue-400 hover:bg-blue-500 hover:text-white rounded text-xs font-bold border border-blue-500/20 transition-all"
                                        >
                                            Review
                                        </button>
                                        <button 
                                            onclick="updateDisputeStatus(<?= $dispute['id'] ?>, 'resolved')"
                                            class="px-3 py-1 bg-green-500/10 text-green-400 hover:bg-green-500 hover:text-white rounded text-xs font-bold border border-green-500/20 transition-all"
                                        >
                                            Resolve
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="p-8 text-center text-gray-500">
                                    No payment disputes found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Screenshot Modal -->
    <div id="screenshotModal" class="hidden fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4" onclick="closeScreenshotModal()">
        <div class="bg-[#111] border border-white/10 rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-auto" onclick="event.stopPropagation()">
            <div class="p-4 border-b border-white/10 flex justify-between items-center">
                <h3 class="font-bold text-white">Payment Screenshot</h3>
                <button onclick="closeScreenshotModal()" class="text-gray-400 hover:text-white text-2xl font-bold">&times;</button>
            </div>
            <div class="p-4">
                <img id="screenshotImage" src="" alt="Screenshot" class="max-w-full h-auto rounded-lg" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'400\' height=\'300\'%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' fill=\'%23999\'%3EImage not found%3C/text%3E%3C/svg%3E'; this.alt='Image not found';">
            </div>
        </div>
    </div>

    <script>
    function updateDisputeStatus(id, status) {
        if (!confirm(`Are you sure you want to mark this dispute as ${status}?`)) return;
        
        fetch('api_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'update_dispute_status', 
                id: id, 
                status: status 
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(err => alert('Network error'));
    }

    function viewScreenshot(url) {
        const modal = document.getElementById('screenshotModal');
        const img = document.getElementById('screenshotImage');
        img.src = url;
        modal.classList.remove('hidden');
    }

    function closeScreenshotModal() {
        const modal = document.getElementById('screenshotModal');
        modal.classList.add('hidden');
    }

    function downloadScreenshot(url, filename) {
        // Fetch the image as a blob and download it
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.blob();
            })
            .then(blob => {
                // Create a blob URL
                const blobUrl = window.URL.createObjectURL(blob);
                
                // Create a temporary anchor element to trigger download
                const link = document.createElement('a');
                link.href = blobUrl;
                link.download = filename || 'screenshot.jpg';
                document.body.appendChild(link);
                link.click();
                
                // Clean up
                document.body.removeChild(link);
                window.URL.revokeObjectURL(blobUrl);
            })
            .catch(error => {
                console.error('Download error:', error);
                alert('Failed to download screenshot. Please try again.');
            });
    }

    // Close on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeScreenshotModal();
        }
    });
    </script>
</body>
</html>
