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
    <title>Test Payment Gateways - Master Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-[#050505] text-white min-h-screen flex">

    <?php include 'components/sidebar.php'; ?>

    <main class="ml-64 flex-1 p-8">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold">Test Payment Gateways</h1>
                <p class="text-gray-500 text-sm">Test and manage payment gateway visibility</p>
            </div>
        </header>

        <!-- Payment Methods Enable/Disable -->
        <div class="bg-[#111] border border-white/5 rounded-2xl p-8 shadow-xl mb-6">
            <h2 class="text-lg font-bold mb-6">Payment Methods Visibility</h2>
            <p class="text-sm text-gray-400 mb-6">Enable or disable payment methods for users</p>
            
            <div class="space-y-4" id="paymentMethodsList">
                <!-- Will be loaded via JavaScript -->
            </div>
            
            <div id="settingsMessage" class="hidden mt-4 p-4 rounded-lg"></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Test Form -->
            <div class="bg-[#111] border border-white/5 rounded-2xl p-8 shadow-xl">
                <h2 class="text-lg font-bold mb-6">Test Configuration</h2>
                
                <form id="testForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-400 mb-2">User ID</label>
                        <input 
                            type="number" 
                            id="userId" 
                            value="3378" 
                            class="w-full px-4 py-3 bg-[#0a0a0a] border border-white/10 rounded-lg text-white focus:outline-none focus:border-yellow-500/50"
                            required
                        />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-400 mb-2">Amount (₹)</label>
                        <input 
                            type="number" 
                            id="amount" 
                            value="100" 
                            step="0.01"
                            min="1"
                            class="w-full px-4 py-3 bg-[#0a0a0a] border border-white/10 rounded-lg text-white focus:outline-none focus:border-yellow-500/50"
                            required
                        />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-400 mb-2">Payment Gateway</label>
                        <select 
                            id="gateway" 
                            class="w-full px-4 py-3 bg-[#0a0a0a] border border-white/10 rounded-lg text-white focus:outline-none focus:border-yellow-500/50"
                        >
                            <option value="WATCHPAY" selected>WatchPay</option>
                            <option value="SILKPAY">SilkPay</option>
                            <option value="SIMPLYPAY">SimplyPay</option>
                            <option value="CUSTOM_QR">Custom QR</option>
                        </select>
                    </div>
                    
                    <button 
                        type="submit" 
                        class="w-full px-6 py-3 bg-yellow-500/20 hover:bg-yellow-500/30 text-yellow-400 font-bold rounded-lg border border-yellow-500/30 transition-colors"
                    >
                        Generate Test Payment
                    </button>
                </form>
            </div>

            <!-- Test Results -->
            <div class="bg-[#111] border border-white/5 rounded-2xl p-8 shadow-xl">
                <h2 class="text-lg font-bold mb-6">Test Results</h2>
                
                <div id="loading" class="hidden text-center py-8">
                    <div class="inline-block w-8 h-8 border-2 border-yellow-500 border-t-transparent rounded-full animate-spin"></div>
                    <p class="mt-4 text-gray-400">Generating payment...</p>
                </div>
                
                <div id="results" class="hidden space-y-4">
                    <div class="bg-[#0a0a0a] border border-white/5 rounded-lg p-4">
                        <p class="text-sm text-gray-400 mb-2">Order ID:</p>
                        <p id="orderId" class="font-mono text-blue-400 font-bold"></p>
                    </div>
                    
                    <div class="bg-[#0a0a0a] border border-white/5 rounded-lg p-4">
                        <p class="text-sm text-gray-400 mb-2">Deposit ID:</p>
                        <p id="depositId" class="font-mono text-green-400 font-bold"></p>
                    </div>
                    
                    <div id="paymentUrlSection" class="bg-[#0a0a0a] border border-white/5 rounded-lg p-4 hidden">
                        <p class="text-sm text-gray-400 mb-2">Payment URL:</p>
                        <p id="paymentUrl" class="font-mono text-yellow-400 text-sm break-all mb-3"></p>
                        <a 
                            id="paymentLink" 
                            href="#" 
                            target="_blank"
                            class="inline-block px-4 py-2 bg-blue-500/20 hover:bg-blue-500/30 text-blue-400 font-bold rounded border border-blue-500/30 transition-colors"
                        >
                            Open Payment Gateway
                        </a>
                    </div>
                    
                    <div id="qrSection" class="bg-[#0a0a0a] border border-white/5 rounded-lg p-4 hidden">
                        <p class="text-sm text-gray-400 mb-2">QR Code:</p>
                        <div id="qrImage" class="flex justify-center"></div>
                    </div>
                    
                    <div id="errorSection" class="bg-red-500/10 border border-red-500/20 rounded-lg p-4 hidden">
                        <p class="text-sm text-red-400 font-bold mb-2">Error:</p>
                        <p id="errorMessage" class="text-red-300 text-sm"></p>
                    </div>
                </div>
                
                <div id="noResults" class="text-center py-8 text-gray-500">
                    <p>Fill the form and click "Generate Test Payment" to test</p>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Load payment methods settings
        async function loadPaymentMethods() {
            try {
                const res = await fetch('api_action.php?action=get_payment_methods');
                const text = await res.text();
                
                // Try to parse JSON, handle HTML errors
                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error('Error parsing JSON:', parseError);
                    console.error('Response was:', text.substring(0, 200));
                    // Use defaults if JSON parse fails
                    data = {
                        simplypay_enabled: '1',
                        watchpay_enabled: '1',
                        silkpay_enabled: '1',
                        custom_qr_enabled: '1'
                    };
                }
                
                const methods = [
                    { key: 'simplypay_enabled', name: 'SimplyPay', default: true },
                    { key: 'watchpay_enabled', name: 'WatchPay', default: true },
                    { key: 'silkpay_enabled', name: 'SilkPay', default: true },
                    { key: 'custom_qr_enabled', name: 'Custom QR', default: true }
                ];
                
                const container = document.getElementById('paymentMethodsList');
                if (!container) {
                    console.error('paymentMethodsList container not found');
                    return;
                }
                container.innerHTML = '';
                
                methods.forEach(method => {
                    const enabled = data[method.key] !== undefined ? data[method.key] === '1' : method.default;
                    
                    const div = document.createElement('div');
                    div.className = 'flex items-center justify-between bg-[#0a0a0a] border border-white/5 rounded-xl p-6';
                    div.innerHTML = `
                        <div>
                            <p class="font-bold text-white">${method.name}</p>
                            <p class="text-sm text-gray-500">${enabled ? 'Visible to users' : 'Hidden from users'}</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" class="sr-only peer" data-method="${method.key}" ${enabled ? 'checked' : ''}>
                            <div class="w-14 h-7 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-yellow-500/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-yellow-500"></div>
                        </label>
                    `;
                    
                    const checkbox = div.querySelector('input[type="checkbox"]');
                    checkbox.addEventListener('change', function() {
                        togglePaymentMethod(method.key, this.checked);
                    });
                    
                    container.appendChild(div);
                });
            } catch (error) {
                console.error('Error loading payment methods:', error);
            }
        }
        
        // Toggle payment method
        async function togglePaymentMethod(methodKey, enabled) {
            try {
                const res = await fetch('api_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'toggle_payment_method',
                        method_key: methodKey,
                        enabled: enabled ? 1 : 0
                    })
                });
                
                const data = await res.json();
                
                if (data.success) {
                    showMessage(`Payment method ${enabled ? 'enabled' : 'disabled'} successfully!`, 'success');
                } else {
                    showMessage(data.error || 'Failed to update payment method', 'error');
                    // Revert checkbox
                    document.querySelector(`[data-method="${methodKey}"]`).checked = !enabled;
                }
            } catch (error) {
                console.error('Error toggling payment method:', error);
                showMessage('Error updating payment method', 'error');
                document.querySelector(`[data-method="${methodKey}"]`).checked = !enabled;
            }
        }
        
        // Show message
        function showMessage(text, type) {
            const messageDiv = document.getElementById('settingsMessage');
            messageDiv.textContent = text;
            messageDiv.className = `mt-4 p-4 rounded-lg ${type === 'success' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'}`;
            messageDiv.classList.remove('hidden');
            setTimeout(() => {
                messageDiv.classList.add('hidden');
            }, 3000);
        }
        
        // Load on page load
        loadPaymentMethods();
        
        document.getElementById('testForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const userId = document.getElementById('userId').value;
            const amount = document.getElementById('amount').value;
            const gateway = document.getElementById('gateway').value;
            
            // Show loading - check if elements exist
            const loadingEl = document.getElementById('loading');
            const resultsEl = document.getElementById('results');
            if (loadingEl) loadingEl.classList.remove('hidden');
            if (resultsEl) resultsEl.classList.add('hidden');
            const noResultsEl = document.getElementById('noResults');
            const errorSectionEl = document.getElementById('errorSection');
            const paymentUrlSectionEl = document.getElementById('paymentUrlSection');
            const qrSectionEl = document.getElementById('qrSection');
            if (noResultsEl) noResultsEl.classList.add('hidden');
            if (errorSectionEl) errorSectionEl.classList.add('hidden');
            if (paymentUrlSectionEl) paymentUrlSectionEl.classList.add('hidden');
            if (qrSectionEl) qrSectionEl.classList.add('hidden');
            
            try {
                // Call admin test API
                const testRes = await fetch('api_test_payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        amount: amount,
                        gateway: gateway
                    })
                });
                
                const testData = await testRes.json();
                
                if (!testData.success) {
                    throw new Error(testData.error || 'Failed to create test payment');
                }
                
                // Show results - check if elements exist
                const loadingEl2 = document.getElementById('loading');
                const resultsEl2 = document.getElementById('results');
                if (loadingEl2) loadingEl2.classList.add('hidden');
                if (resultsEl2) resultsEl2.classList.remove('hidden');
                
                document.getElementById('orderId').textContent = testData.order_id || 'N/A';
                document.getElementById('depositId').textContent = testData.deposit_id || 'N/A';
                
                if (gateway === 'CUSTOM_QR') {
                    if (testData.qr_image) {
                        document.getElementById('qrSection').classList.remove('hidden');
                        document.getElementById('qrImage').innerHTML = `
                            <img src="${testData.qr_image}" alt="QR Code" class="max-w-xs border-2 border-yellow-500/30 rounded-lg p-2 bg-white">
                        `;
                    } else {
                        throw new Error('QR code not available or disabled');
                    }
                } else if (testData.payment_url) {
                    // Show payment URL
                    document.getElementById('paymentUrlSection').classList.remove('hidden');
                    document.getElementById('paymentUrl').textContent = testData.payment_url;
                    document.getElementById('paymentLink').href = testData.payment_url;
                } else {
                    throw new Error('Payment URL not received');
                }
                
            } catch (error) {
                document.getElementById('loading').classList.add('hidden');
                document.getElementById('results').classList.remove('hidden');
                document.getElementById('errorSection').classList.remove('hidden');
                document.getElementById('errorMessage').textContent = error.message;
                console.error('Test Error:', error);
            }
        });
    </script>
</body>
</html>
