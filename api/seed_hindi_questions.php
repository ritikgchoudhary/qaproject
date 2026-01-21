<?php
include 'config.php';

echo "Seeding Hindi Image Questions...\n";

// Clear existing questions and answers to avoid duplicates/constraints
$pdo->exec("DELETE FROM answers");
$pdo->exec("DELETE FROM questions");

$questions = [
    [
        "question" => "यह प्रसिद्ध स्मारक भारत के किस शहर में स्थित है?",
        "image_url" => "https://upload.wikimedia.org/wikipedia/commons/thumb/t/t6/India_Gate_600x400.jpg/640px-India_Gate_600x400.jpg",
        "option_a" => "मुंबई (Mumbai)",
        "option_b" => "नई दिल्ली (New Delhi)",
        "option_c" => "आगरा (Agra)",
        "option_d" => "जयपुर (Jaipur)",
        "answer" => "नई दिल्ली (New Delhi)"
    ],
    [
        "question" => "चित्र में दिखाए गए भारत के राष्ट्रीय पक्षी का क्या नाम है?",
        "image_url" => "https://upload.wikimedia.org/wikipedia/commons/thumb/c/c2/Peacock_Plumage.jpg/640px-Peacock_Plumage.jpg",
        "option_a" => "तोता (Parrot)",
        "option_b" => "कबूतर (Pigeon)",
        "option_c" => "मोर (Peacock)",
        "option_d" => "हंस (Swan)",
        "answer" => "मोर (Peacock)"
    ],
    [
        "question" => "यह किस खेल से संबंधित है?",
        "image_url" => "https://upload.wikimedia.org/wikipedia/commons/thumb/2/22/Cricket_ball.jpg/640px-Cricket_ball.jpg",
        "option_a" => "फुटबॉल (Football)",
        "option_b" => "हॉकी (Hockey)",
        "option_c" => "टेनिस (Tennis)",
        "option_d" => "क्रिकेट (Cricket)",
        "answer" => "क्रिकेट (Cricket)"
    ],
    [
        "question" => "फलों का राजा किसे कहा जाता है?",
        "image_url" => "https://upload.wikimedia.org/wikipedia/commons/thumb/9/90/Hapus_Mango.jpg/640px-Hapus_Mango.jpg",
        "option_a" => "केला (Banana)",
        "option_b" => "सेब (Apple)",
        "option_c" => "आम (Mango)",
        "option_d" => "अंगूर (Grapes)",
        "answer" => "आम (Mango)"
    ],
    [
        "question" => "दुनिया का सबसे बड़ा महाद्वीप कौन सा है?",
        "image_url" => "https://upload.wikimedia.org/wikipedia/commons/thumb/8/80/Asia_%28orthographic_projection%29.svg/640px-Asia_%28orthographic_projection%29.svg.png",
        "option_a" => "अफ्रीका (Africa)",
        "option_b" => "यूरोप (Europe)",
        "option_c" => "एशिया (Asia)",
        "option_d" => "ऑस्ट्रेलिया (Australia)",
        "answer" => "एशिया (Asia)"
    ],
    [
        "question" => "भारत की मुद्रा (Currency) क्या है?",
        "image_url" => "https://upload.wikimedia.org/wikipedia/commons/thumb/e/e3/2000_in_Note.jpg/640px-2000_in_Note.jpg",
        "option_a" => "डॉलर (Dollar)",
        "option_b" => "यूरो (Euro)",
        "option_c" => "रुपया (Rupee)",
        "option_d" => "येन (Yen)",
        "answer" => "रुपया (Rupee)"
    ]
];

$stmt = $pdo->prepare("INSERT INTO questions (question, image_url, option_a, option_b, option_c, option_d, answer) VALUES (?, ?, ?, ?, ?, ?, ?)");

foreach ($questions as $q) {
    // If image URL fails (404), we might want a fallback, but here we assume Wikipedia works.
    $stmt->execute([
        $q['question'],
        $q['image_url'],
        $q['option_a'],
        $q['option_b'],
        $q['option_c'],
        $q['option_d'],
        $q['answer']
    ]);
}

echo "Successfully added " . count($questions) . " Hindi Image Questions.\n";
?>
