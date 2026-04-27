<?php
require_once 'includes/config.php';
startSession();
$pageTitle  = 'Tableau de bord';
$activePage = 'dashboard';
require_once 'includes/header.php';

$pdo = getDB();

// Stats
$nbObjets    = $pdo->query("SELECT COUNT(*) FROM objets_connectes WHERE etat='actif'")->fetchColumn();
$nbInactifs  = $pdo->query("SELECT COUNT(*) FROM objets_connectes WHERE etat='inactif'")->fetchColumn();
$nbPieces    = $pdo->query("SELECT COUNT(*) FROM pieces")->fetchColumn();
$nbMembres   = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE statut='actif'")->fetchColumn();

// Consommation du jour
$stmt = $pdo->prepare("SELECT valeur FROM attributs_objets WHERE objet_id = (SELECT id FROM objets_connectes WHERE id_unique='COMPTEUR_01') AND cle='consommation_jour'");
$stmt->execute();
$consoJour = $stmt->fetchColumn() ?: '—';

// Historique énergie (7 jours)
$historiqueEnergie = $pdo->query("
    SELECT DATE(timestamp) as jour, valeur
    FROM historique_donnees
    WHERE objet_id = (SELECT id FROM objets_connectes WHERE id_unique='COMPTEUR_01')
    AND cle = 'consommation_jour'
    ORDER BY timestamp DESC LIMIT 7
")->fetchAll();

// Derniers objets consultés / actifs
$derniersObjets = $pdo->query("
    SELECT o.*, c.nom AS categorie, p.nom AS piece_nom
    FROM objets_connectes o
    LEFT JOIN categories_objets c ON o.categorie_id = c.id
    LEFT JOIN pieces p ON o.piece_id = p.id
    ORDER BY o.derniere_interaction DESC
    LIMIT 6
")->fetchAll();

// Attributs principaux par objet
function getMainAttrs(PDO $pdo, int $objetId): array {
    $stmt = $pdo->prepare("SELECT cle, valeur, unite FROM attributs_objets WHERE objet_id = ? LIMIT 3");
    $stmt->execute([$objetId]);
    return $stmt->fetchAll();
}

// Mes dernières actions
$mesActions = $pdo->prepare("
    SELECT a.*, o.nom AS objet_nom
    FROM actions_utilisateurs a
    LEFT JOIN objets_connectes o ON a.objet_id = o.id
    WHERE a.utilisateur_id = ?
    ORDER BY a.timestamp DESC
    LIMIT 5
");
$mesActions->execute([$user['id']]);
$mesActions = $mesActions->fetchAll();

// Actualités récentes
$actualites = $pdo->query("SELECT * FROM actualites WHERE visible=1 ORDER BY date_publication DESC LIMIT 3")->fetchAll();

$catEmojis = ['Thermostat'=>'🌡️','Éclairage'=>'💡','Sécurité'=>'🔒','Électroménager'=>'🍽️','Capteurs'=>'📡','Multimédia'=>'📺','Énergie'=>'⚡','Confort'=>'🌿'];
function catEmoji(string $cat, array $map): string { return $map[$cat] ?? '🔌'; }
?>

<!-- STATS RAPIDES -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon cyan">⚡</div>
    <div class="stat-info">
      <div class="stat-label">Objets actifs</div>
      <div class="stat-value"><?= $nbObjets ?></div>
      <div class="stat-sub"><?= $nbInactifs ?> inactif(s)</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">🏠</div>
    <div class="stat-info">
      <div class="stat-label">Pièces</div>
      <div class="stat-value"><?= $nbPieces ?></div>
      <div class="stat-sub">connectées</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">👥</div>
    <div class="stat-info">
      <div class="stat-label">Membres</div>
      <div class="stat-value"><?= $nbMembres ?></div>
      <div class="stat-sub">actifs</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon amber">🔋</div>
    <div class="stat-info">
      <div class="stat-label">Conso. aujourd'hui</div>
      <div class="stat-value"><?= $consoJour ?></div>
      <div class="stat-sub">kWh</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon cyan">🏆</div>
    <div class="stat-info">
      <div class="stat-label">Mes points</div>
      <div class="stat-value"><?= number_format($user['points'], 1) ?></div>
      <div class="stat-sub"><?= $user['nb_connexions'] ?> connexion(s)</div>
    </div>
  </div>
</div>

<!-- PROGRESSION NIVEAU -->
<?php if (canUpgradeNiveau($user)): ?>
<div class="alert alert-success" style="margin-bottom:20px">
  🎉 Vous avez suffisamment de points pour passer au niveau <strong><?= getNextNiveau($user['niveau']) ?></strong> !
  <a href="profil.php#niveau" class="btn btn-success btn-sm" style="margin-left:10px">Changer de niveau</a>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px">

  <!-- Historique énergie -->
  <div class="card">
    <div class="card-title">⚡ Consommation 7 jours (kWh)</div>
    <canvas id="energyChart" height="120"></canvas>
  </div>

  <!-- Mes actions récentes -->
  <div class="card">
    <div class="card-title">🕐 Mes actions récentes</div>
    <?php if (empty($mesActions)): ?>
      <p style="color:var(--text-muted);font-size:13px">Aucune action pour l'instant.</p>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($mesActions as $act): ?>
        <div style="display:flex;align-items:center;gap:10px;font-size:13px">
          <span style="color:var(--text-muted);font-family:var(--font-mono);min-width:80px"><?= date('d/m H:i', strtotime($act['timestamp'])) ?></span>
          <span style="flex:1;color:var(--text-secondary)"><?= sanitize($act['objet_nom'] ?? $act['description'] ?? $act['type_action']) ?></span>
          <span style="color:var(--success);font-weight:600">+<?= $act['points_gagnes'] ?>pt</span>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- OBJETS RÉCENTS -->
<div class="section-header">
  <div>
    <h2 class="section-title">Objets connectés récents</h2>
    <p class="section-sub">Dernières interactions</p>
  </div>
  <a href="objets.php" class="btn btn-secondary btn-sm">Voir tout →</a>
</div>

<div class="devices-grid">
  <?php foreach ($derniersObjets as $obj): ?>
  <a href="objet-detail.php?id=<?= $obj['id'] ?>" class="device-card <?= $obj['etat'] === 'inactif' ? 'inactive' : '' ?>" style="text-decoration:none;color:inherit">
    <div class="device-header">
      <div class="device-icon"><?= catEmoji($obj['categorie'] ?? '', $catEmojis) ?></div>
      <span class="device-status <?= $obj['etat'] ?>"><?= ucfirst($obj['etat']) ?></span>
    </div>
    <div class="device-name"><?= sanitize($obj['nom']) ?></div>
    <div class="device-location">
      <span style="font-size:13px">📍</span>
      <?= sanitize($obj['piece_nom'] ?? 'Non assigné') ?>
      <?php if ($obj['marque']): ?> · <span class="mono"><?= sanitize($obj['marque']) ?></span><?php endif; ?>
    </div>
    <?php $attrs = getMainAttrs($pdo, $obj['id']); ?>
    <?php if ($attrs): ?>
    <div class="device-attrs">
      <?php foreach ($attrs as $a): ?>
      <span class="attr-pill"><strong><?= ucfirst(str_replace('_', ' ', $a['cle'])) ?></strong> : <?= sanitize($a['valeur']) ?><?= $a['unite'] ? ' '.$a['unite'] : '' ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- ACTUALITÉS -->
<?php if (!empty($actualites)): ?>
<div style="margin-top:28px">
  <div class="section-header">
    <div><h2 class="section-title">Actualités</h2></div>
    <a href="actualites.php" class="btn btn-secondary btn-sm">Toutes →</a>
  </div>
  <div class="news-grid">
    <?php foreach ($actualites as $news): ?>
    <div class="news-card">
      <div class="news-date"><?= date('d/m/Y', strtotime($news['date_publication'])) ?></div>
      <div class="news-title"><?= sanitize($news['titre']) ?></div>
      <div class="news-excerpt"><?= sanitize(mb_substr($news['contenu'], 0, 130)) ?>…</div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('energyChart');
const labels = <?= json_encode(array_reverse(array_column($historiqueEnergie, 'jour'))) ?>;
const data   = <?= json_encode(array_reverse(array_map(fn($r) => (float)$r['valeur'], $historiqueEnergie))) ?>;

new Chart(ctx, {
  type: 'bar',
  data: {
    labels: labels.map(l => l.substring(5)),
    datasets: [{
      data: data,
      backgroundColor: 'rgba(0,212,255,0.25)',
      borderColor: '#00d4ff',
      borderWidth: 2,
      borderRadius: 6,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#8892a4', font: { size: 11 } } },
      y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#8892a4', font: { size: 11 } } }
    }
  }
});
</script>

<?php require_once 'includes/footer.php'; ?>
