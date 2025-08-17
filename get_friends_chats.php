<?php
session_start();
include("connect.php");

header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$currentUserId = $_SESSION['user_id'];

// Fetch friends who have active chats
$stmt = $conn->prepare("
    SELECT u.id AS user_id, u.name AS user_name, u.avatar,
        (SELECT COUNT(*) FROM private_messages pm 
         WHERE pm.sender_id=u.id AND pm.receiver_id=? AND pm.is_read=0) AS unread
    FROM users u
    WHERE u.id != ?
    ORDER BY u.name ASC
");
$stmt->bind_param("ii", $currentUserId, $currentUserId);
$stmt->execute();
$result = $stmt->get_result();

$friends = [];
while($row = $result->fetch_assoc()){
    $friends[] = $row;
}

echo json_encode($friends);
