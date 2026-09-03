<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$active = $active ?? '';
$page_title = $page_title ?? 'Archivents — Galeries photo pour photographes d\'événement';
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($page_title); ?></title>
    <meta name="description" content="Archivents — livrez vos galeries photo d'événement en ligne : galeries privées, QR, statistiques et téléchargement HD. Abonnez-vous ou payez à l'événement.">
    <meta name="theme-color" content="#181919">
    <!-- Aperçu du lien (WhatsApp / réseaux sociaux) -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Archivents">
    <meta property="og:title" content="Archivents — Galeries photo pour vos événements">
    <meta property="og:description" content="Livrez des galeries privées et élégantes, partagées par lien ou QR. Dès 13 500 FCFA l'événement, ou clé en main avec assistance le jour J.">
    <meta property="og:url" content="<?php echo site_url(); ?>">
    <meta property="og:image" content="<?php echo base_url('assets/img/og-cover.jpg'); ?>">
    <meta property="og:locale" content="fr_FR">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Hanken+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,300,0,0&display=swap" rel="stylesheet">
    <style>
        :root{
            /* Palette « Élégance » — near-black + taupe + champagne + sauge */
            --ink:#181919; --bg:#faf9f5; --surface:#ffffff;
            --accent:#685d4b; --accent-deep:#504535; --accent-soft:#eeddc7; --accent-lite:#d4c4af;
            --champagne:#f2ebe1; --sage:#bcc6ad;
            --muted:#5f5a52; --soft:#8a857c;
            --line:rgba(24,25,25,.12); --line-strong:rgba(24,25,25,.20);
            --shadow:0 1px 2px rgba(24,25,25,.04),0 16px 44px rgba(24,25,25,.07);
            --shadow-lg:0 2px 8px rgba(24,25,25,.08),0 40px 90px rgba(24,25,25,.16);
        }
        *{ box-sizing:border-box; }
        html{ scroll-behavior:smooth; }
        body{ margin:0; background:var(--bg); color:var(--ink); overflow-x:hidden;
            font-family:'Hanken Grotesk',system-ui,sans-serif; -webkit-font-smoothing:antialiased; line-height:1.6; }
        .display{ font-family:'Playfair Display',serif; font-weight:400; }
        a{ color:inherit; text-decoration:none; }
        img{ display:block; max-width:100%; }
        .wrap{ max-width:1280px; margin:0 auto; padding:0 24px; }
        @media(min-width:768px){ .wrap{ padding:0 64px; } }
        .label{ text-transform:uppercase; letter-spacing:.22em; font-weight:600; font-size:12px; }
        .eyebrow{ color:var(--accent); }
        .material-symbols-outlined{ font-family:'Material Symbols Outlined'; font-weight:normal; font-style:normal; line-height:1; vertical-align:middle;
            font-variation-settings:'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 24; }

        /* Titres */
        .h1{ font-size:clamp(40px,6.4vw,64px); line-height:1.06; font-weight:400; letter-spacing:-.02em; }
        .h2{ font-size:clamp(30px,4.2vw,48px); line-height:1.12; font-weight:400; letter-spacing:-.01em; }
        .lead{ font-size:19px; color:var(--muted); }
        .center{ text-align:center; }
        .muted{ color:var(--muted); }
        .section{ padding:clamp(72px,11vw,120px) 0; }

        /* Boutons — rectangulaires, lettres espacées (esprit éditorial) */
        .btn{ display:inline-flex; align-items:center; justify-content:center; gap:8px; cursor:pointer;
            font-weight:600; font-size:12px; letter-spacing:.18em; text-transform:uppercase;
            padding:16px 30px; border:1px solid transparent; border-radius:2px; transition:.25s ease; white-space:nowrap; }
        .btn .material-symbols-outlined{ font-size:18px; letter-spacing:normal; }
        .btn-primary{ background:var(--ink); color:#fff; }
        .btn-primary:hover{ background:var(--accent); }
        .btn-light{ background:var(--surface); color:var(--ink); border-color:var(--line-strong); }
        .btn-light:hover{ background:var(--ink); color:#fff; border-color:var(--ink); }
        .btn-ghost{ background:transparent; color:var(--ink); border-color:var(--line-strong); }
        .btn-ghost:hover{ background:var(--ink); color:#fff; border-color:var(--ink); }
        /* Pour fonds sombres (carte Studio, CTA finale) */
        .btn-cream{ background:var(--champagne); color:var(--ink); }
        .btn-cream:hover{ background:#fff; }
        .btn-outline-light{ background:transparent; color:#fff; border-color:rgba(255,255,255,.5); }
        .btn-outline-light:hover{ background:rgba(255,255,255,.12); border-color:#fff; }

        /* En-tête */
        header.nav{ position:sticky; top:0; z-index:50; background:color-mix(in srgb,var(--bg) 82%,transparent);
            backdrop-filter:blur(16px); border-bottom:1px solid var(--line); }
        .nav-in{ height:76px; display:flex; align-items:center; justify-content:space-between; }
        .brand{ font-family:'Playfair Display',serif; font-weight:600; font-size:26px; letter-spacing:-.02em; color:var(--ink); }
        .brand b{ color:var(--accent); font-style:italic; font-weight:600; }
        .nav-links{ display:flex; align-items:center; gap:34px; }
        .nav-links a.link{ font-weight:600; text-transform:uppercase; letter-spacing:.16em; font-size:12px; color:var(--muted); }
        .nav-links a.link:hover, .nav-links a.link.on{ color:var(--ink); }
        .nav-cta{ display:flex; align-items:center; gap:14px; }
        .burger{ display:none; background:none; border:0; cursor:pointer; color:var(--ink); }
        @media(max-width:900px){
            .nav-links.main{ position:fixed; inset:76px 0 auto 0; flex-direction:column; gap:0;
                background:var(--surface); border-bottom:1px solid var(--line); padding:8px 0; display:none; }
            .nav-links.main.open{ display:flex; }
            .nav-links.main a.link{ width:100%; padding:16px 28px; }
            .nav-cta .btn-ghost{ display:none; }
            .burger{ display:inline-flex; }
        }

        /* Révélation au scroll */
        .reveal{ opacity:0; transform:translateY(26px); transition:opacity .9s cubic-bezier(.16,1,.3,1), transform .9s cubic-bezier(.16,1,.3,1); }
        .reveal.in{ opacity:1; transform:none; }
        @media(prefers-reduced-motion:reduce){ .reveal{ opacity:1; transform:none; } }

        /* ---- Accents vibrants (une galerie photo, ça doit vivre) ---- */
        :root{
            --warm:#c4703f; --warm-deep:#9c4f26; --gold:#d9a441; --gold-deep:#a5771c;
            --blush:#f5ddd0; --rose:#e7a58a; --sage-l:#e4ead9; --sky:#dbe8ea; --teal:#5f8a8f;
            --grad-warm:linear-gradient(120deg,#c4703f 0%,#d9a441 100%);
            --grad-sun:linear-gradient(135deg,#e7a58a 0%,#c4703f 45%,#8a3f1e 100%);
        }
        .text-warm{ color:var(--warm); }
        .chip{ display:inline-flex; align-items:center; justify-content:center; width:58px; height:58px; border-radius:50%; }
        .chip .material-symbols-outlined{ font-size:30px; }
        .pill{ display:inline-flex; align-items:center; gap:8px; padding:8px 16px; border-radius:999px; font-weight:600; font-size:13px; }

        /* ---- Galerie défilante (marquee) ---- */
        .gwall{ position:relative; }
        .gwall::before, .gwall::after{ content:''; position:absolute; top:0; bottom:0; width:90px; z-index:3; pointer-events:none; }
        .gwall::before{ left:0;  background:linear-gradient(90deg,var(--gw-fade,var(--bg)),transparent); }
        .gwall::after{  right:0; background:linear-gradient(270deg,var(--gw-fade,var(--bg)),transparent); }
        .grow{ overflow:hidden; }
        .grow + .grow{ margin-top:16px; }
        .marquee{ display:flex; gap:16px; width:max-content; animation:scrollX 50s linear infinite; will-change:transform; }
        .marquee.rev{ animation-direction:reverse; animation-duration:62s; }
        .gwall:hover .marquee{ animation-play-state:paused; }
        @keyframes scrollX{ from{transform:translateX(0)} to{transform:translateX(-50%)} }
        .tile{ flex:0 0 auto; width:clamp(210px,25vw,290px); aspect-ratio:4/5; overflow:hidden; border-radius:8px; box-shadow:var(--shadow); }
        .tile img{ width:100%; height:100%; object-fit:cover; transition:transform .7s cubic-bezier(.16,1,.3,1); }
        .tile:hover img{ transform:scale(1.07); }
        @media(prefers-reduced-motion:reduce){ .marquee{ animation:none; } }
    </style>
</head>
<body>
<header class="nav">
    <div class="wrap nav-in">
        <a href="<?php echo site_url(); ?>" class="brand">Archiv<b>ents</b></a>
        <nav class="nav-links main" id="navmain">
            <a class="link<?php echo $active==='home'?' on':''; ?>" href="<?php echo site_url(); ?>#features">Prestations</a>
            <a class="link<?php echo $active==='home'?' on':''; ?>" href="<?php echo site_url(); ?>#gallery">Aperçu</a>
            <a class="link<?php echo $active==='pricing'?' on':''; ?>" href="<?php echo site_url('pricing'); ?>">Tarifs</a>
            <a class="link" href="<?php echo site_url(); ?>#cle-en-main">Clé en main</a>
        </nav>
        <div class="nav-cta">
            <a class="btn btn-ghost" href="<?php echo site_url('login'); ?>">Se connecter</a>
            <a class="btn btn-primary" href="<?php echo site_url('register'); ?>">Commencer</a>
            <button class="burger" aria-label="Menu" onclick="document.getElementById('navmain').classList.toggle('open')">
                <span class="material-symbols-outlined" style="font-size:30px">menu</span>
            </button>
        </div>
    </div>
</header>
<main>
