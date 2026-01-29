<?php
/**
 * Test endpoint to manually test WatchPay callback
 * Usage: POST/GET with test parameters
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = '72.60.96.75';
$db_name = 'qa_platform';
$username = 'qa_platform';
$password = 'qa_platform';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

echo "<h2>WatchPay Callback Test</h2>";

// Check if order_id is provided
$test_order_id = $_GET['order_id'] ?? $_POST['order_id'] ?? '';

if ($test_order_id) {
    echo "<h3>Testing Order ID: $test_order_id</h3>";
    
    // Check if deposit exists
    $stmt = $pdo->prepare("SELECT id, user_id, amount, status, order_id, created_at FROM deposits WHERE order_id = ?");
    $stmt->execute([$test_order_id]);
    $deposit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($deposit) {
        echo "<pre>";
        echo "✅ Deposit Found:\n";
        print_r($deposit);
        echo "</pre>";
        
        if ($deposit['status'] == 'pending') {
            echo "<p style='color: orange;'>⚠️ Status is 'pending' - Callback should process this</p>";
        } elseif ($deposit['status'] == 'success') {
            echo "<p style='color: green;'>✅ Status is 'success' - Already processed</p>";
        } else {
            echo "<p style='color: red;'>❌ Status is '{$deposit['status']}'</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Deposit NOT found with order_id: $test_order_id</p>";
        
        // Show recent deposits
        $stmt = $pdo->query("SELECT id, order_id, user_id, amount, status, created_at FROM deposits ORDER BY created_at DESC LIMIT 10");
        $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<h4>Recent Deposits:</h4><pre>";
        print_r($recent);
        echo "</pre>";
    }
} else {
    echo "<p>Usage: ?order_id=YOUR_ORDER_ID</p>";
    
    // Show recent pending deposits
    $stmt = $pdo->query("SELECT id, order_id, user_id, amount, status, created_at FROM deposits WHERE status = 'pending' ORDER BY created_at DESC LIMIT 10");
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Recent Pending Deposits:</h3>";
    if ($pending) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Order ID</th><th>User ID</th><th>Amount</th><th>Status</th><th>Created</th><th>Test</th></tr>";
        foreach ($pending as $p) {
            $test_url = "?order_id=" . urlencode($p['order_id']);
            echo "<tr>";
            echo "<td>{$p['id']}</td>";
            echo "<td>{$p['order_id']}</td>";
            echo "<td>{$p['user_id']}</td>";
            echo "<td>₹{$p['amount']}</td>";
            echo "<td>{$p['status']}</td>";
            echo "<td>{$p['created_at']}</td>";
            echo "<td><a href='$test_url'>Test</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No pending deposits found.</p>";
    }
}

// Check callback log
$log_file = __DIR__ . "/callback_log.txt";
if (file_exists($log_file)) {
    echo "<h3>Recent Callback Logs (last 20 lines):</h3>";
    $lines = file($log_file);
    $recent_lines = array_slice($lines, -20);
    echo "<pre style='background: #1a1a1a; color: #0f0; padding: 10px; max-height: 400px; overflow: auto;'>";
    echo htmlspecialchars(implode('', $recent_lines));
    echo "</pre>";
} else {
    echo "<p style='color: orange;'>⚠️ Log file not found: callback_log.txt</p>";
}
?>
