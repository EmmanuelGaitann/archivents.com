<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$h2 = 'font-size:21px; margin:30px 0 10px;';
?>
<section class="section">
    <div class="wrap" style="max-width:800px;">
        <div class="label eyebrow" style="margin-bottom:14px;">Vos données</div>
        <h1 class="display h2" style="margin:0 0 8px;">Politique de confidentialité</h1>
        <p class="muted" style="margin:0 0 24px;">Dernière mise à jour : <?php echo date('d/m/Y'); ?>.</p>

        <div style="line-height:1.8; color:#3f3b34;">
            <p>
                LoyalTeck (« Archivents ») attache une grande importance à la protection des données. Cette politique
                explique quelles données nous traitons dans le cadre du service Archivents, pourquoi, avec qui, combien de
                temps, et quels sont vos droits. Elle fait partie intégrante de nos
                <a href="<?php echo site_url('conditions'); ?>" style="color:var(--warm-deep);text-decoration:underline;">Conditions</a>.
            </p>

            <h2 class="display" style="<?php echo $h2; ?>">1. Responsable du traitement</h2>
            <p>
                Le responsable du traitement est <b>LoyalTeck</b>, siège social à Yaoundé (Cameroun).
                Contact : <a href="mailto:contact@archivents.com" style="color:var(--warm-deep);text-decoration:underline;">contact@archivents.com</a>.
            </p>

            <h2 class="display" style="<?php echo $h2; ?>">2. Données que nous traitons</h2>
            <ul style="padding-left:20px;">
                <li><b>Compte photographe</b> : nom du studio, adresse e‑mail, mot de passe (stocké sous forme chiffrée / haché), identifiant d'espace.</li>
                <li><b>Facturation & abonnement</b> : forfait, statut, montants, moyen et références de paiement, historique.</li>
                <li><b>Contenus</b> : les photos et vidéos que le photographe met en ligne, ainsi que les personnes qui y figurent.</li>
                <li><b>Mesure d'audience des galeries</b> : un identifiant de visite anonyme (cookie <code>av_uid</code>), l'événement/album/photo consulté, la source (QR ou lien), l'adresse IP, l'agent utilisateur et des caractéristiques techniques du navigateur.</li>
                <li><b>Journaux techniques</b> : logs de sécurité et d'erreurs. <i>Aucune adresse MAC n'est collectée (techniquement impossible depuis un navigateur).</i></li>
            </ul>

            <h2 class="display" style="<?php echo $h2; ?>">3. Finalités et bases</h2>
            <p>
                Nous traitons ces données pour : fournir le Service (héberger et diffuser les galeries), créer et gérer les
                comptes et abonnements, traiter les paiements, fournir au photographe des statistiques de consultation,
                assurer la sécurité et prévenir les abus, et respecter nos obligations légales. Les bases sont l'exécution
                du contrat, notre intérêt légitime (sécurité, statistiques) et le respect d'obligations légales.
            </p>

            <h2 class="display" style="<?php echo $h2; ?>">4. Rôle du photographe vis‑à‑vis des personnes photographiées</h2>
            <p>
                Les photos et vidéos peuvent contenir des données personnelles de tiers (invités). Le <b>photographe</b> décide
                du contenu mis en ligne et de sa diffusion ; il est responsable d'avoir recueilli les <b>autorisations et le
                consentement</b> nécessaires (droit à l'image). Archivents agit à cet égard comme prestataire technique
                (sous‑traitant) hébergeant et diffusant ces contenus pour le compte du photographe. Toute personne concernée
                peut nous écrire pour signaler un contenu ou demander son retrait.
            </p>

            <h2 class="display" style="<?php echo $h2; ?>">5. Hébergement, stockage et sous‑traitants</h2>
            <p>
                Les données de compte et la base sont hébergées par <b>O2Switch</b> (France, Union européenne). Les médias
                (photos, vidéos) sont stockés sur <b>Cloudflare R2</b> et diffusés via le réseau <b>Cloudflare</b>. Les
                paiements sont traités via les opérateurs de <b>Mobile Money (Orange Money, MTN MoMo)</b> et/ou l'établissement
                bancaire concerné. Ces prestataires agissent en sous‑traitants et n'utilisent pas vos données à d'autres fins
                que la fourniture de leurs services.
            </p>

            <h2 class="display" style="<?php echo $h2; ?>">6. Durées de conservation</h2>
            <p>
                Les données de compte sont conservées tant que le compte est actif. Les <b>fichiers originaux haute résolution</b>
                sont conservés pour la durée de rétention du forfait (de 15 jours à 1 an), puis peuvent être purgés. Les données
                de facturation sont conservées pour la durée requise par la loi. Les journaux techniques sont conservés pour une
                durée limitée à des fins de sécurité. À la clôture du compte, vos données et contenus sont supprimés après un
                délai raisonnable.
            </p>

            <h2 class="display" style="<?php echo $h2; ?>">7. Sécurité</h2>
            <p>
                Vos données sont protégées par des mesures techniques et organisationnelles : chiffrement <b>en transit</b>
                (HTTPS/TLS) et <b>au repos</b> (stockage R2), mots de passe <b>hachés</b>, protection anti‑CSRF, liens de galerie
                non devinables et mot de passe optionnel par galerie, accès restreint aux personnes habilitées. Aucun système
                n'étant infaillible, nous ne pouvons garantir une sécurité absolue.
            </p>

            <h2 class="display" style="<?php echo $h2; ?>">8. Pas d'IA, pas de revente</h2>
            <p>
                Nous n'utilisons <b>pas</b> vos contenus pour entraîner des modèles d'intelligence artificielle et nous ne les
                <b>vendons</b> ni ne les cédons à des tiers à des fins commerciales.
            </p>

            <h2 class="display" style="<?php echo $h2; ?>">9. Vos droits</h2>
            <p>
                Vous disposez d'un droit d'accès, de rectification, de suppression, d'opposition et de portabilité de vos
                données, ainsi que du droit de demander la fermeture de votre compte. Pour exercer ces droits, écrivez à
                <a href="mailto:contact@archivents.com" style="color:var(--warm-deep);text-decoration:underline;">contact@archivents.com</a>.
                Nous répondons dans un délai raisonnable et pouvons vérifier votre identité.
            </p>

            <h2 class="display" style="<?php echo $h2; ?>">10. Cookies</h2>
            <p>
                Nous utilisons un cookie technique de session (nécessaire à la connexion) et un identifiant de visite
                (<code>av_uid</code>) servant uniquement à la mesure d'audience des galeries. <b>Aucun cookie publicitaire tiers</b>
                n'est déposé. Vous pouvez configurer votre navigateur pour bloquer les cookies, au risque de dégrader certaines
                fonctionnalités.
            </p>

            <h2 class="display" style="<?php echo $h2; ?>">11. Mineurs</h2>
            <p>
                Le Service n'est pas destiné à être utilisé, en tant que titulaire de compte, par des personnes mineures.
            </p>

            <h2 class="display" style="<?php echo $h2; ?>">12. Modifications</h2>
            <p>
                Cette politique peut être mise à jour ; la date en tête indique la dernière version. En cas de changement
                significatif, nous vous en informerons par un moyen raisonnable.
            </p>

            <p style="margin-top:32px;">
                Voir aussi nos
                <a href="<?php echo site_url('mentions-legales'); ?>" style="color:var(--warm-deep);text-decoration:underline;">mentions légales</a>
                et nos
                <a href="<?php echo site_url('conditions'); ?>" style="color:var(--warm-deep);text-decoration:underline;">Conditions</a>.
            </p>
        </div>
    </div>
</section>
