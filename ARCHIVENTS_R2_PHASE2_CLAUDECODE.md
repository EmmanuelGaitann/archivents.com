# Archivents — Phase 2 : intégration R2 dans CodeIgniter 3

> Handoff pour Claude Code. L'infra Cloudflare/R2 est déjà montée et fonctionnelle :
> buckets créés, domaine `cdn.archivents.com` actif, CORS configuré, certificat d'origine en place (SSL Full strict).
> Cette phase branche le stockage sur l'application CI3.

---

## 0. Contexte à connaître

- **Stack** : CodeIgniter 3 (PHP), MySQL 8.0 (port 3307, legacy auth).
- **Architecture** : le serveur ne touche jamais les octets média. Upload navigateur → R2 en direct via URL présignée. Affichage via `cdn.archivents.com` (edge Cloudflare, egress gratuit).
- **⚠️ R2 est sensible à la casse** : `test.JPG` ≠ `test.jpg`. **Ne jamais réutiliser le nom de fichier de l'utilisateur comme clé.** Toujours générer une clé normalisée en minuscules (voir `buildKey()` ci-dessous).
- **Convention de clé** : `tenant/event_id/uuid.ext` en minuscules, ex. `studio-mbappe/evt-2026-07/a1b2c3d4.jpg`.

---

## 1. Valeurs d'environnement (déjà connues)

Créer un fichier `.env` **non versionné** (l'ajouter à `.gitignore`). Le `R2_SECRET_ACCESS_KEY` provient du jeton API R2 généré (à copier depuis le gestionnaire de secrets) :

```
R2_ACCOUNT_ID=08be184955efc6ebea1f8c3619081003
R2_ENDPOINT=https://08be184955efc6ebea1f8c3619081003.r2.cloudflarestorage.com
R2_BUCKET=archivents-prod
R2_ACCESS_KEY_ID=<clé du jeton R2>
R2_SECRET_ACCESS_KEY=<secret du jeton R2>
R2_PUBLIC_URL=https://cdn.archivents.com
```

> Migration future vers Hetzner : ce fichier reste **identique**, on ne change rien côté R2.

---

## 2. Installer le SDK

À la racine du projet :

```bash
composer require aws/aws-sdk-php vlucas/phpdotenv
```

Charger `vendor/autoload.php` et le `.env` au bootstrap. Dans `index.php` (avant le chargement de CI) ou via un hook `pre_system` :

```php
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
```

> **Piège connu** : selon la version de phpdotenv, `getenv()` peut ne rien renvoyer.
> Si c'est le cas, lire via `$_ENV['R2_...']` au lieu de `getenv('R2_...')` dans la librairie ci-dessous.

---

## 3. Librairie R2

`application/libraries/R2.php` :

```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use Aws\S3\S3Client;

class R2 {

    protected $s3;
    protected $bucket;
    protected $publicUrl;

    public function __construct()
    {
        $this->bucket    = $_ENV['R2_BUCKET'];
        $this->publicUrl = rtrim($_ENV['R2_PUBLIC_URL'], '/');

        $this->s3 = new S3Client([
            'version'                 => 'latest',
            'region'                  => 'auto',
            'endpoint'                => $_ENV['R2_ENDPOINT'],
            'use_path_style_endpoint' => true,
            'credentials'             => [
                'key'    => $_ENV['R2_ACCESS_KEY_ID'],
                'secret' => $_ENV['R2_SECRET_ACCESS_KEY'],
            ],
        ]);
    }

    /**
     * Génère une clé d'objet propre et normalisée.
     * Ne JAMAIS réutiliser le nom de fichier original (casse, accents, espaces, collisions).
     */
    public function buildKey($tenant, $eventId, $originalFilename)
    {
        $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        // normaliser jpeg -> jpg
        if ($ext === 'jpeg') { $ext = 'jpg'; }
        $uuid = bin2hex(random_bytes(8));

        $slug = function ($s) {
            $s = strtolower($s);
            $s = preg_replace('/[^a-z0-9\-]+/', '-', $s);
            return trim($s, '-');
        };

        return $slug($tenant) . '/' . $slug($eventId) . '/' . $uuid . '.' . $ext;
    }

    /** URL présignée : le NAVIGATEUR uploade directement vers R2 (fichiers <= 5 Go) */
    public function presignUpload($key, $contentType = 'image/jpeg', $expires = '+15 minutes')
    {
        $cmd = $this->s3->getCommand('PutObject', [
            'Bucket'      => $this->bucket,
            'Key'         => $key,
            'ContentType' => $contentType,
        ]);
        return (string) $this->s3->createPresignedRequest($cmd, $expires)->getUri();
    }

    /** URL publique de lecture via le domaine CDN */
    public function publicUrl($key)
    {
        return $this->publicUrl . '/' . ltrim($key, '/');
    }

    /** Upload côté serveur (worker de dérivés WebP, etc.) */
    public function put($key, $body, $contentType = 'image/webp')
    {
        return $this->s3->putObject([
            'Bucket'       => $this->bucket,
            'Key'          => $key,
            'Body'         => $body,
            'ContentType'  => $contentType,
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);
    }

    public function delete($key)
    {
        return $this->s3->deleteObject([
            'Bucket' => $this->bucket,
            'Key'    => $key,
        ]);
    }

    /** Vérifie qu'un objet existe (confirmation post-upload) */
    public function exists($key)
    {
        return $this->s3->doesObjectExist($this->bucket, $key);
    }
}
```

---

## 4. Flux d'upload présigné (photos)

Deux endpoints côté app + l'upload direct côté navigateur.

**A. Endpoint « demander une URL d'upload »** — `application/controllers/Upload.php` :

```php
public function sign()
{
    $this->load->library('r2');

    $tenant   = $this->input->post('tenant');    // depuis la session/DB, pas l'utilisateur brut
    $eventId  = $this->input->post('event_id');
    $filename = $this->input->post('filename');
    $type     = $this->input->post('content_type') ?: 'image/jpeg';

    $key = $this->r2->buildKey($tenant, $eventId, $filename);
    $url = $this->r2->presignUpload($key, $type);

    // enregistrer la métadonnée en base avec statut "pending"
    $this->photo_model->create([
        'tenant'   => $tenant,
        'event_id' => $eventId,
        'r2_key'   => $key,
        'status'   => 'pending',
    ]);

    echo json_encode(['upload_url' => $url, 'key' => $key]);
}
```

**B. Endpoint « confirmer »** — appelé par le navigateur après le PUT réussi :

```php
public function confirm()
{
    $this->load->library('r2');
    $key = $this->input->post('key');

    if ($this->r2->exists($key)) {
        $this->photo_model->mark_ready($key);   // status -> "ready"
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false]);
    }
}
```

**C. Côté navigateur** — flux : demander l'URL → PUT direct vers R2 → confirmer :

```javascript
async function uploadPhoto(file, tenant, eventId) {
  // 1. demander l'URL présignée
  const signRes = await fetch('/upload/sign', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
      tenant, event_id: eventId,
      filename: file.name, content_type: file.type
    })
  });
  const { upload_url, key } = await signRes.json();

  // 2. PUT direct vers R2 (ne passe PAS par le serveur)
  await fetch(upload_url, {
    method: 'PUT',
    headers: {'Content-Type': file.type},
    body: file
  });

  // 3. confirmer
  await fetch('/upload/confirm', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ key })
  });

  return key;
}
```

---

## 5. Affichage des galeries

Les vues n'appellent jamais le serveur pour les octets : elles construisent l'URL CDN.

```php
// dans le contrôleur galerie
$photo['url'] = $this->r2->publicUrl($photo['r2_key']);
```

Pour les miniatures / tailles d'affichage en WebP/AVIF, utiliser les **transformations d'image Cloudflare** à la volée plutôt que de convertir sur le serveur. Servir via une URL de transformation avec `format=auto` (sert WebP ou AVIF selon le navigateur, compté comme UNE seule transformation). Réserver le téléchargement des originaux full-res aux forfaits Pro/Studio.

---

## 6. Vidéo (forfaits haut de gamme) — upload multipart reprenable

Les fichiers > 5 Go ne passent pas en `PutObject` simple : il faut l'**upload multipart** (S3 multipart, supporté par R2). Le serveur n'est toujours pas dans le flux d'octets ; il orchestre juste les parties.

Points d'implémentation pour Claude Code :

- Créer l'upload multipart : `createMultipartUpload` → récupérer l'`UploadId`.
- Présigner chaque partie : commande `UploadPart` (Bucket, Key, UploadId, PartNumber) → URL présignée par partie.
- Le navigateur envoie chaque partie en PUT direct vers R2, collecte les `ETag`.
- Finaliser : `completeMultipartUpload` avec la liste des parties (PartNumber + ETag).
- Côté navigateur, utiliser **Uppy** (plugin AWS S3 multipart) qui gère le découpage, le parallélisme et la **reprise** après coupure réseau — indispensable pour les connexions locales.
- La « Default Multipart Abort Rule » (déjà active sur le bucket, 7 jours) nettoie automatiquement les uploads inachevés.
- **Exiger un MP4 déjà prêt web** (H.264/AAC) à l'upload : pas de transcodage serveur. R2 gère les *range requests*, donc lecture fluide et seekable dans le navigateur.
- Dimensionner les quotas Go du forfait vidéo en « nombre d'événements vidéo » (une vidéo = 2 à 20 Go).

---

## 7. Ordre de test

1. Charger `.env`, vérifier que les 6 variables sont lues (test rapide `var_dump($_ENV['R2_BUCKET'])`).
2. Tester un upload photo complet (sign → PUT → confirm) depuis un vrai formulaire.
3. Vérifier l'affichage via `https://cdn.archivents.com/<clé>`.
4. Vérifier la normalisation : uploader `IMG_1234.JPG` → la clé stockée doit être en minuscules `.jpg`.
5. Puis brancher le multipart pour la vidéo.

---

## Checklist Phase 2

- [ ] `composer require aws/aws-sdk-php vlucas/phpdotenv`
- [ ] `.env` rempli (6 variables) et hors Git
- [ ] Bootstrap charge autoload + dotenv
- [ ] `R2.php` en place, clés générées via `buildKey()` (minuscules)
- [ ] Endpoints `sign` + `confirm` fonctionnels
- [ ] Upload direct navigateur OK, statut `pending` → `ready`
- [ ] Affichage galerie via `cdn.archivents.com`
- [ ] Transformations d'image `format=auto` pour les miniatures
- [ ] Upload multipart reprenable (Uppy) pour la vidéo
- [ ] Quotas Go du forfait vidéo dimensionnés « par événement vidéo »
```