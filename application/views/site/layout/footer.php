<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
</main>

<style>
    .feat:hover{ background:#fff !important; border-color:var(--sage) !important; }
    .ph img{ transition:transform .7s cubic-bezier(.16,1,.3,1); }
    .ph:hover img{ transform:scale(1.05); }
</style>

<footer style="background:var(--surface); border-top:1px solid var(--line); padding:clamp(64px,9vw,110px) 0 40px;">
    <div class="wrap" style="display:flex; flex-wrap:wrap; gap:40px; justify-content:space-between;">
        <div style="max-width:340px;">
            <div class="brand" style="font-size:28px;">Archiv<b>ents</b></div>
            <p class="muted" style="margin-top:14px;">
                La plateforme des photographes d'événement : livrez vos galeries privées,
                suivez vos statistiques, encaissez vos abonnements.
            </p>
        </div>
        <div style="display:flex; gap:64px; flex-wrap:wrap;">
            <div>
                <div class="label" style="color:var(--soft); margin-bottom:16px;">Produit</div>
                <div style="display:grid; gap:12px;">
                    <a href="<?php echo site_url(); ?>#features" class="muted">Prestations</a>
                    <a href="<?php echo site_url(); ?>#gallery" class="muted">Aperçu</a>
                    <a href="<?php echo site_url('pricing'); ?>" class="muted">Tarifs</a>
                </div>
            </div>
            <div>
                <div class="label" style="color:var(--soft); margin-bottom:16px;">Compte</div>
                <div style="display:grid; gap:12px;">
                    <a href="<?php echo site_url('register'); ?>" class="muted">S'inscrire</a>
                    <a href="<?php echo site_url('login'); ?>" class="muted">Se connecter</a>
                    <a href="mailto:contact@archivents.com" class="muted">Nous contacter</a>
                </div>
            </div>
        </div>
    </div>
    <div class="wrap" style="border-top:1px solid var(--line); margin-top:48px; padding-top:24px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px;">
        <span class="muted" style="font-size:14px;">© <?php echo date('Y'); ?> Archivents — un produit de LoyalTeck. Tous droits réservés.</span>
        <span class="muted" style="font-size:14px; display:flex; gap:16px; flex-wrap:wrap;">
            <a href="<?php echo site_url('conditions'); ?>" style="color:inherit;">Conditions</a>
            <a href="<?php echo site_url('confidentialite'); ?>" style="color:inherit;">Confidentialité</a>
            <a href="<?php echo site_url('mentions-legales'); ?>" style="color:inherit;">Mentions légales</a>
        </span>
    </div>
</footer>

<script>
    (function(){
        var els = document.querySelectorAll('.reveal');
        if (!('IntersectionObserver' in window) || !els.length){
            els.forEach(function(e){ e.classList.add('in'); });
            return;
        }
        var io = new IntersectionObserver(function(entries){
            entries.forEach(function(en){
                if (en.isIntersecting){ en.target.classList.add('in'); io.unobserve(en.target); }
            });
        }, { threshold:0.12, rootMargin:'0px 0px -8% 0px' });
        els.forEach(function(e){ io.observe(e); });
    })();
</script>

<!-- Bouton WhatsApp flottant -->
<a href="https://wa.me/237658956855?text=Bonjour%2C%20je%20viens%20du%20site%20Archivents." target="_blank" rel="noopener" aria-label="Nous écrire sur WhatsApp"
   style="position:fixed; bottom:22px; right:22px; z-index:80; width:56px; height:56px; border-radius:50%; background:#25D366; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 24px rgba(0,0,0,.25);">
    <svg viewBox="0 0 32 32" width="30" height="30" fill="#fff" aria-hidden="true"><path d="M16 3C9.4 3 4 8.3 4 14.9c0 2.6.8 5 2.3 7L4 29l7.3-2.3c1.9 1 4 1.6 4.7 1.6 6.6 0 12-5.4 12-12S22.6 3 16 3zm0 21.8c-1.4 0-3.2-.5-4.6-1.3l-.3-.2-4.3 1.4 1.4-4.2-.2-.3c-1.1-1.6-1.7-3.5-1.7-5.3C6.3 9.5 10.7 5.2 16 5.2s9.7 4.3 9.7 9.7-4.4 9.9-9.7 9.9zm5.4-7.3c-.3-.1-1.7-.9-2-1s-.5-.1-.7.1-.8 1-1 1.2-.4.2-.7.1c-.3-.1-1.2-.5-2.4-1.5-.9-.8-1.5-1.8-1.6-2.1s0-.5.1-.6l.5-.6c.1-.2.2-.3.3-.5s0-.4 0-.5c-.1-.1-.7-1.6-.9-2.2-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4s-1 1-1 2.5 1.1 2.9 1.2 3.1c.1.2 2.1 3.2 5.1 4.5.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.7-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.1-.3-.2-.6-.3z"/></svg>
</a>
</body>
</html>
