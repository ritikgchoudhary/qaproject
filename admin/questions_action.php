<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    die("Unauthorized");
}
require 'db_connect.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add_question') {
    $question = $_POST['question'];
    $opt_a = $_POST['option_a'];
    $opt_b = $_POST['option_b'];
    $opt_c = $_POST['option_c'];
    $opt_d = $_POST['option_d'];
    $answer = $_POST['answer']; // This holds the correct text value
    
    // Image Handling
    $image_url = '';
    
    // Check if simple URL provided
    if (!empty($_POST['image_url_text'])) {
        $image_url = trim($_POST['image_url_text']);
    }
    
    // Check if File Uploaded (Override text URL if both present)
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../api/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['image_file']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $targetPath)) {
            // Success
            // Store relative path accessible by frontend (or absolute URL if preferred)
            // Frontend is at root/frontend usually? Or accessing /api...
            // If we store 'uploads/xyz.jpg' relative to 'api', frontend needs correct path.
            // Let's store full URL approach or relative to domain root '/api/uploads/...'
            
            // Assuming site root is where index.html is? No, api is in /api. 
            // Better to store '/api/uploads/filename'
            $image_url = '/api/uploads/' . $fileName; 
        } else {
             die("Failed to upload image.");
        }
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO questions (question, option_a, option_b, option_c, option_d, answer, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$question, $opt_a, $opt_b, $opt_c, $opt_d, $answer, $image_url]);
        
        header("Location: questions.php?success=Question Added");
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
    exit();
}

if ($action === 'update_question') {
    $id = $_POST['id'];
    $question = $_POST['question'];
    $opt_a = $_POST['option_a'];
    $opt_b = $_POST['option_b'];
    $opt_c = $_POST['option_c'];
    $opt_d = $_POST['option_d'];
    $answer = $_POST['answer']; 
    
    // Image Handling
    $image_url = null; // Default to null (don't update)
    
    if (!empty($_POST['image_url_text'])) {
        $image_url = trim($_POST['image_url_text']);
    }
    
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../api/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = time() . '_' . basename($_FILES['image_file']['name']);
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $targetPath)) {
            $image_url = '/api/uploads/' . $fileName; 
        }
    }
    
    try {
        if ($image_url !== null) {
            $stmt = $pdo->prepare("UPDATE questions SET question=?, option_a=?, option_b=?, option_c=?, option_d=?, answer=?, image_url=? WHERE id=?");
            $stmt->execute([$question, $opt_a, $opt_b, $opt_c, $opt_d, $answer, $image_url, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE questions SET question=?, option_a=?, option_b=?, option_c=?, option_d=?, answer=? WHERE id=?");
            $stmt->execute([$question, $opt_a, $opt_b, $opt_c, $opt_d, $answer, $id]);
        }
        
        header("Location: questions.php?success=Question Updated");
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
    exit();
}

if ($action === 'delete_question') {
    $id = $_GET['id'];
    try {
        $pdo->beginTransaction();

        // 1. Delete associated answers (history) first
        $stmt = $pdo->prepare("DELETE FROM answers WHERE question_id = ?");
        $stmt->execute([$id]);

        // 2. Now delete the question
        $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        header("Location: questions.php?success=Deleted");
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error deleting: " . $e->getMessage());
    }
    exit();
}
?>
