<?php
declare(strict_types=1);

namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class Mailer
{
    /**
     * Verschickt eine Antwort-Email mit korrektem Threading (In-Reply-To/References),
     * damit der Kunde alles in einem zusammenhängenden Email-Verlauf sieht.
     *
     * @return string Die generierte Message-ID (zum Speichern für zukünftiges Threading)
     * @throws PHPMailerException
     */
    public static function sendReply(
        string $toEmail,
        string $subject,
        string $htmlBody,
        ?string $inReplyTo = null
    ): string {
        $mail = new PHPMailer(true);

        $smtp = config('smtp');

        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->Port = $smtp['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['username'];
        $mail->Password = $smtp['password'];
        if (!empty($smtp['encryption'])) {
            $mail->SMTPSecure = $smtp['encryption']; // 'tls' oder 'ssl'
        }
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($smtp['from_email'], $smtp['from_name']);
        $mail->addAddress($toEmail);
        $mail->addReplyTo($smtp['from_email'], $smtp['from_name']);

        $messageId = '<' . bin2hex(random_bytes(16)) . '@' . config('mail_domain', 'localhost') . '>';
        $mail->MessageID = $messageId;
        if ($inReplyTo) {
            $mail->addCustomHeader('In-Reply-To', $inReplyTo);
            $mail->addCustomHeader('References', $inReplyTo);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = nl2br(htmlspecialchars($htmlBody, ENT_QUOTES, 'UTF-8'));
        $mail->AltBody = $htmlBody;

        $mail->send();

        return $messageId;
    }
}
