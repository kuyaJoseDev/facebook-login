<?php
session_start();
include("connect.php");

if (isset($_SESSION['user_id'])) {
    $update = $conn->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
    $update->bind_param("i", $_SESSION['user_id']);
    $update->execute();
}
?>
