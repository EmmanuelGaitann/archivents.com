<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * admin/Retention — gestion de la rétention des originaux d'un événement.
 *   - Prolonger la rétention (repousse la date),
 *   - Purger maintenant (suppression immédiate des originaux de l'événement).
 */
class Retention extends Admin_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_permission('retention.manage');
        $this->load->model(array('Event_model', 'Photo_model'));
    }

    public function index($event_id = 0)
    {
        $events = $this->Event_model->all();
        $event_id = (int) $event_id;
        if ( ! $event_id && ! empty($events))
        {
            $event_id = (int) $events[0]['id'];
        }
        $event = $event_id ? $this->Event_model->get($event_id) : NULL;

        $data = array('events' => $events, 'event' => $event);

        if ($event)
        {
            $settings = $this->Event_model->settings($event_id);
            $data += array(
                'settings'            => $settings,
                'until'               => $settings['originals_available_until'] ?? NULL,
                'originals_available' => $this->Photo_model->originals_available_count($event_id),
                'total_photos'        => $this->Photo_model->count_for_event($event_id),
            );
        }

        $this->load->view('admin/layout/header', array('title' => 'Rétention', 'user' => $this->current_user));
        $this->load->view('admin/retention/index', $data);
        $this->load->view('admin/layout/footer');
    }

    /**
     * Prolonge la rétention de N heures à partir de la date actuelle de rétention
     * (ou de maintenant si déjà dépassée).
     */
    public function extend($event_id)
    {
        $event_id = (int) $event_id;
        $event = $this->Event_model->get($event_id);
        if ( ! $event) show_404();

        $hours = (int) $this->input->post('hours');
        if ($hours < 1) $hours = 48;

        $settings = $this->Event_model->settings($event_id);
        $base = $settings['originals_available_until'] ?? NULL;
        $base_ts = ($base && strtotime($base) > time()) ? strtotime($base) : time();
        $new = date('Y-m-d H:i:s', $base_ts + $hours * 3600);

        $this->Event_model->set_retention_until($event_id, $new);
        $this->session->set_flashdata('ok', 'Rétention prolongée jusqu\'au '.date('d/m/Y H:i', strtotime($new)).'.');
        redirect('admin/retention/index/'.$event_id);
    }

    /**
     * Purge immédiate des originaux de l'événement.
     */
    public function purge($event_id)
    {
        $event_id = (int) $event_id;
        $event = $this->Event_model->get($event_id);
        if ( ! $event) show_404();

        $rows = $this->Photo_model->for_event_to_purge($event_id);
        $n = 0;
        foreach ($rows as $r)
        {
            $path = FCPATH.str_replace('/', DIRECTORY_SEPARATOR, $r['path_original']);
            if (is_file($path)) @unlink($path);
            $this->Photo_model->mark_purged($r['id']);
            $n++;
        }

        // Cale la date de rétention sur maintenant (les futurs originaux suivront).
        $this->Event_model->set_retention_until($event_id, date('Y-m-d H:i:s'));
        $this->session->set_flashdata('ok', $n.' original(aux) purgé(s). Les WebP restent disponibles.');
        redirect('admin/retention/index/'.$event_id);
    }
}
