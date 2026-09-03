<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="flex items-center justify-between mb-8">
    <div>
        <p class="t-label text-[11px] text-muted mb-2">Gestion</p>
        <h1 class="font-display text-3xl text-ink">Événements</h1>
    </div>
    <?php if ( ! empty($can_create)): ?>
    <a href="<?php echo site_url('admin/events/create'); ?>"
       class="inline-flex items-center gap-2 bg-[#1b1c1c] text-white px-5 py-2.5 hover:bg-[#000000] transition">
        <span class="material-symbols-outlined text-xl">add</span>
        <span class="t-label text-[11px]">Créer un événement</span>
    </a>
    <?php else: ?>
    <span class="inline-flex items-center gap-2 bg-[#e4e2e2] text-muted px-5 py-2.5 cursor-not-allowed" title="Création indisponible">
        <span class="material-symbols-outlined text-xl">lock</span>
        <span class="t-label text-[11px]">Créer un événement</span>
    </span>
    <?php endif; ?>
</div>

<?php if ( ! $can_assign): // Bandeau forfait/quota du photographe ?>
    <?php if ($plan): $max = ($plan['max_events'] !== NULL) ? (int) $plan['max_events'] : NULL; ?>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-[#e4e2e2] bg-white px-5 py-4">
        <div class="text-sm">
            <span class="t-label text-[10px] text-muted">Forfait</span>
            <span class="font-display text-lg text-ink ml-2"><?php echo html_escape($plan['nom']); ?></span>
            <span class="text-muted ml-2">
                <?php echo (int) $used; ?><?php echo $max !== NULL ? ' / '.$max : ''; ?>
                événement<?php echo ((int)$used > 1 || $max > 1) ? 's' : ''; ?> utilisé<?php echo (int)$used > 1 ? 's' : ''; ?>
            </span>
        </div>
        <?php if ( ! $can_create && $create_block === 'quota'): ?>
        <a href="<?php echo site_url('pricing'); ?>" target="_blank" class="t-label text-[11px] text-[#1b1c1c] underline">Passer à un forfait supérieur</a>
        <?php endif; ?>
    </div>
    <?php else: // Abonnement pas encore actif ?>
    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 text-sm px-5 py-4 flex items-center gap-3">
        <span class="material-symbols-outlined">schedule</span>
        <div>Votre abonnement n'est pas encore actif. Réglez votre forfait ou
            <a href="mailto:contact@archivents.com" class="underline">contactez-nous</a> pour créer vos événements.</div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($msg = $this->session->flashdata('ok')): ?>
    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3"><?php echo html_escape($msg); ?></div>
<?php endif; ?>
<?php if ($err = $this->session->flashdata('err')): ?>
    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?php echo html_escape($err); ?></div>
<?php endif; ?>

<?php if (empty($events)): ?>
    <p class="text-muted font-display italic text-xl py-12">Aucun événement. Créez-en un.</p>
<?php else: ?>
<div class="bg-white border border-[#e4e2e2] divide-y divide-[#efeded]">
    <?php foreach ($events as $e): ?>
    <div class="flex flex-wrap items-center gap-4 p-5">
        <div class="flex-grow min-w-0">
            <div class="font-display text-xl text-ink"><?php echo html_escape($e['nom']); ?></div>
            <div class="t-label text-[10px] text-muted mt-1">
                /<?php echo html_escape($e['slug']); ?> &middot;
                <?php echo html_escape($e['type']); ?> &middot;
                <?php echo $e['date_evt'] ? html_escape($e['date_evt']) : 'sans date'; ?> &middot;
                <?php echo (int) $e['photos']; ?> photo<?php echo $e['photos']>1?'s':''; ?> &middot;
                <?php echo (int) $e['albums']; ?> album<?php echo $e['albums']>1?'s':''; ?>
            </div>
        </div>

        <div class="text-right">
            <span class="t-label text-[10px] <?php echo $e['statut']==='actif'?'text-green-600':'text-muted'; ?>"><?php echo html_escape($e['statut']); ?></span>
            <?php if ( ! empty($can_assign)): ?>
            <div class="text-xs text-muted mt-0.5">
                <?php echo $e['owner'] ? 'Attribué à '.html_escape($e['owner']) : 'Non attribué'; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="flex items-center gap-2">
            <a href="<?php echo site_url('e/'.$e['public_code']); ?>" target="_blank"
               class="text-muted hover:text-[#1b1c1c] p-2" title="Voir la galerie">
                <span class="material-symbols-outlined text-xl">open_in_new</span>
            </a>
            <a href="<?php echo site_url('admin/events/edit/'.$e['id']); ?>"
               class="text-muted hover:text-[#1b1c1c] p-2" title="Modifier">
                <span class="material-symbols-outlined text-xl">edit</span>
            </a>
            <?php echo form_open('admin/events/delete/'.$e['id'], array(
                'class' => 'inline',
                'onsubmit' => "return confirm('Supprimer « ".html_escape(addslashes($e['nom']))." » ? Cela supprime DÉFINITIVEMENT ses ".$e['photos']." photo(s), albums et fichiers.');",
            )); ?>
                <button class="text-red-600 hover:opacity-70 p-2" title="Supprimer">
                    <span class="material-symbols-outlined text-xl">delete</span>
                </button>
            <?php echo form_close(); ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
