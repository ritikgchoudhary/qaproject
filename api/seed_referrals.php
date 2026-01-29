<?php
include 'config.php';

// Check if running from CLI or Browser
$target_code = isset($_GET['code']) ? $_GET['code'] : (isset($argv[1]) ? $argv[1] : null);

if (!$target_code) {
    die("Error: Referral Code required. Usage: php seed_referrals.php <ReferralCode>\n");
}

echo "Seeding referrals for Code: $target_code\n";

// 1. Verify referrer exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
$stmt->execute([$target_code]);
$referrer_id = $stmt->fetchColumn();

if (!$referrer_id) {
    die("Error: User with code $target_code not found.\n");
}

// 2. Add 3 Direct Referrals
for ($i = 1; $i <= 3; $i++) {
    $name = "Team Member $i";
    $email = "member{$i}_" . uniqid() . "@test.com";
    $pass = password_hash("123", PASSWORD_DEFAULT);
    $new_code = "SUB" . uniqid();
    
    // Check if slot filled (logic validation not strictly needed for forcing seed, but good practice)
    // We just insert.
    
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, referral_code, referred_by) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$name, $email, $pass, $new_code, $target_code])) {
        echo "[+] Added Direct Referral: $name ($new_code)\n";
        
        // 3. Add Sub-referrals (Team Size for this member)
        // Let's give them random 1-5 sub-referrals to make the digits interesting
        $sub_count = rand(1, 5);
        for ($j = 1; $j <= $sub_count; $j++) {
            $sub_name = "Sub-Ref $i-$j";
            $sub_email = "sub{$i}{$j}_" . uniqid() . "@test.com";
            $sub_code = "DEEP" . uniqid();
            $sub_pass = password_hash("123", PASSWORD_DEFAULT);
            
            $stmt_sub = $pdo->prepare("INSERT INTO users (name, email, password, referral_code, referred_by) VALUES (?, ?, ?, ?, ?)");
            $stmt_sub->execute([$sub_name, $sub_email, $sub_pass, $sub_code, $new_code]);
        }
        echo "    -> Added $sub_count sub-referrals (Team Count)\n";
    } else {
        echo "[-] Failed to add member $i\n";
    }
}

echo "Success! Dashboard should now show 3 filled slots with team counts.\n";
?>
