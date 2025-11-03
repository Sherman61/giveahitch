<?php
declare(strict_types=1);

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class MailtrapApi
{
    private HttpClientInterface $http;
    private string $token;

    public function __construct(string $token, ?HttpClientInterface $http = null)
    {
        $this->token = $token;
        $this->http = $http ?? HttpClient::create();
    }

    public function send(
        string $fromEmail,
        string $toEmail,
        string $subject,
        string $text = '',
        ?string $html = null,
        array $headers = []
    ): ResponseInterface {
        $payload = [
            'from' => ['email' => $fromEmail, 'name' => 'GlitchaHitch'],
            'to' => [['email' => $toEmail]],
            'subject' => $subject,
            'text' => $text,
        ];
        if ($html !== null)
            $payload['html'] = $html;
        if ($headers)
            $payload['headers'] = $headers;

        return $this->http->request('POST', 'https://send.api.mailtrap.io/api/send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
            'timeout' => 15,
        ]);
    }
}
