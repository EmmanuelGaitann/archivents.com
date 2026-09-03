<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$pages = (int) ceil(($total ?: 0) / $per_page);
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="font-display text-3xl text-ink">Gérer les photos</h1>
    <a href="<?php echo site_url('admin/dashboard'); ?>" class="text-sm text-[#1b1c1c] hover:underline">&larr; Tableau de bord</a>
</div>

<?php if ($msg = $this->session->flashdata('ok')): ?>
    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3"><?php echo html_escape($msg); ?></div>
<?php endif; ?>

<?php if (empty($events)): ?>
    <p class="text-gray-400">Aucun événement.</p>
<?php else: ?>

<!-- Filtres : événement + album -->
<div class="mb-6 flex flex-wrap items-center gap-3">
    <select onchange="window.location='<?php echo site_url('admin/photos/index'); ?>/'+this.value"
            class="rounded-lg border border-gray-300 px-3 py-2">
        <?php foreach ($events as $e): ?>
            <option value="<?php echo $e['id']; ?>" <?php echo ($event && $e['id']==$event['id'])?'selected':''; ?>>
                <?php echo html_escape($e['nom']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select onchange="window.location='<?php echo site_url('admin/photos/index/'.($event?$event['id']:0)); ?>?album='+this.value"
            class="rounded-lg border border-gray-300 px-3 py-2">
        <option value="all" <?php echo $album_filter==='all'?'selected':''; ?>>Tous les dossiers</option>
        <?php foreach ($albums as $a): ?>
            <option value="<?php echo $a['id']; ?>" <?php echo $album_filter===(string)$a['id']?'selected':''; ?>>
                <?php echo html_escape($a['nom']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <span class="text-sm text-gray-500"><?php echo (int) $total; ?> photo<?php echo $total>1?'s':''; ?></span>
</div>

<?php if (empty($photos)): ?>
    <p class="text-gray-400">Aucune photo ici.</p>
<?php else: ?>

<!-- Barre d'actions -->
<div class="flex flex-wrap items-center gap-3 mb-4">
    <label class="flex items-center gap-2 text-sm text-gray-600">
        <input type="checkbox" id="check-all" class="rounded border-gray-300"> Tout sélectionner
    </label>
    <button id="btn-bulk" type="button" disabled
            class="text-sm rounded-lg border border-red-200 text-red-600 px-3 py-2 hover:bg-red-50 disabled:opacity-40 disabled:cursor-not-allowed">
        Supprimer la sélection (<span id="sel-count">0</span>)
    </button>
</div>

<?php echo form_open('admin/photos/bulk_delete', array('id' => 'bulk-form')); ?>
<input type="hidden" name="event_id" value="<?php echo (int) $event['id']; ?>">

<div id="pgrid" class="grid grid-cols-3 sm:grid-cols-5 lg:grid-cols-6 gap-2">
    <?php foreach ($photos as $p): ?>
    <div class=" pcell relative group rounded-lg overflow-hidden bg-[#e9e8e7] aspect-square"
         data-id="<?php echo (int) $p['id']; ?>">
        <img src="<?php echo base_url($p['path_thumb_webp']); ?>" loading="lazy" decoding="async" alt=""
             class="w-full h-full object-cover">
        <label class="absolute top-1.5 left-1.5 bg-white/85 rounded p-1 cursor-pointer">
            <input type="checkbox" name="ids[]" value="<?php echo (int) $p['id']; ?>" class="sel rounded border-gray-300">
        </label>
        <button type="button" data-del="<?php echo (int) $p['id']; ?>"
                class="absolute top-1.5 right-1.5 bg-white/85 hover:bg-white text-red-600 rounded-full w-7 h-7 flex items-center justify-center opacity-0 group-hover:opacity-100 transition"
                title="Supprimer cette photo">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <?php endforeach; ?>
</div>
<?php echo form_close(); ?>

<?php if ($pages > 1): ?>
<div class="flex flex-wrap gap-2 mt-6">
    <?php $q = ($album_filter==='all') ? '' : ('&album='.$album_filter); ?>
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="<?php echo site_url('admin/photos/index/'.$event['id']).'?p='.$i.$q; ?>"
           class="px-3 py-1.5 rounded-lg text-sm <?php echo $i===$page?'bg-[#1b1c1c] text-white':'bg-white border border-[#e4e2e2] text-gray-600'; ?>">
            <?php echo $i; ?>
        </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<script>
(function () {
    var grid = document.getElementById('pgrid');
    var checkAll = document.getElementById('check-all');
    var btnBulk = document.getElementById('btn-bulk');
    var selCount = document.getElementById('sel-count');
    var DEL_URL = '<?php echo site_url('admin/photos/delete'); ?>';

    function sels() { return Array.prototype.slice.call(grid.querySelectorAll('.sel')); }
    function updateCount() {
        var n = sels().filter(function (c) { return c.checked; }).length;
        selCount.textContent = n;
        btnBulk.disabled = (n === 0);
    }

    grid.addEventListener('change', function (e) {
        if (e.target.classList.contains('sel')) updateCount();
    });

    checkAll.addEventListener('change', function () {
        sels().forEach(function (c) { c.checked = checkAll.checked; });
        updateCount();
    });

    // Suppression unitaire (AJAX).
    grid.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-del]');
        if (!btn) return;
        if (!confirm('Supprimer définitivement cette photo ?')) return;
        var id = btn.getAttribute('data-del');
        var dfd = new FormData();
        if (window.CSRF) dfd.append(window.CSRF.name, window.CSRF.hash);
        fetch(DEL_URL + '/' + id, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: dfd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) {
                    var cell = grid.querySelector('.pcell[data-id="' + id + '"]');
                    if (cell) cell.remove();
                    updateCount();
                } else {
                    alert(d.error || 'Échec de la suppression.');
                }
            })
            .catch(function () { alert('Erreur réseau.'); });
    });

    // Suppression multiple (form POST).
    btnBulk.addEventListener('click', function () {
        var n = sels().filter(function (c) { return c.checked; }).length;
        if (n === 0) return;
        if (confirm('Supprimer définitivement ' + n + ' photo(s) ?')) {
            document.getElementById('bulk-form').submit();
        }
    });
})();
</script>

<?php endif; ?>
<?php endif; ?>
