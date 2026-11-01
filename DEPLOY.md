# Déploiement sur Railway

L'application est livrée avec un `Dockerfile` (FrankenPHP + PHP 8.4) et un fichier `railway.json`.
Railway construit l'image à chaque push sur la branche suivie. Au démarrage, le conteneur :

1. attend que PostgreSQL soit joignable ;
2. applique les migrations Doctrine ;
3. en mode démonstration (`APP_DEMO=1`), charge l'établissement fictif si la base est vide ;
4. démarre le serveur sur le port fourni par Railway (`$PORT`).

## 1. Créer le projet

1. Sur [railway.com](https://railway.com) : **New Project** → **Deploy from GitHub repo** → `ovvoso/TrackMateriel`.
2. Dans le service créé : **Settings** → **Source** → **Branch** : `v2`.
3. Railway détecte le `Dockerfile` grâce à `railway.json`.

## 2. Ajouter la base PostgreSQL

Dans le projet : **+ Create** → **Database** → **Add PostgreSQL**, puis attendre que le service soit vert.

## 3. Variables du service web

Onglet **Variables** du service de l'application (pas celui de Postgres) :

| Variable | Valeur |
|---|---|
| `DATABASE_URL` | `${{Postgres.DATABASE_URL}}` |
| `APP_SECRET` | 32 caractères hexadécimaux aléatoires (voir ci-dessous) |
| `DEFAULT_URI` | l'adresse publique, par exemple `https://trackmateriel-production.up.railway.app` |
| `APP_DEMO` | `1` pour l'instance de démonstration publique, `0` pour un vrai établissement |
| `DEMO_PASSWORD` | mot de passe des comptes de démonstration (affiché sur l'écran de connexion en mode démo) |
| `MAILER_DSN` | `null://null` en démonstration (aucun e-mail ne part) ; `brevo+api://CLÉ@default` sinon |
| `MAILER_SENDER` / `MAILER_FROM` | adresse d'expédition validée chez Brevo (hors démonstration) |

`APP_ENV=prod` et la confiance envers le proxy HTTPS de Railway sont déjà définis dans l'image.

```bash
php -r "echo bin2hex(random_bytes(16)), PHP_EOL;"
```

## 4. Domaine public

**Settings** → **Networking** → **Generate Domain**, port **8080**. Reporter l'adresse obtenue dans `DEFAULT_URI`.

## 5. Tâche quotidienne

Créer un second service à partir du même dépôt et de la même branche, avec les mêmes variables
(**Variables** → **Shared variables** facilite le partage), puis dans **Settings** :

- **Cron Schedule** : `0 6 * * *` (tous les jours à 6 h UTC) ;
- **Custom Start Command** :
  - instance de démonstration : `app-entrypoint php bin/console app:demo:load --force` (remise à zéro de la démo) ;
  - vrai établissement : `app-entrypoint php bin/console app:loans:remind` (rappels de retour des prêts).

## 6. Vérifier le déploiement

Dans **Deployments** → **View logs** :

```
[OK] Successfully migrated to version: ...
[OK] Démo chargée : 6 comptes, 90 équipements, 8 prêts.
```

Parcours à tester :

- [ ] Écran de connexion : entrer avec chacun des quatre profils de démonstration
- [ ] Gestionnaire : inventaire filtré, fiche d'un équipement, nouveau prêt, retour partiel
- [ ] Technicien : envoyer un équipement en maintenance, puis le remettre en circulation
- [ ] Usager : « Mes emprunts » ne montre que ses propres prêts
- [ ] Administrateur : liste des utilisateurs, invitation
- [ ] Affichage sur téléphone et en mode sombre

## Premier administrateur (vrai établissement)

Sans démonstration, la base démarre vide. Dans le service web : **⋮** → **Shell** (ou `railway ssh`) :

```bash
php bin/console app:user:create prenom.nom@etablissement.fr Prénom Nom --role=admin
```

Les autres comptes sont ensuite invités depuis l'application (menu **Utilisateurs**).

## Limites connues

- Les sessions sont stockées dans le conteneur : un redéploiement déconnecte les utilisateurs.
- Les e-mails partent pendant la requête : sans fournisseur configuré (`null://null`), invitations et
  liens de mot de passe ne sont pas envoyés.
