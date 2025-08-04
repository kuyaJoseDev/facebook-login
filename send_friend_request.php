<?php
session_start();
include("connect.php");

$senderId = $_SESSION['user_id'] ?? null;
$receiverId = $_POST['receiver_id'] ?? null; // not $_GET

if (!$senderId || !$receiverId) {
    die("❌ Cannot send request: missing user ID.");
}

// Prevent sending request to self
if ($senderId == $receiverId) {
    die("❌ Cannot send a request to yourself.");
}

// Insert into friend_requests table
$stmt = $conn->prepare("INSERT INTO friend_requests (sender_id, receiver_id, status) VALUES (?, ?, 'pending')");
$stmt->bind_param("ii", $senderId, $receiverId);
$stmt->execute();

echo "✅ Friend request sent!";
?>
