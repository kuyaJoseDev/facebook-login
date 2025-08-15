<?php
session_start();
header('Content-Type: application/json');
include "connect.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$post_id   = intval($data['post_id'] ?? 0);
$comment   = trim($data['comment'] ?? '');
$parent_id = intval($data['parent_id'] ?? 0);
$stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content, parent_id, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("iiis", $post_id, $user_id, $comment, $parent_id);


if (!$post_id || !$comment) {
    echo json_encode(['success' => false, 'message' => 'Missing post_id or comment']);
    exit;
}

// Insert comment
$stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content, parent_id, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("iiis", $post_id, $user_id, $comment, $parent_id);

if ($stmt->execute()) {
    $comment_id = $conn->insert_id;

    // Fetch the inserted comment with username
    $res = $conn->prepare("
        SELECT c.id, c.content, c.parent_id, u.name AS user_name
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    $res->bind_param("i", $comment_id);
    $res->execute();
    $newComment = $res->get_result()->fetch_assoc();
    $newComment['replies'] = []; // initialize replies array

    echo json_encode([
        'success' => true,
        'new_comment' => $newComment
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to insert comment']);
}
?>
