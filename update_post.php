<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id'])) {
    die("Login required.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = $_POST['post_id'] ?? 0;
    $content = trim($_POST['content'] ?? '');

    if (empty($content)) {
        die("Content cannot be empty.");
    }

    $user_id = $_SESSION['user_id'];

    // Optional: update the edited timestamp
    $stmt = $conn->prepare("UPDATE posts SET content = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $content, $post_id, $user_id);
    
    if ($stmt->execute()) {
        header("Location: LeagueBook_Page.php?updated=1");
        exit();
    } else {
        echo "❌ Failed to update post.";
    }
} else {
    echo "⛔ Invalid request.";
}
?>
