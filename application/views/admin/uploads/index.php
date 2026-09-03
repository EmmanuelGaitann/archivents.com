<?php defined('BASEPATH') OR exit('No direct script access allowed');

if ( ! function_exists('av_vol')) {
    function av_vol($bytes) {
        $bytes = (float) $bytes;
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2).' Go';
        return round($bytes / 1048576).' Mo';
    }
}
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="font-display text-3xl text-ink">Importer des médias</h1>
    <a href="<?php echo site_url('admin/dashboard'); ?>" class="text-sm text-[#1b1c1c] hover:underline">&larr; Tableau de bord</a>
</div>

<?php if ($storage_used !== NULL && $storage_quota_mo !== NULL):
    $quota_bytes = $storage_quota_mo * 1048576;
    $pct = $quota_bytes > 0 ? min(100, round($storage_used / $quota_bytes * 100)) : 100;
?>
<div class="bg-white rounded-xl border border-[#e4e2e2] p-4 mb-6">
    <div class="flex items-center justify-between text-sm">
        <span class="text-gray-600">Stockage de votre forfait</span>
        <span class="<?php echo $pct >= 90 ? 'text-red-600 font-semibold' : 'text-gray-600'; ?>">
            <?php echo av_vol($storage_used); ?> / <?php echo av_vol($quota_bytes); ?>
        </span>
    </div>
    <div class="h-2 bg-gray-100 rounded mt-2 overflow-hidden">
        <div class="h-full <?php echo $pct >= 90 ? 'bg-red-400' : 'bg-[#1b1c1c]'; ?>" style="width:<?php echo $pct; ?>%"></div>
    </div>
    <?php if ($pct >= 90): ?>
    <p class="text-xs text-red-600 mt-2">Votre espace est presque plein : supprimez des médias ou passez à un forfait supérieur.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (empty($events)): ?>
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-4 py-3">
        Aucun événement. Créez-en un d'abord.
    </div>
<?php else: ?>

<!-- Sélecteurs -->
<div class="bg-white rounded-xl border border-[#e4e2e2] p-5 mb-6 grid sm:grid-cols-2 gap-4">
    <label class="block">
        <span class="text-sm text-gray-600">Événement</span>
        <select id="sel-event"
                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"
                onchange="window.location='<?php echo site_url('admin/uploads'); ?>?event='+this.value">
            <?php foreach ($events as $e): ?>
                <option value="<?php echo $e['id']; ?>" <?php echo ($event && $e['id']==$event['id'])?'selected':''; ?>>
                    <?php echo html_escape($e['nom']); ?> (<?php echo html_escape($e['slug']); ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label class="block">
        <span class="text-sm text-gray-600">Dossier / album</span>
        <select id="sel-album" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
            <option value="">— Sans dossier —</option>
            <?php foreach ($albums as $a): ?>
                <option value="<?php echo $a['id']; ?>"><?php echo html_escape($a['nom']); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
</div>

<?php if (empty($r2_active)): ?>
<!-- File d'attente (temps réel) — mode local uniquement -->
<div id="queue" class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6 text-center">
    <?php
    $cells = array(
        'pending'    => array('En attente', 'bg-amber-50 text-amber-700'),
        'processing' => array('En cours',   'bg-blue-50 text-blue-700'),
        'done'       => array('Traitées',   'bg-green-50 text-green-700'),
        'error'      => array('Erreurs',    'bg-red-50 text-red-700'),
        'photos'     => array('Photos',     'bg-[#efeded] text-[#1b1c1c]'),
    );
    foreach ($cells as $k => $c):
        $val = ($k === 'photos') ? 0 : (int) ($counts[$k] ?? 0);
    ?>
        <div class="rounded-lg p-3 <?php echo $c[1]; ?>">
            <div class="font-display text-3xl text-ink" data-stat="<?php echo $k; ?>"><?php echo $val; ?></div>
            <div class="text-xs"><?php echo $c[0]; ?></div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Dropzone -->
<div id="dropzone"
     class="rounded-2xl border-2 border-dashed border-[#d8c3ad] bg-white p-10 text-center cursor-pointer transition hover:border-[#6b6b6b]">
    <p class="text-lg text-gray-700">Glissez-déposez vos <?php echo ! empty($video_allowed) ? 'photos et vidéos' : 'photos'; ?> ici</p>
    <p class="text-sm text-gray-400 mt-1">
        ou cliquez pour sélectionner — JPEG, PNG<?php echo empty($r2_active) ? ' (HEIC si serveur compatible)' : ''; ?><?php echo ! empty($video_allowed) ? ', MP4 (H.264)' : ''; ?>
    </p>
    <input id="file-input" type="file" accept="image/*<?php echo ! empty($video_allowed) ? ',video/mp4' : ''; ?>" multiple class="hidden">
</div>
<?php if ( ! empty($r2_active)): ?>
<p class="text-xs text-gray-400 mt-2">
    <span class="material-symbols-outlined align-middle text-sm">bolt</span>
    Envoi direct sécurisé vers le stockage cloud — les vignettes sont générées automatiquement.
</p>
<?php else: ?>
<div class="mt-3 flex flex-wrap items-center gap-3">
    <button id="btn-process" type="button"
            class="rounded-lg border border-[#e4e2e2] text-gray-600 hover:bg-[#f5f3f3] text-sm px-4 py-2">
        Relancer le traitement
    </button>
    <span id="process-msg" class="text-xs text-gray-500"></span>
</div>
<p class="text-xs text-gray-400 mt-2">
    Le traitement démarre <strong>automatiquement</strong> dès qu'une photo est déposée :
    chaque image est convertie une par une et s'affiche ci-dessous dès qu'elle est prête.
    Le bouton ne sert qu'à relancer manuellement si besoin.
</p>
<?php endif; ?>

<!-- Aperçu des photos prêtes (affichage live) -->
<div id="ready-grid" class="mt-6 grid grid-cols-3 sm:grid-cols-6 gap-2"></div>

<!-- Liste des transferts -->
<ul id="file-list" class="mt-6 space-y-2"></ul>

<script>
(function () {
    var STORE_URL  = '<?php echo site_url('admin/uploads/store'); ?>';
    var STATUS_URL = '<?php echo site_url('admin/uploads/status'); ?>/<?php echo $event ? (int)$event['id'] : 0; ?>';
    var EVENT_ID   = '<?php echo $event ? (int)$event['id'] : 0; ?>';
    var SIGN_URL   = '<?php echo site_url('admin/uploads/sign'); ?>';
    var CONFIRM_URL= '<?php echo site_url('admin/uploads/confirm'); ?>';
    var R2_ACTIVE  = <?php echo ! empty($r2_active) ? 'true' : 'false'; ?>;
    var VIDEO_OK   = <?php echo ! empty($video_allowed) ? 'true' : 'false'; ?>;
    var V_SIGN     = '<?php echo site_url('admin/uploads/sign_video'); ?>';
    var V_PART     = '<?php echo site_url('admin/uploads/video_part'); ?>';
    var V_COMPLETE = '<?php echo site_url('admin/uploads/video_complete'); ?>';
    var V_CONFIRM  = '<?php echo site_url('admin/uploads/video_confirm'); ?>';
    var V_ABORT    = '<?php echo site_url('admin/uploads/video_abort'); ?>';

    var dz    = document.getElementById('dropzone');
    var input = document.getElementById('file-input');
    var list  = document.getElementById('file-list');
    var ready = document.getElementById('ready-grid');

    // Registre job_id -> éléments de la ligne, pour révéler la vignette
    // dès que le worker a produit le WebP de cette photo.
    var jobsById = {};

    dz.addEventListener('click', function () { input.click(); });
    ['dragenter','dragover'].forEach(function (ev) {
        dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('border-[#6b6b6b]','bg-[#f5f3f3]'); });
    });
    ['dragleave','drop'].forEach(function (ev) {
        dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove('border-[#6b6b6b]','bg-[#f5f3f3]'); });
    });
    dz.addEventListener('drop', function (e) { handleFiles(e.dataTransfer.files); });
    input.addEventListener('change', function () { handleFiles(input.files); input.value=''; });

    function handleFiles(files) {
        for (var i = 0; i < files.length; i++) {
            var f = files[i];
            if ((f.type || '').indexOf('video/') === 0) {
                if (VIDEO_OK) { uploadVideoR2(f); }
                else { flashRejected(f.name, "L'ajout de vidéos n'est pas inclus dans votre forfait."); }
                continue;
            }
            R2_ACTIVE ? uploadOneR2(f) : uploadOne(f);
        }
    }

    function flashRejected(name, msg) {
        var li = document.createElement('li');
        li.className = 'bg-white border border-red-200 rounded-lg px-4 py-2 text-sm text-red-600';
        li.textContent = name + ' — ' + msg;
        list.prepend(li);
    }

    /* ---- Mode R2 : upload DIRECT navigateur -> R2 (sign -> PUT -> confirm) ---- */
    function showReady(src) {
        var cell = document.createElement('div');
        cell.className = 'aspect-square overflow-hidden rounded-lg bg-[#e9e8e7]';
        cell.innerHTML = '<img src="' + src + '" loading="lazy" decoding="async" alt="" class="w-full h-full object-cover">';
        ready.prepend(cell);
    }
    function failRow(st, bar, msg) {
        st.textContent = msg; st.className = 'status text-red-600';
        bar.classList.remove('bg-[#6b6b6b]'); bar.classList.add('bg-red-400');
    }
    function uploadOneR2(file) {
        var li = document.createElement('li');
        li.className = 'bg-white border border-[#e4e2e2] rounded-lg px-4 py-2';
        li.innerHTML =
            '<div class="flex justify-between text-sm"><span class="truncate pr-2">' +
            escapeHtml(file.name) + '</span><span class="status text-gray-400">0%</span></div>' +
            '<div class="h-1.5 bg-gray-100 rounded mt-2 overflow-hidden"><div class="bar h-full bg-[#6b6b6b]" style="width:0"></div></div>';
        list.prepend(li);
        var bar = li.querySelector('.bar'), st = li.querySelector('.status');

        function proceed(w, h) {
            var fd = new FormData();
            fd.append('event_id', EVENT_ID);
            fd.append('album_id', document.getElementById('sel-album').value);
            fd.append('filename', file.name);
            fd.append('content_type', file.type || 'image/jpeg');
            fd.append('size', file.size);
            if (window.CSRF) fd.append(window.CSRF.name, window.CSRF.hash);

            fetch(SIGN_URL, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (s) {
                    if (!s.ok) { failRow(st, bar, s.error || 'Erreur'); return; }
                    var xhr = new XMLHttpRequest();
                    xhr.open('PUT', s.upload_url, true);
                    xhr.setRequestHeader('Content-Type', file.type || 'image/jpeg');
                    xhr.upload.onprogress = function (e) {
                        if (e.lengthComputable) { var p = Math.round(e.loaded / e.total * 100); bar.style.width = p + '%'; st.textContent = p + '%'; }
                    };
                    xhr.onload = function () {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            st.textContent = 'Confirmation…'; st.className = 'status text-amber-600';
                            var cf = new FormData();
                            cf.append('photo_id', s.photo_id); cf.append('key', s.key);
                            cf.append('w', w); cf.append('h', h);
                            if (window.CSRF) cf.append(window.CSRF.name, window.CSRF.hash);
                            postRetry(CONFIRM_URL, cf, 3)
                                .then(function (c) {
                                    if (c.ok) {
                                        showReady(c.thumb);
                                        st.textContent = 'Affichée'; st.className = 'status text-green-600';
                                        setTimeout(function () { if (li.parentNode) li.remove(); }, 1200);
                                    } else { failRow(st, bar, c.error || 'Confirmation échouée'); }
                                })
                                .catch(function () { failRow(st, bar, 'Réseau (confirm)'); });
                        } else { failRow(st, bar, 'Upload R2 (' + xhr.status + ')'); }
                    };
                    xhr.onerror = function () { failRow(st, bar, 'Échec réseau R2'); };
                    xhr.send(file);
                })
                .catch(function () { failRow(st, bar, 'Réseau (sign)'); });
        }

        // Dimensions naturelles (masonry) via le navigateur, sans toucher le serveur.
        var url = URL.createObjectURL(file), img = new Image();
        img.onload  = function () { var w = img.naturalWidth, h = img.naturalHeight; URL.revokeObjectURL(url); proceed(w, h); };
        img.onerror = function () { URL.revokeObjectURL(url); proceed(0, 0); };
        img.src = url;
    }

    /* ---- VIDÉO : upload direct R2 (PUT simple, ou multipart par tranches) ---- */
    // POST avec re-tentatives : rejoue si la requête échoue (réseau) ou si le
    // serveur signale une panne passagère vers R2 ({retry:true}, HTTP 503).
    function postRetry(url, fd, tries) {
        return fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (c) {
                if (c && !c.ok && c.retry && tries > 1) {
                    return new Promise(function (res) { setTimeout(res, 1500); })
                        .then(function () { return postRetry(url, fd, tries - 1); });
                }
                return c;
            })
            .catch(function (e) {
                if (tries > 1) {
                    return new Promise(function (res) { setTimeout(res, 1500); })
                        .then(function () { return postRetry(url, fd, tries - 1); });
                }
                throw e;
            });
    }
    function postJSON(url, fields, tries) {
        var fd = new FormData();
        Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
        if (window.CSRF) fd.append(window.CSRF.name, window.CSRF.hash);
        return postRetry(url, fd, tries || 1);
    }

    // PUT d'un blob vers une URL présignée ; résout avec l'ETag de la réponse.
    function putBlob(url, blob, contentType, onProgress) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('PUT', url, true);
            if (contentType) xhr.setRequestHeader('Content-Type', contentType);
            xhr.upload.onprogress = onProgress || null;
            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(xhr.getResponseHeader('ETag') || '');
                } else { reject(new Error('HTTP ' + xhr.status)); }
            };
            xhr.onerror = function () { reject(new Error('réseau')); };
            xhr.send(blob);
        });
    }

    function uploadVideoR2(file) {
        var li = document.createElement('li');
        li.className = 'bg-white border border-[#e4e2e2] rounded-lg px-4 py-2';
        li.innerHTML =
            '<div class="flex justify-between text-sm"><span class="truncate pr-2">' +
            '<span class="material-symbols-outlined align-middle text-base mr-1">movie</span>' +
            escapeHtml(file.name) + '</span><span class="status text-gray-400">0%</span></div>' +
            '<div class="h-1.5 bg-gray-100 rounded mt-2 overflow-hidden"><div class="bar h-full bg-[#6b6b6b]" style="width:0"></div></div>';
        list.prepend(li);
        var bar = li.querySelector('.bar'), st = li.querySelector('.status');
        var ct = file.type || 'video/mp4';

        function setPct(done) {
            var p = Math.min(100, Math.round(done / file.size * 100));
            bar.style.width = p + '%'; st.textContent = p + '%';
        }
        function videoDone() {
            st.textContent = 'Vidéo en ligne'; st.className = 'status text-green-600';
            bar.style.width = '100%';
            setTimeout(function () { if (li.parentNode) li.remove(); }, 2500);
        }
        function videoFail(videoId, msg) {
            failRow(st, bar, msg);
            if (videoId) postJSON(V_ABORT, { video_id: videoId }).catch(function () {});
        }

        // Durée + dimensions via le navigateur (métadonnées, sans upload).
        var meta = { duration: 0, w: 0, h: 0 };
        var url = URL.createObjectURL(file), vid = document.createElement('video');
        vid.preload = 'metadata';
        vid.onloadedmetadata = function () {
            meta.duration = Math.round(vid.duration || 0);
            meta.w = vid.videoWidth || 0; meta.h = vid.videoHeight || 0;
            URL.revokeObjectURL(url); start();
        };
        vid.onerror = function () { URL.revokeObjectURL(url); start(); };
        vid.src = url;

        function start() {
            postJSON(V_SIGN, {
                event_id: EVENT_ID,
                album_id: document.getElementById('sel-album').value,
                filename: file.name, content_type: ct, size: file.size,
                duration: meta.duration, w: meta.w, h: meta.h
            }).then(function (s) {
                if (!s.ok) { failRow(st, bar, s.error || 'Erreur'); return; }
                if (s.mode === 'single') {
                    putBlob(s.upload_url, file, ct, function (e) { if (e.lengthComputable) setPct(e.loaded); })
                        .then(function () {
                            st.textContent = 'Confirmation…'; st.className = 'status text-amber-600';
                            return postJSON(V_CONFIRM, { video_id: s.video_id }, 3);
                        })
                        .then(function (c) { c && c.ok ? videoDone() : videoFail(s.video_id, (c && c.error) || 'Confirmation échouée'); })
                        .catch(function () { videoFail(s.video_id, 'Échec réseau R2'); });
                    return;
                }
                uploadParts(s, 0);
            }).catch(function () { failRow(st, bar, 'Réseau (sign)'); });
        }

        // Multipart : tranches séquentielles, 1 retry par part, abort si échec.
        function uploadParts(s, doneBytes) {
            var partSize = s.part_size, parts = [], total = Math.ceil(file.size / partSize);

            function sendPart(n, attempt) {
                var from = (n - 1) * partSize;
                var blob = file.slice(from, Math.min(from + partSize, file.size));
                postJSON(V_PART, { video_id: s.video_id, part_number: n })
                    .then(function (p) {
                        if (!p.ok) throw new Error(p.error || 'sign part');
                        return putBlob(p.url, blob, null, function (e) {
                            if (e.lengthComputable) setPct(from + e.loaded);
                        });
                    })
                    .then(function (etag) {
                        parts.push({ PartNumber: n, ETag: etag });
                        if (n < total) { sendPart(n + 1, 1); return; }
                        st.textContent = 'Assemblage…'; st.className = 'status text-amber-600';
                        postJSON(V_COMPLETE, { video_id: s.video_id, parts: JSON.stringify(parts) })
                            .then(function (c) { c && c.ok ? videoDone() : videoFail(s.video_id, (c && c.error) || 'Assemblage échoué'); })
                            .catch(function () { videoFail(s.video_id, 'Réseau (assemblage)'); });
                    })
                    .catch(function (err) {
                        if (attempt < 2) { sendPart(n, attempt + 1); }   // 1 nouvel essai
                        else { videoFail(s.video_id, 'Part ' + n + '/' + total + ' : ' + err.message); }
                    });
            }
            sendPart(1, 1);
        }
    }

    function uploadOne(file) {
        var li = document.createElement('li');
        li.className = 'bg-white border border-[#e4e2e2] rounded-lg px-4 py-2';
        li.innerHTML =
            '<div class="flex justify-between text-sm"><span class="truncate pr-2">' +
            escapeHtml(file.name) + '</span><span class="status text-gray-400">0%</span></div>' +
            '<div class="h-1.5 bg-gray-100 rounded mt-2 overflow-hidden"><div class="bar h-full bg-[#6b6b6b]" style="width:0"></div></div>';
        list.prepend(li);
        var bar = li.querySelector('.bar');
        var st  = li.querySelector('.status');

        var fd = new FormData();
        fd.append('event_id', EVENT_ID);
        fd.append('album_id', document.getElementById('sel-album').value);
        fd.append('file', file);
        if (window.CSRF) fd.append(window.CSRF.name, window.CSRF.hash);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', STORE_URL, true);
        xhr.upload.onprogress = function (e) {
            if (e.lengthComputable) {
                var p = Math.round(e.loaded / e.total * 100);
                bar.style.width = p + '%';
                st.textContent = p + '%';
            }
        };
        xhr.onload = function () {
            var ok = false, msg = 'Erreur', jobId = null;
            try { var r = JSON.parse(xhr.responseText); ok = r.ok; jobId = r.job_id; if (!ok) msg = r.error || msg; } catch (e) {}
            if (ok) {
                bar.style.width = '100%';
                st.textContent = 'Conversion…';
                st.className = 'status text-amber-600';
                if (jobId != null) { jobsById[jobId] = { li: li, bar: bar, st: st, name: file.name }; }
                refreshStatus();
                // Déclenche la conversion automatiquement (groupée).
                scheduleProcess();
            } else {
                st.textContent = msg;
                st.className = 'status text-red-600';
                bar.classList.remove('bg-[#6b6b6b]'); bar.classList.add('bg-red-400');
            }
        };
        xhr.onerror = function () { st.textContent = 'Échec réseau'; st.className = 'status text-red-600'; };
        xhr.send(fd);
    }

    function refreshStatus() {
        if (!EVENT_ID || EVENT_ID === '0') return;
        fetch(STATUS_URL).then(function (r) { return r.json(); }).then(function (d) {
            if (!d.ok) return;
            ['pending','processing','done','error'].forEach(function (k) {
                var el = document.querySelector('[data-stat="' + k + '"]');
                if (el) el.textContent = d.counts[k];
            });
            var ph = document.querySelector('[data-stat="photos"]');
            if (ph) ph.textContent = d.photos;
        }).catch(function () {});
    }

    function escapeHtml(s) {
        return s.replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    // Rafraîchit la file toutes les 5 s (effet temps réel).
    setInterval(refreshStatus, 5000);

    /* ---- Traitement automatique + affichage live ---- */
    var btnProcess = document.getElementById('btn-process');
    var procMsg = document.getElementById('process-msg');
    var PROCESS_URL = '<?php echo site_url('admin/uploads/process'); ?>';
    var processing = false;          // un seul traitement en cours à la fois
    var rerun = false;               // un déclenchement est arrivé pendant le traitement
    var scheduleTimer = null;

    // Regroupe les uploads rapprochés avant de lancer la conversion.
    function scheduleProcess() {
        if (scheduleTimer) clearTimeout(scheduleTimer);
        scheduleTimer = setTimeout(runProcess, 500);
    }

    function runProcess() {
        if (processing) { rerun = true; return; }
        processing = true; rerun = false;
        if (btnProcess) btnProcess.disabled = true;
        procMsg.textContent = 'Conversion en cours…';

        var pfd = new FormData();
        if (window.CSRF) pfd.append(window.CSRF.name, window.CSRF.hash);
        fetch(PROCESS_URL, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: pfd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                processing = false;
                refreshStatus();
                if (d.ok && d.results) { d.results.forEach(applyResult); }

                // On continue tant qu'il reste des jobs (ou qu'un upload est arrivé).
                if ((d.ok && d.processed > 0) || rerun) {
                    procMsg.textContent = 'Conversion en cours…';
                    runProcess();
                } else {
                    procMsg.textContent = 'Tout est converti.';
                    if (btnProcess) btnProcess.disabled = false;
                }
            })
            .catch(function () {
                processing = false;
                procMsg.textContent = 'Erreur pendant la conversion.';
                if (btnProcess) btnProcess.disabled = false;
            });
    }

    // Révèle la vignette de la photo dès que son WebP existe.
    function applyResult(res) {
        var entry = jobsById[res.job_id];
        if (res.ok) {
            // Affiche la vignette dans la grille « prêtes ».
            var cell = document.createElement('div');
            cell.className = 'aspect-square overflow-hidden rounded-lg bg-[#e9e8e7]';
            cell.innerHTML = '<img src="' + res.webp + '" loading="lazy" decoding="async" alt="" ' +
                             'class="w-full h-full object-cover">';
            ready.prepend(cell);
            if (entry) {
                entry.st.textContent = 'Affichée';
                entry.st.className = 'status text-green-600';
                // Retire la ligne de transfert après un court instant.
                setTimeout(function () { if (entry.li && entry.li.parentNode) entry.li.remove(); }, 1200);
                delete jobsById[res.job_id];
            }
        } else if (entry) {
            entry.st.textContent = res.error || 'Erreur de conversion';
            entry.st.className = 'status text-red-600';
            entry.bar.classList.remove('bg-[#6b6b6b]'); entry.bar.classList.add('bg-red-400');
            delete jobsById[res.job_id];
        }
    }

    if (btnProcess) {
        btnProcess.addEventListener('click', function () { runProcess(); });
    }
})();
</script>

<?php endif; ?>
