<?php
require_once __DIR__ . '/../src/bootstrap.php';
use App\Auth;
use App\Models\Template;

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($name !== '' && $subject !== '' && $body !== '') {
            Template::create($name, $subject, $body);
        }
    }

    if ($action === 'delete') {
        Template::delete((int) ($_POST['id'] ?? 0));
    }

    header('Location: /admin/templates.php');
    exit;
}

$templates = Template::all();
$activePage = 'admin';
require __DIR__ . '/../src/Views/header.php';
require __DIR__ . '/../src/Views/admin_tabs.php';
?>

<div class="grid-2">
  <div class="card" style="padding:0;">
    <?php if (empty($templates)): ?>
      <p class="text-muted" style="padding:16px;">Keine Vorlagen angelegt.</p>
    <?php endif; ?>
    <?php foreach ($templates as $tpl): ?>
      <div style="padding:14px 16px; border-top:1px solid var(--border);">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <strong><?= e($tpl['name']) ?></strong>
          <form method="post" action="/admin/templates.php" data-confirm="Vorlage '<?= e($tpl['name']) ?>' wirklich löschen?">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $tpl['id'] ?>">
            <button type="submit" class="btn btn-danger" style="padding:4px 10px; font-size:0.75rem;"><i class='bx bx-trash'></i> Löschen</button>
          </form>
        </div>
        <div class="small text-muted">Betreff: <?= e($tpl['subject']) ?></div>
        <div class="small mono" style="background:var(--surface-2); border-radius:8px; padding:8px; margin-top:6px; white-space:pre-wrap;"><?= e($tpl['body']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <form class="card" method="post" action="/admin/templates.php">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="create">
    <h2 class="mt-0" style="font-size:1rem; display:flex; align-items:center; gap:8px;"><i class='bx bx-file-blank icon'></i> Neue Vorlage</h2>
    <p class="small text-muted">Platzhalter: <code>{{requester.name}}</code>, <code>{{ticket.number}}</code> (Fall-Nr.), <code>{{ticket.subject}}</code>, <code>{{agent.name}}</code></p>
    <div class="field">
      <label for="name">Name der Vorlage</label>
      <input type="text" id="name" name="name" required>
    </div>
    <div class="field">
      <label for="subject">Betreff</label>
      <input type="text" id="subject" name="subject" required>
    </div>
    <div class="field">
      <label for="body">Text der Vorlage</label>
      <textarea id="body" name="body" rows="6" required></textarea>
    </div>
    <button type="submit" class="btn" style="width:100%;"><i class='bx bx-plus'></i> Vorlage anlegen</button>
  </form>
</div>

<?php require __DIR__ . '/../src/Views/footer.php'; ?>
