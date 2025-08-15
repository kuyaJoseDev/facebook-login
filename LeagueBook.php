<?php
session_start();
include("connect.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    // 🔐 SIGN UP
    if ($action === 'signup') {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';
        

        if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
            header("Location: LeagueBook.php?error=empty");
            exit();
        }

        if ($password !== $confirm) {
            header("Location: LeagueBook.php?error=nomatch");
            exit();
        }

        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();
        if ($result->num_rows > 0) {
            header("Location: LeagueBook.php?error=exists");
            exit();
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $hashedPassword);

        if ($stmt->execute()) {
            header("Location: LeagueBook.php?success=signup");
            exit();
        } else {
            header("Location: LeagueBook.php?error=sql");
            exit();
        }
    }

    // 🔐 LOGIN
    if ($action === 'login') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
               $_SESSION['user_id'] = $user['id'];
               $_SESSION['email'] = $user['email'];
               $_SESSION['user_name'] = $user['name'];
               $_SESSION['is_admin'] = $user['is_admin']; // ✅ Must be set here
               
                header("Location: LeagueBook_Page.php");
                exit();
            }
        }

        header("Location: LeagueBook.php?error=invalid");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>LeagueBook Login</title>
  <link rel="stylesheet" href="LeagueBook.css" />
  <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css"/>
  <style>
    .input_box {
      position: relative;
    }
    .input_box .pw_toggle {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #555;
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

  <!-- 🔐 LOGIN FORM -->
  <div class="form login_form" id="login_form">
    <form action="LeagueBook.php" method="POST">
      <input type="hidden" name="action" value="login" />

      <?php if (isset($_GET['error'])): ?>
        <div class="error_message">
          <?php
          switch ($_GET['error']) {
            case 'invalid': echo 'Incorrect email or password.'; break;
            case 'empty': echo 'All fields are required.'; break;
            case 'nomatch': echo 'Passwords do not match.'; break;
            case 'exists': echo 'Email is already registered.'; break;
            case 'sql': echo 'Database error. Please try again.'; break;
            default: echo 'An unknown error occurred.'; break;
          }
          ?>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['success']) && $_GET['success'] === 'signup'): ?>
        <div class="success_message">Account created successfully! You can now log in.</div>
      <?php endif; ?>

      <div class="input_box">
        <input type="email" name="email" placeholder="Enter your email address" required />
      
      </div>
        <i class="uil uil-envelope-alt email"></i>
      <div class="input_box">
        <input type="password" name="password" id="login_password" placeholder="Enter your password" required />

      </div>
      <i class="uil uil-lock password"></i>
        <i class="uil uil-eye pw_toggle" toggle="login_password"></i>

      <button class="button" type="submit">Login</button>

      <a href="forgot_password.php">Forgot Password?</a>
      <hr class="divider" />
      <div class="login_signup">
        <a href="#" id="show_signup">Create New Account</a>
      </div>
    </form>
  </div>

  <!-- 📝 SIGN UP FORM -->
  <div class="form signup_form" id="signup_form" style="display: none;">
    <form action="LeagueBook.php" method="POST">
      <input type="hidden" name="action" value="signup" />
      <div class="input_box">
        <input type="text" name="name" placeholder="Enter your name" required />
      </div>
       <i class="uil uil-user"></i>
      <div class="input_box">
        <input type="email" name="email" placeholder="Enter your email address" required />   
      </div>
      <i class="uil uil-envelope-alt email"></i>
      <div class="input_box">
        <input type="password" name="password" id="signup_password" placeholder="Create a password" required />
      </div>
         <i class="uil uil-lock password"></i>
        <i class="uil uil-eye pw_toggle" toggle="signup_password"></i>
      <div class="input_box">
        <input type="password" name="confirm" id="signup_confirm" placeholder="Confirm your password" required />
      </div>
      <i class="uil uil-lock password"></i>
        <i class="uil uil-eye pw_toggle" toggle="signup_confirm"></i>

      <button class="button" type="submit">Sign Up</button>
      <div class="login_signup">
        <p>Already have an account? <a href="#" id="show_login">Login</a></p>
      </div>
    </form>
  </div>
</div>

<!-- ✅ Scripts -->
<script>
  // Toggle Login/Signup Form
  document.getElementById('show_signup').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('login_form').style.display = 'none';
    document.getElementById('signup_form').style.display = 'block';
  });

  document.getElementById('show_login').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('login_form').style.display = 'block';
    document.getElementById('signup_form').style.display = 'none';
  });

  // Toggle Password Visibility
  document.querySelectorAll('.pw_toggle').forEach(function(icon) {
  icon.addEventListener('click', function() {
    const targetId = this.getAttribute('toggle');
    const input = document.getElementById(targetId);
    const isPassword = input.type === "password";
    input.type = isPassword ? "text" : "password";
    this.classList.toggle('uil-eye');
    this.classList.toggle('uil-eye-slash');
    
  });
});
  // Auto fade out success message after 3 seconds
  window.addEventListener('DOMContentLoaded', () => {
    const successMsg = document.querySelector('.success_message');
    if (successMsg) {
      setTimeout(() => {
        successMsg.style.opacity = '0';
        // Optional: remove from DOM after fade
        setTimeout(() => successMsg.remove(), 1000);
      }, 3000); // 3 seconds before fade
    }
  });



</script>

</body>
</html>


