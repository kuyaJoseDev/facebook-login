<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    die("Unauthorized access");
}

$action = $_POST['action'] ?? '';
$report_id = (int) ($_POST['report_id'] ?? 0);
$post_id   = (int) ($_POST['post_id'] ?? 0);

// ✅ Delete the post if requested
if ($action === 'delete' && $post_id > 0) {
    $deletePostStmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
    $deletePostStmt->bind_param("i", $post_id);
    $deletePostStmt->execute();
}

// ✅ Delete the report record (for both delete and dismiss actions)
if (($action === 'delete' || $action === 'dismiss') && $report_id > 0) {
    $deleteReportStmt = $conn->prepare("DELETE FROM reports WHERE id = ?");
    $deleteReportStmt->bind_param("i", $report_id);
    $deleteReportStmt->execute();
}

// ✅ Redirect back to admin report panel
if (!headers_sent()) {
    header("Location: admin_report.php");
    exit();
} else {
    echo "<script>window.location.href='admin_report.php';</script>";
    exit;
}
