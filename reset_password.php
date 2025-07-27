<?php
session_start();

if (!isset($_SESSION['reset_email'])) {
    // No email from OTP — go back to forgot password
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION['reset_email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password</title>
  <link rel="stylesheet" href="LeagueBook.css">
</head>
<body>
  <div class="form_container">
    <form action="update_password.php" method="POST">
      <h2>Reset Password</h2>
      <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
      <div class="input_box">
        <input type="password" name="new_password" placeholder="New Password" required>
      </div>
      <div class="input_box">
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
      </div>
      <button class="button" type="submit">Update Password</button>
    </form>
  </div>
</body>
</html>
