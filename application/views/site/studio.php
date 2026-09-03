<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Vitrine d'un studio — {studio}.archivents.com
 * Page volontairement sobre et SANS liste de galeries : les liens de
 * galerie sont non devinables (confidentialité) et ne se partagent que
 * par le photographe (lien direct ou QR code).
 */
$nom      = isset($studio['nom']) ? $studio['nom'] : 'Studio';
$initiale = mb_strtoupper(mb_substr(trim($nom), 0, 1, 'UTF-8'), 'UTF-8');
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo html_escape($nom); ?> — galeries d'événements</title>
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;1,500&family=Hanken+Grotesk:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root{ --ink:#17171a; --bg:#faf7f2; --muted:#8a857c; --line:#e7e2d9; --accent:#bd5c33; }
  *{ margin:0; padding:0; box-sizing:border-box; }
  body{ background:var(--bg); color:var(--ink); font-family:'Hanken Grotesk',system-ui,sans-serif;
        min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center;
        text-align:center; padding:32px 20px; }
  .mono{ width:88px; height:88px; border:1px solid var(--ink); border-radius:50%;
         display:flex; align-items:center; justify-content:center; margin:0 auto 28px;
         font-family:'Playfair Display',serif; font-size:38px; font-style:italic; }
  h1{ font-family:'Playfair Display',serif; font-weight:600; font-size:clamp(30px,6vw,46px); letter-spacing:.01em; }
  .tag{ text-transform:uppercase; letter-spacing:.22em; font-size:11px; color:var(--muted); margin-top:14px; }
  .card{ margin-top:36px; max-width:520px; border:1px solid var(--line); background:#fff;
         border-radius:14px; padding:28px 26px; line-height:1.7; font-size:15px; color:#4c4a45; }
  .card b{ color:var(--ink); }
  .qr{ display:inline-flex; align-items:center; gap:8px; margin-top:14px; color:var(--accent); font-weight:500; }
  footer{ margin-top:44px; font-size:12px; color:var(--muted); }
  footer a{ color:var(--muted); }
</style>
</head>
<body>
  <div>
    <div class="mono"><?php echo html_escape($initiale); ?></div>
    <h1><?php echo html_escape($nom); ?></h1>
    <div class="tag">Galeries photo &amp; vidéo d'événements</div>

    <div class="card">
      Les galeries de <b><?php echo html_escape($nom); ?></b> sont <b>privées</b>.<br>
      Pour accéder aux photos de votre événement, utilisez le <b>lien</b> ou le
      <b>QR&nbsp;code</b> qui vous a été remis par votre photographe.
      <div class="qr">◼ Scannez votre QR code d'invitation</div>
    </div>

    <?php if ( ! empty($show_branding)): ?>
    <footer>Propulsé par <a href="https://archivents.com">Archivents</a></footer>
    <?php endif; ?>
  </div>
</body>
</html>
