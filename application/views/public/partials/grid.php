<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Galerie masonry éditoriale — colonnes équilibrées, orientation respectée.
 *
 * Chaque photo garde son ratio naturel (portrait/paysage) ; les images sont
 * réparties dans la colonne la plus courte → rendu organique (vagues, blocs)
 * tout en conservant l'ordre chronologique d'import (tri 'ordre').
 *
 * Variables attendues : $slug, $first_photos, $total_photos, $page_size,
 *                       $album_filter ('all' ou id), $settings, [$sort].
 */
$album_filter = isset($album_filter) ? $album_filter : 'all';
$sort = isset($sort) ? $sort : 'ordre';

// Payload initial (rendu côté serveur -> hydraté par JS dans les colonnes).
$initial = array();
foreach ($first_photos as $p)
{
    $initial[] = array(
        'id'       => (int) $p['id'],
        'thumb'    => av_thumb_url($p),
        'medium'   => av_medium_url($p),
        'download' => site_url('e/'.$slug.'/download/'.$p['id']),
        'w'        => (int) $p['largeur'] ?: 4,
        'h'        => (int) $p['hauteur'] ?: 3,
    );
}
$initial_offset = count($first_photos);

// Libellé de rétention des originaux.
$retention_label = '';
$until = $settings['originals_available_until'] ?? NULL;
if ($until)
{
    $ts = strtotime($until);
    if ($ts && $ts > time())
    {
        $retention_label = 'Téléchargement en JPEG haute qualité jusqu\'au '
            .date('d/m/Y à H:i', $ts).', puis disponible en WebP haute qualité.';
    }
    else
    {
        $retention_label = 'Téléchargement en WebP haute qualité.';
    }
}
?>

<?php if ($retention_label): ?>
<p class="text-center t-label text-[10px] text-muted/70 mb-8"><?php echo html_escape($retention_label); ?></p>
<?php endif; ?>

<?php if ($total_photos === 0): ?>
    <p class="text-center text-muted py-20 font-display italic text-xl">Aucune photo pour le moment. Revenez bientôt&nbsp;!</p>
<?php else: ?>

<div id="grid"
     class="flex gap-4 md:gap-6 items-start"
     data-photos-url="<?php echo site_url('e/'.$slug.'/photos'); ?>"
     data-album="<?php echo html_escape($album_filter); ?>"
     data-sort="<?php echo html_escape($sort); ?>"
     data-offset="<?php echo $initial_offset; ?>"
     data-pagesize="<?php echo (int) $page_size; ?>">
    <!-- Colonnes injectées par JS (masonry équilibré). -->
</div>

<!-- Repli sans JavaScript : grille simple. -->
<noscript>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <?php foreach ($first_photos as $p): ?>
            <img src="<?php echo av_thumb_url($p); ?>" loading="lazy" alt=""
                 class="w-full h-auto rounded-xl">
        <?php endforeach; ?>
    </div>
</noscript>

<!-- Sentinelle de scroll infini -->
<div id="sentinel" class="h-12"></div>
<p id="grid-loading" class="text-center t-label text-[10px] text-muted py-8 hidden">Chargement…</p>

<!-- Lightbox -->
<div id="lightbox" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 md:p-12"
     style="background:var(--c-bg)">
    <button id="lb-close" class="absolute top-6 right-6 text-ink hover:opacity-60 transition" aria-label="Fermer">
        <span class="material-symbols-outlined text-3xl">close</span>
    </button>
    <button id="lb-prev" class="absolute left-3 md:left-8 text-ink/70 hover:text-ink transition" aria-label="Précédente">
        <span class="material-symbols-outlined text-4xl">chevron_left</span>
    </button>
    <button id="lb-next" class="absolute right-3 md:right-8 text-ink/70 hover:text-ink transition" aria-label="Suivante">
        <span class="material-symbols-outlined text-4xl">chevron_right</span>
    </button>

    <div class="max-w-full max-h-full flex items-center justify-center">
        <img id="lb-img" src="" alt="" class="max-w-full max-h-[80vh] object-contain shadow-2xl rounded">
    </div>

    <div class="absolute bottom-10 left-1/2 -translate-x-1/2 flex gap-8">
        <a id="lb-dl" href="" download class="flex items-center gap-2 t-label text-[11px] text-ink border-b border-ink/70 pb-1 hover:opacity-70 transition">
            <span class="material-symbols-outlined text-lg">download</span> Télécharger
        </a>
        <button id="lb-share" class="flex items-center gap-2 t-label text-[11px] text-ink border-b border-ink/70 pb-1 hover:opacity-70 transition">
            <span class="material-symbols-outlined text-lg">share</span> Partager
        </button>
    </div>
</div>

<script>
(function () {
    var grid = document.getElementById('grid');
    if (!grid) return;

    var cfg = {
        url: grid.dataset.photosUrl,
        album: grid.dataset.album,
        sort: grid.dataset.sort,
        offset: parseInt(grid.dataset.offset, 10) || 0,
        pageSize: parseInt(grid.dataset.pagesize, 10) || 40
    };
    var hasMore = true, loading = false;
    var items = <?php echo json_encode($initial, JSON_UNESCAPED_SLASHES); ?>; // ordre chronologique

    /* ---- Masonry : colonnes équilibrées (plus courte d'abord) ---- */
    var cols = [];  // éléments DOM des colonnes
    var colH = [];  // hauteur cumulée estimée (ratio h/w) par colonne

    function colCount() {
        var w = window.innerWidth;
        if (w < 640) return 2;
        if (w < 1024) return 3;
        return 4;
    }

    function buildColumns(n) {
        grid.innerHTML = '';
        cols = []; colH = [];
        for (var i = 0; i < n; i++) {
            var c = document.createElement('div');
            c.className = 'flex-1 min-w-0 flex flex-col gap-4 md:gap-6';
            grid.appendChild(c);
            cols.push(c);
            colH.push(0);
        }
    }

    function shortest() {
        var idx = 0;
        for (var i = 1; i < colH.length; i++) {
            if (colH[i] < colH[idx]) idx = i;
        }
        return idx;
    }

    function makeCell(p, index) {
        var cell = document.createElement('div');
        cell.className = 'ph group relative block w-full overflow-hidden rounded-xl bg-surface cursor-zoom-in zoom';
        cell.dataset.id = p.id;
        cell.dataset.medium = p.medium;
        cell.dataset.download = p.download;
        cell.dataset.idx = index;
        cell.innerHTML =
            '<img src="' + p.thumb + '" srcset="' + p.thumb + ' 300w, ' + p.medium + ' 1600w" ' +
            'sizes="(max-width:640px) 50vw, (max-width:1024px) 33vw, 25vw" ' +
            'width="' + p.w + '" height="' + p.h + '" loading="lazy" decoding="async" alt="" ' +
            'class="grid-img w-full h-auto block">' +
            '<div class="absolute inset-0 bg-black/0 group-hover:bg-black/5 transition-colors duration-500"></div>' +
            '<a href="' + p.download + '" download class="dl absolute bottom-3 right-3 w-10 h-10 rounded-full ' +
            'bg-white/90 backdrop-blur flex items-center justify-center opacity-0 translate-y-2 ' +
            'group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-300" title="Télécharger">' +
            '<span class="material-symbols-outlined text-accent text-xl">download</span></a>';
        return cell;
    }

    // Place une photo (déjà présente dans items) dans la colonne la plus courte.
    function place(p, index) {
        var i = shortest();
        cols[i].appendChild(makeCell(p, index));
        colH[i] += (p.h / p.w) || 1; // hauteur relative (largeur de colonne constante)
    }

    function layoutAll() {
        buildColumns(colCount());
        for (var i = 0; i < items.length; i++) place(items[i], i);
    }

    layoutAll();

    var lastN = colCount(), rzT = null;
    window.addEventListener('resize', function () {
        if (rzT) clearTimeout(rzT);
        rzT = setTimeout(function () {
            var n = colCount();
            if (n !== lastN) { lastN = n; layoutAll(); }
        }, 200);
    });

    /* ---- Scroll infini ---- */
    var sentinel = document.getElementById('sentinel');
    var loadingEl = document.getElementById('grid-loading');

    function fetchMore() {
        if (loading || !hasMore) return;
        loading = true; loadingEl.classList.remove('hidden');

        var u = cfg.url + '?album=' + encodeURIComponent(cfg.album) +
                '&sort=' + encodeURIComponent(cfg.sort) + '&offset=' + cfg.offset;

        fetch(u, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                loading = false; loadingEl.classList.add('hidden');
                if (!d.ok) { hasMore = false; return; }
                d.photos.forEach(function (p) {
                    var index = items.length;
                    items.push(p);
                    place(p, index);
                });
                cfg.offset += d.photos.length;
                hasMore = d.has_more;
                if (!hasMore && io) io.disconnect();
            })
            .catch(function () { loading = false; loadingEl.classList.add('hidden'); });
    }

    var io = null;
    if ('IntersectionObserver' in window) {
        io = new IntersectionObserver(function (entries) {
            if (entries[0].isIntersecting) fetchMore();
        }, { rootMargin: '600px' });
        io.observe(sentinel);
    } else {
        window.addEventListener('scroll', function () {
            if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 600) fetchMore();
        });
    }

    /* ---- Lightbox ---- */
    var lb = document.getElementById('lightbox');
    var lbImg = document.getElementById('lb-img');
    var lbDl = document.getElementById('lb-dl');
    var lbShare = document.getElementById('lb-share');
    var cur = 0;

    function openLb(idx) {
        cur = idx;
        lbImg.src = items[idx].medium;
        lbDl.href = items[idx].download;
        lb.classList.remove('hidden'); lb.classList.add('flex');
        document.body.style.overflow = 'hidden';
        if (window.AVTrack && items[idx].id) AVTrack.hit('photo_view', { photo_id: items[idx].id });
    }
    function closeLb() {
        lb.classList.add('hidden'); lb.classList.remove('flex');
        lbImg.src = ''; document.body.style.overflow = '';
    }
    function step(n) {
        var i = cur + n;
        if (i < 0) i = items.length - 1;
        if (i >= items.length) i = 0;
        openLb(i);
    }

    // Clic sur une vignette : ouvre la lightbox (sauf clic sur le bouton télécharger).
    grid.addEventListener('click', function (e) {
        if (e.target.closest('.dl')) return; // laisser l'ancre de téléchargement agir
        var cell = e.target.closest('.ph');
        if (cell) openLb(parseInt(cell.dataset.idx, 10));
    });

    document.getElementById('lb-close').addEventListener('click', closeLb);
    document.getElementById('lb-prev').addEventListener('click', function () { step(-1); });
    document.getElementById('lb-next').addEventListener('click', function () { step(1); });
    lb.addEventListener('click', function (e) { if (e.target === lb) closeLb(); });
    document.addEventListener('keydown', function (e) {
        if (lb.classList.contains('hidden')) return;
        if (e.key === 'Escape') closeLb();
        else if (e.key === 'ArrowLeft') step(-1);
        else if (e.key === 'ArrowRight') step(1);
    });

    // Partage natif (si disponible), repli sur copie du lien.
    lbShare.addEventListener('click', function () {
        var url = items[cur].download;
        if (navigator.share) {
            navigator.share({ title: document.title, url: url }).catch(function () {});
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function () {
                lbShare.classList.add('opacity-50');
                setTimeout(function () { lbShare.classList.remove('opacity-50'); }, 1200);
            });
        }
    });
})();
</script>

<?php endif; ?>
