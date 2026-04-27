-- =====================================================
-- SMART HOME PLATFORM - Base de Données
-- =====================================================

CREATE DATABASE IF NOT EXISTS smarthome_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smarthome_db;

-- =====================================================
-- TABLE: utilisateurs
-- =====================================================
CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    nom VARCHAR(100),
    prenom VARCHAR(100),
    age INT,
    sexe ENUM('homme','femme','autre'),
    date_naissance DATE,
    type_membre ENUM('père','mère','enfant','grand-parent','autre') DEFAULT 'autre',
    photo VARCHAR(255) DEFAULT 'default.png',
    niveau ENUM('débutant','intermédiaire','avancé','expert') DEFAULT 'débutant',
    points DECIMAL(8,2) DEFAULT 0.00,
    nb_connexions INT DEFAULT 0,
    nb_actions INT DEFAULT 0,
    statut ENUM('en_attente','actif','suspendu') DEFAULT 'en_attente',
    token_validation VARCHAR(255),
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion DATETIME,
    INDEX idx_login (login),
    INDEX idx_email (email),
    INDEX idx_niveau (niveau),
    INDEX idx_statut (statut)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: categories_objets
-- =====================================================
CREATE TABLE categories_objets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    icone VARCHAR(50) DEFAULT 'device_hub',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: pieces
-- =====================================================
CREATE TABLE pieces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    etage INT DEFAULT 0,
    superficie DECIMAL(6,2),
    description TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: objets_connectes
-- =====================================================
CREATE TABLE objets_connectes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_unique VARCHAR(50) UNIQUE NOT NULL,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    marque VARCHAR(100),
    modele VARCHAR(100),
    type_connectivite ENUM('Wi-Fi','Bluetooth','Zigbee','Z-Wave','Ethernet','NFC') DEFAULT 'Wi-Fi',
    force_signal VARCHAR(20) DEFAULT 'fort',
    etat ENUM('actif','inactif','maintenance','erreur') DEFAULT 'actif',
    categorie_id INT,
    piece_id INT,
    batterie INT DEFAULT 100,
    firmware VARCHAR(50),
    ip_locale VARCHAR(20),
    mac_address VARCHAR(20),
    date_installation DATETIME DEFAULT CURRENT_TIMESTAMP,
    derniere_interaction DATETIME DEFAULT CURRENT_TIMESTAMP,
    ajoute_par INT,
    FOREIGN KEY (categorie_id) REFERENCES categories_objets(id) ON DELETE SET NULL,
    FOREIGN KEY (piece_id) REFERENCES pieces(id) ON DELETE SET NULL,
    FOREIGN KEY (ajoute_par) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_etat (etat),
    INDEX idx_categorie (categorie_id),
    INDEX idx_piece (piece_id)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: attributs_objets (valeurs dynamiques)
-- =====================================================
CREATE TABLE attributs_objets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    objet_id INT NOT NULL,
    cle VARCHAR(100) NOT NULL,
    valeur VARCHAR(500),
    unite VARCHAR(50),
    type_attribut ENUM('capteur','energie','connectivite','usage','configuration') DEFAULT 'capteur',
    mise_a_jour DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (objet_id) REFERENCES objets_connectes(id) ON DELETE CASCADE,
    INDEX idx_objet (objet_id)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: historique_donnees
-- =====================================================
CREATE TABLE historique_donnees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    objet_id INT NOT NULL,
    cle VARCHAR(100) NOT NULL,
    valeur VARCHAR(500),
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (objet_id) REFERENCES objets_connectes(id) ON DELETE CASCADE,
    INDEX idx_objet_time (objet_id, timestamp)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: historique_connexions
-- =====================================================
CREATE TABLE historique_connexions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    ip_adresse VARCHAR(50),
    user_agent VARCHAR(500),
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    actions_session INT DEFAULT 0,
    points_gagnes DECIMAL(5,2) DEFAULT 0,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_user_time (utilisateur_id, timestamp)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: actions_utilisateurs
-- =====================================================
CREATE TABLE actions_utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    type_action ENUM('consultation_objet','modification_objet','ajout_objet','suppression_objet','consultation_service','configuration') NOT NULL,
    objet_id INT,
    description TEXT,
    points_gagnes DECIMAL(5,2) DEFAULT 0,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (objet_id) REFERENCES objets_connectes(id) ON DELETE SET NULL,
    INDEX idx_user_time (utilisateur_id, timestamp)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: demandes_suppression (complexe → admin)
-- =====================================================
CREATE TABLE demandes_suppression (
    id INT AUTO_INCREMENT PRIMARY KEY,
    demandeur_id INT NOT NULL,
    objet_id INT NOT NULL,
    motif TEXT,
    statut ENUM('en_attente','approuvée','refusée') DEFAULT 'en_attente',
    date_demande DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_traitement DATETIME,
    FOREIGN KEY (demandeur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (objet_id) REFERENCES objets_connectes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: actualites
-- =====================================================
CREATE TABLE actualites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(200) NOT NULL,
    contenu TEXT NOT NULL,
    auteur_id INT,
    image VARCHAR(255),
    date_publication DATETIME DEFAULT CURRENT_TIMESTAMP,
    visible TINYINT(1) DEFAULT 1,
    FOREIGN KEY (auteur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: rapports
-- =====================================================
CREATE TABLE rapports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(200),
    type_rapport ENUM('energie','usage','maintenance','global') DEFAULT 'global',
    periode_debut DATE,
    periode_fin DATE,
    contenu_json LONGTEXT,
    cree_par INT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cree_par) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- DONNÉES INITIALES
-- =====================================================

-- Catégories
INSERT INTO categories_objets (nom, description, icone) VALUES
('Thermostat','Régulation température','thermostat'),
('Éclairage','Ampoules et spots connectés','lightbulb'),
('Sécurité','Caméras, alarmes, serrures','security'),
('Électroménager','Machines, fours, frigos connectés','kitchen'),
('Capteurs','Température, humidité, CO2, mouvement','sensors'),
('Multimédia','TV, enceintes, box connectées','tv'),
('Énergie','Compteurs, panneaux solaires, prises','bolt'),
('Confort','Volets, climatisation, ventilation','air');

-- Pièces
INSERT INTO pieces (nom, etage, superficie, description) VALUES
('Salon',0, 28.5, 'Pièce principale de vie'),
('Cuisine',0, 14.0, 'Cuisine équipée'),
('Chambre principale',1, 18.0, 'Chambre des parents'),
('Chambre enfant 1',1, 12.5, 'Chambre de Lucas'),
('Chambre enfant 2',1, 11.0, 'Chambre de Léa'),
('Salle de bain',1, 8.5, 'Salle de bain principale'),
('Bureau',1, 10.0, 'Bureau/télétravail'),
('Cave/Garage',0, 22.0, 'Cave et garage');

-- Objets connectés
INSERT INTO objets_connectes (id_unique, nom, description, marque, modele, type_connectivite, force_signal, etat, categorie_id, piece_id, batterie, firmware, ip_locale, mac_address, ajoute_par) VALUES
('THERMO_SAL_01','Thermostat Salon','Thermostat intelligent principal','Netatmo','NAT-TH01','Wi-Fi','fort','actif',1,1,NULL,'v3.2.1','192.168.1.10','AA:BB:CC:DD:EE:01',NULL),
('THERMO_CH_01','Thermostat Chambre','Thermostat chambre principale','Netatmo','NAT-TH01','Wi-Fi','fort','actif',1,3,NULL,'v3.2.1','192.168.1.11','AA:BB:CC:DD:EE:02',NULL),
('LAMP_SAL_01','Lampe Salon Principal','Ampoule connectée RGB plafond','Philips','Hue A21','Zigbee','fort','actif',2,1,NULL,'v2.4','192.168.1.20','AA:BB:CC:DD:EE:03',NULL),
('LAMP_SAL_02','Lampe Salon Appoint','Lampe de sol connectée','Philips','Hue Go','Zigbee','fort','actif',2,1,NULL,'v2.4','192.168.1.21','AA:BB:CC:DD:EE:04',NULL),
('LAMP_CUI_01','Lumière Cuisine','Bandeau LED sous meuble','IKEA','Trådfri','Zigbee','moyen','actif',2,2,NULL,'v1.8','192.168.1.22','AA:BB:CC:DD:EE:05',NULL),
('CAM_ENTREE_01','Caméra Entrée','Caméra surveillance extérieure','Ring','Pro 2','Wi-Fi','fort','actif',3,NULL,NULL,'v5.1.0','192.168.1.30','AA:BB:CC:DD:EE:06',NULL),
('SERRURE_01','Serrure Connectée','Serrure porte principale','Yale','Linus L2','Bluetooth','fort','actif',3,NULL,65,'v2.0.3',NULL,'AA:BB:CC:DD:EE:07',NULL),
('MACHINE_LAV_01','Machine à Laver','Lave-linge connecté 9kg','Samsung','WW90T','Wi-Fi','fort','actif',4,2,NULL,'v1.5','192.168.1.40','AA:BB:CC:DD:EE:08',NULL),
('FOUR_01','Four Connecté','Four multifonction connecté','Bosch','HBG7','Wi-Fi','moyen','actif',4,2,NULL,'v2.1','192.168.1.41','AA:BB:CC:DD:EE:09',NULL),
('FRIGO_01','Réfrigérateur','Réfrigérateur smart avec caméra','LG','InstaView','Wi-Fi','fort','actif',4,2,NULL,'v3.0','192.168.1.42','AA:BB:CC:DD:EE:10',NULL),
('CAPTEMP_SAL_01','Capteur Température Salon','Capteur T°/Humidité/CO2','Aqara','TVOC','Zigbee','fort','actif',5,1,82,'v1.2.3',NULL,'AA:BB:CC:DD:EE:11',NULL),
('CAPTMVT_01','Détecteur Mouvement','Capteur de présence entrée','Philips','Hue Motion','Zigbee','fort','actif',5,NULL,70,'v2.0',NULL,'AA:BB:CC:DD:EE:12',NULL),
('TV_SAL_01','TV Salon','Téléviseur OLED 65 pouces','LG','OLED65C2','Wi-Fi','fort','inactif',6,1,NULL,'v03.33','192.168.1.50','AA:BB:CC:DD:EE:13',NULL),
('ENCEINTE_01','Enceinte Connectée','Enceinte smart avec assistant','Sonos','Era 300','Wi-Fi','fort','actif',6,1,NULL,'v15.4','192.168.1.51','AA:BB:CC:DD:EE:14',NULL),
('COMPTEUR_01','Compteur Électrique','Compteur Linky connecté','Enedis','Linky 3.0','Wi-Fi','fort','actif',7,NULL,NULL,'v4.0','192.168.1.60','AA:BB:CC:DD:EE:15',NULL),
('PRISE_BUR_01','Prise Bureau','Prise intelligente bureau','TP-Link','Tapo P110','Wi-Fi','fort','actif',7,7,NULL,'v1.3','192.168.1.61','AA:BB:CC:DD:EE:16',NULL),
('VOLET_SAL_01','Volet Salon','Volet roulant motorisé salon','Somfy','Tahoma','Wi-Fi','fort','actif',8,1,NULL,'v2.5','192.168.1.70','AA:BB:CC:DD:EE:17',NULL),
('VOLET_CH_01','Volet Chambre','Volet roulant chambre principale','Somfy','Tahoma','Wi-Fi','fort','actif',8,3,NULL,'v2.5','192.168.1.71','AA:BB:CC:DD:EE:18',NULL),
('CLIM_SAL_01','Climatiseur Salon','Climatiseur réversible','Daikin','FTXM35R','Wi-Fi','fort','inactif',8,1,NULL,'v3.1','192.168.1.72','AA:BB:CC:DD:EE:19',NULL),
('ASPI_ROBOT_01','Aspirateur Robot','Robot aspirateur programmable','Roomba','j7+','Wi-Fi','fort','actif',4,NULL,45,'v3.10.65','192.168.1.43','AA:BB:CC:DD:EE:20',NULL);

-- Attributs dynamiques
INSERT INTO attributs_objets (objet_id, cle, valeur, unite, type_attribut) VALUES
(1,'temperature_actuelle','21','°C','capteur'),
(1,'temperature_cible','22','°C','configuration'),
(1,'mode','Automatique',NULL,'configuration'),
(1,'consommation_jour','0.8','kWh','energie'),
(2,'temperature_actuelle','19','°C','capteur'),
(2,'temperature_cible','20','°C','configuration'),
(2,'mode','Manuel',NULL,'configuration'),
(3,'luminosite','75','%','usage'),
(3,'couleur','#FFE4B5',NULL,'configuration'),
(3,'consommation','8.5','W','energie'),
(4,'luminosite','40','%','usage'),
(4,'couleur','#FFFFFF',NULL,'configuration'),
(5,'luminosite','100','%','usage'),
(5,'consommation','12','W','energie'),
(6,'resolution','1080p',NULL,'capteur'),
(6,'enregistrement','actif',NULL,'configuration'),
(6,'stockage_utilise','64','%','usage'),
(7,'verrou','fermé',NULL,'usage'),
(7,'tentatives_echec','0',NULL,'capteur'),
(8,'programme','Coton 60°',NULL,'configuration'),
(8,'temps_restant','42','min','usage'),
(8,'consommation','1800','W','energie'),
(8,'consommation_eau','50','L','energie'),
(9,'temperature_four','0','°C','capteur'),
(9,'mode_cuisson','éteint',NULL,'configuration'),
(10,'temperature_frigo','-4','°C','capteur'),
(10,'temperature_congelateur','-18','°C','capteur'),
(10,'consommation','120','W','energie'),
(11,'temperature','21.3','°C','capteur'),
(11,'humidite','48','%','capteur'),
(11,'co2','412','ppm','capteur'),
(11,'qualite_air','Bonne',NULL,'capteur'),
(12,'presence','Non détectée',NULL,'capteur'),
(12,'luminosite_ambiante','180','lux','capteur'),
(13,'volume','0','%','configuration'),
(14,'volume','35','%','configuration'),
(14,'lecture_en_cours','Jazz Playlist',NULL,'usage'),
(15,'consommation_totale','4823','kWh','energie'),
(15,'puissance_instant','892','W','energie'),
(15,'consommation_jour','18.4','kWh','energie'),
(16,'puissance','45','W','energie'),
(16,'energie_jour','0.36','kWh','energie'),
(17,'position','100','%','usage'),
(18,'position','0','%','usage'),
(19,'temperature_consigne','26','°C','configuration'),
(19,'mode_clim','Refroidissement',NULL,'configuration'),
(20,'progression','23','%','usage'),
(20,'surface_nettoyee','18','m²','usage'),
(20,'batterie','45','%','energie');

-- Données historiques (simulation 7 derniers jours)
INSERT INTO historique_donnees (objet_id, cle, valeur, timestamp) VALUES
(15,'consommation_jour','16.2','2024-12-08 23:59:00'),
(15,'consommation_jour','17.8','2024-12-09 23:59:00'),
(15,'consommation_jour','19.1','2024-12-10 23:59:00'),
(15,'consommation_jour','15.6','2024-12-11 23:59:00'),
(15,'consommation_jour','18.9','2024-12-12 23:59:00'),
(15,'consommation_jour','21.3','2024-12-13 23:59:00'),
(15,'consommation_jour','18.4','2024-12-14 23:59:00'),
(1,'temperature_actuelle','20.5','2024-12-14 08:00:00'),
(1,'temperature_actuelle','21.0','2024-12-14 10:00:00'),
(1,'temperature_actuelle','21.5','2024-12-14 12:00:00'),
(1,'temperature_actuelle','22.0','2024-12-14 14:00:00'),
(1,'temperature_actuelle','21.8','2024-12-14 16:00:00'),
(1,'temperature_actuelle','21.2','2024-12-14 18:00:00'),
(1,'temperature_actuelle','21.0','2024-12-14 20:00:00'),
(1,'temperature_actuelle','20.8','2024-12-14 22:00:00'),
(8,'consommation_eau','48','2024-12-12 10:00:00'),
(8,'consommation_eau','52','2024-12-13 11:00:00'),
(8,'consommation_eau','50','2024-12-14 09:00:00');

-- Actualités
INSERT INTO actualites (titre, contenu, image) VALUES
('Bienvenue sur SmartHome !','Votre plateforme de maison intelligente est désormais en ligne. Gérez tous vos objets connectés depuis un seul endroit.',NULL),
('Mise à jour firmware thermostats','Les thermostats Netatmo ont reçu une mise à jour améliorant la précision de +0.1°C et réduisant la consommation en veille de 15%.',NULL),
('Nouveau : intégration aspirateur robot','L''aspirateur Roomba j7+ est maintenant pleinement intégré. Planifiez vos nettoyages depuis la plateforme !',NULL),
('Conseil éco : réduisez votre facture','Activez le mode Eco sur votre climatiseur pour réduire jusqu''à 30% votre consommation estivale. Consultez le module Gestion pour configurer les plages horaires.',NULL);

-- Utilisateur admin par défaut (mot de passe : Admin@2025)
INSERT INTO utilisateurs (login, email, mot_de_passe, nom, prenom, type_membre, niveau, points, statut, token_validation) VALUES
('admin','admin@smarthome.local','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Administrateur','Super','autre','expert',100.00,'actif',NULL);
