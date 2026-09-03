<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$is_edit = ($mode === 'edit');
$action  = $is_edit ? 'admin/users/edit/'.$user['id'] : 'admin/users/create';
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="font-display text-3xl text-ink"><?php echo $is_edit ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur'; ?></h1>
    <a href="<?php echo site_url('admin/users'); ?>" class="text-sm text-[#1b1c1c] hover:underline">&larr; Liste</a>
</div>

<?php if ( ! empty($errors)): ?>
    <div class="mb-5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?php echo $errors; ?></div>
<?php endif; ?>

<div class="bg-white rounded-xl border border-[#e4e2e2] p-6 max-w-lg">
    <?php echo form_open($action); ?>

        <label class="block mb-4">
            <span class="text-sm text-gray-600">Nom</span>
            <input type="text" name="nom" required value="<?php echo html_escape($user['nom']); ?>"
                   class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#6b6b6b]">
        </label>

        <label class="block mb-4">
            <span class="text-sm text-gray-600">Email</span>
            <input type="email" name="email" required value="<?php echo html_escape($user['email']); ?>"
                   class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#6b6b6b]">
        </label>

        <label class="block mb-4">
            <span class="text-sm text-gray-600">
                Mot de passe <?php echo $is_edit ? '(laisser vide pour ne pas changer)' : '(min. 8 caractères)'; ?>
            </span>
            <input type="password" name="password" <?php echo $is_edit ? '' : 'required'; ?>
                   class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#6b6b6b]">
        </label>

        <label class="block mb-4">
            <span class="text-sm text-gray-600">Rôle</span>
            <select name="role" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                <?php foreach ($roles as $r): ?>
                    <option value="<?php echo html_escape($r['slug']); ?>" <?php echo ($user['role'] === $r['slug']) ? 'selected' : ''; ?>>
                        <?php echo html_escape($r['nom']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="flex items-center gap-2 mb-6">
            <input type="checkbox" name="actif" value="1" <?php echo ((int) $user['actif'] === 1) ? 'checked' : ''; ?>
                   class="rounded border-gray-300">
            <span class="text-sm text-gray-600">Compte actif</span>
        </label>

        <div class="flex gap-3">
            <button class="rounded-lg bg-[#1b1c1c] hover:bg-[#000000] text-white px-5 py-2.5">
                <?php echo $is_edit ? 'Enregistrer' : 'Créer'; ?>
            </button>
            <a href="<?php echo site_url('admin/users'); ?>" class="rounded-lg border border-[#e4e2e2] text-gray-600 px-5 py-2.5">Annuler</a>
        </div>

    <?php echo form_close(); ?>
</div>
