<?php
session_start();
include("connect.php");

header('Content-Type: application/json');
$conn->set_charset("utf8mb4"); // ensures emojis and special chars work

$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
if (!$post_id) {
    echo json_encode(["success" => false, "comments" => [], "message" => "Invalid post ID"]);
    exit;
}

// Fetch all comments for the post
$stmt = $conn->prepare("
    SELECT c.id, c.post_id, c.user_id, c.parent_id, c.content, c.created_at,
           u.name AS user_name
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.post_id = ?
    ORDER BY c.created_at ASC
");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();
$comments = $result->fetch_all(MYSQLI_ASSOC);

// Format created_at for each comment
foreach ($comments as &$c) {
    $c['created_at'] = date('c', strtotime($c['created_at'])); // ISO 8601 format
}
unset($c);

// Build nested comment tree
function buildTree($comments, $parentId = 0) {
    $tree = [];
    foreach ($comments as $comment) {
        if ((int)$comment['parent_id'] === $parentId) {
            $children = buildTree($comments, (int)$comment['id']);
            $comment['replies'] = $children; // always include replies
            $tree[] = $comment;
        }
    }
    return $tree;
}

$commentTree = buildTree($comments);

// Send JSON response including server time
echo json_encode([
    "success" => true,
    "server_time" => date('c'), // ISO 8601 format
    "comments" => $commentTree
]);
