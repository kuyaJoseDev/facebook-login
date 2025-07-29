<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['report_post_id'])) {
    header("Location: LeagueBook.php");
    exit();
}
?>

<form action="submit_report.php" method="POST">
    <h2>Why are you reporting this post?</h2>
    <textarea name="reason" required></textarea><br>
    <button type="submit">Submit Report</button>
</form>
