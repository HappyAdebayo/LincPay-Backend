<?php

class PaystackService
{
    private $secretKey;
    private $baseUrl;

    public function __construct()
    {
        $config = include __DIR__ . '/../config/paystack.php';
        $this->secretKey = $config['secret_key'];
        $this->baseUrl = $config['base_url'];
    }

    public function initializeTransaction($email, $amount, $reference)
    {
        $url = $this->baseUrl . '/transaction/initialize';
        $data = [
            'email' => $email,
            'amount' => $amount * 100, // Paystack expects amount in kobo (Naira minor unit)
            'reference' => $reference,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return ['status' => 'errror', 'message' => $err];
        }

        return json_decode($response, true);
    }

    public function verifyTransaction($reference)
    {
        $url = $this->baseUrl . '/transaction/verify/' . $reference;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->secretKey,
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return ['status' => 'error', 'message' => $err];
        }

        return json_decode($response, true);
    }
}
