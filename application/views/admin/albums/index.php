<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="font-display text-3xl text-ink">Albums (dossiers)</h1>
    <a href="<?php echo site_url('admin/dashboard'); ?>" class="text-sm text-[#1b1c1c] hover:underline">&larr; Tableau de bord</a>
</div>

<?php if ($msg = $this->session->flashdata('ok')): ?>
    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3"><?php echo html_escape($msg); ?></div>
<?php endif; ?>
<?php if ($err = $this->session->flashdata('err')): ?>
    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?php echo html_escape($err); ?></div>
<?php endif; ?>

<?php if (empty($events)): ?>
    <p class="text-gray-400">Aucun événement.</p>
<?php else: ?>

<div class="mb-6 flex flex-wrap items-center gap-3">
    <select onchange="window.location='<?php echo site_url('admin/albums/index'); ?>/'+this.value"
            class="rounded-lg border border-gray-300 px-3 py-2">
        <?php foreach ($events as $e): ?>
            <option value="<?php echo $e['id']; ?>" <?php echo ($event && $e['id']==$event['id'])?'selected':''; ?>>
                <?php echo html_escape($e['nom']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <a href="<?php echo site_url('admin/uploads').'?event='.$event['id']; ?>"
       class="text-sm rounded-lg border border-[#e4e2e2] text-gray-600 px-4 py-2 hover:bg-[#f5f3f3]">Importer des photos</a>
    <a href="<?php echo site_url('admin/photos/index/'.$event['id']); ?>"
       class="text-sm rounded-lg border border-[#e4e2e2] text-gray-600 px-4 py-2 hover:bg-[#f5f3f3]">Gérer / supprimer des photos</a>
</div>

<!-- Ajouter un dossier -->
<div class="bg-white rounded-xl border border-[#e4e2e2] p-5 mb-6 max-w-xl">
    <h2 class="font-display text-lg mb-3">Nouveau dossier</h2>
    <?php echo form_open('admin/albums/create/'.$event['id'], array('class' => 'flex gap-3')); ?>
        <input type="text" name="nom" placeholder="Nom du dossier (ex. « Cérémonie »)" required
               class="flex-grow rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#6b6b6b]">
        <button class="rounded-lg bg-[#1b1c1c] hover:bg-[#000000] text-white px-5 py-2.5 whitespace-nowrap">Créer</button>
    <?php echo form_close(); ?>
</div>

<!-- Liste des dossiers -->
<?php if (empty($albums)): ?>
    <p class="text-gray-400">Aucun dossier pour cet événement. Créez-en un ci-dessus.</p>
<?php else: ?>
<p class="text-xs text-gray-400 mb-2 max-w-3xl">L'ordre ci-dessous est celui des dossiers sur la galerie publique — utilisez les flèches pour le changer.</p>
<div class="bg-white rounded-xl border border-[#e4e2e2] divide-y divide-[#f0e6da] max-w-3xl">
    <?php $last = count($albums) - 1; foreach ($albums as $i => $a):
        $cnt = (int) ($counts[$a['id']] ?? 0);
    ?>
    <div class="flex flex-wrap items-center gap-3 p-4">
        <div class="flex items-center gap-1">
            <?php echo form_open('admin/albums/move/'.$a['id'].'/up'); ?>
                <button <?php echo $i === 0 ? 'disabled' : ''; ?> title="Monter"
                        class="w-8 h-8 flex items-center justify-center rounded-lg border border-[#e4e2e2] text-gray-500 hover:bg-[#f5f3f3] disabled:opacity-25 disabled:cursor-default">
                    <span class="material-symbols-outlined text-base">arrow_upward</span>
                </button>
            <?php echo form_close(); ?>
            <?php echo form_open('admin/albums/move/'.$a['id'].'/down'); ?>
                <button <?php echo $i === $last ? 'disabled' : ''; ?> title="Descendre"
                        class="w-8 h-8 flex items-center justify-center rounded-lg border border-[#e4e2e2] text-gray-500 hover:bg-[#f5f3f3] disabled:opacity-25 disabled:cursor-default">
                    <span class="material-symbols-outlined text-base">arrow_downward</span>
                </button>
            <?php echo form_close(); ?>
        </div>
        <?php echo form_open('admin/albums/rename/'.$a['id'], array('class' => 'flex items-center gap-2 flex-grow')); ?>
            <input type="text" name="nom" value="<?php echo html_escape($a['nom']); ?>" required
                   class="flex-grow rounded-lg border border-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#6b6b6b]">
            <span class="text-xs text-gray-400 whitespace-nowrap"><?php echo $cnt; ?> photo<?php echo $cnt>1?'s':''; ?></span>
            <button class="text-sm rounded-lg border border-[#e4e2e2] text-gray-600 px-3 py-2 hover:bg-[#f5f3f3]">Renommer</button>
        <?php echo form_close(); ?>

        <?php echo form_open('admin/albums/delete/'.$a['id'], array(
            'onsubmit' => "return confirm('Supprimer le dossier « ".html_escape(addslashes($a['nom']))." » ? Les ".$cnt." photo(s) seront conservées mais sans dossier.');",
        )); ?>
            <button class="text-sm rounded-lg border border-red-200 text-red-600 px-3 py-2 hover:bg-red-50">Supprimer</button>
        <?php echo form_close(); ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>
