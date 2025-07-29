<?php
session_start();
include("connect.php");

// 1. Validate session
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access. Please log in.");
}

$user_id = $_SESSION['user_id'];
$post_id = (int)($_POST['post_id'] ?? 0);
$parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
$content = trim($_POST['content'] ?? '');

// 2. Validate input
if (!$post_id || $content === '') {
    die("Post ID or content missing.");
}

// 3. Insert comment
$stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, parent_id, content, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("iiis", $post_id, $user_id, $parent_id, $content);

if ($stmt->execute()) {
    // Redirect to the post view (with optional open_reply flag if it's a reply)
    $redirect_url = "view_post.php?id=$post_id";
    if (!empty($parent_id)) {
        $redirect_url .= "&open_reply=$parent_id";
    }
    header("Location: $redirect_url");
    exit();
} else {
    error_log("Failed to insert comment: " . $stmt->error);
    echo "❌ Failed to add comment. Please try again later.";
}
?>
