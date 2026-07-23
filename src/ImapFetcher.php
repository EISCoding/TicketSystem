<?php
declare(strict_types=1);

namespace App;

use App\Models\Message;
use App\Models\Team;
use App\Models\Ticket;
use Webklex\PHPIMAP\ClientManager;

class ImapFetcher
{
    // Erkennt "[FALL-00142]" (aktuelles Format) sowie das ältere "[TICKET-142]" im Betreff
    // (case-insensitive, führende Nullen werden toleriert) - wichtig für laufende Email-Threads
    // aus der Zeit vor der Umbenennung von "Ticket" auf "Fall".
    private const TICKET_TAG_REGEX = '/\[(?:FALL|TICKET)-0*(\d+)\]/i';

    public static function poll(): array
    {
        $imapConfig = config('imap');
        if (empty($imapConfig['host'])) {
            return ['ok' => false, 'error' => 'IMAP nicht konfiguriert'];
        }

        $cm = new ClientManager();
        $client = $cm->make([
            'host' => $imapConfig['host'],
            'port' => $imapConfig['port'],
            'encryption' => $imapConfig['encryption'],
            'validate_cert' => $imapConfig['validate_cert'] ?? true,
            'username' => $imapConfig['username'],
            'password' => $imapConfig['password'],
            'protocol' => 'imap',
        ]);

        $client->connect();
        $folder = $client->getFolder('INBOX');

        $messages = $folder->messages()->unseen()->setFetchOrderAsc()->get();

        $processed = 0;
        foreach ($messages as $mail) {
            self::processMail($mail);
            $mail->setFlag('Seen');
            $processed++;
        }

        $client->disconnect();

        return ['ok' => true, 'processed' => $processed];
    }

    private static function processMail($mail): void
    {
        $uid = (string) $mail->getUid();

        $pdo = \getPDO();
        $check = $pdo->prepare('SELECT id FROM processed_emails WHERE uid = ?');
        $check->execute([$uid]);
        if ($check->fetch()) {
            return; // bereits verarbeitet
        }

        $fromAddress = $mail->getFrom()[0]->mail ?? 'unbekannt@unbekannt.de';
        $fromName = $mail->getFrom()[0]->personal ?? $fromAddress;
        $subject = (string) ($mail->getSubject() ?? '(kein Betreff)');
        $bodyText = trim((string) ($mail->getTextBody() ?: strip_tags((string) $mail->getHTMLBody())));
        $messageId = $mail->getMessageId() ? (string) $mail->getMessageId() : null;
        $inReplyToHeader = $mail->getInReplyTo() ? (string) $mail->getInReplyTo() : null;
        $referencesRaw = $mail->getReferences() ? (string) $mail->getReferences() : '';
        $references = array_filter(preg_split('/\s+/', $referencesRaw) ?: []);

        $ticket = self::findExistingTicket($subject, $inReplyToHeader, $references);

        if (!$ticket) {
            $teamId = Team::resolveForContent($subject, $bodyText);
            $cleanSubject = trim(preg_replace(self::TICKET_TAG_REGEX, '', $subject));
            $ticketId = Ticket::create($cleanSubject !== '' ? $cleanSubject : $subject, $fromAddress, $fromName, $teamId);
        } else {
            $ticketId = (int) $ticket['id'];
            if (in_array($ticket['status'], ['RESOLVED', 'CLOSED'], true)) {
                Ticket::updateFields($ticketId, ['status' => 'OPEN']);
            }
        }

        Message::create($ticketId, 'INCOMING', $bodyText, $fromAddress, null, $messageId, $inReplyToHeader);
        Ticket::touch($ticketId);

        $pdo->prepare('INSERT INTO processed_emails (uid) VALUES (?)')->execute([$uid]);
    }

    /**
     * Sucht ein existierendes Ticket über:
     * 1. Ticket-Nummer im Betreff ([TICKET-xxx])
     * 2. In-Reply-To / References Header gegen gespeicherte Message-IDs
     */
    private static function findExistingTicket(string $subject, ?string $inReplyTo, array $references): ?array
    {
        if (preg_match(self::TICKET_TAG_REGEX, $subject, $m)) {
            $ticket = Ticket::find((int) $m[1]);
            if ($ticket) {
                return $ticket;
            }
        }

        $candidateIds = array_merge($inReplyTo ? [$inReplyTo] : [], $references);
        if (!empty($candidateIds)) {
            $msg = Message::findByMessageIds($candidateIds);
            if ($msg) {
                return Ticket::find((int) $msg['ticket_id']);
            }
        }

        return null;
    }
}
