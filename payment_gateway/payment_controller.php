<?php
// require_once '../config.php'; 
include 'paystack.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

class PaymentController {
    private $secretKey;
    private $conn;

    public function __construct($secretKey, $conn) {
        $this->secretKey = $secretKey;
        $this->conn = $conn;

        if (!$this->secretKey) {
            die(json_encode(['status' => 'error', 'message' => 'Paystack secret key not set']));
        }
    }

    public function initializePayment($email, $amount, $customData = null) {
        if (!is_numeric($amount) || $amount <= 0) {
            return ['status' => 'error', 'message' => 'Invalid amount'];
        }

        $amountKobo = intval($amount * 100);
        $url = "https://api.paystack.co/transaction/initialize";

        $fields = [
            'email' => $email,
            'amount' => $amountKobo,
            'metadata' => [
                'custom_data' => $customData,
            ],
            'callback_url' => "http://192.168.209.1:8080/lincpay_backend/api/payment_api.php?action=validate"
            // 'callback_url' => "http://192.168.74.1/lincpay_backend/payment_success.html"

        ];

        $fields_string = json_encode($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->secretKey}",
            "Content-Type: application/json",
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return ['status' => 'error', 'message' => curl_error($ch)];
        }
        curl_close($ch);

        $response = json_decode($result, true);
        if ($response === null) {
            return ['status' => 'error', 'message' => 'Invalid response from Paystack'];
        }

        if (isset($response['status']) && $response['status'] === true) {
            return [
                'status' => 'success',
                'message' => 'Payment initialized',
                'data' => $response['data'],
            ];
        }

        return ['status' => 'error', 'message' => $response['message'] ?? 'Failed to initialize payment'];
    }

    public function verifyPayment($reference) {
        $url = "https://api.paystack.co/transaction/verify/" . urlencode($reference);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->secretKey}",
            "Content-Type: application/json",
        ]);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return ['status' => 'error', 'message' => curl_error($ch)];
        }
        curl_close($ch);

        $response = json_decode($result, true);
        if ($response === null) {
            return ['status' => 'error', 'message' => 'Invalid response from Paystack'];
        }

if (isset($response['status']) && $response['status'] === true) {
    $paymentData = $response['data'];

    $amount = $paymentData['amount'] / 100;
    $email = $paymentData['customer']['email'];
    $status = $paymentData['status']; // 'success'
    $reference = $paymentData['reference'];
    $customData = $paymentData['metadata']['custom_data'] ?? null;
    
    $user_id = $customData['user_id'] ?? null;

    if (
        empty($user_id) ||
        empty($amount) ||
        empty($reference)
    ) {
        return [
            'status' => 'error',
            'message' => 'Missing required payment data fields.'
        ];
    }

    // Check if user record exists
    $stmt = $this->conn->prepare("SELECT amount_paid FROM student_account WHERE user_id = ?");
    if (!$stmt) {
        return ['status' => 'error', 'message' => 'Database error: ' . $this->conn->error];
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // User exists, update amount and reference
        $row = $result->fetch_assoc();
        $newAmount = $row['amount_paid'] + $amount;
        $stmt->close();

        $updateStmt = $this->conn->prepare("UPDATE student_account SET amount_paid = ?, paystack_reference = ?, status = ? WHERE user_id = ?");
        if (!$updateStmt) {
            return ['status' => 'error', 'message' => 'Database error: ' . $this->conn->error];
        }
        $updateStmt->bind_param("dsii", $newAmount, $reference, $status, $user_id);

        if (!$updateStmt->execute()) {
            return ['status' => 'error', 'message' => 'Failed to update transaction: ' . $updateStmt->error];
        }
        $updateStmt->close();
    } else {
        // No existing user record, insert new
        $stmt->close();

        $insertStmt = $this->conn->prepare("INSERT INTO student_account (user_id, amount_paid, status, paystack_reference) VALUES (?, ?, ?, ?)");
        if (!$insertStmt) {
            return ['status' => 'error', 'message' => 'Database error: ' . $this->conn->error];
        }
        $insertStmt->bind_param("idss", $user_id, $amount, $status, $reference);

        if (!$insertStmt->execute()) {
            return ['status' => 'error', 'message' => 'Failed to save transaction: ' . $insertStmt->error];
        }
        $insertStmt->close();
    }

    $this->conn->close();

    return [
        'status' => 'success',
        'message' => 'Payment verified and stored successfully',
        'data' => $paymentData,
    ];
}



        return ['status' => 'error', 'message' => $response['message'] ?? 'Payment verification failed'];
    }
}
