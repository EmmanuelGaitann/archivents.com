<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$vol = function($b){ return $b >= 1073741824 ? round($b/1073741824, 2).' Go' : round($b/1048576).' Mo'; };
// Badge par état d'une tâche cron (classes présentes dans le CSS compilé).
$cron_badge = array(
    'ok'    => array('bg-green-50 text-green-700 border border-green-200',  'À jour'),
    'stale' => array('bg-amber-50 text-amber-800 border border-amber-200',  'En retard'),
    'error' => array('bg-red-50 text-red-700 border border-red-200',        'Erreur'),
    'never' => array('bg-red-50 text-red-700 border border-red-200',        'Jamais exécutée'),
);
$flag = function($on, $yes = 'Oui', $no = 'Non') {
    return $on
        ? '<span class="t-label text-[10px] px-3 py-1 rounded-full bg-green-50 text-green-700 border border-green-200">'.$yes.'</span>'
        : '<span class="t-label text-[10px] px-3 py-1 rounded-full bg-red-50 text-red-700 border border-red-200">'.$no.'</span>';
};
?>

<div class="mb-8">
    <p class="t-label text-[11px] text-muted mb-2">Opérateur</p>
    <h1 class="font-display text-3xl text-ink">Système</h1>
    <p class="text-muted text-sm mt-1">Tâches planifiées, stockage, files d'attente et erreurs — tout ce qui doit tourner pour que la plateforme vive seule.</p>
</div>

<?php if ($msg = $this->session->flashdata('ok')): ?>
    <div class="mb-6 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3"><?php echo html_escape($msg); ?></div>
<?php endif; ?>
<?php if ($e = $this->session->flashdata('err')): ?>
    <div class="mb-6 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?php echo html_escape($e); ?></div>
<?php endif; ?>

<!-- Tâches planifiées (cron) -->
<h2 class="font-display text-2xl text-ink mb-4">Tâches planifiées</h2>
<div class="bg-white border border-[#e4e2e2] divide-y divide-[#efeded] mb-4">
    <?php foreach ($cron as $c): list($cls, $lbl) = $cron_badge[$c['state']]; ?>
    <div class="flex flex-wrap items-center gap-4 p-5">
        <div class="flex-grow min-w-0">
            <div class="text-sm text-ink font-mono"><?php echo html_escape($c['task']); ?></div>
            <div class="text-xs text-muted mt-0.5"><?php echo html_escape($c['label']); ?></div>
            <?php if ($c['last']): ?>
            <div class="t-label text-[10px] text-muted mt-1">
                Dernière exécution : <?php echo html_escape($c['last']['finished_at']); ?>
            </div>
            <?php endif; ?>
        </div>
        <span class="t-label text-[10px] px-3 py-1 rounded-full <?php echo $cls; ?>"><?php echo $lbl; ?></span>
    </div>
    <?php endforeach; ?>
</div>
<p class="text-xs text-muted mb-4">
    « Jamais exécutée » ou « En retard » = le cron n'est pas (ou plus) en place sur l'hébergement.
    Les deux lignes crontab à poser sur O2Switch sont documentées dans le README (section déploiement).
</p>

<!-- Purge manuelle (filet de sécurité si le cron n'est pas encore programmé) -->
<div class="bg-white border border-[#e4e2e2] p-5 mb-12">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h3 class="font-display text-lg text-ink mb-1">Lancer la purge maintenant</h3>
            <p class="text-xs text-muted max-w-xl">
                Exécute la tâche <span class="font-mono">purge_media</span> à la demande (même code que le cron) :
                nettoie les uploads jamais confirmés, rejoue les orphelins R2 et purge les galeries
                en fin de rétention/abonnement. Commencez par la <b>simulation</b> pour voir ce qui serait supprimé,
                sans rien effacer.
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <?php echo form_open('admin/system/run_purge', array('class' => 'inline')); ?>
                <input type="hidden" name="mode" value="dry">
                <button class="t-label text-[11px] border border-[#e4e2e2] px-4 py-2 hover:bg-[#f5f3f3]">Simulation</button>
            <?php echo form_close(); ?>
            <?php echo form_open('admin/system/run_purge', array('class' => 'inline',
                'onsubmit' => "return confirm('Lancer la purge réelle ? Les galeries en fin de rétention seront supprimées (action irréversible).');")); ?>
                <input type="hidden" name="mode" value="apply">
                <button class="t-label text-[11px] bg-[#1b1c1c] text-white px-4 py-2 hover:bg-[#000000]">Lancer la purge</button>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>

<!-- Santé de la plateforme -->
<h2 class="font-display text-2xl text-ink mb-4">Santé de la plateforme</h2>
<section class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-[#e4e2e2] border border-[#e4e2e2] mb-12">
    <?php
    $cards = array(
        array('Environnement',      strtoupper($health['env']).' · PHP '.$health['php'], NULL),
        array('Stockage médias',    strtoupper($health['storage_driver']), $health['storage_driver'] === 'r2' ? $flag($health['r2_configured'], 'R2 configuré', 'R2 NON configuré') : ''),
        array('Transformations CF', '', $flag($health['cf_transform'], 'Actives', 'Inactives')),
        array('E-mail (SMTP)',      '', $flag($health['smtp'], 'Configuré', 'NON configuré')),
    );
    foreach ($cards as $c): ?>
    <div class="bg-[#fbf9f9] p-6">
        <p class="t-label text-[10px] text-muted mb-2"><?php echo $c[0]; ?></p>
        <?php if ($c[1] !== ''): ?><p class="font-display text-xl text-ink mb-2"><?php echo html_escape($c[1]); ?></p><?php endif; ?>
        <?php echo $c[2]; ?>
    </div>
    <?php endforeach; ?>
    <?php if ($health['disk_free'] !== NULL): ?>
    <div class="bg-[#fbf9f9] p-6">
        <p class="t-label text-[10px] text-muted mb-2">Disque serveur libre</p>
        <p class="font-display text-xl text-ink"><?php echo $vol($health['disk_free']); ?><?php if ($health['disk_total']): ?> <span class="text-muted text-sm">/ <?php echo $vol($health['disk_total']); ?></span><?php endif; ?></p>
    </div>
    <?php endif; ?>
</section>

<!-- Stockage R2 -->
<h2 class="font-display text-2xl text-ink mb-4">Stockage (palier gratuit R2 : 10 Go)</h2>
<div class="bg-white border border-[#e4e2e2] p-6 mb-4">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <div class="text-sm text-ink">
            <b><?php echo $vol($storage['bytes']); ?></b> utilisés
            <span class="text-muted">· <?php echo (int) $storage['photos_n']; ?> photos, <?php echo (int) $storage['videos_n']; ?> vidéos</span>
        </div>
        <span class="text-xs text-muted"><?php echo $storage['pct']; ?> % des 10 Go gratuits</span>
    </div>
    <div class="h-1.5 rounded-full bg-[#efeded] overflow-hidden">
        <div class="h-full <?php echo $storage['pct'] >= 90 ? 'bg-red-500' : 'bg-[#1b1c1c]'; ?>" style="width:<?php echo $storage['pct']; ?>%;"></div>
    </div>
    <p class="text-xs text-muted mt-3">
        Au-delà de 10 Go, R2 facture 0,015 $/Go/mois. La purge quotidienne (<span class="font-mono">purge_media</span>)
        maintient ce volume en supprimant les galeries en fin de rétention.
    </p>
</div>
<?php if ( ! empty($storage['top'])): ?>
<div class="bg-white border border-[#e4e2e2] divide-y divide-[#efeded] mb-12">
    <?php foreach ($storage['top'] as $t): ?>
    <div class="flex items-center gap-4 p-4">
        <div class="flex-grow min-w-0">
            <div class="text-sm text-ink"><?php echo html_escape($t['nom']); ?></div>
            <div class="t-label text-[10px] text-muted"><?php echo html_escape($t['email']); ?></div>
        </div>
        <div class="text-sm text-ink"><b><?php echo $vol((float) $t['bytes']); ?></b>
            <span class="text-muted text-xs">· <?php echo (int) $t['objets']; ?> fichier(s)</span></div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<p class="text-muted text-sm mb-12">Aucun média stocké pour l'instant.</p>
<?php endif; ?>

<!-- Files d'attente & anomalies -->
<h2 class="font-display text-2xl text-ink mb-4">Files d'attente &amp; anomalies</h2>
<section class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-[#e4e2e2] border border-[#e4e2e2] mb-12">
    <?php
    $q = array(
        array('Photos en attente de confirmation', $queues['photos_pending'], 'Uploads jamais confirmés — nettoyés après 48 h par la purge.'),
        array('Vidéos en attente',                 $queues['videos_pending'], 'Idem, pipeline vidéo.'),
        array('Orphelins R2',                      $queues['orphans'],        $queues['orphans_stuck'] ? $queues['orphans_stuck'].' bloqué(s) après 3 tentatives — à vérifier.' : 'Suppressions R2 échouées, re-tentées par la purge.'),
        array('Jobs disque (ancien pipeline)',     $queues['jobs_pending'],   'File du traitement local (inutilisée quand R2 est actif).'),
    );
    foreach ($q as $c): $alert = ($c[1] > 0); ?>
    <div class="bg-[#fbf9f9] p-6">
        <p class="t-label text-[10px] text-muted mb-1"><?php echo $c[0]; ?></p>
        <h3 class="font-display text-3xl <?php echo $alert ? 'text-red-700' : 'text-ink'; ?>"><?php echo number_format((int) $c[1], 0, ',', ' '); ?></h3>
        <p class="text-xs text-muted mt-2"><?php echo $c[2]; ?></p>
    </div>
    <?php endforeach; ?>
</section>

<!-- Abonnements à surveiller -->
<h2 class="font-display text-2xl text-ink mb-4">Abonnements à surveiller</h2>
<section class="grid grid-cols-2 lg:grid-cols-3 gap-px bg-[#e4e2e2] border border-[#e4e2e2] mb-4">
    <?php
    $sc = array(
        array('À activer (en attente)', $subs['pending'],          $subs['pending'] > 0),
        array('Actifs',                 $subs['active'],           FALSE),
        array('Paiements en attente',   $subs['payments_pending'], $subs['payments_pending'] > 0),
    );
    foreach ($sc as $c): ?>
    <div class="bg-[#fbf9f9] p-6">
        <p class="t-label text-[10px] text-muted mb-1"><?php echo $c[0]; ?></p>
        <h3 class="font-display text-3xl <?php echo $c[2] ? 'text-amber-800' : 'text-ink'; ?>"><?php echo (int) $c[1]; ?></h3>
    </div>
    <?php endforeach; ?>
</section>
<?php if ( ! empty($subs['expiring'])): ?>
<div class="bg-white border border-[#e4e2e2] divide-y divide-[#efeded] mb-4">
    <?php foreach ($subs['expiring'] as $s): ?>
    <div class="flex flex-wrap items-center gap-4 p-4">
        <div class="flex-grow min-w-0">
            <div class="text-sm text-ink"><?php echo html_escape($s['nom']); ?> <span class="text-muted">· <?php echo html_escape($s['plan_nom']); ?></span></div>
            <div class="t-label text-[10px] text-muted"><?php echo html_escape($s['email']); ?></div>
        </div>
        <span class="t-label text-[10px] px-3 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200">
            Expire le <?php echo substr($s['expires_at'], 0, 10); ?>
        </span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<p class="text-xs text-muted mb-12">
    Les abonnements listés ici expirent sous 7 jours — relancez le client avant que la purge
    (30 jours après expiration) ne supprime ses galeries.
    <a class="underline" href="<?php echo site_url('admin/subscriptions'); ?>">Gérer les abonnements</a>
</p>

<!-- Erreurs PHP récentes -->
<h2 class="font-display text-2xl text-ink mb-4">Erreurs PHP récentes (48 h)</h2>
<?php if (empty($errors)): ?>
    <p class="text-muted text-sm mb-12">Aucune erreur consignée. ✓</p>
<?php else: ?>
<div class="bg-white border border-red-200 divide-y divide-[#efeded] mb-12">
    <?php foreach ($errors as $l): ?>
    <div class="p-3 text-xs font-mono text-red-700 break-words"><?php echo html_escape($l); ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Historique des exécutions cron -->
<h2 class="font-display text-2xl text-ink mb-4">Historique des tâches</h2>
<?php if (empty($logs)): ?>
    <p class="text-muted text-sm">Aucune exécution journalisée (la table <span class="font-mono">cron_log</span> se remplit à chaque passage du cron).</p>
<?php else: ?>
<div class="bg-white border border-[#e4e2e2] divide-y divide-[#efeded]">
    <?php foreach ($logs as $l): ?>
    <details class="p-4">
        <summary class="cursor-pointer flex flex-wrap items-center gap-3">
            <span class="text-sm font-mono text-ink"><?php echo html_escape($l['task']); ?></span>
            <span class="text-xs text-muted"><?php echo html_escape($l['finished_at']); ?></span>
            <span class="t-label text-[10px] px-3 py-1 rounded-full <?php echo (int) $l['ok'] ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                <?php echo (int) $l['ok'] ? 'OK' : 'Erreur'; ?>
            </span>
        </summary>
        <pre class="mt-3 text-xs text-muted whitespace-pre-wrap break-words"><?php echo html_escape($l['output']); ?></pre>
    </details>
    <?php endforeach; ?>
</div>
<?php endif; ?>
