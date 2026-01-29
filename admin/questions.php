<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
require 'db_connect.php';

// Fetch Questions
$stmt = $pdo->query("SELECT * FROM questions ORDER BY id DESC");
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Questions - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-[#050505] text-white min-h-screen flex">

    <?php include 'components/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="ml-64 flex-1 p-8">
        
        <header class="flex justify-between items-center mb-8">
             <div>
                <h1 class="text-2xl font-bold">Questions Manager</h1>
                <p class="text-gray-500 text-sm">Add or Remove Quiz Content</p>
            </div>
        </header>
        
        <!-- Add Question Form -->
        <div class="bg-[#111] border border-white/5 rounded-2xl p-6 mb-8">
            <h2 id="formTitle" class="text-xl font-bold mb-4 text-yellow-500">Add New Question</h2>
            <form action="questions_action.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 gap-4 max-w-4xl">
                <input type="hidden" name="action" id="actionInput" value="add_question">
                <input type="hidden" name="id" id="hiddenId" value="">
                
                <!-- Question -->
                <div>
                    <label class="block text-xs uppercase text-gray-500 font-bold mb-1">Question Text (Hindi/English)</label>
                    <input type="text" name="question" required class="w-full bg-[#1a1a1a] border border-white/10 rounded px-3 py-2 text-white focus:outline-none focus:border-yellow-500" placeholder="e.g. What is the capital of India?">
                </div>

                <!-- Image -->
                <div class="grid grid-cols-2 gap-4">
                     <div>
                        <label class="block text-xs uppercase text-gray-500 font-bold mb-1">Image URL (Optional)</label>
                        <input type="text" name="image_url_text" class="w-full bg-[#1a1a1a] border border-white/10 rounded px-3 py-2 text-white focus:outline-none focus:border-yellow-500" placeholder="https://...">
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500 font-bold mb-1">OR Upload Image</label>
                        <input type="file" name="image_file" accept="image/*" class="w-full bg-[#1a1a1a] border border-white/10 rounded px-3 py-1.5 text-sm text-gray-400 file:mr-4 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-yellow-500 file:text-black hover:file:bg-yellow-400">
                    </div>
                </div>

                <!-- Options -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs uppercase text-gray-500 font-bold mb-1">Option A</label>
                        <input type="text" name="option_a" required class="w-full bg-[#1a1a1a] border border-white/10 rounded px-3 py-2 text-white focus:outline-none focus:border-yellow-500">
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500 font-bold mb-1">Option B</label>
                        <input type="text" name="option_b" required class="w-full bg-[#1a1a1a] border border-white/10 rounded px-3 py-2 text-white focus:outline-none focus:border-yellow-500">
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500 font-bold mb-1">Option C</label>
                        <input type="text" name="option_c" required class="w-full bg-[#1a1a1a] border border-white/10 rounded px-3 py-2 text-white focus:outline-none focus:border-yellow-500">
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500 font-bold mb-1">Option D</label>
                        <input type="text" name="option_d" required class="w-full bg-[#1a1a1a] border border-white/10 rounded px-3 py-2 text-white focus:outline-none focus:border-yellow-500">
                    </div>
                </div>

                <!-- Correct Answer -->
                <div>
                    <label class="block text-xs uppercase text-gray-500 font-bold mb-1">Correct Answer (Exact Match)</label>
                    <select name="answer" id="answer_select" class="w-full bg-[#1a1a1a] border border-white/10 rounded px-3 py-2 text-white focus:outline-none focus:border-yellow-500">
                        <option value="" disabled selected>Select Correct Option...</option>
                        <!-- JS can sync these but for now manual or simple text input might be safer. 
                             Let's assume user types exact text or we provide dropdown logic if complex. 
                             For simplicity: User must copy-paste correct text OR we make a JS listener to populate option values. -->
                         <option value="A">Option A</option>
                         <option value="B">Option B</option>
                         <option value="C">Option C</option>
                         <option value="D">Option D</option>
                    </select>
                    <p class="text-[10px] text-gray-500 mt-1">Note: System will use the text from the selected option.</p>
                </div>

                <div class="flex gap-4">
                    <button type="submit" id="submitKey" class="flex-1 bg-yellow-500 text-black font-bold py-3 rounded hover:bg-yellow-400 transition transform active:scale-95">Add Question</button>
                    <button type="button" id="cancelBtn" onclick="resetForm()" class="hidden flex-none bg-gray-700 text-white font-bold py-3 px-6 rounded hover:bg-gray-600 transition">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Questions List -->
        <div class="bg-[#111] border border-white/5 rounded-2xl overflow-hidden">
             <div class="px-6 py-4 border-b border-white/5 bg-[#151515]">
                <h3 class="font-bold text-white">Existing Questions (<?= count($questions) ?>)</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-400">
                    <thead class="text-xs text-gray-500 uppercase bg-[#0a0a0a] border-b border-white/5">
                        <tr>
                            <th class="px-6 py-3">ID</th>
                            <th class="px-6 py-3">Image</th>
                            <th class="px-6 py-3">Question</th>
                            <th class="px-6 py-3">Correct Answer</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php foreach($questions as $q): ?>
                        <tr class="hover:bg-white/5">
                            <td class="px-6 py-4 font-mono text-xs"><?= $q['id'] ?></td>
                            <td class="px-6 py-4">
                                <?php if($q['image_url']): ?>
                                <img src="<?= htmlspecialchars($q['image_url']) ?>" class="w-16 h-10 object-cover rounded border border-white/10">
                                <?php else: ?>
                                <span class="text-xs text-gray-600">No Img</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 font-medium text-white max-w-xs truncate"><?= htmlspecialchars($q['question']) ?></td>
                            <td class="px-6 py-4 text-green-400"><?= htmlspecialchars($q['answer']) ?></td>
                            <td class="px-6 py-4 text-right flex justify-end gap-2">
                                <button onclick='editQuestion(<?= json_encode($q) ?>)' class="text-blue-500 hover:text-blue-400 font-bold text-xs uppercase">Edit</button>
                                <a href="questions_action.php?action=delete_question&id=<?= $q['id'] ?>" onclick="return confirm('Delete this question?')" class="text-red-500 hover:text-red-400 font-bold text-xs uppercase">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        // Simple script to update Answer Select values when Options change
        const form = document.querySelector('form');
        const opts = inputs = ['option_a', 'option_b', 'option_c', 'option_d'];
        const select = document.getElementById('answer_select');
        const submitBtn = document.getElementById('submitKey');
        const cancelBtn = document.getElementById('cancelBtn');
        const actionInput = document.getElementById('actionInput');
        const formTitle = document.getElementById('formTitle');
        const hiddenId = document.getElementById('hiddenId');

        form.addEventListener('input', (e) => {
            if (opts.includes(e.target.name)) {
                syncOptions();
            }
        });

        function syncOptions() {
            const vals = {
                A: document.querySelector('[name="option_a"]').value,
                B: document.querySelector('[name="option_b"]').value,
                C: document.querySelector('[name="option_c"]').value,
                D: document.querySelector('[name="option_d"]').value
            };
            
            // Update select options value
            select.options[1].value = vals.A; // Option A
            select.options[1].text = "A: " + (vals.A.substring(0, 20) || "Option A");
            
            select.options[2].value = vals.B;
            select.options[2].text = "B: " + (vals.B.substring(0, 20) || "Option B");
            
            select.options[3].value = vals.C;
            select.options[3].text = "C: " + (vals.C.substring(0, 20) || "Option C");
            
            select.options[4].value = vals.D;
            select.options[4].text = "D: " + (vals.D.substring(0, 20) || "Option D");
        }

        function editQuestion(q) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            // Set Form to Edit Mode
            formTitle.innerText = "Edit Question (ID: " + q.id + ")";
            formTitle.classList.add('text-blue-500');
            formTitle.classList.remove('text-yellow-500');
            
            actionInput.value = 'update_question';
            hiddenId.value = q.id;
            
            document.querySelector('[name="question"]').value = q.question;
            document.querySelector('[name="image_url_text"]').value = q.image_url;
            document.querySelector('[name="option_a"]').value = q.option_a;
            document.querySelector('[name="option_b"]').value = q.option_b;
            document.querySelector('[name="option_c"]').value = q.option_c;
            document.querySelector('[name="option_d"]').value = q.option_d;
            
            // Sync options first so values exist in select
            syncOptions();
            
            // Set correct answer
            select.value = q.answer;
            
            submitBtn.innerText = "Update Question";
            submitBtn.classList.remove('bg-yellow-500', 'hover:bg-yellow-400', 'text-black');
            submitBtn.classList.add('bg-blue-600', 'hover:bg-blue-500', 'text-white');
            
            cancelBtn.classList.remove('hidden');
        }

        function resetForm() {
            form.reset();
            formTitle.innerText = "Add New Question";
            formTitle.classList.remove('text-blue-500');
            formTitle.classList.add('text-yellow-500');
            
            actionInput.value = 'add_question';
            hiddenId.value = '';
            
            submitBtn.innerText = "Add Question";
            submitBtn.classList.add('bg-yellow-500', 'hover:bg-yellow-400', 'text-black');
            submitBtn.classList.remove('bg-blue-600', 'hover:bg-blue-500', 'text-white');
            
            cancelBtn.classList.add('hidden');
        }
    </script>
</body>
</html>
