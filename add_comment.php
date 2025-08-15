<?php
session_start();
include("connect.php");

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'], $_POST['content'])) {
    $post_id = intval($_POST['post_id']);
    $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    $content = trim($_POST['content']);
    $user_id = $_SESSION['user_id'];

    if ($content === '') {
        echo json_encode(["status" => "error", "message" => "Empty comment"]);
        exit;
    }

    // Insert comment into DB
    $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, parent_id, content, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiis", $post_id, $user_id, $parent_id, $content);

    if ($stmt->execute()) {
        // Fetch username for immediate display
        $sql = "SELECT COALESCE(username, name, full_name, 'Anonymous') AS user_name FROM users WHERE id = ?";
        $u_stmt = $conn->prepare($sql);
        $u_stmt->bind_param("i", $user_id);
        $u_stmt->execute();
        $u_result = $u_stmt->get_result();
        $user = $u_result->fetch_assoc();

        echo json_encode([
            "status" => "success",
            "id" => $stmt->insert_id,
            "user_name" => $user['user_name'],
            "content" => $content,
            "created_at" => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Insert failed"]);
    }
}
?>
