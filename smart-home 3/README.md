# SmartHome

Projet ING1 2025-2026 - plateforme de maison connectée.

Stack : PHP 8 / MySQL / Bootstrap 5

## Installation

1. Copier le projet dans le dossier web :
   - Wamp : `C:/wamp64/www/smart-home/`
   - MAMP : `/Applications/MAMP/htdocs/smart-home/`
   - Linux : `/var/www/html/smart-home/`

2. Créer la base de données et importer `database.sql` :
```
mysql -u root -p < database.sql
```
Ou via phpMyAdmin (créer la base `smarthome_db` puis Importer).

3. Configurer la connexion BDD dans `includes/config.php` :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'smarthome_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('SITE_URL', 'http://localhost/smart-home');
```

4. Ouvrir `http://localhost/smart-home`

## Compte de test

| Login | Mot de passe | Niveau |
|-------|--------------|--------|
| admin | password     | Expert |

Pour générer un autre hash :
```php
echo password_hash('motdepasse', PASSWORD_BCRYPT);
```

## Types d'utilisateurs

- Visiteur : module Information uniquement
- Simple (débutant / intermédiaire) : Information + Visualisation
- Complexe (avancé / expert) : + module Gestion
- Admin (expert) : + module Administration

## Système de points

| Action          | Points |
|-----------------|--------|
| Connexion       | 0.25   |
| Consultation    | 0.50   |
| Modification    | 1.00   |
| Ajout           | 1.50   |

Seuils de niveau : 0 / 5 / 15 / 30 points.

## Technologies

- PHP 8 (PDO, sessions, bcrypt)
- MySQL 8
- Bootstrap 5.3
- Chart.js 4 pour les graphiques
- HTML / CSS / JS natifs

## Email de validation

La fonction `mail()` PHP nécessite un SMTP configuré. En local, le plus simple est de valider manuellement en BDD :
```sql
UPDATE utilisateurs SET statut='actif', token_validation=NULL WHERE email='test@test.fr';
```
