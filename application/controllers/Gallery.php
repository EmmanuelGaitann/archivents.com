<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Gallery — site public (invités).
 *
 * Accès par slug d'événement : /e/{slug}
 *   - écran de chargement + hero + galerie (2 modes) + footer,
 *   - mot de passe optionnel (validé en session),
 *   - scroll infini via /e/{slug}/photos (JSON),
 *   - téléchargement par photo (logique original/WebP).
 *
 * NB : la journalisation des visites (visit_events) est ajoutée en Phase 4.
 */
class Gallery extends Public_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model(array(
            'Event_model', 'Album_model', 'Photo_model', 'Video_model',
            'Visitor_model', 'Visit_event_model',
            'Subscription_model', 'Plan_model',
        ));
        $this->load->library('r2');
        $this->load->helper('media');
    }

    /* =================================================================
     |  Pages
     | ================================================================= */

    /**
     * Accueil de l'événement : hero + galerie.
     */
    public function view($slug)
    {
        $ctx = $this->load_context($slug);

        // Capture la source (?src=qr) à l'entrée, mémorisée en session.
        if ($this->input->get('src') === 'qr')
        {
            $this->session->set_userdata('src_'.$ctx['event']['id'], 'qr');
        }

        // Mur de mot de passe.
        if ($this->needs_password($ctx))
        {
            return $this->render_password($ctx);
        }

        $page_size = (int) $this->config->item('gallery_page_size');
        $event_id  = (int) $ctx['event']['id'];

        // Filtre/tri (mode all_then_filter) via query string.
        $album_q  = $this->input->get('album');
        $sort     = ($this->input->get('sort') === 'date') ? 'date' : 'ordre';
        $album_id = ($album_q === NULL || $album_q === '' || $album_q === 'all') ? NULL : (int) $album_q;
        $album_filter = ($album_id === NULL) ? 'all' : (string) $album_id;

        $data = $ctx + array(
            'gallery_mode'  => $ctx['settings']['gallery_mode'] ?? 'folders_first',
            'current_album' => NULL,        // NULL = vue d'accueil
            'albums'        => $this->Album_model->for_event($event_id),
            'album_counts'  => $this->Photo_model->counts_by_album($event_id),
            'album_covers'  => $this->Photo_model->covers_by_album($event_id),
            'total_photos'  => $this->Photo_model->count_for_event($event_id, $album_id),
            'first_photos'  => $this->Photo_model->for_event($event_id, $album_id, $page_size, 0, $sort),
            'videos'        => $this->Video_model->for_event($event_id, $album_id),
            'page_size'     => $page_size,
            'album_filter'  => $album_filter,
            'sort'          => $sort,
            'track_type'    => 'open',
            'track_album_id'=> NULL,
        );

        $this->render('public/gallery', $data);
    }

    /**
     * Vue d'un album précis (mode folders_first), ou "Tout voir" (album = all).
     */
    public function album($slug, $album_id)
    {
        $ctx = $this->load_context($slug);

        if ($this->needs_password($ctx))
        {
            return $this->render_password($ctx);
        }

        $album_id = (int) $album_id;
        $event_id = (int) $ctx['event']['id'];

        if ($album_id === 0)
        {
            // "Tout voir" : toutes les photos de l'événement.
            $album = array('id' => 0, 'nom' => 'Toutes les photos');
            $filter = NULL;
            $album_filter = 'all';
        }
        else
        {
            $album = $this->Album_model->get($album_id);
            if ( ! $album || (int) $album['event_id'] !== $event_id)
            {
                show_404();
            }
            $filter = $album_id;
            $album_filter = (string) $album_id;
        }

        $page_size = (int) $this->config->item('gallery_page_size');

        $data = $ctx + array(
            'current_album' => $album,
            'albums'        => $this->Album_model->for_event($event_id),
            'total_photos'  => $this->Photo_model->count_for_event($event_id, $filter),
            'first_photos'  => $this->Photo_model->for_event($event_id, $filter, $page_size, 0),
            'videos'        => $this->Video_model->for_event($event_id, $filter),
            'page_size'     => $page_size,
            'album_filter'  => $album_filter,
            'sort'          => 'ordre',
            'track_type'    => 'album_view',
            'track_album_id'=> ($album_id ?: NULL),
        );

        $this->render('public/album', $data);
    }

    /**
     * Endpoint AJAX : renvoie un paquet de photos en JSON (scroll infini).
     */
    public function photos($slug)
    {
        $this->output->set_content_type('application/json');
        $ctx = $this->load_context($slug, FALSE);

        if ($this->needs_password($ctx))
        {
            return $this->json(array('ok' => FALSE, 'error' => 'locked'), 403);
        }

        $event_id = (int) $ctx['event']['id'];
        $album    = $this->input->get('album'); // id numérique, 'all' ou vide
        $offset   = max(0, (int) $this->input->get('offset'));
        $sort     = ($this->input->get('sort') === 'date') ? 'date' : 'ordre';
        $limit    = (int) $this->config->item('gallery_page_size');

        $album_id = ($album === NULL || $album === '' || $album === 'all') ? NULL : (int) $album;

        $rows  = $this->Photo_model->for_event($event_id, $album_id, $limit + 1, $offset, $sort);
        $has_more = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);

        $photos = array();
        foreach ($rows as $p)
        {
            $photos[] = $this->photo_payload($slug, $p);
        }

        return $this->json(array('ok' => TRUE, 'photos' => $photos, 'has_more' => $has_more));
    }

    /**
     * Validation du mot de passe (POST).
     */
    public function password($slug)
    {
        $ctx = $this->load_context($slug);
        $settings = $ctx['settings'];

        if ($this->input->method() === 'post' && ! empty($settings['password_enabled']))
        {
            $pw = (string) $this->input->post('password', FALSE);
            if ($settings['password_hash'] && password_verify($pw, $settings['password_hash']))
            {
                $this->session->set_userdata('pw_ok_'.$ctx['event']['id'], TRUE);
                redirect('e/'.$slug);
            }
            $ctx['pw_error'] = 'Mot de passe incorrect.';
        }

        $this->render_password($ctx);
    }

    /**
     * Téléchargement d'une photo.
     * Logique : original JPEG si disponible, sinon WebP pleine résolution.
     * Jamais d'erreur visible côté invité.
     * (Phase 4 : enregistrement d'un visit_event type=download.)
     */
    public function download($slug, $photo_id)
    {
        $ctx = $this->load_context($slug);

        if ($this->needs_password($ctx))
        {
            redirect('e/'.$slug);
        }

        $photo = $this->Photo_model->get((int) $photo_id);
        if ( ! $photo || (int) $photo['event_id'] !== (int) $ctx['event']['id'])
        {
            show_404();
        }

        // Journalise le téléchargement (le cookie av_uid est envoyé avec le GET).
        $this->log_download($ctx['event']['id'], $photo);

        // Photo stockée sur R2 : redirection vers l'original servi par le CDN
        // (egress gratuit ; le serveur ne diffuse jamais les octets).
        if ( ! empty($photo['r2_key']))
        {
            redirect($this->r2->publicUrl($photo['r2_key']));
        }

        $orig_path = $photo['path_original']
            ? FCPATH.str_replace('/', DIRECTORY_SEPARATOR, $photo['path_original'])
            : NULL;

        if ( ! $photo['original_purged'] && $orig_path && is_file($orig_path))
        {
            $this->stream_file($orig_path, $slug.'-'.$photo['id'].'.jpg', 'image/jpeg');
        }

        // Repli : WebP pleine résolution.
        $full_path = $photo['path_full_webp']
            ? FCPATH.str_replace('/', DIRECTORY_SEPARATOR, $photo['path_full_webp'])
            : NULL;

        if ($full_path && is_file($full_path))
        {
            $this->stream_file($full_path, $slug.'-'.$photo['id'].'.webp', 'image/webp');
        }

        show_404();
    }

    /* =================================================================
     |  Helpers
     | ================================================================= */

    /**
     * Charge l'événement + settings décodés, ou 404.
     */
    protected function load_context($slug, $html_404 = TRUE)
    {
        $event = $this->Event_model->get_by_code($slug);
        if ( ! $event || $event['statut'] === 'archive')
        {
            if ($html_404) show_404();
            $this->json(array('ok' => FALSE, 'error' => 'not_found'), 404);
            exit;
        }

        $settings = $this->Event_model->settings($event['id']) ?: array();

        // Décodage JSON + valeurs par défaut.
        $settings['page_titles']        = $this->json_decode($settings['page_titles'] ?? NULL);
        $settings['footer_couple_info'] = $this->json_decode($settings['footer_couple_info'] ?? NULL);
        $settings['theme']              = $this->json_decode($settings['theme'] ?? NULL);
        $settings['header_options']     = $this->json_decode($settings['header_options'] ?? NULL);

        $flags = $this->owner_plan_flags($event['user_id'] ?? NULL);
        return array(
            'event'          => $event,
            'settings'       => $settings,
            'slug'           => $slug,
            'show_branding'  => $flags['branding'],   // « Propulsé par Archivents »
            'show_watermark' => $flags['watermark'],  // filigrane (forfait gratuit)
        );
    }

    /**
     * Drapeaux d'affichage dérivés du forfait ACTIF du propriétaire :
     *  - branding  : afficher « Propulsé par Archivents » (retiré si remove_branding) ;
     *  - watermark : superposer un filigrane (plan gratuit).
     * Sans propriétaire / sans abonnement actif : marque affichée, pas de filigrane.
     */
    protected function owner_plan_flags($user_id)
    {
        if ( ! $user_id) return array('branding' => TRUE, 'watermark' => FALSE);
        $sub = $this->Subscription_model->active_for_user($user_id);
        if ( ! $sub) return array('branding' => TRUE, 'watermark' => FALSE);
        $plan = $this->Plan_model->get($sub['plan_id']);
        return array(
            'branding'  => empty($plan['remove_branding']),
            'watermark' => ! empty($plan['watermark']),
        );
    }

    /**
     * Journalise un téléchargement à partir du cookie visiteur first-party.
     */
    protected function log_download($event_id, $photo)
    {
        $uid = (string) $this->input->cookie('av_uid');
        if ($uid === '' || ! preg_match('/^[A-Za-z0-9\-]{1,64}$/', $uid))
        {
            return; // visiteur sans cookie (ex. JS désactivé) : pas de log
        }

        $ua = substr((string) $this->input->user_agent(), 0, 255);
        $ip = $this->input->ip_address();
        $fp = hash('sha256', $ua.'|cookie');
        $source = ($this->session->userdata('src_'.$event_id) === 'qr') ? 'qr' : 'link';

        $visitor_id = $this->Visitor_model->touch($event_id, $uid, $ip, $ua, $fp);
        $this->Visit_event_model->log(
            $event_id, $visitor_id, 'download',
            $photo['album_id'] ?: NULL, $photo['id'], $source
        );
    }

    protected function needs_password($ctx)
    {
        $s = $ctx['settings'];
        if (empty($s['password_enabled']))
        {
            return FALSE;
        }
        return ! $this->session->userdata('pw_ok_'.$ctx['event']['id']);
    }

    protected function render_password($ctx)
    {
        $this->load->view('public/password', $ctx + array(
            'pw_error' => $ctx['pw_error'] ?? NULL,
        ));
    }

    /**
     * Construit le payload JSON d'une photo (URLs + dimensions).
     */
    protected function photo_payload($slug, $p)
    {
        return array(
            'id'       => (int) $p['id'],
            'thumb'    => av_thumb_url($p),
            'medium'   => av_medium_url($p),
            'full'     => av_full_url($p),
            'w'        => (int) $p['largeur'],
            'h'        => (int) $p['hauteur'],
            'download' => site_url('e/'.$slug.'/download/'.$p['id']),
        );
    }

    /**
     * Rendu d'une page publique avec layout (header/footer).
     */
    protected function render($view, $data)
    {
        $this->load->view('public/layout/header', $data);
        $this->load->view($view, $data);
        $this->load->view('public/layout/footer', $data);
    }

    protected function stream_file($path, $download_name, $mime)
    {
        // Nettoie tout buffer pour un flux binaire propre.
        while (ob_get_level()) { ob_end_clean(); }

        header('Content-Description: File Transfer');
        header('Content-Type: '.$mime);
        header('Content-Disposition: attachment; filename="'.$download_name.'"');
        header('Content-Length: '.filesize($path));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        readfile($path);
        exit;
    }

    protected function json_decode($raw)
    {
        if (is_array($raw)) return $raw;
        if (empty($raw))    return array();
        $d = json_decode($raw, TRUE);
        return is_array($d) ? $d : array();
    }

    protected function json($payload, $status = 200)
    {
        $this->output->set_status_header($status)->set_output(json_encode($payload));
    }
}
