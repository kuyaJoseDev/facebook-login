<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: LeagueBook.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['post_id'])) {
    $post_id = intval($_POST['post_id']);
    $_SESSION['report_post_id'] = $post_id;
    header("Location: report_reason.php");
    exit();
} else {
    echo "Invalid report request.";
}
?>
