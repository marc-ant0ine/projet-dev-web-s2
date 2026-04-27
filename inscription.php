<?php
require_once 'includes/config.php';
startSession();

if (isLoggedIn()) redirect(SITE_URL . '/dashboard.php');

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'login'        => sanitize($_POST['login'] ?? ''),
        'email'        => sanitize($_POST['email'] ?? ''),
        'mdp'          => $_POST['mot_de_passe'] ?? '',
        'mdp_confirm'  => $_POST['mdp_confirm'] ?? '',
        'nom'          => sanitize($_POST['nom'] ?? ''),
        'prenom'       => sanitize($_POST['prenom'] ?? ''),
        'age'          => (int)($_POST['age'] ?? 0),
        'sexe'         => sanitize($_POST['sexe'] ?? ''),
        'dob'          => sanitize($_POST['date_naissance'] ?? ''),
        'type_membre'  => sanitize($_POST['type_membre'] ?? ''),
    ];

    // Validation
    if (strlen($data['login']) < 3) $errors[] = 'Le login doit faire au moins 3 caractères.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
    if (strlen($data['mdp']) < 8) $errors[] = 'Le mot de passe doit faire au moins 8 caractères.';
    if ($data['mdp'] !== $data['mdp_confirm']) $errors[] = 'Les mots de passe ne correspondent pas.';
    if (empty($data['nom']) || empty($data['prenom'])) $errors[] = 'Nom et prénom obligatoires.';
    if ($data['age'] < 1 || $data['age'] > 120) $errors[] = 'Âge invalide.';

    if (empty($errors)) {
        $pdo = getDB();

        // Vérifier unicité
        $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE login = ? OR email = ?");
        $stmt->execute([$data['login'], $data['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'Ce login ou cet email est déjà utilisé.';
        } else {
            $token  = generateToken();
            $hash   = password_hash($data['mdp'], PASSWORD_BCRYPT);

            $pdo->prepare("INSERT INTO utilisateurs
                (login, email, mot_de_passe, nom, prenom, age, sexe, date_naissance, type_membre, token_validation, statut)
                VALUES (?,?,?,?,?,?,?,?,?,?,'en_attente')")
                ->execute([
                    $data['login'], $data['email'], $hash,
                    $data['nom'], $data['prenom'], $data['age'],
                    $data['sexe'], $data['dob'], $data['type_membre'], $token
                ]);

            // Envoyer email de validation
            sendValidationEmail($data['email'], $token, $data['prenom']);

            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inscription — SmartHome</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/css/style.css">
</head>
<body>
<div class="auth-wrapper" style="padding:60px 20px">
  <div class="auth-box" style="max-width:540px">
    <div class="auth-logo">
      <div class="logo-icon">🏠</div>
      <div class="logo-text">Smart<span>Home</span></div>
    </div>

    <?php if ($success): ?>
    <div style="text-align:center;padding:20px 0">
      <div style="font-size:48px;margin-bottom:16px">✉️</div>
      <h2 style="font-size:22px;font-weight:700;margin-bottom:10px">Vérifiez votre email !</h2>
      <p style="color:var(--text-secondary);margin-bottom:24px">
        Un email de validation a été envoyé à votre adresse.<br>
        Cliquez sur le lien pour activer votre compte.<br>
        <small style="color:var(--text-muted)">(En local, consultez les logs PHP ou activez directement en BDD)</small>
      </p>
      <a href="<?= SITE_URL ?>/login.php" class="btn btn-primary">Aller à la connexion</a>
    </div>
    <?php else: ?>

    <h2 class="auth-title">Créer un compte</h2>
    <p class="auth-sub">Créer un compte</p>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <div>
        <?php foreach ($errors as $e): ?>
          <div>• <?= sanitize($e) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Prénom *</label>
          <input type="text" name="prenom" class="form-control" value="<?= sanitize($_POST['prenom'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Nom *</label>
          <input type="text" name="nom" class="form-control" value="<?= sanitize($_POST['nom'] ?? '') ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Login (pseudonyme) *</label>
        <input type="text" name="login" class="form-control" placeholder="ex: lucas_martin" value="<?= sanitize($_POST['login'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Email *</label>
        <input type="email" name="email" class="form-control" placeholder="vous@exemple.com" value="<?= sanitize($_POST['email'] ?? '') ?>" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Mot de passe *</label>
          <input type="password" name="mot_de_passe" class="form-control" placeholder="≥ 8 caractères" required>
        </div>
        <div class="form-group">
          <label class="form-label">Confirmer *</label>
          <input type="password" name="mdp_confirm" class="form-control" placeholder="••••••••" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Date de naissance</label>
          <input type="date" name="date_naissance" class="form-control" value="<?= sanitize($_POST['date_naissance'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Âge *</label>
          <input type="number" name="age" class="form-control" min="1" max="120" value="<?= sanitize($_POST['age'] ?? '') ?>" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Sexe / Genre</label>
          <select name="sexe" class="form-control">
            <option value="">-- Choisir --</option>
            <option value="homme" <?= ($_POST['sexe'] ?? '') === 'homme' ? 'selected' : '' ?>>Homme</option>
            <option value="femme" <?= ($_POST['sexe'] ?? '') === 'femme' ? 'selected' : '' ?>>Femme</option>
            <option value="autre" <?= ($_POST['sexe'] ?? '') === 'autre' ? 'selected' : '' ?>>Autre</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Rôle dans la maison *</label>
          <select name="type_membre" class="form-control" required>
            <option value="">-- Choisir --</option>
            <option value="père" <?= ($_POST['type_membre'] ?? '') === 'père' ? 'selected' : '' ?>>Père</option>
            <option value="mère" <?= ($_POST['type_membre'] ?? '') === 'mère' ? 'selected' : '' ?>>Mère</option>
            <option value="enfant" <?= ($_POST['type_membre'] ?? '') === 'enfant' ? 'selected' : '' ?>>Enfant</option>
            <option value="grand-parent" <?= ($_POST['type_membre'] ?? '') === 'grand-parent' ? 'selected' : '' ?>>Grand-parent</option>
            <option value="autre" <?= ($_POST['type_membre'] ?? '') === 'autre' ? 'selected' : '' ?>>Autre</option>
          </select>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
        Créer mon compte
      </button>
    </form>

    <div class="divider"></div>
    <p style="text-align:center;font-size:13px;color:var(--text-secondary)">
      Déjà membre ? <a href="<?= SITE_URL ?>/login.php">Se connecter</a>
    </p>

    <?php endif; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
