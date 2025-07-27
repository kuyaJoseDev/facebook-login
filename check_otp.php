<?php
session_start();
$userOtp = $_POST['otp'] ?? '';
$sessionOtp = $_SESSION['otp'] ?? '';
$expires = $_SESSION['otp_expiry'] ?? 0;

if (time() > $expires) {
    echo "❌ OTP expired. Please request again.";
    session_destroy();
    exit;
}

if ($userOtp === $sessionOtp) {
    // Verified — proceed to reset password
    header("Location: reset_password.php");
    exit;
} else {
    echo "❌ Invalid OTP.";
}
?>
