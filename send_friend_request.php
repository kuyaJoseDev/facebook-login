<?php
session_start();
include("connect.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $sender_id = $_SESSION['user_id'];
    $receiver_id = $_POST['receiver_id'];

    if ($sender_id == $receiver_id) {
        die("You can't add yourself.");
    }

    // Check if request already exists
    $check = $conn->prepare("SELECT * FROM friend_requests WHERE sender_id = ? AND receiver_id = ?");
    $check->bind_param("ii", $sender_id, $receiver_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        die("Friend request already sent.");
    }

    $stmt = $conn->prepare("INSERT INTO friend_requests (sender_id, receiver_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $sender_id, $receiver_id);
    $stmt->execute();

    echo "✅ Friend request sent!";
}
?>
