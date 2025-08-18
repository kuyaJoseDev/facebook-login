<?php
session_start();
include("connect.php");
header('Content-Type: application/json');

$loggedInUser = $_SESSION['user_id'] ?? null;
$profileUser = $_GET['user_id'] ?? null;

if (!$loggedInUser || !$profileUser) {
    echo json_encode(['status' => 'error']);
    exit;
}

// Check if already friends
$stmt = $conn->prepare("SELECT 1 FROM friends WHERE (user1_id=? AND user2_id=?) OR (user1_id=? AND user2_id=?)");
$stmt->bind_param("iiii", $loggedInUser, $profileUser, $profileUser, $loggedInUser);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['status' => 'friends']);
    exit;
}

// Check if pending request exists
$stmt = $conn->prepare("SELECT 1 FROM friend_requests WHERE ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) AND status='pending'");
$stmt->bind_param("iiii", $loggedInUser, $profileUser, $profileUser, $loggedInUser);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['status' => 'pending']);
    exit;
}

// Otherwise, can send request
echo json_encode(['status' => 'none']);
?>
