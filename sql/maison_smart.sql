

CREATE DATABASE IF NOT EXISTS maison_smart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE maison_smart;


CREATE TABLE IF NOT EXISTS utilisateurs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(100) NOT NULL,
    prenom      VARCHAR(100) NOT NULL,
    mail        VARCHAR(150) NOT NULL UNIQUE,
    mdp         VARCHAR(255) NOT NULL,
    naissance   DATE,
    role        ENUM('visiteur','simple','complexe','admin') DEFAULT 'simple',
    statut      ENUM('actif','banni') DEFAULT 'actif',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS pieces (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    nom     VARCHAR(100) NOT NULL,
    etage   TINYINT DEFAULT 0
);


CREATE TABLE IF NOT EXISTS objets_connectes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(150) NOT NULL,
    type        ENUM('securite','energie','confort','electromenager') NOT NULL,
    piece_id    INT,
    unite       VARCHAR(20),
    actif       TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (piece_id) REFERENCES pieces(id) ON DELETE SET NULL
);


CREATE TABLE IF NOT EXISTS donnees_capteurs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    objet_id    INT NOT NULL,
    valeur      DECIMAL(10,2) NOT NULL,
    statut      ENUM('ok','warn','alert') DEFAULT 'ok',
    enregistre_le DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (objet_id) REFERENCES objets_connectes(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS consommation (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    objet_id    INT NOT NULL,
    type_conso  ENUM('energie','eau') NOT NULL,
    valeur      DECIMAL(8,3) NOT NULL,
    jour        DATE NOT NULL,
    FOREIGN KEY (objet_id) REFERENCES objets_connectes(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS acces (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    personne    VARCHAR(150) NOT NULL,
    porte       VARCHAR(100) NOT NULL,
    statut      ENUM('autorise','alerte') DEFAULT 'autorise',
    enregistre_le DATETIME DEFAULT CURRENT_TIMESTAMP
);


INSERT INTO pieces (nom, etage) VALUES
('Salon', 0), ('Cuisine', 0), ('Garage', 0),
('Chambre principale', 1), ('Chambre enfant', 1), ('Salle de bain', 1);


INSERT INTO objets_connectes (nom, type, piece_id, unite) VALUES
('Thermostat salon',        'confort',        1, '°C'),
('Capteur CO₂ salon',       'securite',       1, 'ppm'),
('Aspirateur robot',        'electromenager', 1, '%'),
('Éclairage LED salon',     'confort',        1, '%'),
('Réfrigérateur',           'electromenager', 2, '°C'),
('Lave-vaisselle',          'electromenager', 2, '°C'),
('Détecteur fumée cuisine', 'securite',       2, 'ppm'),
('Caméra entrée garage',    'securite',       3, 'actif'),
('Compteur énergie',        'energie',        3, 'kWh'),
('Volets chambre',          'confort',        4, '%'),
('Éclairage LED chambre',   'confort',        4, '%'),
('Machine à laver',         'electromenager', 6, '°C'),
('Capteur fuite eau',       'securite',       6, '%'),
('Alarme intrusion',        'securite',       1, 'actif');


INSERT INTO donnees_capteurs (objet_id, valeur, statut, enregistre_le) VALUES
(1, 21.5, 'ok',    NOW() - INTERVAL 0 DAY),
(1, 20.8, 'ok',    NOW() - INTERVAL 1 DAY),
(1, 22.1, 'ok',    NOW() - INTERVAL 2 DAY),
(2, 870,  'warn',  NOW() - INTERVAL 0 DAY),
(2, 650,  'ok',    NOW() - INTERVAL 1 DAY),
(3, 78,   'ok',    NOW()),
(5, 4.2,  'ok',    NOW()),
(6, 55,   'ok',    NOW()),
(7, 0,    'ok',    NOW()),
(9, 38.4, 'ok',    NOW()),
(12, 60,  'warn',  NOW()),
(13, 99,  'alert', NOW()),
(14, 1,   'ok',    NOW());


INSERT INTO consommation (objet_id, type_conso, valeur, jour) VALUES
(9, 'energie', 4.2,  CURDATE() - INTERVAL 6 DAY),
(9, 'energie', 3.8,  CURDATE() - INTERVAL 5 DAY),
(9, 'energie', 5.1,  CURDATE() - INTERVAL 4 DAY),
(9, 'energie', 4.6,  CURDATE() - INTERVAL 3 DAY),
(9, 'energie', 6.2,  CURDATE() - INTERVAL 2 DAY),
(9, 'energie', 7.4,  CURDATE() - INTERVAL 1 DAY),
(9, 'energie', 3.9,  CURDATE()),
(12,'eau',     35.0, CURDATE() - INTERVAL 6 DAY),
(12,'eau',     28.5, CURDATE() - INTERVAL 5 DAY),
(12,'eau',     42.0, CURDATE() - INTERVAL 4 DAY),
(12,'eau',     31.0, CURDATE() - INTERVAL 3 DAY),
(12,'eau',     38.0, CURDATE() - INTERVAL 2 DAY),
(12,'eau',     25.0, CURDATE() - INTERVAL 1 DAY),
(12,'eau',     10.5, CURDATE());


INSERT INTO acces (personne, porte, statut, enregistre_le) VALUES
('Jean Dupont',      'Entrée principale', 'autorise', NOW() - INTERVAL 8  HOUR),
('Marie Dupont',     'Entrée principale', 'autorise', NOW() - INTERVAL 3  HOUR),
('Inconnu',          'Garage',            'alerte',   NOW() - INTERVAL 22 HOUR),
('Livreur',          'Portail',           'autorise', NOW() - INTERVAL 5  HOUR);


INSERT INTO utilisateurs (nom, prenom, mail, mdp, naissance, role, statut) VALUES
('Dupont', 'Jean',   'jean@maison.fr',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '1985-03-12', 'admin',    'actif'),
('Dupont', 'Marie',  'marie@maison.fr',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '1988-07-24', 'complexe', 'actif'),
('Dupont', 'Théo',   'theo@maison.fr',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2010-11-05', 'simple',   'actif'),
('Temp',   'Invité', 'invite@tmp.fr',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2000-01-01', 'simple',   'banni');

