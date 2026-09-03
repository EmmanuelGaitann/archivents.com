<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| =====================================================================
|  ARCHIVEN — Constantes applicatives
|  (Remplace l'usage d'un .env, non natif à CodeIgniter 3.)
|  Ce fichier est chargé automatiquement (voir config/autoload.php).
|  Accès : $this->config->item('clé') ou define() ci-dessous.
| =====================================================================
*/

/* ---------------------------------------------------------------------
 |  Chemins de stockage des uploads
 |  uploads/ est SOUS la racine web pour être servi en statique
 |  par LiteSpeed (thumb/medium), sans passer par PHP.
 | --------------------------------------------------------------------- */

// Dossier physique racine des uploads (avec slash final).
$config['upload_root'] = FCPATH . 'uploads' . DIRECTORY_SEPARATOR;

// Dossier temporaire des fichiers sources en attente de traitement.
$config['upload_incoming'] = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . '_incoming' . DIRECTORY_SEPARATOR;

// Sous-dossiers générés par événement : uploads/{slug}/{thumb|medium|full|original}/
$config['upload_variants'] = array('thumb', 'medium', 'full', 'original');

/* ---------------------------------------------------------------------
 |  Pipeline images
 | --------------------------------------------------------------------- */
$config['img_thumb_px']   = 300;   // plus grand côté de la vignette
$config['img_medium_px']  = 1600;  // plus grand côté du medium (lightbox)
$config['img_full_quality'] = 90;  // qualité WebP du "full" (pleine résolution)
$config['img_webp_quality'] = 82;  // qualité WebP thumb/medium

// Types MIME acceptés à l'upload.
$config['upload_allowed_mime'] = array(
    'image/jpeg', 'image/png', 'image/heic', 'image/heif',
);

/* ---------------------------------------------------------------------
 |  Rétention des originaux
 |  Par défaut : date de l'événement + 48h.
 | --------------------------------------------------------------------- */
$config['retention_default_hours'] = 48;

/* ---------------------------------------------------------------------
 |  Galerie publique
 | --------------------------------------------------------------------- */
$config['gallery_page_size'] = 40; // photos par paquet (scroll infini)

/* ---------------------------------------------------------------------
 |  Rôles & permissions — système extensible (pas un simple booléen).
 |  Source de vérité applicative ; la table `roles` en base la double
 |  (et permet une édition future sans redéploiement).
 |  '*' = toutes les permissions.
 | --------------------------------------------------------------------- */
$config['role_permissions'] = array(

    'super_admin' => array('*'),

    // « photographe » = le tenant qui s'inscrit et s'abonne (ex-« admin »).
    // Gère SES événements (cloisonné). La création d'événement est ouverte
    // au photographe mais bornée par le quota de son plan (contrôle applicatif,
    // Phase 4), et non par une permission statique. La rétention, les
    // utilisateurs et la gestion des abonnements restent au super_admin.
    'photographe' => array(
        'event.create',     // crée SES événements — borné par le quota du plan (contrôle applicatif)
        'album.crud',       // CRUD des dossiers/albums (dans ses événements)
        'photo.upload',     // upload + gestion des photos
        'settings.edit',    // paramètres de l'événement (thème, mode, branding…)
        'site_texts.edit',  // titres du site et des pages
        'stats.view',       // statistiques de ses événements
        'collab.manage',    // invite/gère ses collaborateurs — borné par max_collaborators du plan
    ),

    // « collaborateur » = compte invité par un photographe (users.parent_user_id).
    // Il travaille sur les événements du TITULAIRE (photos, albums, paramètres,
    // stats) mais ne crée pas d'événement et ne touche ni à l'abonnement ni
    // aux collaborateurs. Ses quotas sont ceux du titulaire.
    'collaborateur' => array(
        'album.crud',
        'photo.upload',
        'settings.edit',
        'site_texts.edit',
        'stats.view',
    ),
);

/* ---------------------------------------------------------------------
 |  Permissions réservées au super_admin (référence / documentation).
 |  Un « admin » ne les possède jamais.
 | --------------------------------------------------------------------- */
$config['superadmin_only_permissions'] = array(
    'users.manage',         // gestion des utilisateurs
    'event.delete',         // suppression d'un événement
    'event.assign',         // attribution d'un événement à un admin
    'retention.manage',     // prolonger / purger la rétention
    'subscriptions.manage', // back-office des abonnements (activation manuelle)
    'system.monitor',       // page Système (cron, stockage, files, erreurs)
);
