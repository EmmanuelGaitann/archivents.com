# Archiven — Galerie photo d'événement (CodeIgniter 3)

Application de galerie photo d'événement (mariage, séminaire, etc.) : l'admin
uploade les photos **en temps réel** pendant l'événement, les invités y accèdent
via un **QR code** vers une URL publique pour consulter et télécharger.

- Rendu **côté serveur** (CodeIgniter 3.1, PHP 8) + JS vanilla, **Tailwind pré-compilé**
  en CSS statique (`assets/css/tailwind.css`, voir `build-css.md`) — pas de CDN en prod.
- Pipeline d'images **asynchrone** (worker cron) : 3 WebP + original JPEG temporaire.
- Pensé pour l'**hébergement mutualisé O2Switch** (LiteSpeed, `.htaccess`, cron) —
  pas de root, pas de daemon, pas d'étape de build front.
- Structuré pour devenir **multi-tenant** plus tard (1 événement = futur tenant).

---

## 1. Prérequis O2Switch

- **PHP 8.x** (sélectionnable via cPanel → « Sélectionner une version de PHP »).
- Extensions PHP : `mysqli`, `gd` **ou** `imagick` (recommandé), `fileinfo`, `exif`.
  - **Imagick + libheif** : nécessaire pour convertir le **HEIC** (iPhone).
    Sans Imagick, le fallback **GD** fonctionne pour **JPEG/PNG** uniquement —
    dans ce cas, fournissez du JPEG (voir §8).
- MySQL/MariaDB (base créée et importée via **phpMyAdmin**).
- Accès **cron** (cPanel → « Tâches Cron »).

---

## 2. Installation des fichiers

1. Copiez tout le contenu du projet dans le dossier web (ex. `~/public_html/`
   pour le domaine principal, ou le dossier du sous-domaine).
2. Vérifiez que `index.php`, `application/`, `system/`, `uploads/` et `.htaccess`
   sont bien présents.

---

## 3. Base de données (phpMyAdmin)

1. cPanel → **MySQL® Databases** : créez une base (ex. `monuser_archiven`) et un
   utilisateur, puis associez l'utilisateur à la base avec **tous les droits**.
2. cPanel → **phpMyAdmin** : sélectionnez la base, onglet **Importer**, envoyez
   le fichier [`install.sql`](install.sql). Il crée toutes les tables et insère
   les données de démonstration (1 super_admin, 1 editor, 1 événement, 2 albums).

> `install.sql` ne crée PAS la base : créez-la d'abord, puis importez dedans.

### 3bis. Mise à niveau d'une base de PRODUCTION existante

**Ne JAMAIS importer `install.sql` en production** : ses `DROP TABLE`
effaceraient comptes, événements et photos (l'import s'arrête d'ailleurs
sur `#1451 — contrainte externe` dès `DROP TABLE users`, signe que des
données liées existent : c'est un garde-fou, pas un bug).

Utilisez **`migration-prod-full-2026-07-10.sql`** : il amène n'importe
quelle version antérieure du schéma vers le schéma actuel, sans rien
effacer, et il est **rejouable** sans danger.

Procédure phpMyAdmin :
1. sélectionnez la base de prod (ex. `sc3koga4651_archiven`) ;
2. onglet **Importer** → **décochez « Activer la vérification des clés
   étrangères »** ;
3. envoyez `migration-prod-full-2026-07-10.sql` ;
4. vérifiez : `SELECT COUNT(*) FROM plans;` → 8, puis ouvrez
   `https://archivents.com/ping` → `pong … db=ok`.

> L'erreur `#1146 — la table plans n'existe pas` signifie que vous avez
> lancé l'ancienne migration partielle (`migration-prod-2026-07-10.sql`,
> désormais marquée obsolète) sur une base qui n'avait pas encore les
> tables SaaS : la migration **full** les crée.

---

## 4. Configuration (`application/config/` + secrets dans `.env`)

Les secrets (R2, SMTP) vivent dans un fichier **`.env` à la racine**, lu au
bootstrap par `index.php` (jamais committé, bloqué en HTTP par `.htaccess`).
**Pensez à le téléverser sur le serveur** : les clients FTP masquent souvent
les fichiers `.xxx` (activez « afficher les fichiers cachés »). Sans lui :
pas d'envoi d'e-mails (SMTP_PASS) et pas de stockage R2.

### `application/config/database.php`
```php
'hostname' => 'localhost',
'username' => 'monuser_archiven',
'password' => 'VOTRE_MOT_DE_PASSE',
'database' => 'monuser_archiven',
'port'     => 3306,            // O2Switch : 3306 (en local XAMPP : 3307)
'char_set' => 'utf8mb4',
'dbcollat' => 'utf8mb4_unicode_ci',
```

### `application/config/config.php`
```php
$config['base_url']       = 'https://votre-domaine.tld/';  // avec le / final
$config['index_page']     = '';                            // URLs propres (.htaccess actif)
$config['encryption_key'] = 'METTEZ_UNE_CLE_ALEATOIRE';    // 32 caractères hex
```
Générez une clé : `php -r "echo bin2hex(random_bytes(16));"`.

### `application/config/app_config.php`
Constantes applicatives (durées de rétention, tailles d'images, pagination,
matrice de rôles/permissions). Valeurs par défaut utilisables telles quelles ;
ajustez si besoin :
```php
$config['retention_default_hours'] = 48;   // originaux dispo = date_evt + 48h
$config['img_thumb_px']  = 300;
$config['img_medium_px'] = 1600;
$config['gallery_page_size'] = 40;
```

### `.htaccess` (racine)
À la racine du domaine, remplacez :
```apache
RewriteBase /Archiven/
```
par :
```apache
RewriteBase /
```
(ou le chemin du sous-dossier si l'app n'est pas à la racine).

---

## 5. Droits sur `uploads/`

Le worker écrit les images dans `uploads/`. Donnez les droits d'écriture :
```bash
chmod -R 755 uploads
# si l'écriture échoue côté worker, essayez 775
```
Sous-dossiers créés automatiquement par événement :
`uploads/{slug}/{thumb|medium|full|original}/`.
Le dossier `uploads/_incoming/` (sources temporaires) est protégé par un
`.htaccess` « Deny from all ».

---

## 6. Tâches Cron (O2Switch)

cPanel → **Tâches Cron**. Adaptez le chemin PHP et le chemin du projet.
Chemin PHP type O2Switch : `/usr/local/bin/php` (ou `/usr/local/bin/ea-php82`).

**INDISPENSABLE en production — Purge des médias, une fois par jour (ex. 4h15)** :
```
15 4 * * * /usr/local/bin/php /home/USER/public_html/index.php cron purge_media >> /home/USER/cron_purge.log 2>&1
```
C'est la tâche qui maîtrise les coûts de stockage R2. Chaque nuit elle :
1. rejoue la suppression des **orphelins R2** (objets dont l'effacement a échoué) ;
2. supprime les uploads **jamais confirmés** depuis plus de 48 h ;
3. purge les événements dont la **rétention du forfait** est dépassée
   (Test = 7 j, Ponctuel = 15 j, + 3 j de grâce) ;
4. purge les événements des comptes **sans abonnement actif** depuis plus de 30 j.
« Purger » = photos + vidéos supprimées (R2 inclus), événement passé en
statut `archive` (galerie publique → 404). L'événement/albums/réglages sont
conservés (réactivation commerciale possible).
Test sans rien supprimer : `php index.php cron purge_media dry`.
Chaque exécution est journalisée dans `cron_log` → visible sur la page
**admin → Système**.

**INDISPENSABLE en production — Alertes d'abonnement, une fois par jour (ex. 8h00)** :
```
0 8 * * * /usr/local/bin/php /home/USER/public_html/index.php cron subscription_alerts >> /home/USER/cron_alerts.log 2>&1
```
E-mails automatiques aux photographes : rappel à J-7 puis J-1 avant
l'échéance de l'abonnement, puis avis d'expiration (« 30 jours pour
renouveler avant suppression ») avec bascule du statut en `expire`.
Chaque rappel n'est envoyé qu'une fois (drapeaux `notif_j7/j1/expired`,
remis à zéro à chaque activation/prolongation). L'opérateur reçoit par
ailleurs un e-mail à chaque nouvelle inscription (forfait payant =
« à encaisser puis activer »).
Test sans envoyer : `php index.php cron subscription_alerts dry`.

**Uniquement si `ARCHIVENTS_STORAGE=local` (pipeline disque, inutile en mode R2)** :
```
* * * * *  /usr/local/bin/php /home/USER/public_html/index.php cron process_uploads >> /home/USER/cron_uploads.log 2>&1
30 3 * * * /usr/local/bin/php /home/USER/public_html/index.php cron purge_originals >> /home/USER/cron_purge.log 2>&1
```

- Ces contrôleurs ne sont accessibles **qu'en CLI** (`is_cli()`), tout accès HTTP
  est refusé (403).
- Test SMTP en SSH : `TEST_EMAIL=vous@exemple.com php index.php cron test_email`.

### Supervision (UptimeRobot)

Créez un moniteur **HTTP(s) → mot-clé** sur `https://archivents.com/ping` :
- mot-clé attendu : **`db=ok`** (type « keyword exists ») ;
- intervalle : 5 min.

Ainsi l'alerte part non seulement si le site est down (500/timeout), mais
aussi si PHP répond alors que **la base est injoignable** (`db=FAIL`).
La page `/ping` n'expose aucune information sensible.

---

## 6bis. Sous-domaines studio ({studio}.archivents.com)

L'application résout elle-même l'hôte : si la requête arrive sur
`{slug}.archivents.com` et que `{slug}` correspond au `studio_slug` d'un
photographe actif, elle affiche sa **vitrine studio** (page sobre, sans
liste de galeries — les liens `/e/{code}` restent non devinables).
Sous-domaine inconnu → redirection vers archivents.com.

Configuration hébergement (une seule fois) :
1. **cPanel O2Switch → Domaines / Sous-domaines** : créer le sous-domaine
   joker `*` (wildcard) pour archivents.com, **racine du document = le même
   dossier que le site principal** (ex. `/home/USER/archivents`).
2. **DNS (zone Cloudflare)** : ajouter un enregistrement `A` (ou CNAME)
   pour `*` pointant vers l'IP du serveur O2Switch, **proxié (nuage
   orange)** — le certificat universel Cloudflare couvre `*.archivents.com`
   (SSL/TLS en mode « Full »).
3. Rien à déployer côté code : la détection est dans `Home::studio_from_host()`.
   Sous-domaines réservés (www, cdn, mail…) : liste dans la même méthode.

NB : un sous-domaine EXPLICITE créé dans cPanel (ex. `matuta`) a priorité
sur le joker — utile pour conserver une ancienne installation.

---

## 6ter. Vérification d'e-mail (anti-spam)

L'inscription envoie un lien de confirmation (jeton HMAC, aucune table).
Tant que l'adresse n'est pas confirmée, un photographe **ne peut ni créer
d'événement ni importer de fichiers** (message explicite + bouton
« Renvoyer » sur le tableau de bord). Super admin et collaborateurs
(comptes créés par le titulaire) ne sont pas concernés.

---

## 7. Comptes de démonstration

| Rôle        | Email                   | Mot de passe |
|-------------|-------------------------|--------------|
| super_admin | `super@archiven.test`   | `Admin@123`  |
| photographe | `editor@archiven.test`  | `Editor@123` |

> **Changez ces mots de passe immédiatement** en production (ou créez vos comptes
> puis supprimez les comptes de démo). Pour générer un hash :
> `php -r "echo password_hash('NouveauMotDePasse', PASSWORD_DEFAULT);"`
> puis mettez à jour `users.password_hash` via phpMyAdmin.

**Back-office** : `https://votre-domaine.tld/admin`
**Galerie publique d'un événement** : `https://votre-domaine.tld/e/{slug}`
(le QR encode cette URL avec `?src=qr`).

### Rôles & permissions
- **super_admin** : tout, y compris gestion des utilisateurs, forfaits,
  abonnements et suppression d'événement.
- **photographe** : le tenant qui s'inscrit et s'abonne — albums, upload,
  options, titres, statistiques (dans la limite de son forfait).
- **collaborateur** : compte invité par un photographe (forfait Studio) ;
  travaille sur les événements du titulaire, ne gère ni abonnement ni comptes.

Système extensible : la matrice est dans `app_config.php`
(`$config['role_permissions']`) et doublée dans la table `roles`.

---

## 8. HEIC (photos iPhone)

- Avec **Imagick + libheif** présents sur le serveur, les fichiers `.heic` sont
  convertis automatiquement en JPEG/WebP par le worker.
- Sans libheif, le HEIC **n'est pas décodable** : exportez/transférez les photos
  en **JPEG** avant upload (réglage iPhone : Réglages → Appareil photo →
  Formats → « Le plus compatible »).

---

## 9. Pourquoi pas l'adresse MAC ? (unicité des visiteurs)

L'adresse **MAC n'est pas récupérable côté serveur** : un serveur web ne voit que
l'adresse IP (souvent partagée/portée par NAT, donc inutilisable comme identifiant
unique) et les en-têtes HTTP. La MAC reste cantonnée au réseau local du visiteur.

Archiven identifie donc chaque **appareil** par un **`visitor_uid`** :
- un **UUID** généré côté navigateur, stocké en **cookie first-party** *et* en
  **localStorage** (l'un restaure l'autre s'il est effacé) ;
- complété par une **empreinte navigateur légère** (écran, fuseau, langue,
  plateforme) **hashée** côté serveur, en secours.

L'**IP** n'est conservée que pour une **géolocalisation grossière**, **jamais**
comme compteur d'unicité. Les statistiques distinguent **connexions totales** et
**appareils uniques** (`COUNT DISTINCT visitor_uid`), ainsi que la **source**
(QR vs lien).

---

## 10. Performance

- `thumb` et `medium` sont servis en **statique** par LiteSpeed (jamais via PHP),
  avec un cache long (`Cache-Control: immutable`, 1 an) défini dans
  `uploads/.htaccess` — URLs stables compatibles cache Cloudflare.
- **Aucun redimensionnement à l'affichage** : tout est pré-généré par le worker.
- Galerie en **lazy-load** (natif + IntersectionObserver) avec **scroll infini**
  par paquets de 40 — jamais 1500 images chargées d'un coup.

---

## 11. Dépannage rapide

| Symptôme | Piste |
|---|---|
| Page blanche / 500 | Les erreurs sont journalisées dans `application/logs/` (`log_threshold = 1` par défaut ; montez à 4 pour un débogage fin). Cause classique après déploiement : **base non migrée** (table `plans` absente → fatal sur la page d'accueil) — importez `migration-prod-full-2026-07-10.sql` (§3bis) puis testez `/ping`. |
| **404 sur toutes les pages** (`/pricing`, `/login`, `/admin`…) | 1) Ouvrez `https://votre-domaine/ping` : si 404 → le `.htaccess` **manque à la racine** (les clients FTP masquent les fichiers `.xxx` — activez « afficher les fichiers cachés » et re-téléversez-le) ou le domaine pointe vers le **mauvais dossier** (cPanel → Domaines → racine du document = le dossier contenant `index.php`). 2) Si `/ping` répond `pong … db=ok` mais une page précise 404 → le code déployé est **incomplet/ancien** : re-téléversez TOUT (`application/`, `system/`, `index.php`, `.htaccess`, `.env`) — notamment `application/config/routes.php` et `application/controllers/Home.php`. 3) `db=FAIL` → identifiants MySQL de production dans `application/config/database.php`. |
| 404 après une mise à jour partielle | Ne jamais téléverser seulement les vues : `routes.php`, les contrôleurs et les vues vont ensemble. Re-déployez le dossier `application/` complet + ré-importez les nouveautés de `install.sql` (nouvelles tables/colonnes : `videos`, `photos.size_bytes`, `subscriptions.storage_quota_mo`…). |
| Les photos restent « en file » | Le cron `process_uploads` tourne-t-il ? Testez en SSH : `php index.php cron process_uploads`. |
| HEIC non traité | Imagick + libheif absents → fournir du JPEG. |
| Upload échoue (gros fichiers) | Augmentez `upload_max_filesize` / `post_max_size` (cPanel → MultiPHP INI Editor). |
| Écriture impossible dans uploads | `chmod 775 uploads`. |

---

## 12. Hors périmètre (structuré pour, non codé)

Reconnaissance faciale, paiement Mobile Money automatique (l'activation
manuelle des abonnements par le super admin est en place). Les
sous-domaines par studio sont, eux, **codés et documentés** (§6bis) —
il ne reste que la configuration hébergement (wildcard cPanel + DNS).
#   a r c h i v e n t s . c o m  
 