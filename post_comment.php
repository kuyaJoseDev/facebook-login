<?php
session_start();
include("connect.php");
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$post_id = intval($data['post_id'] ?? 0);
$parent_id = intval($data['parent_id'] ?? 0);
$content = trim($data['comment'] ?? '');
$user_id = $_SESSION['user_id'];

if (!$post_id || $content === '') {
    echo json_encode(["success" => false, "message" => "Missing post ID or empty comment"]);
    exit;
}

// Insert comment
$stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, parent_id, content, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("iiis", $post_id, $user_id, $parent_id, $content);

if ($stmt->execute()) {
    // Return inserted comment with username
    $stmt2 = $conn->prepare("SELECT name AS user_name FROM users WHERE id = ?");
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $res = $stmt2->get_result();
    $user = $res->fetch_assoc();

    echo json_encode([
        "success" => true,
        "new_comment" => [
            "id" => $stmt->insert_id,
            "post_id" => $post_id,
            "user_id" => $user_id,
            "parent_id" => $parent_id,
            "content" => $content,
            "user_name" => $user['user_name'],
            "replies" => []
        ]
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Insert failed"]);
}
