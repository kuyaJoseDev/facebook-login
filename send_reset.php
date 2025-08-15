<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

include("connect.php");
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'] ?? '';

    if (empty($email)) {
        header("Location: forgot_password.php?error=empty");
        exit();
    }

    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        // ✅ Generate OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    
        // ✅ Save OTP in session
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_email'] = $email;
        $_SESSION['otp_expiry'] = time() + 300; // 5 min

        // ✅ Send email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'miguelajosefernan@gmail.com'; // ✅ Your Gmail
            $mail->Password = 'hcmo ctri mssh llyf'; // ✅ App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('miguelajosefernan@gmail.com', 'LeagueBook');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'LeagueBook OTP Verification';
            $mail->Body = "<h3>Your OTP is: <strong>$otp</strong></h3><p>This code expires in 5 minutes.</p>";

            $mail->send();

            // ✅ Now redirect only after email is sent
            header("Location: verify_otp.php");
            exit();
        } catch (Exception $e) {
            echo "Mailer Error: " . $mail->ErrorInfo;
        }

    } else {
       // After sending the reset email
header("Location: forgot_password.php?success=sent");
exit();
    }
}
?>
