<?php
require_once '../includes/config.php';
startSession();
requireLevel(['avancé','expert']);
$pageTitle  = 'Rapports';
$activePage = 'rapports';
require_once '../includes/header.php';

$pdo = getDB();

// Générer un rapport
$rapport = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type   = sanitize($_POST['type_rapport'] ?? 'global');
    $debut  = sanitize($_POST['periode_debut'] ?? date('Y-m-01'));
    $fin    = sanitize($_POST['periode_fin']   ?? date('Y-m-d'));
    $titre  = sanitize($_POST['titre'] ?? 'Rapport ' . date('d/m/Y'));

    $data = [];

    if ($type === 'energie' || $type === 'global') {
        // Historique énergie
        $stmt = $pdo->prepare("
            SELECT DATE(h.timestamp) as jour, h.valeur, o.nom as objet
            FROM historique_donnees h
            JOIN objets_connectes o ON h.objet_id = o.id
            WHERE h.cle='consommation_jour' AND DATE(h.timestamp) BETWEEN ? AND ?
            ORDER BY h.timestamp DESC
        ");
        $stmt->execute([$debut, $fin]);
        $data['energie'] = $stmt->fetchAll();

        // Consommation par objet
        $stmt2 = $pdo->prepare("
            SELECT o.nom, a.valeur, a.unite
            FROM attributs_objets a
            JOIN objets_connectes o ON a.objet_id = o.id
            WHERE a.cle IN ('consommation','consommation_jour','puissance_instant')
            AND o.etat='actif'
            ORDER BY CAST(a.valeur AS DECIMAL) DESC
            LIMIT 10
        ");
        $stmt2->execute();
        $data['conso_par_objet'] = $stmt2->fetchAll();
    }

    if ($type === 'usage' || $type === 'global') {
        // Actions utilisateurs
        $stmt3 = $pdo->prepare("
            SELECT u.login, COUNT(a.id) as nb_actions, SUM(a.points_gagnes) as pts
            FROM actions_utilisateurs a
            JOIN utilisateurs u ON a.utilisateur_id = u.id
            WHERE DATE(a.timestamp) BETWEEN ? AND ?
            GROUP BY u.id ORDER BY nb_actions DESC
        ");
        $stmt3->execute([$debut, $fin]);
        $data['activite_users'] = $stmt3->fetchAll();

        // Objets les plus consultés
        $stmt4 = $pdo->prepare("
            SELECT o.nom, COUNT(a.id) as nb
            FROM actions_utilisateurs a
            JOIN objets_connectes o ON a.objet_id = o.id
            WHERE a.type_action = 'consultation_objet'
            AND DATE(a.timestamp) BETWEEN ? AND ?
            GROUP BY o.id ORDER BY nb DESC LIMIT 10
        ");
        $stmt4->execute([$debut, $fin]);
        $data['objets_consultes'] = $stmt4->fetchAll();
    }

    if ($type === 'maintenance' || $type === 'global') {
        // Objets nécessitant maintenance
        $data['maintenance'] = $pdo->query("
            SELECT o.nom, o.etat, o.batterie, c.nom AS categorie, p.nom AS piece
            FROM objets_connectes o
            LEFT JOIN categories_objets c ON o.categorie_id=c.id
            LEFT JOIN pieces p ON o.piece_id=p.id
            WHERE o.etat IN ('maintenance','erreur') OR o.batterie < 20
            ORDER BY o.etat, o.batterie ASC
        ")->fetchAll();
    }

    $data['meta'] = [
        'type'   => $type,
        'debut'  => $debut,
        'fin'    => $fin,
        'genere' => date('d/m/Y H:i'),
        'par'    => $user['login'],
    ];

    // Sauvegarder le rapport
    $pdo->prepare("INSERT INTO rapports (titre,type_rapport,periode_debut,periode_fin,contenu_json,cree_par) VALUES (?,?,?,?,?,?)")
        ->execute([$titre, $type, $debut, $fin, json_encode($data), $user['id']]);

    $rapport = $data;
    $rapportTitre = $titre;
}

// Anciens rapports
$anciens = $pdo->prepare("SELECT r.*,u.login FROM rapports r LEFT JOIN utilisateurs u ON r.cree_par=u.id ORDER BY r.date_creation DESC LIMIT 10");
$anciens->execute(); $anciens = $anciens->fetchAll();
?>

<div style="display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start">

  <!-- Formulaire génération -->
  <div class="card">
    <div class="card-title">📊 Générer un rapport</div>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Titre</label>
        <input type="text" name="titre" class="form-control" value="Rapport <?= date('d/m/Y') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Type de rapport</label>
        <select name="type_rapport" class="form-control">
          <option value="global">Global (tout)</option>
          <option value="energie">Énergie</option>
          <option value="usage">Usage & activité</option>
          <option value="maintenance">Maintenance</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Du</label>
        <input type="date" name="periode_debut" class="form-control" value="<?= date('Y-m-01') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Au</label>
        <input type="date" name="periode_fin" class="form-control" value="<?= date('Y-m-d') ?>">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
        ⚙️ Générer
      </button>
    </form>

    <?php if (!empty($anciens)): ?>
    <div class="divider"></div>
    <div class="card-title">Rapports précédents</div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <?php foreach ($anciens as $r): ?>
      <a href="voir-rapport.php?id=<?= $r['id'] ?>" style="display:block;padding:10px;background:var(--bg-secondary);border:1px solid var(--border);border-radius:var(--radius-md);text-decoration:none;color:inherit;transition:var(--transition)" onmouseover="this.style.borderColor='var(--border-accent)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="font-size:13px;font-weight:600"><?= sanitize($r['titre']) ?></div>
        <div style="font-size:11px;color:var(--text-muted);margin-top:2px">
          <?= ucfirst($r['type_rapport']) ?> · <?= date('d/m/Y', strtotime($r['date_creation'])) ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Résultat rapport -->
  <div>
    <?php if ($rapport): ?>

    <div class="card" style="margin-bottom:16px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div>
          <h2 style="font-size:18px;font-weight:700"><?= sanitize($rapportTitre) ?></h2>
          <div style="font-size:12px;color:var(--text-muted)">
            Période : <?= $rapport['meta']['debut'] ?> → <?= $rapport['meta']['fin'] ?>
            · Généré le <?= $rapport['meta']['genere'] ?> par <?= sanitize($rapport['meta']['par']) ?>
          </div>
        </div>
        <a href="exporter-rapport.php?id=<?= $pdo->lastInsertId() ?: '' ?>" class="btn btn-secondary btn-sm">⬇️ CSV</a>
      </div>

      <?php if (isset($rapport['energie'])): ?>
      <div style="margin-bottom:20px">
        <div class="card-title">⚡ Consommation sur la période</div>
        <?php if (empty($rapport['energie'])): ?>
        <p style="color:var(--text-muted);font-size:13px">Aucune donnée sur cette période.</p>
        <?php else: ?>
        <div class="table-wrap">
          <table class="table table-striped table-hover">
            <thead><tr><th>Date</th><th>Objet</th><th>Conso. (kWh)</th></tr></thead>
            <tbody>
              <?php foreach ($rapport['energie'] as $row): ?>
              <tr>
                <td class="mono"><?= $row['jour'] ?></td>
                <td><?= sanitize($row['objet']) ?></td>
                <td><strong><?= sanitize($row['valeur']) ?></strong></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (isset($rapport['conso_par_objet']) && !empty($rapport['conso_par_objet'])): ?>
      <div style="margin-bottom:20px">
        <div class="card-title">🔋 Consommation par objet (actuel)</div>
        <canvas id="consoChart" height="120"></canvas>
      </div>
      <?php endif; ?>

      <?php if (isset($rapport['activite_users'])): ?>
      <div style="margin-bottom:20px">
        <div class="card-title">👥 Activité des membres</div>
        <?php if (empty($rapport['activite_users'])): ?>
        <p style="color:var(--text-muted);font-size:13px">Aucune activité sur cette période.</p>
        <?php else: ?>
        <div class="table-wrap">
          <table class="table table-striped table-hover">
            <thead><tr><th>Membre</th><th>Actions</th><th>Points gagnés</th></tr></thead>
            <tbody>
              <?php foreach ($rapport['activite_users'] as $row): ?>
              <tr>
                <td><?= sanitize($row['login']) ?></td>
                <td><?= $row['nb_actions'] ?></td>
                <td style="color:var(--success)">+<?= number_format((float)$row['pts'], 2) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (isset($rapport['objets_consultes']) && !empty($rapport['objets_consultes'])): ?>
      <div style="margin-bottom:20px">
        <div class="card-title">🔍 Objets les plus consultés</div>
        <div class="table-wrap">
          <table class="table table-striped table-hover">
            <thead><tr><th>Objet</th><th>Nb consultations</th></tr></thead>
            <tbody>
              <?php foreach ($rapport['objets_consultes'] as $row): ?>
              <tr><td><?= sanitize($row['nom']) ?></td><td><?= $row['nb'] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <?php if (isset($rapport['maintenance']) && !empty($rapport['maintenance'])): ?>
      <div>
        <div class="card-title">🔧 Objets nécessitant attention</div>
        <div class="table-wrap">
          <table class="table table-striped table-hover">
            <thead><tr><th>Objet</th><th>État</th><th>Batterie</th><th>Catégorie</th><th>Pièce</th></tr></thead>
            <tbody>
              <?php foreach ($rapport['maintenance'] as $row): ?>
              <tr>
                <td><?= sanitize($row['nom']) ?></td>
                <td><span class="device-status <?= $row['etat'] ?>"><?= ucfirst($row['etat']) ?></span></td>
                <td><?= $row['batterie'] ? $row['batterie'].'%' : '—' ?></td>
                <td><?= sanitize($row['categorie'] ?? '—') ?></td>
                <td><?= sanitize($row['piece'] ?? '—') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <?php if (isset($rapport['conso_par_objet']) && !empty($rapport['conso_par_objet'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    new Chart(document.getElementById('consoChart'), {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_map(fn($r) => mb_substr($r['nom'],0,20), $rapport['conso_par_objet'])) ?>,
        datasets: [{
          data: <?= json_encode(array_map(fn($r) => (float)$r['valeur'], $rapport['conso_par_objet'])) ?>,
          backgroundColor: 'rgba(0,212,255,0.25)',
          borderColor: '#00d4ff',
          borderWidth: 2,
          borderRadius: 6,
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#8892a4', font: { size: 11 } } },
          y: { grid: { display: false }, ticks: { color: '#8892a4', font: { size: 11 } } }
        }
      }
    });
    </script>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state">
      <div class="icon">📊</div>
      <h3>Aucun rapport généré</h3>
      <p>Configurez les paramètres à gauche et cliquez sur Générer</p>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once '../includes/footer.php'; ?>
