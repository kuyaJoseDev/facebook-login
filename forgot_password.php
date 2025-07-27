<?php if (isset($_GET['error'])): ?>
  <div class="error_message">
    <?php
      switch ($_GET['error']) {
        case 'empty': echo 'Please enter your email address.'; break;
        case 'notfound': echo 'No account found with that email.'; break;
        default: echo 'Something went wrong.'; break;
      }
    ?>
  </div>
<?php endif; ?>

<?php if (isset($_GET['success']) && $_GET['success'] === 'sent'): ?>
  <div class="success_message">
    A password reset link has been sent to your email.
  </div>
<?php endif; ?>



<!-- forgot_password.php -->

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password</title>
  <link rel="stylesheet" href="LeagueBook.css">
</head>
<body>
  <div class="form_container">
    <form action="send_reset.php" method="POST">
      <h2>Reset Your Password</h2>
      <div class="input_box">
        <input type="email" name="email" placeholder="Enter your registered email" required />
      </div>
      <button class="button" type="submit">Send Reset Link</button>
    </form>
  </div>
</body>
</html>
