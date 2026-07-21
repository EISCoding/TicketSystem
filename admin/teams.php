<?php
require_once __DIR__ . '/../src/bootstrap.php';
use App\Auth;
use App\Models\Team;

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $keywords = trim((string) ($_POST['keywords'] ?? ''));
        $isDefault = isset($_POST['is_default']);
        if ($name !== '') {
            Team::create($name, $keywords, $isDefault);
        }
    }

    if ($action === 'delete') {
        Team::delete((int) ($_POST['id'] ?? 0));
    }

    header('Location: /admin/teams.php');
    exit;
}

$teams = Team::all();
$activePage = 'admin';
require __DIR__ . '/../src/Views/header.php';
require __DIR__ . '/../src/Views/admin_tabs.php';
?>

<div class="grid-2">
  <div class="card" style="padding:0;">
    <?php if (empty($teams)): ?>
      <p class="text-muted" style="padding:16px;">Keine Teams angelegt.</p>
    <?php endif; ?>
    <?php foreach ($teams as $team): ?>
      <div class="list-item" style="padding:14px 16px;">
        <div>
          <strong><?= e($team['name']) ?></strong>
          <?php if ((int) $team['is_default'] === 1): ?><span class="small text-muted">(Standard)</span><?php endif; ?>
          <div class="small text-muted">Keywords: <?= e($team['keywords'] ?: '–') ?></div>
        </div>
        <form method="post" action="/admin/teams.php" data-confirm="Team '<?= e($team['name']) ?>' wirklich löschen?">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int) $team['id'] ?>">
          <button type="submit" class="btn btn-danger" style="padding:5px 12px; font-size:0.8rem;">Löschen</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>

  <form class="card" method="post" action="/admin/teams.php">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="create">
    <h2 class="mt-0" style="font-size:1rem;">Neues Team</h2>
    <div class="field">
      <label for="name">Team-Name</label>
      <input type="text" id="name" name="name" required>
    </div>
    <div class="field">
      <label for="keywords">Keywords, kommagetrennt</label>
      <input type="text" id="keywords" name="keywords" placeholder="rechnung,billing,zahlung">
    </div>
    <div class="field">
      <label style="display:flex; align-items:center; gap:6px; font-weight:normal;">
        <input type="checkbox" name="is_default" style="width:auto;"> Standard-Team (Fallback)
      </label>
    </div>
    <button type="submit" class="btn" style="width:100%;">Team anlegen</button>
  </form>
</div>

<?php require __DIR__ . '/../src/Views/footer.php'; ?>
