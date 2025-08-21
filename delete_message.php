<?php
session_start();
include "connect.php";

header('Content-Type: application/json');

// --- Security: must be logged in ---
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$message_id = isset($data['message_id']) ? (int)$data['message_id'] : 0;

if ($message_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid message ID"]);
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];

// --- Check ownership ---
$stmt = $conn->prepare("SELECT sender_id, receiver_id FROM private_messages WHERE id = ?");
$stmt->bind_param("i", $message_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(["success" => false, "message" => "Message not found"]);
    exit;
}

if ((int)$row['sender_id'] !== $currentUserId) {
    echo json_encode(["success" => false, "message" => "You can only delete your own messages"]);
    exit;
}

$receiverId = (int)$row['receiver_id'];

// --- Delete the message ---
$del = $conn->prepare("DELETE FROM private_messages WHERE id = ? AND sender_id = ?");
$del->bind_param("ii", $message_id, $currentUserId);
$del->execute();

if ($del->affected_rows > 0) {
    echo json_encode(["success" => true, "message" => "Message deleted"]);

    // --- Notify WebSocket server for real-time deletion ---
    // Make sure your JS WebSocket is listening for type="delete_message"
    $payload = [
        "type"       => "delete_message",
        "message_id" => $message_id,
        "sender_id"  => $currentUserId,
        "receiver_id"=> $receiverId
    ];

    // If using a PHP WebSocket bridge endpoint
    $wsBridge = "http://localhost:8080"; // Replace with actual WebSocket bridge endpoint
    $ch = curl_init($wsBridge);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);

} else {
    echo json_encode(["success" => false, "message" => "Delete failed"]);
}

$del->close();
$conn->close();
