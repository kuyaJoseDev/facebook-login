<?php
session_start();
include("connect.php");

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: LeagueBook.php");
    exit();
}

$currentUserId = $_SESSION['user_id'];

// Define online status checker
function getStatusToken($last_active) {
    $threshold = strtotime('-5 minutes');
    $lastActiveTime = strtotime($last_active);
    return $lastActiveTime > $threshold ? "🟢 Online" : "⚫ Offline";
}

// Fetch conversations with unread count and last active
$query = $conn->prepare("
    SELECT u.id, u.name, u.last_active, MAX(pm.created_at) AS last_message,
           SUM(CASE WHEN pm.receiver_id = ? AND pm.is_read = 0 THEN 1 ELSE 0 END) AS unread_count
    FROM private_messages pm
    JOIN users u ON (
        (u.id = pm.sender_id AND pm.receiver_id = ?) OR 
        (u.id = pm.receiver_id AND pm.sender_id = ?)
    )
    WHERE u.id != ?
    GROUP BY u.id, u.name, u.last_active
    ORDER BY last_message DESC
");
$query->bind_param("iiii", $currentUserId, $currentUserId, $currentUserId, $currentUserId);
$query->execute();
$conversations = $query->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>📨 Your Inbox</title>
    <link rel="stylesheet" href="LeagueBook_Page.css">
</head>
<body>
<div class="main-container">
    <h2>📨 Your Conversations</h2>

    <?php if ($conversations->num_rows === 0): ?>
        <p>No private messages yet.</p>
    <?php else: ?>
        <ul>
            <?php while ($user = $conversations->fetch_assoc()): ?>
                <li style="margin-bottom: 12px;">
                    <a href="messages.php?user_id=<?= $user['id'] ?>" class="button">
                        💬 Chat with <?= htmlspecialchars($user['name']) ?> - <?= getStatusToken($user['last_active']) ?>
                        <?php if ($user['unread_count'] > 0): ?>
                            <span class="badge" style="color: black;">🔴 <?= $user['unread_count'] ?> New</span>
                        <?php endif; ?>
                    </a><br>
                    <small>Last message: <?= htmlspecialchars($user['last_message']) ?></small>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php endif; ?>

    <br>
    <a href="LeagueBook_Page.php" class="button">🏠 Back to Main</a>
</div>
</body>
</html>
