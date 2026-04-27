<?php
require_once 'includes/config.php';
startSession();

$token = sanitize($_GET['token'] ?? '');
$msg   = '';
$type  = 'error';

if (empty($token)) {
    $msg = 'Token manquant.';
} else {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE token_validation = ? AND statut = 'en_attente'");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $pdo->prepare("UPDATE utilisateurs SET statut='actif', token_validation=NULL WHERE id=?")
            ->execute([$user['id']]);
        $msg  = 'Votre compte est activé ! Vous pouvez maintenant vous connecter.';
        $type = 'success';
    } else {
        $msg = 'Token invalide ou déjà utilisé.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Validation inscription — SmartHome</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/css/style.css">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-box" style="text-align:center">
    <div style="font-size:48px;margin-bottom:16px"><?= $type === 'success' ? '✅' : '❌' ?></div>
    <h2 style="font-size:22px;font-weight:700;margin-bottom:10px">
      <?= $type === 'success' ? 'Compte activé !' : 'Erreur de validation' ?>
    </h2>
    <p style="color:var(--text-secondary);margin-bottom:24px"><?= sanitize($msg) ?></p>
    <a href="<?= SITE_URL ?>/<?= $type === 'success' ? 'login.php' : 'inscription.php' ?>" class="btn btn-primary">
      <?= $type === 'success' ? 'Se connecter' : 'Retour à l\'inscription' ?>
    </a>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
