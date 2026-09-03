<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — Archivents</title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/tailwind.css'); ?>?v=<?php echo @filemtime(FCPATH.'assets/css/tailwind.css'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600&family=Hanken+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Hanken Grotesk', system-ui, sans-serif; }
        .font-display { font-family: 'Playfair Display', serif; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-[#fbf9f9] px-4">

    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="font-display text-5xl text-[#1b1c1c]">Archivents</h1>
            <p class="font-display text-xl text-[#6b6b6b] mt-1">Espace administration</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-[#e4e2e2] p-8">

            <?php if ( ! empty($error)): ?>
                <div class="mb-5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php echo form_open('admin/auth/login'); ?>
                <label class="block mb-4">
                    <span class="text-sm text-gray-600">Email</span>
                    <input type="email" name="email" required autofocus
                           value="<?php echo set_value('email'); ?>"
                           class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#6b6b6b]">
                </label>

                <label class="block mb-6">
                    <span class="text-sm text-gray-600">Mot de passe</span>
                    <input type="password" name="password" required
                           class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#6b6b6b]">
                </label>

                <button type="submit"
                        class="w-full rounded-lg bg-[#1b1c1c] hover:bg-[#000000] text-white font-medium py-2.5 transition">
                    Se connecter
                </button>
                <p class="mt-4 text-center text-sm">
                    <a href="<?php echo site_url('forgot'); ?>" class="text-gray-500 hover:text-[#1b1c1c] underline">Mot de passe oublié ?</a>
                </p>
            <?php echo form_close(); ?>
        </div>

        <p class="text-center text-xs text-gray-400 mt-6">
            Archivents — Galerie photo d'événement
        </p>
    </div>

</body>
</html>
