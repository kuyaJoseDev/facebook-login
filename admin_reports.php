<?php
include("connect.php");
$result = $conn->query("SELECT reports.*, posts.content AS post_content, users.name AS reporter
    FROM reports
    JOIN posts ON reports.post_id = posts.id
    JOIN users ON reports.user_id = users.id
    ORDER BY report_date DESC");

while ($row = $result->fetch_assoc()) {
    echo "<h3>Reported by: {$row['reporter']}</h3>";
    echo "<p>Post: {$row['post_content']}</p>";
    echo "<p>Reason: {$row['reason']}</p>";
    echo "<hr>";
}
?>
