<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$couple = $settings['footer_couple_info'] ?? array();
?>
<?php if ( ! empty($show_watermark)): ?>
<!-- Filigrane du forfait gratuit (superposition légère, non cliquable). -->
<div aria-hidden="true" style="position:fixed; inset:0; z-index:40; pointer-events:none; opacity:.10;
     background-image:url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='260' height='170'%3E%3Ctext x='16' y='95' fill='%23000000' font-family='Georgia,serif' font-style='italic' font-size='26' transform='rotate(-28 130 85)'%3EArchivents%3C/text%3E%3C/svg%3E&quot;);
     background-repeat:repeat;"></div>
<?php endif; ?>
<footer class="mt-24 border-t border-line bg-surface">
    <div class="max-w-[1440px] mx-auto px-5 md:px-12 py-14 flex flex-col md:flex-row justify-between items-center gap-8 text-center md:text-left">
        <div>
            <?php if ( ! empty($couple['noms'])): ?>
                <p class="font-display italic text-3xl text-accent"><?php echo html_escape($couple['noms']); ?></p>
            <?php endif; ?>
            <?php if ( ! empty($couple['message'])): ?>
                <p class="mt-3 font-display italic text-lg text-muted max-w-xl"><?php echo html_escape($couple['message']); ?></p>
            <?php endif; ?>
        </div>
        <div class="text-center md:text-right">
            <?php if ( ! empty($couple['date'])): ?>
                <p class="t-label text-[11px] text-muted"><?php echo html_escape($couple['date']); ?></p>
            <?php endif; ?>
            <?php if ( ! empty($couple['photographe'])): ?>
                <p class="mt-2 t-label text-[11px] text-muted/80">Photographie — <?php echo html_escape($couple['photographe']); ?></p>
            <?php endif; ?>
            <?php if ( ! isset($show_branding) || $show_branding): ?>
            <p class="mt-3 text-[11px] tracking-wide text-muted/50">
                Propulsé par <a href="<?php echo site_url(); ?>" target="_blank" rel="noopener" class="hover:text-accent">Archivents</a>
            </p>
            <?php endif; ?>
        </div>
    </div>
</footer>

<script>
/* Archivents — suivi de visite (visitor_uid first-party). */
(function () {
    var HIT = '<?php echo site_url('track/hit/'.($slug ?? '')); ?>';

    function readCookie(n) {
        var m = document.cookie.match('(?:^|; )' + n + '=([^;]*)');
        return m ? decodeURIComponent(m[1]) : null;
    }
    function setCookie(n, v) {
        var d = new Date(); d.setFullYear(d.getFullYear() + 1);
        document.cookie = n + '=' + encodeURIComponent(v) + '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
    }
    function uuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
    function getUid() {
        var c = readCookie('av_uid');
        if (c) { try { localStorage.setItem('av_uid', c); } catch (e) {} return c; }
        var ls = null; try { ls = localStorage.getItem('av_uid'); } catch (e) {}
        if (ls) { setCookie('av_uid', ls); return ls; }
        var u = uuid();
        setCookie('av_uid', u);
        try { localStorage.setItem('av_uid', u); } catch (e) {}
        return u;
    }

    var uid = getUid();
    // Empreinte légère (secours) — jamais d'adresse MAC (impossible côté navigateur).
    var fp = [screen.width, screen.height, screen.colorDepth,
              (Intl.DateTimeFormat().resolvedOptions().timeZone || ''),
              navigator.language, navigator.platform || ''].join('|');

    window.AVTrack = {
        hit: function (type, opts) {
            opts = opts || {};
            var fd = new FormData();
            fd.append('uid', uid);
            fd.append('type', type);
            fd.append('fp', fp);
            if (opts.album_id) fd.append('album_id', opts.album_id);
            if (opts.photo_id) fd.append('photo_id', opts.photo_id);
            if (navigator.sendBeacon) { navigator.sendBeacon(HIT, fd); }
            else { fetch(HIT, { method: 'POST', body: fd, keepalive: true }).catch(function () {}); }
        }
    };

    // Hit initial de la page (open ou album_view).
    AVTrack.hit('<?php echo $track_type ?? 'open'; ?>',
        { album_id: <?php echo ($track_album_id ?? 0) ? (int) $track_album_id : 'null'; ?> });
})();
</script>

</body>
</html>
