<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    die("Unauthorized");
}

$action = $_POST['action'] ?? '';
$report_id = $_POST['report_id'] ?? 0;
$post_id = $_POST['post_id'] ?? 0;

if ($action === 'delete' && $post_id > 0) {
    $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
}

if ($report_id > 0) {
    $stmt = $conn->prepare("DELETE FROM reports WHERE id = ?");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
}

header("Location: admin_report.php");
exit();
