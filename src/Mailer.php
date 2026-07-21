<?php
declare(strict_types=1);

namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class Mailer
{
    /** Platzhalter-Markenname für den Mail-Header. Bei Bedarf einfach anpassen. */
    private const BRAND_NAME = 'Unser Support-Team';

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
        ?string $inReplyTo = null,
        ?int $ticketId = null,
        ?string $requesterName = null
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
        $mail->Body = self::renderHtml($htmlBody, $ticketId, $requesterName);
        $mail->AltBody = $htmlBody;

        $mail->send();

        return $messageId;
    }

    /** Baut eine schlichte, aber saubere HTML-Mail (tabellenbasiert, inline gestylt für Mail-Client-Kompatibilität). */
    private static function renderHtml(string $plainBody, ?int $ticketId, ?string $requesterName): string
    {
        $brand = e(self::BRAND_NAME);
        $bodyHtml = nl2br(e($plainBody));
        $ticketRef = $ticketId !== null ? 'Ticket #' . (int) $ticketId : null;

        // Vom Agenten getippter Text (bzw. Vorlage) enthält bereits eine eigene Anrede,
        // daher wird hier keine zusätzliche Begrüßung eingefügt.
        $preheaderText = $ticketRef !== null
            ? (($requesterName ? e($requesterName) . ': ' : '') . 'Neue Antwort zu ' . e($ticketRef))
            : 'Neue Antwort zu Ihrer Anfrage';

        $ticketBadge = $ticketRef !== null
            ? '<span style="display:inline-block;background:#eef0ff;color:#4338ca;font-size:12px;font-weight:700;padding:4px 10px;border-radius:999px;">' . e($ticketRef) . '</span>'
            : '';
        $footerNote = $ticketRef !== null
            ? 'Diese Nachricht bezieht sich auf ' . e($ticketRef) . '. Antworten Sie einfach direkt auf diese Email, um mit uns in Kontakt zu bleiben — Ihre Antwort wird automatisch dem Ticket zugeordnet.'
            : 'Antworten Sie einfach direkt auf diese Email, um mit uns in Kontakt zu bleiben.';

        return <<<HTML
<!doctype html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$brand}</title>
</head>
<body style="margin:0; padding:0; background:#f2f4fa; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<div style="display:none; max-height:0; overflow:hidden; opacity:0; font-size:1px; line-height:1px; color:#f2f4fa;">{$preheaderText}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f4fa; padding:32px 16px;">
  <tr>
    <td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e8f0;">
        <tr>
          <td style="background:linear-gradient(135deg,#5b6ef5,#4338ca); background-color:#4759e6; padding:28px 32px;">
            <span style="color:#ffffff; font-size:18px; font-weight:800; letter-spacing:-0.01em;">🎫 {$brand}</span>
          </td>
        </tr>
        <tr>
          <td style="padding:32px;">
            {$ticketBadge}
            <div style="margin-top:16px; font-size:15px; line-height:1.6; color:#1c2030;">{$bodyHtml}</div>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 32px; background:#fafbfd; border-top:1px solid #e5e8f0;">
            <p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.6;">
              {$footerNote}
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;
    }
}
