<?php
include __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$secretKey = $_ENV['PAYSTACK_SECRET_KEY'] ?? null;

if (!$secretKey) {
    file_put_contents("php://stderr", "❌ ENV not loaded or key missing\n");
}
$publicKey = $_ENV['PAYSTACK_PUBLIC_KEY'] ?? null;
$baseUrl   = $_ENV['PAYSTACK_BASE_URL'] ?? null;

if (!$secretKey) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Paystack secret key not set in environment file'
    ]);
    exit;
}

return [
    'secret_key' => $secretKey,
    'public_key' => $publicKey,
    'base_url' => $baseUrl,
];
