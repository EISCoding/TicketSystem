<?php
require_once __DIR__ . '/../src/bootstrap.php';
use App\Auth;
use App\Models\User;

Auth::requireAdmin();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? 'AGENT') === 'ADMIN' ? 'ADMIN' : 'AGENT';

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            $error = 'Bitte gültigen Namen, Email und ein Passwort mit mind. 8 Zeichen angeben.';
        } else {
            try {
                User::create($name, $email, $password, $role);
            } catch (\PDOException $e) {
                $error = 'Diese Email-Adresse ist bereits vergeben.';
            }
        }
    }

    if ($action === 'delete' && !$error) {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id !== Auth::userId()) { // Verhindert versehentliches Selbst-Löschen
            User::delete($id);
        }
        header('Location: /admin/users.php');
        exit;
    }

    if (!$error && $action === 'create') {
        header('Location: /admin/users.php');
        exit;
    }
}

$users = User::all();
$activePage = 'admin';
require __DIR__ . '/../src/Views/header.php';
require __DIR__ . '/../src/Views/admin_tabs.php';
?>

<div class="grid-2">
  <div class="card" style="padding:0;">
    <?php foreach ($users as $u): ?>
      <div class="list-item" style="padding:14px 16px;">
        <div>
          <strong><?= e($u['name']) ?></strong> <span class="small text-muted">(<?= e($u['role']) ?>)</span>
          <div class="small text-muted"><?= e($u['email']) ?></div>
        </div>
        <?php if ((int) $u['id'] !== Auth::userId()): ?>
          <form method="post" action="/admin/users.php" data-confirm="Benutzer '<?= e($u['name']) ?>' wirklich löschen?">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <button type="submit" class="btn btn-danger" style="padding:5px 12px; font-size:0.8rem;">Löschen</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <form class="card" method="post" action="/admin/users.php">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="create">
    <h2 class="mt-0" style="font-size:1rem;">Neuer Benutzer</h2>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <div class="field">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" required>
    </div>
    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required>
    </div>
    <div class="field">
      <label for="password">Passwort (mind. 8 Zeichen)</label>
      <input type="password" id="password" name="password" minlength="8" required>
    </div>
    <div class="field">
      <label for="role">Rolle</label>
      <select id="role" name="role">
        <option value="AGENT">Agent</option>
        <option value="ADMIN">Admin</option>
      </select>
    </div>
    <button type="submit" class="btn" style="width:100%;">Benutzer anlegen</button>
  </form>
</div>

<?php require __DIR__ . '/../src/Views/footer.php'; ?>
