<?php
// ============================================================
//  login.php — MaisonSmart
// ============================================================
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mail    = trim($_POST['mail'] ?? '');
    $mdpBrut = $_POST['mdp'] ?? '';

    if (empty($mail) || empty($mdpBrut)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE mail = ?');
        $stmt->execute([$mail]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($mdpBrut, $user['mdp'])) {
            $error = 'Email ou mot de passe incorrect.';
        } elseif ($user['statut'] === 'banni') {
            $error = 'Votre compte a été suspendu. Contactez l\'administrateur.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nom']     = $user['nom'];
            $_SESSION['prenom']  = $user['prenom'];
            $_SESSION['mail']    = $user['mail'];
            $_SESSION['role']    = $user['role'];
            header('Location: index.php');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — MaisonSmart</title>
    <link rel="stylesheet" href="../style/main.css">
    <link rel="stylesheet" href="../style/auth.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<main class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Bienvenue sur <span class="brand">MaisonSmart</span></h1>
            <p>Connectez-vous à votre maison intelligente</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <div class="form-group">
                <label for="mail">Email</label>
                <input type="email" id="mail" name="mail" required
                       value="<?= htmlspecialchars($_POST['mail'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="mdp">Mot de passe</label>
                <input type="password" id="mdp" name="mdp" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Se connecter</button>
        </form>

        <div class="demo-accounts">
            <p class="hint-title">Comptes de démonstration (mdp : <code>Motdepasse1</code>)</p>
            <div class="demo-grid">
                <span>jean@maison.fr</span><span class="role-pill admin">admin</span>
                <span>marie@maison.fr</span><span class="role-pill complexe">habitant+</span>
                <span>theo@maison.fr</span><span class="role-pill simple">habitant</span>
            </div>
        </div>

        <p class="auth-switch">Pas encore de compte ? <a href="inscription.php">S'inscrire</a></p>
    </div>
</main>

<?php include 'footer.php'; ?>
</body>
</html>
