<?php
// Example: load_messages.php
session_start();
include("connect.php");

$currentUserId = $_SESSION['user_id'];
$chatUserId = intval($_GET['user_id'] ?? 0);
$offset = intval($_GET['offset'] ?? 0);
$limit = 10;

$stmt = $conn->prepare("SELECT pm.*, u.name AS sender_name FROM private_messages pm
                        JOIN users u ON pm.sender_id = u.id
                        WHERE (pm.sender_id = ? AND pm.receiver_id = ?) OR (pm.sender_id = ? AND pm.receiver_id = ?)
                        ORDER BY pm.created_at ASC
                        LIMIT ?, ?");
$stmt->bind_param("iiiiii", $currentUserId, $chatUserId, $chatUserId, $currentUserId, $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while($row = $result->fetch_assoc()){
    $messages[] = $row;
}

echo json_encode($messages);
