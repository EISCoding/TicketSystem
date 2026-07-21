<?php
require_once __DIR__ . '/src/bootstrap.php';
use App\Auth;

if (Auth::check()) {
    header('Location: /tickets.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Bitte Email und Passwort eingeben.';
    } elseif (Auth::attemptLogin($email, $password)) {
        header('Location: /tickets.php');
        exit;
    } else {
        $error = 'Ungültige Zugangsdaten oder Konto vorübergehend gesperrt.';
    }
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Ticketsystem</title>
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-brand">
    <span class="brand-icon">🎫</span>
    <h1>Ticketsystem</h1>
    <p>Alle Kundenanfragen an einem Ort — zuweisen, beantworten und im Blick behalten.</p>
  </div>
  <div class="login-form-side">
    <form class="login-card" method="post" action="/login.php">
      <h1>Willkommen zurück</h1>
      <p>Melde dich mit deinem Team-Account an.</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
      <?php endif; ?>

      <?= Auth::csrfField() ?>

      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus>
      </div>
      <div class="field">
        <label for="password">Passwort</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="btn" style="width:100%">Anmelden</button>
    </form>
  </div>
</div>
</body>
</html>
