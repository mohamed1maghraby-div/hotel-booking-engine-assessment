<?php

$options = getopt('', [
    'url:',
    'token:',
    'hotel_id:',
    'room_type_id:',
    'check_in:',
    'check_out:',
    'quantity:',
    'idempotency_key:',
    'start_at:',
]);

$startAt = (float) $options['start_at'];

while (microtime(true) < $startAt) {
    usleep(1000);
}

$payload = json_encode([
    'hotel_id' => (int) $options['hotel_id'],
    'room_type_id' => (int) $options['room_type_id'],
    'check_in' => $options['check_in'],
    'check_out' => $options['check_out'],
    'quantity' => (int) $options['quantity'],
]);

$ch = curl_init($options['url']);

curl_setopt_array($ch, [
    CURLOPT_POST => true,

    CURLOPT_POSTFIELDS => $payload,

    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $options['token'],
        'Idempotency-Key: ' . $options['idempotency_key'],
    ],

    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => false,

    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 60,
]);

$responseBody = curl_exec($ch);

$curlError = curl_error($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($responseBody === false || $curlError !== '') {
    fwrite(STDERR, $curlError ?: 'Unknown cURL error');

    exit(1);
}

echo json_encode([
    'status' => $statusCode,
    'body' => json_decode($responseBody, true),
]);