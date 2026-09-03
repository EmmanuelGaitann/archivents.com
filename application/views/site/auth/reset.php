<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$in = 'width:100%; padding:13px 15px; border:1px solid var(--line-strong); border-radius:10px; font-size:15px; font-family:inherit; background:#fff; outline:none;';
?>
<section class="section">
    <div class="wrap" style="max-width:460px;">
        <div class="reveal" style="background:var(--surface); border:1px solid var(--line); border-radius:20px; padding:40px; box-shadow:var(--shadow);">
            <?php if ( ! empty($invalid)): ?>
                <span class="material-symbols-outlined" style="color:var(--warm); font-size:40px;">link_off</span>
                <h1 class="display" style="font-size:26px; margin:14px 0 6px;">Lien invalide ou expiré</h1>
                <p class="muted" style="margin:0 0 22px;">Ce lien de réinitialisation n'est plus valable. Demandez-en un nouveau.</p>
                <a class="btn btn-primary" href="<?php echo site_url('forgot'); ?>">Nouveau lien</a>
            <?php else: ?>
                <h1 class="display" style="font-size:28px; margin:0 0 6px;">Nouveau mot de passe</h1>
                <p class="muted" style="margin:0 0 20px;">Choisissez un mot de passe d'au moins 8 caractères.</p>
                <?php if ( ! empty($error)): ?>
                    <div style="background:var(--blush); color:var(--warm-deep); border-radius:8px; padding:10px 12px; font-size:14px; margin-bottom:14px;"><?php echo html_escape($error); ?></div>
                <?php endif; ?>
                <?php echo form_open('reset/'.$token); ?>
                    <input type="password" name="password" placeholder="Nouveau mot de passe" required style="<?php echo $in; ?>" autocomplete="new-password">
                    <input type="password" name="password2" placeholder="Confirmation" required style="<?php echo $in; ?> margin-top:12px;" autocomplete="new-password">
                    <button type="submit" class="btn btn-primary" style="width:100%; margin-top:16px;">Mettre à jour</button>
                <?php echo form_close(); ?>
            <?php endif; ?>
        </div>
    </div>
</section>
