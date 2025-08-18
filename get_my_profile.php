<?php
session_start();
include("connect.php");

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'id'      => null,
    'name'    => null,
    'avatar'  => 'uploads/default-avatar.png'
];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in';
    echo json_encode($response);
    exit;
}

$currentUserId = intval($_SESSION['user_id']);

$stmt = $conn->prepare("SELECT id, name, avatar_path FROM users WHERE id = ?");
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    $response['message'] = 'User not found';
    echo json_encode($response);
    exit;
}

$response['success'] = true;
$response['id']      = (int)$user['id'];
$response['name']    = $user['name'];
$response['avatar']  = !empty($user['avatar_path']) ? $user['avatar_path'] : 'uploads/default-avatar.png';

echo json_encode($response);
