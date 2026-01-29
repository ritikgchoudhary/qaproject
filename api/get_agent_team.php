<?php
include 'config.php';
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20; // Default 20 for scroll
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get My Referral Code
$stmt = $pdo->prepare("SELECT referral_code FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$my_code = $stmt->fetchColumn();

if (!$my_code) {
    echo json_encode(["success" => true, "team" => [], "my_code" => null]);
    exit();
}

// Optimized Tree Construction Logic
// 1. Fetch Root Nodes (Direct Referrals) Paginated
$sqlRoots = "SELECT id, name, email, role, created_at, referral_code, referred_by, 1 as level 
             FROM users 
             WHERE referred_by = :code 
             ORDER BY created_at DESC 
             LIMIT :limit OFFSET :offset";
$stmtRoots = $pdo->prepare($sqlRoots);
$stmtRoots->bindValue(':code', $my_code);
$stmtRoots->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtRoots->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtRoots->execute();
$roots = $stmtRoots->fetchAll(PDO::FETCH_ASSOC);

$all_members = $roots;

// If we found roots, fetch their descendants (Level 2 & Level 3)
// We do this immediately to show the "Full Tree" for these visible roots.
if (!empty($roots)) {
    // Get L1 Codes
    $l1_codes = array_column($roots, 'referral_code');
    $placeholders = implode(',', array_fill(0, count($l1_codes), '?'));

    // 2. Fetch Level 2 (Children of L1)
    if (!empty($l1_codes)) {
        $stmtL2 = $pdo->prepare("SELECT id, name, email, role, created_at, referral_code, referred_by, 2 as level 
                                 FROM users WHERE referred_by IN ($placeholders)");
        $stmtL2->execute($l1_codes);
        $l2 = $stmtL2->fetchAll(PDO::FETCH_ASSOC);
        $all_members = array_merge($all_members, $l2);
        
        // Get L2 Codes to fetch L3
        $l2_codes = array_column($l2, 'referral_code');
        if (!empty($l2_codes)) {
             $placeholders2 = implode(',', array_fill(0, count($l2_codes), '?'));
             $stmtL3 = $pdo->prepare("SELECT id, name, email, role, created_at, referral_code, referred_by, 3 as level 
                                      FROM users WHERE referred_by IN ($placeholders2)");
             $stmtL3->execute($l2_codes);
             $l3 = $stmtL3->fetchAll(PDO::FETCH_ASSOC);
             $all_members = array_merge($all_members, $l3);
        }
    }
}

// Optimized Enrichment (Bulk Fetch) for ALL fetched members
if (!empty($all_members)) {
    $ids = array_column($all_members, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?')); // Reset placeholders
    
    // 1. Bulk Deposits (Success)
    $stmt_dep = $pdo->prepare("SELECT user_id, SUM(amount) as total FROM deposits WHERE user_id IN ($placeholders) AND status = 'success' GROUP BY user_id");
    $stmt_dep->execute($ids);
    $deposits_map = $stmt_dep->fetchAll(PDO::FETCH_KEY_PAIR);

    // 1b. Fetch LATEST deposit status (to show pending status)
    $stmt_status = $pdo->prepare("
        SELECT d1.user_id, d1.status 
        FROM deposits d1
        INNER JOIN (
            SELECT user_id, MAX(created_at) as latest 
            FROM deposits 
            WHERE user_id IN ($placeholders)
            GROUP BY user_id
        ) d2 ON d1.user_id = d2.user_id AND d1.created_at = d2.latest
    ");
    $stmt_status->execute($ids);
    $status_map = $stmt_status->fetchAll(PDO::FETCH_KEY_PAIR); // [user_id => status]
    
    // 2. Bulk Earnings
    $stmt_earn = $pdo->prepare("SELECT from_user_id, SUM(amount) as total FROM agent_commissions WHERE agent_id = ? AND from_user_id IN ($placeholders) GROUP BY from_user_id");
    $params_earn = array_merge([$user_id], $ids);
    $stmt_earn->execute($params_earn);
    $earnings_map = $stmt_earn->fetchAll(PDO::FETCH_KEY_PAIR); // [from_user_id => total]
    
    // Apply map
    foreach ($all_members as &$member) {
        $member['total_deposit'] = isset($deposits_map[$member['id']]) ? $deposits_map[$member['id']] : 0;
        $member['earned_from'] = isset($earnings_map[$member['id']]) ? $earnings_map[$member['id']] : 0;
        $member['latest_deposit_status'] = isset($status_map[$member['id']]) ? $status_map[$member['id']] : 'none';
    }
}

echo json_encode([
    "success" => true,
    "team" => $all_members, // Flat list of (Roots + their descendants)
    "my_code" => $my_code,
    "has_more" => count($roots) === $limit // Infinite scroll check based on ROOTS
]);
?>
