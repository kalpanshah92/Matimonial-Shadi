<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

// Check if sample stories already exist
$check = $pdo->query("SELECT COUNT(*) FROM success_stories")->fetchColumn();
if ($check > 0) {
    echo "Sample stories already exist in database. Skipping.\n";
    exit;
}

$sampleStories = [
    [
        'user_id' => 0,
        'partner_name' => 'Rahul',
        'title' => 'A Match Made in Heaven',
        'story' => 'We connected on ' . SITE_NAME . ' in early 2023. What started as a simple "hello" turned into endless conversations about life, dreams, and family. Our families met within a month and instantly bonded. Today we are blessed with a beautiful marriage and cherish every moment together. Thank you for making our dreams come true!',
        'marriage_date' => '2024-02-14',
        'location' => 'Ahmedabad, Gujarat',
        'photo' => 'https://placehold.co/600x400/FFB6C1/FFFFFF?text=Couple+1&font=playfair',
        'is_approved' => 1,
    ],
    [
        'user_id' => 0,
        'partner_name' => 'Vivek',
        'title' => 'Found My Soulmate',
        'story' => 'After months of searching, I almost gave up. Then I came across Vivek\'s profile and something just clicked. We share the same values, love for family, and passion for travel. Our wedding was a dream come true with both families blessing us. ' . SITE_NAME . ' truly changed our lives forever.',
        'marriage_date' => '2024-05-22',
        'location' => 'Surat, Gujarat',
        'photo' => 'https://placehold.co/600x400/FFA07A/FFFFFF?text=Couple+2&font=playfair',
        'is_approved' => 1,
    ],
    [
        'user_id' => 0,
        'partner_name' => 'Karan',
        'title' => 'Tradition Meets Love',
        'story' => 'Both our families were looking for someone from the same Samaj. When our parents connected through ' . SITE_NAME . ', they knew it was a perfect match. Karan and I met and instantly felt a deep connection. Our traditional wedding was blessed with love from everyone. We are forever grateful!',
        'marriage_date' => '2023-11-10',
        'location' => 'Vadodara, Gujarat',
        'photo' => 'https://placehold.co/600x400/DDA0DD/FFFFFF?text=Couple+3&font=playfair',
        'is_approved' => 1,
    ],
    [
        'user_id' => 0,
        'partner_name' => 'Amit',
        'title' => 'From Strangers to Soulmates',
        'story' => 'Our story began with a simple "interest" sent through ' . SITE_NAME . '. What followed was weeks of beautiful conversations that grew into deep love and understanding. Our families loved each other instantly. The wedding was a magical celebration of our journey together.',
        'marriage_date' => '2024-01-18',
        'location' => 'Rajkot, Gujarat',
        'photo' => 'https://placehold.co/600x400/98D8C8/FFFFFF?text=Couple+4&font=playfair',
        'is_approved' => 1,
    ],
    [
        'user_id' => 0,
        'partner_name' => 'Rohan',
        'title' => 'Destiny Through Destiny',
        'story' => 'I was skeptical about online matrimony until I met Rohan. Our values aligned perfectly and we both wanted similar things in life. Our parents met, blessed our union, and we got married within 6 months. Every day feels like a blessing. Thank you ' . SITE_NAME . '!',
        'marriage_date' => '2024-06-15',
        'location' => 'Mumbai, Maharashtra',
        'photo' => 'https://placehold.co/600x400/87CEEB/FFFFFF?text=Couple+5&font=playfair',
        'is_approved' => 1,
    ],
    [
        'user_id' => 0,
        'partner_name' => 'Sagar',
        'title' => 'Happily Ever After',
        'story' => 'Sagar\'s profile stood out from the rest. His kindness, ambition, and family values matched exactly what I was looking for. Our engagement happened within 3 months and we celebrated our wedding surrounded by loved ones. We recommend ' . SITE_NAME . ' to everyone searching for true love.',
        'marriage_date' => '2023-12-05',
        'location' => 'Bhavnagar, Gujarat',
        'photo' => 'https://placehold.co/600x400/F0E68C/FFFFFF?text=Couple+6&font=playfair',
        'is_approved' => 1,
    ],
];

$stmt = $pdo->prepare(
    "INSERT INTO success_stories (user_id, partner_name, title, story, photo, marriage_date, location, is_approved) 
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);

$count = 0;
foreach ($sampleStories as $story) {
    $stmt->execute([
        $story['user_id'],
        $story['partner_name'],
        $story['title'],
        $story['story'],
        $story['photo'],
        $story['marriage_date'],
        $story['location'],
        $story['is_approved'],
    ]);
    $count++;
}

echo "Successfully inserted {$count} sample stories into the database.\n";
