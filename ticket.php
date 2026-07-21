<?php
require_once __DIR__ . '/src/bootstrap.php';
use App\Auth;
use App\Mailer;
use App\Models\Message;
use App\Models\Team;
use App\Models\Template;
use App\Models\Ticket;
use App\Models\User;

Auth::requireLogin();

$ticketId = (int) ($_GET['id'] ?? 0);
$ticket = Ticket::find($ticketId);
if (!$ticket) {
    http_response_code(404);
    die('Ticket nicht gefunden.');
}

$notice = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_fields') {
        Ticket::updateFields($ticketId, [
            'status' => $_POST['status'] ?? null,
            'priority' => $_POST['priority'] ?? null,
            'team_id' => $_POST['team_id'] !== '' ? (int) $_POST['team_id'] : null,
            'assigned_to_id' => $_POST['assigned_to_id'] !== '' ? (int) $_POST['assigned_to_id'] : null,
        ]);
        header('Location: /ticket.php?id=' . $ticketId);
        exit;
    }

    if ($action === 'add_note') {
        $body = trim((string) ($_POST['note_body'] ?? ''));
        if ($body !== '') {
            Message::create($ticketId, 'INTERNAL_NOTE', $body, null, Auth::userId());
            Ticket::touch($ticketId);
        }
        header('Location: /ticket.php?id=' . $ticketId);
        exit;
    }

    if ($action === 'send_reply') {
        $body = trim((string) ($_POST['reply_body'] ?? ''));
        if ($body === '') {
            $error = 'Bitte einen Antworttext eingeben.';
        } else {
            $lastIncoming = Message::lastIncoming($ticketId);
            $subject = '[TICKET-' . $ticketId . '] ' . $ticket['subject'];
            try {
                $messageId = Mailer::sendReply(
                    $ticket['requester_email'],
                    $subject,
                    $body,
                    $lastIncoming['message_id'] ?? null,
                    $ticketId,
                    $ticket['requester_name'] ?: null
                );
                Message::create($ticketId, 'OUTGOING', $body, null, Auth::userId(), $messageId);
                Ticket::updateFields($ticketId, ['status' => 'PENDING']);
                header('Location: /ticket.php?id=' . $ticketId);
                exit;
            } catch (\Throwable $e) {
                error_log('[mailer] ' . $e->getMessage());
                $error = 'Die Antwort konnte nicht versendet werden. Bitte SMTP-Konfiguration prüfen.';
            }
        }
    }
}

$ticket = Ticket::find($ticketId); // frisch nach evtl. Änderungen
$messages = Message::forTicket($ticketId);
$teams = Team::all();
$users = User::all();
$templates = Template::all();

$statusLabels = ['OPEN' => 'Offen', 'PENDING' => 'Wartend', 'RESOLVED' => 'Gelöst', 'CLOSED' => 'Geschlossen'];
$priorityLabels = ['LOW' => 'Niedrig', 'MEDIUM' => 'Mittel', 'HIGH' => 'Hoch', 'URGENT' => 'Dringend'];
$directionLabels = ['INCOMING' => '📩', 'OUTGOING' => '📤', 'INTERNAL_NOTE' => '📝'];

$activePage = 'tickets';
require __DIR__ . '/src/Views/header.php';

// Vorlagen für JS vorbereiten (bereits serverseitig gerendert, JSON-escaped für sicheres Einbetten)
$templateMap = [];
foreach ($templates as $tpl) {
    $rendered = Template::render($tpl['body'], [
        'requester' => ['name' => $ticket['requester_name'] ?: $ticket['requester_email']],
        'ticket' => ['number' => $ticket['id'], 'subject' => $ticket['subject']],
        'agent' => ['name' => Auth::userName()],
    ]);
    $templateMap[$tpl['id']] = $rendered;
}
?>

<script>window.TEMPLATES = <?= json_encode($templateMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;</script>

<div class="grid-2">
  <div>
    <h1 class="mt-0" style="margin-bottom:4px;">#<?= (int) $ticket['id'] ?> — <?= e($ticket['subject']) ?></h1>
    <p class="text-muted mb-16">
      Von <strong><?= e($ticket['requester_name'] ?: $ticket['requester_email']) ?></strong>
      &lt;<?= e($ticket['requester_email']) ?>&gt;
      <span class="badge badge-<?= strtolower(e($ticket['status'])) ?>" style="margin-left:8px;"><?= e($statusLabels[$ticket['status']] ?? $ticket['status']) ?></span>
      <span class="badge badge-<?= strtolower(e($ticket['priority'])) ?>"><?= e($priorityLabels[$ticket['priority']] ?? $ticket['priority']) ?></span>
    </p>

    <div class="thread">
      <?php foreach ($messages as $m): ?>
        <?php
          $cls = $m['direction'] === 'INCOMING' ? 'message-incoming' : ($m['direction'] === 'OUTGOING' ? 'message-outgoing' : 'message-note');
          $avatarCls = $m['direction'] === 'INCOMING' ? 'avatar-muted' : ($m['direction'] === 'INTERNAL_NOTE' ? 'avatar-note' : '');
          $senderName = $m['direction'] === 'INCOMING' ? ($ticket['requester_name'] ?: $ticket['requester_email'])
                 : ($m['direction'] === 'OUTGOING' ? ($m['author_name'] ?? 'Team')
                 : ($m['author_name'] ?? ''));
          $label = $m['direction'] === 'INTERNAL_NOTE' ? 'Interne Notiz' : ($m['direction'] === 'INCOMING' ? 'Kunde' : 'Antwort');
        ?>
        <div class="message <?= $cls ?>">
          <span class="avatar <?= $avatarCls ?>"><?= e(mb_strtoupper(mb_substr((string) $senderName, 0, 1))) ?></span>
          <div class="message-content">
            <div class="message-meta">
              <span><strong><?= e((string) $senderName) ?></strong> · <?= $directionLabels[$m['direction']] ?? '' ?> <?= e($label) ?></span>
              <span><?= e(date('d.m.Y H:i', strtotime($m['created_at']))) ?></span>
            </div>
            <div class="message-body"><?= e($m['body']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="tabs">
        <button type="button" id="tab-reply-btn" class="tab-btn active-reply">Antwort an Kunde</button>
        <button type="button" id="tab-note-btn" class="tab-btn">Interne Notiz</button>
      </div>

      <form id="reply-form" method="post" action="/ticket.php?id=<?= (int) $ticketId ?>">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="send_reply">
        <div class="field">
          <label for="template_select">Vorlage (optional)</label>
          <select id="template_select">
            <option value="">Vorlage wählen...</option>
            <?php foreach ($templates as $tpl): ?>
              <option value="<?= (int) $tpl['id'] ?>"><?= e($tpl['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <textarea id="reply_body" name="reply_body" rows="7" placeholder="Antwort schreiben..."></textarea>
        </div>
        <button type="submit" class="btn">Antwort senden</button>
      </form>

      <form id="note-form" method="post" action="/ticket.php?id=<?= (int) $ticketId ?>" style="display:none;">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="add_note">
        <div class="field">
          <textarea name="note_body" rows="5" placeholder="Interne Notiz (nicht sichtbar für Kunde)..."></textarea>
        </div>
        <button type="submit" class="btn" style="background:#f5c518;">Notiz hinzufügen</button>
      </form>
    </div>
  </div>

  <div class="card sidebar">
    <h2>Ticket-Details</h2>
    <form method="post" action="/ticket.php?id=<?= (int) $ticketId ?>" onchange="this.requestSubmit()">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="update_fields">

      <div class="sidebar-section">
        <div class="sidebar-section-title">Status &amp; Priorität</div>
        <div class="field">
          <label for="status">Status</label>
          <select id="status" name="status">
            <?php foreach ($statusLabels as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= $ticket['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" style="margin-bottom:0;">
          <label for="priority">Priorität</label>
          <select id="priority" name="priority">
            <?php foreach ($priorityLabels as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= $ticket['priority'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="sidebar-section">
        <div class="sidebar-section-title">Zuordnung</div>
        <div class="field">
          <label for="team_id">Team</label>
          <select id="team_id" name="team_id">
            <option value="">Kein Team</option>
            <?php foreach ($teams as $team): ?>
              <option value="<?= (int) $team['id'] ?>" <?= (int) $ticket['team_id'] === (int) $team['id'] ? 'selected' : '' ?>>
                <?= e($team['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" style="margin-bottom:0;">
          <label for="assigned_to_id">Zugewiesen an</label>
          <select id="assigned_to_id" name="assigned_to_id">
            <option value="">Nicht zugewiesen</option>
            <?php foreach ($users as $u): ?>
              <option value="<?= (int) $u['id'] ?>" <?= (int) $ticket['assigned_to_id'] === (int) $u['id'] ? 'selected' : '' ?>>
                <?= e($u['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="sidebar-section">
        <div class="sidebar-section-title">Zeitverlauf</div>
        <p class="small text-muted" style="margin:0 0 4px;">Erstellt: <?= e(date('d.m.Y H:i', strtotime($ticket['created_at']))) ?></p>
        <p class="small text-muted" style="margin:0;">Aktualisiert: <?= e(date('d.m.Y H:i', strtotime($ticket['updated_at']))) ?></p>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/src/Views/footer.php'; ?>
