<?php
require_once '../includes/config.php';
startSession();
requireLevel(['avancé','expert']);

$id   = (int)($_POST['id'] ?? 0);
$etat = sanitize($_POST['etat'] ?? '');
$allowed = ['actif','inactif','maintenance','erreur'];

if ($id && in_array($etat, $allowed)) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT nom FROM objets_connectes WHERE id=?");
    $stmt->execute([$id]); $obj = $stmt->fetch();

    if ($obj) {
        $pdo->prepare("UPDATE objets_connectes SET etat=?,derniere_interaction=NOW() WHERE id=?")
            ->execute([$etat, $id]);
        addPoints($user['id'], POINTS_MODIFICATION, 'modification_objet', $id, 'Changement état → '.$etat.' : '.$obj['nom']);
        setFlash('success', 'État de "'.sanitize($obj['nom']).'" mis à jour : '.$etat);
    }
}

redirect(SITE_URL . '/objet-detail.php?id=' . $id);
