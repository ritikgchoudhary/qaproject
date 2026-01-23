<?php
include 'config.php';

// Accept Pagination Params
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get my code
$stmt = $pdo->prepare("SELECT referral_code FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$my_code = $stmt->fetchColumn();

// Get Total Count (For stats)
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
$stmtCount->execute([$my_code]);
$total_count = $stmtCount->fetchColumn();

// Get Paginated Referrals
$stmt = $pdo->prepare("
    SELECT 
        u.name, 
        u.email, 
        u.level,
        u.created_at,
        (SELECT COUNT(*) FROM users WHERE referred_by = u.referral_code) as team_count
    FROM users u 
    WHERE u.referred_by = :my_code
    ORDER BY u.created_at DESC
    LIMIT :limit OFFSET :offset
");

$stmt->bindValue(':my_code', $my_code);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    "referrals" => $referrals, 
    "count" => $total_count, // Total count for badge
    "code" => $my_code,
    "has_more" => ($offset + count($referrals)) < $total_count,
    "page" => $page
]);
?>
