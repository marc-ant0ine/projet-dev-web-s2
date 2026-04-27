<?php
require_once '../includes/config.php';
startSession();
requireLevel(['avancé','expert']);

$id  = (int)($_GET['id'] ?? 0);
$pdo = getDB();

if (!$id) {
    // Exporter tous les objets si pas d'ID rapport
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="objets_connectes_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM UTF-8
    fputcsv($out, ['ID','Nom','Marque','Modèle','Catégorie','Pièce','État','Connectivité','Signal','Batterie','Firmware','IP','MAC','Installé le']);

    $objets = $pdo->query("
        SELECT o.id_unique,o.nom,o.marque,o.modele,c.nom AS cat,p.nom AS piece,o.etat,o.type_connectivite,o.force_signal,o.batterie,o.firmware,o.ip_locale,o.mac_address,o.date_installation
        FROM objets_connectes o
        LEFT JOIN categories_objets c ON o.categorie_id=c.id
        LEFT JOIN pieces p ON o.piece_id=p.id
        ORDER BY o.nom
    ")->fetchAll();

    foreach ($objets as $obj) {
        fputcsv($out, $obj);
    }
    fclose($out);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM rapports WHERE id=?");
$stmt->execute([$id]); $rapport = $stmt->fetch();
if (!$rapport) redirect(SITE_URL . '/gestion/rapports.php');

$data = json_decode($rapport['contenu_json'], true);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="rapport_' . date('Ymd', strtotime($rapport['date_creation'])) . '.csv"');
$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF");

fputcsv($out, ['Rapport : ' . $rapport['titre']]);
fputcsv($out, ['Période : ' . $rapport['periode_debut'] . ' → ' . $rapport['periode_fin']]);
fputcsv($out, ['Généré le : ' . $rapport['date_creation']]);
fputcsv($out, []);

if (isset($data['energie']) && !empty($data['energie'])) {
    fputcsv($out, ['=== ÉNERGIE ===']);
    fputcsv($out, ['Date','Objet','Valeur (kWh)']);
    foreach ($data['energie'] as $row) {
        fputcsv($out, [$row['jour'], $row['objet'], $row['valeur']]);
    }
    fputcsv($out, []);
}

if (isset($data['activite_users']) && !empty($data['activite_users'])) {
    fputcsv($out, ['=== ACTIVITÉ MEMBRES ===']);
    fputcsv($out, ['Membre','Nb actions','Points gagnés']);
    foreach ($data['activite_users'] as $row) {
        fputcsv($out, [$row['login'], $row['nb_actions'], $row['pts']]);
    }
    fputcsv($out, []);
}

if (isset($data['maintenance']) && !empty($data['maintenance'])) {
    fputcsv($out, ['=== MAINTENANCE ===']);
    fputcsv($out, ['Objet','État','Batterie','Catégorie','Pièce']);
    foreach ($data['maintenance'] as $row) {
        fputcsv($out, [$row['nom'], $row['etat'], $row['batterie'].'%', $row['categorie'], $row['piece']]);
    }
}

fclose($out);
exit;
