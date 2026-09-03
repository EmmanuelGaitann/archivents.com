<?php
defined('BASEPATH') OR exit('No direct script access allowed');
if ( ! function_exists('av_price')) {
    function av_price($n){ return number_format((int)$n, 0, ',', ' ').' FCFA'; }
}
$nom     = html_escape($info['nom'] ?? '');
$slug    = html_escape($info['studio_slug'] ?? '');
$plan    = html_escape($info['plan_nom'] ?? '');
$prix    = isset($info['plan_prix']) ? av_price($info['plan_prix']) : '';
$is_free = ! empty($info['is_free']);
?>
<section class="section">
    <div class="wrap" style="max-width:640px;">
        <div class="reveal" style="background:var(--surface); border:1px solid var(--line); border-radius:22px; padding:44px; box-shadow:var(--shadow); text-align:center;">
            <span class="chip" style="width:76px; height:76px; background:var(--sage-l); margin:0 auto;">
                <span class="material-symbols-outlined" style="font-size:42px; color:var(--teal);">celebration</span>
            </span>
            <h1 class="display" style="font-size:34px; margin:20px 0 8px;">Bienvenue<?php echo $nom ? ', '.$nom : ''; ?> !</h1>
            <p class="muted" style="margin:0 auto 22px; max-width:460px;">
                Votre espace est créé. Votre adresse&nbsp;:
                <b style="color:var(--warm-deep);"><?php echo $slug; ?>.archivents.com</b>
            </p>

            <?php if ($is_free): ?>
            <!-- Gratuit : actif immédiatement -->
            <div style="background:var(--sage-l); border-radius:14px; padding:20px; text-align:left; display:flex; gap:14px; align-items:flex-start; margin-bottom:26px;">
                <span class="material-symbols-outlined" style="color:var(--teal); font-size:26px;">check_circle</span>
                <div>
                    <div style="font-weight:600;">Formule <?php echo $plan; ?> — active immédiatement</div>
                    <div style="color:#3f4b2c; font-size:14px; margin-top:4px;">
                        Vous pouvez créer votre première galerie dès maintenant. Besoin de plus d'événements,
                        d'une rétention plus longue ou de retirer la marque Archivents ?
                        <a href="<?php echo site_url('pricing'); ?>" style="color:var(--warm-deep); text-decoration:underline;">Passez à un forfait supérieur</a>.
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Payant : en attente d'activation -->
            <div style="background:var(--blush); border-radius:14px; padding:20px; text-align:left; display:flex; gap:14px; align-items:flex-start; margin-bottom:26px;">
                <span class="material-symbols-outlined" style="color:var(--warm); font-size:26px;">schedule</span>
                <div>
                    <div style="font-weight:600;">Forfait <?php echo $plan; ?> — en attente d'activation</div>
                    <div style="color:#5a4a40; font-size:14px; margin-top:4px;">
                        Réglez <b><?php echo $prix; ?></b> par Orange Money, MTN MoMo ou virement — ou
                        <a href="mailto:contact@archivents.com?subject=<?php echo rawurlencode('Activation Archivents — '.$slug); ?>" style="color:var(--warm-deep); text-decoration:underline;">contactez-nous</a>
                        pour une activation immédiate. Votre compte reste accessible entre-temps.
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
                <a class="btn btn-primary" href="<?php echo site_url('admin/dashboard'); ?>">Accéder à mon espace</a>
                <a class="btn btn-light" href="<?php echo site_url('pricing'); ?>">Voir les forfaits</a>
            </div>
        </div>
    </div>
</section>
