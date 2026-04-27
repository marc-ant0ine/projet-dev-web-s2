<?php
// includes/header.php
// Utilisé dans toutes les pages connectées
// Paramètres : $pageTitle, $activePage

if (!defined('INCLUDED')) define('INCLUDED', true);
startSession();
requireLogin();
$user = getCurrentUser();
if (!$user) { redirect(SITE_URL . '/login.php'); }

$flash = getFlash();

$niveauEmoji = [
    'débutant'      => '🌱',
    'intermédiaire' => '⚡',
    'avancé'        => '🚀',
    'expert'        => '👑',
];
$emoji = $niveauEmoji[$user['niveau']] ?? '🌱';

// Calculer % vers prochain niveau
$nextNiveauSeuil = [
    'débutant'      => POINTS_INTERMEDIAIRE,
    'intermédiaire' => POINTS_AVANCE,
    'avancé'        => POINTS_EXPERT,
    'expert'        => null,
];
$currentSeuil = [
    'débutant'      => 0,
    'intermédiaire' => POINTS_INTERMEDIAIRE,
    'avancé'        => POINTS_AVANCE,
    'expert'        => POINTS_EXPERT,
];
$next   = $nextNiveauSeuil[$user['niveau']] ?? null;
$from   = $currentSeuil[$user['niveau']] ?? 0;
$pct    = $next ? min(100, round(($user['points'] - $from) / ($next - $from) * 100)) : 100;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sanitize($pageTitle ?? 'Dashboard') ?> — SmartHome</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/css/style.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
<meta name="theme-color" content="#0a0e1a">
</head>
<body>
<div class="layout">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">🏠</div>
    <div class="logo-text">Smart<span>Home</span></div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-title">Navigation</div>

    <a href="<?= SITE_URL ?>/index.php" class="nav-item <?= ($activePage ?? '') === 'accueil' ? 'active' : '' ?>">
      <span class="nav-icon"><span class="material-icons" style="font-size:18px">home</span></span>
      Accueil
    </a>

    <a href="<?= SITE_URL ?>/actualites.php" class="nav-item <?= ($activePage ?? '') === 'actualites' ? 'active' : '' ?>">
      <span class="nav-icon"><span class="material-icons" style="font-size:18px">newspaper</span></span>
      Actualités
    </a>

    <div class="nav-section-title">Espace membre</div>

    <a href="<?= SITE_URL ?>/dashboard.php" class="nav-item <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
      <span class="nav-icon"><span class="material-icons" style="font-size:18px">dashboard</span></span>
      Tableau de bord
    </a>

    <a href="<?= SITE_URL ?>/profil.php" class="nav-item <?= ($activePage ?? '') === 'profil' ? 'active' : '' ?>">
      <span class="nav-icon"><span class="material-icons" style="font-size:18px">person</span></span>
      Mon profil
    </a>

    <a href="<?= SITE_URL ?>/membres.php" class="nav-item <?= ($activePage ?? '') === 'membres' ? 'active' : '' ?>">
      <span class="nav-icon"><span class="material-icons" style="font-size:18px">group</span></span>
      Membres
    </a>

    <a href="<?= SITE_URL ?>/objets.php" class="nav-item <?= ($activePage ?? '') === 'objets' ? 'active' : '' ?>">
      <span class="nav-icon"><span class="material-icons" style="font-size:18px">devices</span></span>
      Objets connectés
    </a>

    <?php if (in_array($user['niveau'], ['avancé', 'expert'])): ?>
    <div class="nav-section-title">Gestion</div>

    <a href="<?= SITE_URL ?>/gestion/dashboard.php" class="nav-item <?= ($activePage ?? '') === 'gestion' ? 'active' : '' ?>">
      <span class="nav-icon"><span class="material-icons" style="font-size:18px">settings</span></span>
      Gestion avancée
    </a>

    <a href="<?= SITE_URL ?>/gestion/rapports.php" class="nav-item <?= ($activePage ?? '') === 'rapports' ? 'active' : '' ?>">
      <span class="nav-icon"><span class="material-icons" style="font-size:18px">bar_chart</span></span>
      Rapports
    </a>
    <?php endif; ?>

    <?php if ($user['niveau'] === 'expert'): ?>
    <div class="nav-section-title">Administration</div>
    <a href="<?= SITE_URL ?>/admin/utilisateurs.php" class="nav-item <?= ($activePage ?? '') === 'admin' ? 'active' : '' ?>">
      <span class="nav-icon"><span class="material-icons" style="font-size:18px">admin_panel_settings</span></span>
      Administration
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="sidebar-avatar">
        <?php if ($user['photo'] && $user['photo'] !== 'default.png'): ?>
          <img src="<?= SITE_URL ?>/assets/img/<?= sanitize($user['photo']) ?>" alt="avatar">
        <?php else: ?>
          <?= $emoji ?>
        <?php endif; ?>
      </div>
      <div class="sidebar-user-info">
        <div class="name"><?= sanitize($user['login']) ?></div>
        <div class="niveau"><?= $emoji ?> <?= getNiveauLabel($user['niveau']) ?></div>
      </div>
    </div>
    <div style="margin-top:10px">
      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);margin-bottom:4px">
        <span><?= number_format($user['points'], 2) ?> pts</span>
        <?php if ($next): ?><span>→ <?= $next ?></span><?php endif; ?>
      </div>
      <div class="progress-bar">
        <div class="progress-fill" style="width:<?= $pct ?>%"></div>
      </div>
    </div>
    <a href="<?= SITE_URL ?>/logout.php" class="btn btn-secondary btn-sm" style="width:100%;margin-top:12px;justify-content:center">
      <span class="material-icons" style="font-size:15px">logout</span> Déconnexion
    </a>
  </div>
</aside>

<!-- BOUTON MENU MOBILE -->
<button class="btn btn-secondary btn-icon" id="menu-toggle" style="position:fixed;top:14px;left:14px;z-index:150;display:none">
  <span class="material-icons">menu</span>
</button>

<!-- CONTENU PRINCIPAL -->
<main class="main-content">
  <div class="topbar">
    <h1 class="topbar-title"><?= sanitize($pageTitle ?? 'Dashboard') ?></h1>
    <div style="display:flex;align-items:center;gap:10px">
      <span class="badge-niveau <?= $user['niveau'] ?>"><?= $emoji ?> <?= getNiveauLabel($user['niveau']) ?></span>
    </div>
  </div>

  <div class="page-content">
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'danger' : 'info') ?> alert-dismissible fade show">
      <?= sanitize($flash['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
