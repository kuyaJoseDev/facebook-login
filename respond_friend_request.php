<?php
session_start();
include("connect.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $request_id = $_POST['request_id'] ?? 0;
    $action = $_POST['action'] ?? '';

    if (!in_array($action, ['accept', 'decline'])) {
        die("Invalid action.");
    }

    $status = ($action === 'accept') ? 'accepted' : 'declined';

    $stmt = $conn->prepare("UPDATE friend_requests SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $request_id);
    $stmt->execute();

    // ✅ Redirect to home page after action
    header("Location: LeagueBook.php?friend_status=$status");
    
    exit();
}
?>
