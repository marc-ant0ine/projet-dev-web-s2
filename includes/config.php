<?php
// Configuration

define('DB_HOST', 'localhost');
define('DB_NAME', 'smarthome_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', 'SmartHome');
define('SITE_URL', 'http://localhost/smart-home');
define('MAIL_FROM', 'noreply@smarthome.local');
define('MAIL_FROM_NAME', 'SmartHome Platform');

// Système de points
define('POINTS_CONNEXION', 0.25);
define('POINTS_CONSULTATION', 0.50);
define('POINTS_MODIFICATION', 1.00);
define('POINTS_AJOUT', 1.50);

// Seuils de niveaux
define('POINTS_INTERMEDIAIRE', 5.0);
define('POINTS_AVANCE', 15.0);
define('POINTS_EXPERT', 30.0);

// Durée de session (secondes)
define('SESSION_DURATION', 3600);

// connexion bdd
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Connexion base de données impossible : ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// helpers
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect(SITE_URL . '/login.php');
    }
}

function requireLevel(array $levels): void {
    requireLogin();
    if (!in_array($_SESSION['user_niveau'], $levels)) {
        redirect(SITE_URL . '/dashboard.php?error=access_denied');
    }
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function getNiveauLabel(string $niveau): string {
    $labels = [
        'débutant'     => 'Débutant',
        'intermédiaire'=> 'Intermédiaire',
        'avancé'       => 'Avancé',
        'expert'       => 'Expert',
    ];
    return $labels[$niveau] ?? $niveau;
}

function getNiveauPoints(string $niveau): float {
    return match($niveau) {
        'débutant'      => 0,
        'intermédiaire' => POINTS_INTERMEDIAIRE,
        'avancé'        => POINTS_AVANCE,
        'expert'        => POINTS_EXPERT,
        default         => 0,
    };
}

function getNextNiveau(string $niveau): ?string {
    return match($niveau) {
        'débutant'      => 'intermédiaire',
        'intermédiaire' => 'avancé',
        'avancé'        => 'expert',
        default         => null,
    };
}

function canUpgradeNiveau(array $user): bool {
    $next = getNextNiveau($user['niveau']);
    if (!$next) return false;
    $needed = getNiveauPoints($next);
    return $user['points'] >= $needed;
}

// Ajouter des points et mettre à jour le niveau si besoin
function addPoints(int $userId, float $points, string $typeAction, ?int $objetId = null, string $desc = ''): void {
    $pdo = getDB();

    // Mettre à jour les points
    $pdo->prepare("UPDATE utilisateurs SET points = points + ?, nb_actions = nb_actions + 1 WHERE id = ?")
        ->execute([$points, $userId]);

    // Enregistrer l'action
    $pdo->prepare("INSERT INTO actions_utilisateurs (utilisateur_id, type_action, objet_id, description, points_gagnes) VALUES (?,?,?,?,?)")
        ->execute([$userId, $typeAction, $objetId, $desc, $points]);

    // Vérifier upgrade automatique
    $stmt = $pdo->prepare("SELECT points, niveau FROM utilisateurs WHERE id = ?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    if ($u) {
        $newNiveau = $u['niveau'];
        if ($u['points'] >= POINTS_EXPERT)        $newNiveau = 'expert';
        elseif ($u['points'] >= POINTS_AVANCE)    $newNiveau = 'avancé';
        elseif ($u['points'] >= POINTS_INTERMEDIAIRE) $newNiveau = 'intermédiaire';

        if ($newNiveau !== $u['niveau']) {
            $pdo->prepare("UPDATE utilisateurs SET niveau = ? WHERE id = ?")->execute([$newNiveau, $userId]);
            $_SESSION['user_niveau'] = $newNiveau;
        }
    }
}

function logConnexion(int $userId): void {
    $pdo = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'inconnue';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $pdo->prepare("INSERT INTO historique_connexions (utilisateur_id, ip_adresse, user_agent, points_gagnes) VALUES (?,?,?,?)")
        ->execute([$userId, $ip, $ua, POINTS_CONNEXION]);
    $pdo->prepare("UPDATE utilisateurs SET nb_connexions = nb_connexions + 1, derniere_connexion = NOW(), points = points + ? WHERE id = ?")
        ->execute([POINTS_CONNEXION, $userId]);
}

function sendValidationEmail(string $email, string $token, string $prenom): bool {
    require_once __DIR__ . '/PHPMailer.php';
    require_once __DIR__ . '/SMTP.php';
    require_once __DIR__ . '/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Connexion via Gmail
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'adem.gluntz@gmail.com';   // ton email Gmail
        $mail->Password   = 'eynf ccyh hsar rano';     // le mot de passe d'application (avec les espaces)
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Expéditeur et destinataire
        $mail->setFrom('adem.gluntz@gmail.com', 'SmartHome');
        $mail->addAddress($email, $prenom);

        // Contenu
        $lien = SITE_URL . '/valider-inscription.php?token=' . urlencode($token);

        $mail->isHTML(true);
        $mail->Subject = 'Validez votre inscription — SmartHome';
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif;max-width:500px;margin:0 auto;padding:20px'>
                <h2 style='color:#58d8ff'>🏠 SmartHome</h2>
                <p>Bonjour <strong>{$prenom}</strong>,</p>
                <p>Merci de vous être inscrit sur la plateforme SmartHome !</p>
                <p>Cliquez sur le bouton ci-dessous pour activer votre compte :</p>
                <a href='{$lien}'
                   style='display:inline-block;padding:12px 24px;background:#58d8ff;
                          color:#0d1117;border-radius:8px;text-decoration:none;
                          font-weight:bold;margin:16px 0'>
                    ✅ Activer mon compte
                </a>
                <p style='color:#888;font-size:12px;margin-top:16px'>
                    Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
                    <a href='{$lien}'>{$lien}</a>
                </p>
                <hr style='border:none;border-top:1px solid #eee;margin:20px 0'>
                <p style='color:#aaa;font-size:11px'>SmartHome — Projet ING1 2025-2026</p>
            </div>
        ";
        $mail->AltBody = "Bonjour {$prenom},\n\nActivez votre compte SmartHome ici :\n{$lien}";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Erreur envoi email : ' . $mail->ErrorInfo);
        return false;
    }
}

// Générer un token unique
function generateToken(): string {
    return bin2hex(random_bytes(32));
}

// Démarrer la session de façon sécurisée
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_DURATION,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// Flasher un message
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
