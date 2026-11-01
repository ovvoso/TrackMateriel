# TrackMatériel

Gestion du parc matériel des établissements d'enseignement : inventaire, prêts aux enseignants et aux étudiants, réservations pour les cours et suivi de la maintenance.

> **Version 2 en cours de construction.** La version 1, réalisée en Master 1, reste disponible sur la branche `main`. Cette branche repart d'un socle neuf : modèle métier repensé, sécurité renforcée et interface entièrement redessinée.

## Ce que fait l'application

| Module | État |
|---|---|
| Comptes, profils et connexion sécurisée | ✅ Livré |
| Inventaire (codes d'inventaire, emplacements, cycle de vie) | 🔜 En cours |
| Prêts et affectations, avec retours et retards | 🔜 |
| Réservations de matériel pour les cours | 🔜 |
| Tickets de maintenance et maintenance préventive | 🔜 |
| Étiquettes QR code et signalement de panne | 🔜 |

### Profils

| Profil | Rôle |
|---|---|
| Administrateur | Configure l'établissement et gère les comptes |
| Gestionnaire de parc | Tient l'inventaire, les prêts et les réservations |
| Technicien | Traite les tickets de maintenance |
| Usager | Enseignant ou personnel : emprunte, réserve, signale une panne |

Chaque profil hérite des droits du profil inférieur. Il n'y a pas d'inscription publique : les comptes sont créés par l'établissement.

## Stack technique

- **PHP 8.4** et **Symfony 7.4 LTS**
- **PostgreSQL 18** avec Doctrine ORM et migrations
- **Twig**, Twig Components, **Turbo** et **Stimulus** (AssetMapper, sans étape de build Node)
- Composants Symfony **Workflow**, **Scheduler**, **Messenger** et **Rate Limiter**
- Icônes **Lucide** et police **IBM Plex**, hébergées localement
- Déploiement **Docker / FrankenPHP** sur **Railway**

## Architecture

Le code est organisé par domaine métier plutôt que par type technique :

```
src/
├── Identity/      Comptes, profils, connexion
│   ├── Entity/  Enum/  Repository/  Security/  Controller/  Command/  Factory/
├── Shared/        Briques transverses : en-têtes de sécurité, CSP, tableau de bord
├── Story/         Jeu de données de démonstration (dev et test)
└── Migrations/
```

Chaque nouveau domaine (Inventaire, Prêts, Réservations, Maintenance) suit la même structure et déclare sa propre cartographie Doctrine.

## Sécurité

- Mots de passe hachés avec l'algorithme recommandé par Symfony, rehachés automatiquement s'il évolue
- Connexion protégée par jeton CSRF, limitée à 5 tentatives par quart d'heure
- Comptes désactivables, déconnexion en POST uniquement
- Identifiants publics en UUID v7 (pas d'identifiants séquentiels devinables)
- Content-Security-Policy stricte à nonce, sans `unsafe-inline` ni CDN externe
- En-têtes `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` et HSTS en production

## Installation locale

Prérequis : PHP 8.4 (extensions `pdo_pgsql` et `intl`), Composer, PostgreSQL.

```bash
git clone https://github.com/ovvoso/TrackMateriel.git
cd TrackMateriel
git switch v2
composer install
```

Créez un fichier `.env.local` avec l'accès à votre base :

```dotenv
DATABASE_URL="postgresql://utilisateur:motdepasse@127.0.0.1:5432/trackmateriel?serverVersion=18&charset=utf8"
APP_SECRET=une-valeur-aleatoire
```

Puis :

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console foundry:load-fixtures main   # comptes de démonstration
symfony serve                                # ou : php -S 127.0.0.1:8000 -t public
```

Les comptes de démonstration (un par profil, domaine `lycee-demo.sn`) sont définis dans `src/Story/AppStory.php`.

Pour créer un vrai compte, par exemple le premier administrateur :

```bash
php bin/console app:user:create prenom.nom@etablissement.fr Prénom Nom --role=admin
```

## Qualité

```bash
php bin/phpunit                      # tests fonctionnels et unitaires
vendor/bin/phpstan analyse           # analyse statique, niveau maximal
vendor/bin/php-cs-fixer check        # style du code
```

Ces vérifications tournent à chaque push via GitHub Actions, sur une base PostgreSQL.

## Déploiement

L'image Docker (FrankenPHP) applique les migrations au démarrage. Sur Railway, il suffit d'ajouter une base PostgreSQL et de définir `DATABASE_URL`, `APP_SECRET` et `DEFAULT_URI`.

## Auteur

**Thierno Maadjou Sow** · thiernomaadjous@gmail.com
