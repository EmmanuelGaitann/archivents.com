-- =====================================================================
--  Archiven — Galerie photo d'événement (CodeIgniter 3)
--  install.sql : schéma complet + données de démo (seed)
--
--  À importer via phpMyAdmin sur la base de votre choix.
--  Charset : utf8mb4 (emoji / accents). Moteur : InnoDB (clés étrangères).
--
--  IMPORTANT : ce script ne crée PAS la base. Créez d'abord une base
--  (ex. "archiven") dans phpMyAdmin puis importez ce fichier dedans.
--
--  /!\ INSTALLATION NEUVE UNIQUEMENT (local / première mise en ligne).
--  NE JAMAIS l'importer sur une base de PRODUCTION existante : les
--  DROP TABLE effaceraient toutes les données (comptes, événements,
--  photos). Pour mettre à niveau une base existante, utilisez
--  migration-prod-full-2026-07-10.sql (additif et rejouable).
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------
--  Table : roles  (rôles stockés en base — système extensible)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(50)  NOT NULL,           -- super_admin | editor | ...
  `nom`         VARCHAR(100) NOT NULL,
  `permissions` JSON         NULL,               -- liste des permissions accordées
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Table : users
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`                     VARCHAR(150) NOT NULL,
  `email`                   VARCHAR(190) NOT NULL,
  `password_hash`           VARCHAR(255) NOT NULL,
  `role`                    VARCHAR(50)  NOT NULL DEFAULT 'photographe', -- slug de roles
  `parent_user_id`          INT UNSIGNED NULL,        -- collaborateur : id du photographe titulaire
  `actif`                   TINYINT(1)   NOT NULL DEFAULT 1,
  -- --- SaaS : le photographe est un tenant qui s'inscrit et s'abonne ---
  `studio_slug`             VARCHAR(190) NULL,        -- espace du photographe (ex : sonstudio) ; futur sous-domaine
  `email_verified`          TINYINT(1)   NOT NULL DEFAULT 0,
  `current_plan_id`         INT UNSIGNED NULL,        -- plan en cours (dénormalisé, géré par l'app)
  `current_subscription_id` INT UNSIGNED NULL,        -- abonnement actif (dénormalisé, géré par l'app)
  `created_at`              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_studio` (`studio_slug`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_plan` (`current_plan_id`),
  KEY `idx_users_parent` (`parent_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Table : events  (un event = futur tenant)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NULL,               -- propriétaire (admin attribué) ; NULL = non attribué
  `nom`         VARCHAR(190) NOT NULL,
  `type`        ENUM('mariage','seminaire','anniversaire','bapteme','corporate','autre') NOT NULL DEFAULT 'mariage',
  `date_evt`    DATE         NULL,
  `slug`        VARCHAR(190) NOT NULL,            -- identifiant lisible (admin / dossiers fichiers)
  `public_code` VARCHAR(32)  NOT NULL,            -- jeton non devinable de l'URL invité /e/{code}
  `statut`      ENUM('actif','archive') NOT NULL DEFAULT 'actif',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_events_slug` (`slug`),
  UNIQUE KEY `uq_events_code` (`public_code`),
  KEY `idx_events_user` (`user_id`),
  CONSTRAINT `fk_events_user` FOREIGN KEY (`user_id`)
      REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Table : event_settings  (1-1 avec events ; branding par événement)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `event_settings`;
CREATE TABLE `event_settings` (
  `event_id`                  INT UNSIGNED NOT NULL,
  `site_title`                VARCHAR(190) NULL,
  `page_titles`               JSON         NULL,
  `gallery_mode`              ENUM('photos_only','folders_only','folders_first') NOT NULL DEFAULT 'folders_first',
  `password_enabled`          TINYINT(1)   NOT NULL DEFAULT 0,
  `password_hash`             VARCHAR(255) NULL,
  `loading_screen_enabled`    TINYINT(1)   NOT NULL DEFAULT 1,
  `loading_logo_choice`       ENUM('photographer','couple_monogram') NOT NULL DEFAULT 'couple_monogram',
  `loading_screen_duration`   SMALLINT UNSIGNED NOT NULL DEFAULT 600,
  `originals_available_until` DATETIME     NULL,
  `footer_couple_info`        JSON         NULL,
  `header_options`            JSON         NULL,
  `couple_photo_path`         VARCHAR(255) NULL,
  `logo_photographer_path`    VARCHAR(255) NULL,
  `logo_couple_path`          VARCHAR(255) NULL,
  `theme`                     JSON         NULL,
  PRIMARY KEY (`event_id`),
  CONSTRAINT `fk_settings_event` FOREIGN KEY (`event_id`)
      REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Table : albums
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `albums`;
CREATE TABLE `albums` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` INT UNSIGNED NOT NULL,
  `nom`      VARCHAR(190) NOT NULL,
  `ordre`    INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_albums_event` (`event_id`),
  CONSTRAINT `fk_albums_event` FOREIGN KEY (`event_id`)
      REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Table : photos
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `photos`;
CREATE TABLE `photos` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`         INT UNSIGNED NOT NULL,
  `album_id`         INT UNSIGNED NULL,
  `filename_base`    VARCHAR(190) NOT NULL,        -- hash non devinable, sans extension
  `r2_key`           VARCHAR(255) NULL,            -- clé objet Cloudflare R2 (mode stockage r2)
  `size_bytes`       BIGINT UNSIGNED NULL,         -- poids de l'original (quota de stockage)
  `path_thumb_webp`  VARCHAR(255) NULL,
  `path_medium_webp` VARCHAR(255) NULL,
  `path_full_webp`   VARCHAR(255) NULL,
  `path_original`    VARCHAR(255) NULL,
  `original_purged`  TINYINT(1)   NOT NULL DEFAULT 0,
  `status`           ENUM('pending','ready') NOT NULL DEFAULT 'ready',  -- upload direct R2
  `largeur`          INT          NULL,
  `hauteur`          INT          NULL,
  `ordre`            INT          NOT NULL DEFAULT 0,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_photos_event` (`event_id`),
  KEY `idx_photos_album` (`album_id`),
  KEY `idx_photos_purged` (`original_purged`),
  KEY `idx_photos_status` (`status`),
  CONSTRAINT `fk_photos_event` FOREIGN KEY (`event_id`)
      REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_photos_album` FOREIGN KEY (`album_id`)
      REFERENCES `albums` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Table : videos  (forfaits Pro & Studio — MP4 web-ready stocké sur R2)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `videos`;
CREATE TABLE `videos` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`    INT UNSIGNED NOT NULL,
  `album_id`    INT UNSIGNED NULL,
  `titre`       VARCHAR(190) NULL,
  `r2_key`      VARCHAR(255) NOT NULL,
  `upload_id`   VARCHAR(191) NULL,                -- id d'upload multipart en cours
  `size_bytes`  BIGINT UNSIGNED NULL,
  `duration_s`  INT UNSIGNED NULL,
  `largeur`     INT NULL,
  `hauteur`     INT NULL,
  `status`      ENUM('pending','ready') NOT NULL DEFAULT 'pending',
  `ordre`       INT NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_videos_event` (`event_id`),
  KEY `idx_videos_album` (`album_id`),
  KEY `idx_videos_status` (`status`),
  CONSTRAINT `fk_videos_event` FOREIGN KEY (`event_id`)
      REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_videos_album` FOREIGN KEY (`album_id`)
      REFERENCES `albums` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Table : r2_orphans  (objets R2 dont la suppression a échoué — réseau)
--  Chaque suppression ratée est mise en file ici et re-tentée par le
--  cron purge_media, pour ne jamais payer un stockage orphelin.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `r2_orphans`;
CREATE TABLE `r2_orphans` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `r2_key`     VARCHAR(255) NOT NULL,
  `upload_id`  VARCHAR(255) NULL,                 -- multipart en cours à avorter (sinon NULL)
  `note`       VARCHAR(190) NULL,                 -- provenance (photo #id, vidéo #id…)
  `attempts`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orph_key` (`r2_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Table : upload_jobs  (file d'attente de traitement)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `upload_jobs`;
CREATE TABLE `upload_jobs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`    INT UNSIGNED NOT NULL,
  `album_id`    INT UNSIGNED NULL,
  `source_path` VARCHAR(255) NOT NULL,
  `statut`      ENUM('pending','processing','done','error') NOT NULL DEFAULT 'pending',
  `error_msg`   TEXT         NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_jobs_statut` (`statut`),
  KEY `idx_jobs_event` (`event_id`),
  CONSTRAINT `fk_jobs_event` FOREIGN KEY (`event_id`)
      REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_jobs_album` FOREIGN KEY (`album_id`)
      REFERENCES `albums` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Table : visitors
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `visitors`;
CREATE TABLE `visitors` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`         INT UNSIGNED NOT NULL,
  `visitor_uid`      VARCHAR(64)  NOT NULL,
  `first_seen`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip`               VARCHAR(45)  NULL,
  `user_agent`       VARCHAR(255) NULL,
  `fingerprint_hash` VARCHAR(64)  NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_visitor_uid` (`visitor_uid`),
  KEY `idx_visitors_event` (`event_id`),
  CONSTRAINT `fk_visitors_event` FOREIGN KEY (`event_id`)
      REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Table : visit_events  (journal d'évènements de visite)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `visit_events`;
CREATE TABLE `visit_events` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`   INT UNSIGNED NOT NULL,
  `visitor_id` INT UNSIGNED NOT NULL,
  `type`       ENUM('open','album_view','photo_view','download') NOT NULL,
  `album_id`   INT UNSIGNED NULL,
  `photo_id`   INT UNSIGNED NULL,
  `source`     ENUM('qr','link') NOT NULL DEFAULT 'link',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ve_event` (`event_id`),
  KEY `idx_ve_visitor` (`visitor_id`),
  KEY `idx_ve_type` (`type`),
  KEY `idx_ve_created` (`created_at`),
  CONSTRAINT `fk_ve_event` FOREIGN KEY (`event_id`)
      REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ve_visitor` FOREIGN KEY (`visitor_id`)
      REFERENCES `visitors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Table : plans  (forfaits / abonnements — catalogue SaaS)
--  Un « plan » = une offre : Pass événement (paiement unique),
--  ou abonnement mensuel/annuel des paliers Essentiel/Studio/Signature.
--  Les quotas (max_events, stockage, rétention…) pilotent les limites.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `plans`;
CREATE TABLE `plans` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`              VARCHAR(60)  NOT NULL,            -- ex : studio_mensuel
  `nom`               VARCHAR(120) NOT NULL,
  `tier`              ENUM('gratuit','pass','essentiel','studio','signature') NOT NULL,
  `billing_period`    ENUM('free','per_event','monthly','yearly') NOT NULL,
  `prix`              INT UNSIGNED NOT NULL DEFAULT 0,  -- montant entier (FCFA)
  `devise`            CHAR(3)      NOT NULL DEFAULT 'XAF',
  `max_events`        INT UNSIGNED NULL,                -- NULL = illimité
  `max_photos`        INT UNSIGNED NULL,                -- plafond photos/événement ; NULL = illimité
  `storage_mo`        INT UNSIGNED NULL,                -- quota disque en Mo ; NULL = illimité
  `retention_days`    SMALLINT UNSIGNED NULL,           -- durée de conservation des originaux
  `max_collaborators` SMALLINT UNSIGNED NULL,           -- NULL = illimité ; 0 = aucun
  `remove_branding`   TINYINT(1)   NOT NULL DEFAULT 0,  -- 1 = retire « Propulsé par Archivents »
  `watermark`         TINYINT(1)   NOT NULL DEFAULT 0,  -- 1 = filigrane Archivents sur la galerie
  `video`             TINYINT(1)   NOT NULL DEFAULT 0,  -- 1 = ajout de vidéos autorisé
  `gallery_password`  TINYINT(1)   NOT NULL DEFAULT 0,  -- 1 = galerie protégeable par mot de passe (« lien chiffré »)
  `custom_domain`     TINYINT(1)   NOT NULL DEFAULT 0,  -- 1 = domaine personnalisé autorisé
  `features`          JSON         NULL,                -- extensible (stats, hd_download, support…)
  `actif`             TINYINT(1)   NOT NULL DEFAULT 1,  -- proposé à la vente ?
  `ordre`             INT          NOT NULL DEFAULT 0,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plans_slug` (`slug`),
  KEY `idx_plans_tier` (`tier`),
  KEY `idx_plans_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Table : subscriptions  (abonnement d'un photographe à un plan)
--  Source de vérité de l'état d'abonnement (statut, expiration, quota).
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `plan_id`      INT UNSIGNED NOT NULL,
  `statut`       ENUM('en_attente','actif','expire','annule') NOT NULL DEFAULT 'en_attente',
  `started_at`   DATETIME     NULL,
  `expires_at`   DATETIME     NULL,                 -- NULL = sans expiration (ex : pass événement)
  `events_quota` INT UNSIGNED NULL,                 -- quota effectif (copie de max_events, modifiable par le super admin) ; NULL = celui du plan ; 0 = illimité
  `storage_quota_mo` INT UNSIGNED NULL,             -- dérogation stockage accordée par le super admin (Mo) ; NULL = celui du plan ; 0 = illimité
  `events_used`  INT UNSIGNED NOT NULL DEFAULT 0,   -- consommation (utile au pass événement)
  `note`         VARCHAR(255) NULL,                 -- ex : « activé manuellement par super_admin »
  `notif_j7`      TINYINT(1)  NOT NULL DEFAULT 0,   -- rappel J-7 envoyé (cron subscription_alerts)
  `notif_j1`      TINYINT(1)  NOT NULL DEFAULT 0,   -- rappel J-1 envoyé
  `notif_expired` TINYINT(1)  NOT NULL DEFAULT 0,   -- avis d'expiration envoyé
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_subs_user` (`user_id`),
  KEY `idx_subs_plan` (`plan_id`),
  KEY `idx_subs_statut` (`statut`),
  CONSTRAINT `fk_subs_user` FOREIGN KEY (`user_id`)
      REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_subs_plan` FOREIGN KEY (`plan_id`)
      REFERENCES `plans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Table : payments  (traces des transactions ; auto ou activation manuelle)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `subscription_id` INT UNSIGNED NULL,
  `plan_id`         INT UNSIGNED NULL,
  `montant`         INT UNSIGNED NOT NULL DEFAULT 0,
  `devise`          CHAR(3)      NOT NULL DEFAULT 'XAF',
  `methode`         ENUM('om','momo','virement','carte','manuel','autre') NOT NULL DEFAULT 'manuel',
  `reference`       VARCHAR(190) NULL,             -- réf passerelle / réf virement
  `statut`          ENUM('en_attente','paye','echoue','rembourse') NOT NULL DEFAULT 'en_attente',
  `paid_at`         DATETIME     NULL,
  `note`            VARCHAR(255) NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pay_user` (`user_id`),
  KEY `idx_pay_sub` (`subscription_id`),
  KEY `idx_pay_statut` (`statut`),
  CONSTRAINT `fk_pay_user` FOREIGN KEY (`user_id`)
      REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pay_sub` FOREIGN KEY (`subscription_id`)
      REFERENCES `subscriptions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Table : password_resets  (jetons de réinitialisation de mot de passe)
--  On ne stocke QUE le hash du jeton ; le jeton brut voyage par e-mail.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(190) NOT NULL,
  `token_hash` CHAR(64)     NOT NULL,          -- sha256 du jeton
  `expires_at` DATETIME     NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pr_email` (`email`),
  KEY `idx_pr_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Table : cron_log  (journal des tâches planifiées — page admin Système)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `cron_log`;
CREATE TABLE `cron_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task`        VARCHAR(60)  NOT NULL,            -- process_uploads | purge_originals | purge_media
  `ok`          TINYINT(1)   NOT NULL DEFAULT 1,
  `output`      TEXT         NULL,                -- sortie de la tâche (100 premières lignes)
  `started_at`  DATETIME     NOT NULL,
  `finished_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cronlog_task` (`task`, `finished_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  SEED (données de démonstration)
-- =====================================================================

-- Rôles (les permissions sont aussi définies dans app_config.php ;
--  la colonne JSON sert de source extensible / éditable en base).
INSERT INTO `roles` (`slug`, `nom`, `permissions`) VALUES
('super_admin', 'Super administrateur',
  JSON_ARRAY('*')),
-- « photographe » = le tenant qui s'inscrit et s'abonne (ex-« admin »).
-- La création d'événement reste pilotée par le quota du plan (Phase 4),
-- pas par une permission statique ici.
('photographe', 'Photographe',
  JSON_ARRAY(
    'album.crud','photo.upload','settings.edit','site_texts.edit',
    'stats.view'
  )),
-- « collaborateur » = compte invité par un photographe (users.parent_user_id).
-- Travaille sur les événements du titulaire ; ne crée pas d'événement,
-- ne touche ni à l'abonnement ni aux collaborateurs.
('collaborateur', 'Collaborateur',
  JSON_ARRAY(
    'album.crud','photo.upload','settings.edit','site_texts.edit',
    'stats.view'
  ));

-- Utilisateurs de démo
--   super_admin  : super@archiven.test  / mot de passe : Admin@123
--   photographe  : editor@archiven.test / mot de passe : Editor@123
--   Le photographe démo est abonné au plan Pro (id 3, voir plus bas).
INSERT INTO `users`
  (`nom`, `email`, `password_hash`, `role`, `actif`,
   `studio_slug`, `email_verified`, `current_plan_id`, `current_subscription_id`)
VALUES
('Super Admin', 'super@archiven.test',
  '$2y$10$3xfwCzzEUN0NfU7lW5E/J.Sh4JVM80XEtL9k.9JJ1GwKyYBhaq0Ty', 'super_admin', 1,
  NULL, 1, NULL, NULL),
('Studio Lumière', 'editor@archiven.test',
  '$2y$10$ezT4i467fSPmGOUFR.a0MeUBHRWvwPXC7NnJXgsu4YJ9hIJ/uLoIe', 'photographe', 1,
  'studio-demo', 1, 3, 1);

-- ---------------------------------------------------------------------
--  Plans (catalogue). Prix en FCFA (XAF). Grille provisoire, éditable en base.
--  Annuel = mensuel ×10 (2 mois offerts). Pass événement = paiement unique.
-- ---------------------------------------------------------------------
--  Modèle v5 : Test (gratuit) · Ponctuel (paiement unique) · Découverte / Pro /
--  Studio (mensuels). Stockage en Go ; rétention « tant que l'abonnement actif »
--  (retention_days NULL) pour les mensuels ; filigrane sur Test ; vidéo Pro+Studio.
--  (Les variantes annuelles restent en base mais désactivées : actif=0.)
INSERT INTO `plans`
  (`id`,`slug`,`nom`,`tier`,`billing_period`,`prix`,`devise`,
   `max_events`,`max_photos`,`storage_mo`,`retention_days`,`max_collaborators`,
   `remove_branding`,`watermark`,`video`,`gallery_password`,`custom_domain`,`features`,`actif`,`ordre`)
VALUES
-- Test — gratuit (1 évt, filigrane, 7 j, 1 Go, code seul)
(8,'gratuit','Test','gratuit','free', 0,'XAF',
  1, NULL, 1024, 7, 0, 0, 1, 0, 0, 0,
  JSON_OBJECT('stats','basic','hd_download',true,'theme','basic','support','community'), 1, 5),
-- Ponctuel — paiement unique (1 évt, 15 j, 5 Go, photos uniquement, code seul)
(7,'ponctuel','Ponctuel','pass','per_event', 13500,'XAF',
  1, NULL, 5120, 15, 0, 0, 0, 0, 0, 0,
  JSON_OBJECT('stats','basic','hd_download',true,'theme','basic','support','email'), 1, 10),
-- Découverte — 15 500/mois (2 évts, 10 Go, lien à code non devinable SANS mot de passe)
(1,'decouverte','Découverte','essentiel','monthly', 15500,'XAF',
  2, NULL, 10240, NULL, 0, 0, 0, 0, 0, 0,
  JSON_OBJECT('stats','basic','hd_download',true,'theme','basic','support','email'), 1, 20),
-- Pro — 27 000/mois (le plus populaire ; 5 évts, 30 Go, mot de passe, sans marque ; PAS de vidéo ni collaborateurs)
(3,'pro','Pro','studio','monthly', 27000,'XAF',
  5, NULL, 30720, NULL, 0, 1, 0, 0, 1, 0,
  JSON_OBJECT('stats','advanced','hd_download',true,'theme','full','support','priority'), 1, 30),
-- Studio — 51 000/mois (12 évts, 80 Go, vidéo, mot de passe, collaborateurs, domaine perso)
(5,'studio','Studio','signature','monthly', 51000,'XAF',
  12, NULL, 81920, NULL, NULL, 1, 0, 1, 1, 1,
  JSON_OBJECT('stats','advanced','hd_download',true,'theme','full','support','dedicated'), 1, 40),
-- --- Variantes annuelles conservées mais désactivées (actif=0) ---
(2,'essentiel_annuel','Découverte — annuel','essentiel','yearly', 155000,'XAF',
  3, NULL, 25600, 30, 0, 0, 0, 0, 0, 0,
  JSON_OBJECT('stats','basic','hd_download',true,'theme','basic','support','email'), 0, 21),
(4,'studio_annuel','Pro — annuel','studio','yearly', 270000,'XAF',
  10, NULL, 81920, 90, 0, 1, 0, 0, 1, 0,
  JSON_OBJECT('stats','advanced','hd_download',true,'theme','full','support','priority'), 0, 31),
(6,'signature_annuel','Studio — annuel','signature','yearly', 510000,'XAF',
  NULL, NULL, 256000, 365, NULL, 1, 0, 1, 1, 1,
  JSON_OBJECT('stats','advanced','hd_download',true,'theme','full','support','dedicated'), 0, 41);

-- Abonnement de démonstration (photographe démo -> Pro, id 3)
INSERT INTO `subscriptions`
  (`id`,`user_id`,`plan_id`,`statut`,`started_at`,`expires_at`,`events_quota`,`events_used`,`note`)
VALUES
(1, 2, 3, 'actif', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), 10, 0, 'Abonnement de démonstration');

-- Paiement de démonstration (activation manuelle)
INSERT INTO `payments`
  (`user_id`,`subscription_id`,`plan_id`,`montant`,`devise`,`methode`,`reference`,`statut`,`paid_at`,`note`)
VALUES
(2, 1, 3, 27000, 'XAF', 'manuel', 'DEMO-0001', 'paye', NOW(), 'Activation manuelle de démonstration');

-- Événement mariage de démo (attribué à l'admin démo, user id 2)
INSERT INTO `events` (`id`, `nom`, `type`, `date_evt`, `slug`, `public_code`, `statut`, `user_id`) VALUES
(1, 'Mariage Awa & Junior', 'mariage', '2026-07-12', 'awa-junior', 'a1b2c3d4e5', 'actif', 2);

-- Paramètres de l'événement de démo
INSERT INTO `event_settings`
  (`event_id`, `site_title`, `page_titles`, `gallery_mode`, `password_enabled`,
   `password_hash`, `loading_screen_enabled`, `loading_logo_choice`,
   `originals_available_until`, `footer_couple_info`, `couple_photo_path`,
   `logo_photographer_path`, `logo_couple_path`, `theme`)
VALUES
(1,
 'Awa & Junior',
 JSON_OBJECT('accueil','Bienvenue','galerie','Nos photos','password','Accès privé'),
 'folders_first',
 0,
 NULL,
 1,
 'couple_monogram',
 '2026-07-14 23:59:59',
 JSON_OBJECT(
   'noms','Awa & Junior',
   'date','12 juillet 2026',
   'message','Merci d''avoir partagé ce jour avec nous',
   'photographe','Studio Lumière — +237 6 00 00 00 00'
 ),
 NULL,
 NULL,
 NULL,
 JSON_OBJECT('primary','#b08968','accent','#7f5539','bg','#fdf8f3')
);

-- Albums de démo
INSERT INTO `albums` (`id`, `event_id`, `nom`, `ordre`) VALUES
(1, 1, 'Cérémonie', 1),
(2, 1, 'Réception', 2);

SET FOREIGN_KEY_CHECKS = 1;

-- Fin de install.sql
