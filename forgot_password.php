<?php
// Show messages for error/success if redirected back
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password</title>
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
  <?php if (isset($_GET['error'])): ?>
    <div class="error_message">
      <?php
        switch ($_GET['error']) {
          case 'empty': echo '⚠️ Please enter your email address.'; break;
          case 'notfound': echo '❌ No account found with that email.'; break;
          default: echo '❌ Something went wrong.'; break;
        }
      ?>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['success']) && $_GET['success'] === 'sent'): ?>
    <div class="success_message">
      ✅ A password reset link has been sent to your email.
    </div>
  <?php endif; ?>

  <div class="form_container">
    <form action="send_reset.php" method="POST">
      <h2 style="color: white;">Reset Your Password</h2>
      <div class="input_box">
        <input type="email" name="email" placeholder="Enter your registered email" required />
      </div>
      <button class="button" type="submit">Send Reset Link</button>
      <div class="forgot_container" style="margin-top: 15px;">
        <a id="forgot_password" href="LeagueBook.php">⬅ Back to Login</a>
      </div>
    </form>
  </div>
</body>
</html>
