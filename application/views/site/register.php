<?php
defined('BASEPATH') OR exit('No direct script access allowed');
if ( ! function_exists('av_price')) {
    function av_price($n){ return number_format((int)$n, 0, ',', ' ').' FCFA'; }
}
$period_label = array('free'=>'','monthly'=>'/mois','yearly'=>'/an','per_event'=>'paiement unique');
$err = function($k) use ($errors){ return isset($errors[$k]) ? $errors[$k] : NULL; };
$old_nom  = html_escape($old['nom'] ?? '');
$old_mail = html_escape($old['email'] ?? '');
$old_slug = html_escape($old['studio_slug'] ?? '');
$in_style = 'width:100%; padding:13px 15px; border:1px solid var(--line-strong); border-radius:10px; font-size:15px; font-family:inherit; background:#fff; outline:none;';

$is_free    = ($plan['billing_period'] === 'free' || (int)$plan['prix'] === 0);
$price_disp = $is_free ? 'Gratuit' : av_price($plan['prix']).' '.($period_label[$plan['billing_period']] ?? '');
// Emblème selon le palier.
$tier_icon = array('gratuit'=>'redeem','pass'=>'confirmation_number','essentiel'=>'photo_camera','studio'=>'workspace_premium','signature'=>'diamond');
$emb = $tier_icon[$plan['tier']] ?? 'sell';
?>
<style>
    .field label{ display:block; font-weight:600; font-size:14px; margin-bottom:7px; }
    .field{ margin-bottom:18px; }
    .field .err{ color:#ba1a1a; font-size:13px; margin-top:6px; }
    .field input:focus{ border-color:var(--warm)!important; box-shadow:0 0 0 3px color-mix(in srgb,var(--warm) 18%,transparent); }
    .slugbox{ display:flex; align-items:stretch; border:1px solid var(--line-strong); border-radius:10px; overflow:hidden; background:#fff; }
    .slugbox input{ border:0!important; box-shadow:none!important; flex:1; padding:13px 15px; font-size:15px; font-family:inherit; outline:none; }
    .slugbox .suffix{ display:flex; align-items:center; padding:0 14px; background:var(--champagne); color:var(--muted); font-size:14px; white-space:nowrap; }
</style>

<section class="section">
    <div class="wrap" style="max-width:1040px;">
        <div style="display:grid; grid-template-columns:1.15fr .85fr; gap:40px; align-items:start;">

            <!-- Formulaire -->
            <div class="reveal">
                <span class="pill" style="background:var(--blush); color:var(--warm-deep);">Créons votre espace</span>
                <h1 class="display" style="font-size:clamp(30px,4vw,42px); margin:16px 0 6px;">Inscription photographe</h1>
                <p class="muted" style="margin:0 0 26px;">Quelques informations et votre studio est prêt à livrer sa première galerie.</p>

                <!-- Forfait choisi (résumé, pas de menu déroulant) -->
                <div style="display:flex; align-items:center; gap:14px; background:<?php echo $is_free?'var(--sage-l)':'var(--accent-soft)'; ?>; border-radius:14px; padding:16px 18px; margin-bottom:26px;">
                    <span class="chip" style="width:46px; height:46px; background:#fff; box-shadow:var(--shadow); flex:0 0 auto;">
                        <span class="material-symbols-outlined" style="font-size:24px; color:<?php echo $is_free?'var(--teal)':'var(--warm)'; ?>;"><?php echo $emb; ?></span>
                    </span>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:12px; letter-spacing:.12em; text-transform:uppercase; font-weight:600; color:var(--muted);">Votre formule</div>
                        <div style="font-weight:700;"><?php echo html_escape($plan['nom']); ?> · <?php echo $price_disp; ?></div>
                    </div>
                    <a href="<?php echo site_url('pricing'); ?>" style="font-size:13px; font-weight:600; color:var(--warm-deep); text-decoration:underline; white-space:nowrap;">Changer</a>
                </div>

                <?php echo form_open('register'); ?>
                    <input type="hidden" name="plan_slug" value="<?php echo html_escape($plan['slug']); ?>">

                    <div class="field">
                        <label for="nom">Nom du studio</label>
                        <input type="text" id="nom" name="nom" value="<?php echo $old_nom; ?>" placeholder="Studio Lumière" style="<?php echo $in_style; ?>" autocomplete="organization">
                        <?php if ($err('nom')): ?><div class="err"><?php echo html_escape($err('nom')); ?></div><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="studio_slug">Votre adresse Archivents</label>
                        <div class="slugbox">
                            <input type="text" id="studio_slug" name="studio_slug" value="<?php echo $old_slug; ?>" placeholder="studio-lumiere" autocapitalize="off" autocomplete="off" spellcheck="false">
                            <span class="suffix">.archivents.com</span>
                        </div>
                        <div class="muted" style="font-size:13px; margin-top:6px;">
                            Aperçu : <b id="slug-preview" style="color:var(--warm-deep);"><?php echo $old_slug ?: 'votre-studio'; ?>.archivents.com</b>
                        </div>
                        <?php if ($err('studio_slug')): ?><div class="err"><?php echo html_escape($err('studio_slug')); ?></div><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" value="<?php echo $old_mail; ?>" placeholder="vous@exemple.com" style="<?php echo $in_style; ?>" autocomplete="email">
                        <?php if ($err('email')): ?><div class="err"><?php echo html_escape($err('email')); ?></div><?php endif; ?>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                        <div class="field">
                            <label for="password">Mot de passe</label>
                            <input type="password" id="password" name="password" placeholder="8 caractères min." style="<?php echo $in_style; ?>" autocomplete="new-password">
                            <?php if ($err('password')): ?><div class="err"><?php echo html_escape($err('password')); ?></div><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="password2">Confirmation</label>
                            <input type="password" id="password2" name="password2" placeholder="Retapez le mot de passe" style="<?php echo $in_style; ?>" autocomplete="new-password">
                            <?php if ($err('password2')): ?><div class="err"><?php echo html_escape($err('password2')); ?></div><?php endif; ?>
                        </div>
                    </div>

                    <label style="display:flex; align-items:flex-start; gap:10px; font-size:13px; color:var(--muted); margin-bottom:6px;">
                        <input type="checkbox" name="cgu" value="1" style="margin-top:3px;" <?php echo ! empty($_POST['cgu']) ? 'checked' : ''; ?> required>
                        <span>J'ai lu et j'accepte les
                            <a href="<?php echo site_url('conditions'); ?>" target="_blank" style="color:var(--warm-deep); text-decoration:underline;">Conditions</a>
                            et la
                            <a href="<?php echo site_url('confidentialite'); ?>" target="_blank" style="color:var(--warm-deep); text-decoration:underline;">Politique de confidentialité</a>.
                        </span>
                    </label>
                    <?php if ($err('cgu')): ?><div class="err" style="margin-bottom:8px;"><?php echo html_escape($err('cgu')); ?></div><?php endif; ?>

                    <button type="submit" class="btn btn-primary" style="width:100%; margin-top:8px;">
                        <?php echo $is_free ? 'Commencer gratuitement' : 'Créer mon espace'; ?>
                    </button>

                    <div class="muted center" style="margin-top:16px; font-size:14px;">
                        <?php if ($is_free): ?>Sans carte bancaire. <?php endif; ?>
                        Déjà un compte ? <a href="<?php echo site_url('login'); ?>" style="color:var(--warm-deep); text-decoration:underline;">Se connecter</a>
                    </div>
                <?php echo form_close(); ?>
            </div>

            <!-- Récap / réassurance -->
            <aside class="reveal" style="background:var(--ink); color:#fff; border-radius:18px; padding:32px; position:sticky; top:96px;">
                <div class="display" style="font-size:24px; margin-bottom:18px;">Pourquoi Archivents ?</div>
                <ul style="list-style:none; padding:0; margin:0; display:grid; gap:16px;">
                    <?php foreach (array(
                        array('bolt','Prêt en quelques minutes','Votre espace et votre première galerie, sans installation.'),
                        array('lock','Galeries privées','Lien non devinable + mot de passe optionnel par galerie.'),
                        array('insights','Statistiques en direct','Sachez qui regarde et télécharge vos photos.'),
                        array('payments','Paiement flexible','Orange Money, MTN MoMo ou virement — ou activation manuelle.'),
                    ) as $b): ?>
                    <li style="display:flex; gap:13px; align-items:flex-start;">
                        <span class="chip" style="width:40px; height:40px; background:rgba(255,255,255,.12); flex:0 0 auto;">
                            <span class="material-symbols-outlined" style="font-size:22px; color:var(--gold);"><?php echo $b[0]; ?></span>
                        </span>
                        <div>
                            <div style="font-weight:600;"><?php echo $b[1]; ?></div>
                            <div style="color:#c9c3b9; font-size:14px;"><?php echo $b[2]; ?></div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </aside>
        </div>
    </div>
</section>

<script>
(function(){
    var nom = document.getElementById('nom'),
        slug = document.getElementById('studio_slug'),
        prev = document.getElementById('slug-preview'),
        touched = <?php echo $old_slug ? 'true' : 'false'; ?>;
    function toSlug(s){
        return (s||'').toLowerCase()
            .normalize('NFD').replace(/[̀-ͯ]/g,'')
            .replace(/[^a-z0-9]+/g,'-').replace(/-{2,}/g,'-').replace(/^-|-$/g,'');
    }
    function render(){ prev.textContent = (toSlug(slug.value) || 'votre-studio') + '.archivents.com'; }
    if (slug) slug.addEventListener('input', function(){ touched = true; render(); });
    if (nom) nom.addEventListener('input', function(){ if(!touched){ slug.value = toSlug(nom.value); render(); } });
})();
</script>
