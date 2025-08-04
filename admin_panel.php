<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    die("⛔ Access Denied. Admins only.");
}

// Fetch reported posts
$query = "
  SELECT r.id AS report_id, r.post_id, r.reason, r.reported_at,
         u.name AS reporter_name,
         p.content AS post_content, p.user_id AS post_owner,
         pu.name AS post_owner_name
  FROM reports r
  JOIN users u ON r.reporter_id = u.id
  JOIN posts p ON r.post_id = p.id
  JOIN users pu ON p.user_id = pu.id
  ORDER BY r.reported_at DESC
";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Admin Report Panel</title>
  <style>
    body { font-family: Arial; background: #f4f4f4; padding: 20px; }
    .report-box {
      background: white;
      border: 1px solid #ccc;
      padding: 15px;
      margin-bottom: 15px;
      border-radius: 10px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .admin-action-btns button {
      margin-right: 10px;
      padding: 5px 10px;
    }
  </style>
</head>
<body>

  <h2>🚨 Reported Posts</h2>

  <?php if ($result->num_rows === 0): ?>
    <p>No reports found.</p>
  <?php else: ?>
    <?php while ($row = $result->fetch_assoc()): ?>
      <div class="report-box">
        <strong>Reported By:</strong> <?= htmlspecialchars($row['reporter_name']) ?><br>
        <strong>Post Owner:</strong> <?= htmlspecialchars($row['post_owner_name']) ?><br>
        <strong>Reason:</strong> <?= htmlspecialchars($row['reason']) ?><br>
        <strong>Reported At:</strong> <?= $row['reported_at'] ?><br>
        <hr>
        <strong>Post Content:</strong><br>
        <div style="margin: 10px 0;"><?= nl2br(htmlspecialchars($row['post_content'])) ?></div>

        <div class="admin-action-btns">
          <form method="POST" action="admin_action.php" style="display: inline;">
            <input type="hidden" name="report_id" value="<?= $row['report_id'] ?>">
            <input type="hidden" name="post_id" value="<?= $row['post_id'] ?>">
            <button name="action" value="dismiss">✅ Dismiss</button>
            <button name="action" value="delete">🗑️ Delete Post</button>
          </form>
        </div>
      </div>
    <?php endwhile; ?>
  <?php endif; ?>

</body>
</html>
