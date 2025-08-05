<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit("Unauthorized");
}

$update = $conn->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
$update->bind_param("i", $_SESSION['user_id']);
$update->execute();
?>
