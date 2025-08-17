<?php
session_start();
include("connect.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

$currentUserId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$sender_id = intval($data['sender_id'] ?? 0);

if ($sender_id <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

// Mark all messages from sender as read
$update = $conn->prepare("
    UPDATE private_messages 
    SET is_read = 1 
    WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
");
$update->bind_param("ii", $sender_id, $currentUserId);
$update->execute();

echo json_encode(['success' => true]);
