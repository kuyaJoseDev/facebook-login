<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $post_id = $_POST['post_id'];
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $post_id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        header("Location: LeagueBook_Page.php?deleted=true");
    } else {
        header("Location: LeagueBook_Page.php?error=delete_failed");
    }
    exit();
}
?>
