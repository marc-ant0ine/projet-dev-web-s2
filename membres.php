<?php
require_once 'includes/config.php';
startSession();
$pageTitle  = 'Membres';
$activePage = 'membres';
require_once 'includes/header.php';

$pdo = getDB();

$q           = sanitize($_GET['q'] ?? '');
$filterNiveau= sanitize($_GET['niveau'] ?? '');

$sql = "SELECT id,login,age,sexe,type_membre,photo,niveau,points,nb_connexions,date_inscription
        FROM utilisateurs WHERE statut='actif'";
$params = [];
if ($q) {
    $sql .= " AND login LIKE ?"; $params[] = "%$q%";
}
if ($filterNiveau) {
    $sql .= " AND niveau = ?"; $params[] = $filterNiveau;
}
$sql .= " ORDER BY points DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$membres = $stmt->fetchAll();

$niveauEmoji = ['débutant'=>'🌱','intermédiaire'=>'⚡','avancé'=>'🚀','expert'=>'👑'];
?>

<!-- Filtres -->
<div class="card" style="margin-bottom:20px">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <div class="search-bar" style="flex:1;min-width:200px">
      <span style="font-size:20px;color:var(--text-muted)">🔍</span>
      <input type="text" name="q" placeholder="Rechercher par login..." value="<?= sanitize($q) ?>">
    </div>
    <select name="niveau" class="filter-select" onchange="this.form.submit()">
      <option value="">Tous niveaux</option>
      <?php foreach (['débutant','intermédiaire','avancé','expert'] as $n): ?>
      <option value="<?= $n ?>" <?= $filterNiveau === $n ? 'selected' : '' ?>><?= $niveauEmoji[$n] ?> <?= ucfirst($n) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary btn-sm">Filtrer</button>
    <?php if ($q || $filterNiveau): ?><a href="membres.php" class="btn btn-secondary btn-sm">✕</a><?php endif; ?>
  </form>
</div>

<div class="section-header">
  <div>
    <h2 class="section-title"><?= count($membres) ?> membre<?= count($membres) > 1 ? 's' : '' ?></h2>
    <p class="section-sub">Liste des membres</p>
  </div>
</div>

<div class="row g-3">
  <?php foreach ($membres as $m): ?>
  <div class="col-sm-6 col-md-4 col-lg-3">
    <div class="card h-100" style="text-align:center">
      <div class="card-body">
        <div style="font-size:40px;margin-bottom:10px">
          <?= $niveauEmoji[$m['niveau']] ?? '👤' ?>
        </div>
        <div style="font-size:16px;font-weight:600;margin-bottom:4px"><?= sanitize($m['login']) ?></div>
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:10px">
          <?= sanitize($m['type_membre']) ?>
          <?php if ($m['age']): ?> · <?= $m['age'] ?> ans<?php endif; ?>
        </div>
        <span class="badge-niveau <?= $m['niveau'] ?>"><?= $niveauEmoji[$m['niveau']] ?> <?= getNiveauLabel($m['niveau']) ?></span>
        <div style="margin-top:10px;font-size:12px;color:var(--text-secondary)">
          <span style="color:var(--accent);font-weight:600"><?= number_format($m['points'], 1) ?> pts</span>
          · <?= $m['nb_connexions'] ?> co.
        </div>
        <div style="font-size:11px;color:var(--text-muted);margin-top:4px">
          Depuis <?= date('d/m/Y', strtotime($m['date_inscription'])) ?>
        </div>
        <?php if ($m['id'] === $user['id']): ?>
        <div style="margin-top:8px;font-size:11px;color:var(--accent)">← Vous</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
