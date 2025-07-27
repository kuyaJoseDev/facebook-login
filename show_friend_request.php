<?php
session_start();
include("connect.php");

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT u.id, u.email
    FROM users u
    JOIN friend_requests f ON (
        (f.sender_id = ? AND f.receiver_id = u.id) OR
        (f.receiver_id = ? AND f.sender_id = u.id)
    )
    WHERE f.status = 'accepted'
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<h2>Your Friends</h2>
<ul>
<?php while ($row = $result->fetch_assoc()): ?>
  <li><?php echo htmlspecialchars($row['email']); ?></li>
<?php endwhile; ?>
</ul>
