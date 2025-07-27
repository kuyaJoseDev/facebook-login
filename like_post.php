<?php
session_start();
include("connect.php");

$post_id = $_POST['post_id'];
$user_id = $_SESSION['user_id'];

$conn->query("INSERT IGNORE INTO likes (post_id, user_id) VALUES ($post_id, $user_id)");
header("Location: LeagueBook_Page.php");
?>
