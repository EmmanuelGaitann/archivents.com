<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$now = time();
$until_ts = $until ? strtotime($until) : NULL;
$active = ($until_ts && $until_ts > $now);
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="font-display text-3xl text-ink">Rétention des originaux</h1>
    <a href="<?php echo site_url('admin/dashboard'); ?>" class="text-sm text-[#1b1c1c] hover:underline">&larr; Tableau de bord</a>
</div>

<?php if ($msg = $this->session->flashdata('ok')): ?>
    <div class="mb-5 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">
        <?php echo html_escape($msg); ?>
    </div>
<?php endif; ?>

<?php if (empty($events)): ?>
    <p class="text-gray-400">Aucun événement.</p>
<?php else: ?>

<div class="mb-6">
    <select onchange="window.location='<?php echo site_url('admin/retention/index'); ?>/'+this.value"
            class="rounded-lg border border-gray-300 px-3 py-2">
        <?php foreach ($events as $e): ?>
            <option value="<?php echo $e['id']; ?>" <?php echo ($event && $e['id']==$event['id'])?'selected':''; ?>>
                <?php echo html_escape($e['nom']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="grid sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-5">
        <div class="text-3xl font-semibold text-[#1b1c1c]"><?php echo (int) $originals_available; ?></div>
        <div class="text-sm text-gray-500 mt-1">Originaux encore disponibles</div>
    </div>
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-5">
        <div class="text-3xl font-semibold text-[#1b1c1c]"><?php echo (int) $total_photos; ?></div>
        <div class="text-sm text-gray-500 mt-1">Photos au total</div>
    </div>
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-5">
        <div class="text-sm <?php echo $active?'text-green-700':'text-gray-500'; ?> font-medium">
            <?php echo $active ? 'Originaux disponibles jusqu\'au' : 'Rétention expirée'; ?>
        </div>
        <div class="text-lg mt-1"><?php echo $until_ts ? date('d/m/Y H:i', $until_ts) : '—'; ?></div>
    </div>
</div>

<div class="grid sm:grid-cols-2 gap-4">
    <!-- Prolonger -->
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-5">
        <h2 class="font-display text-lg mb-3">Prolonger la rétention</h2>
        <?php echo form_open('admin/retention/extend/'.$event['id'], array('class'=>'flex items-end gap-3')); ?>
            <label class="block">
                <span class="text-sm text-gray-600">Durée</span>
                <select name="hours" class="mt-1 rounded-lg border border-gray-300 px-3 py-2">
                    <option value="24">+ 24 heures</option>
                    <option value="48" selected>+ 48 heures</option>
                    <option value="168">+ 7 jours</option>
                    <option value="720">+ 30 jours</option>
                </select>
            </label>
            <button class="rounded-lg bg-[#1b1c1c] hover:bg-[#000000] text-white px-4 py-2">Prolonger</button>
        <?php echo form_close(); ?>
    </div>

    <!-- Purger -->
    <div class="bg-white rounded-xl border border-red-200 p-5">
        <h2 class="font-display text-lg mb-3 text-red-700">Purger maintenant</h2>
        <p class="text-sm text-gray-500 mb-3">
            Supprime immédiatement tous les originaux JPEG de cet événement.
            Les 3 versions WebP sont conservées. Action irréversible.
        </p>
        <?php echo form_open('admin/retention/purge/'.$event['id'],
            array('onsubmit' => "return confirm('Purger définitivement les originaux de cet événement ?');")); ?>
            <button class="rounded-lg bg-red-600 hover:bg-red-700 text-white px-4 py-2">
                Purger les <?php echo (int) $originals_available; ?> original(aux)
            </button>
        <?php echo form_close(); ?>
    </div>
</div>

<?php endif; ?>
