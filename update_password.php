<?php
session_start();
include("connect.php"); // ← your DB connection

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // ⚠️ Validation check
    if (empty($email) || empty($newPassword) || empty($confirmPassword)) {
        die("Missing fields.");
    }

    if ($newPassword !== $confirmPassword) {
        die("Passwords do not match.");
    }

    // ✅ Optional: Password strength check
    if (strlen($newPassword) < 6) {
        die("Password must be at least 6 characters.");
    }

    // ✅ Hash password and update
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $hashedPassword, $email);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo "✅ Password successfully updated. <a href='LeagueBook.php'>Login</a>";
        unset($_SESSION['reset_email']);
    } else {
        echo "❌ Failed to update password or user not found.";
    }
}
?>
