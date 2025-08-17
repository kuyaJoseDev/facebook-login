<?php
session_start();
include("connect.php");

$currentUserId = $_SESSION['user_id'] ?? 0;

// Fetch the last user who sent or received a message with current user
$stmt = $conn->prepare("
    SELECT 
        CASE 
            WHEN sender_id = ? THEN receiver_id
            ELSE sender_id
        END AS user_id,
        u.name AS user_name
    FROM private_messages pm
    JOIN users u ON u.id = CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END
    WHERE sender_id = ? OR receiver_id = ?
    ORDER BY pm.created_at DESC
    LIMIT 1
");
$stmt->bind_param("iiii", $currentUserId, $currentUserId, $currentUserId, $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

header('Content-Type: application/json');
echo json_encode($user ?? []);
