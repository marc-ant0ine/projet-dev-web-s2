<?php
// php/recherche-publique.php
// Endpoint AJAX pour la recherche publique (visiteurs)
require_once '../includes/config.php';
$pdo = getDB();

$q           = sanitize($_GET['q'] ?? '');
$filterCat   = (int)($_GET['cat'] ?? 0);
$filterPiece = (int)($_GET['piece'] ?? 0);

if (!$q && !$filterCat && !$filterPiece) {
    echo '<p style="color:var(--text-secondary);font-size:13px;text-align:center;padding:20px">Saisissez un mot-clé ou choisissez un filtre pour explorer les objets.</p>';
    exit;
}

$sql = "SELECT o.*, c.nom AS cat_nom, p.nom AS piece_nom
        FROM objets_connectes o
        LEFT JOIN categories_objets c ON o.categorie_id = c.id
        LEFT JOIN pieces p ON o.piece_id = p.id
        WHERE 1=1";
$params = [];

if ($q) {
    $sql .= " AND (o.nom LIKE ? OR o.description LIKE ? OR o.marque LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($filterCat)   { $sql .= " AND o.categorie_id = ?"; $params[] = $filterCat; }
if ($filterPiece) { $sql .= " AND o.piece_id = ?";     $params[] = $filterPiece; }
$sql .= " LIMIT 12";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$objets = $stmt->fetchAll();

$catEmojis = ['Thermostat'=>'🌡️','Éclairage'=>'💡','Sécurité'=>'🔒','Électroménager'=>'🍽️','Capteurs'=>'📡','Multimédia'=>'📺','Énergie'=>'⚡','Confort'=>'🌿'];

header('Content-Type: text/html; charset=utf-8');

if (empty($objets)): ?>
<div class="empty-state" style="padding:32px">
  <div class="icon">🔍</div>
  <h3>Aucun résultat</h3>
  <p>Essayez avec d'autres mots-clés</p>
</div>
<?php else: ?>
<p style="font-size:12px;color:var(--text-secondary);margin-bottom:14px"><?= count($objets) ?> résultat(s) trouvé(s)</p>
<div class="devices-grid">
  <?php foreach ($objets as $obj): ?>
  <div class="device-card <?= $obj['etat'] === 'inactif' ? 'inactive' : '' ?>">
    <div class="device-header">
      <div class="device-icon"><?= $catEmojis[$obj['cat_nom'] ?? ''] ?? '🔌' ?></div>
      <span class="device-status <?= $obj['etat'] ?>"><?= ucfirst($obj['etat']) ?></span>
    </div>
    <div class="device-name"><?= sanitize($obj['nom']) ?></div>
    <div class="device-location">
      📍 <?= sanitize($obj['piece_nom'] ?? 'Non assigné') ?>
      <?php if ($obj['marque']): ?> · <span class="mono"><?= sanitize($obj['marque']) ?></span><?php endif; ?>
    </div>
    <div style="font-size:11px;color:var(--text-muted)">
      <?= sanitize($obj['cat_nom'] ?? '') ?> · <?= sanitize($obj['type_connectivite']) ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
