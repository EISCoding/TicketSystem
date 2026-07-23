<?php
require_once __DIR__ . '/src/bootstrap.php';
use App\Auth;
use App\Models\Ticket;
use App\Models\Team;

Auth::requireLogin();

$filters = [
    'status' => $_GET['status'] ?? '',
    'team_id' => $_GET['team_id'] ?? '',
    'search' => trim((string) ($_GET['search'] ?? '')),
];

$tickets = Ticket::all($filters);
$teams = Team::all();

$statusLabels = ['OPEN' => 'Offen', 'PENDING' => 'Wartend', 'RESOLVED' => 'Gelöst', 'CLOSED' => 'Geschlossen'];
$priorityLabels = ['LOW' => 'Niedrig', 'MEDIUM' => 'Mittel', 'HIGH' => 'Hoch', 'URGENT' => 'Dringend'];

$activePage = 'tickets';
require __DIR__ . '/src/Views/header.php';
?>

<h1 class="mt-0" style="margin-bottom:4px;">Fälle</h1>
<p class="text-muted mb-16"><?= count($tickets) ?> <?= count($tickets) === 1 ? 'Fall' : 'Fälle' ?> gefunden</p>

<form class="filters" method="get" action="/tickets.php">
  <div class="input-icon">
    <i class='bx bx-search'></i>
    <input type="search" name="search" placeholder="Suche im Betreff..." value="<?= e($filters['search']) ?>">
  </div>
  <select name="status">
    <option value="">Alle Status</option>
    <?php foreach ($statusLabels as $key => $label): ?>
      <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="team_id">
    <option value="">Alle Teams</option>
    <?php foreach ($teams as $team): ?>
      <option value="<?= (int) $team['id'] ?>" <?= (string) $filters['team_id'] === (string) $team['id'] ? 'selected' : '' ?>>
        <?= e($team['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn"><i class='bx bx-filter-alt icon'></i> Filtern</button>
</form>

<?php if (empty($tickets)): ?>
  <div class="card empty-state">
    <div class="empty-icon"><i class='bx bx-folder-open'></i></div>
    <p style="margin:0; font-weight:600; color:var(--text);">Keine Fälle gefunden</p>
    <p class="small" style="margin:4px 0 0;">Passe die Filter an oder warte auf neue Kundenanfragen.</p>
  </div>
<?php else: ?>
  <div class="card" style="padding:0; overflow:hidden;">
    <div class="ticket-list">
      <?php foreach ($tickets as $t): ?>
        <?php $requesterLabel = $t['requester_name'] ?: $t['requester_email']; ?>
        <a class="ticket-row" href="/ticket.php?id=<?= (int) $t['id'] ?>">
          <span class="avatar avatar-muted"><?= e(mb_strtoupper(mb_substr((string) $requesterLabel, 0, 1))) ?></span>
          <div class="ticket-row-main">
            <div class="ticket-row-title"><span class="ticket-num">Fall-Nr. <?= e(caseNumber((int) $t['id'])) ?></span><?= e($t['subject']) ?></div>
            <div class="ticket-row-sub small text-muted">
              <?= e($requesterLabel) ?> · <?= e($t['team_name'] ?? 'Kein Team') ?> · <?= e($t['assigned_name'] ?? 'Nicht zugewiesen') ?>
            </div>
          </div>
          <div class="ticket-row-badges">
            <span class="badge badge-<?= strtolower(e($t['priority'])) ?>"><?= e($priorityLabels[$t['priority']] ?? $t['priority']) ?></span>
            <span class="badge badge-<?= strtolower(e($t['status'])) ?>"><?= e($statusLabels[$t['status']] ?? $t['status']) ?></span>
          </div>
          <div class="ticket-row-time small text-muted"><?= e(date('d.m.Y H:i', strtotime($t['updated_at']))) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/src/Views/footer.php'; ?>
