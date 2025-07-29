<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: LeagueBook.php");
    exit();
}

$post_id = $_POST['post_id'] ?? 0;
$content = trim($_POST['content'] ?? '');
$user_id = $_SESSION['user_id'];

if (empty($post_id) || empty($content)) {
    die("⚠️ Invalid post data.");
}

// Initialize video path
$video_path = null;

if (!empty($_FILES['video']['name'])) {
    $videoTmp = $_FILES['video']['tmp_name'];
    $videoName = uniqid('vid_', true) . '_' . basename($_FILES['video']['name']);
    $targetDir = "uploads/videos/";

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $video_path = $targetDir . $videoName;

    $videoType = mime_content_type($videoTmp);
    if (str_starts_with($videoType, 'video/')) {
        if (!move_uploaded_file($videoTmp, $video_path)) {
            die("❌ Failed to upload video.");
        }
    } else {
        die("⚠️ Invalid video format.");
    }
}

// Update query
if ($video_path) {
    $stmt = $conn->prepare("UPDATE posts SET content = ?, video_path = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ssii", $content, $video_path, $post_id, $user_id);
} else {
    $stmt = $conn->prepare("UPDATE posts SET content = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $content, $post_id, $user_id);
}

// Execute
if ($stmt->execute()) {
    header("Location: LeagueBook_Page.php");
    exit();
} else {
    echo "❌ Failed to update post.";
}
?>
