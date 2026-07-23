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
        $body = sanitizeHtml((string) ($_POST['body'] ?? ''));
        if ($name !== '' && $subject !== '' && !isHtmlEmpty($body)) {
            Template::create($name, $subject, $body);
        }
        header('Location: /admin/templates.php');
        exit;
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = sanitizeHtml((string) ($_POST['body'] ?? ''));
        if ($id > 0 && $name !== '' && $subject !== '' && !isHtmlEmpty($body)) {
            Template::update($id, $name, $subject, $body);
        }
        header('Location: /admin/templates.php');
        exit;
    }

    if ($action === 'delete') {
        Template::delete((int) ($_POST['id'] ?? 0));
        header('Location: /admin/templates.php');
        exit;
    }
}

$templates = Template::all();
$editId = (int) ($_GET['edit'] ?? 0);
$editingTemplate = $editId > 0 ? Template::find($editId) : null;

$activePage = 'admin';
$needsEditor = true;
require __DIR__ . '/../src/Views/header.php';
require __DIR__ . '/../src/Views/admin_tabs.php';
?>

<div class="grid-2">
  <div class="card" style="padding:0;">
    <?php if (empty($templates)): ?>
      <p class="text-muted" style="padding:16px;">Keine Vorlagen angelegt.</p>
    <?php endif; ?>
    <?php foreach ($templates as $tpl): ?>
      <?php
        $isEditingThis = $editId === (int) $tpl['id'];
        $preview = preg_replace('/<\/(p|div|h[1-6]|li|blockquote)>/i', '$0 ', $tpl['body']) ?? $tpl['body'];
        $preview = html_entity_decode(strip_tags($preview), ENT_QUOTES, 'UTF-8');
        $preview = trim(preg_replace('/\s+/', ' ', $preview) ?? $preview);
        if (mb_strlen($preview) > 160) {
            $preview = mb_substr($preview, 0, 160) . '…';
        }
      ?>
      <div style="padding:14px 16px; border-top:1px solid var(--border); <?= $isEditingThis ? 'background:var(--brand-light);' : '' ?>">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
          <strong><?= e($tpl['name']) ?></strong>
          <div style="display:flex; gap:6px; flex-shrink:0;">
            <a href="/admin/templates.php?edit=<?= (int) $tpl['id'] ?>" class="btn btn-secondary" style="padding:4px 10px; font-size:0.75rem;"><i class='bx bx-edit'></i> Bearbeiten</a>
            <form method="post" action="/admin/templates.php" data-confirm="Vorlage '<?= e($tpl['name']) ?>' wirklich löschen?">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $tpl['id'] ?>">
              <button type="submit" class="btn btn-danger" style="padding:4px 10px; font-size:0.75rem;"><i class='bx bx-trash'></i> Löschen</button>
            </form>
          </div>
        </div>
        <div class="small text-muted">Betreff: <?= e($tpl['subject']) ?></div>
        <div class="small text-muted" style="margin-top:6px;"><?= e($preview) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <form id="template-form" class="card" method="post" action="/admin/templates.php">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="<?= $editingTemplate ? 'update' : 'create' ?>">
    <?php if ($editingTemplate): ?>
      <input type="hidden" name="id" value="<?= (int) $editingTemplate['id'] ?>">
    <?php endif; ?>
    <h2 class="mt-0" style="font-size:1rem; display:flex; align-items:center; gap:8px;">
      <i class='bx bx-file-blank icon'></i> <?= $editingTemplate ? 'Vorlage bearbeiten' : 'Neue Vorlage' ?>
    </h2>
    <p class="small text-muted">Platzhalter: <code>{{requester.name}}</code>, <code>{{ticket.number}}</code> (Fall-Nr.), <code>{{ticket.subject}}</code>, <code>{{agent.name}}</code></p>
    <div class="field">
      <label for="name">Name der Vorlage</label>
      <input type="text" id="name" name="name" value="<?= e($editingTemplate['name'] ?? '') ?>" required>
    </div>
    <div class="field">
      <label for="subject">Betreff</label>
      <input type="text" id="subject" name="subject" value="<?= e($editingTemplate['subject'] ?? '') ?>" required>
    </div>
    <div class="field">
      <label for="body">Text der Vorlage</label>
      <textarea id="body" name="body" style="display:none;"><?= e($editingTemplate ? ensureHtml($editingTemplate['body']) : '') ?></textarea>
      <div id="body_editor" class="editor-wrap is-compact"></div>
    </div>
    <button type="submit" class="btn" style="width:100%;">
      <i class='bx <?= $editingTemplate ? 'bx-save' : 'bx-plus' ?>'></i> <?= $editingTemplate ? 'Änderungen speichern' : 'Vorlage anlegen' ?>
    </button>
    <?php if ($editingTemplate): ?>
      <a href="/admin/templates.php" class="btn btn-secondary" style="width:100%; margin-top:8px; justify-content:center;">Abbrechen</a>
    <?php endif; ?>
  </form>
</div>

<?php require __DIR__ . '/../src/Views/footer.php'; ?>
