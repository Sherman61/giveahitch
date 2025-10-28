<?php
$token = getenv('MAILTRAP_TOKEN'); // put it in your .env and load it
$payload = [
    'from' => ['email' => 'glitchahitch@gmail.com', 'name' => 'Mailtrap Test'],
    'to' => [['email' => 'shermanshiya@gmail.com']],
    'subject' => 'You are awesome!',
    'text' => 'Congrats for sending test email with Mailtrap!',
    'category' => 'Integration Test',
];

$ch = curl_init('https://send.api.mailtrap.io/api/send');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

header('Content-Type: text/plain');
echo "HTTP $code\n";
echo $err ? "cURL error: $err\n" : $resp . "\n";
