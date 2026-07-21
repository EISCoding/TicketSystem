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

<h1 class="mt-0" style="margin-bottom:4px;">Tickets</h1>
<p class="text-muted mb-16"><?= count($tickets) ?> Ticket<?= count($tickets) === 1 ? '' : 's' ?> gefunden</p>

<form class="filters" method="get" action="/tickets.php">
  <input type="search" name="search" placeholder="Suche im Betreff..." value="<?= e($filters['search']) ?>">
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
  <button type="submit" class="btn">Filtern</button>
</form>

<?php if (empty($tickets)): ?>
  <div class="card empty-state">
    <div class="empty-icon">🗂️</div>
    <p style="margin:0; font-weight:600; color:var(--text);">Keine Tickets gefunden</p>
    <p class="small" style="margin:4px 0 0;">Passe die Filter an oder warte auf neue Kundenanfragen.</p>
  </div>
<?php else: ?>
  <div class="card" style="padding:0; overflow:hidden;">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th><th>Betreff</th><th>Anfragender</th><th>Team</th>
            <th>Zugewiesen</th><th>Priorität</th><th>Status</th><th>Aktualisiert</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tickets as $t): ?>
            <tr>
              <td><a href="/ticket.php?id=<?= (int) $t['id'] ?>">#<?= (int) $t['id'] ?></a></td>
              <td><a href="/ticket.php?id=<?= (int) $t['id'] ?>"><?= e($t['subject']) ?></a></td>
              <td><?= e($t['requester_name'] ?: $t['requester_email']) ?></td>
              <td><?= e($t['team_name'] ?? '–') ?></td>
              <td><?= e($t['assigned_name'] ?? '–') ?></td>
              <td><span class="badge badge-<?= strtolower(e($t['priority'])) ?>"><?= e($priorityLabels[$t['priority']] ?? $t['priority']) ?></span></td>
              <td><span class="badge badge-<?= strtolower(e($t['status'])) ?>"><?= e($statusLabels[$t['status']] ?? $t['status']) ?></span></td>
              <td class="small text-muted"><?= e(date('d.m.Y H:i', strtotime($t['updated_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/src/Views/footer.php'; ?>
