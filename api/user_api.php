<?php
include '../cors.php'; 
include '../config.php';
include '../functions/user_function.php';
include '../functions/reset_password_function.php';
include '../validation.php';

$action = $_GET['action'] ?? '';

if ($action === 'update_profile') {
    $postData = $_POST;
    $files = $_FILES;

    $debugInfo = [];

    $debugInfo['files'] = $files;

    $validation = validateUpdateProfile($postData, $files);
    if ($validation['status'] === 'error') {
        echo json_encode([
            "status" => "error",
            "errors" => $validation['errors'],
            "debug" => $debugInfo
        ]);
        exit;
    }

    if (isset($files['profile_image']) && $files['profile_image']['error'] === 0) {
        $imageFileName = saveUploadedFile($files['profile_image']);
        if (!$imageFileName) {
            echo json_encode([
                "status" => "error",
                "message" => "Failed to save profile image",
                "debug" => $debugInfo
            ]);
            exit;
        }
        $debugInfo['saved_image'] = $imageFileName;
    } elseif (!empty($postData['existing_image'])) {
        $imageFileName = $postData['existing_image'];
        $debugInfo['saved_image'] = $imageFileName;
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "No valid image uploaded",
            "debug" => $debugInfo
        ]);
        exit;
    }

    $result = updateUserProfile($postData, $imageFileName, $conn);

    $result['debug'] = $debugInfo;

    echo json_encode($result);
    exit;
}

if ($action === 'complaint') {
    $input = json_decode(file_get_contents("php://input"), true);
    $validation = validateComplaintData($input);
    if ($validation['status'] === 'error') {
        echo json_encode($validation);
        exit;
    }

    $result = submitComplaint($conn, $input);
    echo json_encode($result);
    exit;
}

if ($action === 'change_password') {
    $input = json_decode(file_get_contents("php://input"), true);
    $validation = validatePasswordChange($input,$conn);
    if ($validation['status'] === 'error') {
        echo json_encode($validation);
        exit;
    }

    $result = updateUserPassword($input, $conn);
    echo json_encode($result);
    exit;
}

if ($action === 'reset_password') {
    $input = json_decode(file_get_contents("php://input"), true);

    $result = sendPasswordResetCode($conn, $input);
    echo json_encode($result);
    exit;
}

if ($action === 'reset_password_code') {
    $input = json_decode(file_get_contents("php://input"), true);

    $result = resetPasswordWithCode($conn, $input);
    echo json_encode($result);
    exit;
}

if ($action === 'resend_reset_password') {
    $input = json_decode(file_get_contents("php://input"), true);

    $result = resendPasswordResetCode($conn, $input);
    echo json_encode($result);
    exit;
}

if ($action === 'get_notification') {
    $input = json_decode(file_get_contents("php://input"), true);

    $result = getUserNotifications($conn, $input);
    echo json_encode($result);
    exit;
}

if ($action === 'mark_has_read') {
    $input = json_decode(file_get_contents("php://input"), true);

    $result = markAllNotificationsAsRead($conn, $input);
    echo json_encode($result);
    exit;
}

if ($action === 'get_transactions') {
    $input = json_decode(file_get_contents("php://input"), true);

    $result = getUserTransactions($conn, $input);
    echo json_encode($result);
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action"]);
