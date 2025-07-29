<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: LeagueBook.php");
    exit();
}

$loggedInUserId = $_SESSION['user_id'];
$profileUserId = $_GET['id'] ?? null;

if (!is_numeric($profileUserId) || $profileUserId <= 0) {
    header("Location: LeagueBook_Page.php?error=invalid_profile");
    exit();
}

// Fetch profile user data
$stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $profileUserId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("User not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Profile - <?php echo htmlspecialchars($user['name']); ?></title>
    <link rel="stylesheet" href="LeagueBook_Page.css">
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('friend-request-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                const receiverId = form.querySelector('[name="receiver_id"]').value;

                fetch('send_friend_request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'receiver_id=' + encodeURIComponent(receiverId)
                })
                .then(res => res.json())
                .then(data => {
                    const status = document.getElementById('request-status');
                    status.textContent = data.message;
                    status.style.color = data.success ? 'green' : 'orange';

                    if (data.success || data.message.includes('already')) {
                        document.getElementById('send-request-btn').disabled = true;
                    }
                })
                .catch(() => {
                    const status = document.getElementById('request-status');
                    status.textContent = '❌ Something went wrong.';
                    status.style.color = 'red';
                });
            });
        }
    });
    // Already friends
$isFriend = $conn->prepare("SELECT * FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
$isFriend->bind_param("iiii", $myId, $otherId, $otherId, $myId);
$isFriend->execute();
$res = $isFriend->get_result();

if ($res->num_rows > 0) {
    // Show "Already Friends" or no button
}

    </script>
</head>
<body>
<div class="main-container">
    <?php if (isset($_GET['success'])): ?>
        <div style="color: green;">
            <?php
            if ($_GET['success'] === 'request_sent') {
                echo "✅ Friend request sent successfully.";
            } elseif ($_GET['success'] === 'already_sent') {
                echo "⚠️ You already sent a friend request to this user.";
            }
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'self_request'): ?>
        <div style="color: red;">
            ⚠️ You cannot send a friend request to yourself.
        </div>
    <?php endif; ?>

    <a href="LeagueBook_Page.php"><button class="button">🏠 Back to LeagueBook</button></a>

    <h2>👤 Profile: <?php echo htmlspecialchars($user['name']); ?></h2>
    <p>Email: <?php echo htmlspecialchars($user['email']); ?></p>

    <?php if ($loggedInUserId != $user['id']): ?>
        <!-- AJAX Friend Request Form -->
        <form id="friend-request-form" method="POST" style="margin-top: 10px;">
            <input type="hidden" name="receiver_id" value="<?php echo (int)$user['id']; ?>">
            <button type="submit" id="send-request-btn">➕ Add Friend</button>
        </form>
        <div id="request-status" style="margin-top: 5px;"></div>
    <?php endif; ?>

    <hr>
    <h3>📝 Posts by <?php echo htmlspecialchars($user['name']); ?>:</h3>

    <?php
    $pstmt = $conn->prepare("SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC");
    $pstmt->bind_param("i", $profileUserId);
    $pstmt->execute();
    $posts = $pstmt->get_result();

    if ($posts->num_rows === 0) {
        echo "<p>No posts yet.</p>";
    } else {
        while ($post = $posts->fetch_assoc()):
    ?>
        <div class="post-box">
            <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>

            <?php if (!empty($post['image_path'])): ?>
                <img src="<?php echo htmlspecialchars($post['image_path']); ?>" style="max-width: 100%; height: auto;"><br>
            <?php endif; ?>

            <small>Posted on: <?php echo $post['created_at']; ?></small><br>
            <?php if (!empty($post['updated_at'])): ?>
                <small><i>Edited on: <?php echo $post['updated_at']; ?></i></small>
            <?php endif; ?>
        </div>
        <hr>
    <?php
        endwhile;
    }
    ?>
</div>
</body>
</html>
