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

        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();
        if ($result->num_rows > 0) {
            header("Location: LeagueBook.php?error=exists");
            exit();
        }

        // Save new user
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
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name']; // ✅ store user name
                header("Location: LeagueBook_Page.php");
                exit();
            }
        }

        // Login failed
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
        <i class="uil uil-envelope-alt email"></i>
      </div>
      <div class="input_box">
        <input type="password" name="password" placeholder="Enter your password" required />
        <i class="uil uil-lock password"></i>
        <i class="uil uil-eye-slash pw_hide"></i>
      </div>

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
  <i class="uil uil-user"></i>
</div>
      <div class="input_box">
        <input type="email" name="email" placeholder="Enter your email address" required />
        <i class="uil uil-envelope-alt email"></i>
      </div>
      <div class="input_box">
        <input type="password" name="password" placeholder="Create a password" required />
        <i class="uil uil-lock password"></i>
        <i class="uil uil-eye-slash pw_hide"></i>
      </div>
      <div class="input_box">
        <input type="password" name="confirm" placeholder="Confirm your password" required />
        <i class="uil uil-lock password"></i>
        <i class="uil uil-eye-slash pw_hide"></i>
      </div>

      <button class="button" type="submit">Sign Up</button>

      <div class="login_signup">
        <p>Already have an account? <a href="#" id="show_login">Login</a></p>
      </div>
    </form>
  </div>
</div>

<script>
  const showSignup = document.getElementById('show_signup');
  const showLogin = document.getElementById('show_login');
  const loginForm = document.getElementById('login_form');
  const signupForm = document.getElementById('signup_form');

  showSignup.addEventListener('click', function (e) {
    e.preventDefault();
    loginForm.style.display = 'none';
    signupForm.style.display = 'block';
  });

  showLogin.addEventListener('click', function (e) {
    e.preventDefault();
    loginForm.style.display = 'block';
    signupForm.style.display = 'none';
  });
</script>
</body>
</html>
