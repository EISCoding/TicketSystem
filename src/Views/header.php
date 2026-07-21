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
    <span class="brand">🎫 <span class="brand-label">Ticketsystem</span></span>
    <nav>
      <a href="/tickets.php" class="<?= ($activePage ?? '') === 'tickets' ? 'active' : '' ?>">Tickets</a>
      <?php if (Auth::isAdmin()): ?>
        <a href="/admin/teams.php" class="<?= ($activePage ?? '') === 'admin' ? 'active' : '' ?>">Admin</a>
      <?php endif; ?>
    </nav>
  </div>
  <div class="header-right">
    <span class="user-chip">
      <span class="avatar"><?= e(mb_strtoupper(mb_substr(Auth::userName(), 0, 1))) ?></span>
      <span class="user-name"><?= e(Auth::userName()) ?></span>
    </span>
    <a href="/logout.php">Abmelden</a>
  </div>
</header>
<main class="container">
