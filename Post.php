<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: LeagueBook.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$content = $_POST['content'] ?? '';
$imagePath = '';
$videoPath = '';

// Create upload folders if they don't exist
if (!is_dir('uploads/images')) mkdir('uploads/images', 0755, true);
if (!is_dir('uploads/videos')) mkdir('uploads/videos', 0755, true);

// Handle image upload
if (!empty($_FILES['image']['name'])) {
    $imageTmp = $_FILES['image']['tmp_name'];
    $imageName = uniqid('img_', true) . '_' . basename($_FILES['image']['name']);
    $targetImage = "uploads/images/" . $imageName;

    $imageType = mime_content_type($imageTmp);
    if (str_starts_with($imageType, 'image/')) {
        move_uploaded_file($imageTmp, $targetImage);
        $imagePath = $targetImage;
    }
}

// Handle video upload
if (!empty($_FILES['video']['name'])) {
    $videoTmp = $_FILES['video']['tmp_name'];
    $videoName = uniqid('vid_', true) . '_' . basename($_FILES['video']['name']);
    $targetVideo = "uploads/videos/" . $videoName;

    $videoType = mime_content_type($videoTmp);
    if (str_starts_with($videoType, 'video/')) {
        move_uploaded_file($videoTmp, $targetVideo);
        $videoPath = $targetVideo;
    }
}

// Insert into database
$stmt = $conn->prepare("INSERT INTO posts (user_id, content, image_path, video_path, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("isss", $user_id, $content, $imagePath, $videoPath);
$stmt->execute();

// Redirect back
header("Location: LeagueBook_Page.php");
exit();
?>
