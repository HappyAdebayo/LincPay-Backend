<?php
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

function updateUserProfile($data, $profile_image, $conn) {
    $full_name = trim($data['full_name']);
    $student_id = trim($data['student_id']);
    $semester = trim($data['semester']);
    $email = trim($data['email']);
    $user_id = trim($data['user_id']);

    $selectStmt = $conn->prepare("SELECT profile_image FROM student_profiles WHERE user_id = ?");
    if (!$selectStmt) {
        return ["status" => "error", "message" => "Select failed: " . $conn->error];
    }

    $selectStmt->bind_param("i", $user_id);
    $selectStmt->execute();
    $result = $selectStmt->get_result();

    if ($result->num_rows === 0) {
        return ["status" => "error", "message" => "User not found"];
    }

    $row = $result->fetch_assoc();
    $old_image = $row['profile_image'];

   
if ($profile_image !== $old_image) {
    $imagePath = __DIR__ . "/../Student_images/" . $old_image;
    if (!empty($old_image) && file_exists($imagePath) && is_file($imagePath)) {
        if (!unlink($imagePath)) {
            error_log("Failed to delete old image: " . $imagePath);
        }
    }
}


    $updateStmt = $conn->prepare("
        UPDATE student_profiles 
        SET full_name = ?, email = ?, semester = ?, student_id = ?, profile_image = ?
        WHERE user_id = ?
    ");

    if (!$updateStmt) {
        return ["status" => "error", "message" => "Prepare failed: " . $conn->error];
    }

    $updateStmt->bind_param("sssssi", $full_name, $email, $semester, $student_id, $profile_image, $user_id);

    if ($updateStmt->execute()) {
        return ["status" => "success", "message" => "Profile updated successfully"];
    } else {
        return ["status" => "error", "message" => "Update failed: " . $updateStmt->error];
    }
}


function submitComplaint($conn, $data) {
    $title = trim($data['title']);
    $complaint = trim($data['complaint']);
    $user_id = trim($data['user_id']);

    $stmt = $conn->prepare("INSERT INTO complaints (user_id, title, complaint) VALUES (?, ?, ?)");

    if (!$stmt) {
        return ["status" => "error", "message" => "Prepare failed: " . $conn->error];
    }

    $stmt->bind_param("iss", $user_id, $title, $complaint);

    if ($stmt->execute()) {
        return ["status" => "success", "message" => "Complaint submitted successfully"];
    } else {
        return ["status" => "error", "message" => "Submit failed: " . $stmt->error];
    }
}

function getUserNotifications($conn, $data) {
    $user_id=$data['user_id'];
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY sent_at DESC");

    if (!$stmt) {
        return ["status" => "error", "message" => "Prepare failed: " . $conn->error];
    }

    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
        return ["status" => "error", "message" => "Execution failed: " . $stmt->error];
    }

    $result = $stmt->get_result();
    $notifications = [];

    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            "id" => (string)$row["id"],
            "type" => $row["status"],
            "title" => "Alert",
            "message" => $row["message"],
            "time" => getTimeAgo($row["sent_at"]),
            "read" => $row["seen"] == 1 ? true : false
        ];
    }

    return [
        "status" => "success",
        "message" => "Notifications fetched successfully",
        "data" => $notifications
    ];
}

function getTimeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;

    if ($difference < 60) return "Just now";
    elseif ($difference < 3600) return floor($difference / 60) . " minute(s) ago";
    elseif ($difference < 86400) return floor($difference / 3600) . " hour(s) ago";
    elseif ($difference < 604800) return floor($difference / 86400) . " day(s) ago";
    else return date("M j, Y", $timestamp);
}


function updateUserPassword($data, $conn) {
    $newPassword = trim($data['new_password']);
    $user_id = trim($data['user_id']);
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $sql = "UPDATE user SET password = '$hashedPassword' WHERE id = $user_id";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        return ["status" => "success", "message" => "Password updated successfully."];
    } else {
        return ["status" => "error", "message" => "Failed to update password."];
    }
}

function markAllNotificationsAsRead($conn, $data) {
    $user_id=$data['user_id'];
    $stmt = $conn->prepare("UPDATE notifications SET seen = 1 WHERE user_id = ? AND seen = 0");

    if (!$stmt) {
        return ["status" => "error", "message" => "Prepare failed: " . $conn->error];
    }

    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        return [
            "status" => "success",
            "message" => "All notifications marked as read"
        ];
    } else {
        return [
            "status" => "error",
            "message" => "Execution failed: " . $stmt->error
        ];
    }
}

function getUserTransactions($conn, $data) {
    $user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;

    if ($user_id === 0) {
        return ["status" => "error", "message" => "User ID is missing or invalid."];
    }

    $stmt = $conn->prepare("SELECT id, fee_name, semester, amount_paid, created_at, status FROM student_transactions WHERE user_id = ? ORDER BY created_at DESC");

    if (!$stmt) {
        return ["status" => "error", "message" => "Prepare failed: " . $conn->error];
    }

    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
        return ["status" => "error", "message" => "Execution failed: " . $stmt->error];
    }

    $result = $stmt->get_result();
    $transactions = [];

    while ($row = $result->fetch_assoc()) {
        $transactions[] = [
            "id" => (string)$row['id'],
            "type" => strtolower($row['status']) === 'debit' ? 'expense' : 'income',
            "title" => $row['fee_name'],
            "date" => formatDateTime($row['created_at']),
            "amount" => floatval($row['amount_paid']),
            "category" => $row['semester'],
        ];
    }

    return [
        "status" => "success",
        "message" => "Transactions fetched successfully.",
        "data" => $transactions
    ];
}


function formatDateTime($datetime) {
    $timestamp = strtotime($datetime);
    $now = time();
    $date = "";

    if (date("Y-m-d", $timestamp) === date("Y-m-d", $now)) {
        $date = "Today, " . date("g:i A", $timestamp);
    } elseif (date("Y-m-d", $timestamp) === date("Y-m-d", strtotime("-1 day", $now))) {
        $date = "Yesterday, " . date("g:i A", $timestamp);
    } else {
        $date = date("M j, Y, g:i A", $timestamp);
    }

    return $date;
}
