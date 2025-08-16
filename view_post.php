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
$stmt = $conn->prepare("SELECT posts.*, users.name AS user_name 
                        FROM posts 
                        JOIN users ON posts.user_id = users.id 
                        WHERE posts.id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$post_result = $stmt->get_result();

if ($post_result->num_rows === 0) {
    echo "Post not found.";
    exit();
}
$post = $post_result->fetch_assoc();

// 3. Fetch comments for this post
$commentStmt = $conn->prepare("SELECT comments.*, users.name AS user_name 
                               FROM comments 
                               JOIN users ON comments.user_id = users.id 
                               WHERE comments.post_id = ? 
                               ORDER BY comments.created_at ASC");
$commentStmt->bind_param("i", $post['id']);
$commentStmt->execute();
$commentResult = $commentStmt->get_result();
$comments = $commentResult->fetch_all(MYSQLI_ASSOC);

// 4. Optional auto-open replies
$open_reply_id = $_GET['open_reply'] ?? null;

// 5. Recursive comment display
function showComments($comments, $parent_id = null, $depth = 0) {
    foreach ($comments as $c) {
        if ($c['parent_id'] == $parent_id) {
            // Count replies
            $replies = array_filter($comments, fn($reply) => $reply['parent_id'] == $c['id']);
            $reply_count = count($replies);

            echo "<div style='margin-left:" . ($depth * 20) . "px; 
                          border-left:1px solid #ccc; 
                          padding-left:10px; 
                          margin-top:5px;'>";

            echo "<strong>" . htmlspecialchars($c['user_name']) . ":</strong> " 
                 . nl2br(htmlspecialchars($c['content']));

            echo "<div><a href='#' class='reply-link' data-comment-id='{$c['id']}'>Reply</a></div>";

            // Hidden reply form
            echo "<form class='reply-form' id='reply-form-{$c['id']}' 
                        action='comment.php' method='POST' 
                        style='display:none; margin-top:5px;'>
                    <input type='hidden' name='post_id' value='{$c['post_id']}'>
                    <input type='hidden' name='parent_id' value='{$c['id']}'>
                    <textarea name='comment' required></textarea>
                    <button type='submit'>Reply</button>
                  </form>";

            // Replies toggle button
            if ($reply_count > 0) {
                echo "<a href='#' class='toggle-replies' 
                        data-target='replies-{$c['id']}' 
                        style='color:green; display:block; margin-top:5px;'>
                        🔽 View {$reply_count} " . ($reply_count === 1 ? "Reply" : "Replies") . "
                      </a>";
            }

            // Replies container
            echo "<div class='replies' id='replies-{$c['id']}' style='display:none;'>";
            showComments($comments, $c['id'], $depth + 1);
            echo "</div>";

            echo "</div>";
        }
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
        </a>
    </div>

    <?php if (!empty($post['video_path'])): ?>
        <video width="100%" autoplay controls>
            <source src="<?= htmlspecialchars($post['video_path']); ?>" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    <?php endif; ?>

    <h2><?= htmlspecialchars($post['user_name']); ?>'s Post</h2>
    <p><?= nl2br(htmlspecialchars($post['content'])); ?></p>
    <p><small>📅 Posted on: <?= $post['created_at']; ?></small></p>

    <hr>

    <!-- 💬 New Comment Form -->
    <form action="comment.php" method="POST" class="comment-form">
        <input type="hidden" name="post_id" value="<?= $post['id']; ?>">
        <input type="text" name="comment" placeholder="Write a comment..." required>
        <button type="submit">💬 Comment</button>
    </form>

    <!-- 💬 Comments Section -->
    <h3>Comments</h3>
    <?php showComments($comments); ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Toggle reply form
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
                this.innerText = this.innerText.replace(
                    visible ? '🔼 Hide' : '🔽 View',
                    visible ? '🔽 View' : '🔼 Hide'
                );
            }
        });
    });

    // Auto-open replies (if `open_reply` param is present)
    <?php if (!empty($open_reply_id)) : ?>
    const replyContainer = document.getElementById('replies-<?= $open_reply_id ?>');
    const toggleButton = document.querySelector('[data-target="replies-<?= $open_reply_id ?>"]');
    if (replyContainer && toggleButton) {
        replyContainer.style.display = 'block';
        toggleButton.innerText = toggleButton.innerText.replace('🔽 View', '🔼 Hide');
    }
    <?php endif; ?>
});
</script>
</body>
</html>
