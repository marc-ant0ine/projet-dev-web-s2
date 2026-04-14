<?php

session_start();
require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom       = trim($_POST['nom']    ?? '');
    $prenom    = trim($_POST['prenom'] ?? '');
    $mail      = filter_var(trim($_POST['mail'] ?? ''), FILTER_VALIDATE_EMAIL);
    $mdpBrut   = $_POST['mdp'] ?? '';
    $naissance = $_POST['naissance'] ?? '';
    $role      = 'simple';

    if (empty($nom) || empty($prenom) || !$mail || empty($mdpBrut) || empty($naissance)) {
        $error = 'Veuillez remplir tous les champs correctement.';
    } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $mdpBrut)) {
        $error = 'Le mot de passe doit contenir au moins 8 caractères, une lettre et un chiffre.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT id FROM utilisateurs WHERE mail = ?');
        $stmt->execute([$mail]);
        if ($stmt->fetch()) {
            $error = 'Cette adresse email est déjà utilisée.';
        } else {
            $mdpHash = password_hash($mdpBrut, PASSWORD_BCRYPT);
            $ins = $pdo->prepare(
                "INSERT INTO utilisateurs (nom, prenom, mail, mdp, naissance, role, statut)
                 VALUES (?, ?, ?, ?, ?, ?, 'actif')"
            );
            $ins->execute([$nom, $prenom, $mail, $mdpHash, $naissance, $role]);

            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['nom']     = $nom;
            $_SESSION['prenom']  = $prenom;
            $_SESSION['mail']    = $mail;
            $_SESSION['role']    = $role;
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
    <title>Inscription — MaisonSmart</title>
   
    <link rel="stylesheet" href="style/main.css">
    <link rel="stylesheet" href="style/auth.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<main class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Rejoindre <span class="brand">MaisonSmart</span></h1>
            <p>Créez votre compte habitant</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" autocomplete="off">
            <div class="form-row">
                <div class="form-group">
                    <label for="nom">Nom <span class="req">*</span></label>
                    <input type="text" id="nom" name="nom" required
                           value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="prenom">Prénom <span class="req">*</span></label>
                    <input type="text" id="prenom" name="prenom" required
                           value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="naissance">Date de naissance <span class="req">*</span></label>
                <input type="date" id="naissance" name="naissance" required
                       value="<?= htmlspecialchars($_POST['naissance'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="mail">Adresse email <span class="req">*</span></label>
                <input type="email" id="mail" name="mail" required
                       value="<?= htmlspecialchars($_POST['mail'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="mdp">Mot de passe <span class="req">*</span></label>
                <input type="password" id="mdp" name="mdp" required minlength="8"
                       pattern="(?=.*[A-Za-z])(?=.*\d).{8,}"
                       title="8 caractères minimum, une lettre et un chiffre">
                <small class="hint">8 caractères minimum, incluant une lettre et un chiffre.</small>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Créer mon compte</button>
        </form>
        <p class="auth-switch">Déjà un compte ? <a href="login.php">Se connecter</a></p>
    </div>
</main>

<?php include 'footer.php'; ?>
</body>
</html>


<?php include 'footer.php'; ?>
</body>
</html>
