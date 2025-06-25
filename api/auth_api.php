<?php
include '../cors.php'; 
include '../config.php';
include '../functions/auth_function.php';
include '../validation.php';

$action = $_GET['action'] ?? '';

if ($action === 'register') {
    $input = json_decode(file_get_contents("php://input"), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    $validation = validateSignup($username, $password, $conn);

    if ($validation['status'] === 'error') {
        echo json_encode($validation);
        exit;
    }

    $result = addUser($username, $password, $conn);
    echo json_encode($result);
    exit;
}

if ($action === 'studentdetails') {
    $postData = $_POST;
    $files = $_FILES;

    $validation = validateStudentProfile($postData, $files, $conn);
    if ($validation['status'] === 'error') {
        echo json_encode($validation);
        exit;
    }

    $imageFileName = saveUploadedFile($files['image']);
    if (!$imageFileName) {
        echo json_encode(["status" => "error", "message" => "Failed to save profile image"]);
        exit;
    }

    $result = addStudentProfile($postData, $imageFileName, $conn);
    echo json_encode($result);
    exit;
}

if ($action === 'login') {
    $input = json_decode(file_get_contents("php://input"), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    $validation = validateLoginDetails($username, $password, $conn);

    if ($validation['status'] === 'error') {
        echo json_encode($validation);
        exit;
    }

    $result = loginUser($username, $password, $conn);
    echo json_encode($result);
    exit;
}

if ($action === 'validate_2fa') {
    $input = json_decode(file_get_contents("php://input"), true);
    $user_id = $input['user_id'] ?? null;
    $verificationCode = $input['verificationCode'] ?? '';

    // Basic validation
    if (empty($user_id) || empty($verificationCode)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'User ID and verification code are required.'
        ]);
        exit;
    }

    $response = validateTwoFactorCode($conn, $user_id, $verificationCode);

    echo json_encode($response);
    exit;
}

if ($action === 'create_2fa') {
    $input = json_decode(file_get_contents("php://input"), true);
    $user_id = $input['user_id'] ?? null;
    $verificationCode = $input['verificationCode'] ?? '';

    if (empty($user_id) || empty($verificationCode)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'User ID and verification code are required.'
        ]);
        exit;
    }

    $response = createTwoFactorAuth($conn, $user_id, $verificationCode);

    echo json_encode($response);
    exit;
}


if ($action === 'delete_2fa') {
    $input = json_decode(file_get_contents("php://input"), true);
    $user_id = $input['user_id'] ?? null;

    if (empty($user_id)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'User ID is required.'
        ]);
        exit;
    }

    $response = deleteTwoFactorAuth($conn, $user_id);

    echo json_encode($response);
    exit;
}

if ($action === 'validate_2fa_code') {
    $input = json_decode(file_get_contents("php://input"), true);
    $user_id = $input['user_id'] ?? null;
    $code = $input['code'] ?? null;

    if (empty($user_id)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'User ID is required.'
        ]);
        exit;
    }

    $response = validateTwoFactorAuthCode($conn, $user_id, $code);

    echo json_encode($response);
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action"]);
