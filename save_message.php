<?php
session_start();
include("connect.php");

header('Content-Type: application/json');

$response = ['success' => false, 'message' => null];

// --- Decode JSON input ---
$input = json_decode(file_get_contents("php://input"), true);

if (!isset($_SESSION['user_id'], $input['receiver_id'])) {
    echo json_encode($response);
    exit;
}

$sender_id   = $_SESSION['user_id'];
$receiver_id = intval($input['receiver_id']);
$message     = trim($input['message'] ?? '');

$media_path  = null;
$media_type  = null;

// --- Prevent empty messages ---
if ($message === '' && empty($_FILES['media']['name'])) {
    echo json_encode($response);
    exit;
}

// --- Handle media upload ---
if (!empty($_FILES['media']['name']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/messages/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $fileTmp  = $_FILES['media']['tmp_name'];
    $fileName = basename($_FILES['media']['name']);
    $safeFileName = preg_replace("/[^a-zA-Z0-9_\.-]/", "_", $fileName);
    $media_path = $uploadDir . time() . "_" . $safeFileName;

    $ext = strtolower(pathinfo($media_path, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','gif'])) $media_type = 'image';
    elseif (in_array($ext, ['mp4','webm','ogg'])) $media_type = 'video';
    else $media_path = $media_type = null;

    if ($media_path && !move_uploaded_file($fileTmp, $media_path)) {
        $media_path = $media_type = null;
    }
}

// --- Insert into database ---
$stmt = $conn->prepare("
    INSERT INTO private_messages (sender_id, receiver_id, message, media_path, media_type) 
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("iisss", $sender_id, $receiver_id, $message, $media_path, $media_type);

if ($stmt->execute()) {
    $msg_id = $conn->insert_id;

    // Fetch full row with sender name
    $msgRowStmt = $conn->prepare("
        SELECT pm.*, u.name AS sender_name
        FROM private_messages pm
        JOIN users u ON pm.sender_id = u.id
        WHERE pm.id = ?
    ");
    $msgRowStmt->bind_param("i", $msg_id);
    $msgRowStmt->execute();
    $msgRow = $msgRowStmt->get_result()->fetch_assoc();

    if ($msgRow) {
        $response['success'] = true;
        $response['message'] = [
            'id'          => (int)$msgRow['id'],
            'sender_id'   => (int)$msgRow['sender_id'],
            'receiver_id' => (int)$msgRow['receiver_id'],
            'sender_name' => $msgRow['sender_name'],
            'message'     => $msgRow['message'],
            'created_at'  => $msgRow['created_at'],
            'media_path'  => $msgRow['media_path'],
            'media_type'  => $msgRow['media_type']
        ];
    }
}

echo json_encode($response);
