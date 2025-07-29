<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header("Location: LeagueBook.php");
    exit();
}

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
<body>
  <div class="main-container">
     <!-- Logout -->
    <form action="LeagueBook.php" method="POST" class="logout-form">
      <p>Logged in as: <strong><?php echo htmlspecialchars($userName); ?></strong></p>
      <button type="submit">Logout</button>
    </form>
    <h2>👋 Welcome, <?php echo htmlspecialchars($userName); ?>!</h2>

    <h3><a href="view_friend_request.php">📬 View Friend Requests</a></h3>
    <h3>🧑‍🤝‍🧑 People You May Know:</h3>

    <?php if (isset($friendRequestMessage)): ?>
      <div class="alert-message"><?php echo $friendRequestMessage; ?></div>
    <?php endif; ?>

    <?php
    $usersQuery = $conn->prepare("SELECT id, name FROM users WHERE id != ?");
    $usersQuery->bind_param("i", $userId);
    $usersQuery->execute();
    $usersResult = $usersQuery->get_result();
    while ($user = $usersResult->fetch_assoc()):
    ?>
      <div class="user-suggestion">
        <strong><?php echo htmlspecialchars($user['name']); ?></strong>
        <form method="GET" action="LeagueBook_Page.php">
          <input type="hidden" name="receiver_id" value="<?php echo (int)$user['id']; ?>">
          <button type="submit">➕ Add Friend</button>
        </form>
      </div>
    <?php endwhile; ?>

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
</body>
</html>
