<?php
// ============================================================
//  index.php — MaisonSmart  (corrigé pour MAMP)
//  CORRECTIONS :
//  1. Chemins CSS/JS : '../style/' → 'style/' (structure à plat)
//  2. Chemin JS : '../js/'       → 'js/'
//  3. Requête pièces : guillemets doubles dans SQL → simples
//  4. Toutes les requêtes exécutées seulement si la BDD répond
// ============================================================
session_start();
require_once 'db.php';

$role   = $_SESSION['role']   ?? 'visiteur';
$prenom = $_SESSION['prenom'] ?? 'Visiteur';

$access = [
    'visiteur' => ['info'],
    'simple'   => ['info', 'visu'],
    'complexe' => ['info', 'visu', 'gestion'],
    'admin'    => ['info', 'visu', 'gestion', 'admin'],
];
$allowed = $access[$role] ?? ['info'];

$pdo = getDB();

// ── Module Information ──────────────────────────────────────
$infoObjets = $pdo->query(
    'SELECT o.id, o.nom, o.type, o.unite, p.nom AS piece
     FROM objets_connectes o
     LEFT JOIN pieces p ON o.piece_id = p.id
     WHERE o.actif = 1
     ORDER BY o.nom'
)->fetchAll();

// ── Module Visualisation ────────────────────────────────────
$dernieresMesures = $pdo->query(
    'SELECT d.objet_id, o.nom, p.nom AS piece, o.type, o.unite,
            d.valeur, d.statut, d.enregistre_le
     FROM donnees_capteurs d
     INNER JOIN (
         SELECT objet_id, MAX(enregistre_le) AS mx
         FROM donnees_capteurs GROUP BY objet_id
     ) latest ON d.objet_id = latest.objet_id
               AND d.enregistre_le = latest.mx
     INNER JOIN objets_connectes o ON o.id = d.objet_id
     LEFT JOIN pieces p ON o.piece_id = p.id
     ORDER BY d.statut DESC, o.nom'
)->fetchAll();

$consoSemaine = $pdo->query(
    "SELECT jour, SUM(valeur) AS total
     FROM consommation
     WHERE type_conso = 'energie'
       AND jour >= CURDATE() - INTERVAL 6 DAY
     GROUP BY jour ORDER BY jour"
)->fetchAll();

$statsGlobales = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM objets_connectes WHERE actif = 1) AS nb_actifs,
        (SELECT COUNT(*) FROM donnees_capteurs
         WHERE statut IN ('warn','alert')
           AND enregistre_le >= NOW() - INTERVAL 24 HOUR)       AS nb_alertes,
        (SELECT IFNULL(SUM(valeur),0) FROM consommation
         WHERE type_conso = 'energie' AND jour = CURDATE())      AS conso_energie,
        (SELECT IFNULL(SUM(valeur),0) FROM consommation
         WHERE type_conso = 'eau' AND jour = CURDATE())          AS conso_eau"
)->fetch();

// ── Module Gestion ──────────────────────────────────────────
// CORRECTION 3 : guillemets doubles dans SQL remplacés par simples
// MySQL accepte les doubles, mais certaines configs MAMP/PDO strict les rejettent
$pieces = $pdo->query(
    "SELECT p.id, p.nom,
            (SELECT d.valeur FROM donnees_capteurs d
             INNER JOIN objets_connectes o ON o.id = d.objet_id
             WHERE o.piece_id = p.id AND o.type = 'confort'
             ORDER BY d.enregistre_le DESC LIMIT 1) AS temp,
            (SELECT d.valeur FROM donnees_capteurs d
             INNER JOIN objets_connectes o ON o.id = d.objet_id
             WHERE o.piece_id = p.id AND o.type = 'securite' AND o.unite = 'ppm'
             ORDER BY d.enregistre_le DESC LIMIT 1) AS co2
     FROM pieces p ORDER BY p.etage, p.nom"
)->fetchAll();

$accesLog = $pdo->query(
    'SELECT * FROM acces ORDER BY enregistre_le DESC LIMIT 10'
)->fetchAll();

// ── Module Administration ────────────────────────────────────
$users = $objetsAdmin = [];
if (in_array('admin', $allowed)) {
    $users       = $pdo->query('SELECT * FROM utilisateurs ORDER BY role, nom')->fetchAll();
    $objetsAdmin = $pdo->query(
        'SELECT o.*, p.nom AS piece FROM objets_connectes o
         LEFT JOIN pieces p ON o.piece_id = p.id ORDER BY o.type, o.nom'
    )->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MaisonSmart — Tableau de bord</title>
    <!-- CORRECTION 1 : chemins relatifs depuis le dossier php/ -->
    <link rel="stylesheet" href="style/main.css">
    <link rel="stylesheet" href="style/dashboard.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<main class="dashboard">

    <nav class="mod-nav">
        <button class="mod-btn active" data-mod="info">
            <span class="mod-icon">&#9432;</span> Information
        </button>
        <?php if (in_array('visu', $allowed)): ?>
        <button class="mod-btn" data-mod="visu">
            <span class="mod-icon">&#9000;</span> Visualisation
        </button>
        <?php endif; ?>
        <?php if (in_array('gestion', $allowed)): ?>
        <button class="mod-btn" data-mod="gestion">
            <span class="mod-icon">&#9881;</span> Gestion
        </button>
        <?php endif; ?>
        <?php if (in_array('admin', $allowed)): ?>
        <button class="mod-btn" data-mod="admin">
            <span class="mod-icon">&#9632;</span> Administration
        </button>
        <?php endif; ?>
    </nav>

    <!-- MODULE INFORMATION -->
    <section class="module active" id="mod-info">
        <div class="section-header">
            <h2>Objets connectés — accès libre</h2>
            <p class="section-sub">Consultez les appareils de la maison sans vous connecter.</p>
        </div>

        <div class="filters-bar">
            <div class="filter-group">
                <label for="f-type">Type d'appareil</label>
                <select id="f-type" onchange="filterInfo()">
                    <option value="">Tous les types</option>
                    <option value="securite">Sécurité</option>
                    <option value="energie">Énergie</option>
                    <option value="confort">Confort</option>
                    <option value="electromenager">Électroménager</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="f-piece">Pièce</label>
                <select id="f-piece" onchange="filterInfo()">
                    <option value="">Toutes les pièces</option>
                    <?php foreach (array_unique(array_column($infoObjets, 'piece')) as $p): ?>
                        <option value="<?= htmlspecialchars((string)$p) ?>">
                            <?= htmlspecialchars((string)$p) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group filter-search">
                <label for="f-search">Recherche libre</label>
                <input type="text" id="f-search" placeholder="ex: caméra, thermostat..."
                       oninput="filterInfo()">
            </div>
        </div>

        <div class="info-grid" id="info-grid">
            <?php foreach ($infoObjets as $obj): ?>
            <div class="info-card"
                 data-type="<?= htmlspecialchars($obj['type']) ?>"
                 data-piece="<?= htmlspecialchars((string)($obj['piece'] ?? '')) ?>"
                 data-nom="<?= strtolower(htmlspecialchars($obj['nom'])) ?>">
                <div class="info-card-type type-<?= htmlspecialchars($obj['type']) ?>">
                    <?= htmlspecialchars(ucfirst($obj['type'])) ?>
                </div>
                <h3 class="info-card-name"><?= htmlspecialchars($obj['nom']) ?></h3>
                <div class="info-card-meta">
                    <span class="piece-tag"><?= htmlspecialchars($obj['piece'] ?? 'N/A') ?></span>
                    <span class="unite-tag"><?= htmlspecialchars($obj['unite']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="empty-msg hidden" id="info-empty">Aucun appareil ne correspond à ces filtres.</div>

        <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="signup-cta">
            <p>Vous souhaitez contrôler votre maison ?</p>
            <a href="inscription.php" class="btn btn-primary">Créer un compte</a>
            <a href="login.php" class="btn btn-outline">Se connecter</a>
        </div>
        <?php endif; ?>
    </section>

    <!-- MODULE VISUALISATION -->
    <?php if (in_array('visu', $allowed)): ?>
    <section class="module" id="mod-visu">
        <div class="section-header">
            <h2>Tableau de bord — temps réel</h2>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Appareils actifs</div>
                <div class="stat-value ok"><?= (int)$statsGlobales['nb_actifs'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Alertes (24h)</div>
                <div class="stat-value <?= $statsGlobales['nb_alertes'] > 0 ? 'alert' : 'ok' ?>">
                    <?= (int)$statsGlobales['nb_alertes'] ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Énergie aujourd'hui (kWh)</div>
                <div class="stat-value"><?= number_format((float)$statsGlobales['conso_energie'], 1) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Eau aujourd'hui (L)</div>
                <div class="stat-value"><?= number_format((float)$statsGlobales['conso_eau'], 1) ?></div>
            </div>
        </div>

        <div class="chart-card">
            <h3>Consommation électrique — 7 derniers jours (kWh)</h3>
            <div class="chart-bars" id="chart-bars">
                <?php
                $jours  = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
                $totaux = array_column($consoSemaine, 'total');
                $maxConso = !empty($totaux) ? max($totaux) : 1;
                foreach ($consoSemaine as $c):
                    $pct    = round(($c['total'] / $maxConso) * 100);
                    $libelle = $jours[date('N', strtotime($c['jour'])) - 1];
                ?>
                <div class="bar-col">
                    <span class="bar-val"><?= number_format((float)$c['total'], 1) ?></span>
                    <div class="bar-fill" style="height: <?= $pct ?>%"></div>
                    <span class="bar-day"><?= $libelle ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="tri-bar">
            <label for="tri-capteurs">Trier les capteurs :</label>
            <select id="tri-capteurs" onchange="applySorting()">
                <option value="valeur_asc">Valeur croissante</option>
                <option value="valeur_desc">Valeur décroissante</option>
                <option value="nom_asc">Nom A → Z</option>
                <option value="nom_desc">Nom Z → A</option>
                <option value="statut_asc">Statut (OK en premier)</option>
                <option value="statut_desc">Statut (Alertes en premier)</option>
            </select>
        </div>

        <div class="capteurs-grid" id="capteurs-box">
            <?php foreach ($dernieresMesures as $m): ?>
            <div class="capteur-card statut-<?= htmlspecialchars($m['statut']) ?>"
                 data-valeur="<?= floatval($m['valeur']) ?>"
                 data-nom="<?= strtolower(htmlspecialchars($m['nom'])) ?>"
                 data-statut="<?= $m['statut'] === 'ok' ? 0 : ($m['statut'] === 'warn' ? 1 : 2) ?>">
                <div class="capteur-head">
                    <span class="capteur-nom"><?= htmlspecialchars($m['nom']) ?></span>
                    <span class="badge badge-<?= htmlspecialchars($m['statut']) ?>">
                        <?= strtoupper(htmlspecialchars($m['statut'])) ?>
                    </span>
                </div>
                <div class="capteur-valeur">
                    <?= htmlspecialchars($m['valeur']) ?>
                    <span class="capteur-unite"><?= htmlspecialchars($m['unite']) ?></span>
                </div>
                <div class="capteur-meta"><?= htmlspecialchars((string)($m['piece'] ?? '')) ?> · <?= htmlspecialchars($m['type']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- MODULE GESTION -->
    <?php if (in_array('gestion', $allowed)): ?>
    <section class="module" id="mod-gestion">
        <div class="section-header">
            <h2>Gestion de la maison</h2>
        </div>

        <h3 class="sub-title">Températures &amp; CO₂ par pièce</h3>
        <div class="rooms-grid">
            <?php foreach ($pieces as $piece): ?>
            <div class="room-card">
                <div class="room-name"><?= htmlspecialchars($piece['nom']) ?></div>
                <div class="room-row">
                    <span>Température</span>
                    <span><?= $piece['temp'] ? htmlspecialchars($piece['temp']) . ' °C' : '—' ?></span>
                </div>
                <div class="room-row">
                    <span>CO₂</span>
                    <span class="<?= (($piece['co2'] ?? 0) > 800) ? 'val-warn' : 'val-ok' ?>">
                        <?= $piece['co2'] ? htmlspecialchars($piece['co2']) . ' ppm' : '—' ?>
                    </span>
                </div>
                <?php if ($role === 'complexe' || $role === 'admin'): ?>
                <a href="gestion_piece.php?id=<?= (int)$piece['id'] ?>" class="btn btn-sm">Gérer</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <h3 class="sub-title" style="margin-top: 2rem">Journal des accès</h3>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Personne</th><th>Porte</th><th>Heure</th><th>Statut</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($accesLog as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['personne']) ?></td>
                        <td><?= htmlspecialchars($a['porte']) ?></td>
                        <td><?= date('d/m H\hi', strtotime($a['enregistre_le'])) ?></td>
                        <td>
                            <span class="badge badge-<?= $a['statut'] === 'autorise' ? 'ok' : 'alert' ?>">
                                <?= $a['statut'] === 'autorise' ? 'Autorisé' : 'Alerte' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <!-- MODULE ADMINISTRATION -->
    <?php if (in_array('admin', $allowed)): ?>
    <section class="module" id="mod-admin">
        <div class="section-header">
            <h2>Administration</h2>
        </div>

        <h3 class="sub-title">Utilisateurs enregistrés</h3>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Habitant</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></td>
                        <td><?= htmlspecialchars($u['mail']) ?></td>
                        <td>
                            <form method="POST" action="admin_action.php" style="display:inline">
                                <input type="hidden" name="action" value="change_role">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <select name="role" onchange="this.form.submit()" class="select-inline">
                                    <?php foreach (['simple','complexe','admin'] as $r): ?>
                                    <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>>
                                        <?= $r ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td><span class="badge badge-<?= $u['statut'] === 'actif' ? 'ok' : 'alert' ?>">
                            <?= htmlspecialchars($u['statut']) ?>
                        </span></td>
                        <td>
                            <form method="POST" action="admin_action.php" style="display:inline">
                                <input type="hidden" name="action" value="toggle_ban">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <?= $u['statut'] === 'banni' ? 'Débannir' : 'Bannir' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h3 class="sub-title" style="margin-top: 2rem">Objets connectés en base</h3>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Appareil</th><th>Pièce</th><th>Type</th><th>Unité</th><th>Actif</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($objetsAdmin as $o): ?>
                    <tr>
                        <td><?= htmlspecialchars($o['nom']) ?></td>
                        <td><?= htmlspecialchars($o['piece'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($o['type']) ?></td>
                        <td><?= htmlspecialchars($o['unite']) ?></td>
                        <td><span class="badge badge-<?= $o['actif'] ? 'ok' : 'off' ?>">
                            <?= $o['actif'] ? 'Oui' : 'Non' ?>
                        </span></td>
                        <td>
                            <form method="POST" action="admin_action.php" style="display:inline">
                                <input type="hidden" name="action" value="toggle_objet">
                                <input type="hidden" name="objet_id" value="<?= (int)$o['id'] ?>">
                                <button type="submit" class="btn btn-sm">
                                    <?= $o['actif'] ? 'Désactiver' : 'Activer' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h3 class="sub-title" style="margin-top: 2rem">Ajouter un appareil</h3>
        <form method="POST" action="admin_action.php" class="add-form">
            <input type="hidden" name="action" value="add_objet">
            <div class="form-row">
                <div class="form-group">
                    <label>Nom de l'appareil</label>
                    <input type="text" name="nom" required placeholder="ex: Caméra jardin">
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" required>
                        <option value="securite">Sécurité</option>
                        <option value="energie">Énergie</option>
                        <option value="confort">Confort</option>
                        <option value="electromenager">Électroménager</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Pièce</label>
                    <select name="piece_id">
                        <option value="">— Aucune —</option>
                        <?php foreach ($pieces as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Unité de mesure</label>
                    <input type="text" name="unite" placeholder="ex: °C, ppm, %">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Ajouter l'appareil</button>
        </form>
    </section>
    <?php endif; ?>

</main>

<?php include 'footer.php'; ?>
<!-- CORRECTION 2 : chemin JS corrigé -->
<script src="js/main.js"></script>
</body>
</html>

