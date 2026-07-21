<?php
/** @var string $activePage */
use App\Auth;
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ticketsystem</title>
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="header">
  <div class="header-left">
    <span class="brand">🎫 Ticketsystem</span>
    <nav>
      <a href="/tickets.php" class="<?= ($activePage ?? '') === 'tickets' ? 'active' : '' ?>">Tickets</a>
      <?php if (Auth::isAdmin()): ?>
        <a href="/admin/teams.php" class="<?= ($activePage ?? '') === 'admin' ? 'active' : '' ?>">Admin</a>
      <?php endif; ?>
    </nav>
  </div>
  <div class="header-right">
    <span><?= e(Auth::userName()) ?></span>
    <a href="/logout.php">Abmelden</a>
  </div>
</header>
<main class="container">
