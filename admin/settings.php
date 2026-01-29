<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
require '../api/config.php';
?>
<?php
$message = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_name = trim($_POST['site_name']);
    $seo_title = trim($_POST['seo_title']);
    $seo_desc = trim($_POST['seo_desc']);
    $seo_keywords = trim($_POST['seo_keywords']);
    $site_logo = '';

    // File Upload Logic for Logo
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['logo_file']['tmp_name'];
        $fileName = $_FILES['logo_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowed = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
        
        if (in_array($fileExtension, $allowed)) {
            $uploadFileDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadFileDir)) mkdir($uploadFileDir, 0755, true);
            
            $newFileName = 'logo_' . time() . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;
            
            if(move_uploaded_file($fileTmpPath, $dest_path)) {
                // Use actual domain instead of localhost
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'iquizz.in';
                $site_logo = $protocol . "://" . $host . "/admin/uploads/" . $newFileName;
            } else {
                $error = "Logo upload failed. Check permissions.";
            }
        } else {
            $error = "Invalid logo format. Allowed: jpg, png, svg, webp.";
        }
    }

    if (empty($error)) {
        $settings = [
            'site_name' => $site_name,
            'seo_title' => $seo_title,
            'seo_desc' => $seo_desc,
            'seo_keywords' => $seo_keywords
        ];
        if (!empty($site_logo)) {
            $settings['site_logo'] = $site_logo;
        }

        $success = true;
        foreach ($settings as $key => $val) {
             $stmt = $pdo->prepare("INSERT INTO global_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
             if (!$stmt->execute([$key, $val, $val])) $success = false;
        }

        if ($success) $message = "General settings updated!";
        else $error = "Failed to update.";
    }
}

// Fetch Current Settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM global_settings");
$all_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Settings - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-[#050505] text-white min-h-screen flex">

    <?php include 'components/sidebar.php'; ?>

    <main class="ml-64 flex-1 p-8">
        <header class="flex justify-between items-center mb-8">
             <div>
                <h1 class="text-2xl font-bold">General Settings</h1>
                <p class="text-gray-500 text-sm">Configure site identity and SEO.</p>
            </div>
            <a href="video_settings.php" class="bg-white/5 hover:bg-white/10 text-white px-4 py-2 rounded-lg text-sm font-medium border border-white/10 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                Manage Video
            </a>
        </header>

        <div class="max-w-3xl">
            <?php if ($message): ?>
                <div class="bg-green-500/10 border border-green-500/20 text-green-500 p-4 rounded-xl mb-6"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-500 p-4 rounded-xl mb-6"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <!-- Brand Section -->
                <div class="bg-[#111] border border-white/5 rounded-2xl p-6">
                    <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                        Brand Identity
                    </h2>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Website Name</label>
                            <input type="text" name="site_name" value="<?= htmlspecialchars($all_settings['site_name'] ?? 'Pinnacle') ?>" 
                                class="w-full bg-[#050505] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-yellow-500 transition-all placeholder-gray-700"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Website Logo</label>
                            <div class="flex items-start gap-6">
                                <div class="w-24 h-24 bg-[#050505] border border-white/10 rounded-lg flex items-center justify-center overflow-hidden relative group">
                                    <?php if (!empty($all_settings['site_logo'])): ?>
                                        <img src="<?= htmlspecialchars($all_settings['site_logo']) ?>" class="w-full h-full object-contain p-2">
                                    <?php else: ?>
                                        <span class="text-xs text-gray-600">No Logo</span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <input type="file" name="logo_file" accept=".jpg,.jpeg,.png,.svg,.webp" 
                                        class="w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-yellow-500/10 file:text-yellow-500 hover:file:bg-yellow-500/20 mb-2"
                                    >
                                    <p class="text-[10px] text-gray-600">Recommended: PNG or SVG, transparent background. Max 2MB.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SEO Section -->
                <div class="bg-[#111] border border-white/5 rounded-2xl p-6">
                    <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>
                        SEO Configuration
                    </h2>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Meta Title</label>
                            <input type="text" name="seo_title" value="<?= htmlspecialchars($all_settings['seo_title'] ?? '') ?>" 
                                class="w-full bg-[#050505] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-yellow-500 transition-all placeholder-gray-700"
                                placeholder="e.g. Pinnacle - Earn While You Learn"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Meta Description</label>
                            <textarea name="seo_desc" rows="3"
                                class="w-full bg-[#050505] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-yellow-500 transition-all placeholder-gray-700"
                                placeholder="Brief description of your website for search engines..."
                            ><?= htmlspecialchars($all_settings['seo_desc'] ?? '') ?></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Keywords</label>
                            <input type="text" name="seo_keywords" value="<?= htmlspecialchars($all_settings['seo_keywords'] ?? '') ?>" 
                                class="w-full bg-[#050505] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-yellow-500 transition-all placeholder-gray-700"
                                placeholder="quiz, earn money, rewards, learning (comma separated)"
                            >
                        </div>
                    </div>
                </div>

                <button type="submit" class="bg-yellow-500 hover:bg-yellow-400 text-black font-bold py-3 px-6 rounded-xl transition-colors w-full shadow-lg shadow-yellow-500/20">
                    Save Changes
                </button>
            </form>
        </div>
    </main>
</body>
</html>
