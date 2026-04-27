<?php
// php/objets-resultats.php
// Endpoint AJAX : retourne uniquement le HTML des résultats d'objets
require_once '../includes/config.php';
startSession();
if (!isLoggedIn()) { http_response_code(403); exit; }

$user = getCurrentUser();
$pdo  = getDB();

$q          = sanitize($_GET['q'] ?? '');
$filterCat  = (int)($_GET['categorie'] ?? 0);
$filterPiece= (int)($_GET['piece'] ?? 0);
$filterEtat = sanitize($_GET['etat'] ?? '');
$filterMarque= sanitize($_GET['marque'] ?? '');

$sql = "SELECT o.*, c.nom AS categorie, p.nom AS piece_nom
        FROM objets_connectes o
        LEFT JOIN categories_objets c ON o.categorie_id = c.id
        LEFT JOIN pieces p ON o.piece_id = p.id
        WHERE 1=1";
$params = [];

if ($q) {
    $sql .= " AND (o.nom LIKE ? OR o.description LIKE ? OR o.marque LIKE ? OR o.modele LIKE ?)";
    $params = array_merge($params, ["%$q%", "%$q%", "%$q%", "%$q%"]);
}
if ($filterCat)    { $sql .= " AND o.categorie_id = ?"; $params[] = $filterCat; }
if ($filterPiece)  { $sql .= " AND o.piece_id = ?";     $params[] = $filterPiece; }
if ($filterEtat)   { $sql .= " AND o.etat = ?";         $params[] = $filterEtat; }
if ($filterMarque) { $sql .= " AND o.marque LIKE ?";     $params[] = "%$filterMarque%"; }
$sql .= " ORDER BY o.etat='actif' DESC, o.nom ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$objets = $stmt->fetchAll();

// Attributs principaux
function getAttrs(PDO $pdo, int $id): array {
    $s = $pdo->prepare("SELECT cle, valeur, unite FROM attributs_objets WHERE objet_id = ? LIMIT 3");
    $s->execute([$id]);
    return $s->fetchAll();
}

$catEmojis = ['Thermostat'=>'🌡️','Éclairage'=>'💡','Sécurité'=>'🔒','Électroménager'=>'🍽️','Capteurs'=>'📡','Multimédia'=>'📺','Énergie'=>'⚡','Confort'=>'🌿'];

// Enregistrer l'action si filtres actifs
if ($q || $filterCat || $filterPiece || $filterEtat) {
    addPoints($user['id'], POINTS_CONSULTATION, 'consultation_service', null, 'Recherche : ' . ($q ?: 'filtre'));
}

// Retourner le HTML
header('Content-Type: text/html; charset=utf-8');

if (empty($objets)): ?>
<div class="empty-state">
  <div class="icon">🔌</div>
  <h3>Aucun objet trouvé</h3>
  <p>Essayez avec d'autres critères de recherche</p>
</div>
<?php else: ?>
<p style="font-size:13px;color:var(--text-secondary);margin-bottom:14px">
  <?= count($objets) ?> objet<?= count($objets) > 1 ? 's' : '' ?> trouvé<?= count($objets) > 1 ? 's' : '' ?>
</p>
<div class="devices-grid">
  <?php foreach ($objets as $obj): ?>
  <a href="<?= SITE_URL ?>/objet-detail.php?id=<?= $obj['id'] ?>"
     class="device-card <?= $obj['etat'] === 'inactif' ? 'inactive' : '' ?>"
     style="text-decoration:none;color:inherit">
    <div class="device-header">
      <div class="device-icon"><?= $catEmojis[$obj['categorie'] ?? ''] ?? '🔌' ?></div>
      <span class="device-status <?= $obj['etat'] ?>"><?= ucfirst($obj['etat']) ?></span>
    </div>
    <div class="device-name"><?= sanitize($obj['nom']) ?></div>
    <div class="device-location">
      📍 <?= sanitize($obj['piece_nom'] ?? 'Non assigné') ?>
      <?php if ($obj['marque']): ?>
        · <span class="mono"><?= sanitize($obj['marque']) ?></span>
      <?php endif; ?>
    </div>
    <div style="font-size:11px;color:var(--text-muted);margin-bottom:10px">
      <?= sanitize($obj['categorie'] ?? '') ?> · 📶 <?= sanitize($obj['type_connectivite']) ?> (<?= sanitize($obj['force_signal']) ?>)
    </div>
    <?php $attrs = getAttrs($pdo, $obj['id']); if ($attrs): ?>
    <div class="device-attrs">
      <?php foreach ($attrs as $a): ?>
      <span class="attr-pill">
        <strong><?= sanitize(str_replace('_', ' ', $a['cle'])) ?></strong>
        : <?= sanitize($a['valeur']) ?><?= $a['unite'] ? ' ' . $a['unite'] : '' ?>
      </span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($obj['batterie'] !== null): ?>
    <div style="margin-top:10px">
      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);margin-bottom:4px">
        <span>🔋</span><span><?= $obj['batterie'] ?>%</span>
      </div>
      <div class="progress-bar">
        <div class="progress-fill" style="width:<?= $obj['batterie'] ?>%;background:<?= $obj['batterie'] < 20 ? 'var(--danger)' : ($obj['batterie'] < 50 ? 'var(--warning)' : 'var(--success)') ?>"></div>
      </div>
    </div>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
