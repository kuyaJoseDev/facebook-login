<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit("Unauthorized");
}

$currentUserId = $_SESSION['user_id'];
$chatUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = 10;

// Validate user_id
if ($chatUserId <= 0) {
    http_response_code(400);
    exit("Invalid user ID.");
}

$stmt = $conn->prepare("
    SELECT pm.*, u.name AS sender_name 
    FROM private_messages pm 
    JOIN users u ON pm.sender_id = u.id 
    WHERE (pm.sender_id = ? AND pm.receiver_id = ?) 
       OR (pm.sender_id = ? AND pm.receiver_id = ?)
    ORDER BY pm.created_at DESC
    LIMIT ? OFFSET ?
");

$stmt->bind_param("iiiiii", $currentUserId, $chatUserId, $chatUserId, $currentUserId, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'sender_id'    => (int)$row['sender_id'],
        'sender_name'  => $row['sender_name'],
        'message'      => $row['message'],
        'created_at'   => $row['created_at'],
        'media_path'   => $row['media_path'],
        'media_type'   => $row['media_type']
    ];
}

header('Content-Type: application/json');
echo json_encode($messages);
