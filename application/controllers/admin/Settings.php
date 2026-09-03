<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * admin/Settings — paramètres d'un événement :
 *   - noms du couple / titre du site / infos de pied de page,
 *   - écran de chargement (activer/désactiver + logo affiché),
 *   - import des logos (couple, photographe) et de la photo du couple.
 */
class Settings extends Admin_Controller {

    /** Champs image gérés : clé settings => sous-dossier/branding. */
    protected $image_fields = array(
        'couple_photo_path',
        'logo_couple_path',
        'logo_photographer_path',
    );

    public function __construct()
    {
        parent::__construct();
        $this->require_permission('settings.edit');
        $this->load->model('Event_model');
    }

    public function index($event_id = 0)
    {
        $events = $this->accessible_events();
        $event_id = (int) $event_id;
        if ( ! $event_id && ! empty($events))
        {
            $event_id = (int) $events[0]['id'];
        }
        if ($event_id) $this->guard_event($event_id);
        $event = $event_id ? $this->Event_model->get($event_id) : NULL;

        $data = array('events' => $events, 'event' => $event, 'errors' => NULL);

        if ($event)
        {
            $settings = $this->Event_model->settings($event_id) ?: array();
            $settings['footer_couple_info'] = $this->json_decode($settings['footer_couple_info'] ?? NULL);
            $settings['theme'] = $this->json_decode($settings['theme'] ?? NULL);
            $settings['header_options'] = $this->json_decode($settings['header_options'] ?? NULL);
            $settings['page_titles'] = $this->json_decode($settings['page_titles'] ?? NULL);
            $data['settings'] = $settings;
        }

        // Mot de passe de galerie : réservé aux forfaits qui l'incluent.
        $data['can_password'] = $this->plan_allows_password();

        $this->render('admin/settings/index', $data);
    }

    public function save($event_id)
    {
        $event_id = (int) $event_id;
        $event = $this->Event_model->get($event_id);
        if ( ! $event) show_404();
        $this->guard_event($event_id);

        $current = $this->Event_model->settings($event_id) ?: array();

        // --- Champs texte ---
        $couple = array(
            'noms'        => $this->input->post('couple_noms', TRUE),
            'date'        => $this->input->post('couple_date', TRUE),
            'message'     => $this->input->post('couple_message', TRUE),
            'photographe' => $this->input->post('couple_photographe', TRUE),
        );

        // Titres/textes éditables (fusionnés avec l'existant : on préserve
        // les clés non exposées dans le formulaire, ex. 'accueil', 'password').
        $titles = $this->json_decode($current['page_titles'] ?? NULL);
        $header_label = trim((string) $this->input->post('header_label', TRUE));
        $loading_text = trim((string) $this->input->post('loading_text', TRUE));
        if ($header_label !== '') { $titles['galerie'] = $header_label; } else { unset($titles['galerie']); }
        if ($loading_text !== '') { $titles['loading'] = $loading_text; } else { unset($titles['loading']); }

        // Marque du header/écran de chargement : texte perso ou initiales auto.
        $brand_mode = $this->input->post('brand_mode');
        $brand_text = trim((string) $this->input->post('brand_text', TRUE));
        if ($brand_mode === 'custom' && $brand_text !== '') { $titles['brand'] = $brand_text; }
        else { unset($titles['brand']); }

        $gallery_mode = $this->input->post('gallery_mode');
        if ( ! in_array($gallery_mode, array('photos_only', 'folders_only', 'folders_first'), TRUE))
        {
            $gallery_mode = 'folders_first';
        }

        // Couleurs du thème du mariage (validées en #rrggbb, repli sur défaut).
        $theme = array(
            'primary' => $this->sanitize_hex($this->input->post('theme_primary'), '#b08968'),
            'accent'  => $this->sanitize_hex($this->input->post('theme_accent'),  '#7f5539'),
            'bg'      => $this->sanitize_hex($this->input->post('theme_bg'),       '#fdf8f3'),
        );

        // Réglages du fond du header (bannière) : opacité du voile, luminosité
        // de l'image, position de recadrage (background-position en %).
        $header_options = array(
            'opacity'    => $this->clamp_int($this->input->post('hdr_opacity'),    0, 80,  30),
            'brightness' => $this->clamp_int($this->input->post('hdr_brightness'), 50, 130, 100),
            'pos_x'      => $this->clamp_int($this->input->post('hdr_pos_x'),      0, 100, 50),
            'pos_y'      => $this->clamp_int($this->input->post('hdr_pos_y'),      0, 100, 50),
        );

        $update = array(
            'site_title'             => $this->input->post('site_title', TRUE),
            'page_titles'            => json_encode($titles, JSON_UNESCAPED_UNICODE),
            'footer_couple_info'     => json_encode($couple, JSON_UNESCAPED_UNICODE),
            'theme'                  => json_encode($theme, JSON_UNESCAPED_UNICODE),
            'header_options'         => json_encode($header_options, JSON_UNESCAPED_UNICODE),
            'gallery_mode'           => $gallery_mode,
            'loading_screen_enabled' => $this->input->post('loading_screen_enabled') ? 1 : 0,
            'loading_logo_choice'    => ($this->input->post('loading_logo_choice') === 'photographer')
                                            ? 'photographer' : 'couple_monogram',
            'loading_screen_duration'=> $this->clamp_int($this->input->post('loading_duration'), 0, 3000, 600),
        );

        $errors = array();

        // --- Mot de passe de galerie (forfaits l'incluant uniquement) ---
        // Sans l'option au forfait, les champs postés sont IGNORÉS : l'état
        // existant (posé par un super admin, ou avant un downgrade) est gardé.
        if ($this->plan_allows_password())
        {
            $pw_enabled = $this->input->post('password_enabled') ? 1 : 0;
            $pw_new     = (string) $this->input->post('gallery_password', FALSE);

            if ($pw_new !== '')
            {
                if (mb_strlen($pw_new) < 4)
                {
                    $errors[] = 'Mot de passe de galerie : 4 caractères minimum.';
                }
                else
                {
                    $update['password_hash'] = password_hash($pw_new, PASSWORD_DEFAULT);
                }
            }

            if ($pw_enabled && empty($update['password_hash']) && empty($current['password_hash']))
            {
                $errors[] = 'Définissez un mot de passe pour activer la protection de la galerie.';
                $pw_enabled = 0;
            }
            $update['password_enabled'] = $pw_enabled;
        }

        // --- Uploads d'images (optionnels) ---
        foreach ($this->image_fields as $field)
        {
            $rel = $this->handle_image_upload($field, $event['slug'], $current[$field] ?? NULL, $errors);
            if ($rel !== NULL)
            {
                $update[$field] = $rel;
            }
        }

        if ( ! empty($errors))
        {
            $this->session->set_flashdata('err', implode(' ', $errors));
            redirect('admin/settings/index/'.$event_id);
        }

        $this->Event_model->update_settings($event_id, $update);
        $this->session->set_flashdata('ok', 'Paramètres enregistrés.');
        redirect('admin/settings/index/'.$event_id);
    }

    /* -----------------------------------------------------------------
     |  Helpers
     | ----------------------------------------------------------------- */

    /**
     * Gère l'upload d'un champ image optionnel.
     * Retourne le chemin relatif si un nouveau fichier a été envoyé,
     * NULL si aucun fichier (on garde l'existant). Empile les erreurs.
     */
    protected function handle_image_upload($field, $slug, $old_rel, &$errors)
    {
        if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE)
        {
            return NULL; // pas de nouveau fichier
        }

        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK)
        {
            $errors[] = ucfirst($field).' : échec de l\'upload.';
            return NULL;
        }

        $tmp = $_FILES[$field]['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmp);
        finfo_close($finfo);

        $allowed = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
        if ( ! isset($allowed[$mime]))
        {
            $errors[] = ucfirst($field).' : format non autorisé ('.$mime.'). Utilisez JPEG, PNG ou WebP.';
            return NULL;
        }

        $dir = $this->config->item('upload_root').$slug.DIRECTORY_SEPARATOR.'branding'.DIRECTORY_SEPARATOR;
        if ( ! is_dir($dir) && ! @mkdir($dir, 0775, TRUE) && ! is_dir($dir))
        {
            $errors[] = 'Dossier branding non accessible en écriture.';
            return NULL;
        }

        $name = bin2hex(random_bytes(12)).'.'.$allowed[$mime];
        if ( ! move_uploaded_file($tmp, $dir.$name))
        {
            $errors[] = ucfirst($field).' : enregistrement impossible.';
            return NULL;
        }

        // Supprime l'ancien fichier s'il existait.
        if ($old_rel)
        {
            $old_abs = FCPATH.str_replace('/', DIRECTORY_SEPARATOR, $old_rel);
            if (is_file($old_abs)) @unlink($old_abs);
        }

        return 'uploads/'.$slug.'/branding/'.$name;
    }

    /**
     * Valide une couleur #rrggbb (#rgb accepté et étendu). Repli sur défaut.
     */
    protected function sanitize_hex($val, $default)
    {
        $val = trim((string) $val);
        if (preg_match('/^#([0-9a-fA-F]{6})$/', $val))
        {
            return strtolower($val);
        }
        if (preg_match('/^#([0-9a-fA-F]{3})$/', $val, $m))
        {
            $h = $m[1];
            return strtolower('#'.$h[0].$h[0].$h[1].$h[1].$h[2].$h[2]);
        }
        return $default;
    }

    /**
     * Convertit en entier borné [$min,$max], repli sur $default si vide/invalide.
     */
    protected function clamp_int($val, $min, $max, $default)
    {
        if ($val === NULL || $val === '' || ! is_numeric($val))
        {
            return $default;
        }
        $n = (int) $val;
        return max($min, min($max, $n));
    }

    protected function json_decode($raw)
    {
        if (is_array($raw)) return $raw;
        if (empty($raw))    return array();
        $d = json_decode($raw, TRUE);
        return is_array($d) ? $d : array();
    }

    protected function render($view, $data)
    {
        $data['current_user'] = $this->current_user;
        $this->load->view('admin/layout/header', array('title' => 'Paramètres', 'user' => $this->current_user));
        $this->load->view($view, $data);
        $this->load->view('admin/layout/footer');
    }
}
