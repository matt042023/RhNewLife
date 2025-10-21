# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**RhNewLife** - Application SIRH (Système d'Information Ressources Humaines) pour l'Association NewLife, un lieu de vie accueillant des enfants.

- **Framework**: Symfony 7.3 (PHP 8.3)
- **Base de données**: MariaDB 10.11
- **Frontend**: Tailwind CSS 4 + Flowbite + Stimulus/Turbo
- **Build**: Webpack Encore
- **Email**: Symfony Mailer avec MailHog (dev)
- **Développeur**: Matthieu
- **Date cible V1**: Janvier 2026 (20 semaines)

## Contexte Métier

### Utilisateurs cibles
- **1 Administrateur** : Configuration système, gestion globale
- **1 Directeur** : Gestion RH complète, validation congés, entretiens
- **~10 Salariés (Éducateurs)** : Consultation planning, demandes congés, documents

### Périmètre Fonctionnel V1 (Modules SIRH)

**Modules Critiques** :
- 🆕 **Onboarding** : Email automatique + lien sécurisé pour complétion profil
- 👤 **Fiches Salariés** : État civil, RIB, coordonnées, matricule
- 📝 **Contrats** : CDI/CDD/Bénévolat, salaire, mutuelle
- 🏥 **Visites Médicales** : Suivi et alertes renouvellement
- 💰 **Éléments Variables Paie** : Primes, frais, acomptes
- 📊 **Export Paie** : Récap mensuel PDF + CSV
- 🏖️ **Congés & Absences** : 9 types, workflow validation
- 📅 **Planning** : Roulements 35h par villa
- 🔔 **Alertes RH** : Notifications automatiques

**Modules Importants** :
- 🎤 **Entretiens**, 🎯 **Objectifs**, 🎓 **Formations**
- 📄 **Documents** (versioning), 👋 **Offboarding**
- 💬 **Messagerie**, 🔐 **RGPD** (audit logs)

## Docker Development Environment

### Architecture

Le projet utilise une stack Docker composée de 5 services:
- **php** (PHP 8.3-FPM) - Application Symfony
- **nginx** (1.27-alpine) - Serveur web
- **mariadb** (10.11) - Base de données
- **phpmyadmin** - Interface de gestion BDD
- **mailhog** - Capture des emails en développement

### Commandes Docker

```bash
# Démarrer l'environnement
docker compose up -d

# Arrêter l'environnement
docker compose down

# Voir les logs
docker compose logs -f php
docker compose logs -f nginx

# Rebuild après modification du Dockerfile
docker compose up -d --build
```

### URLs de développement

- Application: http://localhost:8080/
- PHPMyAdmin: http://localhost:8081/ (root/root)
- MailHog: http://localhost:8025/
- Test Email: http://localhost:8080/test-mail

## Symfony Commands

Toutes les commandes Symfony doivent être exécutées dans le conteneur PHP:

```bash
# Template de commande
docker compose exec php php bin/console [command]

# Exemples courants
docker compose exec php php bin/console cache:clear
docker compose exec php php bin/console doctrine:migrations:migrate
docker compose exec php php bin/console doctrine:schema:update --force
docker compose exec php php bin/console make:entity
docker compose exec php php bin/console make:controller
docker compose exec php php bin/console messenger:consume async -vv
```

## Database & Migrations

### Modèle de Données Principal

**Entités principales** :
- `User` : Salariés (éducateurs, directeur, admin)
- `Contrat` : Informations contractuelles (1 contrat actif par user)
- `Document` : Fichiers liés aux salariés (contrats, RIB, justificatifs)
- `Villa` : Unités d'accueil (Villa A, Villa B)
- `Affectation` : Planning des éducateurs par villa (blocs 48h)
- `Absence` : Congés et absences (9 types: CP, RTT, AT, MAL, CPSS, etc.)
- `ElementVariable` : Éléments de paie variables (frais, avances)
- `Entretien` : Entretiens individuels RH
- `Objectif` : Objectifs personnels
- `Formation` : Formations suivies (PSC1, etc.)
- `Alerte` : Notifications RH automatiques
- `Journal` : Audit trail (RGPD)

**Relations importantes** :
- 1 User ↔ 1 Contrat actif
- 1 User ↔ n Documents
- 1 User ↔ n Absences
- 1 Villa ↔ n Affectations
- 1 User ↔ n Affectations

### Création de migrations

```bash
docker compose exec php php bin/console make:migration
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### Accès direct à la base de données

```bash
docker compose exec mariadb mysql -u root -proot rhnewlife
```

### Configuration

- **Base de données**: `rhnewlife`
- **User**: `root`
- **Password**: `root`
- **Host**: `mariadb` (dans Docker) ou `localhost:3307` (depuis l'hôte)

## Frontend Assets

Le projet utilise Webpack Encore avec Tailwind CSS 4 et Flowbite.

### Commandes NPM

```bash
# Installer les dépendances
npm install

# Build dev (une fois)
npm run dev

# Watch mode (rebuild automatique)
npm run watch

# Build production
npm run build
```

**Important**: Les assets doivent être compilés AVANT le premier lancement de l'application.

### Structure des assets

```
assets/
├── app.js          # Point d'entrée JavaScript
├── styles/
│   └── app.css     # Styles Tailwind/Flowbite
└── controllers/    # Stimulus controllers
```

Build output: `public/build/`

## Email Development

### Configuration Messenger

Les emails sont envoyés de manière **synchrone** en développement (pas de queue):

```yaml
# config/packages/messenger.yaml
routing:
    Symfony\Component\Mailer\Messenger\SendEmailMessage: sync
```

Pour utiliser le mode asynchrone, changer `sync` en `async` et lancer le worker:

```bash
docker compose exec php php bin/console messenger:consume async -vv
```

### Test d'envoi d'email

1. Accéder à: http://localhost:8080/test-mail
2. Vérifier dans MailHog: http://localhost:8025/

## Testing

```bash
# Lancer les tests PHPUnit
docker compose exec php php bin/phpunit

# Lancer un test spécifique
docker compose exec php php bin/phpunit tests/Controller/HomeControllerTest.php
```

## Code Architecture

### Structure MVC classique Symfony

```
src/
├── Controller/     # Contrôleurs (HomeController, TestMailController)
├── Entity/         # Entités Doctrine (User, Contrat, Villa, etc.)
├── Repository/     # Repositories Doctrine
└── Kernel.php      # Kernel Symfony
```

### Sécurité & RGPD

**Rôles & Permissions** :
- `ROLE_USER` : Salarié (consultation limitée)
- `ROLE_DIRECTOR` : Directeur (gestion RH complète)
- `ROLE_ADMIN` : Administrateur (accès total)

**Données Sensibles** :
- **Chiffrement** : RIB et données sensibles
- **Audit Logs** : Table `Journal` pour traçabilité (RGPD)
- **Masquage** : Affichage partiel des RIB (`****1234`)
- **SoftDelete** : `deleted_at` pour archivage

### Routing

Routes définies via attributes PHP 8 dans les contrôleurs:

```php
#[Route('/test-mail', name: 'test_mail')]
public function send(MailerInterface $mailer): Response
```

### Templates

Templates Twig dans `templates/` avec base layout utilisant Tailwind/Flowbite.

## Environment Variables

### Fichiers d'environnement

- `.env` - Valeurs par défaut (committé)
- `.env.local` - Surcharges locales (non committé)
- `.env.docker` - Configuration Docker (si utilisé)

### Variables importantes

```bash
APP_ENV=dev
DATABASE_URL="mysql://root:root@mariadb:3306/rhnewlife?serverVersion=10.11.2-MariaDB&charset=utf8mb4"
MAILER_DSN="smtp://mailhog:1025"
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
```

## Common Issues

### Assets 404

Si les CSS/JS ne se chargent pas:
1. Compiler les assets: `npm run dev`
2. Vider le cache: `docker compose exec php php bin/console cache:clear`

### Emails non envoyés (mode async)

Si les emails ne partent pas avec `async`:
1. Vérifier la queue: `docker compose exec php php bin/console messenger:stats`
2. Lancer le worker: `docker compose exec php php bin/console messenger:consume async -vv`

### Problèmes de permissions

Si erreurs de permissions dans `/var`:
```bash
docker compose exec php chown -R www-data:www-data var/
```

## Development Workflow

1. **Démarrer Docker**: `docker compose up -d`
2. **Installer les dépendances**: `composer install` + `npm install`
3. **Compiler les assets**: `npm run watch`
4. **Lancer les migrations**: `docker compose exec php php bin/console doctrine:migrations:migrate`
5. **Accéder à l'app**: http://localhost:8080/

## Documentation du Projet

Le projet contient une documentation complète dans `docs/` :

- **📖 Guide Navigation** : Vue d'ensemble de la documentation
- **🗺️ Roadmap Globale** : Épics → Tickets → Tâches détaillées
- **🧭 Cahier des Charges** : Spécifications fonctionnelles
- **🧩 Modèle de Données** : Schéma BDD complet avec 12 entités
- **⚠️ STD** : Spécifications techniques détaillées
- **Architecture UX** : Design et wireframes
- **User Stories** : Cas d'usage détaillés par module

**Document de référence** : Voir README.md à la racine du projet parent (`GestionRHNewLife/`)

## Roadmap V1 (20 semaines - Janvier 2026)

### Épics principaux

| Epic | Description | Référence |
|------|-------------|-----------|
| **EP-00** | Bootstrap Projet & DevOps | Docker, CI/CD, qualité code |
| **EP-01** | Onboarding RH | Invitation, création compte, upload justificatifs |
| **EP-02** | Fiche Salarié & Contrat | CRUD salarié, contrat unique actif |
| **EP-03** | Planning & Affectations | Roulements 35h par villa |
| **EP-04** | Congés & Absences | 9 types, workflow validation |
| **EP-05** | Paie & Éléments Variables | Export mensuel PDF/CSV |
| **EP-06** | Documents & Versioning | Upload, stockage sécurisé |
| **EP-07** | Entretiens & Objectifs | Suivi RH individuel |
| **EP-08** | Formations | PSC1, alertes renouvellement |
| **EP-09** | Alertes RH | Notifications automatiques |
| **EP-10** | RGPD & Audit | Logs, export données |

## Production Deployment

⚠️ Avant le déploiement en production:

1. Changer `APP_ENV=prod` dans `.env`
2. Build assets production: `npm run build`
3. Utiliser des secrets sécurisés (pas `root/root`)
4. Configurer clés JWT pour authentification
5. Activer OPcache et désactiver Xdebug
6. Configurer HTTPS/SSL (Let's Encrypt)
7. Utiliser un vrai serveur SMTP (pas MailHog)
8. Mettre en place backups automatiques MariaDB
9. Configurer monitoring (Sentry, logs)
10. Tester la conformité RGPD

## Contact & Support

**Développeur**: Matthieu
**Email**: matthieu@newlife.fr
**Product Owner**: Directeur Association NewLife
