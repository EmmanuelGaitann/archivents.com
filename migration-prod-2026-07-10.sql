-- =====================================================================
--  /!\ FICHIER OBSOLÈTE — NE PLUS UTILISER /!\
--  Ce fichier suppose que les tables SaaS (plans, subscriptions…)
--  existent déjà : sur une base plus ancienne il échoue avec
--  « #1146 la table plans n'existe pas ».
--  ==> Utilisez migration-prod-full-2026-07-10.sql, qui rattrape
--      N'IMPORTE QUELLE version du schéma (idempotent, sans perte).
-- =====================================================================
--  Archivents — migration PRODUCTION (2026-07-10)
--  À exécuter sur la base O2Switch EXISTANTE (n'efface rien).
--  Syntaxe MariaDB (IF NOT EXISTS) — O2Switch est sous MariaDB.
--  Si une ligne échoue avec « Duplicate column », elle est déjà passée :
--  continuez simplement avec la suivante.
-- =====================================================================

-- --- Pipeline R2 (photos directes) ---
ALTER TABLE `photos` ADD COLUMN IF NOT EXISTS `r2_key` VARCHAR(255) NULL AFTER `path_original`;
ALTER TABLE `photos` ADD COLUMN IF NOT EXISTS `status` ENUM('pending','ready') NOT NULL DEFAULT 'ready' AFTER `r2_key`;
ALTER TABLE `photos` ADD COLUMN IF NOT EXISTS `size_bytes` BIGINT UNSIGNED NULL AFTER `status`;
ALTER TABLE `photos` ADD INDEX IF NOT EXISTS `idx_photos_status` (`status`);
ALTER TABLE `photos` ADD INDEX IF NOT EXISTS `idx_photos_r2_key` (`r2_key`);

-- --- Vidéos (forfait Studio) ---
CREATE TABLE IF NOT EXISTS `videos` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`    INT UNSIGNED NOT NULL,
  `album_id`    INT UNSIGNED NULL,
  `r2_key`      VARCHAR(255) NOT NULL,
  `titre`       VARCHAR(190) NULL,
  `upload_id`   VARCHAR(255) NULL,
  `size_bytes`  BIGINT UNSIGNED NULL,
  `duration_s`  INT UNSIGNED NULL,
  `largeur`     INT UNSIGNED NULL,
  `hauteur`     INT UNSIGNED NULL,
  `ordre`       INT NOT NULL DEFAULT 0,
  `status`      ENUM('pending','ready') NOT NULL DEFAULT 'pending',
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

-- --- Orphelins R2 (suppressions échouées, re-tentées par le cron) ---
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

-- --- Forfaits : colonnes de capacités ---
ALTER TABLE `plans` ADD COLUMN IF NOT EXISTS `max_photos` INT UNSIGNED NULL AFTER `max_events`;
ALTER TABLE `plans` ADD COLUMN IF NOT EXISTS `watermark` TINYINT(1) NOT NULL DEFAULT 0 AFTER `remove_branding`;
ALTER TABLE `plans` ADD COLUMN IF NOT EXISTS `video` TINYINT(1) NOT NULL DEFAULT 0 AFTER `watermark`;
ALTER TABLE `plans` ADD COLUMN IF NOT EXISTS `gallery_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `video`;

-- --- Dérogations super admin sur un abonnement ---
ALTER TABLE `subscriptions` ADD COLUMN IF NOT EXISTS `storage_quota_mo` INT UNSIGNED NULL AFTER `events_quota`;

-- --- Réinitialisation de mot de passe ---
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(190) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pr_email` (`email`),
  KEY `idx_pr_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Matrice commerciale (grille validée le 10/07/2026) ---
--  Pro (27 000)    : mot de passe OUI ; vidéo NON ; collaborateurs NON.
--  Studio (51 000) : vidéo + mot de passe + collaborateurs illimités.
--  Test / Ponctuel / Découverte : lien à code non devinable, sans mot de passe.
UPDATE `plans` SET `video`=0, `max_collaborators`=0, `gallery_password`=1 WHERE `slug`='pro';
UPDATE `plans` SET `video`=1, `gallery_password`=1 WHERE `slug`='studio';
UPDATE `plans` SET `gallery_password`=0, `max_collaborators`=0 WHERE `slug` IN ('gratuit','ponctuel','decouverte');

-- --- Collaborateurs (forfait Studio) ---
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `parent_user_id` INT UNSIGNED NULL AFTER `role`;
ALTER TABLE `users` ADD INDEX IF NOT EXISTS `idx_users_parent` (`parent_user_id`);
-- Rôle « collaborateur » (INSERT IGNORE : ne fait rien s'il existe déjà).
INSERT IGNORE INTO `roles` (`slug`, `nom`, `permissions`) VALUES
('collaborateur', 'Collaborateur',
  JSON_ARRAY('album.crud','photo.upload','settings.edit','site_texts.edit','stats.view'));

-- --- Journal des tâches planifiées (page admin Système) ---
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

-- --- Alertes e-mail d'abonnement (cron subscription_alerts) ---
-- Drapeaux anti-doublon : remis à 0 à chaque activation/prolongation.
ALTER TABLE `subscriptions` ADD COLUMN IF NOT EXISTS `notif_j7`      TINYINT(1) NOT NULL DEFAULT 0 AFTER `note`;
ALTER TABLE `subscriptions` ADD COLUMN IF NOT EXISTS `notif_j1`      TINYINT(1) NOT NULL DEFAULT 0 AFTER `notif_j7`;
ALTER TABLE `subscriptions` ADD COLUMN IF NOT EXISTS `notif_expired` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notif_j1`;
