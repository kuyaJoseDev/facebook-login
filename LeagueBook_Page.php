<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header("Location: LeagueBook.php");
    exit();
}

$userName = $_SESSION['user_name'] ?? 'Guest';
$userId = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>LeagueBook</title>
  <link rel="stylesheet" href="LeagueBook_Page.css" />
</head>
<body>
  <div class="main-container">
    <h2>Welcome, <?php echo htmlspecialchars($userName); ?>!</h2>
    
    <!-- Post Form -->
    <form action="Post.php" method="POST" enctype="multipart/form-data" class="post-form">
      <textarea name="content" placeholder="What's on your mind?" required></textarea>
      <input type="file" name="image" accept="image/*" />
      <button type="submit">➕ Post</button>
    </form>

    <!-- Logout -->
    <form action="LeagueBook.php" method="POST" class="logout-form">
      <p>Logged in as: <strong><?php echo $userName; ?></strong></p>
      <button type="submit">Logout</button>
    </form>

    <hr>
    <h3>📰 News Feed:</h3>

    <?php
    $sql = "SELECT posts.*, users.name FROM posts JOIN users ON posts.user_id = users.id ORDER BY posts.created_at DESC";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<div class='post-box'>";
            echo "<strong>" . htmlspecialchars($row['name']) . "</strong><br>";
            echo "<p>" . nl2br(htmlspecialchars($row['content'])) . "</p>";

            if (!empty($row['image_path'])) {
                echo "<img src='" . htmlspecialchars($row['image_path']) . "' style='max-width: 100%; height: auto;'><br>";
            }

            echo "<small>Posted on: " . $row['created_at'] . "</small><br>";
            if (!empty($row['updated_at'])) {
                echo "<small><i>Edited on: " . $row['updated_at'] . "</i></small><br>";
            }

            // Likes Count
            $likeQuery = $conn->query("SELECT COUNT(*) AS like_count FROM likes WHERE post_id = {$row['id']}");
            $likeCount = $likeQuery->fetch_assoc()['like_count'];
            echo "<form action='like_post.php' method='POST' style='display:inline;'>
                    <input type='hidden' name='post_id' value='{$row['id']}'>
                    <button type='submit'>👍 Like ($likeCount)</button>
                  </form>";

            // Only owner sees Edit/Delete
            if ($userId == $row['user_id']) {
                echo "<a href='edit_post.php?id={$row['id']}'><button>✏️ Edit</button></a>
                      <form action='delete_post.php' method='POST' style='display:inline;' onsubmit=\"return confirm('Delete this post?');\">
                        <input type='hidden' name='post_id' value='{$row['id']}'>
                        <button type='submit'>🗑️ Delete</button>
                      </form>";
            }

            // Comments form
            echo "<form action='comment_post.php' method='POST'>
                    <input type='hidden' name='post_id' value='{$row['id']}'>
                    <input type='text' name='comment' placeholder='Write a comment...' required>
                    <button type='submit'>💬 Comment</button>
                  </form>";

            // Display comments
            $c_stmt = $conn->prepare("SELECT comments.*, users.name FROM comments JOIN users ON comments.user_id = users.id WHERE post_id = ? ORDER BY comments.created_at ASC");
            $c_stmt->bind_param("i", $row['id']);
            $c_stmt->execute();
            $c_result = $c_stmt->get_result();

            echo "<div class='comments'>";
            while ($comment = $c_result->fetch_assoc()) {
                echo "<div class='comment-box'>
                        <strong>" . htmlspecialchars($comment['name']) . "</strong>: " .
                        nl2br(htmlspecialchars($comment['content'])) . "<br>
                        <small>" . $comment['created_at'] . "</small>
                      </div>";
            }
            echo "</div>";

            echo "</div><hr>";
        }
    } else {
        echo "<p>No posts yet.</p>";
    }
    if ($_SESSION['user_id'] == $row['user_id']) {
    echo "<a href='edit_post.php?id={$row['id']}'>
            <button class='button'>✏️ Edit</button>
          </a>";
}

    ?>
  </div>
</body>
</html>
