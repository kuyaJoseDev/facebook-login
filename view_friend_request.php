<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: LeagueBook.php");
    exit();
}

$userName = $_SESSION['user_name'] ?? 'Guest';
$userId = $_SESSION['user_id'];

// Fetch pending friend requests
$stmt = $conn->prepare("
    SELECT fr.id AS request_id, fr.created_at, u.id AS sender_id, u.name, u.email 
    FROM friend_requests fr 
    JOIN users u ON fr.sender_id = u.id 
    WHERE fr.receiver_id = ? AND fr.status = 'pending'
    ORDER BY fr.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Pending Friend Requests</title>
    <link rel="stylesheet" href="LeagueBook_Page.css" />
</head>
<body>
<div class="main-container">
    <h2>📬 Pending Friend Requests for <?php echo htmlspecialchars($userName); ?></h2>
    <hr>

    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="post-box friend-request-box">
                <p>
                    <strong>
                        <a href="view_profile.php?id=<?php echo (int)$row['sender_id']; ?>">
                            <?php echo htmlspecialchars($row['name']); ?>
                        </a>
                    </strong>
                    (<span class="email"><?php echo htmlspecialchars($row['email']); ?></span>)<br>
                    <small>🕒 Requested on: <?php echo date("F j, Y g:i A", strtotime($row['created_at'])); ?></small>
                </p>
                <form method="post" action="respond_friend_request.php" style="display:inline;">
                    <input type="hidden" name="request_id" value="<?php echo $row['request_id']; ?>">
                    <button type="submit" name="action" value="accept">✅ Accept</button>
                    <button type="submit" name="action" value="decline">❌ Decline</button>
                </form>
            </div>
            <hr>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No pending friend requests.</p>
    <?php endif; ?>

    <div style="margin-top: 20px;">
        <a href="LeagueBook_Page.php">
            <button class="button">⬅ Back to Home</button>
        </a>
    </div>
</div>
</body>
</html>
