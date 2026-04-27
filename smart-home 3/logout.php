<?php
require_once 'includes/config.php';
startSession();
session_destroy();
header('Location: ' . SITE_URL . '/index.php');
exit;
