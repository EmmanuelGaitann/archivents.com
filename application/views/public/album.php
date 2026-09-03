<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$site_title = $settings['site_title'] ?? $event['nom'];
?>

<header class="pt-32 md:pt-40 pb-4 text-center px-4">
    <a href="<?php echo site_url('e/'.$slug); ?>"
       class="t-label text-[11px] text-muted hover:text-ink transition inline-flex items-center gap-1">
        <span class="material-symbols-outlined text-base">arrow_back</span> Tous les albums
    </a>
    <h1 class="mt-5 font-display italic text-4xl md:text-5xl text-ink"><?php echo html_escape($current_album['nom']); ?></h1>
    <p class="mt-3 t-label text-[11px] text-muted"><?php echo html_escape($site_title); ?></p>
</header>

<main class="max-w-[1440px] mx-auto px-5 md:px-12 pt-12 pb-24">
    <?php $this->load->view('public/partials/videos', array('videos' => $videos ?? array())); ?>
    <?php $this->load->view('public/partials/grid', array(
        'slug'         => $slug,
        'first_photos' => $first_photos,
        'total_photos' => $total_photos,
        'page_size'    => $page_size,
        'album_filter' => $album_filter,
        'sort'         => $sort,
        'settings'     => $settings,
    )); ?>
</main>
