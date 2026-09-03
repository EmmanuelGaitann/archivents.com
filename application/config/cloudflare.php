<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| =====================================================================
|  ARCHIVENTS — Configuration Cloudflare (R2 + Images à l'edge)
| ---------------------------------------------------------------------
|  Stratégie (validée) :
|   - Les ORIGINAUX (photos/vidéos) vont du NAVIGATEUR vers R2 en upload
|     PRÉSIGNÉ DIRECT : ils ne transitent JAMAIS par le serveur mutualisé.
|     Le serveur ne fait qu'émettre une URL présignée (aucun CPU/disque).
|   - Les VIGNETTES/WebP ne sont PAS générées côté serveur : Cloudflare
|     Images les produit À LA VOLÉE à l'edge (format=auto -> AVIF/WebP/JPEG
|     selon le navigateur, une seule transformation facturable + cache).
|   - La VIDÉO est stockée telle quelle sur R2 (egress gratuit) et servie
|     en range-requests (lecture seekable). MP4 web-ready (H.264/AAC) exigé,
|     AUCUN transcodage serveur. Gros fichiers -> upload multipart reprenable.
|
|  Secrets : renseignés par variable d'environnement de préférence
|  (getenv), sinon en clair ci-dessous. NE PAS committer de vrais secrets.
| =====================================================================
*/

/* ---------------------------------------------------------------------
 |  Pilote de stockage : 'local' (pipeline disque actuel) | 'r2'
 |  Tant que R2 n'est pas configuré, on reste en 'local' (rien ne change).
 | --------------------------------------------------------------------- */
$config['storage_driver'] = getenv('ARCHIVENTS_STORAGE') ?: 'local';

/* ---------------------------------------------------------------------
 |  Cloudflare R2 (stockage objet, API S3-compatible)
 | --------------------------------------------------------------------- */
$config['r2'] = array(
    'account_id'    => getenv('R2_ACCOUNT_ID')    ?: '',      // ID de compte Cloudflare
    'access_key_id' => getenv('R2_ACCESS_KEY_ID')     ?: '',  // jeton d'accès R2
    'secret_key'    => getenv('R2_SECRET_ACCESS_KEY') ?: '',  // secret R2
    'bucket'        => getenv('R2_BUCKET')            ?: 'archivents-prod',
    'region'        => 'auto',
    // Laisser vide : calculé depuis account_id -> https://<id>.r2.cloudflarestorage.com
    'endpoint'      => getenv('R2_ENDPOINT')          ?: '',
    // Domaine public du bucket (bucket lié à un domaine perso), ex : https://cdn.archivents.com
    // Sert la LECTURE des originaux (vidéo servie telle quelle, téléchargement HD).
    'public_base'   => getenv('R2_PUBLIC_URL')        ?: '',
);

/* ---------------------------------------------------------------------
 |  Cloudflare Images — transformations à l'edge (WebP/AVIF auto)
 |  URL type : https://cdn.archivents.com/cdn-cgi/image/<options>/<clé R2>
 | --------------------------------------------------------------------- */
$config['cf_images'] = array(
    'enabled'         => TRUE,
    // Zone servant les transformations (souvent = public_base du bucket).
    'delivery_base'   => getenv('CF_IMAGES_BASE') ?: '',
    'path_prefix'     => '/cdn-cgi/image',
    'default_quality' => 82,
    // IMPORTANT : toujours format=auto (une seule transformation facturable,
    // au lieu de webp + avif séparés qui doubleraient la facture).
    'format'          => 'auto',
    'fit'             => 'scale-down',
    // Presets de largeur (px). 0 = original (pas de redimensionnement).
    'sizes' => array(
        'thumb'  => 300,   // grille
        'medium' => 1600,  // lightbox
        'full'   => 2400,  // grand écran
        'orig'   => 0,     // original (téléchargement / repli)
    ),
);

/* ---------------------------------------------------------------------
 |  Vidéo (forfaits Pro & Studio)
 |  MP4 web-ready exigé (H.264/AAC) : stocké et servi tel quel depuis R2.
 |  Gros fichiers -> upload multipart reprenable (S3 multipart / Uppy).
 | --------------------------------------------------------------------- */
$config['video'] = array(
    'allowed_mime'        => array('video/mp4'),
    'max_bytes'           => 20 * 1024 * 1024 * 1024, // 20 Go / fichier
    // Au-delà de ce seuil : upload multipart (PUT présigné simple plafonne à 5 Go).
    'multipart_threshold' => 100 * 1024 * 1024,       // 100 Mo
    'part_size'           => 64 * 1024 * 1024,        // 64 Mo / part
);
