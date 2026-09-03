<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$site_title   = $settings['site_title'] ?? $event['nom'];
$couple       = $settings['footer_couple_info'] ?? array();
$couple_photo = $settings['couple_photo_path'] ?? NULL;
$page_titles  = $settings['page_titles'] ?? array();
$gallery_label = $page_titles['galerie'] ?? 'Galerie des invités';

$mode = $gallery_mode ?? 'folders_first';
if ($mode === 'all_then_filter') { $mode = 'photos_only'; } // compat ancien mode
if ( ! in_array($mode, array('photos_only', 'folders_only', 'folders_first'), TRUE)) {
    $mode = 'folders_first';
}

$show_pills  = ($mode === 'folders_first');
$show_cards  = ($mode === 'folders_only');
$show_masonry = ($mode !== 'folders_only');

$event_total = array_sum($album_counts); // total toutes photos de l'événement

// Réglages de la bannière (header) définis dans l'admin.
$ho = $settings['header_options'] ?? array();
$hdr_opacity    = isset($ho['opacity'])    ? max(0, min(80,  (int) $ho['opacity']))    : 30;
$hdr_brightness = isset($ho['brightness']) ? max(50, min(130, (int) $ho['brightness'])) : 100;
$hdr_pos_x      = isset($ho['pos_x'])      ? max(0, min(100, (int) $ho['pos_x']))      : 50;
$hdr_pos_y      = isset($ho['pos_y'])      ? max(0, min(100, (int) $ho['pos_y']))      : 50;
?>

<!-- HERO / TITRE ÉDITORIAL -->
<?php if ($couple_photo): ?>
<header class="relative pt-16 md:pt-20">
    <div class="relative h-[58vh] min-h-[340px] overflow-hidden">
        <!-- Couche image (la luminosité ne s'applique qu'à l'image) -->
        <div class="absolute inset-0 bg-cover"
             style="background-image:url('<?php echo base_url($couple_photo); ?>');
                    background-position:<?php echo $hdr_pos_x; ?>% <?php echo $hdr_pos_y; ?>%;
                    filter:brightness(<?php echo $hdr_brightness; ?>%);"></div>
        <!-- Voile -->
        <div class="absolute inset-0 bg-black" style="opacity:<?php echo $hdr_opacity / 100; ?>;"></div>
        <!-- Titre -->
        <div class="relative h-full flex flex-col items-center justify-center text-center text-white px-4">
            <p class="t-label text-[11px] mb-4 text-white/80"><?php echo html_escape($gallery_label); ?></p>
            <h1 class="font-display italic text-5xl md:text-7xl drop-shadow-sm"><?php echo html_escape($site_title); ?></h1>
            <?php if ( ! empty($couple['date'])): ?>
                <p class="mt-4 t-label text-[11px] text-white/90"><?php echo html_escape($couple['date']); ?></p>
            <?php endif; ?>
        </div>
    </div>
</header>
<?php else: ?>
<header class="pt-32 md:pt-40 pb-4 text-center px-4">
    <p class="t-label text-[11px] text-muted mb-5"><?php echo html_escape($gallery_label); ?></p>
    <h1 class="font-display italic text-5xl md:text-6xl text-ink"><?php echo html_escape($site_title); ?></h1>
    <?php if ( ! empty($couple['date'])): ?>
        <p class="mt-4 t-label text-[11px] text-muted"><?php echo html_escape($couple['date']); ?></p>
    <?php endif; ?>
</header>
<?php endif; ?>

<main class="max-w-[1440px] mx-auto px-5 md:px-12 pt-12 pb-24">

<?php if ($show_pills && ! empty($albums)): ?>
    <!-- Navigation par dossiers (pastilles) -->
    <nav class="flex items-center gap-2.5 overflow-x-auto pb-2 mb-12 -mx-1 px-1
                [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <?php $is_all = ($album_filter === 'all'); ?>
        <a href="<?php echo site_url('e/'.$slug); ?>"
           class="whitespace-nowrap px-6 py-2.5 rounded-full t-label text-[11px] transition
                  <?php echo $is_all
                      ? 'bg-accent text-white shadow-sm'
                      : 'bg-surface border border-line text-muted hover:text-ink'; ?>">
            Tous (<?php echo (int) $event_total; ?>)
        </a>
        <?php foreach ($albums as $a):
            $active = ($album_filter === (string) $a['id']);
            $cnt = (int) ($album_counts[$a['id']] ?? 0);
        ?>
            <a href="<?php echo site_url('e/'.$slug).'?album='.$a['id']; ?>"
               class="whitespace-nowrap px-6 py-2.5 rounded-full t-label text-[11px] transition
                      <?php echo $active
                          ? 'bg-accent text-white shadow-sm'
                          : 'bg-surface border border-line text-muted hover:text-ink'; ?>">
                <?php echo html_escape($a['nom']); ?> (<?php echo $cnt; ?>)
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php if ($show_cards): ?>
    <!-- Dossiers seuls : cartes élégantes -->
    <div class="flex items-end justify-between mb-10">
        <h2 class="font-display italic text-3xl text-ink">Albums</h2>
        <a href="<?php echo site_url('e/'.$slug.'/album/0'); ?>"
           class="t-label text-[11px] text-accent hover:opacity-70 transition">Tout voir (<?php echo (int) $event_total; ?>)</a>
    </div>
    <?php if (empty($albums)): ?>
        <p class="text-center text-muted py-16 font-display italic text-xl">Aucun album pour le moment.</p>
    <?php else: ?>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-5 md:gap-6">
        <?php foreach ($albums as $a):
            $cover = $album_covers[$a['id']] ?? NULL;
            $cnt   = (int) ($album_counts[$a['id']] ?? 0);
        ?>
            <a href="<?php echo site_url('e/'.$slug.'/album/'.$a['id']); ?>"
               class="group block overflow-hidden rounded-xl bg-surface zoom">
                <div class="aspect-[4/5] overflow-hidden bg-surface">
                    <?php if ($cover): ?>
                        <img src="<?php echo av_thumb_url($cover); ?>" loading="lazy" decoding="async" alt=""
                             class="w-full h-full object-cover">
                    <?php endif; ?>
                </div>
                <div class="p-4 text-center">
                    <div class="font-display italic text-xl text-ink"><?php echo html_escape($a['nom']); ?></div>
                    <div class="mt-1 t-label text-[10px] text-muted"><?php echo $cnt; ?> photo<?php echo $cnt>1?'s':''; ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php $this->load->view('public/partials/videos', array('videos' => $videos ?? array())); ?>

<?php if ($show_masonry): ?>
    <?php $this->load->view('public/partials/grid', array(
        'slug'         => $slug,
        'first_photos' => $first_photos,
        'total_photos' => $total_photos,
        'page_size'    => $page_size,
        'album_filter' => $album_filter,
        'sort'         => $sort,
        'settings'     => $settings,
    )); ?>
<?php endif; ?>

</main>
