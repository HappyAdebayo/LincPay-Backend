<?php
require_once '../payment_gateway/payment_controller.php';
include '../cors.php'; 
include '../config.php'; 
include '../validation.php';
include '../functions/payment_function.php';

$config = include '../payment_gateway/paystack.php'; 

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

if ($action === 'transfer') {
    $post = json_decode(file_get_contents('php://input'), true);
    
    $email = $post['email'] ?? null;
    $amount = $post['amount'] ?? null;

    $validation = validateTransactionData($post);
    
    if ($validation['status'] === 'error') {
        echo json_encode($validation);
        exit;
    }

    $paymentController = new PaymentController($config['secret_key'], $conn);
    $response = $paymentController->initializePayment($email, $amount, $post);
    echo json_encode($response);
    exit;
}

if ($action === 'validate' && isset($_GET['reference'])) {
    $reference = $_GET['reference'];
    $paymentController = new PaymentController($config['secret_key'], $conn);
    $response = $paymentController->verifyPayment($reference);

    header('Content-Type: application/json');
    // echo json_encode([
    //     'status' => 'success',
    //     'message' => 'Payment verification complete',
    //     'data' => $response
    // ]);

     if ($response['status'] === 'error') {
        header('Location: http://192.168.209.1:8080/lincpay_backend/payment_error.html');
        exit;
    }

    header('Location: http://192.168.209.1:8080/lincpay_backend/payment_success.html');
    exit;
}

if ($method === 'GET' && preg_match('#^/api/payment/verify/([\w\-]+)$#', $uri, $matches)) {
    $reference = $matches[1];
    $paymentController = new PaymentController($config['secret_key'], $conn);
    $response = $paymentController->verifyPayment($reference);
    echo json_encode($response);
    exit;
}

if ($action === 'transfermoney') {
    $input = json_decode(file_get_contents("php://input"), true);
    $validation = validateMoneyTransfer($input);
    if ($validation['status'] === 'error') {
        echo json_encode($validation);
        exit;
    }

    $result = transferMoney($conn, $input);
    echo json_encode($result);
    exit;
}

if ($action === 'userbalance') {
    $input = json_decode(file_get_contents("php://input"), true);

    $result = getUserBalance($conn, $input);
    echo json_encode($result);
    exit;
}

http_response_code(404);
echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
exit;
