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
  <link rel="stylesheet" href="/assets/vendor/boxicons.min.css">
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="app-shell">
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <aside class="app-sidebar" id="appSidebar">
    <div class="sidebar-brand">
      <span class="brand-mark"><i class='bx bxs-purchase-tag'></i></span>
      <span>Ticketsystem</span>
    </div>
    <nav class="sidebar-nav">
      <a href="/tickets.php" class="<?= ($activePage ?? '') === 'tickets' ? 'active' : '' ?>"><i class='bx bx-folder'></i> Fälle</a>
      <?php if (Auth::isAdmin()): ?>
        <a href="/admin/teams.php" class="<?= ($activePage ?? '') === 'admin' ? 'active' : '' ?>"><i class='bx bx-cog'></i> Verwaltung</a>
      <?php endif; ?>
    </nav>
    <div class="sidebar-user">
      <span class="avatar"><?= e(mb_strtoupper(mb_substr(Auth::userName(), 0, 1))) ?></span>
      <div>
        <div class="name"><?= e(Auth::userName()) ?></div>
        <a class="logout-link" href="/logout.php"><i class='bx bx-log-out'></i> Abmelden</a>
      </div>
    </div>
  </aside>

  <div class="app-main">
    <header class="app-topbar">
      <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Menü öffnen" aria-controls="appSidebar" aria-expanded="false">
        <i class='bx bx-menu'></i>
      </button>
      <span class="brand-mark"><i class='bx bxs-purchase-tag'></i></span>
      <span class="topbar-title">Ticketsystem</span>
    </header>
    <main class="container">
