<?php
require_once '../includes/config.php';
startSession();
requireLevel(['avancé','expert']);
$pageTitle  = 'Gestion avancée';
$activePage = 'gestion';
require_once '../includes/header.php';

$pdo = getDB();

// Stats gestion
$nbTotal      = $pdo->query("SELECT COUNT(*) FROM objets_connectes")->fetchColumn();
$nbActifs     = $pdo->query("SELECT COUNT(*) FROM objets_connectes WHERE etat='actif'")->fetchColumn();
$nbMaintenance= $pdo->query("SELECT COUNT(*) FROM objets_connectes WHERE etat='maintenance'")->fetchColumn();
$nbErreur     = $pdo->query("SELECT COUNT(*) FROM objets_connectes WHERE etat='erreur'")->fetchColumn();

// Consommation totale
$consoTotal = $pdo->query("SELECT valeur FROM attributs_objets WHERE objet_id=(SELECT id FROM objets_connectes WHERE id_unique='COMPTEUR_01') AND cle='consommation_totale'")->fetchColumn();

// Objets inefficaces (batterie < 20% ou etat erreur)
$inefficaces = $pdo->query("
    SELECT o.*, c.nom AS categorie, p.nom AS piece_nom
    FROM objets_connectes o
    LEFT JOIN categories_objets c ON o.categorie_id = c.id
    LEFT JOIN pieces p ON o.piece_id = p.id
    WHERE o.etat = 'erreur' OR o.batterie < 20
    ORDER BY o.etat DESC, o.batterie ASC
    LIMIT 5
")->fetchAll();

// Objets par catégorie
$parCat = $pdo->query("
    SELECT c.nom, COUNT(o.id) as nb
    FROM objets_connectes o
    JOIN categories_objets c ON o.categorie_id = c.id
    GROUP BY c.id, c.nom ORDER BY nb DESC
")->fetchAll();

// Demandes suppression en attente
$demandesSupp = $pdo->query("
    SELECT d.*, o.nom AS objet_nom, u.login AS demandeur
    FROM demandes_suppression d
    JOIN objets_connectes o ON d.objet_id = o.id
    JOIN utilisateurs u ON d.demandeur_id = u.id
    WHERE d.statut = 'en_attente'
")->fetchAll();
?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon cyan">🔌</div>
    <div class="stat-info">
      <div class="stat-label">Total objets</div>
      <div class="stat-value"><?= $nbTotal ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">✅</div>
    <div class="stat-info">
      <div class="stat-label">Actifs</div>
      <div class="stat-value"><?= $nbActifs ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon amber">🔧</div>
    <div class="stat-info">
      <div class="stat-label">En maintenance</div>
      <div class="stat-value"><?= $nbMaintenance ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red">❌</div>
    <div class="stat-info">
      <div class="stat-label">Erreurs</div>
      <div class="stat-value"><?= $nbErreur ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon amber">⚡</div>
    <div class="stat-info">
      <div class="stat-label">Conso. totale</div>
      <div class="stat-value"><?= number_format((float)$consoTotal, 0) ?></div>
      <div class="stat-sub">kWh</div>
    </div>
  </div>
</div>

<div style="display:flex;gap:14px;margin-bottom:24px;flex-wrap:wrap">
  <a href="ajouter-objet.php" class="btn btn-primary">＋ Ajouter un objet</a>
  <a href="rapports.php" class="btn btn-secondary">📊 Générer un rapport</a>
  <a href="../objets.php" class="btn btn-secondary">🔌 Gérer les objets</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

  <!-- Objets par catégorie -->
  <div class="card">
    <div class="card-title">📊 Objets par catégorie</div>
    <canvas id="catChart" height="180"></canvas>
  </div>

  <!-- Objets nécessitant attention -->
  <div class="card">
    <div class="card-title">⚠️ Objets à surveiller</div>
    <?php if (empty($inefficaces)): ?>
      <p style="color:var(--text-muted);font-size:13px">Tous les objets sont opérationnels.</p>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:10px">
        <?php foreach ($inefficaces as $obj): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:10px;background:var(--bg-secondary);border-radius:var(--radius-md);border:1px solid var(--border)">
          <div style="flex:1">
            <div style="font-weight:600;font-size:13px"><?= sanitize($obj['nom']) ?></div>
            <div style="font-size:11px;color:var(--text-muted)"><?= sanitize($obj['piece_nom'] ?? 'N/A') ?></div>
          </div>
          <?php if ($obj['etat'] === 'erreur'): ?>
          <span class="device-status erreur">Erreur</span>
          <?php elseif ($obj['batterie'] < 20): ?>
          <span style="color:var(--danger);font-size:12px;font-weight:600">🔋 <?= $obj['batterie'] ?>%</span>
          <?php endif; ?>
          <a href="modifier-objet.php?id=<?= $obj['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Demandes de suppression -->
<?php if (!empty($demandesSupp)): ?>
<div class="card">
  <div class="card-title">🗑️ Demandes de suppression en attente</div>
  <div class="table-wrap">
    <table class="table table-striped table-hover">
      <thead><tr><th>Objet</th><th>Demandé par</th><th>Motif</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($demandesSupp as $d): ?>
        <tr>
          <td><?= sanitize($d['objet_nom']) ?></td>
          <td><?= sanitize($d['demandeur']) ?></td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= sanitize($d['motif'] ?? '—') ?></td>
          <td>
            <form method="POST" action="traiter-suppression.php" style="display:inline">
              <input type="hidden" name="id" value="<?= $d['id'] ?>">
              <input type="hidden" name="action" value="approuver">
              <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer définitivement ?')">✓ Approuver</button>
            </form>
            <form method="POST" action="traiter-suppression.php" style="display:inline;margin-left:6px">
              <input type="hidden" name="id" value="<?= $d['id'] ?>">
              <input type="hidden" name="action" value="refuser">
              <button type="submit" class="btn btn-secondary btn-sm">✕ Refuser</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const catLabels = <?= json_encode(array_column($parCat, 'nom')) ?>;
const catData   = <?= json_encode(array_column($parCat, 'nb')) ?>;
new Chart(document.getElementById('catChart'), {
  type: 'doughnut',
  data: {
    labels: catLabels,
    datasets: [{
      data: catData,
      backgroundColor: ['#00d4ff','#7c3aed','#00e676','#ffab00','#ff1744','#e91e63','#26c6da','#8bc34a'],
      borderWidth: 0,
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { labels: { color: '#8892a4', font: { size: 11 } }, position: 'right' }
    }
  }
});
</script>

<?php require_once '../includes/footer.php'; ?>
