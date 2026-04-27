<?php
require_once 'includes/config.php';
startSession();
$pageTitle  = 'Actualités';
$activePage = 'actualites';
require_once 'includes/header.php';

$pdo = getDB();
$actualites = $pdo->query("SELECT a.*, u.login as auteur FROM actualites a LEFT JOIN utilisateurs u ON a.auteur_id = u.id WHERE a.visible=1 ORDER BY a.date_publication DESC")->fetchAll();
?>

<div class="section-header">
  <div>
    <h2 class="section-title">Actualités de la maison</h2>
    <p class="section-sub">Informations et mises à jour</p>
  </div>
</div>

<?php if (empty($actualites)): ?>
<div class="empty-state"><div class="icon">📰</div><h3>Aucune actualité</h3></div>
<?php else: ?>
<div style="max-width:700px;display:flex;flex-direction:column;gap:16px">
  <?php foreach ($actualites as $news): ?>
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <div class="news-date"><?= date('d/m/Y à H:i', strtotime($news['date_publication'])) ?></div>
      <?php if ($news['auteur']): ?><span style="font-size:11px;color:var(--text-muted)">par <?= sanitize($news['auteur']) ?></span><?php endif; ?>
    </div>
    <div class="news-title" style="font-size:18px"><?= sanitize($news['titre']) ?></div>
    <div style="color:var(--text-secondary);margin-top:10px;line-height:1.7"><?= nl2br(sanitize($news['contenu'])) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
