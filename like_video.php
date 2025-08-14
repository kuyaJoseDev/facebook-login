<?php
session_start();
include("connect.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$post_id = $data['post_id'];
$user_id = $_SESSION['user_id'];

// Check if user already liked
$check = $conn->prepare("SELECT id FROM likes WHERE post_id = ? AND user_id = ?");
$check->bind_param("ii", $post_id, $user_id);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    // Already liked → unlike
    $delete = $conn->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?");
    $delete->bind_param("ii", $post_id, $user_id);
    $delete->execute();
    $action = 'unliked';
} else {
    // Not liked yet → like
    $insert = $conn->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)");
    $insert->bind_param("ii", $post_id, $user_id);
    $insert->execute();
    $action = 'liked';
}

// Count total likes
$count = $conn->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ?");
$count->bind_param("i", $post_id);
$count->execute();
$count->bind_result($likes_count);
$count->fetch();
$count->close();

echo json_encode(['success' => true, 'action' => $action, 'likes_count' => $likes_count]);
?>
