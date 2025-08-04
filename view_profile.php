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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Profile - <?= htmlspecialchars($user['name']) ?></title>
    <link rel="stylesheet" href="LeagueBook_Page.css">
</head>
<body>
<div class="main-container">
    <a href="LeagueBook_Page.php"><button class="button">🏠 Back to LeagueBook</button></a>

    <h2>👤 Profile: <?= htmlspecialchars($user['name']) ?> <?= getStatusToken($user['last_active']) ?></h2>
    <p>Email: <?= htmlspecialchars($user['email']) ?></p>

    <?php if ($loggedInUserId !== $user['id']): ?>
        <?php if (isFriend($conn, $loggedInUserId, $user['id'])): ?>
            <div class="friend-status">
                <span style="color: blue;">✅ Already Friends</span><br>
                <?php if (hasMutualFriend($conn, $loggedInUserId, $user['id'])): ?>
                    <span style="color: green;">🤞 Mutual Friend</span>
                <?php else: ?>
                    <span style="color: blue;">No Mutual Friends</span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <form method="GET" action="LeagueBook_Page.php">
                <input type="hidden" name="receiver_id" value="<?= (int)$user['id']; ?>">
                <button type="submit">➕ Add Friend</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <hr>
    <h3>📝 Posts by <?= htmlspecialchars($user['name']) ?>:</h3>

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
            <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
            <?php if (!empty($post['image_path'])): ?>
                <img src="<?= htmlspecialchars($post['image_path']) ?>" style="max-width: 100%; height: auto;"><br>
            <?php endif; ?>
            <small>Posted on: <?= $post['created_at'] ?></small><br>
            <?php if (!empty($post['updated_at'])): ?>
                <small><i>Edited on: <?= $post['updated_at'] ?></i></small>
            <?php endif; ?>
        </div>
        <hr>
    <?php endwhile; } ?>
</div>
</body>
</html>