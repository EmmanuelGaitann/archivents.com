<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| =====================================================================
|  ARCHIVENTS — E-mail (SMTP O2Switch) + coordonnées de contact
| ---------------------------------------------------------------------
|  Le mot de passe SMTP est lu par variable d'environnement (getenv),
|  JAMAIS committé. Sur O2Switch : SetEnv dans .htaccess ou fichier PHP
|  non versionné. Sans mot de passe, l'envoi est simplement ignoré
|  (les flux ne cassent pas) — voir Mailer::is_configured().
| =====================================================================
*/
$config['mailer'] = array(
    'from_email'  => getenv('MAIL_FROM')   ?: 'contact@archivents.com',
    'from_name'   => getenv('MAIL_NAME')   ?: 'Archivents',
    'smtp_host'   => getenv('SMTP_HOST')   ?: 'crayon.o2switch.net',
    'smtp_user'   => getenv('SMTP_USER')   ?: 'contact@archivents.com',
    'smtp_pass'   => getenv('SMTP_PASS')   ?: '',      // mot de passe de la boîte — UNIQUEMENT via .env / env serveur
    'smtp_port'   => (int) (getenv('SMTP_PORT') ?: 465),
    'smtp_crypto' => getenv('SMTP_CRYPTO') ?: 'ssl',   // 465 = SSL/TLS implicite
);

/* Coordonnées affichées sur le site (contact / support). */
$config['contact'] = array(
    'email'    => 'contact@archivents.com',
    'whatsapp' => getenv('CONTACT_WHATSAPP') ?: '',    // ex : +237 6 XX XX XX XX
);
