<?php
defined('BASEPATH') OR exit('No direct script access allowed');
function av_bars($hours) {
    $max = max(1, max($hours));
    $out = '';
    foreach ($hours as $h => $n) {
        $pct = round($n / $max * 100);
        $out .= '<div class="flex-1 flex flex-col items-center justify-end" title="'.$h.'h : '.$n.'">'
              . '<div class="w-full bg-[#6b6b6b] rounded-t" style="height:'.max(2,$pct).'%"></div>'
              . '<span class="text-[9px] text-gray-400 mt-0.5">'.$h.'</span></div>';
    }
    return $out;
}
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="font-display text-3xl text-ink">Statistiques</h1>
    <a href="<?php echo site_url('admin/dashboard'); ?>" class="text-sm text-[#1b1c1c] hover:underline">&larr; Tableau de bord</a>
</div>

<?php if (empty($events)): ?>
    <p class="text-gray-400">Aucun événement.</p>
<?php else: ?>

<div class="mb-6">
    <select onchange="window.location='<?php echo site_url('admin/stats'); ?>/'+this.value"
            class="rounded-lg border border-gray-300 px-3 py-2">
        <?php foreach ($events as $e): ?>
            <option value="<?php echo $e['id']; ?>" <?php echo ($event && $e['id']==$event['id'])?'selected':''; ?>>
                <?php echo html_escape($e['nom']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<!-- KPI principaux -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-5">
        <div class="text-3xl font-semibold text-[#1b1c1c]"><?php echo (int) $total_opens; ?></div>
        <div class="text-sm text-gray-500 mt-1">Connexions totales</div>
    </div>
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-5">
        <div class="text-3xl font-semibold text-[#1b1c1c]"><?php echo (int) $unique_devices; ?></div>
        <div class="text-sm text-gray-500 mt-1">Appareils uniques</div>
        <div class="text-[11px] text-gray-400 mt-1">COUNT DISTINCT visitor_uid</div>
    </div>
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-5">
        <div class="text-3xl font-semibold text-[#1b1c1c]"><?php echo (int) $types['photo_view']; ?></div>
        <div class="text-sm text-gray-500 mt-1">Vues de photos</div>
    </div>
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-5">
        <div class="text-3xl font-semibold text-[#1b1c1c]"><?php echo (int) $types['download']; ?></div>
        <div class="text-sm text-gray-500 mt-1">Téléchargements</div>
    </div>
</div>

<!-- Source QR vs lien -->
<div class="bg-white rounded-xl border border-[#e4e2e2] p-5 mb-6">
    <h2 class="font-display text-lg mb-3">Provenance des ouvertures</h2>
    <div class="flex gap-6">
        <div><span class="font-display text-3xl text-ink text-[#1b1c1c]"><?php echo (int) $sources['qr']; ?></span>
             <span class="text-sm text-gray-500 ml-1">via QR code</span></div>
        <div><span class="font-display text-3xl text-ink text-[#1b1c1c]"><?php echo (int) $sources['link']; ?></span>
             <span class="text-sm text-gray-500 ml-1">via lien</span></div>
    </div>
</div>

<!-- Répartitions horaires -->
<div class="grid lg:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-5">
        <h2 class="font-display text-lg mb-3">Connexions par heure</h2>
        <div class="flex items-end gap-0.5 h-32"><?php echo av_bars($hourly_opens); ?></div>
    </div>
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-5">
        <h2 class="font-display text-lg mb-3">Téléchargements par heure</h2>
        <div class="flex items-end gap-0.5 h-32"><?php echo av_bars($hourly_downloads); ?></div>
    </div>
</div>

<!-- Tops albums -->
<div class="grid lg:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-5">
        <h2 class="font-display text-lg mb-3">Albums les plus visités</h2>
        <?php echo av_album_list($top_albums_view); ?>
    </div>
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-5">
        <h2 class="font-display text-lg mb-3">Albums les plus téléchargés</h2>
        <?php echo av_album_list($top_albums_dl); ?>
    </div>
</div>

<!-- Tops photos -->
<div class="grid lg:grid-cols-2 gap-4">
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-5">
        <h2 class="font-display text-lg mb-3">Photos les plus vues</h2>
        <?php echo av_photo_list($top_photos_view); ?>
    </div>
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-5">
        <h2 class="font-display text-lg mb-3">Photos les plus téléchargées</h2>
        <?php echo av_photo_list($top_photos_dl); ?>
    </div>
</div>

<p class="text-[11px] text-gray-400 mt-6">
    L'adresse IP n'est utilisée que pour une géolocalisation grossière, jamais comme compteur d'unicité.
    L'unicité repose sur <code>visitor_uid</code> (cookie + localStorage + empreinte légère).
    L'adresse MAC n'est pas récupérable côté serveur (voir README).
</p>

<?php endif; ?>

<?php
function av_album_list($rows) {
    if (empty($rows)) return '<p class="text-sm text-gray-400">Aucune donnée.</p>';
    $out = '<ul class="space-y-2">';
    foreach ($rows as $r) {
        $out .= '<li class="flex justify-between text-sm"><span>'.html_escape($r['nom']).'</span>'
              . '<span class="font-medium text-[#1b1c1c]">'.(int)$r['n'].'</span></li>';
    }
    return $out.'</ul>';
}
function av_photo_list($rows) {
    if (empty($rows)) return '<p class="text-sm text-gray-400">Aucune donnée.</p>';
    $out = '<div class="flex flex-wrap gap-3">';
    foreach ($rows as $r) {
        $thumb = $r['thumb'] ? '<img src="'.$r['thumb'].'" class="w-16 h-16 object-cover rounded">' : '<div class="w-16 h-16 bg-gray-100 rounded"></div>';
        $out .= '<div class="text-center">'.$thumb.'<div class="text-xs text-[#1b1c1c] mt-1">'.(int)$r['n'].'</div></div>';
    }
    return $out.'</div>';
}
?>
