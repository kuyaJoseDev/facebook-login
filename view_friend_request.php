<?php
session_start();
include("connect.php");

// Get logged-in user ID
$user_id = $_SESSION['user_id'] ?? 0;

if (!$user_id) {
    die("Please log in to view friend requests.");
}

// Fetch pending friend requests sent to this user
$stmt = $conn->prepare("
    SELECT fr.id AS request_id, u.id AS sender_id, u.email AS sender_email
    FROM friend_requests fr
    JOIN users u ON fr.sender_id = u.id
    WHERE fr.receiver_id = ? AND fr.status = 'pending'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

?>
<?php if (isset($_GET['success'])): ?>
  <p style="color: green;">Friend request <?php echo htmlspecialchars($_GET['success']); ?>.</p>
<?php endif; ?>

<!DOCTYPE html>
<html>
<head>
    <title>Friend Requests</title>
    <link rel="stylesheet" href="LeagueBook.css">
</head>
<body>
    <div class="form_container">
        <h2>📬 Pending Friend Requests</h2>

        <?php if (isset($_GET['success'])): ?>
            <p style="color: green;">Request <?php echo htmlspecialchars($_GET['success']); ?>!</p>
        <?php endif; ?>

        <?php if ($result->num_rows === 0): ?>
            <p>No pending friend requests.</p>
        <?php else: ?>
            <ul>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($row['sender_email']); ?></strong>
                        <form action="respond_friend_request.php" method="POST" style="display:inline;">
                            <input type="hidden" name="request_id" value="<?php echo $row['request_id']; ?>">
                            <button type="submit" name="action" value="accept">✅ Accept</button>
                            <button type="submit" name="action" value="decline">❌ Decline</button>
                        </form>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php endif; ?>
    </div>
    <a href="LeagueBook_Page.php">
  <button class="button">🏠 Back to LeagueBook</button>
</a>

</body>
</html>
