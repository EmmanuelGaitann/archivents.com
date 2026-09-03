<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
    </main>
</div>

<footer class="px-5 md:px-8 py-8 mt-8 border-t border-line flex items-center justify-between">
    <span class="font-display text-lg text-ink">Archivents</span>
    <span class="t-label text-[10px] text-muted">Console d'administration &middot; <?php echo date('Y'); ?></span>
</footer>

<script>
(function () {
    var btn = document.getElementById('sb-toggle');
    var sb = document.getElementById('sidebar');
    var ov = document.getElementById('sb-overlay');
    if (!btn) return;
    function open()  { sb.classList.remove('-translate-x-full'); ov.classList.remove('hidden'); }
    function close() { sb.classList.add('-translate-x-full'); ov.classList.add('hidden'); }
    btn.addEventListener('click', function () {
        sb.classList.contains('-translate-x-full') ? open() : close();
    });
    ov.addEventListener('click', close);
})();
</script>
</body>
</html>
