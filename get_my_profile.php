<?php
session_start();
include("connect.php");

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'id' => null,
    'name' => null,
    'avatar' => 'default-avatar.png'
];

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in';
    echo json_encode($response);
    exit;
}

$currentUserId = intval($_SESSION['user_id']);

// Fetch user info
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

// Success
$response['success'] = true;
$response['id']      = (int)$user['id'];
$response['name']    = $user['name'];
$response['avatar'] = $user['avatar_path'] ?: 'default-avatar.png';

echo json_encode($response);
