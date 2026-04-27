<?php
require_once 'includes/config.php';
startSession();

$pdo = getDB();

$actualites = $pdo->query("SELECT * FROM actualites WHERE visible=1 ORDER BY date_publication DESC LIMIT 4")->fetchAll();
$nbObjets   = $pdo->query("SELECT COUNT(*) FROM objets_connectes WHERE etat='actif'")->fetchColumn();
$nbPieces   = $pdo->query("SELECT COUNT(*) FROM pieces")->fetchColumn();
$nbMembres  = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE statut='actif'")->fetchColumn();
$categories = $pdo->query("SELECT * FROM categories_objets ORDER BY nom")->fetchAll();
$pieces     = $pdo->query("SELECT * FROM pieces ORDER BY nom")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SmartHome — Votre maison intelligente</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/css/style.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
</head>
<body>

<!-- NAVIGATION -->
<nav class="public-nav">
  <div class="sidebar-logo" style="border:none;padding:0">
    <div class="logo-icon">🏠</div>
    <div class="logo-text">Smart<span>Home</span></div>
  </div>
  <div style="display:flex;gap:10px;align-items:center">
    <?php if (isLoggedIn()): ?>
      <a href="<?= SITE_URL ?>/dashboard.php" class="btn btn-primary btn-sm">Mon espace</a>
    <?php else: ?>
      <a href="<?= SITE_URL ?>/login.php" class="btn btn-secondary btn-sm">Connexion</a>
      <a href="<?= SITE_URL ?>/inscription.php" class="btn btn-primary btn-sm">S'inscrire</a>
    <?php endif; ?>
  </div>
</nav>

<!-- HERO -->
<section class="hero-content">
  <div class="hero-badge">
    <span class="material-icons" style="font-size:13px">bolt</span>
    Plateforme de maison intelligente
  </div>
  <h1 class="hero-title">
    Pilotez votre maison<br>
    <span class="highlight">de façon intelligente</span>
  </h1>
  <p class="hero-subtitle">
    Centralisez la gestion de vos <?= $nbObjets ?> objets connectés,
    suivez votre consommation et vivez mieux dans vos <?= $nbPieces ?> pièces.
  </p>
  <div class="hero-actions">
    <a href="#explorer" class="btn btn-primary btn-lg">
      <span class="material-icons" style="font-size:18px">explore</span>
      Explorer la plateforme
    </a>
    <a href="<?= SITE_URL ?>/inscription.php" class="btn btn-secondary btn-lg">
      Rejoindre la maison →
    </a>
  </div>
</section>

<!-- STATS -->
<div style="display:flex;gap:24px;justify-content:center;flex-wrap:wrap;padding:0 40px 60px">
  <div style="text-align:center">
    <div style="font-size:32px;font-weight:700;color:var(--accent)"><?= $nbObjets ?></div>
    <div style="font-size:13px;color:var(--text-secondary)">objets actifs</div>
  </div>
  <div style="width:1px;background:var(--border)"></div>
  <div style="text-align:center">
    <div style="font-size:32px;font-weight:700;color:var(--accent2)"><?= $nbPieces ?></div>
    <div style="font-size:13px;color:var(--text-secondary)">pièces connectées</div>
  </div>
  <div style="width:1px;background:var(--border)"></div>
  <div style="text-align:center">
    <div style="font-size:32px;font-weight:700;color:var(--success)"><?= $nbMembres ?></div>
    <div style="font-size:13px;color:var(--text-secondary)">membres actifs</div>
  </div>
</div>

<!-- FONCTIONNALITÉS -->
<section style="background:var(--bg-secondary);padding:60px 0;border-top:1px solid var(--border)">
  <div style="text-align:center;margin-bottom:40px;padding:0 20px">
    <h2 style="font-size:32px;font-weight:700;margin-bottom:10px">Fonctionnalités</h2>
    <p style="color:var(--text-secondary)">Plateforme de gestion de maison connectée</p>
  </div>
  <div class="features-grid">
    <div class="feature-card"><div class="feature-icon">🌡️</div><div class="feature-title">Thermostats</div><div class="feature-desc">Contrôle de la température par pièce.</div></div>
    <div class="feature-card"><div class="feature-icon">💡</div><div class="feature-title">Éclairage</div><div class="feature-desc">Gestion de la luminosité et des ambiances.</div></div>
    <div class="feature-card"><div class="feature-icon">🔒</div><div class="feature-title">Sécurité</div><div class="feature-desc">Caméras, serrures et détecteurs connectés.</div></div>
    <div class="feature-card"><div class="feature-icon">⚡</div><div class="feature-title">Énergie</div><div class="feature-desc">Suivi de la consommation en temps réel.</div></div>
    <div class="feature-card"><div class="feature-icon">🤖</div><div class="feature-title">Automatisation</div><div class="feature-desc">Programmation de routines.</div></div>
    <div class="feature-card"><div class="feature-icon">📊</div><div class="feature-title">Rapports</div><div class="feature-desc">Statistiques d'utilisation et exports CSV.</div></div>
  </div>
</section>

<!-- recherche -->
<section style="padding:60px 40px;max-width:1000px;margin:0 auto" id="explorer">
  <div class="section-header">
    <div>
      <h2 class="section-title">Explorer les objets</h2>
      <p class="section-sub">Découvrez les équipements de la maison intelligente</p>
    </div>
  </div>

  <!-- Filtres -->
  <div style="margin-bottom:24px">
    <div class="search-bar" style="margin-bottom:14px">
      <span class="material-icons" style="color:var(--text-muted);font-size:20px">search</span>
      <input type="text" id="pub-q" placeholder="Rechercher un objet connecté...">
      <button class="btn btn-primary btn-sm" onclick="rechercherPublic()">Rechercher</button>
    </div>
    <div class="filters-row">
      <select id="pub-cat" class="filter-select" onchange="rechercherPublic()">
        <option value="">Toutes les catégories</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>"><?= sanitize($cat['nom']) ?></option>
        <?php endforeach; ?>
      </select>

      <select id="pub-piece" class="filter-select" onchange="rechercherPublic()">
        <option value="">Toutes les pièces</option>
        <?php foreach ($pieces as $p): ?>
        <option value="<?= $p['id'] ?>"><?= sanitize($p['nom']) ?></option>
        <?php endforeach; ?>
      </select>

      <button id="pub-effacer" class="btn btn-secondary btn-sm"
              onclick="effacerPublic()" style="display:none">
        ✕ Effacer
      </button>
    </div>
  </div>

  <!-- Zone résultats -->
  <div id="pub-resultats"></div>

  <?php if (!isLoggedIn()): ?>
  <div style="text-align:center;margin-top:40px;padding:32px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-xl)">
    <p style="font-size:16px;margin-bottom:16px">Rejoignez la maison pour accéder à toutes les fonctionnalités</p>
    <a href="<?= SITE_URL ?>/inscription.php" class="btn btn-primary">Créer un compte membre</a>
  </div>
  <?php endif; ?>
</section>

<!-- ACTUALITÉS -->
<?php if (!empty($actualites)): ?>
<section style="padding:60px 40px;background:var(--bg-secondary);border-top:1px solid var(--border)">
  <div style="max-width:1000px;margin:0 auto">
    <h2 style="font-size:28px;font-weight:700;margin-bottom:28px">Actualités de la maison</h2>
    <div class="news-grid">
      <?php foreach ($actualites as $news): ?>
      <div class="news-card">
        <div class="news-date"><?= date('d/m/Y', strtotime($news['date_publication'])) ?></div>
        <div class="news-title"><?= sanitize($news['titre']) ?></div>
        <div class="news-excerpt"><?= sanitize(mb_substr($news['contenu'], 0, 160)) ?>…</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- FOOTER -->
<footer style="background:var(--bg-secondary);border-top:1px solid var(--border);padding:24px 40px;text-align:center">
  <p style="color:var(--text-muted);font-size:13px">
    © 2025 SmartHome Platform · Projet ING1 ·
    <a href="<?= SITE_URL ?>/inscription.php">Rejoindre</a> ·
    <a href="<?= SITE_URL ?>/login.php">Connexion</a>
  </p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/js/app.js"></script>
<script>
const SITE_URL = '<?= SITE_URL ?>';

function rechercherPublic() {
  const q     = document.getElementById('pub-q').value.trim();
  const cat   = document.getElementById('pub-cat').value;
  const piece = document.getElementById('pub-piece').value;

  // Afficher/masquer Effacer
  const actif = q || cat || piece;
  document.getElementById('pub-effacer').style.display = actif ? 'inline-flex' : 'none';

  if (!actif) {
    document.getElementById('pub-resultats').innerHTML = '';
    return;
  }

  // Loader discret
  document.getElementById('pub-resultats').innerHTML =
    '<div style="text-align:center;padding:30px;color:var(--text-secondary)"><div class="loading"><span></span><span></span><span></span></div></div>';

  const params = new URLSearchParams();
  if (q)     params.set('q', q);
  if (cat)   params.set('cat', cat);
  if (piece) params.set('piece', piece);

  fetch(SITE_URL + '/php/recherche-publique.php?' + params.toString())
    .then(r => r.text())
    .then(html => {
      document.getElementById('pub-resultats').innerHTML = html;
    });
}

function effacerPublic() {
  document.getElementById('pub-q').value     = '';
  document.getElementById('pub-cat').value   = '';
  document.getElementById('pub-piece').value = '';
  document.getElementById('pub-effacer').style.display = 'none';
  document.getElementById('pub-resultats').innerHTML = '';
}

// Recherche au clavier (Entrée)
document.getElementById('pub-q').addEventListener('keydown', e => {
  if (e.key === 'Enter') rechercherPublic();
});
</script>
</body>
</html>
