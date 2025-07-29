<?php
session_start();
include("connect.php");

$message = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($email) || empty($newPassword) || empty($confirmPassword)) {
        $message = "⚠️ Missing fields.";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "❌ Passwords do not match.";
    } elseif (strlen($newPassword) < 6) {
        $message = "❌ Password must be at least 6 characters.";
    } else {
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashedPassword, $email);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $success = true;
            $message = "✅ Password successfully updated.";
            unset($_SESSION['reset_email']);
        } else {
            $message = "❌ Failed to update password or user not found.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Password Reset Status</title>
  <link rel="stylesheet" href="LeagueBook.css">
</head>
<body>
  <<header class="header">
  <nav class="nav">
    <ul class="facebook_login">
      <li class="League_Book"><h1>LeagueBook</h1></li>
    </ul>
    <span class="League_Book1">
      Connect with League of Legends Players <br> around the world on LeagueBook
    </span>
  </nav>
</header>

  <div class="form_container">
    <h2 style="color: white;">Password Reset</h2>

    <?php if (!empty($message)): ?>
      <div class="<?php echo $success ? 'success_message' : 'error_message'; ?>">
        <?php echo $message; ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="login_signup">
        <a id="signup" href="LeagueBook.php">🔐 Go to Login</a>
      </div>
    <?php else: ?>
      <div class="login_signup">
        <a id="signup" href="reset_password.php">🔁 Try Again</a>
      </div>
    <?php endif; ?>
  </div>

</body>
</html>
