<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("connect.php");
header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

$post_id = intval($data['post_id'] ?? 0);
$comment = trim($data['comment'] ?? '');
$user_id = 1; // Replace with actual logged-in user ID from session

if (!$post_id || !$comment) {
    echo json_encode(['success' => false, 'message' => 'Missing post_id or comment']);
    exit;
}

// Insert comment
$stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iis", $post_id, $user_id, $comment);
if ($stmt->execute()) {
    // Count total comments for this post
    $countRes = $conn->prepare("SELECT COUNT(*) AS total FROM comments WHERE post_id = ?");
    $countRes->bind_param("i", $post_id);
    $countRes->execute();
    $totalComments = $countRes->get_result()->fetch_assoc()['total'];

    echo json_encode(['success' => true, 'comments_count' => $totalComments]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to insert comment']);
}
?>
