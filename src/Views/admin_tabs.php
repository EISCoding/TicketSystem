<?php $current = basename($_SERVER['SCRIPT_NAME']); ?>
<h1>Administration</h1>
<div class="tabs" style="margin-bottom:20px;">
  <a href="/admin/teams.php" class="tab-btn <?= $current === 'teams.php' ? 'active-reply' : '' ?>" style="text-decoration:none; display:inline-block;">Teams &amp; Routing</a>
  <a href="/admin/users.php" class="tab-btn <?= $current === 'users.php' ? 'active-reply' : '' ?>" style="text-decoration:none; display:inline-block;">Benutzer</a>
  <a href="/admin/templates.php" class="tab-btn <?= $current === 'templates.php' ? 'active-reply' : '' ?>" style="text-decoration:none; display:inline-block;">Antwort-Vorlagen</a>
</div>
