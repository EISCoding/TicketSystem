<?php
require_once __DIR__ . '/../src/bootstrap.php';
use App\Auth;
use App\Models\Ticket;

Auth::requireAdmin();

$statusLabels = ['OPEN' => 'Offen', 'PENDING' => 'Wartend', 'RESOLVED' => 'Gelöst', 'CLOSED' => 'Geschlossen'];
$priorityLabels = ['LOW' => 'Niedrig', 'MEDIUM' => 'Mittel', 'HIGH' => 'Hoch', 'URGENT' => 'Dringend'];
$statusFill = ['OPEN' => 'f-info', 'PENDING' => 'f-warn', 'RESOLVED' => 'f-success', 'CLOSED' => 'f-neutral'];
$priorityFill = ['LOW' => 'f-neutral', 'MEDIUM' => 'f-info', 'HIGH' => 'f-warn', 'URGENT' => 'f-danger'];

$total = Ticket::totalCount();
$statusCounts = Ticket::countsByStatus();
$priorityCounts = Ticket::countsByPriority();
$trend = Ticket::createdPerDay(14);
$teamCounts = Ticket::countsByTeam();
$agentCounts = Ticket::openCountsByAgent();
$avgFirstResponse = Ticket::avgFirstResponseHours();

$trendMax = max(1, ...array_column($trend, 'count'));
$statusMax = max(1, ...array_values($statusCounts));
$priorityMax = max(1, ...array_values($priorityCounts));
$teamMax = max(1, ...array_map(fn ($r) => $r['count'], $teamCounts) ?: [0]);
$agentMax = max(1, ...array_map(fn ($r) => $r['count'], $agentCounts) ?: [0]);

$activePage = 'admin';
require __DIR__ . '/../src/Views/header.php';
require __DIR__ . '/../src/Views/admin_tabs.php';
?>

<div class="stat-grid">
  <div class="stat-tile">
    <div class="stat-icon c-brand"><i class='bx bx-folder'></i></div>
    <div class="stat-value mono"><?= $total ?></div>
    <div class="stat-label">Fälle gesamt</div>
  </div>
  <div class="stat-tile">
    <div class="stat-icon c-info"><i class='bx bx-loader-circle'></i></div>
    <div class="stat-value mono"><?= $statusCounts['OPEN'] ?></div>
    <div class="stat-label">Offen</div>
  </div>
  <div class="stat-tile">
    <div class="stat-icon c-warn"><i class='bx bx-time-five'></i></div>
    <div class="stat-value mono"><?= $statusCounts['PENDING'] ?></div>
    <div class="stat-label">Wartend</div>
  </div>
  <div class="stat-tile">
    <div class="stat-icon c-danger"><i class='bx bx-error-circle'></i></div>
    <div class="stat-value mono"><?= $priorityCounts['URGENT'] ?></div>
    <div class="stat-label">Dringend</div>
  </div>
  <div class="stat-tile">
    <div class="stat-icon c-success"><i class='bx bx-history'></i></div>
    <div class="stat-value mono"><?= $avgFirstResponse !== null ? number_format($avgFirstResponse, 1, ',', '.') . ' Std.' : '–' ?></div>
    <div class="stat-label">Ø Erstantwortzeit</div>
  </div>
</div>

<div class="analytics-grid">
  <div>
    <div class="card chart-card">
      <h3><i class='bx bx-trending-up icon'></i> Neue Fälle (letzte 14 Tage)</h3>
      <p class="chart-sub">Eingegangene Fälle pro Tag</p>
      <div class="bars">
        <?php foreach ($trend as $day): ?>
          <div class="bar-col">
            <div class="bar" style="height:<?= max(4, round($day['count'] / $trendMax * 100)) ?>%" title="<?= e(date('d.m.Y', strtotime($day['date']))) ?>: <?= $day['count'] ?> Fälle"></div>
            <div class="bar-day"><?= e(date('d.m.', strtotime($day['date']))) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card chart-card">
      <h3><i class='bx bx-group icon'></i> Offene Fälle je Agent</h3>
      <p class="chart-sub">Status "Offen" oder "Wartend"</p>
      <?php if (empty($agentCounts)): ?>
        <p class="small text-muted">Keine offenen Fälle.</p>
      <?php else: ?>
        <div class="bar-list">
          <?php foreach ($agentCounts as $row): ?>
            <div class="bar-list-row">
              <span class="bl-label"><i class='bx bx-user'></i> <span class="bl-text"><?= e($row['label']) ?></span></span>
              <div class="track"><div class="fill f-brand" style="width:<?= round($row['count'] / $agentMax * 100) ?>%;"></div></div>
              <span class="count"><?= $row['count'] ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div class="card chart-card">
      <h3><i class='bx bx-pie-chart-alt-2 icon'></i> Status-Verteilung</h3>
      <div class="bar-list">
        <?php foreach ($statusCounts as $key => $count): ?>
          <div class="bar-list-row">
            <span class="bl-label"><span class="bl-text"><?= e($statusLabels[$key]) ?></span></span>
            <div class="track"><div class="fill <?= $statusFill[$key] ?>" style="width:<?= round($count / $statusMax * 100) ?>%;"></div></div>
            <span class="count"><?= $count ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card chart-card">
      <h3><i class='bx bx-flag icon'></i> Prioritäts-Verteilung</h3>
      <div class="bar-list">
        <?php foreach ($priorityCounts as $key => $count): ?>
          <div class="bar-list-row">
            <span class="bl-label"><span class="bl-text"><?= e($priorityLabels[$key]) ?></span></span>
            <div class="track"><div class="fill <?= $priorityFill[$key] ?>" style="width:<?= round($count / $priorityMax * 100) ?>%;"></div></div>
            <span class="count"><?= $count ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card chart-card">
      <h3><i class='bx bx-sitemap icon'></i> Fälle je Team</h3>
      <?php if (empty($teamCounts)): ?>
        <p class="small text-muted">Keine Fälle vorhanden.</p>
      <?php else: ?>
        <div class="bar-list">
          <?php foreach ($teamCounts as $row): ?>
            <div class="bar-list-row">
              <span class="bl-label"><span class="bl-text"><?= e($row['label']) ?></span></span>
              <div class="track"><div class="fill f-brand" style="width:<?= round($row['count'] / $teamMax * 100) ?>%;"></div></div>
              <span class="count"><?= $row['count'] ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../src/Views/footer.php'; ?>
