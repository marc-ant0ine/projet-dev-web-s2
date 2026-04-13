<?php

$role   = $_SESSION['role']   ?? 'visiteur';
$prenom = $_SESSION['prenom'] ?? null;
?>
<header class="site-header">
    <div class="header-inner">
        <a href="index.php" class="site-logo">Maison<span>Smart</span></a>
        <nav class="site-nav">
            <a href="index.php" class="nav-link">Accueil</a>
            <?php if ($prenom): ?>
                <span class="nav-user">Bonjour, <?= htmlspecialchars($prenom) ?> 
                    <span class="role-pill <?= $role ?>"><?= $role ?></span>
                </span>
                <a href="logout.php" class="btn btn-outline btn-sm">Déconnexion</a>
            <?php else: ?>
                <a href="login.php" class="nav-link">Connexion</a>
                <a href="inscription.php" class="btn btn-primary btn-sm">S'inscrire</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
