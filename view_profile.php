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
$stmt = $conn->prepare("SELECT id, name, email, last_active, avatar, cover_photo FROM users WHERE id = ?");
$stmt->bind_param("i", $profileUserId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) die("User not found.");

// Escape variables
$escapedName = htmlspecialchars($user['name']);
$escapedEmail = htmlspecialchars($user['email']);
$escapedUserId = (int)$user['id'];

// Use user's avatar/cover if exists, fallback to default
$avatar = !empty($user['avatar']) ? $user['avatar'] : 'images/default-avatar.png';
$coverPhoto = !empty($user['cover_photo']) ? $user['cover_photo'] : 'images/default-cover.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Profile - <?= $escapedName ?></title>
<link rel="stylesheet" href="LeagueBook_Page.css">
<style>
    .profile-header { position: relative; width: 100%; height: 200px; background: #ccc center/cover no-repeat; border-radius: 10px 10px 0 0; cursor: pointer; }
    .profile-avatar { position: absolute; bottom: -50px; left: 20px; width: 100px; height: 100px; border-radius: 50%; border: 4px solid #fff; background: #eee center/cover no-repeat; cursor: pointer; }
    .profile-info { margin-top: 60px; padding: 0 20px; }
    .btn-friend { padding: 6px 12px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; }
    .btn-friend:disabled { background: gray; cursor: default; }
</style>
</head>
<body>
<div class="main-container">

<a href="LeagueBook_Page.php"><button class="button">🏠 Back to LeagueBook</button></a>

<!-- Profile Header -->
<div class="profile-header" style="background-image:url('<?= $coverPhoto ?>');">
    <div class="profile-avatar" style="background-image:url('<?= $avatar ?>');"></div>
</div>

<div class="profile-info">
    <h2><?= $escapedName ?> <?= getStatusToken($user['last_active']) ?></h2>
    <p>📧 Email: <?= $escapedEmail ?></p>

<?php if ($loggedInUserId !== $escapedUserId): ?>
    <div class="friend-actions">
        <button id="friendBtn" class="btn-friend">Loading...</button>
    </div>
<?php endif; ?>


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

<script>
// Friend Request
const friendBtn = document.getElementById('friendBtn');
const profileUserId = <?= $escapedUserId ?>;

function updateFriendButton(status) {
    if(status === 'friends') {
        friendBtn.textContent = '✅ Already Friends';
        friendBtn.disabled = true;
    } else if(status === 'pending') {
        friendBtn.textContent = '⏳ Request Pending';
        friendBtn.disabled = true;
    } else {
        friendBtn.textContent = '➕ Add Friend';
        friendBtn.disabled = false;
    }
}

// Load status on page load
fetch(`friend_status.php?user_id=${profileUserId}`)
.then(res => res.json())
.then(data => updateFriendButton(data.status))
.catch(err => console.error(err));

// Send request when button clicked
friendBtn.addEventListener('click', () => {
    fetch('send_friend_request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `receiver_id=${profileUserId}`
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if(data.success) updateFriendButton('pending');
    })
    .catch(err => console.error(err));
});

</script>

</body>
</html>
