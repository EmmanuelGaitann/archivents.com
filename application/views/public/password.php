<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$site_title = $settings['site_title'] ?? $event['nom'];
$titles = $settings['page_titles'] ?? array();
$heading = $titles['password'] ?? 'Accès privé';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($site_title); ?> — <?php echo html_escape($heading); ?></title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/tailwind.css'); ?>?v=<?php echo @filemtime(FCPATH.'assets/css/tailwind.css'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&family=Great+Vibes&display=swap" rel="stylesheet">
    <style>
        body{ background: <?php echo $settings['theme']['bg'] ?? '#fdf8f3'; ?>; }
        .font-wedding{ font-family:'Great Vibes',cursive; }
        .font-serif-w{ font-family:'Cormorant Garamond',serif; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 font-serif-w">
    <div class="w-full max-w-sm text-center">
        <h1 class="font-wedding text-5xl" style="color:<?php echo $settings['theme']['accent'] ?? '#7f5539'; ?>">
            <?php echo html_escape($site_title); ?>
        </h1>
        <p class="mt-2 text-gray-500 uppercase tracking-widest text-xs"><?php echo html_escape($heading); ?></p>

        <div class="mt-8 bg-white rounded-2xl border border-[#eaddd0] p-6">
            <?php if ( ! empty($pw_error)): ?>
                <div class="mb-4 text-sm text-red-600"><?php echo html_escape($pw_error); ?></div>
            <?php endif; ?>
            <?php echo form_open('e/'.$slug.'/password'); ?>
                <input type="password" name="password" required autofocus placeholder="Mot de passe"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-center focus:outline-none focus:ring-2 focus:ring-[#b08968]">
                <button type="submit"
                        class="mt-4 w-full rounded-lg text-white font-medium py-2.5"
                        style="background:<?php echo $settings['theme']['accent'] ?? '#7f5539'; ?>">
                    Entrer
                </button>
            <?php echo form_close(); ?>
        </div>
    </div>
</body>
</html>
