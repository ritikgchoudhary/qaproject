<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

require 'db_connect.php';

$dispute_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$dispute_id) {
    http_response_code(400);
    die('Invalid dispute ID');
}

// Get dispute with screenshot path
$stmt = $pdo->prepare("SELECT screenshot_path FROM deposit_disputes WHERE id = ?");
$stmt->execute([$dispute_id]);
$dispute = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dispute || !$dispute['screenshot_path']) {
    http_response_code(404);
    die('Screenshot not found');
}

$screenshot_path = $dispute['screenshot_path'];
// Ensure path starts with /
if (substr($screenshot_path, 0, 1) !== '/') {
    $screenshot_path = '/' . $screenshot_path;
}

// Get full file path
$project_root = realpath(__DIR__ . '/..');
$file_path = $project_root . $screenshot_path;

if (!file_exists($file_path)) {
    http_response_code(404);
    die('File not found');
}

// Get file info
$file_name = basename($screenshot_path);
$file_size = filesize($file_path);

// Get MIME type based on file extension
$file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$mime_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp'
];
$mime_type = $mime_types[$file_extension] ?? 'application/octet-stream';

// Set headers for download
header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Content-Length: ' . $file_size);
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Output file
readfile($file_path);
exit();
?>
