<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

/**
 * Sends one email.
 *
 *  - With a Brevo API key (live site): sends it through the Brevo web API using cURL.
 *  - Without a key (XAMPP): saves it as an .html file in storage/mail so you can open it.
 *
 * Sending never stops the page: if it fails we write it in the log and carry on.
 */
final class Mailer
{
    private const BREVO_URL = 'https://api.brevo.com/v3/smtp/email';

    public static function send(string $toEmail, string $toName, string $subject, string $html): bool
    {
        $config = require BASE_PATH . '/config/mail.php';

        try {
            if ($config['api_key'] === '') {
                return self::saveToFile($toEmail, $subject, $html);
            }
            return self::sendWithBrevo($config, $toEmail, $toName, $subject, $html);
        } catch (\Throwable $e) {
            Logger::error('Email to ' . $toEmail . ' failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function sendWithBrevo(array $config, string $toEmail, string $toName, string $subject, string $html): bool
    {
        $body = json_encode([
            'sender'      => ['name' => $config['from_name'], 'email' => $config['from_email']],
            'to'          => [['email' => $toEmail, 'name' => $toName !== '' ? $toName : $toEmail]],
            'subject'     => $subject,
            'htmlContent' => $html,
        ]);

        $curl = curl_init(self::BREVO_URL);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'content-type: application/json',
                'api-key: ' . $config['api_key'],
            ],
        ]);

        $response = curl_exec($curl);
        $status   = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error    = curl_error($curl);
        curl_close($curl);

        if ($status >= 200 && $status < 300) {
            Logger::info('Email sent to ' . $toEmail . ': ' . $subject);
            return true;
        }

        Logger::error(sprintf('Brevo refused email to %s (HTTP %d) %s %s', $toEmail, $status, $error, (string) $response));
        return false;
    }

    private static function saveToFile(string $toEmail, string $subject, string $html): bool
    {
        $folder = BASE_PATH . '/storage/mail';
        if (!is_dir($folder)) {
            mkdir($folder, 0775, true);
        }

        $name = date('Y-m-d_His') . '_' . bin2hex(random_bytes(3)) . '_' . preg_replace('/[^a-z0-9]+/i', '-', $toEmail) . '.html';
        $info = '<p style="font:13px monospace;background:#ffd">To: ' . htmlspecialchars($toEmail) . '<br>Subject: ' . htmlspecialchars($subject) . '</p>';

        file_put_contents($folder . '/' . $name, $info . $html);
        Logger::info('Email saved to storage/mail/' . $name . ' (no Brevo key set)');
        return true;
    }
}
