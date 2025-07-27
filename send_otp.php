<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Generate OTP
$otp = rand(100000, 999999);
$email = $_POST['email']; // Email input from form

// Save OTP in session or database here
$_SESSION['otp'] = $otp;

$mail = new PHPMailer(true);
try {
    // Server settings
   $mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = 'yourgmail@gmail.com';       // ✅ Your Gmail
$mail->Password = 'your_app_password_here';    // ✅ App password only
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = 587;


    // Recipients
    $mail->setFrom('yourgmail@gmail.com', 'LeagueBook Admin');
    $mail->addAddress($email);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Your OTP Code';
    $mail->Body    = "Your OTP is: <b>$otp</b>";

    $mail->send();
    echo "OTP has been sent to your email.";
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
?>
