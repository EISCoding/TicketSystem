<?php
declare(strict_types=1);

namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class Mailer
{
    /** Platzhalter-Markenname für den Mail-Header. Bei Bedarf einfach anpassen. */
    private const BRAND_NAME = 'DaAdre.de Support';

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
        $mail->AltBody = self::htmlToPlainText($htmlBody);

        $mail->send();

        return $messageId;
    }

    /** Baut eine grobe Klartext-Alternative aus dem HTML-Body (für Mail-Clients ohne HTML-Darstellung). */
    private static function htmlToPlainText(string $html): string
    {
        $text = preg_replace('/<\/(p|div|h[1-6]|li|blockquote)>/i', "\n\n", $html) ?? $html;
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
        return trim(preg_replace('/\n{3,}/', "\n\n", $text) ?? $text);
    }

    /**
     * Baut eine schlichte, aber saubere HTML-Mail (tabellenbasiert, inline gestylt für
     * Mail-Client-Kompatibilität). $bodyHtml kommt bereits über sanitizeHtml() bereinigt
     * vom Aufrufer und wird hier unverändert eingebettet (nicht escapen!).
     */
    private static function renderHtml(string $bodyHtml, ?int $ticketId, ?string $requesterName): string
    {
        $brand = e(self::BRAND_NAME);
        $ticketRef = $ticketId !== null ? 'Fall-Nr. ' . caseNumber($ticketId) : null;

        // Vom Agenten getippter Text (bzw. Vorlage) enthält bereits eine eigene Anrede,
        // daher wird hier keine zusätzliche Begrüßung eingefügt.
        $preheaderText = $ticketRef !== null
            ? (($requesterName ? e($requesterName) . ': ' : '') . 'Neue Antwort zu ' . e($ticketRef))
            : 'Neue Antwort zu Ihrer Anfrage';

        $ticketBadge = $ticketRef !== null
            ? '<span style="display:inline-block;background:#e2f3f1;color:#0a5b63;font-family:SFMono-Regular,Consolas,\'Liberation Mono\',Menlo,monospace;font-size:12px;font-weight:700;padding:4px 10px;border-radius:999px;">' . e($ticketRef) . '</span>'
            : '';
        $footerNote = $ticketRef !== null
            ? 'Diese Nachricht bezieht sich auf ' . e($ticketRef) . '. Antworten Sie einfach direkt auf diese Email, um mit uns in Kontakt zu bleiben — Ihre Antwort wird automatisch dem Fall zugeordnet.'
            : 'Antworten Sie einfach direkt auf diese Email, um mit uns in Kontakt zu bleiben.';

        return <<<HTML
<!doctype html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$brand}</title>
<style>
  .msg-content p { margin: 0 0 12px; }
  .msg-content p:last-child { margin-bottom: 0; }
  .msg-content ul, .msg-content ol { margin: 0 0 12px; padding-left: 22px; }
  .msg-content a { color: #0e7c86; }
  .msg-content blockquote { margin: 0 0 12px; padding-left: 12px; border-left: 3px solid #dbe5e2; color: #3a4a4d; }
</style>
</head>
<body style="margin:0; padding:0; background:#e9f0ee; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<div style="display:none; max-height:0; overflow:hidden; opacity:0; font-size:1px; line-height:1px; color:#e9f0ee;">{$preheaderText}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e9f0ee; padding:32px 16px;">
  <tr>
    <td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #dbe5e2;">
        <tr>
          <td style="background:linear-gradient(135deg,#17b8ac,#0a5b63); background-color:#0e7c86; padding:28px 32px;">
            <span style="color:#ffffff; font-size:18px; font-weight:800; letter-spacing:-0.01em;">{$brand}</span>
          </td>
        </tr>
        <tr>
          <td style="padding:32px;">
            {$ticketBadge}
            <div class="msg-content" style="margin-top:16px; font-size:15px; line-height:1.6; color:#10181c;">{$bodyHtml}</div>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 32px; background:#f4f8f7; border-top:1px solid #dbe5e2;">
            <p style="margin:0; font-size:12px; color:#6b7a7d; line-height:1.6;">
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
