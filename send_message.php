<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: LeagueBook.php");
    exit();
}

$senderId = $_SESSION['user_id'];
$receiverId = $_POST['receiver_id'] ?? null;
$message = trim($_POST['message'] ?? '');

if (!filter_var($receiverId, FILTER_VALIDATE_INT) || empty($message)) {
    redirectBackWith('error=invalid_message');
}

// 🖼️ Handle optional file upload
$mediaPath = null;
$mediaType = null;

if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/messages/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $fileTmp = $_FILES['media']['tmp_name'];
    $fileName = basename($_FILES['media']['name']);
    $targetPath = $uploadDir . time() . "_" . $fileName;

    $ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        $mediaType = 'image';
    } elseif (in_array($ext, ['mp4', 'webm', 'ogg'])) {
        $mediaType = 'video';
    }

    if ($mediaType && move_uploaded_file($fileTmp, $targetPath)) {
        $mediaPath = $targetPath;
    }
}

// 💬 Insert message with optional media
$stmt = $conn->prepare("INSERT INTO private_messages (sender_id, receiver_id, message, media_path, media_type) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iisss", $senderId, $receiverId, $message, $mediaPath, $mediaType);

if ($stmt->execute()) {
    redirectBackWith('success=message_sent');
} else {
    redirectBackWith('error=message_failed');
}

function redirectBackWith($query) {
    $referer = $_SERVER['HTTP_REFERER'] ?? 'LeagueBook_Page.php';
    $sep = (strpos($referer, '?') !== false) ? '&' : '?';
    header("Location: {$referer}{$sep}{$query}");
    exit();
}


$response = ['success' => false];

if (!isset($_SESSION['user_id']) || !isset($_POST['receiver_id'])) {
    echo json_encode($response);
    exit;
}

$sender_id = $_SESSION['user_id'];
$receiver_id = $_POST['receiver_id'];

if (isset($_FILES['media']) && $_FILES['media']['error'] == 0) {
    $file = $_FILES['media'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = "uploads/" . uniqid() . "." . $ext;
    
    if (move_uploaded_file($file['tmp_name'], $filename)) {
        $media_type = (strpos($file['type'], 'image') !== false) ? 'image' : 'video';
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, media_url, media_type, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiss", $sender_id, $receiver_id, $filename, $media_type);
        $stmt->execute();

        $response['success'] = true;
        $response['file_url'] = $filename;
        $response['media_type'] = $media_type;
    }
}

echo json_encode($response);
?>
