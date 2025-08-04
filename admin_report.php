<?php
session_start();
include("connect.php");

// ✅ Admin access check
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    die("Unauthorized access");
}

// ✅ Fetch reports and related data
$sql = "
  SELECT r.id AS report_id, r.post_id, r.reason, r.reported_at,
         u.name AS reporter_name,
         p.content AS post_content, p.user_id AS post_owner_id,
         pu.name AS post_owner_name
  FROM reports r
  JOIN users u ON r.reported_by = u.id
  JOIN posts p ON r.post_id = p.id
  JOIN users pu ON p.user_id = pu.id
  ORDER BY r.reported_at DESC
";

$result = $conn->query($sql);
if (!$result) {
    die("Query error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html>
<head>
  <link rel="stylesheet" href="LeagueBook_Page.css" />
  <title>Admin Panel - Reports</title>
  <style>
    table {
      width: 100%;
      border-collapse: collapse;
    }

    th, td {
      padding: 8px;
      border: 1px solid #ccc;
    }

    th {
      background-color: #f2f2f2;
    }

    button {
      padding: 5px 10px;
    }
  </style>
</head>
<body>
 <div style="margin-top: 20px;">
        <a href="LeagueBook_Page.php">
            <button class="button">⬅ Back to Home</button>
        </a>
    </div>
<h2>🚨 Reported Posts</h2>

<table>
  <tr>
    <th>Post ID</th>
    <th>Content</th>
    <th>Reported By</th>
    <th>Reported At</th>
    <th>Action</th>
  </tr>
  <?php while ($row = $result->fetch_assoc()): ?>
  <tr>
    <td><?= $row['post_id'] ?></td>
    <td><?= htmlspecialchars($row['post_content']) ?></td>
    <td><?= htmlspecialchars($row['reporter_name']) ?></td>
    <td><?= $row['reported_at'] ?></td>
    <td>
      <form method="POST" action="handle_report_action.php" onsubmit="return confirm('Are you sure?');" style="display:inline;">
        <input type="hidden" name="post_id" value="<?= $row['post_id'] ?>">
        <input type="hidden" name="report_id" value="<?= $row['report_id'] ?>">
        <input type="hidden" name="action" value="delete">
        <button type="submit" style="color: red;">❌ Delete Post</button>
      </form>

      <form method="POST" action="handle_report_action.php" onsubmit="return confirm('Dismiss this report?');" style="display:inline;">
        <input type="hidden" name="report_id" value="<?= $row['report_id'] ?>">
        <input type="hidden" name="action" value="dismiss">
        <button type="submit">✅ Dismiss</button>
      </form>
    </td>
  </tr>
  <?php endwhile; ?>
</table>


</body>
</html>
