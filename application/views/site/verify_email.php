<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="section">
    <div class="wrap center" style="max-width:560px; padding-top:clamp(48px,8vw,90px); padding-bottom:clamp(48px,8vw,90px);">
        <?php if ($ok): ?>
            <span class="material-symbols-outlined" style="font-size:64px; color:var(--sage);">mark_email_read</span>
            <h1 class="display h2" style="margin:18px 0 10px;">Adresse confirmée</h1>
            <p class="lead" style="margin-bottom:28px;">
                <?php echo $already
                    ? 'Votre adresse e-mail était déjà confirmée. Tout est en ordre.'
                    : 'Merci ! Votre adresse e-mail est maintenant confirmée.'; ?>
            </p>
            <a class="btn btn-primary" href="<?php echo site_url('admin/dashboard'); ?>">Accéder à mon espace</a>
        <?php else: ?>
            <span class="material-symbols-outlined" style="font-size:64px; color:var(--warm);">link_off</span>
            <h1 class="display h2" style="margin:18px 0 10px;">Lien invalide</h1>
            <p class="lead" style="margin-bottom:28px;">
                Ce lien de confirmation n'est pas valide (adresse modifiée, ou lien incomplet).
                Connectez-vous puis demandez un nouvel e-mail de confirmation depuis votre tableau de bord.
            </p>
            <a class="btn btn-primary" href="<?php echo site_url('login'); ?>">Se connecter</a>
        <?php endif; ?>
    </div>
</section>
