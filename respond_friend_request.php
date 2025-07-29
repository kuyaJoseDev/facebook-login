<?php
session_start();
include("connect.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'accept') {
        // Get sender and receiver
        $stmt = $conn->prepare("SELECT sender_id, receiver_id FROM friend_requests WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row) {
            $sender = $row['sender_id'];
            $receiver = $row['receiver_id'];

            // Add to friends table
            $insert = $conn->prepare("INSERT INTO friends (user1_id, user2_id) VALUES (?, ?)");
            $insert->bind_param("ii", $sender, $receiver);
            $insert->execute();

            // Update friend request
            $update = $conn->prepare("UPDATE friend_requests SET status = 'accepted' WHERE id = ?");
            $update->bind_param("i", $request_id);
            $update->execute();
        }
    } elseif ($action === 'decline') {
        $update = $conn->prepare("UPDATE friend_requests SET status = 'declined' WHERE id = ?");
        $update->bind_param("i", $request_id);
        $update->execute();
    }
}

header("Location: view_friend_request.php");
exit();
?>
