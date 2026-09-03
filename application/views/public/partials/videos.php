<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Partial : vidéos de l'événement (MP4 servi par le CDN en range-requests).
 * Attend : $videos (lignes 'ready'). N'affiche rien s'il n'y en a pas.
 */
if (empty($videos)) return;
?>
<section class="mb-14">
    <div class="flex items-end justify-between mb-6">
        <h2 class="font-display italic text-3xl text-ink">Vidéos</h2>
        <span class="t-label text-[10px] text-muted"><?php echo count($videos); ?> vidéo<?php echo count($videos) > 1 ? 's' : ''; ?></span>
    </div>
    <div class="grid grid-cols-1 <?php echo count($videos) > 1 ? 'md:grid-cols-2' : ''; ?> gap-5 md:gap-6">
        <?php foreach ($videos as $v): ?>
        <figure class="overflow-hidden rounded-xl bg-black">
            <video controls preload="metadata" playsinline
                   class="w-full h-auto block"
                   <?php if ( ! empty($v['largeur']) && ! empty($v['hauteur'])): ?>
                   width="<?php echo (int) $v['largeur']; ?>" height="<?php echo (int) $v['hauteur']; ?>"
                   <?php endif; ?>
                   src="<?php echo av_video_url($v); ?>"></video>
            <?php if ( ! empty($v['titre'])): ?>
            <figcaption class="p-3 text-center t-label text-[10px] text-muted bg-surface">
                <?php echo html_escape($v['titre']); ?>
            </figcaption>
            <?php endif; ?>
        </figure>
        <?php endforeach; ?>
    </div>
</section>
