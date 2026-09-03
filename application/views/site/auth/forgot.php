<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$in = 'width:100%; padding:13px 15px; border:1px solid var(--line-strong); border-radius:10px; font-size:15px; font-family:inherit; background:#fff; outline:none;';
?>
<section class="section">
    <div class="wrap" style="max-width:460px;">
        <div class="reveal" style="background:var(--surface); border:1px solid var(--line); border-radius:20px; padding:40px; box-shadow:var(--shadow);">
            <h1 class="display" style="font-size:28px; margin:0 0 6px;">Mot de passe oublié</h1>

            <?php if ( ! empty($sent)): ?>
                <p class="muted" style="margin:12px 0 0;">
                    Si un compte existe pour cette adresse, un e-mail contenant un lien de réinitialisation vient d'être envoyé.
                    Le lien est valable 1 heure.
                </p>
                <div style="margin-top:22px;">
                    <a class="btn btn-light" href="<?php echo site_url('login'); ?>">Retour à la connexion</a>
                </div>
            <?php else: ?>
                <p class="muted" style="margin:0 0 20px;">Indiquez votre e-mail : nous vous enverrons un lien pour définir un nouveau mot de passe.</p>
                <?php if ( ! empty($error)): ?>
                    <div style="background:var(--blush); color:var(--warm-deep); border-radius:8px; padding:10px 12px; font-size:14px; margin-bottom:14px;"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php echo form_open('forgot'); ?>
                    <input type="email" name="email" placeholder="vous@exemple.com" required style="<?php echo $in; ?>" autocomplete="email">
                    <button type="submit" class="btn btn-primary" style="width:100%; margin-top:16px;">Envoyer le lien</button>
                <?php echo form_close(); ?>
                <div class="muted center" style="margin-top:16px; font-size:14px;">
                    <a href="<?php echo site_url('login'); ?>" style="color:var(--warm-deep); text-decoration:underline;">Retour à la connexion</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
