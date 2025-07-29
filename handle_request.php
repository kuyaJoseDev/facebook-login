<?php
session_start();
include("connect.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $user_id = $_SESSION['user_id'];

    // Get friend request
    $stmt = $conn->prepare("SELECT * FROM friend_requests WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();

    if (!$request || $request['receiver_id'] != $user_id) {
        echo "Unauthorized request.";
        exit();
    }

    if ($action === 'accept') {
        // Accept request
        $update = $conn->prepare("UPDATE friend_requests SET status = 'accepted' WHERE id = ?");
        $update->bind_param("i", $request_id);
        $update->execute();

        // Add to friends table (both directions)
        $add1 = $conn->prepare("INSERT INTO friends (user_id, friend_id) VALUES (?, ?)");
        $add1->bind_param("ii", $request['sender_id'], $user_id);
        $add1->execute();

        $add2 = $conn->prepare("INSERT INTO friends (user_id, friend_id) VALUES (?, ?)");
        $add2->bind_param("ii", $user_id, $request['sender_id']);
        $add2->execute();

        header("Location: view_friend_requests.php?accepted=1");
        exit();
    } elseif ($action === 'decline') {
        // Decline request
        $update = $conn->prepare("UPDATE friend_requests SET status = 'declined' WHERE id = ?");
        $update->bind_param("i", $request_id);
        $update->execute();

        header("Location: view_friend_requests.php?declined=1");
        exit();
    }
}
?>
