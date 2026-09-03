-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : ven. 10 juil. 2026 à 17:38
-- Version du serveur : 11.4.12-MariaDB
-- Version de PHP : 8.4.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `sc3koga4651_archiven`
--

-- --------------------------------------------------------

--
-- Structure de la table `albums`
--

CREATE TABLE `albums` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(190) NOT NULL,
  `ordre` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `albums`
--

INSERT INTO `albums` (`id`, `event_id`, `nom`, `ordre`) VALUES
(3, 1, 'Dote', 0),
(4, 1, 'Mairie', 1),
(5, 1, 'Eglise', 2),
(6, 1, 'Soirée', 3);

-- --------------------------------------------------------

--
-- Structure de la table `events`
--

CREATE TABLE `events` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `nom` varchar(190) NOT NULL,
  `type` enum('mariage','seminaire','anniversaire','bapteme','corporate','autre') NOT NULL DEFAULT 'mariage',
  `date_evt` date DEFAULT NULL,
  `slug` varchar(190) NOT NULL,
  `public_code` varchar(32) NOT NULL,
  `statut` enum('actif','archive') NOT NULL DEFAULT 'actif',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `events`
--

INSERT INTO `events` (`id`, `user_id`, `nom`, `type`, `date_evt`, `slug`, `public_code`, `statut`, `created_at`) VALUES
(1, 3, 'Mariage Tanisha & Terence', 'mariage', '2026-07-27', 'tanisha-terence', 'a1b2c3d4e5', 'actif', '2026-06-27 12:48:23');

-- --------------------------------------------------------

--
-- Structure de la table `event_settings`
--

CREATE TABLE `event_settings` (
  `event_id` int(10) UNSIGNED NOT NULL,
  `site_title` varchar(190) DEFAULT NULL,
  `page_titles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`page_titles`)),
  `gallery_mode` enum('photos_only','folders_only','folders_first') NOT NULL DEFAULT 'folders_first',
  `password_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `password_hash` varchar(255) DEFAULT NULL,
  `loading_screen_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `loading_logo_choice` enum('photographer','couple_monogram') NOT NULL DEFAULT 'couple_monogram',
  `loading_screen_duration` smallint(5) UNSIGNED NOT NULL DEFAULT 600,
  `originals_available_until` datetime DEFAULT NULL,
  `footer_couple_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`footer_couple_info`)),
  `header_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`header_options`)),
  `couple_photo_path` varchar(255) DEFAULT NULL,
  `logo_photographer_path` varchar(255) DEFAULT NULL,
  `logo_couple_path` varchar(255) DEFAULT NULL,
  `theme` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`theme`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `event_settings`
--

INSERT INTO `event_settings` (`event_id`, `site_title`, `page_titles`, `gallery_mode`, `password_enabled`, `password_hash`, `loading_screen_enabled`, `loading_logo_choice`, `loading_screen_duration`, `originals_available_until`, `footer_couple_info`, `header_options`, `couple_photo_path`, `logo_photographer_path`, `logo_couple_path`, `theme`) VALUES
(1, 'Mariage Tanisha & Terence', '{\"accueil\":\"Bienvenue\",\"galerie\":\"Tanisha & Terence\",\"password\":\"Accès privé\",\"loading\":\"Tanisha & Terence\",\"brand\":\"The T\"}', 'folders_first', 0, NULL, 1, 'photographer', 1500, '2026-07-14 23:59:59', '{\"noms\":\"Tanisha & Terence\",\"date\":\"27 Juin 2026\",\"message\":\"Merci d\'avoir partagé ce jour avec nous\",\"photographe\":\"Studio Matuta : +237 699 617 785\"}', '{\"opacity\":38,\"brightness\":120,\"pos_x\":43,\"pos_y\":21}', 'uploads/awa-junior/branding/fa4f5400d6f4495802fc2a3a.jpg', 'uploads/awa-junior/branding/e851511361b354118554926b.jpg', 'uploads/tanisha-terence/branding/a0d517c94d9e6e555bedef63.jpg', '{\"primary\":\"#c4194c\",\"accent\":\"#ce094e\",\"bg\":\"#fdf8f3\"}');

-- --------------------------------------------------------

--
-- Structure de la table `photos`
--

CREATE TABLE `photos` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED NOT NULL,
  `album_id` int(10) UNSIGNED DEFAULT NULL,
  `filename_base` varchar(190) NOT NULL,
  `path_thumb_webp` varchar(255) DEFAULT NULL,
  `path_medium_webp` varchar(255) DEFAULT NULL,
  `path_full_webp` varchar(255) DEFAULT NULL,
  `path_original` varchar(255) DEFAULT NULL,
  `original_purged` tinyint(1) NOT NULL DEFAULT 0,
  `largeur` int(11) DEFAULT NULL,
  `hauteur` int(11) DEFAULT NULL,
  `ordre` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `photos`
--

INSERT INTO `photos` (`id`, `event_id`, `album_id`, `filename_base`, `path_thumb_webp`, `path_medium_webp`, `path_full_webp`, `path_original`, `original_purged`, `largeur`, `hauteur`, `ordre`, `created_at`) VALUES
(5, 1, 5, 'bc1d9220829022a7411c6074c8f175bc', 'uploads/tanisha-terence/thumb/bc1d9220829022a7411c6074c8f175bc.webp', 'uploads/tanisha-terence/medium/bc1d9220829022a7411c6074c8f175bc.webp', 'uploads/tanisha-terence/full/bc1d9220829022a7411c6074c8f175bc.webp', 'uploads/tanisha-terence/original/bc1d9220829022a7411c6074c8f175bc.jpg', 0, 4351, 2901, 1, '2026-06-28 00:18:22'),
...(139, 1, 4, 'e6a5c855e92792b3a90e24ba99c7cd8d', 'uploads/tanisha-terence/thumb/e6a5c855e92792b3a90e24ba99c7cd8d.webp', 'uploads/tanisha-terence/medium/e6a5c855e92792b3a90e24ba99c7cd8d.webp', 'uploads/tanisha-terence/full/e6a5c855e92792b3a90e24ba99c7cd8d.webp', 'uploads/tanisha-terence/original/e6a5c855e92792b3a90e24ba99c7cd8d.jpg', 0, 7952, 5304, 134, '2026-06-28 01:07:35'),
(140, 1, 4, '26596e5112a0d6c69f3a42afba512415', 'uploads/tanisha-terence/thumb/26596e5112a0d6c69f3a42afba512415.webp', 'uploads/tanisha-terence/medium/26596e5112a0d6c69f3a42afba512415.webp', 'uploads/tanisha-terence/full/26596e5112a0d6c69f3a42afba512415.webp', 'uploads/tanisha-terence/original/26596e5112a0d6c69f3a42afba512415.jpg', 0, 7952, 5304, 135, '2026-06-28 01:07:44');
INSERT INTO `photos` (`id`, `event_id`, `album_id`, `filename_base`, `path_thumb_webp`, `path_medium_webp`, `path_full_webp`, `path_original`, `original_purged`, `largeur`, `hauteur`, `ordre`, `created_at`) VALUES
(141, 1, 4, 'b092639f7dd0e12cc8b471c4750d0193', 'uploads/tanisha-terence/thumb/b092639f7dd0e12cc8b471c4750d0193.webp', 'uploads/tanisha-terence/medium/b092639f7dd0e12cc8b471c4750d0193.webp', 'uploads/tanisha-terence/full/b092639f7dd0e12cc8b471c4750d0193.webp', 'uploads/tanisha-terence/original/b092639f7dd0e12cc8b471c4750d0193.jpg', 0, 7952, 5304, 136, '2026-06-28 01:07:53'),
...(270, 1, NULL, 'b11f5af12dfd08774fd5c8418f700150', 'uploads/tanisha-terence/thumb/b11f5af12dfd08774fd5c8418f700150.webp', 'uploads/tanisha-terence/medium/b11f5af12dfd08774fd5c8418f700150.webp', 'uploads/tanisha-terence/full/b11f5af12dfd08774fd5c8418f700150.webp', 'uploads/tanisha-terence/original/b11f5af12dfd08774fd5c8418f700150.jpg', 0, 7726, 4757, 265, '2026-06-28 02:37:58'),
(271, 1, 5, '4b4741b7319a5bc58b9ad37d3c765b0c', 'uploads/tanisha-terence/thumb/4b4741b7319a5bc58b9ad37d3c765b0c.webp', 'uploads/tanisha-terence/medium/4b4741b7319a5bc58b9ad37d3c765b0c.webp', 'uploads/tanisha-terence/full/4b4741b7319a5bc58b9ad37d3c765b0c.webp', 'uploads/tanisha-terence/original/4b4741b7319a5bc58b9ad37d3c765b0c.jpg', 0, 4024, 5304, 266, '2026-06-28 03:49:28');
INSERT INTO `photos` (`id`, `event_id`, `album_id`, `filename_base`, `path_thumb_webp`, `path_medium_webp`, `path_full_webp`, `path_original`, `original_purged`, `largeur`, `hauteur`, `ordre`, `created_at`) VALUES
(272, 1, 5, 'b48e97c46a4da2ed7e9c073a6196f2eb', 'uploads/tanisha-terence/thumb/b48e97c46a4da2ed7e9c073a6196f2eb.webp', 'uploads/tanisha-terence/medium/b48e97c46a4da2ed7e9c073a6196f2eb.webp', 'uploads/tanisha-terence/full/b48e97c46a4da2ed7e9c073a6196f2eb.webp', 'uploads/tanisha-terence/original/b48e97c46a4da2ed7e9c073a6196f2eb.jpg', 0, 7952, 5304, 267, '2026-06-28 03:49:37'),
...(401, 1, 3, '5b5ccdd24861c1f7b070dd2857faa6fd', 'uploads/tanisha-terence/thumb/5b5ccdd24861c1f7b070dd2857faa6fd.webp', 'uploads/tanisha-terence/medium/5b5ccdd24861c1f7b070dd2857faa6fd.webp', 'uploads/tanisha-terence/full/5b5ccdd24861c1f7b070dd2857faa6fd.webp', 'uploads/tanisha-terence/original/5b5ccdd24861c1f7b070dd2857faa6fd.jpg', 0, 4000, 5765, 396, '2026-07-02 01:40:22'),
(402, 1, 3, '1bb4bd8ab712411b19db1728c1d7fc44', 'uploads/tanisha-terence/thumb/1bb4bd8ab712411b19db1728c1d7fc44.webp', 'uploads/tanisha-terence/medium/1bb4bd8ab712411b19db1728c1d7fc44.webp', 'uploads/tanisha-terence/full/1bb4bd8ab712411b19db1728c1d7fc44.webp', 'uploads/tanisha-terence/original/1bb4bd8ab712411b19db1728c1d7fc44.jpg', 0, 6000, 3376, 397, '2026-07-02 01:40:27');
INSERT INTO `photos` (`id`, `event_id`, `album_id`, `filename_base`, `path_thumb_webp`, `path_medium_webp`, `path_full_webp`, `path_original`, `original_purged`, `largeur`, `hauteur`, `ordre`, `created_at`) VALUES
(403, 1, 3, '52b7f4ece1f515f4fc348775b59c3f06', 'uploads/tanisha-terence/thumb/52b7f4ece1f515f4fc348775b59c3f06.webp', 'uploads/tanisha-terence/medium/52b7f4ece1f515f4fc348775b59c3f06.webp', 'uploads/tanisha-terence/full/52b7f4ece1f515f4fc348775b59c3f06.webp', 'uploads/tanisha-terence/original/52b7f4ece1f515f4fc348775b59c3f06.jpg', 0, 4000, 5385, 398, '2026-07-02 01:40:33'),
...534, 1, 3, '7a15ad6c7575cd77e0b6fba1ecb7b20f', 'uploads/tanisha-terence/thumb/7a15ad6c7575cd77e0b6fba1ecb7b20f.webp', 'uploads/tanisha-terence/medium/7a15ad6c7575cd77e0b6fba1ecb7b20f.webp', 'uploads/tanisha-terence/full/7a15ad6c7575cd77e0b6fba1ecb7b20f.webp', 'uploads/tanisha-terence/original/7a15ad6c7575cd77e0b6fba1ecb7b20f.jpg', 0, 3376, 6000, 529, '2026-07-02 01:54:09');
INSERT INTO `photos` (`id`, `event_id`, `album_id`, `filename_base`, `path_thumb_webp`, `path_medium_webp`, `path_full_webp`, `path_original`, `original_purged`, `largeur`, `hauteur`, `ordre`, `created_at`) VALUES
(535, 1, 3, '880d2dbec4fbd0f923b796f8fb24f9e6', 'uploads/tanisha-terence/thumb/880d2dbec4fbd0f923b796f8fb24f9e6.webp', 'uploads/tanisha-terence/medium/880d2dbec4fbd0f923b796f8fb24f9e6.webp', 'uploads/tanisha-terence/full/880d2dbec4fbd0f923b796f8fb24f9e6.webp', 'uploads/tanisha-terence/original/880d2dbec4fbd0f923b796f8fb24f9e6.jpg', 0, 6000, 3376, 530, '2026-07-02 01:54:14'),
...(664, 1, NULL, 'd0f93dccaa2900befa978c89f5ce948d', 'uploads/tanisha-terence/thumb/d0f93dccaa2900befa978c89f5ce948d.webp', 'uploads/tanisha-terence/medium/d0f93dccaa2900befa978c89f5ce948d.webp', 'uploads/tanisha-terence/full/d0f93dccaa2900befa978c89f5ce948d.webp', 'uploads/tanisha-terence/original/d0f93dccaa2900befa978c89f5ce948d.jpg', 0, 6000, 3376, 659, '2026-07-02 02:10:30'),
(665, 1, NULL, 'f0351da4e1c21aefb6d7cf9ad25fc2ae', 'uploads/tanisha-terence/thumb/f0351da4e1c21aefb6d7cf9ad25fc2ae.webp', 'uploads/tanisha-terence/medium/f0351da4e1c21aefb6d7cf9ad25fc2ae.webp', 'uploads/tanisha-terence/full/f0351da4e1c21aefb6d7cf9ad25fc2ae.webp', 'uploads/tanisha-terence/original/f0351da4e1c21aefb6d7cf9ad25fc2ae.jpg', 0, 6000, 3376, 660, '2026-07-02 02:10:36');
INSERT INTO `photos` (`id`, `event_id`, `album_id`, `filename_base`, `path_thumb_webp`, `path_medium_webp`, `path_full_webp`, `path_original`, `original_purged`, `largeur`, `hauteur`, `ordre`, `created_at`) VALUES
(666, 1, NULL, '1024fd4d690fe56ce3a9bd1af9e0f075', 'uploads/tanisha-terence/thumb/1024fd4d690fe56ce3a9bd1af9e0f075.webp', 'uploads/tanisha-terence/medium/1024fd4d690fe56ce3a9bd1af9e0f075.webp', 'uploads/tanisha-terence/full/1024fd4d690fe56ce3a9bd1af9e0f075.webp', 'uploads/tanisha-terence/original/1024fd4d690fe56ce3a9bd1af9e0f075.jpg', 0, 4000, 5597, 661, '2026-07-02 02:10:41'),
...(752, 1, 4, 'c1a19bc041b767926a7948d543bfa2d6', 'uploads/tanisha-terence/thumb/c1a19bc041b767926a7948d543bfa2d6.webp', 'uploads/tanisha-terence/medium/c1a19bc041b767926a7948d543bfa2d6.webp', 'uploads/tanisha-terence/full/c1a19bc041b767926a7948d543bfa2d6.webp', 'uploads/tanisha-terence/original/c1a19bc041b767926a7948d543bfa2d6.jpg', 0, 7952, 5304, 747, '2026-07-02 16:09:45'),
(753, 1, 4, '7448c3f9165748228dfdd53b6a68dfec', 'uploads/tanisha-terence/thumb/7448c3f9165748228dfdd53b6a68dfec.webp', 'uploads/tanisha-terence/medium/7448c3f9165748228dfdd53b6a68dfec.webp', 'uploads/tanisha-terence/full/7448c3f9165748228dfdd53b6a68dfec.webp', 'uploads/tanisha-terence/original/7448c3f9165748228dfdd53b6a68dfec.jpg', 0, 7952, 5304, 748, '2026-07-02 16:09:53');

-- --------------------------------------------------------

--
-- Structure de la table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(50) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `roles`
--

INSERT INTO `roles` (`id`, `slug`, `nom`, `permissions`, `created_at`) VALUES
(1, 'super_admin', 'Super administrateur', '[\"*\"]', '2026-06-27 12:48:23'),
(2, 'admin', 'Administrateur', '[\"album.crud\", \"photo.upload\", \"settings.edit\", \"site_texts.edit\", \"stats.view\"]', '2026-06-27 12:48:23');

-- --------------------------------------------------------

--
-- Structure de la table `upload_jobs`
--

CREATE TABLE `upload_jobs` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED NOT NULL,
  `album_id` int(10) UNSIGNED DEFAULT NULL,
  `source_path` varchar(255) NOT NULL,
  `statut` enum('pending','processing','done','error') NOT NULL DEFAULT 'pending',
  `error_msg` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `upload_jobs`
--

INSERT INTO `upload_jobs` (`id`, `event_id`, `album_id`, `source_path`, `statut`, `error_msg`, `created_at`) VALUES
(1, 1, NULL, 'uploads/_incoming/e6c417434a29009ed14d9502e7fb73e2.jpg', 'done', NULL, '2026-06-27 20:17:21'),
...(467, 1, 3, 'uploads/_incoming/8b7c08549cfcb77a9d02803e924539dd.jpg', 'done', NULL, '2026-07-02 01:46:31');
INSERT INTO `upload_jobs` (`id`, `event_id`, `album_id`, `source_path`, `statut`, `error_msg`, `created_at`) VALUES
(468, 1, 3, 'uploads/_incoming/37a180ed519ff306be42cf26a3964b8c.jpg', 'done', NULL, '2026-07-02 01:46:31'),
...(758, 1, 4, 'uploads/_incoming/f498aa68cc179c06e36589a857b4e2a1.jpg', 'done', NULL, '2026-07-02 16:09:19');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'admin',
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `nom`, `email`, `password_hash`, `role`, `actif`, `created_at`) VALUES
(1, 'Super Admin', 'super@archiven.test', '$2y$10$3xfwCzzEUN0NfU7lW5E/J.Sh4JVM80XEtL9k.9JJ1GwKyYBhaq0Ty', 'super_admin', 1, '2026-06-27 12:48:23'),
(3, 'MATUTA', 'matuta@gmail.Com', '$2y$10$B9oDL3UqUVflgfhrDv3gFOFlsEDfGuCrR1ddT1FP9hrhLpAd9KJAe', 'admin', 1, '2026-06-27 15:03:02');

-- --------------------------------------------------------

--
-- Structure de la table `visitors`
--

CREATE TABLE `visitors` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED NOT NULL,
  `visitor_uid` varchar(64) NOT NULL,
  `first_seen` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen` datetime NOT NULL DEFAULT current_timestamp(),
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `fingerprint_hash` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `visitors`
--

INSERT INTO `visitors` (`id`, `event_id`, `visitor_uid`, `first_seen`, `last_seen`, `ip`, `user_agent`, `fingerprint_hash`) VALUES
(1, 1, 'a1e5586d-9f20-4d01-a6d9-8a386c001700', '2026-06-27 15:01:17', '2026-07-10 10:39:39', '129.0.189.48', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '8ded5554bacfa575239c4864167119e6cf959d152819681b0fe91c804d6d83e1'),
...(315, 1, '822ef4b3-5709-4873-b33a-e7e8e0ec9b65', '2026-06-28 20:49:29', '2026-07-09 20:35:47', '37.168.190.176', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.5 Mobile/15E148 Safari/604.1', '8d848b99affeb7d8d607c448e4157efdc6c5409a6dc3adedcfd73ade7f37ebe7'),
(316, 1, '57ba93bc-1ae4-454d-972c-4b7de5fa5318', '2026-06-28 20:51:16', '2026-06-28 20:57:55', '82.224.148.45', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '620e7d2f885de86f91d6f97721035f52e59aa42dab560592d1778835102f603e');
INSERT INTO `visitors` (`id`, `event_id`, `visitor_uid`, `first_seen`, `last_seen`, `ip`, `user_agent`, `fingerprint_hash`) VALUES
(317, 1, '429c8180-f249-4185-8960-2fb0997412f5', '2026-06-28 20:52:14', '2026-06-28 21:00:07', '154.72.169.215', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Mobile Safari/537.36', '71fcd0d08fe4a2d01304f4b4b87b15f0f5098a079dcdc23c2690cc098136c1c7'),
...
(472, 1, 'df98ebdd-2a9f-4890-9ae2-19c45540cdc6', '2026-07-03 15:45:49', '2026-07-03 15:45:49', '102.244.43.55', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.5 Mobile/15E148 Safari/604.1', '5e01fab24091bee13c150fdf29bf1cd4d3f9a63fab16c83844e0b4d53e09552f'),
(473, 1, '060bfc67-1cf9-45b8-8fc4-998c074df5c7', '2026-07-03 15:45:58', '2026-07-03 15:52:55', '102.244.43.55', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.5 Mobile/15E148 Safari/604.1', '5e01fab24091bee13c150fdf29bf1cd4d3f9a63fab16c83844e0b4d53e09552f');
INSERT INTO `visitors` (`id`, `event_id`, `visitor_uid`, `first_seen`, `last_seen`, `ip`, `user_agent`, `fingerprint_hash`) VALUES
(474, 1, 'acb6683d-f7fe-4138-8417-b84cad3a7e83', '2026-07-03 15:59:52', '2026-07-03 16:00:21', '102.244.220.192', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_7_12 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6.1 Mobile/15E148 Safari/604.1', 'd3a6099a5b0ac7766e57621b16b8edcd682f81a329aa98acab902b17a4792be4'),
.
.
.
(514, 1, '63a7a2c3-69b3-4336-bf24-4cf1518746a4', '2026-07-10 12:22:25', '2026-07-10 12:22:25', '88.188.86.71', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.5 Mobile/15E148 Safari/604.1', '4fbb9d4c8a52008b7a4079ebb3ecebc9c2f23510eecab452f37efcdf664c5c3a'),
(515, 1, '30e653f3-8c60-4fe8-b9e1-6d31b6c427ad', '2026-07-10 12:23:08', '2026-07-10 12:23:54', '88.188.86.71', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.5 Mobile/15E148 Safari/604.1', '4fbb9d4c8a52008b7a4079ebb3ecebc9c2f23510eecab452f37efcdf664c5c3a');

-- --------------------------------------------------------

--
-- Structure de la table `visit_events`
--

CREATE TABLE `visit_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED NOT NULL,
  `visitor_id` int(10) UNSIGNED NOT NULL,
  `type` enum('open','album_view','photo_view','download') NOT NULL,
  `album_id` int(10) UNSIGNED DEFAULT NULL,
  `photo_id` int(10) UNSIGNED DEFAULT NULL,
  `source` enum('qr','link') NOT NULL DEFAULT 'link',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `visit_events`
--

INSERT INTO `visit_events` (`id`, `event_id`, `visitor_id`, `type`, `album_id`, `photo_id`, `source`, `created_at`) VALUES
(1, 1, 1, 'open', NULL, NULL, 'link', '2026-06-27 15:01:17'),
.
.
.
(15932, 1, 337, 'open', NULL, NULL, 'link', '2026-07-10 17:35:02'),
(15933, 1, 337, 'open', NULL, NULL, 'link', '2026-07-10 17:35:02');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `albums`
--
ALTER TABLE `albums`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_albums_event` (`event_id`);

--
-- Index pour la table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_events_slug` (`slug`),
  ADD UNIQUE KEY `uq_events_code` (`public_code`),
  ADD KEY `idx_events_user` (`user_id`);

--
-- Index pour la table `event_settings`
--
ALTER TABLE `event_settings`
  ADD PRIMARY KEY (`event_id`);

--
-- Index pour la table `photos`
--
ALTER TABLE `photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_photos_event` (`event_id`),
  ADD KEY `idx_photos_album` (`album_id`),
  ADD KEY `idx_photos_purged` (`original_purged`);

--
-- Index pour la table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_slug` (`slug`);

--
-- Index pour la table `upload_jobs`
--
ALTER TABLE `upload_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jobs_statut` (`statut`),
  ADD KEY `idx_jobs_event` (`event_id`),
  ADD KEY `fk_jobs_album` (`album_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role` (`role`);

--
-- Index pour la table `visitors`
--
ALTER TABLE `visitors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_visitor_uid` (`visitor_uid`),
  ADD KEY `idx_visitors_event` (`event_id`);

--
-- Index pour la table `visit_events`
--
ALTER TABLE `visit_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ve_event` (`event_id`),
  ADD KEY `idx_ve_visitor` (`visitor_id`),
  ADD KEY `idx_ve_type` (`type`),
  ADD KEY `idx_ve_created` (`created_at`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `albums`
--
ALTER TABLE `albums`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `photos`
--
ALTER TABLE `photos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=754;

--
-- AUTO_INCREMENT pour la table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `upload_jobs`
--
ALTER TABLE `upload_jobs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=759;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `visitors`
--
ALTER TABLE `visitors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=516;

--
-- AUTO_INCREMENT pour la table `visit_events`
--
ALTER TABLE `visit_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15934;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `albums`
--
ALTER TABLE `albums`
  ADD CONSTRAINT `fk_albums_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_events_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `event_settings`
--
ALTER TABLE `event_settings`
  ADD CONSTRAINT `fk_settings_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `photos`
--
ALTER TABLE `photos`
  ADD CONSTRAINT `fk_photos_album` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_photos_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `upload_jobs`
--
ALTER TABLE `upload_jobs`
  ADD CONSTRAINT `fk_jobs_album` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_jobs_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `visitors`
--
ALTER TABLE `visitors`
  ADD CONSTRAINT `fk_visitors_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `visit_events`
--
ALTER TABLE `visit_events`
  ADD CONSTRAINT `fk_ve_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ve_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
