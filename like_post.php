<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: LeagueBook.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = $_POST['post_id'] ?? null;
    $user_id = $_SESSION['user_id'];

    if (is_numeric($post_id)) {
        $check = $conn->prepare("SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?");
        $check->bind_param("ii", $post_id, $user_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            // Unlike
            $delete = $conn->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?");
            $delete->bind_param("ii", $post_id, $user_id);
            $delete->execute();
        } else {
            // Like
            $insert = $conn->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)");
            $insert->bind_param("ii", $post_id, $user_id);
            $insert->execute();
        }
    }
}

header("Location: LeagueBook_Page.php");
exit();
?>
