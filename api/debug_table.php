<?php
include 'config.php';
$stmt = $pdo->query("SHOW CREATE TABLE answers");
$info = $stmt->fetch(PDO::FETCH_ASSOC);
file_put_contents('table_info.txt', print_r($info, true));
echo "Done.\n";
?>
