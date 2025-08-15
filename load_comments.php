<?php
session_start();
header('Content-Type: application/json');
include "connect.php";

try {
    if (!isset($_GET['post_id'])) {
        throw new Exception("Missing post_id");
    }

    $post_id = intval($_GET['post_id']);

    // Fetch all comments for this post
    $sql = "
        SELECT 
            c.id, 
            c.content, 
            c.parent_id, 
            c.user_id,
            u.name AS user_name
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.post_id = ?
        ORDER BY c.created_at ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Flatten all comments
    $comments_flat = [];
    while ($row = $result->fetch_assoc()) {
        $row['replies'] = []; // initialize replies array
        $comments_flat[] = $row;
    }

    // Build nested comment structure
    $comments_nested = [];
    $lookup = [];
    foreach ($comments_flat as &$c) {
        $lookup[$c['id']] = &$c; // use reference to update replies later
    }
    foreach ($comments_flat as &$c) {
        if ($c['parent_id'] == 0) {
            $comments_nested[] = $c;
        } elseif (isset($lookup[$c['parent_id']])) {
            $lookup[$c['parent_id']]['replies'][] = $c;
        }
    }

    echo json_encode([
        'success' => true,
        'comments' => $comments_nested
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit;
?>
