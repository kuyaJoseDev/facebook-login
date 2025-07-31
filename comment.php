<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id']) || !isset($_POST['post_id']) || !isset($_POST['comment'])) {
    header("Location: LeagueBook_Page.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$post_id = (int)$_POST['post_id'];
$content = trim($_POST['comment']);

if ($content !== '') {
    $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $post_id, $user_id, $content);
    $stmt->execute();
}

// 🔁 Redirect back to the same post after commenting
header("Location: view_post.php?id=$post_id");
exit();
?>
