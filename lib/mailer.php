<?php
declare(strict_types=1);

namespace App\Mailer;

use function curl_close;
use function curl_errno;
use function curl_error;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function error_log;
use function function_exists;
use function getenv;
use function htmlspecialchars;
use function is_string;
use function json_encode;
use function trim;
use function sprintf;
use const CURLINFO_HTTP_CODE;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

const MAILTRAP_ENDPOINT = 'https://send.api.mailtrap.io/api/send';

/**
 * Fetch the configured Mailtrap token from environment variables.
 */
function mailtrap_token(): string
{
    $token = $_ENV['MAILTRAP_TOKEN'] ?? getenv('MAILTRAP_TOKEN') ?? '';
    return is_string($token) ? trim($token) : '';
}

/**
 * Low-level helper to send a message payload to Mailtrap.
 *
 * @param array<string,mixed> $message
 */
function send_mailtrap(array $message): bool
{
    $token = mailtrap_token();
    if ($token === '') {
        error_log('mail: Mailtrap token missing – cannot send email.');
        return false;
    }

    $json = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        error_log('mail: Failed to encode email payload as JSON.');
        return false;
    }

    if (!function_exists('curl_init')) {
        error_log('mail: cURL extension required to send email via Mailtrap.');
        return false;
    }

    $ch = curl_init(MAILTRAP_ENDPOINT);
    if ($ch === false) {
        error_log('mail: Unable to initialise cURL for Mailtrap.');
        return false;
    }

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        $code = curl_errno($ch);
        curl_close($ch);
        error_log(sprintf('mail: Mailtrap request failed (%d): %s', $code, $err));
        return false;
    }

    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        error_log(sprintf('mail: Mailtrap responded with HTTP %d: %s', $status, $response));
        return false;
    }

    return true;
}

function format_display_name(string $name, string $email): string
{
    $name = trim($name);
    if ($name !== '') {
        return $name;
    }
    return trim($email);
}

/**
 * Send a password reset code email.
 */
function send_password_reset_code(string $email, string $name, string $code): bool
{
    $email = trim($email);
    if ($email === '') {
        return false;
    }

    $displayName = format_display_name($name, $email);
    $safeName = htmlspecialchars($displayName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $subject = 'Your Glitch a Hitch password reset code';
    $textBody = sprintf(
        "Hi %s,\n\nUse the verification code %s to reset your Glitch a Hitch password. This code expires in 15 minutes.\n\nIf you did not request a reset you can ignore this email.\n",
        $displayName,
        $code
    );
    $htmlBody = sprintf(
        '<p>Hi %s,</p><p>Use the verification code <strong>%s</strong> to reset your Glitch a Hitch password. This code expires in 15 minutes.</p><p>If you did not request a reset you can ignore this email.</p>',
        $safeName,
        htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    );

    $payload = [
        'from' => [
            'email' => 'support@giveahitch.com',
            'name'  => 'Glitch a Hitch',
        ],
        'to' => [
            [
                'email' => $email,
                'name'  => $displayName,
            ],
        ],
        'subject' => $subject,
        'text' => $textBody,
        'html' => $htmlBody,
        'category' => 'password_reset',
    ];

    return send_mailtrap($payload);
}
