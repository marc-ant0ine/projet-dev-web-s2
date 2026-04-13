<?php
// ============================================================
//  admin_action.php — MaisonSmart
//  Gère les actions POST du module Administration
// ============================================================
session_start();
require_once 'db.php';

// Seul l'admin peut accéder
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit();
}

$pdo    = getDB();
$action = $_POST['action'] ?? '';

switch ($action) {

    case 'change_role':
        $userId = (int) ($_POST['user_id'] ?? 0);
        $role   = $_POST['role'] ?? '';
        $roles  = ['simple', 'complexe', 'admin'];
        if ($userId && in_array($role, $roles)) {
            $stmt = $pdo->prepare('UPDATE utilisateurs SET role = ? WHERE id = ?');
            $stmt->execute([$role, $userId]);
        }
        break;

    case 'toggle_ban':
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId && $userId !== (int) $_SESSION['user_id']) { // ne peut pas se bannir soi-même
            $stmt = $pdo->prepare(
                "UPDATE utilisateurs
                 SET statut = IF(statut = 'actif', 'banni', 'actif')
                 WHERE id = ?"
            );
            $stmt->execute([$userId]);
        }
        break;

    case 'toggle_objet':
        $objetId = (int) ($_POST['objet_id'] ?? 0);
        if ($objetId) {
            $stmt = $pdo->prepare('UPDATE objets_connectes SET actif = IF(actif=1, 0, 1) WHERE id = ?');
            $stmt->execute([$objetId]);
        }
        break;

    case 'add_objet':
        $nom     = trim($_POST['nom']     ?? '');
        $type    = $_POST['type']         ?? '';
        $pieceId = (int) ($_POST['piece_id'] ?? 0) ?: null;
        $unite   = trim($_POST['unite']   ?? '');
        $types   = ['securite','energie','confort','electromenager'];
        if ($nom && in_array($type, $types)) {
            $stmt = $pdo->prepare(
                'INSERT INTO objets_connectes (nom, type, piece_id, unite, actif) VALUES (?,?,?,?,1)'
            );
            $stmt->execute([$nom, $type, $pieceId, $unite]);
        }
        break;
}

header('Location: index.php');
exit();
