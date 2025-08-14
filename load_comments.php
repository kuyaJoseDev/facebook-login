<?php
// Enable full error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include your database connection
include("connect.php");

// Set response type to JSON
header('Content-Type: application/json');

// Check if post_id is provided
if (!isset($_GET['post_id'])) {
    echo json_encode([]);
    exit;
}

$post_id = intval($_GET['post_id']);

// Fetch comments with user names (or 'Anonymous' if user missing)
$stmt = $conn->prepare("
    SELECT c.content, COALESCE(u.user_name, 'Anonymous') AS user_name
    FROM comments c
    LEFT JOIN users u ON u.id = c.user_id
    WHERE c.post_id = ?
    ORDER BY c.created_at ASC
");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();

// Collect comments
$comments = [];
while ($row = $result->fetch_assoc()) {
    $comments[] = $row;
}

// Output JSON
echo json_encode($comments);
