<?php
require 'api/config.php';
try {
    $stmt = $pdo->query("DESCRIBE wallets");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
