<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="font-display text-3xl text-ink">Collaborateurs</h1>
    <a href="<?php echo site_url('admin/dashboard'); ?>" class="text-sm text-[#1b1c1c] hover:underline">&larr; Tableau de bord</a>
</div>

<?php if ($msg = $this->session->flashdata('ok')): ?>
    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3"><?php echo html_escape($msg); ?></div>
<?php endif; ?>
<?php if ($err = $this->session->flashdata('err')): ?>
    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?php echo html_escape($err); ?></div>
<?php endif; ?>

<p class="text-muted text-sm mb-6 max-w-2xl">
    Un collaborateur se connecte avec son propre compte et travaille sur <b>vos</b> événements :
    import de photos, albums, paramètres de galerie et statistiques.
    Il ne peut ni créer d'événement, ni toucher à votre abonnement.
</p>

<?php if ($max === 0): ?>
    <!-- Forfait sans collaboration -->
    <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-5 py-4 max-w-2xl">
        La collaboration n'est pas incluse dans votre forfait.
        Passez au forfait <b>Studio</b> pour inviter des collaborateurs (illimités).
        <a class="underline" href="<?php echo site_url('pricing'); ?>" target="_blank">Voir les forfaits</a>
    </div>
<?php else: ?>

    <!-- Quota -->
    <div class="mb-6 text-sm text-muted">
        <?php echo (int) $used; ?> collaborateur<?php echo $used > 1 ? 's' : ''; ?>
        <?php echo $max === NULL ? '· illimités avec votre forfait' : '· plafond : '.$max; ?>
    </div>

    <!-- Ajout -->
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-5 mb-6 max-w-2xl">
        <h2 class="font-display text-lg mb-3">Inviter un collaborateur</h2>
        <?php echo form_open('admin/collaborators/create', array('class' => 'grid sm:grid-cols-3 gap-3')); ?>
            <input type="text" name="nom" placeholder="Nom" required
                   class="rounded-lg border border-gray-300 px-3 py-2">
            <input type="email" name="email" placeholder="E-mail" required
                   class="rounded-lg border border-gray-300 px-3 py-2">
            <input type="text" name="password" placeholder="Mot de passe (8 car. min.)" required minlength="8"
                   autocomplete="new-password" class="rounded-lg border border-gray-300 px-3 py-2">
            <div class="sm:col-span-3">
                <button class="rounded-lg bg-[#1b1c1c] hover:bg-black text-white px-5 py-2.5">Créer le compte</button>
                <span class="text-xs text-gray-400 ml-2">Transmettez-lui vous-même le mot de passe.</span>
            </div>
        <?php echo form_close(); ?>
    </div>

    <!-- Liste -->
    <?php if (empty($collabs)): ?>
        <p class="text-gray-400">Aucun collaborateur pour le moment.</p>
    <?php else: ?>
    <div class="bg-white rounded-xl border border-[#e4e2e2] divide-y divide-[#f0e6da] max-w-3xl">
        <?php foreach ($collabs as $c): ?>
        <div class="flex flex-wrap items-center gap-3 p-4">
            <div class="flex-grow min-w-[200px]">
                <div class="text-sm font-medium text-ink"><?php echo html_escape($c['nom']); ?>
                    <?php if ( ! (int) $c['actif']): ?>
                        <span class="t-label text-[10px] bg-red-50 text-red-600 px-2 py-0.5 rounded-full ml-1">suspendu</span>
                    <?php endif; ?>
                </div>
                <div class="text-xs text-gray-400"><?php echo html_escape($c['email']); ?></div>
            </div>

            <?php echo form_open('admin/collaborators/password/'.$c['id'], array('class' => 'flex items-center gap-2')); ?>
                <input type="text" name="password" placeholder="Nouveau mot de passe" minlength="8" required
                       autocomplete="new-password" class="rounded-lg border border-gray-200 px-3 py-2 text-sm w-44">
                <button class="text-sm rounded-lg border border-[#e4e2e2] text-gray-600 px-3 py-2 hover:bg-[#f5f3f3]">Changer</button>
            <?php echo form_close(); ?>

            <?php echo form_open('admin/collaborators/toggle/'.$c['id']); ?>
                <button class="text-sm rounded-lg border border-[#e4e2e2] text-gray-600 px-3 py-2 hover:bg-[#f5f3f3]">
                    <?php echo (int) $c['actif'] ? 'Suspendre' : 'Réactiver'; ?>
                </button>
            <?php echo form_close(); ?>

            <?php echo form_open('admin/collaborators/delete/'.$c['id'], array(
                'onsubmit' => "return confirm('Supprimer le compte de « ".html_escape(addslashes($c['nom']))." » ? Vos galeries et photos ne sont pas touchées.');",
            )); ?>
                <button class="text-sm rounded-lg border border-red-200 text-red-600 px-3 py-2 hover:bg-red-50">Supprimer</button>
            <?php echo form_close(); ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php endif; ?>
