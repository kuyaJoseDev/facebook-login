<?php
session_start();

$message = '';
$success_message = '';

// Show success message if redirected from forgot_password.php
if (isset($_GET['success']) && $_GET['success'] === 'sent') {
    $success_message = '✅ The verification code has been sent to your email.';
}

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

        unset($_SESSION['otp'], $_SESSION['otp_expiry']);

        header("Location: reset_password.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify OTP</title>
  <link rel="stylesheet" href="LeagueBook.css">
  <style>
    .success_message {
      color: #4CAF50;
      font-weight: bold;
      text-align: center;
      margin-bottom: 15px;
      font-size: 14px;
    }
  </style>
</head>
<body>
<header class="header">
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
  <form action="verify_otp.php" method="POST">
    <h2 style="color: white;">Verify Your OTP</h2>

    <!-- ✅ Success message -->
    <?php if (!empty($success_message)): ?>
      <div class="success_message"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <!-- ❌ Error message -->
    <?php if (!empty($message)): ?>
      <div class="error_message"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="input_box">
      <input type="text" name="otp" placeholder="Enter the OTP sent to your email" required>
    </div>

    <button class="button" type="submit">Verify</button>

    <div class="forgot_container" style="margin-top: 15px;">
      <a id="forgot_password" href="forgot_password.php">⬅ Back to Forgot Password</a>
    </div>
  </form>
</div>
</body>
</html>
