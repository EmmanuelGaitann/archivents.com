<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$is_edit = ($mode === 'edit');
?>

<div class="flex items-center justify-between mb-8">
    <div>
        <p class="t-label text-[11px] text-muted mb-2">Événements</p>
        <h1 class="font-display text-3xl text-ink"><?php echo $is_edit ? 'Modifier l\'événement' : 'Nouvel événement'; ?></h1>
    </div>
    <a href="<?php echo site_url('admin/events'); ?>" class="text-sm text-[#1b1c1c] hover:underline">&larr; Tous les événements</a>
</div>

<?php echo form_open('admin/events/save', array('class' => 'space-y-6 max-w-2xl')); ?>
    <input type="hidden" name="id" value="<?php echo $is_edit ? (int) $event['id'] : ''; ?>">

    <div class="bg-white border border-[#e4e2e2] p-6 space-y-5">
        <label class="block">
            <span class="text-sm text-gray-600">Nom de l'événement</span>
            <input type="text" name="nom" value="<?php echo html_escape($event['nom']); ?>" required
                   class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#1b1c1c]">
        </label>

        <div class="grid sm:grid-cols-2 gap-4">
            <label class="block">
                <span class="text-sm text-gray-600">Type</span>
                <select name="type" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                    <?php foreach ($types as $t): ?>
                        <option value="<?php echo $t; ?>" <?php echo ($event['type']===$t)?'selected':''; ?>><?php echo ucfirst($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block">
                <span class="text-sm text-gray-600">Date</span>
                <input type="date" name="date_evt" value="<?php echo html_escape($event['date_evt'] ?? ''); ?>"
                       class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
            </label>
        </div>

        <label class="block">
            <span class="text-sm text-gray-600">Slug (URL publique)</span>
            <input type="text" name="slug" value="<?php echo html_escape($event['slug']); ?>"
                   placeholder="auto-généré depuis le nom si vide"
                   class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#1b1c1c]">
            <span class="text-xs text-gray-400">Lettres, chiffres et tirets. Rendu unique automatiquement.</span>
        </label>

        <div class="grid sm:grid-cols-2 gap-4">
            <label class="block">
                <span class="text-sm text-gray-600">Statut</span>
                <select name="statut" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                    <option value="actif"   <?php echo ($event['statut']==='actif')?'selected':''; ?>>Actif</option>
                    <option value="archive" <?php echo ($event['statut']==='archive')?'selected':''; ?>>Archivé</option>
                </select>
            </label>
            <?php if ( ! empty($can_assign)): ?>
            <label class="block">
                <span class="text-sm text-gray-600">Attribuer à (admin)</span>
                <select name="user_id" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                    <option value="">— Non attribué —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo (int) $u['id']; ?>" <?php echo ((int)($event['user_id'] ?? 0) === (int)$u['id'])?'selected':''; ?>>
                            <?php echo html_escape($u['nom']); ?> (<?php echo html_escape($u['role']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
        </div>
    </div>

    <div class="flex gap-3">
        <button class="bg-[#1b1c1c] text-white px-6 py-2.5 hover:bg-[#000000] transition t-label text-[11px]">
            <?php echo $is_edit ? 'Enregistrer' : 'Créer l\'événement'; ?>
        </button>
        <a href="<?php echo site_url('admin/events'); ?>" class="px-6 py-2.5 border border-[#e4e2e2] text-gray-600 hover:bg-[#f5f3f3] transition t-label text-[11px]">Annuler</a>
    </div>
<?php echo form_close(); ?>
