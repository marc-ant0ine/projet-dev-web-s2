<?php
require_once '../includes/config.php';
startSession();
requireLevel(['avancé','expert']);
$pageTitle  = 'Ajouter un objet';
$activePage = 'gestion';
require_once '../includes/header.php';

$pdo = getDB();
$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom        = sanitize($_POST['nom'] ?? '');
    $desc       = sanitize($_POST['description'] ?? '');
    $marque     = sanitize($_POST['marque'] ?? '');
    $modele     = sanitize($_POST['modele'] ?? '');
    $connectiv  = sanitize($_POST['type_connectivite'] ?? 'Wi-Fi');
    $force_signal     = sanitize($_POST['force_signal'] ?? 'fort');
    $etat       = sanitize($_POST['etat'] ?? 'actif');
    $cat_id     = (int)($_POST['categorie_id'] ?? 0);
    $piece_id   = (int)($_POST['piece_id'] ?? 0) ?: null;
    $batterie   = $_POST['batterie'] !== '' ? (int)$_POST['batterie'] : null;
    $firmware   = sanitize($_POST['firmware'] ?? '');
    $ip         = sanitize($_POST['ip_locale'] ?? '');
    $mac        = sanitize($_POST['mac_address'] ?? '');

    if (strlen($nom) < 2) $errors[] = 'Le nom est obligatoire.';
    if (!$cat_id)         $errors[] = 'Veuillez choisir une catégorie.';

    if (empty($errors)) {
        // Générer un ID unique
        $prefix   = strtoupper(preg_replace('/[^A-Z0-9]/i', '_', $nom));
        $idUnique = $prefix . '_' . strtoupper(bin2hex(random_bytes(3)));

        $pdo->prepare("INSERT INTO objets_connectes
            (id_unique,nom,description,marque,modele,type_connectivite,force_signal,etat,categorie_id,piece_id,batterie,firmware,ip_locale,mac_address,ajoute_par)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$idUnique,$nom,$desc,$marque,$modele,$connectiv,$force_signal,$etat,$cat_id,$piece_id,$batterie,$firmware,$ip,$mac,$user['id']]);

        $newId = $pdo->lastInsertId();

        // Ajouter attributs dynamiques saisis
        $attrCles   = $_POST['attr_cle']   ?? [];
        $attrValeurs= $_POST['attr_valeur']?? [];
        $attrUnites = $_POST['attr_unite'] ?? [];
        $attrTypes  = $_POST['attr_type']  ?? [];
        foreach ($attrCles as $i => $cle) {
            $cle = sanitize(trim($cle));
            if (!$cle) continue;
            $pdo->prepare("INSERT INTO attributs_objets (objet_id,cle,valeur,unite,type_attribut) VALUES (?,?,?,?,?)")
                ->execute([$newId, $cle, sanitize($attrValeurs[$i] ?? ''), sanitize($attrUnites[$i] ?? ''), sanitize($attrTypes[$i] ?? 'capteur')]);
        }

        addPoints($user['id'], POINTS_AJOUT, 'ajout_objet', $newId, 'Ajout objet : ' . $nom);
        setFlash('success', 'Objet "' . $nom . '" ajouté avec succès ! ID : ' . $idUnique);
        redirect(SITE_URL . '/objet-detail.php?id=' . $newId);
    }
}

$categories = $pdo->query("SELECT * FROM categories_objets ORDER BY nom")->fetchAll();
$pieces     = $pdo->query("SELECT * FROM pieces ORDER BY nom")->fetchAll();
?>

<a href="dashboard.php" class="btn btn-secondary btn-sm" style="margin-bottom:20px">← Retour gestion</a>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e): ?><div>• <?= sanitize($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="max-width:700px">
  <div class="card">
    <div class="card-title">➕ Nouvel objet connecté</div>

    <form method="POST">
      <!-- INFOS GÉNÉRALES -->
      <div style="font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:12px;margin-top:4px">Informations générales</div>
      <div class="form-group">
        <label class="form-label">Nom de l'objet *</label>
        <input type="text" name="nom" class="form-control" placeholder="ex: Thermostat Cuisine" value="<?= sanitize($_POST['nom'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3" placeholder="Décrivez l'objet..."><?= sanitize($_POST['description'] ?? '') ?></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Catégorie *</label>
          <select name="categorie_id" class="form-control" required>
            <option value="">-- Choisir --</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($_POST['categorie_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
              <?= sanitize($c['nom']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Pièce</label>
          <select name="piece_id" class="form-control">
            <option value="">Non assigné</option>
            <?php foreach ($pieces as $p): ?>
            <option value="<?= $p['id'] ?>" <?= ($_POST['piece_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
              <?= sanitize($p['nom']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Marque</label>
          <input type="text" name="marque" class="form-control" placeholder="ex: Philips" value="<?= sanitize($_POST['marque'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Modèle</label>
          <input type="text" name="modele" class="form-control" placeholder="ex: Hue A21" value="<?= sanitize($_POST['modele'] ?? '') ?>">
        </div>
      </div>

      <div class="divider"></div>

      <!-- CONNECTIVITÉ -->
      <div style="font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:12px">Connectivité</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Type de connexion</label>
          <select name="type_connectivite" class="form-control">
            <?php foreach (['Wi-Fi','Bluetooth','Zigbee','Z-Wave','Ethernet','NFC'] as $c): ?>
            <option value="<?= $c ?>" <?= ($_POST['type_connectivite'] ?? 'Wi-Fi') === $c ? 'selected' : '' ?>><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Force du signal</label>
          <select name="force_signal" class="form-control">
            <?php foreach (['fort','moyen','faible'] as $s): ?>
            <option value="<?= $s ?>" <?= ($_POST['force_signal'] ?? 'fort') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">IP locale</label>
          <input type="text" name="ip_locale" class="form-control" placeholder="192.168.1.x" value="<?= sanitize($_POST['ip_locale'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Adresse MAC</label>
          <input type="text" name="mac_address" class="form-control" placeholder="AA:BB:CC:DD:EE:FF" value="<?= sanitize($_POST['mac_address'] ?? '') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Firmware</label>
          <input type="text" name="firmware" class="form-control" placeholder="ex: v1.0.0" value="<?= sanitize($_POST['firmware'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Batterie (%)</label>
          <input type="number" name="batterie" class="form-control" min="0" max="100" placeholder="Laisser vide si branché" value="<?= sanitize($_POST['batterie'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">État initial</label>
        <select name="etat" class="form-control">
          <?php foreach (['actif','inactif','maintenance','erreur'] as $e): ?>
          <option value="<?= $e ?>" <?= ($_POST['etat'] ?? 'actif') === $e ? 'selected' : '' ?>><?= ucfirst($e) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="divider"></div>

      <!-- ATTRIBUTS DYNAMIQUES -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <div style="font-size:13px;font-weight:600;color:var(--text-secondary)">Attributs / Capteurs</div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="addAttrRow()">＋ Ajouter attribut</button>
      </div>
      <div id="attrs-container">
        <!-- Ligne attribut par défaut -->
        <div class="attr-row" style="display:grid;grid-template-columns:2fr 1.5fr 1fr 1.5fr 32px;gap:8px;margin-bottom:8px;align-items:center">
          <input type="text" name="attr_cle[]" class="form-control" placeholder="Clé (ex: temperature)" style="font-size:13px;padding:8px 10px">
          <input type="text" name="attr_valeur[]" class="form-control" placeholder="Valeur" style="font-size:13px;padding:8px 10px">
          <input type="text" name="attr_unite[]" class="form-control" placeholder="Unité" style="font-size:13px;padding:8px 10px">
          <select name="attr_type[]" class="form-control" style="font-size:13px;padding:8px 10px">
            <option value="capteur">Capteur</option>
            <option value="energie">Énergie</option>
            <option value="connectivite">Connectivité</option>
            <option value="usage">Usage</option>
            <option value="configuration">Config.</option>
          </select>
          <button type="button" onclick="this.closest('.attr-row').remove()" style="background:var(--danger-dim);border:none;color:var(--danger);border-radius:6px;cursor:pointer;height:34px;width:32px;font-size:16px">×</button>
        </div>
      </div>

      <div class="divider"></div>

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">💾 Enregistrer l'objet</button>
        <a href="dashboard.php" class="btn btn-secondary">Annuler</a>
      </div>
    </form>
  </div>
</div>

<script>
function addAttrRow() {
  const tpl = `<div class="attr-row" style="display:grid;grid-template-columns:2fr 1.5fr 1fr 1.5fr 32px;gap:8px;margin-bottom:8px;align-items:center">
    <input type="text" name="attr_cle[]" class="form-control" placeholder="Clé" style="font-size:13px;padding:8px 10px">
    <input type="text" name="attr_valeur[]" class="form-control" placeholder="Valeur" style="font-size:13px;padding:8px 10px">
    <input type="text" name="attr_unite[]" class="form-control" placeholder="Unité" style="font-size:13px;padding:8px 10px">
    <select name="attr_type[]" class="form-control" style="font-size:13px;padding:8px 10px">
      <option value="capteur">Capteur</option>
      <option value="energie">Énergie</option>
      <option value="connectivite">Connectivité</option>
      <option value="usage">Usage</option>
      <option value="configuration">Config.</option>
    </select>
    <button type="button" onclick="this.closest('.attr-row').remove()" style="background:var(--danger-dim);border:none;color:var(--danger);border-radius:6px;cursor:pointer;height:34px;width:32px;font-size:16px">×</button>
  </div>`;
  document.getElementById('attrs-container').insertAdjacentHTML('beforeend', tpl);
}
</script>

<?php require_once '../includes/footer.php'; ?>
