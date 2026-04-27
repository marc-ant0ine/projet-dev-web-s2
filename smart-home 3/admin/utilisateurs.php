<?php
require_once '../includes/config.php';
startSession();
requireLevel(['expert']);
$pageTitle  = 'Administration';
$activePage = 'admin';
require_once '../includes/header.php';

$pdo = getDB();
$errors  = [];
$success = '';

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');

    if ($action === 'update_user') {
        $uid    = (int)$_POST['uid'];
        $niveau = sanitize($_POST['niveau'] ?? '');
        $statut = sanitize($_POST['statut'] ?? '');
        $points = (float)$_POST['points'];
        $pdo->prepare("UPDATE utilisateurs SET niveau=?,statut=?,points=? WHERE id=?")
            ->execute([$niveau,$statut,$points,$uid]);
        $success = 'Utilisateur mis à jour.';
    }
    elseif ($action === 'delete_user') {
        $uid = (int)$_POST['uid'];
        if ($uid !== $user['id']) {
            $pdo->prepare("DELETE FROM utilisateurs WHERE id=?")->execute([$uid]);
            $success = 'Utilisateur supprimé.';
        } else {
            $errors[] = 'Vous ne pouvez pas supprimer votre propre compte.';
        }
    }
    elseif ($action === 'add_news') {
        $titre   = sanitize($_POST['titre'] ?? '');
        $contenu = sanitize($_POST['contenu'] ?? '');
        if ($titre && $contenu) {
            $pdo->prepare("INSERT INTO actualites (titre,contenu,auteur_id) VALUES (?,?,?)")
                ->execute([$titre,$contenu,$user['id']]);
            $success = 'Actualité publiée.';
        }
    }
    elseif ($action === 'validate_user') {
        $uid = (int)$_POST['uid'];
        $pdo->prepare("UPDATE utilisateurs SET statut='actif',token_validation=NULL WHERE id=?")->execute([$uid]);
        $success = 'Compte validé manuellement.';
    }
}

$membres = $pdo->query("SELECT * FROM utilisateurs ORDER BY date_inscription DESC")->fetchAll();
$enAttente = array_filter($membres, fn($m) => $m['statut'] === 'en_attente');

$niveauEmoji = ['débutant'=>'🌱','intermédiaire'=>'⚡','avancé'=>'🚀','expert'=>'👑'];
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-error"><?php foreach ($errors as $e): ?><div>• <?= sanitize($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><?= sanitize($success) ?></div>
<?php endif; ?>

<!-- Comptes en attente -->
<?php if (!empty($enAttente)): ?>
<div class="card" style="margin-bottom:20px;border-color:var(--warning)">
  <div class="card-title" style="color:var(--warning)">⏳ Comptes en attente de validation (<?= count($enAttente) ?>)</div>
  <div class="table-wrap">
    <table class="table table-striped table-hover">
      <thead><tr><th>Login</th><th>Email</th><th>Inscrit le</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($enAttente as $m): ?>
        <tr>
          <td><?= sanitize($m['login']) ?></td>
          <td><?= sanitize($m['email']) ?></td>
          <td class="mono"><?= date('d/m/Y', strtotime($m['date_inscription'])) ?></td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="validate_user">
              <input type="hidden" name="uid" value="<?= $m['id'] ?>">
              <button type="submit" class="btn btn-success btn-sm">✓ Valider</button>
            </form>
            <form method="POST" style="display:inline;margin-left:6px" onsubmit="return confirm('Supprimer ce compte ?')">
              <input type="hidden" name="action" value="delete_user">
              <input type="hidden" name="uid" value="<?= $m['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">✕ Refuser</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Tous les membres -->
<div class="card" style="margin-bottom:20px">
  <div class="card-title">👥 Gestion des membres (<?= count($membres) ?>)</div>
  <div class="table-wrap">
    <table class="table table-striped table-hover">
      <thead><tr><th>Login</th><th>Rôle</th><th>Niveau</th><th>Points</th><th>Statut</th><th>Connexions</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($membres as $m): ?>
        <tr>
          <td>
            <div style="font-weight:600"><?= sanitize($m['login']) ?></div>
            <div style="font-size:11px;color:var(--text-muted)"><?= sanitize($m['email']) ?></div>
          </td>
          <td><?= sanitize($m['type_membre']) ?></td>
          <td><span class="badge-niveau <?= $m['niveau'] ?>"><?= $niveauEmoji[$m['niveau']] ?> <?= getNiveauLabel($m['niveau']) ?></span></td>
          <td style="color:var(--accent);font-weight:600"><?= number_format($m['points'], 1) ?></td>
          <td>
            <?php $couleurStatut = ['actif'=>'var(--success)','en_attente'=>'var(--warning)','suspendu'=>'var(--danger)']; ?>
            <span style="color:<?= $couleurStatut[$m['statut']] ?? 'inherit' ?>;font-size:12px;font-weight:600">
              <?= ucfirst(str_replace('_',' ',$m['statut'])) ?>
            </span>
          </td>
          <td><?= $m['nb_connexions'] ?></td>
          <td>
            <button class="btn btn-secondary btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)">✏️</button>
            <?php if ($m['id'] !== $user['id']): ?>
            <form method="POST" style="display:inline;margin-left:4px" onsubmit="return confirm('Supprimer définitivement ?')">
              <input type="hidden" name="action" value="delete_user">
              <input type="hidden" name="uid" value="<?= $m['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Publier actualité -->
<div class="card">
  <div class="card-title">📰 Publier une actualité</div>
  <form method="POST" style="max-width:600px">
    <input type="hidden" name="action" value="add_news">
    <div class="form-group">
      <label class="form-label">Titre</label>
      <input type="text" name="titre" class="form-control" required>
    </div>
    <div class="form-group">
      <label class="form-label">Contenu</label>
      <textarea name="contenu" class="form-control" rows="4" required></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Publier</button>
  </form>
</div>

<!-- Export global -->
<div class="card" style="margin-top:20px">
  <div class="card-title">📤 Exports</div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="../gestion/exporter-rapport.php" class="btn btn-secondary">⬇️ CSV tous les objets</a>
  </div>
</div>

<!-- Modal édition utilisateur -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">✏️ Modifier l'utilisateur</h3>
      <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('open')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update_user">
      <input type="hidden" name="uid" id="edit-uid">
      <div class="form-group">
        <label class="form-label">Login</label>
        <input type="text" id="edit-login" class="form-control" disabled>
      </div>
      <div class="form-group">
        <label class="form-label">Niveau</label>
        <select name="niveau" id="edit-niveau" class="form-control">
          <option value="débutant">🌱 Débutant</option>
          <option value="intermédiaire">⚡ Intermédiaire</option>
          <option value="avancé">🚀 Avancé</option>
          <option value="expert">👑 Expert</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Statut</label>
        <select name="statut" id="edit-statut" class="form-control">
          <option value="actif">Actif</option>
          <option value="en_attente">En attente</option>
          <option value="suspendu">Suspendu</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Points (ajustement manuel)</label>
        <input type="number" name="points" id="edit-points" class="form-control" step="0.01">
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">Enregistrer</button>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('editModal').classList.remove('open')">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(m) {
  document.getElementById('edit-uid').value    = m.id;
  document.getElementById('edit-login').value  = m.login;
  document.getElementById('edit-niveau').value = m.niveau;
  document.getElementById('edit-statut').value = m.statut;
  document.getElementById('edit-points').value = m.points;
  document.getElementById('editModal').classList.add('open');
}
</script>

<?php require_once '../includes/footer.php'; ?>
