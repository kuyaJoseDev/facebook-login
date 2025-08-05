<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: LeagueBook.php");
    exit();
}

$currentUserId = $_SESSION['user_id'];
$chatUserId = $_GET['user_id'] ?? null;

if (!filter_var($chatUserId, FILTER_VALIDATE_INT)) {
    header("Location: inbox.php");
    exit();
}

function getStatusToken($last_active) {
    $threshold = strtotime('-5 minutes');
    $lastActiveTime = strtotime($last_active);
    return $lastActiveTime > $threshold ? "🟢 Online" : "⚫ Offline";
}

$statusStmt = $conn->prepare("SELECT name, last_active FROM users WHERE id = ?");
$statusStmt->bind_param("i", $chatUserId);
$statusStmt->execute();
$statusResult = $statusStmt->get_result();

if ($statusResult->num_rows === 0) {
    die("User not found.");
}

$chatUser = $statusResult->fetch_assoc();

$markRead = $conn->prepare("UPDATE private_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
$markRead->bind_param("ii", $chatUserId, $currentUserId);
$markRead->execute();

$unreadQuery = $conn->prepare("SELECT COUNT(*) as unread_count FROM private_messages WHERE receiver_id = ? AND is_read = 0");
$unreadQuery->bind_param("i", $currentUserId);
$unreadQuery->execute();
$unreadResult = $unreadQuery->get_result()->fetch_assoc();
$unreadCount = $unreadResult['unread_count'] ?? 0;

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM private_messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
$countStmt->bind_param("iiii", $currentUserId, $chatUserId, $chatUserId, $currentUserId);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalMessages = $countResult->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>📨 Private Messages</title>
    <link rel="stylesheet" href="LeagueBook_Page.css">
    <style>
        .chat-box {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ccc;
            padding: 15px;
            background: #fff;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .chat-message {
            margin-bottom: 12px;
        }
        .chat-message strong {
            color: #1f2937;
        }
        .chat-message small {
            color: #6b7280;
            font-size: 0.8em;
        }
        .chat-form textarea {
            width: 100%;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ccc;
            resize: vertical;
        }
        .chat-form button,
        .chat-form input[type="file"] {
            margin-top: 8px;
        }
        #loadMoreBtn {
            display: block;
            margin: 0 auto 20px;
        }
    </style>
</head>
<body>
<div class="main-container">
    <h2>📨 Private Messages with <?= htmlspecialchars($chatUser['name']) ?> - <?= getStatusToken($chatUser['last_active']) ?></h2>
    <?php if ($totalMessages > 10): ?>
        <button id="loadMoreBtn" class="animated-button">🔼 Show More</button>
    <?php endif; ?>
    <div id="messageContainer" class="chat-box"></div>

    <script>
    let offset = 0;
    const limit = 10;
    const userId = <?= $chatUserId ?>;
    let loading = false;

    function loadMessages(initial = false) {
        if (loading) return;
        loading = true;

        fetch(`load_messages.php?user_id=${userId}&offset=${offset}`)
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) return;

                const container = document.getElementById("messageContainer");
                const shouldScroll = initial;

                data.forEach(msg => {
                    const div = document.createElement("div");
                    div.className = "chat-message";
                    div.innerHTML = `
                        <strong>${msg.sender_name}:</strong>
                        ${msg.message.replace(/\n/g, "<br>")}
                        <br><small>${msg.created_at}</small>
                        ${msg.media_path && msg.media_type === 'image' ? `<br><img src="${msg.media_path}" style="max-width:200px;">` : ''}
                        ${msg.media_path && msg.media_type === 'video' ? `
                            <br><video controls style="max-width:300px;">
                                <source src="${msg.media_path}" type="video/mp4">
                            </video>` : ''}
                    `;
                    container.prepend(div);
                });

                offset += limit;
                loading = false;

                if (shouldScroll) {
                    container.scrollTop = container.scrollHeight;
                }
            });
    }

    loadMessages(true);

    document.getElementById("messageContainer").addEventListener("scroll", function() {
        if (this.scrollTop === 0 && offset < <?= $totalMessages ?>) {
            loadMessages();
        }
    });
    </script>

    <form action="send_message.php" method="POST" enctype="multipart/form-data" class="chat-form">
        <input type="hidden" name="receiver_id" value="<?= htmlspecialchars($chatUserId) ?>">
        <textarea name="message" rows="3" required placeholder="Type your reply..."></textarea>
        <input type="file" name="media" accept="image/*,video/*"><br>
        <button type="submit" class="btn btn-message">Send</button>
    </form>

    <br>
    <a href="inbox.php" class="button">📥 Back to Inbox</a>
    <a href="LeagueBook_Page.php" class="button">🏠 Back to Main</a>
</div>
</body>
</html>
