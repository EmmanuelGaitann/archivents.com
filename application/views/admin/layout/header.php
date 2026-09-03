<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Permissions du rôle courant (pour filtrer le menu).
$role = $user['role'] ?? '';
$matrix = $this->config->item('role_permissions');
$role_perms = $matrix[$role] ?? array();
$can = function ($p) use ($role_perms) {
    return in_array('*', $role_perms, TRUE) || in_array($p, $role_perms, TRUE);
};

// Segment actif (admin/<segment>/...).
$active = $this->uri->segment(2) ?: 'dashboard';

// Définition du menu : [clé, libellé, url, permission|null, icône Material Symbol].
$nav = array(
    array('dashboard', 'Tableau de bord', site_url('admin/dashboard'), NULL,            'dashboard'),
    array('events',    'Événements',      site_url('admin/events'),    'event.create',   'event'),
    array('uploads',   'Importer',        site_url('admin/uploads'),   'photo.upload',   'cloud_upload'),
    array('albums',    'Albums',          site_url('admin/albums'),    'album.crud',     'folder'),
    array('photos',    'Gérer les photos',site_url('admin/photos'),    'photo.upload',   'photo_library'),
    array('settings',  'Paramètres',      site_url('admin/settings'),  'settings.edit',  'settings'),
    array('stats',     'Statistiques',    site_url('admin/stats'),     'stats.view',     'monitoring'),
    array('qr',        'QR code',         site_url('admin/qr'),        NULL,             'qr_code_2'),
    array('retention', 'Rétention',       site_url('admin/retention'), 'retention.manage','history'),
    array('collaborators','Collaborateurs',site_url('admin/collaborators'),'collab.manage', 'group_add'),
    array('subscriptions','Abonnements',  site_url('admin/subscriptions'),'subscriptions.manage','card_membership'),
    array('users',     'Utilisateurs',    site_url('admin/users'),     'users.manage',   'group'),
    array('system',    'Système',         site_url('admin/system'),    'system.monitor', 'monitor_heart'),
);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo isset($title) ? html_escape($title).' — ' : ''; ?>Archivents Admin</title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/tailwind.css'); ?>?v=<?php echo @filemtime(FCPATH.'assets/css/tailwind.css'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,300,0,0&display=swap" rel="stylesheet">
    <style>
        :root{
            --c-bg:#fbf9f9; --c-ink:#1b1c1c; --c-muted:#5d6060;
            --c-surface:#f5f3f3; --c-line:#e4e2e2; --c-outline:#c4c7c7;
        }
        html,body{ background:var(--c-bg); color:var(--c-ink); }
        .font-display{ font-family:'Playfair Display',serif; }
        .font-body{ font-family:'Hanken Grotesk',system-ui,sans-serif; }
        .t-label{ font-family:'Hanken Grotesk',sans-serif; text-transform:uppercase; letter-spacing:.12em; font-weight:600; }
        .text-ink{ color:var(--c-ink); } .text-muted{ color:var(--c-muted); }
        .bg-surface{ background:var(--c-surface); } .border-line{ border-color:var(--c-line); }
        .glass{ backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); }
        .material-symbols-outlined{ font-family:'Material Symbols Outlined'; font-weight:normal; font-style:normal; line-height:1; display:inline-block; white-space:nowrap; direction:ltr; font-variation-settings:'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 24; }
        ::-webkit-scrollbar{ width:6px; height:6px; }
        ::-webkit-scrollbar-thumb{ background:var(--c-outline); border-radius:10px; }
    </style>
</head>
<body class="font-body antialiased min-h-screen text-ink">
<script>
    // Jeton CSRF disponible pour les appels AJAX du back-office.
    window.CSRF = {
        name: '<?php echo $this->security->get_csrf_token_name(); ?>',
        hash: '<?php echo $this->security->get_csrf_hash(); ?>'
    };
</script>

<!-- Barre supérieure (glass) -->
<header class="fixed top-0 left-0 w-full z-40 glass bg-[#fbf9f9]/80 border-b border-line">
    <div class="px-5 md:px-8 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <button id="sb-toggle" class="lg:hidden text-ink hover:opacity-60" aria-label="Menu">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <a href="<?php echo site_url('admin/dashboard'); ?>" class="flex items-baseline gap-2">
                <span class="font-display text-2xl tracking-tight text-ink">Archivents</span>
                <span class="t-label text-[10px] text-muted">admin</span>
            </a>
        </div>
        <div class="flex items-center gap-4 text-sm">
            <?php if (isset($user)): ?>
                <span class="hidden sm:flex items-center gap-2 text-muted">
                    <?php echo html_escape($user['nom']); ?>
                    <?php if (($user['role'] ?? '') === 'super_admin'): ?>
                        <span class="t-label text-[10px] bg-surface text-ink px-2 py-0.5 rounded-full">
                            <?php echo html_escape($user['role']); ?>
                        </span>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
            <a href="<?php echo site_url('admin/auth/logout'); ?>"
               class="t-label text-[11px] text-muted hover:text-[#1b1c1c] transition inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-lg">logout</span>
                <span class="hidden sm:inline">Déconnexion</span>
            </a>
        </div>
    </div>
</header>

<div class="flex pt-16">
    <!-- Sidebar -->
    <aside id="sidebar"
           class="fixed lg:sticky top-16 left-0 z-30 h-[calc(100vh-4rem)] w-72 bg-[#fbf9f9] border-r border-line
                  transform -translate-x-full lg:translate-x-0 transition-transform duration-200 overflow-y-auto">
        <nav class="p-4 space-y-1">
            <?php foreach ($nav as $item):
                list($key, $label, $url, $perm, $icon) = $item;
                if ($perm !== NULL && ! $can($perm)) continue;
                $is_active = ($active === $key);
            ?>
                <a href="<?php echo $url; ?>"
                   class="flex items-center gap-3 rounded-full px-4 py-3 transition
                          <?php echo $is_active
                              ? 'bg-[#1b1c1c] text-white'
                              : 'text-[#5d6060] hover:bg-[#f5f3f3] hover:text-[#1b1c1c]'; ?>">
                    <span class="material-symbols-outlined text-xl"><?php echo $icon; ?></span>
                    <span class="t-label text-[11px]"><?php echo $label; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- Overlay mobile -->
    <div id="sb-overlay" class="fixed inset-0 top-16 bg-black/30 z-20 hidden lg:hidden"></div>

    <!-- Contenu -->
    <main class="flex-1 min-w-0 px-5 sm:px-8 py-10">
