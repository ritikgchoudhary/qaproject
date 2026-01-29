<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
require 'db_connect.php'; // Correct path for consistency inside admin folder scripts usually require db_connect.php which is right there, or ../api/config.php. 
// settings.php used ../api/config.php. Let's stick to what works. 
// Actually, admin/db_connect.php usually exists? Let's check. 
// settings.php used ../api/config.php. I will use the same.
require '../api/config.php';

$message = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $video_url = trim($_POST['video_url']);
    $title = trim($_POST['title']);
    $desc = trim($_POST['desc']);
    $btn_text = trim($_POST['btn_text']);
    
    // Checkbox logic for allow_skip
    $allow_skip = isset($_POST['allow_skip']) ? '1' : '0';

    // File Upload Logic
    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['video_file']['tmp_name'];
        $fileName = $_FILES['video_file']['name'];
        $fileSize = $_FILES['video_file']['size'];
        $fileType = $_FILES['video_file']['type'];
        
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        $allowedfileExtensions = array('mp4', 'webm', 'ogg');
        
        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Directory for uploads
            $uploadFileDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            
            // Unique filename
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;
            
            if(move_uploaded_file($fileTmpPath, $dest_path)) {
                // Use actual domain instead of localhost
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'iquizz.in';
                $video_url = $protocol . "://" . $host . "/admin/uploads/" . $newFileName;
            } else {
                $error = "File upload failed. Check folder permissions.";
            }
        } else {
            $error = "Invalid file format. Only mp4, webm, ogg allowed.";
        }
    }
    
    if (empty($error)) {
        // Upsert all settings
        $settings = [
            'tutorial_video_url' => $video_url, 
            'tutorial_title' => $title,
            'tutorial_desc' => $desc,
            'tutorial_btn_text' => $btn_text,
            'allow_skip' => $allow_skip 
        ];

        $success = true;
        foreach ($settings as $key => $val) {
             if ($key === 'tutorial_video_url' && empty($val)) continue;
             
             $stmt = $pdo->prepare("INSERT INTO global_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
             if (!$stmt->execute([$key, $val, $val])) $success = false;
        }

        if ($success) {
            $message = "Settings updated successfully!";
        } else {
            $error = "Failed to update some settings.";
        }
    }
}

// Fetch Current Settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM global_settings");
$all_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Helpers
$current_url = $all_settings['tutorial_video_url'] ?? '';
$current_title = $all_settings['tutorial_title'] ?? 'How It Works';
$current_desc = $all_settings['tutorial_desc'] ?? 'Watch the full video to unlock your quiz.';
$current_btn = $all_settings['tutorial_btn_text'] ?? 'WATCH TO CONTINUE';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Settings - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-[#050505] text-white min-h-screen flex">

    <?php include 'components/sidebar.php'; ?>

    <main class="ml-64 flex-1 p-8">
        <header class="flex justify-between items-center mb-8">
             <div>
                <h1 class="text-2xl font-bold">Tutorial Video</h1>
                <p class="text-gray-500 text-sm">Manage the onboarding video shown to users.</p>
            </div>
        </header>

        <div class="max-w-2xl">
            <?php if ($message): ?>
                <div class="bg-green-500/10 border border-green-500/20 text-green-500 p-4 rounded-xl mb-6">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-500 p-4 rounded-xl mb-6">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="bg-[#111] border border-white/5 rounded-2xl p-6">
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Modal Title</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($current_title) ?>" 
                                class="w-full bg-[#050505] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-yellow-500 transition-all placeholder-gray-700"
                            >
                        </div>
                         <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Button Text</label>
                            <input type="text" name="btn_text" value="<?= htmlspecialchars($current_btn) ?>" 
                                class="w-full bg-[#050505] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-yellow-500 transition-all placeholder-gray-700"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Description</label>
                        <textarea name="desc" rows="2"
                            class="w-full bg-[#050505] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-yellow-500 transition-all placeholder-gray-700"
                        ><?= htmlspecialchars($current_desc) ?></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Video Source</label>
                        <div class="grid grid-cols-1 gap-4">
                            <!-- Direct URL Input -->
                            <div>
                                <input type="url" name="video_url" value="<?= htmlspecialchars($current_url) ?>" 
                                    class="w-full bg-[#050505] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-yellow-500 transition-all placeholder-gray-700 mb-2"
                                    placeholder="https://example.com/video.mp4"
                                >
                                <p class="text-[10px] text-gray-600">OR Upload a new video file (Max 50MB)</p>
                            </div>
                            
                            <!-- File Upload -->
                            <div class="relative group">
                                <input type="file" name="video_file" accept="video/mp4,video/webm" 
                                    class="w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-yellow-500/10 file:text-yellow-500 hover:file:bg-yellow-500/20"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 p-4 bg-[#050505] rounded-xl border border-white/5">
                        <input type="checkbox" id="allow_skip" name="allow_skip" value="1" <?= ($all_settings['allow_skip'] ?? '0') == '1' ? 'checked' : '' ?> class="w-5 h-5 accent-yellow-500 bg-gray-700 border-gray-600 rounded focus:ring-yellow-500 focus:ring-2">
                        <label for="allow_skip" class="text-sm font-medium text-gray-300">Allow users to skip video?</label>
                    </div>

                    <button type="submit" class="bg-yellow-500 hover:bg-yellow-400 text-black font-bold py-3 px-6 rounded-xl transition-colors w-full">
                        Save Configuration
                    </button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
