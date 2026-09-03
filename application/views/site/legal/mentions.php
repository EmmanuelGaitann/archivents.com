<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="section">
    <div class="wrap" style="max-width:760px;">
        <div class="label eyebrow" style="margin-bottom:14px;">Informations légales</div>
        <h1 class="display h2" style="margin:0 0 8px;">Mentions légales</h1>
        <p class="muted" style="margin:0 0 32px;">Dernière mise à jour : <?php echo date('d/m/Y'); ?>.</p>

        <div style="line-height:1.8; color:#3f3b34;">
            <h2 class="display" style="font-size:22px; margin:28px 0 10px;">Éditeur du site</h2>
            <p>
                Le site et le service <b>Archivents</b> (archivents.com) sont édités par
                <b>LoyalTeck</b>, société établie à <b>Yaoundé (Cameroun)</b>.<br>
                Contact : <a href="mailto:contact@archivents.com" style="color:var(--warm-deep);text-decoration:underline;">contact@archivents.com</a>.
                Les informations d'immatriculation de la société peuvent être obtenues sur simple
                demande à cette adresse.
            </p>

            <h2 class="display" style="font-size:22px; margin:28px 0 10px;">Hébergement</h2>
            <p>
                Le site et la base de données sont hébergés par <b>O2Switch</b> — 222‑224 Boulevard
                Gustave Flaubert, 63000 Clermont‑Ferrand, France —
                <a href="https://www.o2switch.fr" target="_blank" rel="noopener" style="color:var(--warm-deep);text-decoration:underline;">o2switch.fr</a>.<br>
                Le stockage et la diffusion des médias (photos et vidéos) sont assurés par
                <b>Cloudflare, Inc.</b> (Cloudflare R2 / réseau de diffusion Cloudflare).
            </p>

            <h2 class="display" style="font-size:22px; margin:28px 0 10px;">Propriété intellectuelle</h2>
            <p>
                La marque « Archivents », le logo, l'interface, les textes et les éléments graphiques du
                site sont la propriété de LoyalTeck et sont protégés. Les photographies et vidéos
                mises en ligne par les photographes demeurent leur propriété exclusive.
            </p>

            <h2 class="display" style="font-size:22px; margin:28px 0 10px;">Documents contractuels</h2>
            <p>
                L'utilisation du service est régie par nos
                <a href="<?php echo site_url('conditions'); ?>" style="color:var(--warm-deep);text-decoration:underline;">Conditions Générales d'Utilisation et de Vente</a>
                et notre
                <a href="<?php echo site_url('confidentialite'); ?>" style="color:var(--warm-deep);text-decoration:underline;">Politique de confidentialité</a>.
            </p>
        </div>
    </div>
</section>
