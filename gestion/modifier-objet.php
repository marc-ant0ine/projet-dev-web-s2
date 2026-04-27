<?php
require_once '../includes/config.php';
startSession();
requireLevel(['avancé','expert']);

$id  = (int)($_GET['id'] ?? 0);
$pdo = getDB();

$stmt = $pdo->prepare("SELECT o.*, c.nom AS categorie FROM objets_connectes o LEFT JOIN categories_objets c ON o.categorie_id=c.id WHERE o.id=?");
$stmt->execute([$id]);
$obj = $stmt->fetch();
if (!$obj) { setFlash('error','Objet introuvable.'); redirect(SITE_URL.'/objets.php'); }

$pageTitle  = 'Modifier : ' . sanitize($obj['nom']);
$activePage = 'gestion';
require_once '../includes/header.php';

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom      = sanitize($_POST['nom'] ?? '');
    $desc     = sanitize($_POST['description'] ?? '');
    $marque   = sanitize($_POST['marque'] ?? '');
    $modele   = sanitize($_POST['modele'] ?? '');
    $connectiv= sanitize($_POST['type_connectivite'] ?? 'Wi-Fi');
    $force_signal   = sanitize($_POST['force_signal'] ?? 'fort');
    $etat     = sanitize($_POST['etat'] ?? 'actif');
    $cat_id   = (int)($_POST['categorie_id'] ?? 0);
    $piece_id = (int)($_POST['piece_id'] ?? 0) ?: null;
    $batterie = $_POST['batterie'] !== '' ? (int)$_POST['batterie'] : null;
    $firmware = sanitize($_POST['firmware'] ?? '');
    $ip       = sanitize($_POST['ip_locale'] ?? '');
    $mac      = sanitize($_POST['mac_address'] ?? '');

    if (strlen($nom) < 2) $errors[] = 'Nom obligatoire.';

    if (empty($errors)) {
        $pdo->prepare("UPDATE objets_connectes SET nom=?,description=?,marque=?,modele=?,type_connectivite=?,force_signal=?,etat=?,categorie_id=?,piece_id=?,batterie=?,firmware=?,ip_locale=?,mac_address=?,derniere_interaction=NOW() WHERE id=?")
            ->execute([$nom,$desc,$marque,$modele,$connectiv,$force_signal,$etat,$cat_id,$piece_id,$batterie,$firmware,$ip,$mac,$id]);

        // Mettre à jour les attributs existants
        $attrIds    = $_POST['attr_id'] ?? [];
        $attrVals   = $_POST['attr_valeur_edit'] ?? [];
        foreach ($attrIds as $i => $attrId) {
            $pdo->prepare("UPDATE attributs_objets SET valeur=?,mise_a_jour=NOW() WHERE id=? AND objet_id=?")
                ->execute([sanitize($attrVals[$i] ?? ''), (int)$attrId, $id]);
            // Historiser
            $pdo->prepare("INSERT INTO historique_donnees (objet_id,cle,valeur) SELECT objet_id,cle,valeur FROM attributs_objets WHERE id=?")
                ->execute([(int)$attrId]);
        }

        // Nouveaux attributs
        $newCles   = $_POST['new_attr_cle'] ?? [];
        $newVals   = $_POST['new_attr_valeur'] ?? [];
        $newUnites = $_POST['new_attr_unite'] ?? [];
        $newTypes  = $_POST['new_attr_type'] ?? [];
        foreach ($newCles as $i => $cle) {
            $cle = sanitize(trim($cle));
            if (!$cle) continue;
            $pdo->prepare("INSERT INTO attributs_objets (objet_id,cle,valeur,unite,type_attribut) VALUES (?,?,?,?,?)")
                ->execute([$id,$cle,sanitize($newVals[$i]??''),sanitize($newUnites[$i]??''),sanitize($newTypes[$i]??'capteur')]);
        }

        addPoints($user['id'], POINTS_MODIFICATION, 'modification_objet', $id, 'Modification : ' . $nom);
        $success = 'Objet mis à jour avec succès !';
        $obj = $pdo->prepare("SELECT o.*,c.nom AS categorie FROM objets_connectes o LEFT JOIN categories_objets c ON o.categorie_id=c.id WHERE o.id=?");
        $obj->execute([$id]); $obj = $obj->fetch();
    }
}

$categories = $pdo->query("SELECT * FROM categories_objets ORDER BY nom")->fetchAll();
$pieces     = $pdo->query("SELECT * FROM pieces ORDER BY nom")->fetchAll();
$attrs      = $pdo->prepare("SELECT * FROM attributs_objets WHERE objet_id=? ORDER BY type_attribut,cle");
$attrs->execute([$id]); $attrs = $attrs->fetchAll();

$typeLabels = ['capteur'=>'Capteur','energie'=>'Énergie','connectivite'=>'Connectivité','usage'=>'Usage','configuration'=>'Config.'];
?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
  <a href="<?= SITE_URL ?>/objet-detail.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">← Retour</a>
  <h1 style="font-size:20px;font-weight:700">✏️ <?= sanitize($obj['nom']) ?></h1>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-error"><?php foreach ($errors as $e): ?><div>• <?= sanitize($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><?= sanitize($success) ?></div>
<?php endif; ?>

<div style="max-width:700px">
  <form method="POST">
    <div class="card" style="margin-bottom:16px">
      <div class="card-title">Informations générales</div>
      <div class="form-group">
        <label class="form-label">Nom *</label>
        <input type="text" name="nom" class="form-control" value="<?= sanitize($obj['nom']) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"><?= sanitize($obj['description'] ?? '') ?></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Catégorie</label>
          <select name="categorie_id" class="form-control">
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $obj['categorie_id'] == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Pièce</label>
          <select name="piece_id" class="form-control">
            <option value="">Non assigné</option>
            <?php foreach ($pieces as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $obj['piece_id'] == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Marque</label>
          <input type="text" name="marque" class="form-control" value="<?= sanitize($obj['marque'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Modèle</label>
          <input type="text" name="modele" class="form-control" value="<?= sanitize($obj['modele'] ?? '') ?>">
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:16px">
      <div class="card-title">Connectivité & État</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Type connexion</label>
          <select name="type_connectivite" class="form-control">
            <?php foreach (['Wi-Fi','Bluetooth','Zigbee','Z-Wave','Ethernet','NFC'] as $c): ?>
            <option value="<?= $c ?>" <?= $obj['type_connectivite'] === $c ? 'selected' : '' ?>><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Signal</label>
          <select name="force_signal" class="form-control">
            <?php foreach (['fort','moyen','faible'] as $s): ?>
            <option value="<?= $s ?>" <?= $obj['force_signal'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">État</label>
          <select name="etat" class="form-control">
            <?php foreach (['actif','inactif','maintenance','erreur'] as $e): ?>
            <option value="<?= $e ?>" <?= $obj['etat'] === $e ? 'selected' : '' ?>><?= ucfirst($e) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Batterie (%)</label>
          <input type="number" name="batterie" class="form-control" min="0" max="100" value="<?= $obj['batterie'] ?? '' ?>" placeholder="Vide = branché">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">IP locale</label>
          <input type="text" name="ip_locale" class="form-control" value="<?= sanitize($obj['ip_locale'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">MAC</label>
          <input type="text" name="mac_address" class="form-control" value="<?= sanitize($obj['mac_address'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Firmware</label>
        <input type="text" name="firmware" class="form-control" value="<?= sanitize($obj['firmware'] ?? '') ?>">
      </div>
    </div>

    <!-- Attributs existants -->
    <?php if (!empty($attrs)): ?>
    <div class="card" style="margin-bottom:16px">
      <div class="card-title">Attributs actuels</div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($attrs as $a): ?>
        <div style="display:grid;grid-template-columns:2fr 1.5fr 1fr;gap:8px;align-items:center">
          <input type="hidden" name="attr_id[]" value="<?= $a['id'] ?>">
          <label style="font-size:13px;color:var(--text-secondary)">
            <?= sanitize(str_replace('_',' ',$a['cle'])) ?>
            <span style="font-size:10px;color:var(--text-muted)">(<?= $typeLabels[$a['type_attribut']] ?? $a['type_attribut'] ?>)</span>
          </label>
          <input type="text" name="attr_valeur_edit[]" class="form-control" value="<?= sanitize($a['valeur'] ?? '') ?>" style="font-size:13px;padding:8px 10px">
          <span style="font-size:12px;color:var(--text-muted)"><?= sanitize($a['unite'] ?? '') ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Nouveaux attributs -->
    <div class="card" style="margin-bottom:16px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <div class="card-title" style="margin:0">Ajouter des attributs</div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="addRow()">＋</button>
      </div>
      <div id="new-attrs"></div>
    </div>

    <div style="display:flex;gap:10px">
      <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
      <a href="<?= SITE_URL ?>/objet-detail.php?id=<?= $id ?>" class="btn btn-secondary">Annuler</a>
    </div>
  </form>
</div>

<script>
function addRow() {
  const tpl = `<div style="display:grid;grid-template-columns:2fr 1.5fr 1fr 1.5fr 32px;gap:8px;margin-bottom:8px;align-items:center">
    <input type="text" name="new_attr_cle[]" class="form-control" placeholder="Clé" style="font-size:13px;padding:8px 10px">
    <input type="text" name="new_attr_valeur[]" class="form-control" placeholder="Valeur" style="font-size:13px;padding:8px 10px">
    <input type="text" name="new_attr_unite[]" class="form-control" placeholder="Unité" style="font-size:13px;padding:8px 10px">
    <select name="new_attr_type[]" class="form-control" style="font-size:13px;padding:8px 10px">
      <option value="capteur">Capteur</option>
      <option value="energie">Énergie</option>
      <option value="connectivite">Connectivité</option>
      <option value="usage">Usage</option>
      <option value="configuration">Config.</option>
    </select>
    <button type="button" onclick="this.closest('div').remove()" style="background:var(--danger-dim);border:none;color:var(--danger);border-radius:6px;cursor:pointer;height:34px;width:32px;font-size:16px">×</button>
  </div>`;
  document.getElementById('new-attrs').insertAdjacentHTML('beforeend', tpl);
}
</script>

<?php require_once '../includes/footer.php'; ?>
