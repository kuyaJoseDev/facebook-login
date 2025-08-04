<?php
session_start();
include("connect.php");

// 1. Get post ID
$post_id = $_GET['id'] ?? 0;
if (!$post_id) {
    echo "Invalid post ID.";
    exit();
}

// 2. Fetch the post
$stmt = $conn->prepare("SELECT posts.*, users.name AS user_name FROM posts JOIN users ON posts.user_id = users.id WHERE posts.id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$post_result = $stmt->get_result();

if ($post_result->num_rows === 0) {
    echo "Post not found.";
    exit();
}
$post = $post_result->fetch_assoc();

// 3. Fetch comments
$stmt = $conn->prepare("SELECT comments.*, users.name AS user_name 
                        FROM comments 
                        JOIN users ON comments.user_id = users.id 
                        WHERE comments.post_id = ? 
                        ORDER BY comments.created_at ASC");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();
$all_comments = $result->fetch_all(MYSQLI_ASSOC);

// 4. Get optional open_reply param
$open_reply_id = $_GET['open_reply'] ?? null;

// 5. Display comments recursively
function display_comments($comments, $parent_id = null) {
    foreach ($comments as $comment) {
        if (($comment['parent_id'] ?? null) == $parent_id) {
            $replies = array_filter($comments, fn($c) => ($c['parent_id'] ?? null) == $comment['id']);
            $reply_count = count($replies);

            echo "<div class='comment' style='margin-left:" . ($parent_id ? "30px" : "0") . "; padding-left:10px; margin-top:10px; border-left: 1px solid #ccc;'>";

            echo "<p><strong>" . htmlspecialchars($comment['user_name']) . "</strong>: " . nl2br(htmlspecialchars($comment['content'])) . "</p>";

            echo "<a href='#' class='reply-link' data-comment-id='{$comment['id']}'>Reply</a>";

            echo "<form class='reply-form' id='reply-form-{$comment['id']}' action='add_comment.php' method='POST' style='display:none; margin-top:5px;'>
                    <input type='hidden' name='post_id' value='{$comment['post_id']}'>
                    <input type='hidden' name='parent_id' value='{$comment['id']}'>
                    <textarea name='content' required></textarea>
                    <button type='submit'>Reply</button>
                  </form>";

            if ($reply_count > 0) {
                echo "<a href='#' class='toggle-replies' data-target='replies-{$comment['id']}' style='color:green; display:block; margin-top:5px;'>🔽 View {$reply_count} " . ($reply_count === 1 ? "Reply" : "Replies") . "</a>";
            }

            echo "<div class='replies' id='replies-{$comment['id']}' style='display:none;'>";
            display_comments($comments, $comment['id']);
            echo "</div>";

            echo "</div>";
        }
    }
}

// 6. Shared by info
$share_link = "http://localhost/League-University/LeagueBook/view_post.php?id=$post_id";
$shared_by_name = "";
if (isset($_GET['shared_by'])) {
    $shared_by = (int)$_GET['shared_by'];
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $shared_by);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $shared_by_name = htmlspecialchars($user['name']);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>View Post</title>
    <link rel="stylesheet" href="LeagueBook_Page.css" />
    <style>
        .comment { font-family: Arial; margin-bottom: 10px; }
        .reply-link { font-size: 0.9em; color: blue; cursor: pointer; }
        .toggle-replies { cursor: pointer; }
        textarea { width: 100%; height: 60px; margin-top: 5px; }
    </style>
</head>
<body>
<div class="main-container">
    <div style="margin-top: 20px;">
        <a href="LeagueBook_Page.php">
            
            <button class="button">⬅ Back to Home</button>

        </a><br>
    </div>
    <?php if (!empty($post['video_path'])): ?>
    <video width="100%" autoplay controls >
        <source src="<?= htmlspecialchars($post['video_path']); ?>" type="video/mp4">
        Your browser does not support the video tag.
    </video>
<?php endif; ?>


    <h2><?php echo htmlspecialchars($post['user_name']); ?>'s Post</h2>
    <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
    <p><small>📅 Posted on: <?php echo $post['created_at']; ?></small></p>

    <?php if (!empty($shared_by_name)): ?>
        <p><em>🔁 Shared by <strong><?php echo $shared_by_name; ?></strong></em></p>
    <?php endif; ?>

    <hr>
   <!-- 💬 Comment form -->
<form action="comment.php" method="POST" class="comment-form">
    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
    <input type="text" name="comment" placeholder="Write a comment..." required>
    <button type="submit">💬 Comment</button>
</form>

<?php
// Fetch all comments for the current post
$commentStmt = $conn->prepare("SELECT comments.*, users.name AS user_name FROM comments JOIN users ON comments.user_id = users.id WHERE post_id = ? ORDER BY created_at ASC");
$commentStmt->bind_param("i", $post['id']);
$commentStmt->execute();
$commentResult = $commentStmt->get_result();
$comments = $commentResult->fetch_all(MYSQLI_ASSOC);



// Display comments recursively
function showComments($comments, $parent_id = null, $depth = 0) {
    foreach ($comments as $c) {
        if ($c['parent_id'] == $parent_id) {
            // Count replies
            $replies = array_filter($comments, fn($reply) => $reply['parent_id'] == $c['id']);
            $reply_count = count($replies);

            echo "<div style='margin-left:" . ($depth * 20) . "px; border-left:1px solid #ccc; padding-left:10px; margin-top:5px;'>";

            echo "<strong>" . htmlspecialchars($c['user_name']) . ":</strong> " . nl2br(htmlspecialchars($c['content']));
            echo "<div><a href='#' class='reply-link' data-comment-id='{$c['id']}'>Reply</a></div>";

            // Hidden reply form
            echo "<form class='reply-form' id='reply-form-{$c['id']}' action='comment.php' method='POST' style='display:none; margin-top:5px;'>
                    <input type='hidden' name='post_id' value='{$c['post_id']}'>
                    <input type='hidden' name='parent_id' value='{$c['id']}'>
                    <textarea name='comment' required></textarea>
                    <button type='submit'>Reply</button>
                  </form>";

            // Replies toggle button
            if ($reply_count > 0) {
                echo "<a href='#' class='toggle-replies' data-target='replies-{$c['id']}' style='color:green; display:block; margin-top:5px;'>🔽 View {$reply_count} " . ($reply_count === 1 ? "Reply" : "Replies") . "</a>";
            }

            // Replies container
            echo "<div class='replies' id='replies-{$c['id']}' style='display:none;'>";
            showComments($comments, $c['id'], $depth + 1); // Recursive call
            echo "</div>";

            echo "</div>";
        }
    }
}


echo "<h4>💬 Comments</h4>";
showComments($comments);
?>


<script>
document.addEventListener('DOMContentLoaded', function () {
    // Show reply forms
    document.querySelectorAll('.reply-link').forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const form = document.getElementById('reply-form-' + this.dataset.commentId);
            if (form) {
                form.style.display = form.style.display === 'none' ? 'block' : 'none';
            }
        });
    });

    // Toggle replies
    
    document.querySelectorAll('.toggle-replies').forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.getElementById(this.dataset.target);
            if (target) {
                const visible = target.style.display === 'block';
                target.style.display = visible ? 'none' : 'block';
                this.innerText = this.innerText.replace(visible ? '🔼 Hide' : '🔽 View', visible ? '🔽 View' : '🔼 Hide');
            }
        });
    });
    

    // Auto open a reply thread (if open_reply param is set)
    <?php if (!empty($open_reply_id)) : ?>
    const replyContainer = document.getElementById('replies-<?php echo $open_reply_id; ?>');
    const toggleButton = document.querySelector('[data-target="replies-<?php echo $open_reply_id; ?>"]');
    if (replyContainer && toggleButton) {
        replyContainer.style.display = 'block';
        toggleButton.innerText = toggleButton.innerText.replace('🔽 View', '🔼 Hide');
    }
    <?php endif; ?>
});
</script>
<?php while ($row = $result->fetch_assoc()): ?>
  <div class="post-box">
    <h4><?= htmlspecialchars($row['user_name']) ?></h4>
    <p><?= htmlspecialchars($row['content']) ?></p>

    <!-- 🛑 Report Link (Shown Under Each Post) -->
    <p style="color: red; cursor: pointer;" onclick="confirmReport<?= $row['id'] ?>()">
      Are you sure you want to report this post?
    </p>

    <form id="reportForm<?= $row['id'] ?>" action="report_post.php" method="POST" style="display: none;">
      <input type="hidden" name="post_id" value="<?= $row['id'] ?>">
    </form>

    <script>
      function confirmReport<?= $row['id'] ?>() {
        if (confirm("Are you sure you want to report this post?")) {
          document.getElementById('reportForm<?= $row['id'] ?>').submit();
        }
      }
    </script>
  </div>
<?php endwhile; ?>


</body>
</html>
