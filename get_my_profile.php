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

$stmt = $conn->prepare("SELECT id, username, name, avatar_path FROM users WHERE id = ?");
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    $response['message'] = 'User not found';
    echo json_encode($response);
    exit;
}

// prefer "name" if available, else fallback to "username"
$displayName = !empty($user['name']) ? $user['name'] : $user['username'];

// keep session in sync
$_SESSION['username'] = $displayName;

$response['success'] = true;
$response['id']      = (int)$user['id'];
$response['name']    = $displayName;
$response['avatar']  = !empty($user['avatar_path']) ? $user['avatar_path'] : 'uploads/default-avatar.png';

echo json_encode($response);
