<?php
function validateLoginDetails($username, $password) {
    $username = trim($username);
    $password = trim($password);

    $username = strtolower($username);
    $password = strtolower($password);

    if (empty($username)) {
        return ["status" => "error", "message" => "Username is required"];
    } elseif (strlen($username) < 3) {
        return ["status" => "error", "message" => "Username must be at least 3 characters."];
    }

    if (empty($password)) {
        return ["status" => "error", "message" => "Password is required"];
    } elseif (strlen($password) < 6) {
        return ["status" => "error", "message" => "Password must be at least 6 characters."];
    }

    return ["status" => "success"];

}

function validateSignup($username, $password, $conn) {
    $username = trim($username);
    $password = trim($password);

    $username = strtolower($username);
    $password = strtolower($password);

    if (empty($username) || empty($password)) {
        return ["status" => "error", "message" => "Username and password are required"];
    }

    if (strlen($username) < 3) {
        return ["status" => "error", "message" => "Username must be at least 3 characters."];
    }

    if (strlen($password) < 6) {
        return ["status" => "error", "message" => "Password must be at least 6 characters"];
    }

    $stmt = $conn->prepare("SELECT id FROM user WHERE username = ?");
    if (!$stmt) {
        return ["status" => "error", "message" => "Prepare failed: " . $conn->error];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        return ["status" => "error", "message" => "Username already taken"];
    }

    $stmt->close();
    return ["status" => "success"];
}

function validateStudentProfile($data, $files, $conn) {
    $errors = [];

    $fullName = trim($data['fullName'] ?? '');
    $studentId = trim($data['studentId'] ?? '');
    $semester = trim($data['semester'] ?? '');
    $email = trim($data['email'] ?? '');

    if (empty($fullName)) $errors['fullName'] = "Full Name is required";
    if (empty($studentId)) $errors['studentId'] = "Student ID is required";
    if (empty($semester)) $errors['semester'] = "Semester is required";

    if (empty($email)) {
        $errors['email'] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    }

    $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE email = ?");
    if (!$stmt) {
        return ["status" => "error", "message" => "Prepare failed: " . $conn->error];
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors['email'] = "Email already exists";
    }
    $stmt->close();

    if (!isset($files['image']) || $files['image']['error'] !== UPLOAD_ERR_OK) {
        $errors['image'] = "Profile image is required";
    } else {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($files['image']['type'], $allowedTypes)) {
            $errors['image'] = "Invalid image type. Allowed: jpg, png, gif";
        }

        if ($files['image']['size'] > 5 * 1024 * 1024) { 
            $errors['image'] = "Image size must be less than 2MB";
        }
    }

    if (!empty($errors)) {
        return ["status" => "error", "errors" => $errors];
    }

    return ["status" => "success"];
}


function validateUpdateProfile($data, $files) {
    $errors = [];

    $full_name = trim($data['full_name'] ?? '');
    $student_id = trim($data['student_id'] ?? '');
    $semester = trim($data['semester'] ?? '');
    $email = trim($data['email'] ?? '');
    $existing_image = trim($data['existing_image'] ?? '');

    if (empty($full_name)) {
        $errors['full_name'] = "Full Name is required";
    } elseif (strlen($full_name) < 3) {
        $errors['full_name'] = "Full name must be at least 3 characters";
    }

    if (empty($student_id)) {
        $errors['student_id'] = "Student ID is required";
    }

    if (empty($semester)) {
        $errors['semester'] = "Semester is required";
    }

    if (empty($email)) {
        $errors['email'] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    }

    error_log('Existing image filename: ' . ($existing_image ?: 'EMPTY'));

    if (isset($files['profile_image']) && $files['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 5 * 1024 * 1024; 

        $fileType = $files['profile_image']['type'];
        $fileSize = $files['profile_image']['size'];

        if (!in_array($fileType, $allowedTypes)) {
            $errors['profile_image'] = "Only JPG and PNG images are allowed";
        }

        if ($fileSize > $maxSize) {
            $errors['profile_image'] = "Image size must not exceed 2MB";
        }
    } elseif (empty($existing_image)) {
        $errors['profile_image'] = "Profile image is required";
    }

    if (!empty($errors)) {
        return ["status" => "error", "errors" => $errors];
    }

    return ["status" => "success"];
}

function validateComplaintData($data) {
    
    $title = trim($data['title'] ?? '');
    $user_id = trim($data['user_id'] ?? '');
    $complaint = trim($data['complaint'] ?? '');

    if (!$user_id) {
        return ["status" => "error", "message" => "Invalid user ID."];
    }

    if (empty(trim($title))) {
        return ["status" => "error", "message" => "Title is required."];
    }

    if (strlen($title) > 255) {
        return ["status" => "error", "message" => "Title must not exceed 255 characters."];
    }

    if (empty(trim($complaint))) {
        return ["status" => "error", "message" => "Complaint is required."];
    }

    return ["status" => "success"];
}

function validatePasswordChange($data, $conn) {
    $user_id = $data['user_id'] ?? null;
    $oldPassword = $data['old_password'] ?? '';
    $newPassword = $data['new_password'] ?? '';

    if (!$user_id || !is_numeric($user_id)) {
        return ["status" => "error", "message" => "Invalid user ID."];
    }

    if (empty($oldPassword) || empty($newPassword)) {
        return ["status" => "error", "message" => "All fields are required."];
    }

    $user_id = intval($user_id);
    $sql = "SELECT password FROM user WHERE id = $user_id";
    $result = mysqli_query($conn, $sql);

    if (!$result || mysqli_num_rows($result) === 0) {
        return ["status" => "error", "message" => "User not found."];
    }

    $row = mysqli_fetch_assoc($result);
    $hashedPassword = $row['password'];

    if (!password_verify($oldPassword, $hashedPassword)) {
        return ["status" => "error", "message" => "Current password is incorrect."];
    }

    if (strlen($newPassword) < 6) {
        return ["status" => "error", "message" => "New password must be at least 6 characters."];
    }

    if (password_verify($newPassword, $hashedPassword)) {
        return ["status" => "error", "message" => "New password cannot be the same as the current password."];
    }

    return ["status" => "success"];
}


function validateTransactionData($data) {
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ["status" => "error", "message" => "Invalida Email Address Passed"];
    }

    if (!isset($data['user_id']) || !filter_var($data['user_id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        return ["status" => "error", "message" => "Invalid or missing user_id."];
    }

    if (!isset($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
        return ["status" => "error", "message" => "Invalid or missing amount_paid."];
    }

    return ["status" => "success"];
}

function validateMoneyTransfer($data) {
    if (empty($data['semester'])) {
        return ["status" => "error", "message" => "Invalid or missing semester."];
    }

    if (!isset($data['amount'])) {
        return ["status" => "error", "message" => "Invalid or missing amount."];
    } elseif (!is_numeric($data['amount']) || $data['amount'] <= 0) {
        return ["status" => "error", "message" => "Amount must be a number greater than zero."];
    }

    if (!isset($data['user_id']) || !filter_var($data['user_id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        return ["status" => "error", "message" => "Invalid or missing user_id."];
    }

    if (empty($data['fee_name'])) {
        return ["status" => "error", "message" => "Invalid or missing fee name."];
    }

    return ["status" => "success"];
}



?>