<?php
require __DIR__ . '/../vendor/autoload.php'; // Autoload for phpdotenv

use Dotenv\Dotenv;

// Load .env from one folder above (project root)
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

function verifyPayment($reference) {
    if (!$reference) {
        return [
            'status' => 'error',
            'message' => 'Reference is required'
        ];
    }

    $secretKey = $_ENV['PAYSTACK_SECRET_KEY'] ?? '';
    $baseUrl = $_ENV['PAYSTACK_BASE_URL'] ?? 'https://api.paystack.co';

    if (!$secretKey) {
        return [
            'status' => 'error',
            'message' => 'Secret key not set in environment variables'
        ];
    }

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "$baseUrl/transaction/verify/$reference",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $secretKey",
            "Cache-Control: no-cache",
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return [
            'status' => 'error',
            'message' => 'Curl error: ' . $err
        ];
    }

    $result = json_decode($response, true);

    if ($result['status'] && isset($result['data']['status']) && $result['data']['status'] === 'success') {
        // Payment was successful
        return [
            'status' => 'success',
            'message' => 'Payment verified',
            'payment_status' => $result['data']['status'], // should be 'success'
            'reference' => $reference
        ];
    } else {
        return [
            'status' => 'error',
            'message' => 'Payment not successful',
            'payment_status' => $result['data']['status'] ?? 'unknown',
            'reference' => $reference
        ];
    }
}

if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    $reference = $_GET['reference'] ?? '';
    echo json_encode(verifyPayment($reference));
}
