<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: LeagueBook.php");
    exit();
}

$post_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM posts WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $post_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("⚠️ Post not found or you don't have permission to edit it.");
}

$post = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Post</title>
  <link rel="stylesheet" href="LeagueBook_Page.css">
</head>
<body>
  <div class="main-container">
    <h2>✏️ Edit Post</h2>

    <form action="update_post.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post['id']); ?>">

      <textarea name="content" required><?php echo htmlspecialchars($post['content']); ?></textarea>

      <?php if (!empty($post['video_path'])): ?>
        <p><strong>🎬 Current Video:</strong></p>
        <video width="100%" controls>
          <source src="<?php echo htmlspecialchars($post['video_path']); ?>" type="video/mp4">
          Your browser does not support the video tag.
        </video>
      <?php endif; ?>

      <p><strong>Replace Video (optional):</strong></p>
      <input type="file" name="video" accept="video/*">

      <div style="margin-top: 10px;">
        <button type="submit">💾 Update</button>
        <a href="LeagueBook_Page.php"><button type="button">❌ Cancel</button></a>
      </div>
    </form>
  </div>
</body>
</html>
