<?php
session_start();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['otp']) || empty($_POST['otp'])) {
        $message = '⚠️ Missing OTP';
    } elseif (!isset($_SESSION['otp']) || !isset($_SESSION['otp_expiry'])) {
        $message = '⚠️ OTP Invalid or missing.';
    } elseif (time() > $_SESSION['otp_expiry']) {
        $message = '⚠️ OTP expired.';
    } elseif ($_POST['otp'] !== $_SESSION['otp']) {
        $message = '❌ Incorrect OTP.';
    } else {
        // ✅ OTP verified — redirect to password reset form
        $_SESSION['reset_email'] = $_SESSION['otp_email'];

        unset($_SESSION['otp']);
        unset($_SESSION['otp_expiry']);

        header("Location: reset_password.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Verify OTP</title>
  <link rel="stylesheet" href="LeagueBook.css">
</head>
<body>
  <div class="form_container">
    <form action="verify_otp.php" method="POST">
      <h2>Enter OTP</h2>
      <?php if (!empty($message)): ?>
        <p style="color: red;"><?php echo $message; ?></p>
      <?php endif; ?>
      <div class="input_box">
        <input type="text" name="otp" placeholder="Enter the OTP sent to your email" required />
      </div>
      <button class="button" type="submit">Verify</button>
    </form>
  </div>
</body>
</html>
