<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * admin/Qr — affiche le QR code d'accès public d'un événement.
 * Le QR encode l'URL publique avec ?src=qr pour distinguer la provenance
 * dans les statistiques.
 */
class Qr extends Admin_Controller {

    public function __construct()
    {
        parent::__construct();
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

        $public_url = $event ? site_url('e/'.$event['public_code']).'?src=qr' : '';

        $this->load->view('admin/layout/header', array('title' => 'QR code', 'user' => $this->current_user));
        $this->load->view('admin/qr/index', array(
            'events'     => $events,
            'event'      => $event,
            'public_url' => $public_url,
        ));
        $this->load->view('admin/layout/footer');
    }
}
