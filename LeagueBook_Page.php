<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: LeagueBook.php");
    exit();
}
include("connect.php");

// Optional fallback if user_name isn't set
$userName = $_SESSION['user_name'] ?? 'Guest';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LeagueBook</title>
  <link rel="stylesheet" href="LeagueBook_Page.css" />
  <link rel="stylesheet" href="https://unpkg.com/boxicons@latest/css/boxicons.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Goblin+One&display=swap" rel="stylesheet" />
</head>

<body>
  <div class="main-container">
    <h2>Welcome, <?php echo htmlspecialchars($userName); ?>!</h2>

    <!-- Post Form -->
    <form action="Post.php" method="POST" class="post-form">
      <textarea name="content" placeholder="What's on your mind?" required></textarea><br>
      <button type="submit">Post</button>
    </form>

    <!-- Logout Button -->
    <form action="LeagueBook.php" method="POST" class="logout-form">
   <p>Logged in as: <strong><?php echo $_SESSION['user_name'] ?? 'Guest'; ?></strong></p>
    <button type="submit">Logout</button>
</form>


    <hr>
    <h3>News Feed:</h3>

    <?php
    $sql = "SELECT posts.content, posts.created_at, users.name 
            FROM posts 
            JOIN users ON posts.user_id = users.id 
            ORDER BY posts.created_at DESC";

    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<div class='post-box'>
                    <strong>" . htmlspecialchars($row['name']) . "</strong><br>
                    <p>" . nl2br(htmlspecialchars($row['content'])) . "</p>
                    <small>" . $row['created_at'] . "</small>
                  </div>";
        }
    } else {
        echo "<p>No posts yet.</p>";
    }
    ?>
  </div>
</body>
</html>
