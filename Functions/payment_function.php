<?php
function transferMoney($conn, $data) {
    $user_id = $data['user_id'];
    $amount = $data['amount'];
    $semester = $data['semester'];
    $fee_name = $data['fee_name'];
    $notes = $data['notes'] ?? null;

    $stmt = $conn->prepare("SELECT amount_paid FROM student_account WHERE user_id = ?");
    if (!$stmt) {
        return ['status' => 'error', 'message' => 'Database error: ' . $conn->error];
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return ['status' => 'error', 'message' => 'User not found in student account.'];
    }

    if ($user['amount_paid'] < $amount) {
        return ['status' => 'error', 'message' => 'Insufficient balance in student account.'];
    }

    $conn->begin_transaction();

    try {
        $newBalance = $user['amount_paid'] - $amount;
        $updateStmt = $conn->prepare("UPDATE student_account SET amount_paid = ? WHERE user_id = ?");
        if (!$updateStmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $updateStmt->bind_param("di", $newBalance, $user_id);
        $updateStmt->execute();
        $updateStmt->close();

        $insertStmt = $conn->prepare("INSERT INTO student_transactions (user_id, amount_paid, semester, fee_name, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if (!$insertStmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $insertStmt->bind_param("idsss", $user_id, $amount, $semester, $fee_name, $notes);
        $insertStmt->execute();
        $insertStmt->close();

        $conn->commit();

        return ['status' => 'success', 'message' => 'Transfer successful.'];

    } catch (Exception $e) {
        $conn->rollback();
        return ['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()];
    }
}


function getUserBalance($conn, $data) {
    $user_id = $data['user_id'];
    $stmt = $conn->prepare("SELECT amount_paid FROM student_account WHERE user_id = ?");
    if (!$stmt) {
        return ['status' => 'error', 'message' => 'Database error: ' . $conn->error];
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return ['status' => 'error', 'message' => 'User not found.'];
    }

    return [
        'status' => 'success',
        'balance' => (float) $user['amount_paid']
    ];
}
