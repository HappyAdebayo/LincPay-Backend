<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php'; 

function generateUniqueCode($conn, $length = 4) {
    do {
        $code = str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare("SELECT 1 FROM password_reset_code WHERE code = ? AND expired_at > NOW()");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
    } while ($exists);

    return $code;
}

function sendPasswordResetCode($conn, $data) {
    $email = $data['email'];
    
    // 1. Get the user ID and name
    $stmt = $conn->prepare("SELECT u.id, u.username FROM user u JOIN student_profiles sp ON sp.user_id = u.id WHERE sp.email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return ['status' => 'error', 'message' => 'Email not found'];
    }

    $userId = $user['id'];
    $userName = $user['username'];
    $code = generateUniqueCode($conn);

    $expiredAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

    $stmt = $conn->prepare("SELECT id FROM password_reset_code WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $stmt = $conn->prepare("UPDATE password_reset_code SET code = ?, expired_at = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $code, $expiredAt, $userId);
    } else {
        $stmt = $conn->prepare("INSERT INTO password_reset_code (user_id, code, expired_at) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userId, $code, $expiredAt);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return ['status' => 'error', 'message' => 'Failed to save reset code: ' . $conn->error];
    }
    $stmt->close();

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ogbenihappy05@gmail.com';
        $mail->Password   = 'agkevunlkbqmyuyt';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('your_email@example.com', 'LincPay');
        $mail->addAddress($email, $userName);

        $mail->isHTML(true);
        $mail->Subject = 'Your Password Reset Code';
        $mail->Body    = "Hello <strong>{$userName}</strong>,<br><br>Your password reset code is: <strong>{$code}</strong><br>This code will expire in 30 minutes.<br><br>If you did not request a password reset, please ignore this email.";
        $mail->AltBody = "Hello {$userName},\n\nYour password reset code is: {$code}\nThis code will expire in 30 minutes.\n\nIf you did not request a password reset, please ignore this email.";

        $mail->send();

        return ['status' => 'success', 'message' => 'Password reset code sent successfully.'];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'Email could not be sent. Mailer Error: ' . $mail->ErrorInfo];
    }
}


function resetPasswordWithCode($conn, $data) {
    $code = $data['code'];
    $newPassword = $data['new_password'];

    $stmt = $conn->prepare("SELECT user_id FROM password_reset_code WHERE code = ? AND expired_at > NOW()");
    if (!$stmt) {
        return ['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error];
    }

    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $resetRow = $result->fetch_assoc();
    $stmt->close();

    if (!$resetRow) {
        return ['status' => 'error', 'message' => 'Invalid or expired code.'];
    }

    $userId = $resetRow['user_id'];

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE user SET password = ? WHERE id = ?");
    if (!$stmt) {
        return ['status' => 'error', 'message' => 'Prepare failed (update password): ' . $conn->error];
    }
    $stmt->bind_param("si", $hashedPassword, $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['status' => 'error', 'message' => 'Failed to update password: ' . $stmt->error];
    }
    $stmt->close();

    // 4. Delete the used reset code
    $stmt = $conn->prepare("DELETE FROM password_reset_code WHERE code = ?");
    if (!$stmt) {
        return ['status' => 'error', 'message' => 'Prepare failed (delete code): ' . $conn->error];
    }
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $stmt->close();

    return ['status' => 'success', 'message' => 'Password has been reset successfully.'];
}

function resendPasswordResetCode($conn, $data) {
    $email = $data['email'];

    // 1. Get the user ID and username
    $stmt = $conn->prepare("SELECT u.id, u.username FROM user u JOIN student_profiles sp ON sp.user_id = u.id WHERE sp.email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return ['status' => 'error', 'message' => 'Email not found.'];
    }

    $userId = $user['id'];
    $userName = $user['username'];
    $code = generateUniqueCode($conn);
    $expiredAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

    // 2. Update the reset code and expiration
    $stmt = $conn->prepare("UPDATE password_reset_code SET code = ?, expired_at = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $code, $expiredAt, $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['status' => 'error', 'message' => 'Failed to update reset code: ' . $conn->error];
    }
    $stmt->close();

    // 3. Resend the email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ogbenihappy05@gmail.com';
        $mail->Password   = 'agkevunlkbqmyuyt';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('your_email@example.com', 'LincPay');
        $mail->addAddress($email, $userName);

        $mail->isHTML(true);
        $mail->Subject = 'Your Password Reset Code (Resent)';
        $mail->Body    = "Hello <strong>{$userName}</strong>,<br><br>Your new password reset code is: <strong>{$code}</strong><br>This code will expire in 30 minutes.<br><br>If you did not request this, please ignore this email.";
        $mail->AltBody = "Hello {$userName},\n\nYour new password reset code is: {$code}\nThis code will expire in 30 minutes.\n\nIf you did not request this, please ignore this email.";

        $mail->send();

        return ['status' => 'success', 'message' => 'Reset code resent successfully.'];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'Resend failed. Mailer Error: ' . $mail->ErrorInfo];
    }
}
