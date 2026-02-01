<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
require 'db_connect.php';
require 'components/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom QR - Master Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-[#050505] text-white min-h-screen flex">

    <?php include 'components/sidebar.php'; ?>

    <main class="ml-64 flex-1 p-8">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold">Custom QR Management</h1>
                <p class="text-gray-500 text-sm">Upload and manage custom QR code for payments</p>
            </div>
        </header>

        <div class="bg-[#111] border border-white/5 rounded-2xl p-8 shadow-xl">
            <!-- Current QR Status -->
            <div class="mb-8">
                <h2 class="text-lg font-bold mb-4">Current QR Status</h2>
                <div id="currentQR" class="bg-[#0a0a0a] border border-white/5 rounded-xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-gray-400">Status:</span>
                        <span id="statusBadge" class="px-3 py-1 rounded text-xs font-bold"></span>
                    </div>
                    <div id="qrPreview" class="mt-4"></div>
                </div>
            </div>

            <!-- Upload QR Form -->
            <div class="mb-8">
                <h2 class="text-lg font-bold mb-4">Upload QR Code</h2>
                <form id="uploadForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-400 mb-2">Select QR Image</label>
                        <input type="file" id="qrFile" accept="image/*" class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-yellow-500/20 file:text-yellow-400 hover:file:bg-yellow-500/30 file:cursor-pointer bg-[#0a0a0a] border border-white/5 rounded-lg p-2">
                        <p class="text-xs text-gray-500 mt-1">Supported formats: JPG, PNG, GIF, WEBP (Max 5MB)</p>
                    </div>
                    <button type="submit" class="px-6 py-3 bg-yellow-500/20 hover:bg-yellow-500/30 text-yellow-400 font-bold rounded-lg border border-yellow-500/30 transition-colors">
                        Upload QR Code
                    </button>
                </form>
            </div>

            <!-- Toggle Switch -->
            <div class="mb-8">
                <h2 class="text-lg font-bold mb-4">Enable/Disable QR</h2>
                <div class="flex items-center justify-between bg-[#0a0a0a] border border-white/5 rounded-xl p-6">
                    <div>
                        <p class="font-bold text-white">Custom QR Payment</p>
                        <p class="text-sm text-gray-500">Toggle to enable or disable custom QR channel for users</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="toggleQR" class="sr-only peer">
                        <div class="w-14 h-7 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-yellow-500/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-yellow-500"></div>
                    </label>
                </div>
            </div>

            <!-- Message Display -->
            <div id="message" class="hidden mb-4 p-4 rounded-lg"></div>
        </div>
    </main>

    <script>
        // Load current QR status
        function loadQRStatus() {
            fetch('api_action.php?action=get_custom_qr')
                .then(res => res.json())
                .then(data => {
                    const statusBadge = document.getElementById('statusBadge');
                    const qrPreview = document.getElementById('qrPreview');
                    const toggleQR = document.getElementById('toggleQR');

                    if (data.success && data.enabled) {
                        statusBadge.textContent = 'ENABLED';
                        statusBadge.className = 'px-3 py-1 rounded text-xs font-bold text-green-400 bg-green-500/10 border border-green-500/20';
                        toggleQR.checked = true;
                    } else {
                        statusBadge.textContent = 'DISABLED';
                        statusBadge.className = 'px-3 py-1 rounded text-xs font-bold text-red-400 bg-red-500/10 border border-red-500/20';
                        toggleQR.checked = false;
                    }

                    if (data.qr_image) {
                        qrPreview.innerHTML = `
                            <div class="text-sm text-gray-400 mb-2">Current QR Code:</div>
                            <img src="${data.qr_image}" alt="QR Code" class="max-w-xs mx-auto border border-white/10 rounded-lg">
                        `;
                    } else {
                        qrPreview.innerHTML = '<p class="text-gray-500 text-sm">No QR code uploaded yet</p>';
                    }
                })
                .catch(err => {
                    console.error('Error loading QR status:', err);
                    showMessage('Error loading QR status', 'error');
                });
        }

        // Upload QR Form
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const fileInput = document.getElementById('qrFile');
            const file = fileInput.files[0];

            if (!file) {
                showMessage('Please select a QR image file', 'error');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                showMessage('File size must be less than 5MB', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('qr_image', file);

            fetch('api_action.php?action=upload_custom_qr', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showMessage('QR code uploaded successfully!', 'success');
                    fileInput.value = '';
                    loadQRStatus();
                } else {
                    showMessage(data.error || 'Failed to upload QR code', 'error');
                }
            })
            .catch(err => {
                console.error('Error uploading QR:', err);
                showMessage('Error uploading QR code', 'error');
            });
        });

        // Toggle QR
        document.getElementById('toggleQR').addEventListener('change', function() {
            const enabled = this.checked ? 1 : 0;
            
            fetch('api_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle_custom_qr', enabled: enabled })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showMessage(`Custom QR ${enabled ? 'enabled' : 'disabled'} successfully!`, 'success');
                    loadQRStatus();
                } else {
                    showMessage(data.error || 'Failed to toggle QR status', 'error');
                    this.checked = !this.checked; // Revert toggle
                }
            })
            .catch(err => {
                console.error('Error toggling QR:', err);
                showMessage('Error toggling QR status', 'error');
                this.checked = !this.checked; // Revert toggle
            });
        });

        // Show message
        function showMessage(text, type) {
            const messageDiv = document.getElementById('message');
            messageDiv.textContent = text;
            messageDiv.className = `mb-4 p-4 rounded-lg ${type === 'success' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'}`;
            messageDiv.classList.remove('hidden');
            setTimeout(() => {
                messageDiv.classList.add('hidden');
            }, 5000);
        }

        // Initial load
        loadQRStatus();
    </script>
</body>
</html>
