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
$stmt = $conn->prepare("SELECT id, name, email, last_active FROM users WHERE id = ?");
$stmt->bind_param("i", $profileUserId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("User not found.");
}

// Escape variables for output once
$escapedName = htmlspecialchars($user['name']);
$escapedEmail = htmlspecialchars($user['email']);
$escapedUserId = (int) $user['id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Profile - <?= $escapedName ?></title>
    <link rel="stylesheet" href="LeagueBook_Page.css">
</head>
<body>
<div class="main-container">
    <a href="LeagueBook_Page.php">
        <button class="button">🏠 Back to LeagueBook</button>
    </a>

    <div class="profile-wrapper">
        <h2 class="profile-title">
            👤 Profile: <?= $escapedName ?>
            <?= getStatusToken($user['last_active']) ?>
        </h2>

        <p class="profile-email">📧 Email: <?= $escapedEmail ?></p>

        <?php if ($loggedInUserId !== $escapedUserId): ?>
            <div class="friend-actions">

                <?php if (isFriend($conn, $loggedInUserId, $escapedUserId)): ?>
                    <span class="badge badge-friend">✅ Already Friends</span>
<br>
                    <?php if (hasMutualFriend($conn, $loggedInUserId, $escapedUserId)): ?>
                        <span class="badge badge-mutual">🤞 Mutual Friend</span>
                    <?php else: ?>
                        <span class="badge badge-no-mutual">No Mutual Friends</span>
                    <?php endif; ?>

                <?php else: ?>
                    <form method="GET" action="LeagueBook_Page.php" class="inline-form">
                        <input type="hidden" name="receiver_id" value="<?= $escapedUserId ?>">
                        <button type="submit" class="btn btn-friend">➕ Add Friend</button>
                    </form>
                <?php endif; ?>
                <?php if (isset($_GET['success'])): ?>
  <div class="success_message"><?= htmlspecialchars($_GET['success']) ?></div>
<?php elseif (isset($_GET['error'])): ?>
  <div class="error_message"><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>


                <!-- 💬 Private Message Button -->
<form method="POST" action="send_message.php" style="margin-top: 15px;">
  <input type="hidden" name="receiver_id" value="<?= htmlspecialchars($escapedUserId) ?>">
  
  <textarea 
    name="message" 
    placeholder="Write your message..." 
    required 
    style="width:100%; height: 80px; margin-bottom:10px; padding: 10px; border-radius: 6px; border: 1px solid #ccc;"
  ></textarea>
  
<!-- 💬 Private Message Button -->
<button 
  onclick="openChat(<?= (int)$user['id']; ?>, '<?= htmlspecialchars($user['name']); ?>')" 
  class="btn btn-message"
>
  💬 Send Message
</button>

<!-- 📩 View Message Thread Link -->
<a href="messages.php?user_id=<?= $user['id'] ?>" class="button">📩 View Messages</a>

</form>



            </div>
        <?php endif; ?>
    </div>

    <hr>
    <h3>📝 Posts by <?= $escapedName ?>:</h3>

    <?php
    $pstmt = $conn->prepare("SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC");
    $pstmt->bind_param("i", $profileUserId);
    $pstmt->execute();
    $posts = $pstmt->get_result();

    if ($posts->num_rows === 0): ?>
        <p>No posts yet.</p>
    <?php else:
        while ($post = $posts->fetch_assoc()):
            $postContent = nl2br(htmlspecialchars($post['content']));
            $imagePath = htmlspecialchars($post['image_path']);
            $createdAt = $post['created_at'];
            $updatedAt = $post['updated_at'] ?? '';
    ?>
        <div class="post-box">
            <p><?= $postContent ?></p>
            <?php if (!empty($imagePath)): ?>
                <img src="<?= $imagePath ?>" alt="Post Image"><br>
            <?php endif; ?>
            <small>Posted on: <?= $createdAt ?></small><br>
            <?php if (!empty($updatedAt)): ?>
                <small><i>Edited on: <?= $updatedAt ?></i></small>
            <?php endif; ?>
        </div>
        <hr>
    <?php endwhile; endif; ?>
</div>
</body>
</html>
