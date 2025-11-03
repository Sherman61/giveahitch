<?php
declare(strict_types=1);

namespace App\WS;

use RuntimeException;

function env(string $name): ?string
{
    $value = $_ENV[$name] ?? getenv($name);
    if ($value === false || $value === null || $value === '') {
        return null;
    }
    return (string)$value;
}

function secret(): string
{
    return env('WS_BROADCAST_SECRET') ?? '';
}

function hook_base(): ?string
{
    if ($url = env('WS_HOOK_URL')) {
        return rtrim($url, '/');
    }

    $host = env('WS_HOOK_HOST') ?? '127.0.0.1';
    $port = env('WS_HOOK_PORT') ?? '4002';

    return sprintf('http://%s:%s', $host, $port);
}

/**
 * Broadcast a Socket.IO event via the internal hook server.
 * This quietly no-ops when configuration or cURL is unavailable.
 */
function broadcast(string $event, array $payload = [], array $rooms = []): void
{
    $secret = secret();
    $base = hook_base();

    if ($secret === '' || !$base || !function_exists('curl_init')) {
        return;
    }

    $rooms = array_values(array_filter(array_map(static function ($room) {
        return is_string($room) && $room !== '' ? $room : null;
    }, $rooms)));

    try {
        $body = json_encode([
            'event' => $event,
            'payload' => $payload,
            'rooms' => $rooms,
        ], JSON_THROW_ON_ERROR);
    } catch (RuntimeException $e) {
        error_log('WS broadcast encode failed: ' . $e->getMessage());
        return;
    }

    $url = $base . '/broadcast';
    $ch = curl_init($url);
    if (!$ch) {
        return;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 2,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-WS-SECRET: ' . $secret,
        ],
        CURLOPT_POSTFIELDS => $body,
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        error_log('WS broadcast failed: ' . curl_error($ch));
        curl_close($ch);
        return;
    }

    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($status >= 400) {
        error_log(sprintf('WS broadcast HTTP %d: %s', $status, $response));
    }

    curl_close($ch);
}

/**
 * Generate a short-lived authentication token so the web client can
 * identify itself to the Socket.IO server.
 */
function generate_token(int $userId, int $ttlSeconds = 3600): ?string
{
    if ($userId <= 0) {
        return null;
    }

    $secret = secret();
    if ($secret === '') {
        return null;
    }

    $expires = time() + max(60, $ttlSeconds);
    $payload = $userId . '.' . $expires;
    $signature = hash_hmac('sha256', $payload, $secret);

    return base64_encode($payload . '.' . $signature);
}
