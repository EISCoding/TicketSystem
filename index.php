<?php
require_once __DIR__ . '/src/bootstrap.php';
use App\Auth;

header('Location: ' . (Auth::check() ? '/tickets.php' : '/login.php'));
exit;
