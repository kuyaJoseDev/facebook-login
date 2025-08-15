<?php
session_start();
include "connect.php"; // Make sure $conn is defined in connect.php

if (!isset($_GET['post_id'])) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

$post_id = intval($_GET['post_id']);

// Adjusted SQL to match your users table
$sql = "
    SELECT c.content, u.name AS user_name
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.post_id = ?
    ORDER BY c.created_at ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();

$comments = [];
while ($row = $result->fetch_assoc()) {
    $comments[] = $row;
}

header('Content-Type: application/json');
echo json_encode($comments);
exit;
