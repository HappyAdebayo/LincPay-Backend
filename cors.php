<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://192.168.74.1"); // Limit to your Expo IP
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
