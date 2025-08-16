<?php
session_start();
include("connect.php");

header('Content-Type: application/json');

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

// Build nested comment tree
function buildTree($comments, $parentId = 0) {
    $tree = [];
    foreach ($comments as $comment) {
        if ((int)$comment['parent_id'] === $parentId) {
            $children = buildTree($comments, (int)$comment['id']);
            if ($children) $comment['replies'] = $children;
            $tree[] = $comment;
        }
    }
    return $tree;
}

$commentTree = buildTree($comments);

echo json_encode(["success" => true, "comments" => $commentTree]);
