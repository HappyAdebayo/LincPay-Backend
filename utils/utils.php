<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

function getOrCreateRecipientCode($conn, $account_number, $bank_code, $name) {
    $stmt = $conn->prepare("SELECT recipient_code FROM transfer_recipients WHERE account_number = ? AND bank_code = ?");
    $stmt->bind_param("ss", $account_number, $bank_code);
    $stmt->execute();
    $stmt->bind_result($recipient_code);
    if ($stmt->fetch()) {
        $stmt->close();
        return $recipient_code;
    }
    $stmt->close();

    $url = "https://api.paystack.co/transferrecipient";
    $fields = [
        "type" => "nuban",
        "name" => $name,
        "account_number" => $account_number,
        "bank_code" => $bank_code,
        "currency" => "NGN"
    ];
    $fields_string = http_build_query($fields);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $_ENV['PAYSTACK_SECRET_KEY'],
        "Cache-Control: no-cache"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($result && $result['status'] === true) {
        $new_recipient_code = $result['data']['recipient_code'];

        $insertStmt = $conn->prepare("INSERT INTO transfer_recipients (account_number, bank_code, name, recipient_code) VALUES (?, ?, ?, ?)");
        $insertStmt->bind_param("ssss", $account_number, $bank_code, $name, $new_recipient_code);
        $insertStmt->execute();
        $insertStmt->close();

        return $new_recipient_code;
    } else {
        return null;
    }
}


function sendPaystackTransfer($amount, $recipient_code, $reason) {
    $url = "https://api.paystack.co/transfer";

    $fields = [
        "source" => "balance",
        "amount" => $amount * 100,
        "recipient" => $recipient_code,
        "reason" => $reason
    ];

    $fields_string = json_encode($fields);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $_ENV['PAYSTACK_SECRET_KEY'],
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}
