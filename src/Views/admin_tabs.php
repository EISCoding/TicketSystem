<?php $current = basename($_SERVER['SCRIPT_NAME']); ?>
<h1>Verwaltung</h1>
<div class="tabs" style="margin-bottom:20px; flex-wrap:wrap;">
  <a href="/admin/analytics.php" class="tab-btn <?= $current === 'analytics.php' ? 'active-reply' : '' ?>" style="text-decoration:none; display:inline-flex;"><i class='bx bx-bar-chart-alt-2'></i> Analytics</a>
  <a href="/admin/teams.php" class="tab-btn <?= $current === 'teams.php' ? 'active-reply' : '' ?>" style="text-decoration:none; display:inline-flex;"><i class='bx bx-sitemap'></i> Teams &amp; Routing</a>
  <a href="/admin/users.php" class="tab-btn <?= $current === 'users.php' ? 'active-reply' : '' ?>" style="text-decoration:none; display:inline-flex;"><i class='bx bx-user'></i> Benutzer</a>
  <a href="/admin/templates.php" class="tab-btn <?= $current === 'templates.php' ? 'active-reply' : '' ?>" style="text-decoration:none; display:inline-flex;"><i class='bx bx-file'></i> Antwort-Vorlagen</a>
</div>
