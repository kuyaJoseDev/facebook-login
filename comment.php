<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id'])) {
    die("Login required.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $post_id = $_POST['post_id'];
    $comment = trim($_POST['comment']);
    $user_id = $_SESSION['user_id'];

    if (empty($comment)) {
        die("Comment cannot be empty.");
    }

    $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $post_id, $user_id, $comment);
    $stmt->execute();

    header("Location: LeagueBook_Page.php");
    exit();
}
?>
