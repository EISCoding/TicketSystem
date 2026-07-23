<?php

declare(strict_types=1);

namespace App;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

class Mailer
{
    /**
     * Anzeigename im Kopfbereich der E-Mail.
     */
    private const BRAND_NAME = 'DaAdre.de Support';

    /**
     * Verschickt eine Antwort-E-Mail mit korrektem Threading.
     *
     * Die Message-ID sollte gespeichert werden, damit spätere Antworten über
     * In-Reply-To und References demselben E-Mail-Verlauf zugeordnet werden.
     *
     * @return string Generierte Message-ID
     *
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
            $mail->SMTPSecure = $smtp['encryption'];
        }

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            $smtp['from_email'],
            $smtp['from_name']
        );

        $mail->addAddress($toEmail);

        $mail->addReplyTo(
            $smtp['from_email'],
            $smtp['from_name']
        );

        /*
         * Eigene Message-ID für das E-Mail-Threading erzeugen.
         */
        $messageId = sprintf(
            '<%s@%s>',
            bin2hex(random_bytes(16)),
            config('mail_domain', 'localhost')
        );

        $mail->MessageID = $messageId;

        if ($inReplyTo !== null && $inReplyTo !== '') {
            $mail->addCustomHeader('In-Reply-To', $inReplyTo);
            $mail->addCustomHeader('References', $inReplyTo);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;

        /*
         * Der Betreff wird nun ebenfalls an das Template übergeben, damit er
         * in der Ticketübersicht dargestellt werden kann.
         */
        $mail->Body = self::renderHtml(
            $htmlBody,
            $subject,
            $ticketId,
            $requesterName
        );

        $mail->AltBody = self::htmlToPlainText(
            $htmlBody,
            $subject,
            $ticketId
        );

        $mail->send();

        return $messageId;
    }

    /**
     * Erstellt eine Klartext-Alternative für Mail-Clients ohne HTML-Unterstützung.
     */
    private static function htmlToPlainText(
        string $html,
        string $subject,
        ?int $ticketId
    ): string {
        $text = preg_replace(
            '/<\/(p|div|h[1-6]|li|blockquote)>/i',
            "\n\n",
            $html
        ) ?? $html;

        $text = preg_replace(
            '/<br\s*\/?>/i',
            "\n",
            $text
        ) ?? $text;

        $text = html_entity_decode(
            strip_tags($text),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $text = trim(
            preg_replace('/\n{3,}/', "\n\n", $text) ?? $text
        );

        $header = $subject;

        if ($ticketId !== null) {
            $header .= "\nFall-Nr. " . caseNumber($ticketId);
        }

        return $header . "\n\n" . $text;
    }

    /**
     * Rendert das vollständige HTML-E-Mail-Template.
     *
     * $bodyHtml wurde bereits durch sanitizeHtml() bereinigt und wird daher
     * bewusst nicht erneut escaped.
     */
    private static function renderHtml(
        string $bodyHtml,
        string $subject,
        ?int $ticketId,
        ?string $requesterName
    ): string {
        $brand = e(self::BRAND_NAME);
        $ticketSubject = e($subject);

        /*
         * Ticketnummer vorbereiten.
         */
        $ticketNumberRaw = $ticketId !== null
            ? (string) caseNumber($ticketId)
            : '';

        $ticketReferenceRaw = $ticketId !== null
            ? 'Fall-Nr. ' . $ticketNumberRaw
            : 'Support-Anfrage';

        $ticketReference = e($ticketReferenceRaw);

        /*
         * Absenderadresse als Supportadresse verwenden, sofern keine eigene
         * support_email-Konfiguration gesetzt wurde.
         */
        $smtp = config('smtp');

        $supportEmailRaw = trim(
            (string) config(
                'support_email',
                $smtp['from_email'] ?? ''
            )
        );

        $supportEmail = e($supportEmailRaw);

        /*
         * Logo-URL aus der Konfiguration.
         *
         * Beispiel:
         * 'mail_logo_url' => 'https://support.daadre.de/assets/logo.png'
         */
        $logoUrlRaw = trim(
            (string) config('mail_logo_url', '')
        );

        $logoUrl = e($logoUrlRaw);

        /*
         * Optionaler Link zum Ticket.
         *
         * Beispiel:
         * 'ticket_url_template'
         *     => 'https://support.daadre.de/tickets/{ticketId}'
         *
         * Unterstützte Variablen:
         * {ticketId}
         * {caseNumber}
         */
        $ticketUrlTemplate = trim(
            (string) config('ticket_url_template', '')
        );

        $ticketUrlRaw = '';

        if ($ticketId !== null && $ticketUrlTemplate !== '') {
            $ticketUrlRaw = str_replace(
                [
                    '{ticketId}',
                    '{caseNumber}',
                ],
                [
                    rawurlencode((string) $ticketId),
                    rawurlencode($ticketNumberRaw),
                ],
                $ticketUrlTemplate
            );
        }

        $ticketUrl = e($ticketUrlRaw);

        /*
         * Vorschautext, der beispielsweise in Outlook oder Thunderbird neben
         * dem Betreff angezeigt wird.
         */
        if ($ticketId !== null) {
            $preheaderText = $requesterName !== null && trim($requesterName) !== ''
                ? e($requesterName) . ': Neue Antwort zu ' . $ticketReference
                : 'Neue Antwort zu ' . $ticketReference;
        } else {
            $preheaderText = 'Neue Antwort zu Ihrer Support-Anfrage';
        }

        /*
         * Ticket-Badge.
         */
        $ticketBadge = $ticketId !== null
            ? <<<HTML
            <span style="
                display:inline-block;
                padding:7px 11px;
                color:#1d4ed8;
                background-color:#eff6ff;
                border:1px solid #dbeafe;
                border-radius:999px;
                font-family:SFMono-Regular,Consolas,'Liberation Mono',Menlo,monospace;
                font-size:11px;
                font-weight:800;
                line-height:1;
                letter-spacing:0.03em;
                white-space:nowrap;
            ">
                {$ticketReference}
            </span>
            HTML
            : <<<HTML
            <span style="
                display:inline-block;
                padding:7px 11px;
                color:#475569;
                background-color:#f1f5f9;
                border:1px solid #e2e8f0;
                border-radius:999px;
                font-size:11px;
                font-weight:800;
                line-height:1;
                letter-spacing:0.03em;
                white-space:nowrap;
            ">
                SUPPORT
            </span>
            HTML;

        /*
         * Logo oder textbasierter Fallback.
         */
        if ($logoUrlRaw !== '') {
            $logoBlock = <<<HTML
            <table
                role="presentation"
                cellpadding="0"
                cellspacing="0"
                border="0"
            >
                <tr>
                    <td style="
                        padding:10px 14px;
                        background-color:#ffffff;
                        border-radius:10px;
                    ">
                        <img
                            src="{$logoUrl}"
                            width="160"
                            alt="{$brand}"
                            class="logo-image"
                            style="
                                display:block;
                                width:160px;
                                max-width:160px;
                                height:auto;
                                border:0;
                                outline:none;
                                text-decoration:none;
                            "
                        >
                    </td>
                </tr>
            </table>
            HTML;
        } else {
            $logoBlock = <<<HTML
            <table
                role="presentation"
                cellpadding="0"
                cellspacing="0"
                border="0"
            >
                <tr>
                    <td style="
                        padding:11px 15px;
                        color:#0f172a;
                        background-color:#ffffff;
                        border-radius:10px;
                        font-size:17px;
                        font-weight:800;
                        line-height:1.3;
                        letter-spacing:-0.01em;
                    ">
                        {$brand}
                    </td>
                </tr>
            </table>
            HTML;
        }

        /*
         * Ticket-Button wird nur angezeigt, wenn eine Ticket-URL konfiguriert
         * wurde und eine Ticket-ID vorhanden ist.
         */
        if ($ticketUrlRaw !== '') {
            $ticketButton = <<<HTML
            <table
                role="presentation"
                width="100%"
                cellpadding="0"
                cellspacing="0"
                border="0"
                style="margin-top:26px;"
            >
                <tr>
                    <td align="center">

                        <!--[if mso]>
                        <v:roundrect
                            xmlns:v="urn:schemas-microsoft-com:vml"
                            xmlns:w="urn:schemas-microsoft-com:office:word"
                            href="{$ticketUrl}"
                            style="height:50px;v-text-anchor:middle;width:230px;"
                            arcsize="20%"
                            stroke="f"
                            fillcolor="#2563eb"
                        >
                            <w:anchorlock/>
                            <center style="
                                color:#ffffff;
                                font-family:Arial,sans-serif;
                                font-size:15px;
                                font-weight:bold;
                            ">
                                Ticket im Portal öffnen
                            </center>
                        </v:roundrect>
                        <![endif]-->

                        <!--[if !mso]><!-->
                        <a
                            href="{$ticketUrl}"
                            target="_blank"
                            class="button-link"
                            style="
                                display:inline-block;
                                padding:15px 24px;
                                color:#ffffff;
                                background-color:#2563eb;
                                background-image:linear-gradient(
                                    135deg,
                                    #2563eb 0%,
                                    #4f46e5 100%
                                );
                                border-radius:10px;
                                box-shadow:0 8px 20px rgba(37,99,235,0.24);
                                font-size:15px;
                                font-weight:800;
                                line-height:20px;
                                text-align:center;
                                text-decoration:none;
                            "
                        >
                            Ticket im Portal öffnen&nbsp;&nbsp;&rarr;
                        </a>
                        <!--<![endif]-->

                    </td>
                </tr>
            </table>
            HTML;
        } else {
            $ticketButton = '';
        }

        /*
         * Supportkontakt nur anzeigen, wenn eine Adresse vorhanden ist.
         */
        if ($supportEmailRaw !== '') {
            $supportContact = <<<HTML
            <a
                href="mailto:{$supportEmail}"
                style="
                    color:#2563eb;
                    font-size:12px;
                    font-weight:800;
                    line-height:1.6;
                    text-decoration:none;
                "
            >
                {$supportEmail}
            </a>
            HTML;
        } else {
            $supportContact = <<<HTML
            <span style="
                color:#64748b;
                font-size:12px;
                line-height:1.6;
            ">
                Antworten Sie direkt auf diese E-Mail.
            </span>
            HTML;
        }

        /*
         * Footer-Hinweis.
         */
        if ($ticketId !== null) {
            $footerNote = sprintf(
                'Diese Nachricht bezieht sich auf %s. Antworten Sie direkt auf diese E-Mail, damit Ihre Nachricht automatisch dem Fall zugeordnet wird.',
                $ticketReference
            );
        } else {
            $footerNote = 'Antworten Sie direkt auf diese E-Mail, um mit unserem Support-Team in Kontakt zu bleiben.';
        }

        return <<<HTML
<!doctype html>
<html lang="de" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="x-apple-disable-message-reformatting"
    >

    <meta
        name="format-detection"
        content="telephone=no,address=no,email=no,date=no,url=no"
    >

    <title>{$brand}</title>

    <style>
        html,
        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            min-width: 100% !important;
            background-color: #eef2f6;
        }

        body {
            font-family:
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Roboto,
                Helvetica,
                Arial,
                sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        table,
        td {
            border-collapse: collapse !important;
            mso-table-lspace: 0pt !important;
            mso-table-rspace: 0pt !important;
        }

        img {
            display: block;
            border: 0;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
        }

        a {
            color: #2563eb;
            text-decoration: none;
        }

        .msg-content {
            color: #26313d;
            font-size: 15px;
            line-height: 1.7;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .msg-content p {
            margin: 0 0 16px;
        }

        .msg-content p:last-child {
            margin-bottom: 0;
        }

        .msg-content h1,
        .msg-content h2,
        .msg-content h3 {
            margin: 24px 0 12px;
            color: #111827;
            line-height: 1.3;
        }

        .msg-content h1 {
            font-size: 24px;
        }

        .msg-content h2 {
            font-size: 20px;
        }

        .msg-content h3 {
            font-size: 17px;
        }

        .msg-content ul,
        .msg-content ol {
            margin: 0 0 16px;
            padding-left: 24px;
        }

        .msg-content li {
            margin-bottom: 6px;
        }

        .msg-content a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: underline;
            text-decoration-color: #bfdbfe;
            text-underline-offset: 3px;
        }

        .msg-content blockquote {
            margin: 20px 0;
            padding: 16px 18px;
            color: #475569;
            background-color: #f8fafc;
            border-left: 4px solid #2563eb;
            border-radius: 0 10px 10px 0;
        }

        .msg-content code {
            padding: 2px 6px;
            color: #be123c;
            background-color: #fff1f2;
            border: 1px solid #ffe4e6;
            border-radius: 5px;
            font-family:
                Consolas,
                Monaco,
                "Courier New",
                monospace;
            font-size: 13px;
        }

        .msg-content pre {
            margin: 18px 0;
            padding: 16px;
            overflow-x: auto;
            color: #e2e8f0;
            background-color: #0f172a;
            border-radius: 10px;
            font-family:
                Consolas,
                Monaco,
                "Courier New",
                monospace;
            font-size: 13px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
        }

        @media only screen and (max-width: 640px) {
            .outer-cell {
                padding: 0 !important;
            }

            .email-shell {
                width: 100% !important;
                max-width: 100% !important;
                border-radius: 0 !important;
            }

            .header-padding {
                padding: 24px 20px !important;
            }

            .content-padding {
                padding: 26px 20px !important;
            }

            .footer-padding {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }

            .logo-image {
                width: 140px !important;
                max-width: 140px !important;
                height: auto !important;
            }

            .mobile-block {
                display: block !important;
                width: 100% !important;
            }

            .mobile-text-left {
                padding-top: 16px !important;
                text-align: left !important;
            }

            .ticket-title {
                font-size: 22px !important;
            }

            .button-link {
                display: block !important;
                width: auto !important;
                text-align: center !important;
            }
        }

        @media (prefers-color-scheme: dark) {
            .dark-page {
                background-color: #111827 !important;
            }

            .dark-shell {
                background-color: #18212f !important;
                border-color: #334155 !important;
            }

            .dark-content {
                background-color: #18212f !important;
            }

            .dark-card {
                background-color: #202b3a !important;
                border-color: #334155 !important;
            }

            .dark-title {
                color: #f8fafc !important;
            }

            .dark-text {
                color: #cbd5e1 !important;
            }

            .dark-muted {
                color: #94a3b8 !important;
            }

            .dark-footer {
                background-color: #141c28 !important;
                border-color: #334155 !important;
            }
        }
    </style>
</head>

<body
    class="dark-page"
    style="
        margin:0;
        padding:0;
        background-color:#eef2f6;
    "
>

    <!-- Vorschautext für Outlook, Thunderbird, Apple Mail usw. -->
    <div style="
        display:none;
        max-height:0;
        max-width:0;
        overflow:hidden;
        opacity:0;
        color:transparent;
        font-size:1px;
        line-height:1px;
        mso-hide:all;
    ">
        {$preheaderText}
    </div>

    <!-- Verhindert unerwünschten Vorschautext nach dem Preheader -->
    <div style="
        display:none;
        max-height:0;
        overflow:hidden;
        mso-hide:all;
    ">
        &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;
        &zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
    </div>

    <table
        role="presentation"
        width="100%"
        cellpadding="0"
        cellspacing="0"
        border="0"
        class="dark-page"
        style="
            width:100%;
            background-color:#eef2f6;
        "
    >
        <tr>
            <td
                align="center"
                class="outer-cell"
                style="padding:40px 16px;"
            >

                <!--[if mso]>
                <table
                    role="presentation"
                    width="640"
                    cellpadding="0"
                    cellspacing="0"
                    border="0"
                >
                    <tr>
                        <td>
                <![endif]-->

                <table
                    role="presentation"
                    width="100%"
                    cellpadding="0"
                    cellspacing="0"
                    border="0"
                    class="email-shell dark-shell"
                    style="
                        width:100%;
                        max-width:640px;
                        overflow:hidden;
                        background-color:#ffffff;
                        border:1px solid #dfe6ee;
                        border-radius:18px;
                        box-shadow:0 16px 45px rgba(15,23,42,0.10);
                    "
                >

                    <!-- Farbige Akzentleiste -->
                    <tr>
                        <td style="
                            height:6px;
                            font-size:0;
                            line-height:0;
                            background-color:#2563eb;
                            background-image:linear-gradient(
                                90deg,
                                #2563eb 0%,
                                #7c3aed 50%,
                                #0891b2 100%
                            );
                        ">
                            &nbsp;
                        </td>
                    </tr>

                    <!-- Kopfbereich -->
                    <tr>
                        <td
                            class="header-padding"
                            style="
                                padding:26px 32px;
                                background-color:#0f172a;
                                background-image:linear-gradient(
                                    135deg,
                                    #0f172a 0%,
                                    #172554 55%,
                                    #312e81 100%
                                );
                            "
                        >
                            <table
                                role="presentation"
                                width="100%"
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                            >
                                <tr>
                                    <td
                                        class="mobile-block"
                                        valign="middle"
                                        style="vertical-align:middle;"
                                    >
                                        {$logoBlock}
                                    </td>

                                    <td
                                        class="mobile-block mobile-text-left"
                                        align="right"
                                        valign="middle"
                                        style="
                                            padding-top:2px;
                                            vertical-align:middle;
                                            text-align:right;
                                        "
                                    >
                                        <p style="
                                            margin:0 0 4px;
                                            color:#c7d2fe;
                                            font-size:11px;
                                            font-weight:700;
                                            line-height:1.4;
                                            letter-spacing:0.12em;
                                            text-transform:uppercase;
                                        ">
                                            Service &amp; Support
                                        </p>

                                        <p style="
                                            margin:0;
                                            color:#ffffff;
                                            font-size:16px;
                                            font-weight:700;
                                            line-height:1.4;
                                        ">
                                            {$brand}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Hauptinhalt -->
                    <tr>
                        <td
                            class="content-padding dark-content"
                            style="
                                padding:34px 32px 32px;
                                background-color:#ffffff;
                            "
                        >

                            <table
                                role="presentation"
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                                style="margin-bottom:16px;"
                            >
                                <tr>
                                    <td style="
                                        padding:7px 11px;
                                        color:#1d4ed8;
                                        background-color:#eff6ff;
                                        border:1px solid #dbeafe;
                                        border-radius:999px;
                                        font-size:11px;
                                        font-weight:800;
                                        line-height:1;
                                        letter-spacing:0.08em;
                                        text-transform:uppercase;
                                    ">
                                        Ticket-Update
                                    </td>
                                </tr>
                            </table>

                            <h1
                                class="ticket-title dark-title"
                                style="
                                    margin:0 0 10px;
                                    color:#111827;
                                    font-size:27px;
                                    font-weight:800;
                                    line-height:1.25;
                                    letter-spacing:-0.025em;
                                "
                            >
                                Neue Antwort vom Support
                            </h1>

                            <p
                                class="dark-muted"
                                style="
                                    margin:0 0 26px;
                                    color:#64748b;
                                    font-size:15px;
                                    line-height:1.6;
                                "
                            >
                                Zu Ihrer Anfrage gibt es eine neue Nachricht
                                von unserem Support-Team.
                            </p>

                            <!-- Ticketübersicht -->
                            <table
                                role="presentation"
                                width="100%"
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                                class="dark-card"
                                style="
                                    width:100%;
                                    margin-bottom:26px;
                                    background-color:#f8fafc;
                                    border:1px solid #e2e8f0;
                                    border-radius:14px;
                                "
                            >
                                <tr>
                                    <td style="padding:20px;">

                                        <table
                                            role="presentation"
                                            width="100%"
                                            cellpadding="0"
                                            cellspacing="0"
                                            border="0"
                                        >
                                            <tr>
                                                <td
                                                    class="mobile-block"
                                                    valign="top"
                                                    style="
                                                        width:68%;
                                                        vertical-align:top;
                                                    "
                                                >
                                                    <p
                                                        class="dark-muted"
                                                        style="
                                                            margin:0 0 5px;
                                                            color:#64748b;
                                                            font-size:11px;
                                                            font-weight:800;
                                                            line-height:1.4;
                                                            letter-spacing:0.08em;
                                                            text-transform:uppercase;
                                                        "
                                                    >
                                                        Supportfall
                                                    </p>

                                                    <p
                                                        class="dark-title"
                                                        style="
                                                            margin:0 0 6px;
                                                            color:#111827;
                                                            font-size:17px;
                                                            font-weight:800;
                                                            line-height:1.4;
                                                        "
                                                    >
                                                        {$ticketReference}
                                                    </p>

                                                    <p
                                                        class="dark-text"
                                                        style="
                                                            margin:0;
                                                            color:#475569;
                                                            font-size:14px;
                                                            line-height:1.5;
                                                        "
                                                    >
                                                        {$ticketSubject}
                                                    </p>
                                                </td>

                                                <td
                                                    class="mobile-block mobile-text-left"
                                                    align="right"
                                                    valign="top"
                                                    style="
                                                        width:32%;
                                                        vertical-align:top;
                                                        text-align:right;
                                                    "
                                                >
                                                    <div
                                                        style="
                                                            height:4px;
                                                            line-height:4px;
                                                        "
                                                    >
                                                        &nbsp;
                                                    </div>

                                                    {$ticketBadge}
                                                </td>
                                            </tr>
                                        </table>

                                    </td>
                                </tr>
                            </table>

                            <!-- Eigentliche Nachricht -->
                            <table
                                role="presentation"
                                width="100%"
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                                class="dark-card"
                                style="
                                    width:100%;
                                    background-color:#ffffff;
                                    border:1px solid #e2e8f0;
                                    border-radius:14px;
                                "
                            >
                                <tr>
                                    <td style="padding:22px;">

                                        <table
                                            role="presentation"
                                            width="100%"
                                            cellpadding="0"
                                            cellspacing="0"
                                            border="0"
                                            style="margin-bottom:18px;"
                                        >
                                            <tr>
                                                <td
                                                    valign="middle"
                                                    style="
                                                        width:42px;
                                                        vertical-align:middle;
                                                    "
                                                >
                                                    <table
                                                        role="presentation"
                                                        width="38"
                                                        height="38"
                                                        cellpadding="0"
                                                        cellspacing="0"
                                                        border="0"
                                                    >
                                                        <tr>
                                                            <td
                                                                align="center"
                                                                valign="middle"
                                                                style="
                                                                    width:38px;
                                                                    height:38px;
                                                                    color:#ffffff;
                                                                    background-color:#2563eb;
                                                                    border-radius:10px;
                                                                    font-size:17px;
                                                                    font-weight:800;
                                                                    line-height:38px;
                                                                    text-align:center;
                                                                "
                                                            >
                                                                i
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>

                                                <td
                                                    valign="middle"
                                                    style="
                                                        padding-left:12px;
                                                        vertical-align:middle;
                                                    "
                                                >
                                                    <p
                                                        class="dark-title"
                                                        style="
                                                            margin:0;
                                                            color:#111827;
                                                            font-size:15px;
                                                            font-weight:800;
                                                            line-height:1.4;
                                                        "
                                                    >
                                                        Nachricht unseres
                                                        Support-Teams
                                                    </p>

                                                    <p
                                                        class="dark-muted"
                                                        style="
                                                            margin:2px 0 0;
                                                            color:#64748b;
                                                            font-size:12px;
                                                            line-height:1.4;
                                                        "
                                                    >
                                                        Direkt aus Ihrem
                                                        Supportfall
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                        <div
                                            class="msg-content dark-text"
                                            style="
                                                color:#26313d;
                                                font-size:15px;
                                                line-height:1.7;
                                            "
                                        >
                                            {$bodyHtml}
                                        </div>

                                    </td>
                                </tr>
                            </table>

                            {$ticketButton}

                            <!-- Antwort-Hinweis -->
                            <table
                                role="presentation"
                                width="100%"
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                                style="margin-top:26px;"
                            >
                                <tr>
                                    <td style="
                                        padding:15px 17px;
                                        background-color:#fffbeb;
                                        border:1px solid #fde68a;
                                        border-radius:10px;
                                    ">
                                        <p style="
                                            margin:0;
                                            color:#92400e;
                                            font-size:12px;
                                            line-height:1.6;
                                        ">
                                            <strong>Hinweis:</strong>
                                            Antworten Sie direkt auf diese
                                            E-Mail. Ihre Antwort wird
                                            automatisch
                                            <strong>{$ticketReference}</strong>
                                            zugeordnet.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- Supportbereich -->
                    <tr>
                        <td
                            class="dark-footer footer-padding"
                            style="
                                padding:24px 32px;
                                background-color:#f8fafc;
                                border-top:1px solid #e2e8f0;
                            "
                        >
                            <table
                                role="presentation"
                                width="100%"
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                            >
                                <tr>
                                    <td
                                        class="mobile-block"
                                        valign="top"
                                        style="
                                            width:65%;
                                            vertical-align:top;
                                        "
                                    >
                                        <p
                                            class="dark-title"
                                            style="
                                                margin:0 0 5px;
                                                color:#1e293b;
                                                font-size:13px;
                                                font-weight:800;
                                                line-height:1.5;
                                            "
                                        >
                                            Benötigen Sie weitere Unterstützung?
                                        </p>

                                        <p
                                            class="dark-muted"
                                            style="
                                                margin:0;
                                                color:#64748b;
                                                font-size:12px;
                                                line-height:1.6;
                                            "
                                        >
                                            Unser Support-Team hilft Ihnen
                                            gerne weiter.
                                        </p>
                                    </td>

                                    <td
                                        class="mobile-block mobile-text-left"
                                        align="right"
                                        valign="top"
                                        style="
                                            width:35%;
                                            text-align:right;
                                            vertical-align:top;
                                        "
                                    >
                                        {$supportContact}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td
                            class="dark-footer footer-padding"
                            style="
                                padding:20px 32px;
                                background-color:#0f172a;
                            "
                        >
                            <p style="
                                margin:0 0 8px;
                                color:#cbd5e1;
                                font-size:11px;
                                line-height:1.6;
                                text-align:center;
                            ">
                                {$footerNote}
                            </p>

                            <p style="
                                margin:0;
                                color:#64748b;
                                font-size:10px;
                                line-height:1.6;
                                text-align:center;
                            ">
                                Diese Nachricht wurde automatisch durch das
                                Ticketsystem von {$brand} erstellt.
                            </p>
                        </td>
                    </tr>

                </table>

                <!-- Hinweis unterhalb der Mail -->
                <table
                    role="presentation"
                    width="100%"
                    cellpadding="0"
                    cellspacing="0"
                    border="0"
                    style="max-width:640px;"
                >
                    <tr>
                        <td style="padding:18px 24px 0;">
                            <p style="
                                margin:0;
                                color:#94a3b8;
                                font-size:10px;
                                line-height:1.6;
                                text-align:center;
                            ">
                                {$ticketReference} &middot; {$brand}
                            </p>
                        </td>
                    </tr>
                </table>

                <!--[if mso]>
                        </td>
                    </tr>
                </table>
                <![endif]-->

            </td>
        </tr>
    </table>

</body>
</html>
HTML;
    }
}
