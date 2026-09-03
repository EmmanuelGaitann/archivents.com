<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$fmt = function($n){ return number_format((int)$n, 0, ',', ' ').' FCFA'; };
$vol = function($b){ return $b >= 1073741824 ? round($b/1073741824, 2).' Go' : round($b/1048576).' Mo'; };
$storage_by_user = $storage_by_user ?? array();
$per = array('free'=>'gratuit','monthly'=>'/mois','yearly'=>'/an','per_event'=>'unique');
// Styles de badge par statut (classes présentes dans le CSS compilé).
$badge = array(
    'en_attente' => 'bg-amber-50 text-amber-800 border border-amber-200',
    'actif'      => 'bg-green-50 text-green-700 border border-green-200',
    'expire'     => 'bg-[#f5f3f3] text-muted border border-[#e4e2e2]',
    'annule'     => 'bg-red-50 text-red-700 border border-red-200',
);
$slabel = array('en_attente'=>'En attente','actif'=>'Actif','expire'=>'Expiré','annule'=>'Annulé');
$tabs = array(''=>'Tous','en_attente'=>'En attente','actif'=>'Actifs','expire'=>'Expirés','annule'=>'Annulés');
?>

<div class="mb-8">
    <p class="t-label text-[11px] text-muted mb-2">Opérateur</p>
    <h1 class="font-display text-3xl text-ink">Abonnements</h1>
    <p class="text-muted text-sm mt-1">Activez, prolongez et suivez les abonnements et paiements.</p>
</div>

<?php if ($msg = $this->session->flashdata('ok')): ?>
    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3"><?php echo html_escape($msg); ?></div>
<?php endif; ?>
<?php if ($e = $this->session->flashdata('err')): ?>
    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?php echo html_escape($e); ?></div>
<?php endif; ?>

<!-- Filtres -->
<div class="flex flex-wrap gap-2 mb-6">
    <?php foreach ($tabs as $k => $lbl):
        $active = ($filter === ($k ?: NULL));
        $n = $k ? ($counts[$k] ?? 0) : array_sum($counts);
        $url = site_url('admin/subscriptions'.($k ? '?statut='.$k : '')); ?>
    <a href="<?php echo $url; ?>"
       class="t-label text-[11px] px-4 py-2 border <?php echo $active ? 'bg-[#1b1c1c] text-white border-[#1b1c1c]' : 'border-[#e4e2e2] text-muted hover:bg-[#f5f3f3]'; ?>">
        <?php echo $lbl; ?> <span class="opacity-60">(<?php echo (int)$n; ?>)</span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Abonnements -->
<?php if (empty($subs)): ?>
    <p class="text-muted font-display italic text-xl py-12">Aucun abonnement pour ce filtre.</p>
<?php else: ?>
<div class="bg-white border border-[#e4e2e2] divide-y divide-[#efeded] mb-14">
    <?php foreach ($subs as $s): ?>
    <div class="flex flex-wrap items-center gap-4 p-5">
        <div class="flex-grow min-w-0">
            <div class="font-display text-lg text-ink"><?php echo html_escape($s['user_nom'] ?: '—'); ?>
                <?php if ($s['studio_slug']): ?><span class="text-muted text-sm">· <?php echo html_escape($s['studio_slug']); ?>.archivents.com</span><?php endif; ?>
            </div>
            <div class="t-label text-[10px] text-muted mt-1">
                <?php echo html_escape($s['user_email']); ?> &middot;
                <?php echo html_escape($s['plan_nom'] ?: '—'); ?> &middot;
                <?php echo $fmt($s['plan_prix']); ?> <?php echo $per[$s['billing_period']] ?? ''; ?>
            </div>
            <div class="text-xs text-muted mt-1">
                <?php echo $s['started_at'] ? 'Depuis '.substr($s['started_at'],0,10) : 'Non démarré'; ?>
                <?php if ($s['expires_at']): ?> &middot; échéance <?php echo substr($s['expires_at'],0,10); ?><?php endif; ?>
                <?php if ($s['note']): ?> &middot; <span class="italic"><?php echo html_escape($s['note']); ?></span><?php endif; ?>
            </div>
            <div class="text-xs text-muted mt-1">
                Stockage utilisé : <b class="text-ink"><?php echo $vol($storage_by_user[(int) $s['user_id']] ?? 0); ?></b>
                <?php if ($s['storage_quota_mo'] !== NULL): ?>
                    &middot; dérogation stockage : <b class="text-ink"><?php echo (int) $s['storage_quota_mo'] ? $vol(((int) $s['storage_quota_mo']) * 1048576) : 'illimité'; ?></b>
                <?php endif; ?>
                <?php if ($s['events_quota'] !== NULL): ?>
                    &middot; quota événements : <b class="text-ink"><?php echo (int) $s['events_quota'] ?: 'illimité'; ?></b>
                <?php endif; ?>
            </div>

            <!-- Dérogations de quota (super admin) : vide = valeur du plan, 0 = illimité -->
            <details class="mt-2">
                <summary class="t-label text-[10px] text-muted cursor-pointer hover:text-[#1b1c1c]">Dérogations de quota…</summary>
                <?php echo form_open('admin/subscriptions/quota/'.$s['id'], array('class'=>'flex flex-wrap items-end gap-3 mt-2')); ?>
                    <label class="text-xs text-muted">Événements
                        <input type="number" name="events_quota" min="0" value="<?php echo $s['events_quota'] !== NULL ? (int) $s['events_quota'] : ''; ?>"
                               placeholder="plan" class="block text-xs border border-[#e4e2e2] rounded px-2 py-1 w-24 mt-1">
                    </label>
                    <label class="text-xs text-muted">Stockage (Mo)
                        <input type="number" name="storage_quota_mo" min="0" value="<?php echo $s['storage_quota_mo'] !== NULL ? (int) $s['storage_quota_mo'] : ''; ?>"
                               placeholder="plan" class="block text-xs border border-[#e4e2e2] rounded px-2 py-1 w-28 mt-1">
                    </label>
                    <button class="t-label text-[11px] border border-[#e4e2e2] px-4 py-1.5 hover:bg-[#f5f3f3]">Enregistrer</button>
                    <span class="text-[10px] text-muted">vide = plan &middot; 0 = illimité</span>
                <?php echo form_close(); ?>
            </details>
        </div>

        <span class="t-label text-[10px] px-3 py-1 rounded-full <?php echo $badge[$s['statut']] ?? ''; ?>">
            <?php echo $slabel[$s['statut']] ?? $s['statut']; ?>
        </span>

        <div class="flex items-center gap-2">
            <?php if ($s['statut'] === 'en_attente' || $s['statut'] === 'expire'): ?>
                <?php echo form_open('admin/subscriptions/activate/'.$s['id'], array('class'=>'inline')); ?>
                    <button class="t-label text-[11px] bg-[#1b1c1c] text-white px-4 py-2 hover:bg-[#000000]">Activer</button>
                <?php echo form_close(); ?>
            <?php endif; ?>
            <?php if ($s['statut'] === 'actif' && in_array($s['billing_period'], array('monthly','yearly'), TRUE)): ?>
                <?php echo form_open('admin/subscriptions/extend/'.$s['id'], array('class'=>'inline')); ?>
                    <button class="t-label text-[11px] border border-[#e4e2e2] px-4 py-2 hover:bg-[#f5f3f3]">Prolonger</button>
                <?php echo form_close(); ?>
            <?php endif; ?>
            <?php if ($s['statut'] === 'actif'): ?>
                <?php echo form_open('admin/subscriptions/expire/'.$s['id'], array('class'=>'inline','onsubmit'=>"return confirm('Marquer expiré ?');")); ?>
                    <button class="text-muted hover:text-[#1b1c1c] p-2" title="Marquer expiré"><span class="material-symbols-outlined text-xl">history</span></button>
                <?php echo form_close(); ?>
            <?php endif; ?>
            <?php if ($s['statut'] !== 'annule'): ?>
                <?php echo form_open('admin/subscriptions/cancel/'.$s['id'], array('class'=>'inline','onsubmit'=>"return confirm('Annuler cet abonnement ?');")); ?>
                    <button class="text-red-600 hover:opacity-70 p-2" title="Annuler"><span class="material-symbols-outlined text-xl">block</span></button>
                <?php echo form_close(); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Paiements récents -->
<h2 class="font-display text-2xl text-ink mb-4">Paiements récents</h2>
<?php if (empty($payments)): ?>
    <p class="text-muted text-sm">Aucun paiement enregistré.</p>
<?php else: ?>
<div class="bg-white border border-[#e4e2e2] divide-y divide-[#efeded]">
    <?php foreach ($payments as $p): ?>
    <div class="flex flex-wrap items-center gap-4 p-4">
        <div class="flex-grow min-w-0">
            <div class="text-sm text-ink"><?php echo html_escape($p['user_nom'] ?: '—'); ?>
                <span class="text-muted">· <?php echo html_escape($p['plan_nom'] ?: '—'); ?></span></div>
            <div class="t-label text-[10px] text-muted mt-0.5">
                <?php echo $fmt($p['montant']); ?> &middot; <?php echo html_escape($p['methode']); ?>
                <?php if ($p['reference']): ?> &middot; réf <?php echo html_escape($p['reference']); ?><?php endif; ?>
                <?php if ($p['paid_at']): ?> &middot; payé le <?php echo substr($p['paid_at'],0,10); ?><?php endif; ?>
            </div>
        </div>
        <span class="t-label text-[10px] px-3 py-1 rounded-full <?php echo $p['statut']==='paye' ? 'bg-green-50 text-green-700 border border-green-200' : ($p['statut']==='en_attente' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-[#f5f3f3] text-muted border border-[#e4e2e2]'); ?>">
            <?php echo $p['statut']==='paye' ? 'Payé' : ($p['statut']==='en_attente' ? 'En attente' : html_escape($p['statut'])); ?>
        </span>
        <?php if ($p['statut'] === 'en_attente'): ?>
        <?php echo form_open('admin/subscriptions/pay/'.$p['id'], array('class'=>'flex items-center gap-2')); ?>
            <input type="text" name="reference" placeholder="Réf. OM/MoMo" class="text-xs border border-[#e4e2e2] rounded px-2 py-1 w-32">
            <button class="t-label text-[11px] bg-[#1b1c1c] text-white px-3 py-1.5 hover:bg-[#000000]">Marquer payé</button>
        <?php echo form_close(); ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
