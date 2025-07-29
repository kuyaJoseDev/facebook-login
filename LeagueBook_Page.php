<?php
session_start();
include("connect.php");
$update = $conn->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
$update->bind_param("i", $_SESSION['user_id']);
$update->execute();


if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header("Location: LeagueBook.php");
    exit();
}

// 🔔 Count pending friend requests
$pending = 0;
$checkPending = $conn->prepare("SELECT COUNT(*) AS total FROM friend_requests WHERE receiver_id = ? AND status = 'pending'");
$checkPending->bind_param("i", $_SESSION['user_id']);
$checkPending->execute();
$pendingResult = $checkPending->get_result()->fetch_assoc();
$pending = $pendingResult['total'];

$userName = $_SESSION['user_name'] ?? 'Guest';
$userId = $_SESSION['user_id'];

// Handle friend request
if (isset($_GET['receiver_id'])) {
    $receiverId = (int) $_GET['receiver_id'];
    if ($receiverId !== $userId) {
        $check = $conn->prepare("SELECT * FROM friend_requests WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'");
        $check->bind_param("ii", $userId, $receiverId);
        $check->execute();
        $checkResult = $check->get_result();

        if ($checkResult->num_rows > 0) {
            $friendRequestMessage = "⚠️ You already sent a friend request to this user.";
        } else {
            $insert = $conn->prepare("INSERT INTO friend_requests (sender_id, receiver_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
            $insert->bind_param("ii", $userId, $receiverId);
            $friendRequestMessage = $insert->execute() ? "✅ Friend request sent successfully." : "❌ Failed to send friend request.";
        }
    } else {
        $friendRequestMessage = "⚠️ You cannot send a friend request to yourself.";
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>LeagueBook</title>
  <link rel="stylesheet" href="LeagueBook_Page.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <script>
  function copyToClipboard(text) {
      navigator.clipboard.writeText(text).then(() => {
          alert("Post link copied to clipboard!");
      }).catch(err => {
          alert("Failed to copy: " + err);
      });
  }
  </script>
</head>
<div id="loading-screen">
  <div class="loader-content">
    <img src="" alt="" class="loader-logo">
    <div class="spinner"></div>
    <p>Loading LeagueBook...</p>
  </div>
</div>


<body>
  <body>

  <!-- ✅ Stylish Logout Bar -->
  <div class="logout-bar">
    <!-- 🔗 Fixed Top Bar with Homepage Shortcut -->
<div class="top-bar">
  <a href="LeagueBook_Page.php" class="http://localhost/League-University/LeagueBook/LeagueBook_Page.php">HOME</a>
</div>

    <form action="LeagueBook.php" method="POST" class="logout-form">
      <span>👤 Logged in as: <strong><?php echo htmlspecialchars($userName); ?></strong></span>
      <button type="submit" class="logout-button">
  <i class="fas fa-power-off"></i> Logout
</button>

    </form>
  </div>
  <!-- Only visible on mobile -->
<div class="mobile-toggle-section">

  <button class="toggle-btn" onclick="toggleSection('online-offline')">👥 Show/Hide Online & Offline Users</button>
  <div id="online-offline" class="toggle-content">
    <!-- Your left-sidebar content here -->
    <div class="online-users"> ... </div>
    <div class="offline-users"> ... </div>
  </div>

  <button class="toggle-btn" onclick="toggleSection('suggested-users')">🔍 Show/Hide Suggestions</button>
  <div id="suggested-users" class="toggle-content">
    <!-- Your right-sidebar content here -->
    <div class="user-suggestion"> ... </div>
  </div>

</div>


  <div class="main-container">
    <!-- Your layout with left-sidebar, wrapper, right-sidebar -->

  <div class="main-container">
    
<div class="friend-request-container">
  <a href="view_friend_request.php" class="friend-request-link">
    📬 View Friend Requests
    <?php if ($pending > 0): ?>
      <span class="badge"><?php echo $pending; ?></span>
    <?php endif; ?>
  </a>
</div>
    

    <h2>👋 Welcome, <?php echo htmlspecialchars($userName); ?>!</h2>

    
    
<!-- ✅ FIXED RIGHT SIDEBAR -->

<div class="right-sidebar">
  <h3>🧑‍🤝‍🧑 People You May Know</h3>
  <?php if (isset($friendRequestMessage)): ?>
    <div class="alert-message"><?php echo $friendRequestMessage; ?></div>
  <?php endif; ?>
  <!-- Suggested users list -->
</div>





<!-- ✅ FIXED LEFT SIDEBAR -->
<div class="left-sidebar">
  <h4>🟢 Online Users</h4>
  <div class="online-users">
    <?php
    $onlineQuery = $conn->prepare("
      SELECT u.id, u.name, u.last_active FROM users u
      WHERE u.id != ?
        AND u.id NOT IN (
          SELECT CASE
                   WHEN user1_id = ? THEN user2_id
                   WHEN user2_id = ? THEN user1_id
                 END
          FROM friends
          WHERE user1_id = ? OR user2_id = ?
        )
        AND u.id NOT IN (
          SELECT receiver_id FROM friend_requests WHERE sender_id = ? AND status = 'pending'
        )
    ");
    $onlineQuery->bind_param("iiiiii", $userId, $userId, $userId, $userId, $userId, $userId);
    $onlineQuery->execute();
    $onlineResult = $onlineQuery->get_result();

    while ($user = $onlineResult->fetch_assoc()):
      $isOnline = (strtotime($user['last_active']) > strtotime('-2 minutes'));
      if ($isOnline):
    ?>
      <div class="user-suggestion">
        <strong><?= htmlspecialchars($user['name']); ?> 🟢 Online</strong>
        <form method="GET" action="LeagueBook_Page.php">
          <input type="hidden" name="receiver_id" value="<?= (int)$user['id']; ?>">
          <button type="submit">➕ Add Friend</button>
        </form>
      </div>
    <?php endif; endwhile; ?>
  </div>

  <h4>⚫ Offline Users</h4>
  <div class="offline-users">
    <?php
    $offlineQuery = $conn->prepare("
      SELECT u.id, u.name, u.last_active FROM users u
      WHERE u.id != ?
        AND u.id NOT IN (
          SELECT CASE
                   WHEN user1_id = ? THEN user2_id
                   WHEN user2_id = ? THEN user1_id
                 END
          FROM friends
          WHERE user1_id = ? OR user2_id = ?
        )
        AND u.id NOT IN (
          SELECT receiver_id FROM friend_requests WHERE sender_id = ? AND status = 'pending'
        )
    ");
    $offlineQuery->bind_param("iiiiii", $userId, $userId, $userId, $userId, $userId, $userId);
    $offlineQuery->execute();
    $offlineResult = $offlineQuery->get_result();

    while ($user = $offlineResult->fetch_assoc()):
      $isOnline = (strtotime($user['last_active']) > strtotime('-2 minutes'));
      if (!$isOnline):
    ?>
      <div class="user-suggestion">
        <strong><?= htmlspecialchars($user['name']); ?> ⚫ Offline</strong>
        <form method="GET" action="LeagueBook_Page.php">
          <input type="hidden" name="receiver_id" value="<?= (int)$user['id']; ?>">
          <button type="submit">➕ Add Friend</button>
        </form>
      </div>
    <?php endif; endwhile; ?>
  </div>
</div>



    <!-- Post Form -->
    <form action="Post.php" method="POST" enctype="multipart/form-data" class="post-form">
      <textarea name="content" placeholder="What's on your mind?" required></textarea>
      <input type="file" name="image" accept="image/*">
      <input type="file" name="video" accept="video/mp4,video/webm,video/ogg">
      <button type="submit">➕ Post</button>
    </form>

    <hr>
    <h3>📰 News Feed:</h3>

    <?php
    $sql = "SELECT posts.*, users.name FROM posts JOIN users ON posts.user_id = users.id ORDER BY posts.created_at DESC";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0):
        while ($row = $result->fetch_assoc()):
    ?>
      <div class="post-box">
        <strong>
          <a href="view_profile.php?id=<?php echo (int)$row['user_id']; ?>">
            <?php echo htmlspecialchars($row['name']); ?>
          </a><br>
          <small>📅 Posted on: <?php echo $row['created_at']; ?></small>
        </strong>
        <p><?php echo nl2br(htmlspecialchars($row['content'])); ?></p>

        <?php if (!empty($row['image_path'])): ?>
          <img src="<?php echo htmlspecialchars($row['image_path']); ?>" style="max-width:100%; height:auto;"><br>
        <?php endif; ?>

        <?php if (!empty($row['video_path']) && file_exists($row['video_path'])): ?>
          <?php $mime = mime_content_type($row['video_path']); ?>
          <video controls style="max-width:100%; height:auto;">
            <source src="<?php echo htmlspecialchars($row['video_path']); ?>" type="<?php echo $mime; ?>">
            Your browser does not support the video tag.
          </video><br>
        <?php endif; ?>

        <?php if (!empty($row['updated_at'])): ?>
          <small><i>✏️ Edited on: <?php echo $row['updated_at']; ?></i></small><br>
        <?php endif; ?>

        <?php
        $likeQuery = $conn->query("SELECT COUNT(*) AS like_count FROM likes WHERE post_id = {$row['id']}");
        $likeCount = $likeQuery->fetch_assoc()['like_count'];

        $liked = false;
        $checkLike = $conn->prepare("SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?");
        $checkLike->bind_param("ii", $row['id'], $userId);
        $checkLike->execute();
        $checkLike->store_result();
        $liked = $checkLike->num_rows > 0;
        ?>

        <form action="like_post.php" method="POST" style="display:inline;">
          <input type="hidden" name="post_id" value="<?php echo $row['id']; ?>">
          <button type="submit">
            <?php echo $liked ? "👎 Unlike" : "👍 Like"; ?> (<?php echo $likeCount; ?>)
          </button>
        </form>

        <?php if ($userId == $row['user_id']): ?>
          <a href="edit_post.php?id=<?php echo $row['id']; ?>">
            <button>✏️ Edit</button>
          </a>
          <form action="delete_post.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete this post?');">
            <input type="hidden" name="post_id" value="<?php echo $row['id']; ?>">
            <button type="submit">🗑️ Delete</button>
          </form>
        <?php endif; ?>

      <?php
$post_id = $row['id'];
$share_link = "http://localhost/League-University/LeagueBook/view_post.php?id=$post_id";
?>
<a href="view_post.php?id=<?php echo $post_id; ?>">
  <button>🔍 View Post</button>
</a>
<button onclick="copyToClipboard('<?php echo $share_link; ?>')">🔗 Share</button>

        <!-- Comment Form -->
        <form action="comment.php" method="POST" class="comment-form">
          <input type="hidden" name="post_id" value="<?php echo $row['id']; ?>">
          <input type="text" name="comment" placeholder="Write a comment..." required>
          <button type="submit">💬 Comment</button>
        </form>
        

        <!-- Comments -->
        <?php
        $c_stmt = $conn->prepare("SELECT comments.*, users.name FROM comments JOIN users ON comments.user_id = users.id WHERE post_id = ? ORDER BY comments.created_at ASC");
        $c_stmt->bind_param("i", $row['id']);
        $c_stmt->execute();
        $c_result = $c_stmt->get_result();
        ?>
        <div class="comments">
          <?php while ($comment = $c_result->fetch_assoc()): ?>
            <div class="comment-box">
              <strong>
                <a href="view_profile.php?id=<?php echo (int)$comment['user_id']; ?>">
                  <?php echo htmlspecialchars($comment['name']); ?>
                </a>
              </strong>: <?php echo nl2br(htmlspecialchars($comment['content'])); ?><br>
              <small><?php echo $comment['created_at']; ?></small>
              <?php if ($comment['user_id'] == $userId): ?>
                <form method="post" action="delete_comment.php" style="display:inline;">
                  <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                  <button type="submit" onclick="return confirm('Delete this comment?')">🗑️ Delete</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endwhile; ?>
        </div>
      </div>
      <hr>
    <?php endwhile; else: echo "<p>No posts yet.</p>"; endif; ?>
  </div>
  <script>
  window.addEventListener("load", function () {
    const loader = document.getElementById("loading-screen");
    loader.style.opacity = "0";
    setTimeout(() => {
      loader.style.display = "none";
    }, 500);
  });
</script>
<script>
function toggleSection(id) {
  const section = document.getElementById(id);
  if (section.style.display === "block") {
    section.style.display = "none";
  } else {
    section.style.display = "block";
  }
}
</script>

</body>
</html>
