<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if ( ! function_exists('av_price')) {
    function av_price($n){ return number_format((int)$n, 0, ',', ' ').' FCFA'; }
}
function av_tier_month($tiers, $tier){
    if (empty($tiers[$tier])) return NULL;
    return $tiers[$tier]['monthly'] ?? reset($tiers[$tier]);
}
$free = av_tier_month($tiers, 'gratuit');
$ess = av_tier_month($tiers, 'essentiel');
$stu = av_tier_month($tiers, 'studio');
$sig = av_tier_month($tiers, 'signature');
$demo_url = $demo_url ?? NULL;

$hero_img = 'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=2000&q=80';

// Deux rangées de photos pour la galerie défilante (repli couleur si non chargées).
// Mélange volontaire : la joie (mariages, fêtes, naissances, diplômes) domine,
// avec quelques images sobres (hommages / obsèques) plus discrètes.
$row1 = array(
    'photo-1511285560929-80b456fea0bc', // mariage — lâcher de ballons
    'photo-1583939003579-730e3918a45a', // mariage — baiser sous les pétales
    'photo-1462536943532-57a629f6cc60', // remise de diplômes au coucher du soleil
    'photo-1519225421980-715cb0215aed', // table de réception fleurie
    'photo-1495231916356-a86217efff12', // rose blanche — hommage
    'photo-1530103862676-de8c9debad1d', // ballons d'anniversaire
);
$row2 = array(
    'photo-1606216794074-735e91aa2c92', // couple rieur — mariage
    'photo-1492684223066-81342ee5ff30', // confettis — concert / fête
    'photo-1478476868527-002ae3f3e159', // mains réconfortantes — obsèques
    'photo-1519741497674-611481863552', // bouquet en contre-jour
    'photo-1470116945706-e6bf5d5a53ca', // main de nouveau-né — naissance
    'photo-1465495976277-4387d4b0b4c6', // alliances
);
$tints = array('var(--champagne)','var(--sage-l)','var(--blush)','var(--sky)','#f4ead2');
function av_marquee_row($ids, $tints, $rev = false){
    $out = '<div class="grow"><div class="marquee'.($rev?' rev':'').'">';
    // doublé pour une boucle sans couture (-50%)
    for ($k=0; $k<2; $k++){
        foreach ($ids as $i=>$id){
            $bg = $tints[$i % count($tints)];
            $url = 'https://images.unsplash.com/'.$id.'?auto=format&fit=crop&w=600&q=70';
            $out .= '<div class="tile" style="background:'.$bg.'"><img src="'.$url.'" alt="" loading="lazy"></div>';
        }
    }
    $out .= '</div></div>';
    return $out;
}
?>

<!-- HERO -->
<section style="position:relative; height:94vh; min-height:580px; display:flex; align-items:center; justify-content:center; overflow:hidden;">
    <div style="position:absolute; inset:0; z-index:0; background:#2b2620;">
        <div style="width:100%; height:100%; background-image:url('<?php echo $hero_img; ?>'); background-size:cover; background-position:center; transform:scale(1.05);"></div>
        <!-- Voile chaud coloré (plus de vie qu'un simple gris) -->
        <div style="position:absolute; inset:0; background:linear-gradient(180deg,rgba(24,25,25,.28) 0%,rgba(120,55,25,.30) 60%,rgba(24,25,25,.62) 100%);"></div>
    </div>
    <div class="reveal" style="position:relative; z-index:10; text-align:center; padding:0 24px; max-width:940px;">
        <span class="pill" style="background:rgba(255,255,255,.16); color:#fff; backdrop-filter:blur(6px); margin-bottom:22px;">
            <span style="color:var(--gold);">★★★★★</span> Aimé par les photographes d'événement
        </span>
        <h1 class="display" style="color:#fff; font-size:clamp(40px,7.2vw,74px); line-height:1.04; letter-spacing:-.02em; margin:16px 0 18px; text-shadow:0 2px 40px rgba(0,0,0,.3);">
            Vos souvenirs méritent<br><span style="font-style:italic; background:var(--grad-warm); -webkit-background-clip:text; background-clip:text; color:transparent;">une galerie qui rayonne</span>
        </h1>
        <p style="color:rgba(255,255,255,.92); font-size:20px; max-width:620px; margin:0 auto 34px;">
            Livrez des galeries privées, vivantes et élégantes — partagées par lien ou QR,
            que vos clients adorent revoir et partager.
        </p>
        <div style="display:flex; gap:14px; justify-content:center; flex-wrap:wrap;">
            <a class="btn btn-cream" href="<?php echo site_url('register'); ?>">Commencer gratuitement</a>
            <?php if ($demo_url): ?>
            <a class="btn btn-outline-light" href="<?php echo $demo_url; ?>">Voir une galerie <span class="material-symbols-outlined">arrow_outward</span></a>
            <?php endif; ?>
        </div>
    </div>
    <a href="#gallery" style="position:absolute; bottom:30px; left:50%; transform:translateX(-50%); color:rgba(255,255,255,.75);" aria-label="Défiler">
        <span class="material-symbols-outlined" style="font-size:38px;">expand_more</span>
    </a>
</section>

<!-- BANDEAU CONFIANCE (coloré) -->
<section style="background:var(--grad-warm);">
    <div class="wrap" style="display:flex; flex-wrap:wrap; gap:28px; justify-content:space-around; padding:20px 24px; color:#fff;">
        <?php foreach (array(
            array('lock','Galeries privées'),
            array('qr_code_2','QR & lien privé'),
            array('bar_chart','Statistiques en direct'),
            array('hd','Téléchargement HD'),
        ) as $t): ?>
        <div style="display:flex; align-items:center; gap:10px; font-weight:600;">
            <span class="material-symbols-outlined" style="font-size:22px;"><?php echo $t[0]; ?></span><?php echo $t[1]; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- IMMERSION : galerie qui défile -->
<section class="section" id="gallery" style="padding-bottom:clamp(48px,7vw,80px);">
    <div class="wrap">
        <div class="reveal center" style="max-width:680px; margin:0 auto 48px;">
            <span class="pill" style="background:var(--blush); color:var(--warm-deep);">Aperçu vivant</span>
            <h2 class="display h2" style="margin:16px 0 10px;">Une immersion visuelle,<br>en mouvement</h2>
            <p class="lead">Mariages, anniversaires, remises de diplômes, hommages : de vraies galeries qui défilent, comme vos clients les vivront.</p>
        </div>
    </div>
    <div class="reveal gwall" style="--gw-fade:var(--bg);">
        <?php echo av_marquee_row($row1, $tints, false); ?>
        <?php echo av_marquee_row($row2, $tints, true); ?>
    </div>
    <div class="center" style="margin-top:44px;">
        <a class="btn btn-primary" href="<?php echo $demo_url ?: site_url('register'); ?>">Explorer une galerie <span class="material-symbols-outlined">arrow_forward</span></a>
    </div>
</section>

<!-- PRESTATIONS (cartes colorées) -->
<section class="section" id="features" style="background:var(--surface); border-top:1px solid var(--line); border-bottom:1px solid var(--line);">
    <div class="wrap">
        <div class="reveal center" style="max-width:640px; margin:0 auto 60px;">
            <span class="pill" style="background:var(--sage-l); color:var(--teal);">Prestations</span>
            <h2 class="display h2" style="margin-top:16px;">Tout pour livrer, avec style</h2>
        </div>
        <div class="reveal" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:24px;">
            <?php
            // [icône, titre, desc, fond carte, fond puce, couleur puce]
            $features = array(
                array('share','Partage instantané','Un lien privé ou un QR à afficher le jour J. Vos invités accèdent à leurs souvenirs partout dans le monde.','var(--blush)','#fff','var(--warm)'),
                array('palette','Design sur-mesure','Une galerie aux couleurs de chaque événement : thème, typographies et mise en page éditoriale.','var(--sage-l)','#fff','var(--teal)'),
                array('high_quality','Haute définition','Vos fichiers en pleine résolution : téléchargement HD et impressions parfaites, sans compromis.','#f4ead2','#fff','var(--gold-deep)'),
                array('lock','Confidentialité','Lien non devinable et mot de passe optionnel par galerie. Vous décidez qui voit quoi.','var(--sky)','#fff','var(--teal)'),
                array('insights','Statistiques','Visiteurs, photos les plus vues et téléchargées : suivez la vie de chaque galerie en direct.','var(--champagne)','#fff','var(--warm-deep)'),
                array('schedule','Rétention maîtrisée','Les originaux restent disponibles selon votre forfait, puis sont archivés proprement.','var(--blush)','#fff','var(--warm)'),
            );
            foreach ($features as $f): ?>
            <div class="reveal" style="padding:34px; border-radius:14px; background:<?php echo $f[3]; ?>;">
                <span class="chip" style="background:<?php echo $f[4]; ?>; box-shadow:var(--shadow);">
                    <span class="material-symbols-outlined" style="color:<?php echo $f[5]; ?>;"><?php echo $f[0]; ?></span>
                </span>
                <h3 class="display" style="font-size:25px; margin:20px 0 10px;"><?php echo $f[1]; ?></h3>
                <p style="margin:0; color:#4a463f;"><?php echo $f[2]; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="center" style="margin-top:44px;">
            <a class="btn btn-primary" href="<?php echo site_url('register'); ?>">Créer mon espace</a>
        </div>
    </div>
</section>

<!-- COMMENT ÇA MARCHE -->
<section class="section" id="how">
    <div class="wrap">
        <div class="reveal center" style="max-width:640px; margin:0 auto 60px;">
            <span class="pill" style="background:#f4ead2; color:var(--gold-deep);">En 3 étapes</span>
            <h2 class="display h2" style="margin-top:16px;">De la prise de vue à la livraison</h2>
        </div>
        <div class="reveal" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:36px;">
            <?php
            $steps = array(
                array('01','Créez votre espace','Inscrivez-vous et choisissez votre forfait. Votre studio a son propre espace Archivents.','var(--warm)'),
                array('02','Ajoutez vos événements','Importez vos photos : elles sont optimisées automatiquement en plusieurs résolutions.','var(--teal)'),
                array('03','Partagez la galerie','Envoyez le lien privé ou le QR, et suivez les visites en temps réel.','var(--gold-deep)'),
            );
            foreach ($steps as $s): ?>
            <div>
                <div class="display" style="font-size:56px; line-height:1; color:<?php echo $s[3]; ?>;"><?php echo $s[0]; ?></div>
                <div style="width:44px; height:3px; background:<?php echo $s[3]; ?>; margin:14px 0 16px; border-radius:2px;"></div>
                <h3 class="display" style="font-size:25px; margin:0 0 10px;"><?php echo $s[1]; ?></h3>
                <p class="muted" style="margin:0;"><?php echo $s[2]; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- BANNIÈRE CTA colorée -->
<section style="padding:0 24px;">
    <div class="wrap reveal" style="background:var(--grad-sun); border-radius:22px; padding:clamp(40px,6vw,64px); color:#fff; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:24px;">
        <div style="max-width:560px;">
            <h2 class="display" style="font-size:clamp(26px,3.6vw,38px); margin:0 0 10px;">Votre prochaine galerie, prête ce soir.</h2>
            <p style="color:rgba(255,255,255,.9); margin:0;">Créez votre espace en quelques minutes — payez à l'événement ou abonnez-vous.</p>
        </div>
        <a class="btn btn-cream" href="<?php echo site_url('register'); ?>">Commencer maintenant</a>
    </div>
</section>

<!-- APERÇU TARIFS -->
<section class="section" id="pricing-preview">
    <div class="wrap">
        <div class="reveal center" style="max-width:640px; margin:0 auto 56px;">
            <span class="pill" style="background:var(--sage-l); color:var(--teal);">Forfaits</span>
            <h2 class="display h2" style="margin-top:16px;">Choisissez votre formule</h2>
            <p class="lead" style="margin-top:12px;">Payez à l'événement pour un besoin ponctuel, ou abonnez-vous pour livrer toute l'année. Paiement par MTN Mobile Money, Orange Money ou carte.</p>
        </div>
        <div class="reveal" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:22px; align-items:stretch;">

            <?php if ($free): ?>
            <div style="background:var(--surface); border:1px solid var(--line); border-radius:16px; padding:36px; display:flex; flex-direction:column;">
                <div class="label" style="color:var(--teal);">Test</div>
                <div style="margin:16px 0 4px;"><span class="display" style="font-size:40px;">Gratuit</span></div>
                <p class="muted" style="margin:8px 0 20px;">Pour tester Archivents, sans engagement.</p>
                <ul style="list-style:none; padding:0; margin:0 0 24px; display:grid; gap:11px;">
                    <li style="color:var(--teal);">✦ <span style="color:var(--ink);">1 événement actif</span></li>
                    <li style="color:var(--teal);">✦ <span style="color:var(--ink);">Rétention 7 jours</span></li>
                    <li style="color:var(--teal);">✦ <span style="color:var(--ink);">Galerie privée &amp; QR</span></li>
                    <li style="color:var(--teal);">✦ <span style="color:var(--ink);">Photos HD, avec filigrane Archivents</span></li>
                    <li style="color:var(--teal);">✦ <span style="color:var(--ink);">1 Go de stockage</span></li>
                </ul>
                <a class="btn btn-light" style="margin-top:auto;" href="<?php echo site_url('register?plan='.$free['slug']); ?>">Commencer gratuitement</a>
            </div>
            <?php endif; ?>

            <?php if ($ess): ?>
            <div style="background:var(--surface); border:1px solid var(--line); border-radius:16px; padding:36px; display:flex; flex-direction:column;">
                <div class="label" style="color:var(--warm);">Découverte</div>
                <div style="margin:16px 0 4px;"><span class="display" style="font-size:40px;"><?php echo av_price($ess['prix']); ?></span><span class="muted"> /mois</span></div>
                <p class="muted" style="margin:8px 0 20px;">Pour démarrer et livrer vos premiers événements.</p>
                <ul style="list-style:none; padding:0; margin:0 0 24px; display:grid; gap:11px;">
                    <li style="color:var(--warm);">✦ <span style="color:var(--ink);">2 événements actifs</span></li>
                    <li style="color:var(--warm);">✦ <span style="color:var(--ink);">Photos HD, sans filigrane</span></li>
                    <li style="color:var(--warm);">✦ <span style="color:var(--ink);">Lien privé à code non devinable &amp; QR</span></li>
                    <li style="color:var(--warm);">✦ <span style="color:var(--ink);">Galeries conservées tant que l'abonnement est actif</span></li>
                    <li style="color:var(--warm);">✦ <span style="color:var(--ink);">10 Go de stockage</span></li>
                </ul>
                <a class="btn btn-light" style="margin-top:auto;" href="<?php echo site_url('pricing'); ?>">Découvrir</a>
            </div>
            <?php endif; ?>

            <?php if ($stu): ?>
            <div style="background:var(--ink); color:#fff; border-radius:16px; padding:46px; display:flex; flex-direction:column; box-shadow:var(--shadow-lg); position:relative; overflow:hidden;">
                <div style="position:absolute; top:-60px; right:-55px; width:180px; height:180px; background:var(--grad-warm); border-radius:50%; opacity:.5; filter:blur(20px);"></div>
                <div style="position:absolute; top:10px; left:70%; transform:translateX(-50%); background:var(--grad-warm); color:#fff; font-weight:600; font-size:12px; letter-spacing:.14em; text-transform:uppercase; padding:6px 16px; border-radius:999px; z-index:1;">★ Le plus populaire</div>
                <div class="label" style="color:var(--gold); position:relative;">Pro</div>
                <div style="margin:16px 0 4px; position:relative;"><span class="display" style="font-size:40px;"><?php echo av_price($stu['prix']); ?></span><span style="color:#b7b1a7;"> /mois</span></div>
                <p style="color:#c9c3b9; margin:8px 0 20px; position:relative;">Pour le photographe qui livre régulièrement.</p>
                <ul style="list-style:none; padding:0; margin:0 0 24px; display:grid; gap:11px; position:relative;">
                    <li style="color:var(--gold);">✦ <span style="color:#fff;">5 événements actifs</span></li>
                    <li style="color:var(--gold);">✦ <span style="color:#fff;">Mot de passe de galerie (lien chiffré)</span></li>
                    <li style="color:var(--gold);">✦ <span style="color:#fff;">Thèmes aux couleurs de chaque événement</span></li>
                    <li style="color:var(--gold);">✦ <span style="color:#fff;">Statistiques avancées</span></li>
                    <li style="color:var(--gold);">✦ <span style="color:#fff;">Sans marque « Archivents »</span></li>
                    <li style="color:var(--gold);">✦ <span style="color:#fff;">30 Go de stockage</span></li>
                </ul>
                <a class="btn btn-cream" style="margin-top:auto; position:relative;" href="<?php echo site_url('register?plan='.$stu['slug']); ?>">Choisir Pro</a>
            </div>
            <?php endif; ?>

            <?php if ($sig): ?>
            <div style="background:var(--surface); border:1px solid var(--line-strong); border-radius:16px; padding:36px; display:flex; flex-direction:column;">
                <div class="label" style="color:var(--warm);">Studio</div>
                <div style="margin:16px 0 4px;"><span class="display" style="font-size:40px;"><?php echo av_price($sig['prix']); ?></span><span class="muted"> /mois</span></div>
                <p class="muted" style="margin:8px 0 20px;">Tout Pro, sous votre propre nom — pour le studio qui livre chaque semaine.</p>
                <ul style="list-style:none; padding:0; margin:0 0 24px; display:grid; gap:11px;">
                    <li style="color:var(--warm);">✦ <span style="color:var(--ink);">12 événements actifs</span></li>
                    <li style="color:var(--warm);">✦ <span style="color:var(--ink);">Ajout de vidéos</span></li>
                    <li style="color:var(--warm);">✦ <span style="color:var(--ink);">Collaborateurs illimités</span></li>
                    <li style="color:var(--warm);">✦ <span style="color:var(--ink);">Multi-marques : plusieurs identités de studio</span></li>
                    <li style="color:var(--warm);">✦ <span style="color:var(--ink);">Domaine personnalisé</span></li>
                    <li style="color:var(--warm);">✦ <span style="color:var(--ink);">80 Go de stockage</span></li>
                </ul>
                <a class="btn btn-light" style="margin-top:auto;" href="<?php echo site_url('register?plan='.$sig['slug']); ?>">Choisir Studio</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Ponctuel (sans abonnement) -->
        <?php if ($pass): ?>
        <div class="reveal" style="margin-top:26px; background:var(--surface); border:1px dashed var(--line-strong); border-radius:16px; padding:26px 32px; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:18px;">
            <div>
                <div class="label" style="color:var(--teal);">Besoin ponctuel — sans abonnement</div>
                <div style="margin-top:8px;"><span class="display" style="font-size:32px;"><?php echo av_price($pass['prix']); ?></span><span class="muted"> / événement</span></div>
                <p class="muted" style="margin:6px 0 0;">1 événement · photos HD sans filigrane · galerie privée &amp; QR · 5 Go · en ligne 15 jours. Photos uniquement — sans les vidéos, thèmes et statistiques des abonnements.</p>
            </div>
            <a class="btn btn-light" href="<?php echo site_url('register?plan='.$pass['slug']); ?>">Créer mon événement</a>
        </div>
        <?php endif; ?>

        <div class="center" style="margin-top:30px;">
            <a class="btn btn-ghost" href="<?php echo site_url('pricing'); ?>">Comparer tous les forfaits</a>
        </div>
    </div>
</section>

<!-- OFFRE CLÉ EN MAIN — organisateurs & studios qui veulent tout déléguer -->
<section class="section" id="cle-en-main" style="background:var(--champagne);">
    <div class="wrap">
        <div class="reveal center" style="max-width:680px; margin:0 auto 48px;">
            <span class="pill" style="background:var(--surface); color:var(--accent-deep);">Offre clé en main</span>
            <h2 class="display h2" style="margin-top:16px;">Vous organisez l'événement ?<br>Nous nous occupons de la galerie.</h2>
            <p class="lead" style="margin-top:12px;">Mariages, galas d'entreprise, remises de diplômes, célébrations : nous configurons tout et nous sommes présents le jour J.</p>
        </div>
        <div class="reveal" style="background:var(--ink); color:#fff; border-radius:20px; padding:clamp(34px,5vw,56px); box-shadow:var(--shadow-lg); position:relative; overflow:hidden; display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:36px; align-items:center;">
            <div style="position:absolute; top:-70px; right:-60px; width:220px; height:220px; background:var(--grad-warm); border-radius:50%; opacity:.4; filter:blur(28px);"></div>
            <div style="position:relative;">
                <div class="label" style="color:var(--gold);">Événement clé en main</div>
                <div style="margin:14px 0 4px;"><span class="display" style="font-size:44px;">150 000 FCFA</span><span style="color:#b7b1a7;"> / premier événement</span></div>
                <p style="color:#c9c3b9; margin:8px 0 0;">Puis <b style="color:#fff;">50 000 FCFA</b> par événement suivant, votre espace étant déjà en place.</p>
            </div>
            <ul style="list-style:none; padding:0; margin:0; display:grid; gap:12px; position:relative;">
                <li style="color:var(--gold);">✦ <span style="color:#fff;">Préparation avec vous en amont — nous configurons tout</span></li>
                <li style="color:var(--gold);">✦ <span style="color:#fff;">Vos photos importées, triées et mises en ligne par notre équipe</span></li>
                <li style="color:var(--gold);">✦ <span style="color:#fff;">Deux QR codes : standard <b>et crypté</b> — exclusif à cette offre</span></li>
                <li style="color:var(--gold);">✦ <span style="color:#fff;">Un technicien présent sur place le jour J</span></li>
                <li style="color:var(--gold);">✦ <span style="color:#fff;">Invités servis en HD, même à des centaines de connexions simultanées</span></li>
            </ul>
            <div style="position:relative;">
                <a class="btn btn-cream" href="https://wa.me/237658956855?text=Bonjour%2C%20je%20souhaite%20une%20galerie%20cl%C3%A9%20en%20main%20pour%20mon%20%C3%A9v%C3%A9nement." target="_blank" rel="noopener">Discuter sur WhatsApp</a>
                <p style="color:#b7b1a7; font-size:13px; margin:12px 0 0;">Réponse sous 24&nbsp;h — devis ferme après un échange de 15 minutes.</p>
            </div>
        </div>
        <div class="reveal" style="max-width:760px; margin:34px auto 0; text-align:center;">
            <p class="muted" style="margin:0;">Les forfaits plus haut sont un <b style="color:var(--ink);">outil que vous pilotez</b> vous-même.
            Le clé en main est une <b style="color:var(--ink);">prestation : notre équipe fait le travail à votre place</b>, avec une obligation de résultat le jour J.
            Vous savez utiliser la plateforme ? Le ponctuel à 13&nbsp;500 vous suffit. Vous voulez ne penser à rien devant vos invités ? C'est ici.</p>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="section" id="faq" style="padding-top:clamp(56px,8vw,90px);">
    <div class="wrap" style="max-width:820px;">
        <div class="reveal center" style="margin-bottom:40px;">
            <span class="pill" style="background:var(--champagne); color:var(--accent-deep);">Questions fréquentes</span>
            <h2 class="display h2" style="margin-top:16px;">Avant de vous lancer</h2>
        </div>
        <div class="reveal" style="display:grid; gap:12px;">
            <?php
            $faq = array(
                array('Comment se passe le paiement ?', 'Par MTN Mobile Money, Orange Money ou carte bancaire. Le forfait ponctuel se règle en une fois ; les abonnements se renouvellent chaque mois avec une confirmation sur votre téléphone.'),
                array('Combien de temps mes photos restent-elles en ligne ?', 'Selon votre forfait : 7 jours en Test, 15 jours en ponctuel, et aussi longtemps que votre abonnement est actif et sous quota pour Découverte, Pro et Studio. Vous êtes prévenu avant toute archive.'),
                array('À qui appartiennent les photos ?', 'À vous, entièrement. Archivents héberge et diffuse vos fichiers ; nous ne les exploitons jamais et vous pouvez les retirer à tout moment.'),
                array('Ponctuel ou abonnement : que choisir ?', 'Un seul événement de temps en temps : le ponctuel à 13 500 FCFA suffit. Vous livrez plusieurs événements par mois : l\'abonnement devient vite plus économique.'),
                array('Et si je veux qu\'on s\'occupe de tout pour moi ?', 'C\'est l\'offre clé en main : configuration complète, QR crypté et présence de notre équipe le jour de l\'événement.'),
            );
            foreach ($faq as $f): ?>
            <details style="background:var(--surface); border:1px solid var(--line); border-radius:14px; padding:20px 24px;">
                <summary style="cursor:pointer; font-weight:600;"><?php echo html_escape($f[0]); ?></summary>
                <p class="muted" style="margin:12px 0 0;"><?php echo html_escape($f[1]); ?></p>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- TÉMOIGNAGE sur fond image + voile chaud -->
<section style="position:relative; overflow:hidden; padding:clamp(80px,12vw,140px) 0; text-align:center; color:#fff;">
    <div style="position:absolute; inset:0; z-index:0; background:#2b2620;">
        <div style="width:100%; height:100%; background-image:url('https://images.unsplash.com/photo-1522673607200-164d1b6ce486?auto=format&fit=crop&w=1600&q=80'); background-size:cover; background-position:center;"></div>
        <div style="position:absolute; inset:0; background:linear-gradient(135deg,rgba(138,63,30,.82),rgba(24,25,25,.78));"></div>
    </div>
    <div class="wrap reveal" style="position:relative; z-index:10; max-width:1020px;">
        <span class="material-symbols-outlined" style="color:var(--gold); font-size:56px;">format_quote</span>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:clamp(28px,5vw,56px); margin-top:14px; text-align:center;">
            <div>
                <blockquote class="display" style="font-size:clamp(20px,2.6vw,28px); font-style:italic; line-height:1.45; margin:0 0 20px;">
                    « Nous voulions une galerie à la hauteur de notre photographe. Archivents a transformé
                    nos photos en un véritable livre d'art numérique. »
                </blockquote>
                <cite class="label" style="color:var(--gold); font-style:normal;">— Awa &amp; Junior, mariage</cite>
            </div>
            <div>
                <blockquote class="display" style="font-size:clamp(20px,2.6vw,28px); font-style:italic; line-height:1.45; margin:0 0 20px;">
                    « Nos invités ont reçu le lien le soir même : chacun a retrouvé ses photos et les a
                    téléchargées en HD. Simple, élégant, inoubliable. »
                </blockquote>
                <cite class="label" style="color:var(--gold); font-style:normal;">— Tasha &amp; Térence, mariage</cite>
            </div>
        </div>
    </div>
</section>

<!-- CTA FINALE (dégradé chaud) -->
<section style="position:relative; overflow:hidden; background:var(--grad-sun); color:#fff; text-align:center; padding:clamp(72px,11vw,120px) 0;">
    <div style="position:absolute; inset:0; opacity:.18; pointer-events:none;">
        <div style="position:absolute; top:0; left:0; width:440px; height:440px; background:var(--gold); border-radius:50%; filter:blur(140px); transform:translate(-40%,-40%);"></div>
        <div style="position:absolute; bottom:0; right:0; width:440px; height:440px; background:#fff; border-radius:50%; filter:blur(150px); transform:translate(40%,40%);"></div>
    </div>
    <div class="wrap reveal" style="position:relative; z-index:10;">
        <h2 class="display" style="font-size:clamp(30px,4.6vw,50px); margin:0 0 14px;">Prêt à faire rayonner vos souvenirs ?</h2>
        <p style="color:rgba(255,255,255,.92); max-width:560px; margin:0 auto 30px;">Créez votre espace en quelques minutes. Payez à l'événement ou abonnez-vous, sans engagement.</p>
        <div style="display:flex; gap:14px; justify-content:center; flex-wrap:wrap;">
            <a class="btn btn-cream" href="<?php echo site_url('register'); ?>">Commencer gratuitement</a>
            <a class="btn btn-outline-light" href="<?php echo site_url('pricing'); ?>">Voir les tarifs</a>
        </div>
    </div>
</section>
