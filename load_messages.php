<?php
session_start();
include("connect.php");

$currentUserId = $_SESSION['user_id'];
$chatUserId = $_GET['user_id'] ?? 0;
$offset = $_GET['offset'] ?? 0;

$stmt = $conn->prepare("
    SELECT pm.message, pm.created_at, pm.media_path, pm.media_type, u.name AS sender_name
    FROM private_messages pm
    JOIN users u ON u.id = pm.sender_id
    WHERE (pm.sender_id = ? AND pm.receiver_id = ?)
       OR (pm.sender_id = ? AND pm.receiver_id = ?)
    ORDER BY pm.created_at DESC
    LIMIT 10 OFFSET ?
");
$stmt->bind_param("iiiii", $currentUserId, $chatUserId, $chatUserId, $currentUserId, $offset);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode($messages);
