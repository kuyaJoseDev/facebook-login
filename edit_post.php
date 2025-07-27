<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: LeagueBook.php");
    exit();
}

$post_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Fetch post data and make sure the user owns it
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
  <link rel="stylesheet" href="LeagueBook.css">
</head>
<body>
  <div class="form_container">
    <h2>Edit Post</h2>
    <form action="update_post.php" method="POST">
      <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post['id']); ?>">
      <div class="input_box">
        <textarea name="content" required><?php echo htmlspecialchars($post['content']); ?></textarea>
      </div>
      <div class="buttons">
        <button type="submit" class="button">💾 Update</button>
        <a href="LeagueBook_Page.php"><button type="button" class="button">Cancel</button></a>
      </div>
    </form>
  </div>
</body>
</html>
