<?php
require_once 'includes/config.php';
startSession();
$pageTitle  = 'Objets connectés';
$activePage = 'objets';
require_once 'includes/header.php';

$pdo = getDB();

// Valeurs initiales des filtres (pour pré-remplir les selects)
$q            = sanitize($_GET['q'] ?? '');
$filterCat    = (int)($_GET['categorie'] ?? 0);
$filterPiece  = (int)($_GET['piece'] ?? 0);
$filterEtat   = sanitize($_GET['etat'] ?? '');
$filterMarque = sanitize($_GET['marque'] ?? '');

$categories = $pdo->query("SELECT * FROM categories_objets ORDER BY nom")->fetchAll();
$pieces     = $pdo->query("SELECT * FROM pieces ORDER BY nom")->fetchAll();
$marques    = $pdo->query("SELECT DISTINCT marque FROM objets_connectes WHERE marque IS NOT NULL ORDER BY marque")->fetchAll(PDO::FETCH_COLUMN);
?>

<!-- BARRE DE RECHERCHE ET FILTRES -->
<div class="card" style="margin-bottom:20px">
  <div class="search-bar" style="margin-bottom:12px">
    <span style="font-size:20px;color:var(--text-muted)">🔍</span>
    <input type="text" id="filtre-q" placeholder="Nom, description, marque, modèle..."
           value="<?= sanitize($q) ?>">
    <button class="btn btn-primary btn-sm" onclick="appliquerFiltres()">Rechercher</button>
  </div>
  <div class="filters-row">
    <select id="filtre-categorie" class="filter-select" onchange="appliquerFiltres()">
      <option value="">Toutes catégories</option>
      <?php foreach ($categories as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $filterCat == $c['id'] ? 'selected' : '' ?>>
        <?= sanitize($c['nom']) ?>
      </option>
      <?php endforeach; ?>
    </select>

    <select id="filtre-piece" class="filter-select" onchange="appliquerFiltres()">
      <option value="">Toutes pièces</option>
      <?php foreach ($pieces as $p): ?>
      <option value="<?= $p['id'] ?>" <?= $filterPiece == $p['id'] ? 'selected' : '' ?>>
        <?= sanitize($p['nom']) ?>
      </option>
      <?php endforeach; ?>
    </select>

    <select id="filtre-etat" class="filter-select" onchange="appliquerFiltres()">
      <option value="">Tous états</option>
      <option value="actif"       <?= $filterEtat === 'actif'       ? 'selected' : '' ?>>Actif</option>
      <option value="inactif"     <?= $filterEtat === 'inactif'     ? 'selected' : '' ?>>⭕ Inactif</option>
      <option value="maintenance" <?= $filterEtat === 'maintenance' ? 'selected' : '' ?>>🔧 Maintenance</option>
      <option value="erreur"      <?= $filterEtat === 'erreur'      ? 'selected' : '' ?>>Erreur</option>
    </select>

    <select id="filtre-marque" class="filter-select" onchange="appliquerFiltres()">
      <option value="">Toutes marques</option>
      <?php foreach ($marques as $m): ?>
      <option value="<?= sanitize($m) ?>" <?= $filterMarque === $m ? 'selected' : '' ?>>
        <?= sanitize($m) ?>
      </option>
      <?php endforeach; ?>
    </select>

    <button id="btn-effacer" class="btn btn-secondary btn-sm" onclick="effacerFiltres()" style="display:none">
      ✕ Effacer
    </button>
  </div>
</div>

<!-- HEADER RÉSULTATS -->
<div class="section-header" style="margin-bottom:14px">
  <div>
    <h2 class="section-title" id="resultat-titre">Tous les objets connectés</h2>
    <p class="section-sub">Équipements de la maison</p>
  </div>
  <?php if (in_array($user['niveau'], ['avancé', 'expert'])): ?>
  <a href="gestion/ajouter-objet.php" class="btn btn-primary btn-sm">＋ Ajouter</a>
  <?php endif; ?>
</div>

<!-- ZONE RÉSULTATS (mise à jour par AJAX) -->
<div id="resultats">
  <div style="text-align:center;padding:40px;color:var(--text-secondary)">
    <div class="loading"><span></span><span></span><span></span></div>
  </div>
</div>

<script>
const BASE_URL = '<?= SITE_URL ?>';

// Charger les résultats via AJAX sans recharger la page
function appliquerFiltres() {
  const q        = document.getElementById('filtre-q').value.trim();
  const categorie= document.getElementById('filtre-categorie').value;
  const piece    = document.getElementById('filtre-piece').value;
  const etat     = document.getElementById('filtre-etat').value;
  const marque   = document.getElementById('filtre-marque').value;

  // Afficher/masquer le bouton Effacer
  const filtresActifs = q || categorie || piece || etat || marque;
  document.getElementById('btn-effacer').style.display = filtresActifs ? 'inline-flex' : 'none';

  // Construire les paramètres
  const params = new URLSearchParams();
  if (q)        params.set('q', q);
  if (categorie)params.set('categorie', categorie);
  if (piece)    params.set('piece', piece);
  if (etat)     params.set('etat', etat);
  if (marque)   params.set('marque', marque);

  // Mettre à jour l'URL sans recharger la page
  const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
  history.replaceState(null, '', newUrl);

  // Loader
  document.getElementById('resultats').innerHTML =
    '<div style="text-align:center;padding:40px;color:var(--text-secondary)"><div class="loading"><span></span><span></span><span></span></div></div>';

  // Requête AJAX
  fetch(BASE_URL + '/php/objets-resultats.php?' + params.toString())
    .then(r => r.text())
    .then(html => {
      document.getElementById('resultats').innerHTML = html;
    })
    .catch(() => {
      document.getElementById('resultats').innerHTML =
        '<div class="alert alert-error">Erreur lors du chargement des résultats.</div>';
    });
}

function effacerFiltres() {
  document.getElementById('filtre-q').value        = '';
  document.getElementById('filtre-categorie').value= '';
  document.getElementById('filtre-piece').value    = '';
  document.getElementById('filtre-etat').value     = '';
  document.getElementById('filtre-marque').value   = '';
  appliquerFiltres();
}

// Recherche au clavier (Enter dans le champ texte)
document.getElementById('filtre-q').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') appliquerFiltres();
});

// Charger les résultats au démarrage
appliquerFiltres();
</script>

<?php require_once 'includes/footer.php'; ?>
