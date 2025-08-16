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

// Fetch profile user data including avatar and cover
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
    .profile-header {
        position: relative;
        width: 100%;
        height: 200px;
        background: #ccc center/cover no-repeat;
        border-radius: 10px 10px 0 0;
        cursor: pointer;
    }
    .profile-avatar {
        position: absolute;
        bottom: -50px;
        left: 20px;
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 4px solid #fff;
        background: #eee center/cover no-repeat;
        cursor: pointer;
    }
    .profile-info { margin-top: 60px; padding: 0 20px; }
    .edit-icon {
        position: absolute;
        background: rgba(0,0,0,0.6);
        color: white;
        font-size: 12px;
        padding: 3px 6px;
        border-radius: 50%;
        cursor: pointer;
        display: none;
    }
    .profile-avatar:hover .edit-icon,
    .profile-header:hover .edit-icon {
        display: block;
    }
</style>
</head>
<body>
<div class="main-container">

<a href="LeagueBook_Page.php"><button class="button">🏠 Back to LeagueBook</button></a>

<!-- Profile Header -->
<div class="profile-header" id="coverPhoto" style="background-image:url('<?= $coverPhoto ?>');">
    <div class="profile-avatar" id="avatarPhoto" style="background-image:url('<?= $avatar ?>');"></div>
</div>

<!-- Hidden Inputs & Save Buttons -->
<?php if ($loggedInUserId === $escapedUserId): ?>
<input type="file" id="avatarInput" name="avatar" accept="image/*" style="display:none;">
<input type="file" id="coverInput" name="cover_photo" accept="image/*" style="display:none;">
<button id="saveAvatar" style="display:none; margin-top:5px;">💾 Save Avatar</button>
<button id="saveCover" style="display:none; margin-top:5px;">💾 Save Cover</button>
<?php endif; ?>

<div class="profile-info">
    <h2><?= $escapedName ?> <?= getStatusToken($user['last_active']) ?></h2>
    <p>📧 Email: <?= $escapedEmail ?></p>

    <?php if ($loggedInUserId !== $escapedUserId): ?>
    <div class="friend-actions">
        <?php if (isFriend($conn, $loggedInUserId, $escapedUserId)): ?>
            <span class="badge badge-friend">✅ Already Friends</span><br>
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
    </div>

    <form method="POST" action="send_message.php" style="margin-top:15px;">
        <input type="hidden" name="receiver_id" value="<?= $escapedUserId ?>">
        <textarea name="message" placeholder="Write your message..." required
                  style="width:100%; height:80px; margin-bottom:10px; padding:10px; border-radius:6px; border:1px solid #ccc;"></textarea>
        <button onclick="openChat(<?= $escapedUserId ?>, '<?= $escapedName ?>')" class="btn btn-message">💬 Send Message</button>
        <a href="messages.php?user_id=<?= $escapedUserId ?>" class="button">📩 View Messages</a>
    </form>
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

<?php if ($loggedInUserId === $escapedUserId): ?>
<script>
// Elements
const avatarPhoto = document.getElementById('avatarPhoto');
const coverPhoto = document.getElementById('coverPhoto');
const avatarInput = document.getElementById('avatarInput');
const coverInput = document.getElementById('coverInput');
const saveAvatar = document.getElementById('saveAvatar');
const saveCover = document.getElementById('saveCover');

// Click photo to select file
avatarPhoto.addEventListener('click', () => avatarInput.click());
coverPhoto.addEventListener('click', () => coverInput.click());

// Preview selected file & show save button
avatarInput.addEventListener('change', e => {
    const file = e.target.files[0];
    if(file) {
        avatarPhoto.style.backgroundImage = `url(${URL.createObjectURL(file)})`;
        saveAvatar.style.display = 'inline-block';
    }
});
coverInput.addEventListener('change', e => {
    const file = e.target.files[0];
    if(file) {
        coverPhoto.style.backgroundImage = `url(${URL.createObjectURL(file)})`;
        saveCover.style.display = 'inline-block';
    }
});

// Save Avatar
saveAvatar.addEventListener('click', () => {
    const file = avatarInput.files[0];
    if(!file) return;
    const formData = new FormData();
    formData.append('avatar', file);

    fetch('upload_photo.php', { method:'POST', body:formData })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            alert('Avatar saved successfully!');
            saveAvatar.style.display = 'none';
        } else {
            alert(data.message);
        }
    });
});

// Save Cover
saveCover.addEventListener('click', () => {
    const file = coverInput.files[0];
    if(!file) return;
    const formData = new FormData();
    formData.append('cover_photo', file);

    fetch('upload_photo.php', { method:'POST', body:formData })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            alert('Cover photo saved successfully!');
            saveCover.style.display = 'none';
        } else {
            alert(data.message);
        }
    });
});
</script>
<?php endif; ?>
</body>
</html>
