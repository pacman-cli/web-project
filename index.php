<?php
// index.php
// Redirects root guest traffic to the public homepage

require_once __DIR__ . '/config/app.php';
header('Location: ' . BASE_URL . '/43_Public_Homepage/index.php');
exit;
