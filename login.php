<?php
require_once 'includes/config.php';
startSession();

if (isLoggedIn()) redirect(SITE_URL . '/dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = sanitize($_POST['login'] ?? '');
    $mdp   = $_POST['mot_de_passe'] ?? '';

    if (empty($login) || empty($mdp)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE (login = ? OR email = ?) AND statut = 'actif'");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($mdp, $user['mot_de_passe'])) {
            // Démarrer la session
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user_login']  = $user['login'];
            $_SESSION['user_niveau'] = $user['niveau'];

            // Logger la connexion + points
            logConnexion($user['id']);

            setFlash('success', 'Bienvenue ' . $user['prenom'] . ' ! 👋');
            redirect(SITE_URL . '/dashboard.php');
        } elseif ($user === false) {
            // Vérifier si le compte est en attente
            $stmt2 = $pdo->prepare("SELECT statut FROM utilisateurs WHERE login = ? OR email = ?");
            $stmt2->execute([$login, $login]);
            $check = $stmt2->fetch();
            if ($check && $check['statut'] === 'en_attente') {
                $error = 'Votre compte est en attente de validation. Vérifiez votre email.';
            } else {
                $error = 'Identifiants incorrects.';
            }
        } else {
            $error = 'Identifiants incorrects.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — SmartHome</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/css/style.css">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-box">
    <div class="auth-logo">
      <div class="logo-icon">🏠</div>
      <div class="logo-text">Smart<span>Home</span></div>
    </div>

    <h2 class="auth-title">Connexion</h2>
    <p class="auth-sub">Connectez-vous à votre compte</p>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label class="form-label">Login ou Email</label>
        <input type="text" name="login" class="form-control" placeholder="votre_login"
               value="<?= sanitize($_POST['login'] ?? '') ?>" required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label">Mot de passe</label>
        <input type="password" name="mot_de_passe" class="form-control" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
        Se connecter
      </button>
    </form>

    <div class="divider"></div>
    <p style="text-align:center;font-size:13px;color:var(--text-secondary)">
      Pas encore membre ? <a href="<?= SITE_URL ?>/inscription.php">S'inscrire</a>
      · <a href="<?= SITE_URL ?>/index.php">Accueil public</a>
    </p>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
