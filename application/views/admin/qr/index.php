<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="font-display text-3xl text-ink">QR code d'accès</h1>
    <a href="<?php echo site_url('admin/dashboard'); ?>" class="text-sm text-[#1b1c1c] hover:underline">&larr; Tableau de bord</a>
</div>

<?php if (empty($events)): ?>
    <p class="text-gray-400">Aucun événement.</p>
<?php else: ?>

<div class="mb-6">
    <select onchange="window.location='<?php echo site_url('admin/qr/index'); ?>/'+this.value"
            class="rounded-lg border border-gray-300 px-3 py-2">
        <?php foreach ($events as $e): ?>
            <option value="<?php echo $e['id']; ?>" <?php echo ($event && $e['id']==$event['id'])?'selected':''; ?>>
                <?php echo html_escape($e['nom']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="bg-white rounded-xl border border-[#e4e2e2] p-8 max-w-md text-center">
    <div id="qr" class="mx-auto inline-block"></div>
    <p class="mt-4 text-sm text-gray-500 break-all"><?php echo html_escape($public_url); ?></p>
    <div class="mt-5 flex justify-center gap-3">
        <a id="dl" download="qr-<?php echo html_escape($event['slug']); ?>.png"
           class="rounded-lg bg-[#1b1c1c] hover:bg-[#000000] text-white text-sm px-4 py-2">Télécharger PNG</a>
        <button onclick="window.print()"
                class="rounded-lg border border-[#e4e2e2] text-gray-600 text-sm px-4 py-2">Imprimer</button>
    </div>
    <p class="mt-4 text-xs text-gray-400">
        Le QR encode l'URL avec <code>?src=qr</code> : les ouvertures via QR sont distinguées dans les statistiques.
    </p>
</div>

<!-- Librairie QR hébergée localement (fonctionne hors-ligne et en prod). -->
<script src="<?php echo base_url('assets/js/qrcode.min.js'); ?>"></script>
<script>
(function () {
    var url = <?php echo json_encode($public_url); ?>;
    var box = document.getElementById('qr');
    var dl  = document.getElementById('dl');
    if (!url || !box || typeof QRCode === 'undefined') {
        if (dl) { dl.textContent = 'QR indisponible'; dl.removeAttribute('href'); }
        return;
    }

    new QRCode(box, { text: url, width: 280, height: 280, correctLevel: QRCode.CorrectLevel.M });

    // Récupère l'image générée (canvas ou <img>) pour le téléchargement PNG.
    function setHref() {
        var canvas = box.querySelector('canvas');
        var img    = box.querySelector('img');
        var data   = canvas ? canvas.toDataURL('image/png') : (img ? img.src : '');
        if (data) dl.href = data;
    }
    setTimeout(setHref, 150);
    var im = box.querySelector('img');
    if (im) im.addEventListener('load', setHref);
})();
</script>

<?php endif; ?>
