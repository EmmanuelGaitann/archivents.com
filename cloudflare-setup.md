# Archivents — Stockage Cloudflare (R2 + Images) — Guide de mise en place

Objectif : **les originaux (photos & vidéos) sont stockés sur Cloudflare R2**, les
vignettes/WebP sont générées **à l'edge** par Cloudflare Images, et l'upload va
**directement du navigateur vers R2** (le serveur mutualisé n'est jamais chargé).

Ce guide liste ce que **toi** tu configures sur Cloudflare, et ce que **le code**
attend en retour.

---

## 1. Ce que tu crées côté Cloudflare

1. **Bucket R2** (ex. `archivents`).
2. **Jeton API R2** (Access Key ID + Secret) avec droits lecture/écriture sur le bucket.
3. **Domaine public** du bucket : lie le bucket à un sous-domaine, ex. `cdn.archivents.com`
   (R2 → Settings → Custom Domain). C'est lui qui sert les originaux **et** les transformations.
4. **Cloudflare Images / Transformations** : active « Image Resizing / Transformations »
   sur la zone `archivents.com` (Speed → Optimization → Image Transformations → *Enable*).
   → Les URLs `https://cdn.archivents.com/cdn-cgi/image/...` deviennent actives.
5. **CORS du bucket** (indispensable pour l'upload direct depuis le navigateur) :
   autorise `PUT`, `POST`, `GET` depuis `https://*.archivents.com` (et `http://localhost`
   pour les tests), en-têtes `*`, expose `ETag` (nécessaire au multipart).

## 2. Ce que tu me donnes (ou renseignes en variables d'environnement)

| Variable | Exemple | Rôle |
|---|---|---|
| `R2_ACCOUNT_ID` | `a1b2c3…` | ID de compte Cloudflare |
| `R2_ACCESS_KEY_ID` | `…` | jeton R2 |
| `R2_SECRET_KEY` | `…` | secret R2 |
| `R2_BUCKET` | `archivents` | nom du bucket |
| `R2_PUBLIC_BASE` | `https://cdn.archivents.com` | lecture des originaux (vidéo, HD) |
| `CF_IMAGES_BASE` | `https://cdn.archivents.com` | base des transformations d'images |
| `ARCHIVENTS_STORAGE` | `r2` | bascule le pilote de stockage (`local` par défaut) |

Ces valeurs se règlent **sans committer de secret** :
[application/config/cloudflare.php](application/config/cloudflare.php) les lit d'abord via
`getenv()`. Sur O2Switch : `SetEnv` dans `.htaccess`, ou un fichier PHP non versionné inclus
avant l'app. En local : variables d'environnement, ou remplir les placeholders du fichier.

## 3. Ce que le code fournit déjà

- [application/config/cloudflare.php](application/config/cloudflare.php) — tous les paramètres
  (R2, presets d'images `thumb/medium/full`, `format=auto`, quotas vidéo), autoloadé.
- [application/libraries/Cf_storage.php](application/libraries/Cf_storage.php) :
  - `image_url($key, 'thumb'|'medium'|'full')` → URL de transformation edge (**format=auto**).
  - `original_url($key)` → original brut (vidéo/HD).
  - `presign_put($key)` → **URL présignée** pour l'upload direct navigateur → R2.
  - `presign('PUT'|'POST'…, $key, $extraQuery)` → brique SigV4 réutilisée par le **multipart**.
  - `object_key($eventSlug, 'photo'|'video', $ext)` → clé non devinable rangée par événement.

## 4. Ce qu'il reste à brancher (prochaines étapes, dès que R2 est prêt)

1. **Endpoint serveur** `POST /admin/uploads/sign` → renvoie l'URL présignée (photo/vidéo).
2. **Client d'upload direct** (dropzone + **Uppy AWS S3 / multipart** pour la vidéo reprenable).
3. **Schéma `photos`** : stocker la **clé R2** (au lieu des 4 chemins disque) ; table `videos`
   (ou `media`) pour les vidéos (clé R2, durée, taille).
4. **Rendu galerie** : `<img>` via `image_url()` (srcset thumb/medium), `<video>` via `original_url()`.
5. **Quota** : décompte des **octets réels** sur R2 par tenant (le Go par forfait).

> Note vidéo : MP4 web-ready (H.264/AAC) exigé, **aucun transcodage serveur**. Fichiers > 5 Go
> ⇒ upload **multipart reprenable**. Pas de Cloudflare Stream nécessaire pour lancer.

Tant que `ARCHIVENTS_STORAGE=local` (défaut), **rien ne change** : le pipeline disque actuel
reste en place. La bascule se fait quand les identifiants R2 sont renseignés.
