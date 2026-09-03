<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Track — endpoint de journalisation des visites (appelé en AJAX/sendBeacon).
 *
 * Reçoit : uid (visitor_uid généré côté client), type, album_id?, photo_id?, fp.
 * Calcule l'empreinte hashée et la source (qr/link) côté serveur.
 */
class Track extends Public_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('Event_model', 'Visitor_model', 'Visit_event_model'));
    }

    public function hit($slug)
    {
        // Réponse minimale (sendBeacon ignore le corps).
        $this->output->set_content_type('application/json');

        $event = $this->Event_model->get_by_code($slug);
        if ( ! $event)
        {
            return $this->output->set_status_header(404)->set_output('{"ok":false}');
        }
        $event_id = (int) $event['id'];

        $uid = (string) $this->input->post('uid');
        // Garde-fou : un visitor_uid plausible (UUID/hex), longueur bornée.
        if ($uid === '' || strlen($uid) > 64 || ! preg_match('/^[A-Za-z0-9\-]+$/', $uid))
        {
            return $this->output->set_status_header(422)->set_output('{"ok":false}');
        }

        $type = (string) $this->input->post('type');
        if ( ! in_array($type, array('open', 'album_view', 'photo_view', 'download'), TRUE))
        {
            return $this->output->set_status_header(422)->set_output('{"ok":false}');
        }

        $album_id = (int) $this->input->post('album_id') ?: NULL;
        $photo_id = (int) $this->input->post('photo_id') ?: NULL;

        $ua = substr((string) $this->input->user_agent(), 0, 255);
        $ip = $this->input->ip_address();
        $fp = (string) $this->input->post('fp');
        $fingerprint_hash = hash('sha256', $ua.'|'.$fp);

        $source = $this->source_for($event_id);

        $visitor_id = $this->Visitor_model->touch($event_id, $uid, $ip, $ua, $fingerprint_hash);
        $this->Visit_event_model->log($event_id, $visitor_id, $type, $album_id, $photo_id, $source);

        return $this->output->set_status_header(204);
    }

    /**
     * Source de la session (définie à l'ouverture via ?src=qr), défaut "link".
     */
    protected function source_for($event_id)
    {
        return ($this->session->userdata('src_'.$event_id) === 'qr') ? 'qr' : 'link';
    }
}
