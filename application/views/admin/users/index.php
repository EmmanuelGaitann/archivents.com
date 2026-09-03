<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="font-display text-3xl text-ink">Utilisateurs</h1>
    <div class="flex items-center gap-4">
        <a href="<?php echo site_url('admin/dashboard'); ?>" class="text-sm text-[#1b1c1c] hover:underline">&larr; Tableau de bord</a>
        <a href="<?php echo site_url('admin/users/create'); ?>"
           class="rounded-lg bg-[#1b1c1c] hover:bg-[#000000] text-white text-sm px-4 py-2">+ Nouvel utilisateur</a>
    </div>
</div>

<?php if ($msg = $this->session->flashdata('ok')): ?>
    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3"><?php echo html_escape($msg); ?></div>
<?php endif; ?>
<?php if ($err = $this->session->flashdata('err')): ?>
    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?php echo html_escape($err); ?></div>
<?php endif; ?>

<div class="bg-white rounded-xl border border-[#e4e2e2] overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-[#efeded] text-left text-gray-600">
            <tr>
                <th class="px-4 py-3">Nom</th>
                <th class="px-4 py-3">Email</th>
                <th class="px-4 py-3">Rôle</th>
                <th class="px-4 py-3">Statut</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[#f0e6da]">
            <?php foreach ($users as $u): ?>
            <tr>
                <td class="px-4 py-3 font-medium"><?php echo html_escape($u['nom']); ?></td>
                <td class="px-4 py-3 text-gray-600"><?php echo html_escape($u['email']); ?></td>
                <td class="px-4 py-3">
                    <span class="inline-block rounded-full bg-[#efeded] text-[#1b1c1c] px-2 py-0.5 text-xs">
                        <?php echo html_escape($role_map[$u['role']] ?? $u['role']); ?>
                    </span>
                </td>
                <td class="px-4 py-3">
                    <?php if ((int) $u['actif'] === 1): ?>
                        <span class="text-green-600">Actif</span>
                    <?php else: ?>
                        <span class="text-gray-400">Inactif</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-3">
                        <a href="<?php echo site_url('admin/users/edit/'.$u['id']); ?>" class="text-[#1b1c1c] hover:underline">Modifier</a>
                        <?php if ((int) $u['id'] !== (int) $current_user['id']): ?>
                            <?php echo form_open('admin/users/delete/'.$u['id'], array(
                                'class' => 'inline',
                                'onsubmit' => "return confirm('Supprimer cet utilisateur ?');"
                            )); ?>
                                <button class="text-red-600 hover:underline">Supprimer</button>
                            <?php echo form_close(); ?>
                        <?php else: ?>
                            <span class="text-gray-300 text-xs">(vous)</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
