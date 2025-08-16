<?php
session_start();
include("connect.php");

// Ensure user is logged in
if(!isset($_SESSION['user_id'])){
    echo json_encode(['success'=>false,'message'=>'Not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];
$uploadDir = 'uploads/'; // Make sure this folder exists and is writable

// ----------------------
// Upload Avatar
// ----------------------
if(isset($_FILES['avatar'])){
    $file = $_FILES['avatar'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $uploadDir . "avatar_{$userId}." . $ext;

    if(move_uploaded_file($file['tmp_name'], $filename)){
        $stmt = $conn->prepare("UPDATE users SET avatar=? WHERE id=?");
        $stmt->bind_param("si", $filename, $userId);
        $stmt->execute();

        echo json_encode(['success'=>true, 'avatar'=>$filename]);
    } else {
        echo json_encode(['success'=>false, 'message'=>'Failed to upload avatar.']);
    }
    exit;
}

// ----------------------
// Upload Cover Photo
// ----------------------
if(isset($_FILES['cover_photo'])){
    $file = $_FILES['cover_photo'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $uploadDir . "cover_{$userId}." . $ext;

    if(move_uploaded_file($file['tmp_name'], $filename)){
        $stmt = $conn->prepare("UPDATE users SET cover_photo=? WHERE id=?");
        $stmt->bind_param("si", $filename, $userId);
        $stmt->execute();

        echo json_encode(['success'=>true, 'cover_photo'=>$filename]);
    } else {
        echo json_encode(['success'=>false, 'message'=>'Failed to upload cover photo.']);
    }
    exit;
}

// If no file sent
echo json_encode(['success'=>false,'message'=>'No file uploaded']);
?>
