<?php
session_start();
include("connect.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: LeagueBook.php");
    exit();
}

// Get the content from the POST request
$content = $_POST['content'] ?? '';

// Check if content is not empty
if (!empty(trim($content))) {
    $user_id = $_SESSION['user_id'];

    // Insert the post into the database
    $stmt = $conn->prepare("INSERT INTO posts (user_id, content) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $content);
    $stmt->execute();
}

// Redirect back to the main page
header("Location: LeagueBook_Page.php");
exit();
?>
