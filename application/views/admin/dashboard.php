<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<!-- En-tête -->
<div class="mb-12">
    <p class="t-label text-[11px] text-muted mb-2">Tableau de bord</p>
    <h1 class="font-display text-4xl md:text-5xl text-ink mb-3">Vue d'ensemble</h1>
    <p class="text-muted">
        Bienvenue, <?php echo html_escape($user['nom']); ?><?php if (($user['role'] ?? '') === 'super_admin'): ?> —
        rôle <span class="t-label text-[11px] bg-surface px-2 py-0.5 rounded-full"><?php echo html_escape($user['role']); ?></span><?php endif; ?>.
    </p>
</div>

<?php if ($msg = $this->session->flashdata('ok')): ?>
    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3"><?php echo html_escape($msg); ?></div>
<?php endif; ?>
<?php if ($err = $this->session->flashdata('err')): ?>
    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?php echo html_escape($err); ?></div>
<?php endif; ?>

<?php if ( ! empty($email_unverified)): ?>
<!-- Adresse e-mail non confirmée -->
<div class="mb-8 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 px-5 py-4 flex flex-wrap items-center gap-3">
    <span class="material-symbols-outlined">mark_email_unread</span>
    <div class="flex-grow text-sm">
        <b>Confirmez votre adresse e-mail.</b>
        Un lien de confirmation vous a été envoyé à l'inscription — pensez à vérifier vos indésirables.
    </div>
    <?php echo form_open('admin/dashboard/resend_verification'); ?>
        <button class="t-label text-[11px] underline">Renvoyer l'e-mail</button>
    <?php echo form_close(); ?>
</div>
<?php endif; ?>

<?php if ( ! empty($sub_pending)): ?>
<!-- Abonnement en attente d'activation (forfait payant non réglé) -->
<div class="mb-8 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 px-5 py-4 flex flex-wrap items-center gap-3">
    <span class="material-symbols-outlined">schedule</span>
    <div class="flex-grow text-sm">
        <b>Abonnement en attente d'activation.</b>
        Réglez votre forfait par Orange Money, MTN MoMo ou virement — ou contactez-nous pour une activation immédiate.
    </div>
    <a href="<?php echo site_url('pricing'); ?>" target="_blank" class="t-label text-[11px] underline">Voir les forfaits</a>
</div>
<?php elseif ( ! empty($plan)):
    // Quotas effectifs (dérogations du super admin incluses).
    $max = isset($events_max) ? $events_max : (($plan['max_events'] !== NULL) ? (int) $plan['max_events'] : NULL);
    $vol = function($b){ return $b >= 1073741824 ? round($b/1073741824, 2).' Go' : round($b/1048576).' Mo'; };
    $quota_b = ( ! empty($storage_quota_mo)) ? ((int) $storage_quota_mo) * 1048576 : NULL;
    $used_b  = isset($storage_used) ? (int) $storage_used : NULL;
    $pct     = ($quota_b && $used_b !== NULL) ? min(100, round($used_b / $quota_b * 100)) : NULL;
?>
<!-- Forfait actif + quotas (événements & stockage) -->
<div class="mb-8 rounded-lg border border-[#e4e2e2] bg-white px-5 py-4 flex flex-wrap items-center justify-between gap-3">
    <div class="text-sm">
        <span class="t-label text-[10px] text-muted">Forfait</span>
        <span class="font-display text-lg text-ink ml-2"><?php echo html_escape($plan['nom']); ?></span>
        <span class="text-muted ml-2"><?php echo (int) $events_used; ?><?php echo $max !== NULL ? ' / '.$max : ''; ?> événement<?php echo ($max > 1 || (int)$events_used > 1) ? 's' : ''; ?></span>
        <?php if ($used_b !== NULL): ?>
        <span class="text-muted ml-2">&middot; stockage <?php echo $vol($used_b); ?><?php echo $quota_b ? ' / '.$vol($quota_b) : ''; ?></span>
        <?php endif; ?>
    </div>
    <?php if ($pct !== NULL): ?>
    <div class="flex items-center gap-2" style="min-width:160px;">
        <div class="flex-grow h-1.5 rounded-full bg-[#efeded] overflow-hidden" style="min-width:100px;">
            <div class="h-full <?php echo $pct >= 90 ? 'bg-red-500' : 'bg-[#1b1c1c]'; ?>" style="width:<?php echo $pct; ?>%;"></div>
        </div>
        <span class="text-xs text-muted"><?php echo $pct; ?>%</span>
    </div>
    <?php endif; ?>
    <?php if (($max !== NULL && (int) $events_used >= $max) || ($pct !== NULL && $pct >= 90)): ?>
    <a href="<?php echo site_url('pricing'); ?>" target="_blank" class="t-label text-[11px] text-[#1b1c1c] underline">Améliorer mon forfait</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Audience (mis en avant) -->
<section class="grid grid-cols-2 gap-px bg-[#e4e2e2] border border-[#e4e2e2] mb-px">
    <?php
    $audience = array(
        array('group',     'Personnes connectées', $stats['visitors'],    'Appareils distincts'),
        array('login',     'Connexions',           $stats['connections'], 'Ouvertures de la galerie'),
    );
    foreach ($audience as $c): ?>
        <div class="bg-[#fbf9f9] p-6 md:p-8 border-b-2 border-transparent hover:border-[#1b1c1c] transition-all duration-300">
            <span class="material-symbols-outlined text-muted mb-4"><?php echo $c[0]; ?></span>
            <p class="t-label text-[10px] text-muted mb-1"><?php echo $c[1]; ?></p>
            <h3 class="font-display text-4xl md:text-5xl text-ink"><?php echo number_format((int) $c[2], 0, ',', ' '); ?></h3>
            <p class="text-xs text-muted mt-2"><?php echo $c[3]; ?></p>
        </div>
    <?php endforeach; ?>
</section>

<!-- Contenu -->
<section class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-[#e4e2e2] border border-[#e4e2e2] mb-16">
    <?php
    $cards = array(
        array('photo_library', 'Photos',            $stats['photos']),
        array('event_available','Événements actifs', $stats['events']),
        array('folder',         'Albums',            $stats['albums']),
        array('hourglass_top',  'En traitement',     $stats['pending']),
    );
    foreach ($cards as $c): ?>
        <div class="bg-[#fbf9f9] p-6 md:p-8 border-b-2 border-transparent hover:border-[#1b1c1c] transition-all duration-300">
            <span class="material-symbols-outlined text-muted mb-4"><?php echo $c[0]; ?></span>
            <p class="t-label text-[10px] text-muted mb-1"><?php echo $c[1]; ?></p>
            <h3 class="font-display text-3xl md:text-4xl text-ink"><?php echo number_format((int) $c[2], 0, ',', ' '); ?></h3>
        </div>
    <?php endforeach; ?>
</section>

<!-- Accès rapides -->
<div class="flex items-end justify-between mb-6">
    <h2 class="font-display text-2xl text-ink">Accès rapides</h2>
</div>

<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">

    <?php
    $links = array();
    if ($can_delete_evt) {
        $links[] = array('event', 'Événements', 'Créer, attribuer et supprimer des événements.', 'admin/events', 'Gérer les événements');
    }
    if ($can_upload) {
        $links[] = array('cloud_upload', 'Importer', 'Upload temps réel, traitement automatique.', 'admin/uploads', 'Ouvrir l\'import');
        $links[] = array('folder', 'Albums', 'Créer et organiser les dossiers.', 'admin/albums', 'Gérer les albums');
        $links[] = array('photo_library', 'Gérer les photos', 'Consulter et supprimer des photos.', 'admin/photos', 'Ouvrir la galerie');
    }
    $links[] = array('settings', 'Paramètres', 'Thème, modes d\'affichage, branding.', 'admin/settings', 'Configurer');
    if ($can_stats)     $links[] = array('monitoring', 'Statistiques', 'Visiteurs, vues, téléchargements.', 'admin/stats', 'Voir les statistiques');
    $links[] = array('qr_code_2', 'QR code', 'Lien d\'accès invités (suivi QR).', 'admin/qr', 'Afficher le QR');
    if ($can_retention) $links[] = array('history', 'Rétention', 'Prolonger ou purger les originaux.', 'admin/retention', 'Gérer');
    if ($can_users)     $links[] = array('group', 'Utilisateurs', 'Créer, modifier, supprimer les comptes.', 'admin/users', 'Gérer les utilisateurs');

    foreach ($links as $l): ?>
        <a href="<?php echo site_url($l[3]); ?>"
           class="group block bg-white border border-[#e4e2e2] p-6 hover:shadow-xl transition-all duration-300">
            <span class="material-symbols-outlined text-ink mb-4"><?php echo $l[0]; ?></span>
            <h3 class="font-display text-xl text-ink mb-1"><?php echo html_escape($l[1]); ?></h3>
            <p class="text-sm text-muted mb-4"><?php echo html_escape($l[2]); ?></p>
            <span class="t-label text-[11px] text-ink inline-flex items-center gap-1 group-hover:gap-2 transition-all">
                <?php echo html_escape($l[4]); ?>
                <span class="material-symbols-outlined text-base">arrow_forward</span>
            </span>
        </a>
    <?php endforeach; ?>

    <?php if ( ! $can_users): ?>
    <div class="bg-white/60 border border-dashed border-[#e4e2e2] p-6 text-muted">
        <span class="material-symbols-outlined mb-4">lock</span>
        <h3 class="font-display text-xl mb-1">Utilisateurs</h3>
        <p class="text-sm">Réservé au super administrateur.</p>
    </div>
    <?php endif; ?>

</div>
