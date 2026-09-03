<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$theme = $settings['theme'] ?? array();
$c_primary = $theme['primary'] ?? '#b08968';
$c_accent  = $theme['accent']  ?? '#7f5539';
$c_bg      = $theme['bg']      ?? '#fbf9f9';
$site_title = $settings['site_title'] ?? $event['nom'];

// Écran de chargement
$loading_enabled = ! empty($settings['loading_screen_enabled']);
$logo_choice = $settings['loading_logo_choice'] ?? 'couple_monogram';
$couple = $settings['footer_couple_info'] ?? array();
// Monogramme : initiales dérivées des noms du couple.
$monogram = '';
if ( ! empty($couple['noms']))
{
    if (preg_match_all('/\b([A-Za-zÀ-ÿ])/u', $couple['noms'], $m))
    {
        $monogram = mb_strtoupper(implode(' & ', array_slice($m[1], 0, 2)));
    }
}
$photographer_logo = $settings['logo_photographer_path'] ?? NULL;
$couple_date = $couple['date'] ?? '';
$loading_duration = isset($settings['loading_screen_duration']) ? (int) $settings['loading_screen_duration'] : 600;
$page_titles = $settings['page_titles'] ?? array();
$loading_text = ($page_titles['loading'] ?? '') !== '' ? $page_titles['loading'] : 'Chargement…';
// Marque affichée dans le header et l'écran de chargement :
// texte personnalisé si défini, sinon les initiales (monogramme), sinon le titre.
$brand_text = $page_titles['brand'] ?? '';
$brand_display = $brand_text !== '' ? $brand_text : ($monogram ?: $site_title);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($site_title); ?></title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/tailwind.css'); ?>?v=<?php echo @filemtime(FCPATH.'assets/css/tailwind.css'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Hanken+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,300,0,0&display=swap" rel="stylesheet">
    <style>
        :root{
            --c-primary: <?php echo $c_primary; ?>;
            --c-accent:  <?php echo $c_accent; ?>;
            --c-bg:      <?php echo $c_bg; ?>;
            --c-ink:     #1b1c1c;
            --c-muted:   #6b6b6b;
            --c-surface: #f4f1ee;
            --c-line:    rgba(27,28,28,.10);
        }
        html,body{ background: var(--c-bg); }
        body{ color: var(--c-ink); }
        .font-display{ font-family:'Playfair Display',serif; }
        .font-body{ font-family:'Hanken Grotesk',system-ui,sans-serif; }
        .t-label{ font-family:'Hanken Grotesk',sans-serif; text-transform:uppercase; letter-spacing:.2em; font-weight:600; }
        .text-accent{ color:var(--c-accent); }
        .text-ink{ color:var(--c-ink); }
        .text-muted{ color:var(--c-muted); }
        .bg-accent{ background:var(--c-accent); }
        .bg-surface{ background:var(--c-surface); }
        .border-line{ border-color:var(--c-line); }
        .material-symbols-outlined{ font-family:'Material Symbols Outlined'; font-weight:normal; font-style:normal; line-height:1; letter-spacing:normal; text-transform:none; display:inline-block; white-space:nowrap; word-wrap:normal; direction:ltr; }

        /* Grille masonry */
        .grid-img{ content-visibility:auto; }
        .zoom img{ transition: transform 1.2s cubic-bezier(.16,1,.3,1); }
        .zoom:hover img{ transform: scale(1.05); }

        /* Écran de chargement */
        #loader{
            position:fixed; inset:0; z-index:60; display:flex; align-items:center; justify-content:center;
            background:var(--c-bg); transition:opacity .8s ease, visibility .8s ease;
        }
        #loader.hide{ opacity:0; visibility:hidden; }
        #loader .mono{ animation: floatIn 1s ease both; }
        @keyframes floatIn{ from{opacity:0; transform:translateY(12px);} to{opacity:1; transform:none;} }
    </style>
</head>
<body class="font-body antialiased text-ink">

<?php if ($loading_enabled): ?>
<div id="loader">
    <div class="mono text-center">
        <?php if ($logo_choice === 'photographer' && $photographer_logo): ?>
            <img src="<?php echo base_url($photographer_logo); ?>" alt="" class="mx-auto max-h-28">
        <?php else: ?>
            <div class="font-display italic text-5xl text-accent"><?php echo html_escape($brand_display); ?></div>
        <?php endif; ?>
        <div class="mt-4 t-label text-[11px] text-muted"><?php echo html_escape($loading_text); ?></div>
    </div>
</div>
<script>
    window.addEventListener('load', function () {
        var l = document.getElementById('loader');
        if (l) setTimeout(function(){ l.classList.add('hide'); }, <?php echo $loading_duration; ?>);
    });
</script>
<?php endif; ?>

<!-- En-tête fixe -->
<header class="fixed top-0 left-0 w-full z-50 bg-[color:var(--c-bg)]/85 backdrop-blur-md border-b border-line">
    <div class="max-w-[1440px] mx-auto px-5 md:px-12 h-16 md:h-20 flex justify-between items-center">
        <a href="<?php echo site_url('e/'.$slug); ?>" class="flex items-baseline gap-4 group">
            <span class="font-display italic text-2xl md:text-3xl tracking-tight text-accent group-hover:opacity-70 transition">
                <?php echo html_escape($brand_display); ?>
            </span>
            <?php if ($couple_date): ?>
                <span class="hidden md:inline t-label text-[11px] text-muted/80"><?php echo html_escape($couple_date); ?></span>
            <?php endif; ?>
        </a>
        <?php if ($photographer_logo): ?>
            <!-- Logo du photographe : visible à droite sur mobile ET grand écran. -->
            <img src="<?php echo base_url($photographer_logo); ?>" alt="Photographe"
                 class="h-8 md:h-10 w-auto object-contain">
        <?php else: ?>
            <!-- Repli : bouton Imprimer (grand écran) si aucun logo photographe. -->
            <button onclick="window.print()"
                    class="hidden md:flex items-center gap-2 t-label text-[11px] text-muted hover:text-[#1b1c1c] transition">
                <span class="material-symbols-outlined text-lg">print</span> Imprimer
            </button>
        <?php endif; ?>
    </div>
</header>
