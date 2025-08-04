<?php
session_start();
include("connect.php");

$offline = $conn->prepare("UPDATE users SET online_status = 'offline' WHERE id = ?");
$offline->bind_param("i", $_SESSION['user_id']);
$offline->execute();

session_unset();
session_destroy();

header("Location: LeagueBook.php");
exit();


?>