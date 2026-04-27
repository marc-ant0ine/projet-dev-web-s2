<?php
require_once '../includes/config.php';
startSession();
requireLevel(['avancé','expert']);

$id     = (int)($_POST['id'] ?? 0);
$action = sanitize($_POST['action'] ?? '');

if ($id && in_array($action, ['approuver','refuser'])) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT d.*,o.nom AS objet_nom FROM demandes_suppression d JOIN objets_connectes o ON d.objet_id=o.id WHERE d.id=?");
    $stmt->execute([$id]); $demande = $stmt->fetch();

    if ($demande) {
        if ($action === 'approuver') {
            $pdo->prepare("DELETE FROM objets_connectes WHERE id=?")->execute([$demande['objet_id']]);
            $pdo->prepare("UPDATE demandes_suppression SET statut='approuvée',date_traitement=NOW() WHERE id=?")->execute([$id]);
            setFlash('success', 'Objet "'.sanitize($demande['objet_nom']).'" supprimé.');
        } else {
            $pdo->prepare("UPDATE demandes_suppression SET statut='refusée',date_traitement=NOW() WHERE id=?")->execute([$id]);
            setFlash('info', 'Demande refusée.');
        }
    }
}

redirect(SITE_URL . '/gestion/dashboard.php');
