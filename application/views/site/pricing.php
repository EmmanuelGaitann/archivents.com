<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if ( ! function_exists('av_price')) {
    function av_price($n){ return number_format((int)$n, 0, ',', ' ').' FCFA'; }
}
function av_retention_label($days){
    $days = (int)$days;
    if ($days % 365 === 0 && $days >= 365) return ($days/365).' an'.($days>365?'s':'');
    if ($days % 30 === 0 && $days >= 30) return ($days/30).' mois';
    return $days.' jours';
}
function av_go($mo){ $mo=(int)$mo; return ($mo % 1024 === 0) ? ($mo/1024).' Go' : round($mo/1024,1).' Go'; }

/** Liste de fonctionnalités d'un plan : [ [bool inclus, string texte], ... ]. */
function av_features($p){
    $f = $p['features'] ?? array();
    $list = array();
    $list[] = array(true, is_null($p['max_events']) ? 'Événements illimités' : ((int)$p['max_events']).' événement'.($p['max_events']>1?'s':'').' actif'.($p['max_events']>1?'s':''));
    $list[] = array(true, av_go($p['storage_mo']).' de stockage');
    $list[] = is_null($p['retention_days'])
        ? array(true, 'Conservé tant que l\'abonnement est actif')
        : array(true, 'En ligne '.av_retention_label($p['retention_days']));
    $list[] = array(true, 'Galerie privée (lien à code non devinable)');
    $list[] = array(true, 'QR code de partage');
    $list[] = array(!empty($p['gallery_password']), 'Mot de passe de galerie (lien chiffré)');
    $list[] = array(true, (($f['stats'] ?? 'basic')==='advanced') ? 'Statistiques avancées' : 'Statistiques de base');
    $list[] = array(!empty($f['hd_download']), 'Téléchargement HD invités');
    $list[] = array(!empty($p['video']), 'Ajout de vidéos');
    $list[] = array(empty($p['watermark']), 'Sans filigrane Archivents');
    $col = $p['max_collaborators'];
    if (is_null($col))      $list[] = array(true, 'Collaborateurs illimités');
    elseif ((int)$col > 0)  $list[] = array(true, ((int)$col).' collaborateur'.($col>1?'s':''));
    else                    $list[] = array(false, 'Collaborateurs');
    $list[] = array(!empty($p['remove_branding']), 'Sans marque « Archivents »');
    $list[] = array(!empty($p['custom_domain']), 'Domaine personnalisé');
    $support = $f['support'] ?? 'email';
    $slabel = $support==='dedicated' ? 'Support dédié' : ($support==='priority' ? 'Support prioritaire' : ($support==='community' ? 'Support communauté' : 'Support par e-mail'));
    $list[] = array(true, $slabel);
    return $list;
}

// Palier => présentation. (tier interne => libellé commercial)
$order = array(
    'gratuit'   => array('name'=>'Test','tag'=>'Pour tester','icon'=>'science','highlight'=>false,'color'=>'var(--teal)','chip'=>'var(--sage-l)'),
    'essentiel' => array('name'=>'Découverte','tag'=>'Pour démarrer','icon'=>'photo_camera','highlight'=>false,'color'=>'var(--warm)','chip'=>'var(--sky)'),
    'studio'    => array('name'=>'Pro','tag'=>'Le plus populaire','icon'=>'workspace_premium','highlight'=>true,'color'=>'var(--gold)','chip'=>'var(--grad-warm)'),
    'signature' => array('name'=>'Studio','tag'=>'Studios & agences','icon'=>'diamond','highlight'=>false,'color'=>'var(--gold-deep)','chip'=>'#f4ead2'),
);
$per = array('free'=>'','monthly'=>' /mois','yearly'=>' /an','per_event'=>' / événement');
?>

<style>
    .pcard{ transition:transform .3s ease, box-shadow .3s ease; }
    .pcard:hover{ transform:translateY(-6px); box-shadow:var(--shadow-lg); }
    .pcard-hi{ transform:scale(1.03); }
    .pcard-hi:hover{ transform:scale(1.03) translateY(-6px); }
    @media(max-width:640px){ .pcard-hi, .pcard-hi:hover{ transform:none; } }
    .pcheck{ display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:50%; flex:0 0 auto; }
</style>

<section class="section" style="padding-bottom:40px;">
    <div class="wrap center">
        <div class="label eyebrow" style="margin-bottom:16px;">Tarifs</div>
        <h1 class="display h1" style="font-size:clamp(36px,5vw,56px);">Un forfait pour chaque photographe</h1>
        <p class="lead" style="max-width:620px; margin:18px auto 0;">
            Commencez gratuitement, payez à l'événement, ou abonnez-vous au mois. Sans engagement.
        </p>
    </div>
</section>

<section style="padding-bottom:80px;">
    <div class="wrap">
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:22px; align-items:stretch;">
            <?php foreach ($order as $tier => $meta):
                if (empty($tiers[$tier])) continue;
                $base = reset($tiers[$tier]);          // le plan actif du palier
                $hi   = $meta['highlight'];
                $col  = $meta['color'];
                $is_free = ($base['billing_period'] === 'free' || (int)$base['prix'] === 0);
            ?>
            <div class="pcard<?php echo $hi?' pcard-hi':''; ?>" style="border-radius:20px; padding:36px 30px; display:flex; flex-direction:column; position:relative; overflow:hidden;
                <?php echo $hi ? 'background:var(--ink); color:#fff; box-shadow:var(--shadow-lg);' : 'background:var(--surface); color:var(--ink); border:1px solid var(--line);'; ?>">
                <?php if ($hi): ?>
                <div style="position:absolute; top:-70px; right:-70px; width:200px; height:200px; background:var(--grad-warm); border-radius:50%; opacity:.5; filter:blur(24px);"></div>
                <div style="position:absolute; top:16px; right:16px; z-index:1; background:var(--grad-warm); color:#fff; font-weight:600; font-size:11px; letter-spacing:.12em; text-transform:uppercase; padding:6px 13px; border-radius:999px;">★ Populaire</div>
                <?php endif; ?>

                <div style="display:flex; align-items:center; gap:13px; position:relative;">
                    <span class="chip" style="width:48px; height:48px; background:<?php echo $hi?'rgba(255,255,255,.12)':$meta['chip']; ?>;">
                        <span class="material-symbols-outlined" style="font-size:26px; color:<?php echo $hi?'#fff':$col; ?>;"><?php echo $meta['icon']; ?></span>
                    </span>
                    <div>
                        <div class="display" style="font-size:23px; line-height:1.1;"><?php echo $meta['name']; ?></div>
                        <div style="font-size:13px; <?php echo $hi?'color:#c9c3bc;':'color:var(--muted);'; ?>"><?php echo $meta['tag']; ?></div>
                    </div>
                </div>

                <div style="margin:26px 0 2px; position:relative;">
                    <?php if ($is_free): ?>
                        <span class="display" style="font-size:44px; font-weight:700;">Gratuit</span>
                        <div style="font-size:13px; margin-top:6px; <?php echo $hi?'color:#c9c3bc;':'color:var(--muted);'; ?>">Pour toujours · 0 FCFA</div>
                    <?php else: ?>
                        <span class="display" style="font-size:42px; font-weight:700;"><?php echo av_price($base['prix']); ?></span>
                        <span style="<?php echo $hi?'color:#b8b2ab;':'color:var(--muted);'; ?>"><?php echo $per[$base['billing_period']] ?? ''; ?></span>
                    <?php endif; ?>
                </div>

                <div style="height:1px; background:<?php echo $hi?'rgba(255,255,255,.14)':'var(--line)'; ?>; margin:24px 0; position:relative;"></div>

                <ul style="list-style:none; padding:0; margin:0 0 28px; display:grid; gap:12px; position:relative;">
                    <?php foreach (av_features($base) as $ft): $inc=$ft[0]; $txt=$ft[1]; ?>
                    <li style="display:flex; align-items:center; gap:11px; <?php echo $inc?'':'opacity:.4;'; ?>">
                        <span class="pcheck" style="background:<?php echo $inc ? ($hi?'rgba(255,255,255,.14)':'color-mix(in srgb,'.$col.' 15%,transparent)') : 'transparent'; ?>;">
                            <span class="material-symbols-outlined" style="font-size:15px; color:<?php echo $inc ? ($hi?'#fff':$col) : ($hi?'var(--soft)':'var(--muted)'); ?>;"><?php echo $inc?'check':'close'; ?></span>
                        </span>
                        <span style="<?php echo $inc?'':'text-decoration:line-through;'; ?>"><?php echo html_escape($txt); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <a class="btn <?php echo $hi?'btn-cream':'btn-light'; ?>" style="margin-top:auto; justify-content:center; position:relative;"
                   href="<?php echo site_url('register?plan='.$base['slug']); ?>"><?php echo $is_free ? 'Commencer gratuitement' : 'Choisir '.$meta['name']; ?></a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Ponctuel (paiement unique) -->
        <?php if ($pass): ?>
        <div style="margin-top:34px; background:var(--blush); border:1px solid color-mix(in srgb,var(--warm) 25%,transparent); border-radius:20px; padding:32px; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:24px;">
            <div style="display:flex; align-items:flex-start; gap:18px; max-width:660px;">
                <span class="chip" style="width:54px; height:54px; background:#fff; box-shadow:var(--shadow); flex:0 0 auto;">
                    <span class="material-symbols-outlined" style="font-size:28px; color:var(--warm);">confirmation_number</span>
                </span>
                <div>
                    <div class="label" style="color:var(--warm-deep);">Sans abonnement</div>
                    <h3 class="display" style="font-size:27px; margin:6px 0 6px;"><?php echo html_escape($pass['nom']); ?> — <?php echo av_price($pass['prix']); ?></h3>
                    <p style="margin:0; color:#5a4a40;">
                        Un besoin ponctuel ? Payez une seule fois pour <b>1 galerie</b> :
                        <?php echo av_retention_label($pass['retention_days']); ?> de rétention, <?php echo av_go($pass['storage_mo']); ?> de stockage,
                        galerie privée & QR. Idéal pour un événement isolé.
                    </p>
                </div>
            </div>
            <a class="btn btn-primary" href="<?php echo site_url('register?plan='.$pass['slug']); ?>">Prendre un Pass</a>
        </div>
        <?php endif; ?>

        <div class="center muted" style="margin-top:40px; max-width:680px; margin-left:auto; margin-right:auto;">
            <span class="material-symbols-outlined" style="color:var(--warm); font-size:22px;">payments</span>
            Paiement par <b>Orange Money</b>, <b>MTN MoMo</b> ou virement bancaire.
            Depuis l'étranger ou pour une activation immédiate,
            <a href="mailto:contact@archivents.com" style="color:var(--warm-deep); text-decoration:underline;">contactez-nous</a>.
        </div>
    </div>
</section>
