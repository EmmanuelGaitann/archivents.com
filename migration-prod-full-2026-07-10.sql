-- =====================================================================
--  ARCHIVENTS — MIGRATION PROD COMPLÈTE (rattrapage intégral)
--  À jouer sur la base sc3koga4651_archiven via phpMyAdmin.
--
--  Amène N'IMPORTE QUELLE version antérieure du schéma (y compris la
--  toute première mise en prod, avant le virage SaaS) vers le schéma
--  actuel. 100 % ADDITIVE et IDEMPOTENTE :
--    - aucune table existante n'est supprimée ni vidée ;
--    - les données existantes (événements, photos, visiteurs — dont
--      la galerie matuta) sont conservées ;
--    - rejouable sans danger (IF NOT EXISTS / INSERT IGNORE partout).
--
--  IMPORTANT phpMyAdmin : onglet Importer -> DÉCOCHER la case
--  « Activer la vérification des clés étrangères » avant de lancer.
--  NE JAMAIS importer install.sql en production (il contient des DROP).
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  1) USERS — colonnes SaaS + collaborateurs
-- ---------------------------------------------------------------------
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `parent_user_id`          INT UNSIGNED NULL AFTER `role`;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `studio_slug`             VARCHAR(190) NULL AFTER `actif`;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `email_verified`          TINYINT(1)   NOT NULL DEFAULT 0 AFTER `studio_slug`;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `current_plan_id`         INT UNSIGNED NULL AFTER `email_verified`;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `current_subscription_id` INT UNSIGNED NULL AFTER `current_plan_id`;
ALTER TABLE `users` ADD UNIQUE INDEX IF NOT EXISTS `uq_users_studio` (`studio_slug`);
ALTER TABLE `users` ADD INDEX IF NOT EXISTS `idx_users_plan`   (`current_plan_id`);
ALTER TABLE `users` ADD INDEX IF NOT EXISTS `idx_users_parent` (`parent_user_id`);
ALTER TABLE `users` MODIFY `role` VARCHAR(50) NOT NULL DEFAULT 'photographe';

-- TOUS les comptes existants au moment de la migration sont considérés
-- vérifiés : ils ont été créés par l'opérateur (pas d'auto-inscription),
-- donc aucun risque de spam. Sans cela, ils seraient bloqués (création
-- d'événement et upload refusés tant que l'e-mail n'est pas confirmé).
-- Les comptes créés APRÈS la migration passent par le flux normal
-- (e-mail de confirmation à l'inscription). La borne de date rend le
-- script REJOUABLE sans re-vérifier des inscriptions ultérieures.
UPDATE `users` SET `email_verified` = 1 WHERE `created_at` < '2026-07-11 00:00:00';

-- ---------------------------------------------------------------------
--  2) ROLES — renommage editor/admin -> photographe + rôles manquants
-- ---------------------------------------------------------------------
UPDATE `users` SET `role` = 'photographe' WHERE `role` IN ('editor', 'admin');
DELETE FROM `roles` WHERE `slug` IN ('editor', 'admin');
INSERT IGNORE INTO `roles` (`slug`, `nom`, `permissions`) VALUES
('super_admin', 'Super administrateur', JSON_ARRAY('*')),
('photographe', 'Photographe',
  JSON_ARRAY('album.crud','photo.upload','settings.edit','site_texts.edit','stats.view')),
('collaborateur', 'Collaborateur',
  JSON_ARRAY('album.crud','photo.upload','settings.edit','site_texts.edit','stats.view'));

-- ---------------------------------------------------------------------
--  3) EVENTS — propriétaire (multi-tenant) + code public non devinable
-- ---------------------------------------------------------------------
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `user_id`     INT UNSIGNED NULL AFTER `id`;
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `public_code` VARCHAR(32)  NULL AFTER `slug`;
ALTER TABLE `events` ADD INDEX IF NOT EXISTS `idx_events_user` (`user_id`);

-- Génère un code pour les événements qui n'en ont pas (ex : matuta).
-- NOTE : le lien public de ces galeries devient /e/{nouveau code} —
-- à relever ensuite dans admin -> Événements (colonne lien/QR).
UPDATE `events`
   SET `public_code` = LOWER(SUBSTRING(MD5(CONCAT(`id`, '-', RAND(), '-', NOW())), 1, 10))
 WHERE `public_code` IS NULL OR `public_code` = '';

ALTER TABLE `events` ADD UNIQUE INDEX IF NOT EXISTS `uq_events_code` (`public_code`);

-- Clé étrangère propriétaire (suppression d'un compte = events orphelins,
-- jamais purgés). Si votre MariaDB refuse le IF NOT EXISTS sur CONSTRAINT,
-- ignorez uniquement cette ligne.
ALTER TABLE `events` ADD CONSTRAINT IF NOT EXISTS `fk_events_user`
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ---------------------------------------------------------------------
--  4) EVENT_SETTINGS — options ajoutées après la 1re mise en prod
-- ---------------------------------------------------------------------
ALTER TABLE `event_settings` ADD COLUMN IF NOT EXISTS `header_options` JSON NULL AFTER `footer_couple_info`;
ALTER TABLE `event_settings` ADD COLUMN IF NOT EXISTS `page_titles`    JSON NULL AFTER `site_title`;
ALTER TABLE `event_settings` ADD COLUMN IF NOT EXISTS `theme`          JSON NULL;
ALTER TABLE `event_settings` ADD COLUMN IF NOT EXISTS `loading_screen_duration` SMALLINT UNSIGNED NOT NULL DEFAULT 600 AFTER `loading_logo_choice`;

-- gallery_mode : passage à 3 modes en 2 temps (tolère l'ancien
-- 'all_then_filter' puis le convertit, puis fige l'enum final).
ALTER TABLE `event_settings` MODIFY `gallery_mode`
  ENUM('photos_only','folders_only','folders_first','all_then_filter') NOT NULL DEFAULT 'folders_first';
UPDATE `event_settings` SET `gallery_mode` = 'photos_only' WHERE `gallery_mode` = 'all_then_filter';
ALTER TABLE `event_settings` MODIFY `gallery_mode`
  ENUM('photos_only','folders_only','folders_first') NOT NULL DEFAULT 'folders_first';

-- ---------------------------------------------------------------------
--  5) PHOTOS — stockage R2 + quota
-- ---------------------------------------------------------------------
ALTER TABLE `photos` ADD COLUMN IF NOT EXISTS `r2_key`     VARCHAR(255) NULL AFTER `filename_base`;
ALTER TABLE `photos` ADD COLUMN IF NOT EXISTS `size_bytes` BIGINT UNSIGNED NULL AFTER `r2_key`;
ALTER TABLE `photos` ADD COLUMN IF NOT EXISTS `status`     ENUM('pending','ready') NOT NULL DEFAULT 'ready' AFTER `original_purged`;
ALTER TABLE `photos` ADD INDEX IF NOT EXISTS `idx_photos_status` (`status`);

-- ---------------------------------------------------------------------
--  6) NOUVELLES TABLES (SaaS + vidéo + exploitation)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `plans` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`              VARCHAR(60)  NOT NULL,
  `nom`               VARCHAR(120) NOT NULL,
  `tier`              ENUM('gratuit','pass','essentiel','studio','signature') NOT NULL,
  `billing_period`    ENUM('free','per_event','monthly','yearly') NOT NULL,
  `prix`              INT UNSIGNED NOT NULL DEFAULT 0,
  `devise`            CHAR(3)      NOT NULL DEFAULT 'XAF',
  `max_events`        INT UNSIGNED NULL,
  `max_photos`        INT UNSIGNED NULL,
  `storage_mo`        INT UNSIGNED NULL,
  `retention_days`    SMALLINT UNSIGNED NULL,
  `max_collaborators` SMALLINT UNSIGNED NULL,
  `remove_branding`   TINYINT(1)   NOT NULL DEFAULT 0,
  `watermark`         TINYINT(1)   NOT NULL DEFAULT 0,
  `video`             TINYINT(1)   NOT NULL DEFAULT 0,
  `gallery_password`  TINYINT(1)   NOT NULL DEFAULT 0,
  `custom_domain`     TINYINT(1)   NOT NULL DEFAULT 0,
  `features`          JSON         NULL,
  `actif`             TINYINT(1)   NOT NULL DEFAULT 1,
  `ordre`             INT          NOT NULL DEFAULT 0,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plans_slug` (`slug`),
  KEY `idx_plans_tier` (`tier`),
  KEY `idx_plans_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `plan_id`      INT UNSIGNED NOT NULL,
  `statut`       ENUM('en_attente','actif','expire','annule') NOT NULL DEFAULT 'en_attente',
  `started_at`   DATETIME     NULL,
  `expires_at`   DATETIME     NULL,
  `events_quota` INT UNSIGNED NULL,
  `storage_quota_mo` INT UNSIGNED NULL,
  `events_used`  INT UNSIGNED NOT NULL DEFAULT 0,
  `note`         VARCHAR(255) NULL,
  `notif_j7`      TINYINT(1)  NOT NULL DEFAULT 0,
  `notif_j1`      TINYINT(1)  NOT NULL DEFAULT 0,
  `notif_expired` TINYINT(1)  NOT NULL DEFAULT 0,
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

CREATE TABLE IF NOT EXISTS `payments` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `subscription_id` INT UNSIGNED NULL,
  `plan_id`         INT UNSIGNED NULL,
  `montant`         INT UNSIGNED NOT NULL DEFAULT 0,
  `devise`          CHAR(3)      NOT NULL DEFAULT 'XAF',
  `methode`         ENUM('om','momo','virement','carte','manuel','autre') NOT NULL DEFAULT 'manuel',
  `reference`       VARCHAR(190) NULL,
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

CREATE TABLE IF NOT EXISTS `videos` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`    INT UNSIGNED NOT NULL,
  `album_id`    INT UNSIGNED NULL,
  `titre`       VARCHAR(190) NULL,
  `r2_key`      VARCHAR(255) NOT NULL,
  `upload_id`   VARCHAR(191) NULL,
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

CREATE TABLE IF NOT EXISTS `r2_orphans` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `r2_key`     VARCHAR(255) NOT NULL,
  `upload_id`  VARCHAR(255) NULL,
  `note`       VARCHAR(190) NULL,
  `attempts`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orph_key` (`r2_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(190) NOT NULL,
  `token_hash` CHAR(64)     NOT NULL,
  `expires_at` DATETIME     NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pr_email` (`email`),
  KEY `idx_pr_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cron_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task`        VARCHAR(60)  NOT NULL,
  `ok`          TINYINT(1)   NOT NULL DEFAULT 1,
  `output`      TEXT         NULL,
  `started_at`  DATETIME     NOT NULL,
  `finished_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cronlog_task` (`task`, `finished_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  7) CATALOGUE DES FORFAITS (ids figés ; INSERT IGNORE = ne touche pas
--     un catalogue déjà présent)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `plans`
  (`id`,`slug`,`nom`,`tier`,`billing_period`,`prix`,`devise`,
   `max_events`,`max_photos`,`storage_mo`,`retention_days`,`max_collaborators`,
   `remove_branding`,`watermark`,`video`,`gallery_password`,`custom_domain`,`features`,`actif`,`ordre`)
VALUES
(8,'gratuit','Test','gratuit','free', 0,'XAF',
  1, NULL, 1024, 7, 0, 0, 1, 0, 0, 0,
  JSON_OBJECT('stats','basic','hd_download',true,'theme','basic','support','community'), 1, 5),
(7,'ponctuel','Ponctuel','pass','per_event', 13500,'XAF',
  1, NULL, 5120, 15, 0, 0, 0, 0, 0, 0,
  JSON_OBJECT('stats','basic','hd_download',true,'theme','basic','support','email'), 1, 10),
(1,'decouverte','Découverte','essentiel','monthly', 15500,'XAF',
  2, NULL, 10240, NULL, 0, 0, 0, 0, 0, 0,
  JSON_OBJECT('stats','basic','hd_download',true,'theme','basic','support','email'), 1, 20),
(3,'pro','Pro','studio','monthly', 27000,'XAF',
  5, NULL, 30720, NULL, 0, 1, 0, 0, 1, 0,
  JSON_OBJECT('stats','advanced','hd_download',true,'theme','full','support','priority'), 1, 30),
(5,'studio','Studio','signature','monthly', 51000,'XAF',
  12, NULL, 81920, NULL, NULL, 1, 0, 1, 1, 1,
  JSON_OBJECT('stats','advanced','hd_download',true,'theme','full','support','dedicated'), 1, 40),
(2,'essentiel_annuel','Découverte — annuel','essentiel','yearly', 155000,'XAF',
  3, NULL, 25600, 30, 0, 0, 0, 0, 0, 0,
  JSON_OBJECT('stats','basic','hd_download',true,'theme','basic','support','email'), 0, 21),
(4,'studio_annuel','Pro — annuel','studio','yearly', 270000,'XAF',
  10, NULL, 81920, 90, 0, 1, 0, 0, 1, 0,
  JSON_OBJECT('stats','advanced','hd_download',true,'theme','full','support','priority'), 0, 31),
(6,'signature_annuel','Studio — annuel','signature','yearly', 510000,'XAF',
  NULL, NULL, 256000, 365, NULL, 1, 0, 1, 1, 1,
  JSON_OBJECT('stats','advanced','hd_download',true,'theme','full','support','dedicated'), 0, 41);

-- Recale la matrice au cas où un catalogue plus ancien existait déjà.
UPDATE `plans` SET `video`=0, `max_collaborators`=0, `gallery_password`=1 WHERE `slug`='pro';
UPDATE `plans` SET `video`=1, `gallery_password`=1 WHERE `slug`='studio';
UPDATE `plans` SET `gallery_password`=0, `max_collaborators`=0 WHERE `slug` IN ('gratuit','ponctuel','decouverte');

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  APRÈS IMPORT — vérifications rapides (onglet SQL) :
--    SELECT COUNT(*) FROM plans;            -- attendu : 8
--    SELECT id, nom, slug, public_code FROM events;  -- codes générés
--    SELECT slug FROM roles;                -- super_admin, photographe, collaborateur
--  Puis tester https://archivents.com/ping
--
--  NOTE matuta : l'événement existant garde ses photos ; son NOUVEAU
--  lien public est /e/{public_code} (voir admin -> Événements / QR).
--  Ne PAS attribuer cet événement à un photographe sans abonnement
--  actif (il serait purgé après 30 j) — laissez-le sans propriétaire
--  ou attribuez-le au super admin.
-- =====================================================================
