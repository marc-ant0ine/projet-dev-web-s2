<?php
require_once '../includes/config.php';
startSession();
requireLevel(['avancé','expert']);

$objetId = (int)($_POST['objet_id'] ?? 0);
$motif   = sanitize($_POST['motif'] ?? '');

if ($objetId) {
    $pdo = getDB();
    // Vérifier que l'objet existe et qu'il n'y a pas déjà une demande en attente
    $stmt = $pdo->prepare("SELECT id,nom FROM objets_connectes WHERE id=?");
    $stmt->execute([$objetId]); $obj = $stmt->fetch();

    if ($obj) {
        $dup = $pdo->prepare("SELECT id FROM demandes_suppression WHERE objet_id=? AND statut='en_attente'");
        $dup->execute([$objetId]);
        if ($dup->fetch()) {
            setFlash('warning', 'Une demande de suppression est déjà en attente pour cet objet.');
        } else {
            $pdo->prepare("INSERT INTO demandes_suppression (demandeur_id,objet_id,motif) VALUES (?,?,?)")
                ->execute([$user['id'], $objetId, $motif]);
            setFlash('success', 'Demande de suppression envoyée pour "'.$obj['nom'].'".');
        }
    }
}

redirect(SITE_URL . '/objet-detail.php?id=' . $objetId);
