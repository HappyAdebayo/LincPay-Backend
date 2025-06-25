<?php
function addUser($username, $password, $conn) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO user (username, password) VALUES (?, ?)");
    if (!$stmt) {
        return ["status" => "error", "message" => "Prepare failed: " . $conn->error];
    }

    $stmt->bind_param("ss", $username, $hashed_password);

    if ($stmt->execute()) {
        $userId = $stmt->insert_id;
        return [
            "status" => "success",
            "message" => "User added successfully",
            "user_id" => $userId
        ];
    } else {
        return ["status" => "error", "message" => "Execute failed: " . $stmt->error];
    }
}



function saveUploadedFile($file, $uploadDir = "../Student_images/") {
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $originalName = $file['name'];
    $tmpName = $file['tmp_name'];

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($ext, $allowedExt)) {
        return false;
    }

    $uniqueName = uniqid('profile_', true) . '.' . $ext;
    $destination = $uploadDir . $uniqueName;

    if (move_uploaded_file($tmpName, $destination)) {
        return $uniqueName;
    }

    return false;
}


function addStudentProfile($data, $imageFileName, $conn) {
    $fullName = trim($data['fullName']);
    $studentId = trim($data['studentId']);
    $semester = trim($data['semester']);
    $email = trim($data['email']);
    $user_id = trim($data['user_id']);
    $intake = trim($data['intake']);
    $department = trim($data['department']);

    // Insert student profile
    $stmt = $conn->prepare("
        INSERT INTO student_profiles (user_id, full_name, student_id, semester, email, profile_image,department,intake)
        VALUES (?, ?, ?, ?, ?, ?,?,?)
    ");

    if (!$stmt) {
        return ["status" => "error", "message" => "Prepare failed: " . $conn->error];
    }

    $stmt->bind_param("isssssss", $user_id, $fullName, $studentId, $semester, $email, $imageFileName, $department, $intake);

    if ($stmt->execute()) {
        $stmt->close();

        // Update onboarding status in the user table
        $updateStmt = $conn->prepare("UPDATE user SET onboarding = 1 WHERE id = ?");
        if (!$updateStmt) {
            return ["status" => "error", "message" => "Onboarding update failed: " . $conn->error];
        }

        $updateStmt->bind_param("i", $user_id);
        if ($updateStmt->execute()) {
            $updateStmt->close();
            return ["status" => "success", "message" => "Profile setup complete and onboarding updated"];
        } else {
            $updateStmt->close();
            return ["status" => "error", "message" => "Onboarding update failed"];
        }
    } else {
        $stmt->close();
        return ["status" => "error", "message" => "Database insertion failed"];
    }
}

function loginUser($username, $password, $conn) {
    // Step 1: Fetch basic user info
    $stmt = $conn->prepare("
        SELECT s.id AS user_id, s.username, s.password, s.onboarding, s.is_2fa
        FROM user s
        WHERE s.username = ?
    ");

    if (!$stmt) {
        return ["status" => "error", "message" => "Prepare failed: " . $conn->error];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return ["status" => "error", "message" => "Invalid username or password"];
    }

    $user = $result->fetch_assoc();

    // Step 2: Verify password
    if (!password_verify($password, $user['password'])) {
        return ["status" => "error", "message" => "Invalid password"];
    }

    // Step 3: Check onboarding
    if ((int)$user['onboarding'] === 0) {
        return [
            "status" => "not_validated",
            "message" => "User not yet validated",
            "user_id" => $user['user_id']
        ];
    }

    // Step 4: Fetch extra user details if onboarding is completed
    $stmt2 = $conn->prepare("
        SELECT sp.full_name, sp.email, sp.profile_image, sp.semester, sp.student_id, sp.department, sp.intake
        FROM student_profiles sp
        WHERE sp.user_id = ?
    ");

    if (!$stmt2) {
        return ["status" => "error", "message" => "Prepare failed (details): " . $conn->error];
    }

    $stmt2->bind_param("i", $user['user_id']);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $profile = $result2->fetch_assoc();

    return [
        "status" => "success",
        "message" => "Login successful",
        "user" => [
            "id" => $user['user_id'],
            "username" => $user['username'],
            "is_2fa" => $user['is_2fa'],
            "full_name" => $profile['full_name'] ?? null,
            "email" => $profile['email'] ?? null,
            "student_id" => $profile['student_id'] ?? null,
            "semester" => $profile['semester'] ?? null,
            "department" => $profile['department'] ?? null,
            "intake" => $profile['intake'] ?? null,
            "profile_image" => $profile['profile_image'] ?? null
        ]
    ];
}

function validateSignupDetails($username, $password, $email, $fullName = null) {
    $errors = [];

    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters.";
    } elseif (preg_match('/\s/', $username)) {
        $errors[] = "Username cannot contain spaces.";
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }

    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if ($fullName !== null) {
        if (empty($fullName)) {
            $errors[] = "Full name cannot be empty.";
        } elseif (strlen($fullName) < 2) {
            $errors[] = "Full name must be at least 2 characters.";
        }
    }

    return $errors;
}

function createTwoFactorAuth($conn, int $userId, string $code): array {
    if (!preg_match('/^\d{4}$/', $code)) {
        return [
            'status' => 'error',
            'message' => 'Invalid two-factor authentication code format.'
        ];
    }

    $hashedCode = password_hash($code, PASSWORD_DEFAULT);

    $conn->begin_transaction();

    try {
        $delStmt = $conn->prepare("DELETE FROM two_factor_auth WHERE user_id = ?");
        if (!$delStmt) {
            throw new Exception("Prepare failed (delete): " . $conn->error);
        }
        $delStmt->bind_param("i", $userId);
        $delStmt->execute();
        $delStmt->close();

        $insertStmt = $conn->prepare("INSERT INTO two_factor_auth (user_id, auth_code) VALUES (?, ?)");
        if (!$insertStmt) {
            throw new Exception("Prepare failed (insert): " . $conn->error);
        }
        $insertStmt->bind_param("is", $userId, $hashedCode);
        if (!$insertStmt->execute()) {
            throw new Exception("Execute failed (insert): " . $insertStmt->error);
        }
        $insertStmt->close();

        $updateStmt = $conn->prepare("UPDATE user SET is_2fa = 1 WHERE id = ?");
        if (!$updateStmt) {
            throw new Exception("Prepare failed (update): " . $conn->error);
        }
        $updateStmt->bind_param("i", $userId);
        if (!$updateStmt->execute()) {
            throw new Exception("Execute failed (update): " . $updateStmt->error);
        }
        $updateStmt->close();

        $conn->commit();

        return [
            'status' => 'success',
            'message' => 'Two-factor authentication enabled successfully.'
        ];

    } catch (Exception $e) {
        $conn->rollback();
        return [
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

function validateTwoFactorCode($conn, $user_id, $inputCode) {
    if (empty($user_id) || empty($inputCode)) {
        return [
            'status' => 'error',
            'message' => 'f and verification code are required.'
        ];
    }

    // Fetch the encrypted auth_code and user details
    $stmt = $conn->prepare("
        SELECT 
            a.auth_code,
            u.username, u.password, u.onboarding, u.is_2fa,
            sp.full_name, sp.email, sp.profile_image, sp.semester, sp.student_id, sp.user_id
        FROM two_factor_auth a
        JOIN user u ON a.user_id = u.id
        JOIN student_profiles sp ON a.user_id = sp.user_id
        WHERE a.user_id = ? 
        LIMIT 1
    ");

    if (!$stmt) {
        return [
            'status' => 'error',
            'message' => 'Database error: ' . $conn->error
        ];
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result(
        $encryptedCode,
        $username, $password, $onboarding, $is_2fa,
        $full_name, $email, $profile_image, $semester, $student_id, $fetched_user_id
    );

    if (!$stmt->fetch()) {
        $stmt->close();
        return [
            'status' => 'error',
            'message' => 'Two-factor authentication code not found for user.'
        ];
    }

    $stmt->close();

    // Verify input code against encrypted auth_code
    if (!password_verify($inputCode, $encryptedCode)) {
        return [
            'status' => 'error',
            'message' => 'Invalid two-factor authentication code.'
        ];
    }

    return [
        'status' => 'success',
        'message' => 'Two-factor authentication successful.',
        'user' => [
            'id' => $fetched_user_id,
            'username' => $username,
            'full_name' => $full_name,
            'email' => $email,
            'student_id' => $student_id,
            'semester' => $semester,
            'profile_image' => $profile_image,
            'is_2fa' => $is_2fa,
            'onboarding' => $onboarding
        ]
    ];
}


function deleteTwoFactorAuth($conn, int $userId): array {
    $stmt = $conn->prepare("DELETE FROM two_factor_auth WHERE user_id = ?");
    if (!$stmt) {
        return [
            'status' => 'error',
            'message' => 'Prepare failed: ' . $conn->error
        ];
    }

    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        return [
            'status' => 'error',
            'message' => 'Execute failed: ' . $stmt->error
        ];
    }

    $stmt->close();

    $updateStmt = $conn->prepare("UPDATE user SET is_2fa = 0 WHERE id = ?");
    if (!$updateStmt) {
        return [
            'status' => 'error',
            'message' => 'Prepare failed (update): ' . $conn->error
        ];
    }
    $updateStmt->bind_param("i", $userId);
    if (!$updateStmt->execute()) {
        $updateStmt->close();
        return [
            'status' => 'error',
            'message' => 'Execute failed (update): ' . $updateStmt->error
        ];
    }
    $updateStmt->close();

    return [
        'status' => 'success',
        'message' => 'Two-factor authentication disabled and record deleted.'
    ];
}

function validateTwoFactorAuthCode($conn, $user_id, $code) {
    // 1. Validate 4-digit input
    if (!preg_match('/^\d{4}$/', $code)) {
        return [
            'status' => 'error',
            'message' => 'Invalid code format. Code must be 4 digits.',
            'debug' => [
                'user_id' => $user_id,
                'code_entered' => $code,
                'reason' => 'Code must be exactly 4 numeric digits.'
            ]
        ];
    }

    // 2. Fetch hashed code
    $stmt = $conn->prepare("SELECT auth_code FROM two_factor_auth WHERE user_id = ?");
    if (!$stmt) {
        return [
            'status' => 'error',
            'message' => 'Prepare failed: ' . $conn->error,
            'debug' => [
                'user_id' => $user_id,
                'code_entered' => $code,
                'reason' => 'Prepare statement failed.'
            ]
        ];
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $auth = $result->fetch_assoc();
    $stmt->close();

    if (!$auth || empty($auth['auth_code'])) {
        return [
            'status' => 'error',
            'message' => 'No verification code found for this user.',
            'debug' => [
                'user_id' => $user_id,
                'code_entered' => $code,
                'reason' => 'auth_code is empty or not found for user.'
            ]
        ];
    }

    // 3. Prepare debug info
    $verify_result = password_verify($code, $auth['auth_code']) ? 'MATCH' : 'NO MATCH';
    $debug_log = [
        'user_id' => $user_id,
        'code_entered' => $code,
        'code_in_db' => $auth['auth_code'],
        'password_verify_result' => $verify_result
    ];

    // 4. If verification fails
    if ($verify_result !== 'MATCH') {
        return [
            'status' => 'error',
            'message' => 'Invalid verification code.',
            'debug' => $debug_log
        ];
    }

    // 5. Fetch user data
    $fetchStmt = $conn->prepare("
        SELECT 
            u.id AS user_id, u.username, u.is_2fa,
            sp.full_name, sp.email, sp.student_id, sp.semester, sp.profile_image
        FROM user u
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        WHERE u.id = ?
    ");

    if (!$fetchStmt) {
        return [
            'status' => 'error',
            'message' => 'User fetch failed: ' . $conn->error,
            'debug' => $debug_log + ['reason' => 'User fetch prepare failed.']
        ];
    }

    $fetchStmt->bind_param("i", $user_id);
    $fetchStmt->execute();
    $userResult = $fetchStmt->get_result();
    $user = $userResult->fetch_assoc();
    $fetchStmt->close();

    if (!$user) {
        return [
            'status' => 'error',
            'message' => 'User not found.',
            'debug' => $debug_log + ['reason' => 'User data not found.']
        ];
    }

    // 6. Return success with debug
    return [
        'status' => 'success',
        'message' => 'Two-factor authentication validated successfully.',
        'debug' => $debug_log,
        'user' => [
            'id' => $user['user_id'],
            'username' => $user['username'],
            'is_2fa' => (bool)$user['is_2fa'],
            'full_name' => $user['full_name'] ?? null,
            'email' => $user['email'] ?? null,
            'student_id' => $user['student_id'] ?? null,
            'semester' => $user['semester'] ?? null,
            'profile_image' => $user['profile_image'] ?? null
        ]
    ];
}






