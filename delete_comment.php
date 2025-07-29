<?php
session_start();
include("connect.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_id'])) {
    $comment_id = $_POST['comment_id'];
    $user_id = $_SESSION['user_id'];

    // Verify comment belongs to the logged-in user
    $stmt = $conn->prepare("DELETE FROM comments WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $comment_id, $user_id);

    if ($stmt->execute()) {
        // Redirect back to the previous page or homepage
        header("Location: LeagueBook_Page.php");
    } else {
        echo "Failed to delete comment.";
    }
} else {
    echo "Invalid request.";
}
?>
