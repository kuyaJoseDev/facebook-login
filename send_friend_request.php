<?php
session_start();
include("connect.php");

header('Content-Type: application/json');

$senderId = $_SESSION['user_id'] ?? null;
$receiverId = $_POST['receiver_id'] ?? null;

if (!$senderId || !$receiverId) {
    echo json_encode([
        'success' => false,
        'message' => '❌ Cannot send request: missing user ID.'
    ]);
    exit;
}

// Prevent sending request to self
if ($senderId == $receiverId) {
    echo json_encode([
        'success' => false,
        'message' => '❌ Cannot send a request to yourself.'
    ]);
    exit;
}

// Check if already friends
$stmt = $conn->prepare("SELECT 1 FROM friends WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
$stmt->bind_param("iiii", $senderId, $receiverId, $receiverId, $senderId);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode([
        'success' => false,
        'message' => '✅ You are already friends.'
    ]);
    exit;
}

// Check if a pending request already exists
$stmt = $conn->prepare("SELECT 1 FROM friend_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) AND status = 'pending'");
$stmt->bind_param("iiii", $senderId, $receiverId, $receiverId, $senderId);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode([
        'success' => false,
        'message' => '⏳ Friend request already pending.'
    ]);
    exit;
}

// Insert friend request
$stmt = $conn->prepare("INSERT INTO friend_requests (sender_id, receiver_id, status) VALUES (?, ?, 'pending')");
$stmt->bind_param("ii", $senderId, $receiverId);
if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => '✅ Friend request sent!'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => '❌ Failed to send request.'
    ]);
}
?>
