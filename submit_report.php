<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['report_post_id'])) {
    header("Location: LeagueBook.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$post_id = $_SESSION['report_post_id'];
$reason = trim($_POST['reason'] ?? '');

if ($reason !== '') {
    $stmt = $conn->prepare("INSERT INTO reports (post_id, user_id, reason) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $post_id, $user_id, $reason);
    if ($stmt->execute()) {
        echo "Report submitted successfully.";
    } else {
        echo "Failed to submit report.";
    }
    unset($_SESSION['report_post_id']);
} else {
    echo "Please provide a reason.";
}
?>
