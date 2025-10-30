<?php
declare(strict_types=1);

namespace App\Mailer;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Throwable;
use function class_exists;
use function error_log;
use function getenv;
use function htmlspecialchars;
use function is_string;
use function sprintf;
use function trim;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

const MAILTRAP_ENDPOINT = 'https://send.api.mailtrap.io/api/send';

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ENV_LOADED') && class_exists(\Dotenv\Dotenv::class)) {
    \Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
    define('APP_ENV_LOADED', true);
}

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

    try {
        $client = HttpClient::create();
        $response = $client->request('POST', MAILTRAP_ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => $message,
            'timeout' => 15,
        ]);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $body = $response->getContent(false);
            error_log(sprintf('mail: Mailtrap responded with HTTP %d: %s', $status, $body));
            return false;
        }

        return true;
    } catch (TransportExceptionInterface $e) {
        error_log('mail: Mailtrap transport error: ' . $e->getMessage());
        return false;
    } catch (Throwable $e) {
        error_log('mail: Mailtrap request failed: ' . $e->getMessage());
        return false;
    }
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

    $subject = 'Your GlitchaHitch password reset code';
    $textBody = sprintf(
        "Hi %s,\n\nUse the verification code %s to reset your GlitchaHitch password. This code expires in 15 minutes.\n\nIf you did not request a reset you can ignore this email.\n",
        $displayName,
        $code
    );
    $htmlBody = sprintf(
        '<p>Hi %s,</p><p>Use the verification code <strong>%s</strong> to reset your GlitchaHitch password. This code expires in 15 minutes.</p><p>If you did not request a reset you can ignore this email.</p>',
        $safeName,
        htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    );

    $payload = [
        'from' => [
            'email' => 'no-reply@glitchahitch.com',
            'name'  => 'GlitchaHitch',
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

/**
 * Send the signup verification PIN email.
 */
function send_signup_verification_pin(string $email, string $name, string $pin): bool
{
    $email = trim($email);
    if ($email === '') {
        return false;
    }

    $displayName = format_display_name($name, $email);
    $safeName = htmlspecialchars($displayName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $subject = 'Your GlitchaHitch signup PIN';
    $textBody = sprintf(
        "Hi %s,\n\nUse the verification PIN %s to complete your GlitchaHitch signup. This PIN expires in 15 minutes.\n\nIf you did not start this signup you can ignore this email.\n",
        $displayName,
        $pin
    );
    $htmlBody = sprintf(
        '<p>Hi %s,</p><p>Use the verification PIN <strong>%s</strong> to complete your GlitchaHitch signup. This PIN expires in 15 minutes.</p><p>If you did not start this signup you can ignore this email.</p>',
        $safeName,
        htmlspecialchars($pin, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    );

    $payload = [
        'from' => [
            'email' => 'no-reply@glitchahitch.com',
            'name'  => 'GlitchaHitch',
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
        'category' => 'signup_verification',
    ];

    return send_mailtrap($payload);
}

/**
 * Send a welcome email asking the user to confirm their new account.
 */
function send_signup_confirmation(string $email, string $name): bool
{
    $email = trim($email);
    if ($email === '') {
        return false;
    }

    $displayName = format_display_name($name, $email);
    $safeName = htmlspecialchars($displayName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $subject = 'Confirm your GlitchaHitch account';
    $loginUrl = 'https://glitchahitch.com/login';
    $textBody = sprintf(
        "Hi %s,\n\nWelcome to GlitchaHitch! Please confirm your email by signing in at %s so we know we have the right address.\n\nIf you did not create this account you can ignore this email.\n",
        $displayName,
        $loginUrl
    );
    $loginLink = htmlspecialchars($loginUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $htmlBody = sprintf(
        '<p>Hi %s,</p><p>Welcome to GlitchaHitch! Please confirm your email by signing in at <a href="%s">%s</a> so we know we have the right address.</p><p>If you did not create this account you can ignore this email.</p>',
        $safeName,
        $loginLink,
        $loginLink
    );

    $payload = [
        'from' => [
            'email' => 'no-reply@glitchahitch.com',
            'name'  => 'GlitchaHitch',
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
        'category' => 'signup_confirmation',
    ];

    return send_mailtrap($payload);
}
