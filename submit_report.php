<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['report_post_id'])) {
    header("Location: LeagueBook.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$post_id = $_SESSION['report_post_id'];
$reason = trim($_POST['reason'] ?? '');

if ($reason !== '') {
    $stmt = $conn->prepare("INSERT INTO reports (post_id, user_id, reason) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $post_id, $user_id, $reason);
    if ($stmt->execute()) {
        unset($_SESSION['report_post_id']);
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Report Submitted</title>
            <meta http-equiv="refresh" content="2;url=LeagueBook_Page.php" />
            <style>
                body {
                    font-family: 'Segoe UI', sans-serif;
                    background-color: #f4f4f8;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                }
                .checkmark {
                    font-size: 80px;
                    color: green;
                    animation: pop 0.5s ease;
                }
                .message {
                    font-size: 20px;
                    margin-top: 10px;
                    color: #333;
                }
                @keyframes pop {
                    0% { transform: scale(0.5); opacity: 0; }
                    100% { transform: scale(1); opacity: 1; }
                }
            </style>
        </head>
        <body>
            <div class="checkmark">✅</div>
            <div class="message">Report submitted successfully! Redirecting...</div>
        </body>
        </html>
        <?php
        exit();
    } else {
        echo "❌ Failed to submit report.";
    }
} else {
    echo "⚠️ Please provide a reason.";
}
?>
