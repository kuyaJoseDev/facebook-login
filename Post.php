<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id'])) {
    die("Login required.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $content = trim($_POST['content'] ?? '');

    if (empty($content)) {
        die("Post content cannot be empty.");
    }

    // Handle image upload if present
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true); // create folder if not exists
        }

        $imageName = uniqid() . '_' . basename($_FILES['image']['name']);
        $targetPath = $uploadDir . $imageName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $imagePath = $targetPath;
        } else {
            die("❌ Failed to upload image.");
        }
    }

    // Insert post into DB
    $stmt = $conn->prepare("INSERT INTO posts (user_id, content, image_path, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $user_id, $content, $imagePath);
    $stmt->execute();

    header("Location: LeagueBook_Page.php?posted=1");
    exit();
}
?>
