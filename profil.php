<?php
require_once 'includes/config.php';
startSession();
$pageTitle  = 'Mon profil';
$activePage = 'profil';
require_once 'includes/header.php';

$pdo = getDB();
$errors  = [];
$success = '';

// Gestion upload photo
$photoPath = $user['photo'];

// Traitement formulaire modification profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_public') {
        $login       = sanitize($_POST['login'] ?? '');
        $age         = (int)($_POST['age'] ?? 0);
        $sexe        = sanitize($_POST['sexe'] ?? '');
        $dob         = sanitize($_POST['date_naissance'] ?? '');
        $type_membre = sanitize($_POST['type_membre'] ?? '');

        if (strlen($login) < 3) $errors[] = 'Login trop court.';
        
        // Vérifier unicité login (sauf pour soi)
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE login = ? AND id != ?");
            $stmt->execute([$login, $user['id']]);
            if ($stmt->fetch()) $errors[] = 'Ce login est déjà pris.';
        }

        if (empty($errors)) {
            $pdo->prepare("UPDATE utilisateurs SET login=?,age=?,sexe=?,date_naissance=?,type_membre=? WHERE id=?")
                ->execute([$login, $age, $sexe, $dob, $type_membre, $user['id']]);
            $_SESSION['user_login'] = $login;
            $success = 'Profil public mis à jour !';
            $user = getCurrentUser();
        }
    }

    elseif ($action === 'update_private') {
        $nom    = sanitize($_POST['nom'] ?? '');
        $prenom = sanitize($_POST['prenom'] ?? '');
        $email  = sanitize($_POST['email'] ?? '');
        $mdp    = $_POST['new_mdp'] ?? '';
        $mdp_c  = $_POST['new_mdp_confirm'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
        if ($mdp && strlen($mdp) < 8) $errors[] = 'Mot de passe trop court (min 8 car.).';
        if ($mdp && $mdp !== $mdp_c) $errors[] = 'Les mots de passe ne correspondent pas.';

        if (empty($errors)) {
            if ($mdp) {
                $hash = password_hash($mdp, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE utilisateurs SET nom=?,prenom=?,email=?,mot_de_passe=? WHERE id=?")
                    ->execute([$nom, $prenom, $email, $hash, $user['id']]);
            } else {
                $pdo->prepare("UPDATE utilisateurs SET nom=?,prenom=?,email=? WHERE id=?")
                    ->execute([$nom, $prenom, $email, $user['id']]);
            }
            $success = 'Informations privées mises à jour !';
            $user = getCurrentUser();
        }
    }

    elseif ($action === 'upgrade_niveau') {
        $user = getCurrentUser();
        if (canUpgradeNiveau($user)) {
            $next = getNextNiveau($user['niveau']);
            $pdo->prepare("UPDATE utilisateurs SET niveau=? WHERE id=?")->execute([$next, $user['id']]);
            $_SESSION['user_niveau'] = $next;
            $success = '🎉 Félicitations ! Vous êtes maintenant ' . getNiveauLabel($next) . ' !';
            $user = getCurrentUser();
        }
    }
}

// Statistiques utilisateur
$nbActions = $pdo->prepare("SELECT COUNT(*) FROM actions_utilisateurs WHERE utilisateur_id=?");
$nbActions->execute([$user['id']]);
$nbActions = $nbActions->fetchColumn();

$dernieresCo = $pdo->prepare("SELECT * FROM historique_connexions WHERE utilisateur_id=? ORDER BY timestamp DESC LIMIT 5");
$dernieresCo->execute([$user['id']]);
$dernieresCo = $dernieresCo->fetchAll();

$niveauEmoji = ['débutant'=>'🌱','intermédiaire'=>'⚡','avancé'=>'🚀','expert'=>'👑'];
$nextNiveauSeuil = ['débutant'=>POINTS_INTERMEDIAIRE,'intermédiaire'=>POINTS_AVANCE,'avancé'=>POINTS_EXPERT,'expert'=>null];
$currentSeuil    = ['débutant'=>0,'intermédiaire'=>POINTS_INTERMEDIAIRE,'avancé'=>POINTS_AVANCE,'expert'=>POINTS_EXPERT];
$next    = $nextNiveauSeuil[$user['niveau']] ?? null;
$from    = $currentSeuil[$user['niveau']] ?? 0;
$pct     = $next ? min(100, round(($user['points'] - $from) / ($next - $from) * 100)) : 100;
$canUp   = canUpgradeNiveau($user);
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e): ?><div>• <?= sanitize($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><?= sanitize($success) ?></div>
<?php endif; ?>

<!-- EN-TÊTE PROFIL -->
<div class="card" style="margin-bottom:20px">
  <div class="profile-header">
    <div class="profile-avatar-lg">
      <?= $niveauEmoji[$user['niveau']] ?? '👤' ?>
    </div>
    <div class="profile-info">
      <div class="name"><?= sanitize($user['login']) ?></div>
      <div class="type"><?= sanitize($user['type_membre']) ?> · Inscrit le <?= date('d/m/Y', strtotime($user['date_inscription'])) ?></div>
      <div style="margin-top:8px">
        <span class="badge-niveau <?= $user['niveau'] ?>"><?= $niveauEmoji[$user['niveau']] ?> <?= getNiveauLabel($user['niveau']) ?></span>
      </div>
    </div>
    <div style="margin-left:auto;text-align:right">
      <div style="font-size:28px;font-weight:700;color:var(--accent)"><?= number_format($user['points'], 2) ?> pts</div>
      <div style="font-size:12px;color:var(--text-muted)"><?= $user['nb_connexions'] ?> connexions · <?= $nbActions ?> actions</div>
    </div>
  </div>

  <!-- Progression niveau -->
  <div style="margin-top:16px">
    <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-secondary);margin-bottom:6px">
      <span><?= getNiveauLabel($user['niveau']) ?> (<?= $from ?> pts)</span>
      <?php if ($next): ?><span>Prochain : <?= getNiveauLabel($next) ?> (<?= $next ?> pts)</span><?php else: ?><span>Niveau maximum atteint 👑</span><?php endif; ?>
    </div>
    <div class="progress-bar" style="height:8px">
      <div class="progress-fill" style="width:<?= $pct ?>%"></div>
    </div>
    <div style="text-align:center;font-size:12px;color:var(--text-muted);margin-top:4px"><?= $pct ?>%</div>
  </div>

  <?php if ($canUp): ?>
  <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)" id="niveau">
    <div class="alert alert-success" style="margin-bottom:12px">
      🎉 Vous avez assez de points pour passer au niveau <strong><?= getNiveauLabel(getNextNiveau($user['niveau'])) ?></strong> !
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="upgrade_niveau">
      <button type="submit" class="btn btn-success">
        🚀 Passer au niveau <?= getNiveauLabel(getNextNiveau($user['niveau'])) ?>
      </button>
    </form>
  </div>
  <?php endif; ?>
</div>

<!-- ONGLETS -->
<div class="tabs">
  <button class="tab-btn active" onclick="showTab('public', this)">👤 Profil public</button>
  <button class="tab-btn" onclick="showTab('prive', this)">🔒 Infos privées</button>
  <button class="tab-btn" onclick="showTab('activite', this)">📊 Activité</button>
</div>

<!-- ONGLET PUBLIC -->
<div id="tab-public" class="tab-content">
  <div class="card">
    <div class="card-title">Informations publiques</div>
    <form method="POST">
      <input type="hidden" name="action" value="update_public">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Pseudonyme (login) *</label>
          <input type="text" name="login" class="form-control" value="<?= sanitize($user['login']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Âge</label>
          <input type="number" name="age" class="form-control" value="<?= $user['age'] ?>" min="1" max="120">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Date de naissance</label>
          <input type="date" name="date_naissance" class="form-control" value="<?= $user['date_naissance'] ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Sexe / Genre</label>
          <select name="sexe" class="form-control">
            <option value="">--</option>
            <option value="homme" <?= $user['sexe'] === 'homme' ? 'selected' : '' ?>>Homme</option>
            <option value="femme" <?= $user['sexe'] === 'femme' ? 'selected' : '' ?>>Femme</option>
            <option value="autre" <?= $user['sexe'] === 'autre' ? 'selected' : '' ?>>Autre</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Rôle dans la maison</label>
        <select name="type_membre" class="form-control">
          <?php foreach (['père','mère','enfant','grand-parent','autre'] as $tm): ?>
          <option value="<?= $tm ?>" <?= $user['type_membre'] === $tm ? 'selected' : '' ?>><?= ucfirst($tm) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
    </form>
  </div>
</div>

<!-- ONGLET PRIVÉ -->
<div id="tab-prive" class="tab-content" style="display:none">
  <div class="card">
    <div class="card-title">🔒 Informations privées (visibles par vous seul et l'admin)</div>
    <form method="POST">
      <input type="hidden" name="action" value="update_private">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Prénom</label>
          <input type="text" name="prenom" class="form-control" value="<?= sanitize($user['prenom'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Nom</label>
          <input type="text" name="nom" class="form-control" value="<?= sanitize($user['nom'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= sanitize($user['email']) ?>">
      </div>
      <div class="divider"></div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nouveau mot de passe (laisser vide = inchangé)</label>
          <input type="password" name="new_mdp" class="form-control" placeholder="≥ 8 caractères">
        </div>
        <div class="form-group">
          <label class="form-label">Confirmer</label>
          <input type="password" name="new_mdp_confirm" class="form-control" placeholder="••••••••">
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </form>
  </div>
</div>

<!-- ONGLET ACTIVITÉ -->
<div id="tab-activite" class="tab-content" style="display:none">
  <div class="card">
    <div class="card-title">📊 Dernières connexions</div>
    <?php if (empty($dernieresCo)): ?>
    <p style="color:var(--text-muted)">Aucune connexion enregistrée.</p>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Date</th><th>IP</th><th>Points gagnés</th></tr></thead>
        <tbody>
          <?php foreach ($dernieresCo as $co): ?>
          <tr>
            <td class="mono"><?= date('d/m/Y H:i', strtotime($co['timestamp'])) ?></td>
            <td class="mono"><?= sanitize($co['ip_adresse']) ?></td>
            <td style="color:var(--success)">+<?= $co['points_gagnes'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <div style="margin-top:20px">
      <div class="card-title">Système de points</div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;font-size:13px">
        <?php $niveaux = ['débutant'=>0,'intermédiaire'=>POINTS_INTERMEDIAIRE,'avancé'=>POINTS_AVANCE,'expert'=>POINTS_EXPERT]; ?>
        <?php foreach ($niveaux as $niv => $seuil): ?>
        <div style="background:var(--bg-secondary);border:1px solid var(--border);border-radius:var(--radius-md);padding:12px">
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px"><?= $niveauEmoji[$niv] ?> <?= ucfirst($niv) ?></div>
          <div style="font-weight:600"><?= $seuil ?> pts</div>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:12px;font-size:12px;color:var(--text-muted)">
        Connexion : +<?= POINTS_CONNEXION ?>pt · Consultation : +<?= POINTS_CONSULTATION ?>pt · Modification : +<?= POINTS_MODIFICATION ?>pt · Ajout : +<?= POINTS_AJOUT ?>pt
      </div>
    </div>
  </div>
</div>

<script>
function showTab(name, btn) {
  document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).style.display = '';
  btn.classList.add('active');
}
</script>

<?php require_once 'includes/footer.php'; ?>
