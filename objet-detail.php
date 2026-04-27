<?php
require_once 'includes/config.php';
startSession();

$id = (int)($_GET['id'] ?? 0);
$pdo = getDB();

$stmt = $pdo->prepare("
    SELECT o.*, c.nom AS categorie, p.nom AS piece_nom, u.login AS ajoute_par_login
    FROM objets_connectes o
    LEFT JOIN categories_objets c ON o.categorie_id = c.id
    LEFT JOIN pieces p ON o.piece_id = p.id
    LEFT JOIN utilisateurs u ON o.ajoute_par = u.id
    WHERE o.id = ?
");
$stmt->execute([$id]);
$obj = $stmt->fetch();

if (!$obj) redirect(SITE_URL . '/objets.php');

$pageTitle  = sanitize($obj['nom']);
$activePage = 'objets';
require_once 'includes/header.php';

// Attributs complets
$attrs = $pdo->prepare("SELECT * FROM attributs_objets WHERE objet_id = ? ORDER BY type_attribut, cle");
$attrs->execute([$id]);
$attrs = $attrs->fetchAll();

// Grouper par type
$attrsByType = [];
foreach ($attrs as $a) {
    $attrsByType[$a['type_attribut']][] = $a;
}

// Historique des données
$historique = $pdo->prepare("
    SELECT * FROM historique_donnees
    WHERE objet_id = ?
    ORDER BY timestamp DESC
    LIMIT 20
");
$historique->execute([$id]);
$historique = $historique->fetchAll();

// Enregistrer la consultation
addPoints($user['id'], POINTS_CONSULTATION, 'consultation_objet', $id, 'Consultation : ' . $obj['nom']);

$catEmojis = ['Thermostat'=>'🌡️','Éclairage'=>'💡','Sécurité'=>'🔒','Électroménager'=>'🍽️','Capteurs'=>'📡','Multimédia'=>'📺','Énergie'=>'⚡','Confort'=>'🌿'];
$emoji = $catEmojis[$obj['categorie'] ?? ''] ?? '🔌';

$typeLabels = ['capteur'=>'Capteurs','energie'=>'Énergie','connectivite'=>'Connectivité','usage'=>'Usage','configuration'=>'Configuration'];
?>

<div style="display:flex;align-items:center;gap:16px;margin-bottom:24px">
  <a href="objets.php" class="btn btn-secondary btn-sm">← Retour</a>
  <div style="font-size:32px"><?= $emoji ?></div>
  <div>
    <h1 style="font-size:24px;font-weight:700"><?= sanitize($obj['nom']) ?></h1>
    <div style="font-size:13px;color:var(--text-secondary)">
      ID : <span class="mono"><?= sanitize($obj['id_unique']) ?></span>
      · <?= sanitize($obj['categorie'] ?? 'Non catégorisé') ?>
      · <?= sanitize($obj['piece_nom'] ?? 'Non assigné') ?>
    </div>
  </div>
  <span class="device-status <?= $obj['etat'] ?>" style="margin-left:auto"><?= ucfirst($obj['etat']) ?></span>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">

  <!-- INFOS PRINCIPALES -->
  <div>

    <!-- Description -->
    <?php if ($obj['description']): ?>
    <div class="card" style="margin-bottom:16px">
      <p style="color:var(--text-secondary)"><?= sanitize($obj['description']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Attributs par type -->
    <?php foreach ($attrsByType as $type => $typeAttrs): ?>
    <div class="card" style="margin-bottom:16px">
      <div class="card-title"><?= $typeLabels[$type] ?? ucfirst($type) ?></div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px">
        <?php foreach ($typeAttrs as $a): ?>
        <div style="background:var(--bg-secondary);border:1px solid var(--border);border-radius:var(--radius-md);padding:12px">
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px"><?= sanitize(str_replace('_', ' ', $a['cle'])) ?></div>
          <div style="font-size:18px;font-weight:700;color:var(--text-primary)">
            <?= sanitize($a['valeur']) ?>
            <?php if ($a['unite']): ?><span style="font-size:12px;font-weight:400;color:var(--text-secondary)"><?= sanitize($a['unite']) ?></span><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Historique -->
    <?php if (!empty($historique)): ?>
    <div class="card">
      <div class="card-title">📈 Historique récent</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Mesure</th><th>Valeur</th></tr></thead>
          <tbody>
            <?php foreach ($historique as $h): ?>
            <tr>
              <td class="mono"><?= date('d/m/Y H:i', strtotime($h['timestamp'])) ?></td>
              <td><?= sanitize(str_replace('_', ' ', $h['cle'])) ?></td>
              <td><strong><?= sanitize($h['valeur']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- INFOS TECHNIQUES -->
  <div>
    <div class="card" style="margin-bottom:16px">
      <div class="card-title">ℹ️ Informations</div>
      <div style="display:flex;flex-direction:column;gap:10px;font-size:13px">
        <?php $infos = [
          'Marque'         => $obj['marque'],
          'Modèle'         => $obj['modele'],
          'Connectivité'   => $obj['type_connectivite'],
          'Signal'         => $obj['force_signal'],
          'Firmware'       => $obj['firmware'],
          'IP locale'      => $obj['ip_locale'],
          'MAC'            => $obj['mac_address'],
          'Installation'   => $obj['date_installation'] ? date('d/m/Y', strtotime($obj['date_installation'])) : null,
          'Dernière inter.' => $obj['derniere_interaction'] ? date('d/m/Y H:i', strtotime($obj['derniere_interaction'])) : null,
          'Ajouté par'     => $obj['ajoute_par_login'],
        ];
        foreach ($infos as $label => $val): if ($val): ?>
        <div style="display:flex;justify-content:space-between;gap:10px;border-bottom:1px solid var(--border);padding-bottom:8px">
          <span style="color:var(--text-muted)"><?= $label ?></span>
          <span class="mono"><?= sanitize($val) ?></span>
        </div>
        <?php endif; endforeach; ?>
      </div>
    </div>

    <?php if ($obj['batterie']): ?>
    <div class="card" style="margin-bottom:16px">
      <div class="card-title">🔋 Batterie</div>
      <div style="font-size:28px;font-weight:700;margin-bottom:8px;color:<?= $obj['batterie'] < 20 ? 'var(--danger)' : ($obj['batterie'] < 50 ? 'var(--warning)' : 'var(--success)') ?>"><?= $obj['batterie'] ?>%</div>
      <div class="progress-bar">
        <div class="progress-fill" style="width:<?= $obj['batterie'] ?>%;background:<?= $obj['batterie'] < 20 ? 'var(--danger)' : ($obj['batterie'] < 50 ? 'var(--warning)' : 'var(--success)') ?>"></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Actions (module Gestion) -->
    <?php if (in_array($user['niveau'], ['avancé', 'expert'])): ?>
    <div class="card">
      <div class="card-title">⚙️ Actions</div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <a href="gestion/modifier-objet.php?id=<?= $obj['id'] ?>" class="btn btn-secondary btn-sm">✏️ Modifier</a>
        <?php if ($obj['etat'] === 'actif'): ?>
        <form method="POST" action="gestion/toggle-objet.php">
          <input type="hidden" name="id" value="<?= $obj['id'] ?>">
          <input type="hidden" name="etat" value="inactif">
          <button type="submit" class="btn btn-secondary btn-sm" style="width:100%">⭕ Désactiver</button>
        </form>
        <?php else: ?>
        <form method="POST" action="gestion/toggle-objet.php">
          <input type="hidden" name="id" value="<?= $obj['id'] ?>">
          <input type="hidden" name="etat" value="actif">
          <button type="submit" class="btn btn-success btn-sm" style="width:100%">Activer</button>
        </form>
        <?php endif; ?>
        <form method="POST" action="gestion/demande-suppression.php" onsubmit="return confirm('Demander la suppression de cet objet ?')">
          <input type="hidden" name="objet_id" value="<?= $obj['id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm" style="width:100%">🗑️ Demander suppression</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
