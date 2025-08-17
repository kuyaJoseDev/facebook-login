<?php
session_start();
include("connect.php");

header('Content-Type: application/json');

// --- Security: must be logged in ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'messages' => [],
        'error' => 'Unauthorized'
    ]);
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];
$chatUserId    = (int) ($_GET['user_id'] ?? 0);

// --- Validate chat target ---
if ($chatUserId <= 0) {
    echo json_encode([
        'success' => false,
        'messages' => [],
        'error' => 'Invalid chat user'
    ]);
    exit;
}

// --- Pagination (for infinite scroll) ---
$limit  = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 50; 
$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

// --- Fetch messages (newest first, then reversed for display) ---
$query = "
    SELECT pm.*, u.name AS sender_name, u.avatar_path AS sender_avatar
    FROM private_messages pm
    JOIN users u ON pm.sender_id = u.id
    WHERE (pm.sender_id = ? AND pm.receiver_id = ?)
       OR (pm.sender_id = ? AND pm.receiver_id = ?)
    ORDER BY pm.created_at DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param(
    "iiiiii", 
    $currentUserId, $chatUserId, 
    $chatUserId, $currentUserId, 
    $offset, $limit
);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        "id"            => (int) $row['id'],
        "sender_id"     => (int) $row['sender_id'],
        "receiver_id"   => (int) $row['receiver_id'],
        "sender_name"   => $row['sender_name'],
        "sender_avatar" => $row['sender_avatar'] ?: 'default-avatar.png', // ✅ Add this
        "message"       => $row['message'],
        "created_at"    => $row['created_at'],
        "media_path"    => $row['media_path'] ?: null,
        "media_type"    => $row['media_type'] ?: null,
        "is_read"       => (int) $row['is_read']
    ];
}

// Reverse messages to show oldest → newest
$messages = array_reverse($messages);

echo json_encode([
    'success'  => true,
    'messages' => $messages,
    'count'    => count($messages)
]);
