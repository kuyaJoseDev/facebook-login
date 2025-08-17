<?php
session_start();
include("connect.php");

$userId = $_SESSION['user_id'] ?? 0;

$stmt = $conn->prepare("
    SELECT SUM(CASE WHEN receiver_id = ? AND is_read = 0 THEN 1 ELSE 0 END) AS total_unread
    FROM private_messages
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
echo json_encode(['total_unread' => $result['total_unread'] ?? 0]);
