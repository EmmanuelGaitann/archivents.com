<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$s = $settings ?? array();
$couple = $s['footer_couple_info'] ?? array();
$page_titles = $s['page_titles'] ?? array();
$loading_on = ! isset($s['loading_screen_enabled']) || (int) $s['loading_screen_enabled'] === 1;
$logo_choice = $s['loading_logo_choice'] ?? 'couple_monogram';
$loading_dur = isset($s['loading_screen_duration']) ? (int) $s['loading_screen_duration'] : 600;
$gallery_mode = $s['gallery_mode'] ?? 'folders_first';
if ($gallery_mode === 'all_then_filter') { $gallery_mode = 'photos_only'; } // ancien mode
$theme = $s['theme'] ?? array();
$c_primary = $theme['primary'] ?? '#6b6b6b';
$c_accent  = $theme['accent']  ?? '#1b1c1c';
$c_bg      = $theme['bg']      ?? '#f5f3f3';
$ho = $s['header_options'] ?? array();
$hdr_opacity    = isset($ho['opacity'])    ? (int) $ho['opacity']    : 30;
$hdr_brightness = isset($ho['brightness']) ? (int) $ho['brightness'] : 100;
$hdr_pos_x      = isset($ho['pos_x'])      ? (int) $ho['pos_x']      : 50;
$hdr_pos_y      = isset($ho['pos_y'])      ? (int) $ho['pos_y']      : 50;
$hdr_image      = $s['couple_photo_path'] ?? NULL;

function av_img_field($label, $field, $current, $hint = '') {
    $html  = '<div class="border border-[#e4e2e2] rounded-lg p-4">';
    $html .= '<div class="text-sm font-medium text-gray-700 mb-2">'.html_escape($label).'</div>';
    if (!empty($current)) {
        $html .= '<img src="'.base_url($current).'" class="h-20 rounded mb-2 object-contain bg-[#efeded] p-1">';
    } else {
        $html .= '<div class="text-xs text-gray-400 mb-2">Aucune image</div>';
    }
    $html .= '<input type="file" name="'.$field.'" accept="image/jpeg,image/png,image/webp" class="text-sm">';
    if ($hint) $html .= '<p class="text-xs text-gray-400 mt-1">'.html_escape($hint).'</p>';
    $html .= '</div>';
    return $html;
}
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="font-display text-3xl text-ink">Paramètres de l'événement</h1>
    <a href="<?php echo site_url('admin/dashboard'); ?>" class="text-sm text-[#1b1c1c] hover:underline">&larr; Tableau de bord</a>
</div>

<?php if ($msg = $this->session->flashdata('ok')): ?>
    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3"><?php echo html_escape($msg); ?></div>
<?php endif; ?>
<?php if ($err = $this->session->flashdata('err')): ?>
    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?php echo html_escape($err); ?></div>
<?php endif; ?>

<?php if (empty($events)): ?>
    <p class="text-gray-400">Aucun événement.</p>
<?php else: ?>

<div class="mb-6">
    <select onchange="window.location='<?php echo site_url('admin/settings/index'); ?>/'+this.value"
            class="rounded-lg border border-gray-300 px-3 py-2">
        <?php foreach ($events as $e): ?>
            <option value="<?php echo $e['id']; ?>" <?php echo ($event && $e['id']==$event['id'])?'selected':''; ?>>
                <?php echo html_escape($e['nom']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<?php echo form_open_multipart('admin/settings/save/'.$event['id'], array('class' => 'space-y-6 max-w-3xl')); ?>

    <!-- Noms & textes -->
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-6">
        <h2 class="font-display text-lg mb-4">Les mariés &amp; textes</h2>

        <label class="block mb-4">
            <span class="text-sm text-gray-600">Titre du site (affiché en grand sur l'accueil)</span>
            <input type="text" name="site_title" value="<?php echo html_escape($s['site_title'] ?? ''); ?>"
                   class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#6b6b6b]">
        </label>

        <label class="block mb-4">
            <span class="text-sm text-gray-600">Bandeau au-dessus du titre (hero)</span>
            <input type="text" name="header_label" value="<?php echo html_escape($page_titles['galerie'] ?? ''); ?>"
                   placeholder="Galerie des invités"
                   class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#6b6b6b]">
            <span class="text-xs text-gray-400">Petit texte en majuscules affiché au-dessus du grand titre. Vide = « Galerie des invités ».</span>
        </label>

        <?php $brand_val = $page_titles['brand'] ?? ''; $brand_is_custom = ($brand_val !== ''); ?>
        <div class="block mb-4">
            <span class="text-sm text-gray-600">Marque affichée (barre du haut &amp; écran de chargement)</span>
            <div class="mt-2 flex flex-col gap-2">
                <label class="flex items-center gap-2">
                    <input type="radio" name="brand_mode" value="initials" <?php echo $brand_is_custom ? '' : 'checked'; ?>
                           onchange="document.getElementById('brand-text').disabled=true;">
                    <span class="text-sm">Initiales automatiques (d'après les noms du couple, ex. « A &amp; J »)</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="brand_mode" value="custom" <?php echo $brand_is_custom ? 'checked' : ''; ?>
                           onchange="var b=document.getElementById('brand-text'); b.disabled=false; b.focus();">
                    <span class="text-sm">Texte personnalisé</span>
                </label>
                <input type="text" id="brand-text" name="brand_text" value="<?php echo html_escape($brand_val); ?>"
                       placeholder="Ex. Awa &amp; Junior, Notre mariage…" <?php echo $brand_is_custom ? '' : 'disabled'; ?>
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#6b6b6b]">
            </div>
            <span class="text-xs text-gray-400 block mt-1">Par défaut, les initiales du couple. Choisissez « Texte personnalisé » pour mettre votre propre texte.</span>
        </div>

        <label class="block mb-4">
            <span class="text-sm text-gray-600">Noms du couple (ex. « Awa &amp; Junior »)</span>
            <input type="text" name="couple_noms" value="<?php echo html_escape($couple['noms'] ?? ''); ?>"
                   class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#6b6b6b]">
            <span class="text-xs text-gray-400">Sert aussi à générer le monogramme de l'écran de chargement.</span>
        </label>

        <div class="grid sm:grid-cols-2 gap-4 mb-4">
            <label class="block">
                <span class="text-sm text-gray-600">Date (texte libre)</span>
                <input type="text" name="couple_date" value="<?php echo html_escape($couple['date'] ?? ''); ?>"
                       placeholder="12 juillet 2026"
                       class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
            </label>
            <label class="block">
                <span class="text-sm text-gray-600">Photographe / contact</span>
                <input type="text" name="couple_photographe" value="<?php echo html_escape($couple['photographe'] ?? ''); ?>"
                       class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
            </label>
        </div>

        <label class="block">
            <span class="text-sm text-gray-600">Message (pied de page)</span>
            <textarea name="couple_message" rows="2"
                      class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"><?php echo html_escape($couple['message'] ?? ''); ?></textarea>
        </label>
    </div>

    <!-- Couleurs du thème du mariage -->
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-6">
        <h2 class="font-display text-lg mb-4">Couleurs du thème</h2>
        <p class="text-xs text-gray-400 mb-4">Les couleurs d'accentuation de la galerie publique, assorties au thème du mariage.</p>
        <div class="grid sm:grid-cols-3 gap-4">
            <?php
            $color_fields = array(
                array('theme_primary', 'Couleur principale', $c_primary, 'Titres, accents secondaires.'),
                array('theme_accent',  'Couleur secondaire', $c_accent,  'Boutons, éléments mis en avant.'),
                array('theme_bg',      'Couleur de fond',    $c_bg,      'Arrière-plan général des pages.'),
            );
            foreach ($color_fields as $cf):
                list($name, $label, $val, $hint) = $cf; ?>
            <div>
                <span class="text-sm text-gray-600 block mb-1"><?php echo $label; ?></span>
                <div class="flex items-center gap-2">
                    <input type="color" name="<?php echo $name; ?>" value="<?php echo html_escape($val); ?>"
                           class="h-10 w-12 rounded border border-gray-300 p-0.5 cursor-pointer"
                           oninput="this.nextElementSibling.value=this.value">
                    <input type="text" value="<?php echo html_escape($val); ?>" readonly
                           class="w-24 rounded-lg border border-gray-200 px-2 py-2 text-sm text-gray-500 bg-gray-50">
                </div>
                <p class="text-xs text-gray-400 mt-1"><?php echo $hint; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Bannière (header) -->
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-6">
        <h2 class="font-display text-lg mb-1">Bannière du header</h2>
        <p class="text-xs text-gray-400 mb-4">Réglez le rendu de l'image d'accueil (la « photo du couple » ci-dessous sert de fond).</p>

        <!-- Aperçu live -->
        <div class="relative h-44 rounded-lg overflow-hidden bg-[#e9e8e7] mb-5">
            <div id="hdr-bg" class="absolute inset-0"
                 style="background-image:<?php echo $hdr_image ? "url('".base_url($hdr_image)."')" : 'none'; ?>;
                        background-size:cover;
                        background-position:<?php echo $hdr_pos_x; ?>% <?php echo $hdr_pos_y; ?>%;
                        filter:brightness(<?php echo $hdr_brightness; ?>%);"></div>
            <div id="hdr-overlay" class="absolute inset-0 bg-black" style="opacity:<?php echo $hdr_opacity/100; ?>;"></div>
            <div class="relative h-full flex items-center justify-center">
                <span class="font-display italic text-3xl text-white drop-shadow"><?php echo html_escape($s['site_title'] ?? ($event['nom'] ?? '')); ?></span>
            </div>
            <?php if ( ! $hdr_image): ?>
                <div class="absolute inset-0 flex items-center justify-center text-xs text-gray-500">Aucune image — importez la « photo du couple » ci-dessous.</div>
            <?php endif; ?>
        </div>

        <div class="grid sm:grid-cols-2 gap-5">
            <label class="block">
                <span class="text-sm text-gray-600 flex justify-between">Opacité du voile <span id="v-opacity" class="text-gray-400"><?php echo $hdr_opacity; ?>%</span></span>
                <input type="range" name="hdr_opacity" min="0" max="80" value="<?php echo $hdr_opacity; ?>" class="w-full mt-2 accent-[#1b1c1c]" data-hdr="opacity">
            </label>
            <label class="block">
                <span class="text-sm text-gray-600 flex justify-between">Luminosité <span id="v-brightness" class="text-gray-400"><?php echo $hdr_brightness; ?>%</span></span>
                <input type="range" name="hdr_brightness" min="50" max="130" value="<?php echo $hdr_brightness; ?>" class="w-full mt-2 accent-[#1b1c1c]" data-hdr="brightness">
            </label>
            <label class="block">
                <span class="text-sm text-gray-600 flex justify-between">Recadrage horizontal <span id="v-posx" class="text-gray-400"><?php echo $hdr_pos_x; ?>%</span></span>
                <input type="range" name="hdr_pos_x" min="0" max="100" value="<?php echo $hdr_pos_x; ?>" class="w-full mt-2 accent-[#1b1c1c]" data-hdr="posx">
            </label>
            <label class="block">
                <span class="text-sm text-gray-600 flex justify-between">Recadrage vertical <span id="v-posy" class="text-gray-400"><?php echo $hdr_pos_y; ?>%</span></span>
                <input type="range" name="hdr_pos_y" min="0" max="100" value="<?php echo $hdr_pos_y; ?>" class="w-full mt-2 accent-[#1b1c1c]" data-hdr="posy">
            </label>
        </div>

        <script>
        (function () {
            var bg = document.getElementById('hdr-bg');
            var ov = document.getElementById('hdr-overlay');
            if (!bg) return;
            var st = { opacity:<?php echo $hdr_opacity; ?>, brightness:<?php echo $hdr_brightness; ?>, posx:<?php echo $hdr_pos_x; ?>, posy:<?php echo $hdr_pos_y; ?> };
            function apply() {
                bg.style.backgroundPosition = st.posx + '% ' + st.posy + '%';
                bg.style.filter = 'brightness(' + st.brightness + '%)';
                ov.style.opacity = st.opacity / 100;
            }
            document.querySelectorAll('[data-hdr]').forEach(function (r) {
                r.addEventListener('input', function () {
                    st[r.dataset.hdr] = parseInt(r.value, 10);
                    var lbl = { opacity:'v-opacity', brightness:'v-brightness', posx:'v-posx', posy:'v-posy' }[r.dataset.hdr];
                    var el = document.getElementById(lbl);
                    if (el) el.textContent = r.value + '%';
                    apply();
                });
            });
        })();
        </script>
    </div>

    <!-- Mode d'affichage de la galerie -->
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-6">
        <h2 class="font-display text-lg mb-4">Affichage de la galerie publique</h2>
        <p class="text-xs text-gray-400 mb-4">Comment les invités voient les photos sur la page de l'événement.</p>
        <div class="flex flex-col gap-3">
            <?php
            $modes = array(
                'photos_only'   => array('Photos seules', 'Toutes les photos s\'affichent directement, sans dossiers.'),
                'folders_only'  => array('Dossiers seuls', 'Seuls les dossiers (albums) sont affichés ; on clique pour entrer.'),
                'folders_first' => array('Dossiers puis photos', 'Les dossiers s\'affichent d\'abord, puis toutes les photos en dessous.'),
            );
            foreach ($modes as $val => $info): ?>
            <label class="flex items-start gap-3">
                <input type="radio" name="gallery_mode" value="<?php echo $val; ?>" class="mt-1"
                       <?php echo ($gallery_mode === $val) ? 'checked' : ''; ?>>
                <span>
                    <span class="text-sm font-medium text-gray-700"><?php echo $info[0]; ?></span>
                    <span class="block text-xs text-gray-400"><?php echo $info[1]; ?></span>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Accès : mot de passe de galerie (« lien chiffré ») -->
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-6">
        <h2 class="font-display text-lg mb-1">Accès à la galerie</h2>
        <p class="text-xs text-gray-400 mb-4">
            La galerie est toujours privée : seuls son lien à code non devinable et son QR y donnent accès.
            Vous pouvez en plus exiger un mot de passe à l'entrée.
        </p>
        <?php if ( ! empty($can_password)): ?>
            <?php
            $pw_on  = (int) ($s['password_enabled'] ?? 0) === 1;
            $pw_set = ! empty($s['password_hash']);
            ?>
            <label class="flex items-center gap-3 mb-3">
                <input type="checkbox" name="password_enabled" value="1" <?php echo $pw_on ? 'checked' : ''; ?>
                       class="h-4 w-4 rounded border-gray-300">
                <span class="text-sm font-medium text-gray-700">Exiger un mot de passe pour ouvrir la galerie</span>
                <?php if ($pw_on && $pw_set): ?>
                    <span class="text-xs text-green-600 font-medium">— actif</span>
                <?php endif; ?>
            </label>
            <label class="block max-w-sm">
                <span class="text-sm text-gray-700">
                    <?php echo $pw_set ? 'Nouveau mot de passe (laisser vide pour conserver l\'actuel)' : 'Mot de passe de la galerie'; ?>
                </span>
                <input type="text" name="gallery_password" value="" autocomplete="off" spellcheck="false"
                       class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"
                       placeholder="<?php echo $pw_set ? 'Un mot de passe est déjà défini' : 'ex. mariage2026'; ?>">
            </label>
            <p class="text-xs text-gray-400 mt-2">4 caractères minimum. Communiquez-le aux invités avec le lien ou le QR.</p>
        <?php else: ?>
            <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3">
                Le mot de passe de galerie (« lien chiffré ») est inclus à partir du forfait <b>Pro</b>.
                <a class="underline" href="<?php echo site_url('pricing'); ?>" target="_blank">Voir les forfaits</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Écran de chargement -->
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-6">
        <h2 class="font-display text-lg mb-4">Écran de chargement</h2>

        <label class="flex items-center gap-3 mb-4">
            <input type="checkbox" name="loading_screen_enabled" value="1" <?php echo $loading_on ? 'checked' : ''; ?>
                   class="rounded border-gray-300">
            <span class="text-sm text-gray-700">Activer l'écran de chargement animé à l'ouverture</span>
        </label>

        <label class="block mb-4">
            <span class="text-sm text-gray-600">Texte affiché pendant le chargement</span>
            <input type="text" name="loading_text" value="<?php echo html_escape($page_titles['loading'] ?? ''); ?>"
                   placeholder="Chargement…"
                   class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#6b6b6b]">
            <span class="text-xs text-gray-400">Vide = « Chargement… ».</span>
        </label>

        <div class="text-sm text-gray-600 mb-2">Logo affiché pendant le chargement :</div>
        <div class="flex flex-col gap-2">
            <label class="flex items-center gap-2">
                <input type="radio" name="loading_logo_choice" value="couple_monogram" <?php echo ($logo_choice!=='photographer')?'checked':''; ?>>
                <span class="text-sm">Monogramme du couple (initiales)</span>
            </label>
            <label class="flex items-center gap-2">
                <input type="radio" name="loading_logo_choice" value="photographer" <?php echo ($logo_choice==='photographer')?'checked':''; ?>>
                <span class="text-sm">Logo du photographe</span>
            </label>
        </div>

        <label class="block mt-5">
            <span class="text-sm text-gray-600 flex justify-between">
                Temps d'affichage minimum
                <span id="v-loaddur" class="text-gray-400"><?php echo number_format($loading_dur/1000, 1); ?> s</span>
            </span>
            <input type="range" name="loading_duration" min="0" max="3000" step="100"
                   value="<?php echo $loading_dur; ?>" class="w-full mt-2 accent-[#1b1c1c]"
                   oninput="document.getElementById('v-loaddur').textContent=(this.value/1000).toFixed(1)+' s';">
            <span class="block text-xs text-gray-400 mt-1">
                Durée pendant laquelle l'écran de chargement reste visible après le chargement de la page
                (en plus du temps de chargement réel). 0&nbsp;s = disparaît immédiatement.
            </span>
        </label>
    </div>

    <!-- Logos & images -->
    <div class="bg-white rounded-xl border border-[#e4e2e2] p-6">
        <h2 class="font-display text-lg mb-4">Logos &amp; photo</h2>
        <p class="text-xs text-gray-400 mb-4">JPEG, PNG ou WebP. Laissez vide pour conserver l'image actuelle.</p>
        <div class="grid sm:grid-cols-3 gap-4">
            <?php
            echo av_img_field('Photo du couple (accueil)', 'couple_photo_path', $s['couple_photo_path'] ?? NULL, 'Grande image du hero.');
            echo av_img_field('Logo du couple', 'logo_couple_path', $s['logo_couple_path'] ?? NULL);
            echo av_img_field('Logo du photographe', 'logo_photographer_path', $s['logo_photographer_path'] ?? NULL);
            ?>
        </div>
    </div>

    <div class="flex gap-3">
        <button class="rounded-lg bg-[#1b1c1c] hover:bg-[#000000] text-white px-5 py-2.5">Enregistrer</button>
        <a href="<?php echo site_url('e/'.$event['public_code']); ?>" target="_blank"
           class="rounded-lg border border-[#e4e2e2] text-gray-600 px-5 py-2.5">Voir la galerie publique &nearr;</a>
    </div>

<?php echo form_close(); ?>

<?php endif; ?>
