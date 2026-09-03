<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * admin/Stats — tableau de bord statistiques d'un événement.
 * Construit à partir de visitors + visit_events.
 */
class Stats extends Admin_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_permission('stats.view');
        $this->load->model(array(
            'Event_model', 'Album_model', 'Photo_model',
            'Visitor_model', 'Visit_event_model',
        ));
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

        $data = array('events' => $events, 'event' => $event);

        if ($event)
        {
            $types   = $this->Visit_event_model->count_by_type($event_id);
            $sources = $this->Visit_event_model->opens_by_source($event_id);

            // Albums map (id => nom).
            $albums = $this->Album_model->for_event($event_id);
            $album_names = array();
            foreach ($albums as $a) { $album_names[(int) $a['id']] = $a['nom']; }

            $data += array(
                'types'            => $types,
                'sources'          => $sources,
                'unique_devices'   => $this->Visitor_model->unique_devices($event_id),
                'total_opens'      => $types['open'],
                'album_names'      => $album_names,
                'top_albums_view'  => $this->decorate_albums($this->Visit_event_model->top_albums($event_id, 'album_view'), $album_names),
                'top_albums_dl'    => $this->decorate_albums($this->Visit_event_model->top_albums($event_id, 'download'), $album_names),
                'top_photos_view'  => $this->decorate_photos($this->Visit_event_model->top_photos($event_id, 'photo_view')),
                'top_photos_dl'    => $this->decorate_photos($this->Visit_event_model->top_photos($event_id, 'download')),
                'hourly_opens'     => $this->Visit_event_model->hourly($event_id, 'open'),
                'hourly_downloads' => $this->Visit_event_model->hourly($event_id, 'download'),
            );
        }

        $this->load->view('admin/layout/header', array('title' => 'Statistiques', 'user' => $this->current_user));
        $this->load->view('admin/stats/index', $data);
        $this->load->view('admin/layout/footer');
    }

    /* ----------------------------------------------------------------- */

    protected function decorate_albums($rows, $names)
    {
        foreach ($rows as &$r)
        {
            $r['nom'] = $names[(int) $r['album_id']] ?? ('Album #'.$r['album_id']);
        }
        return $rows;
    }

    protected function decorate_photos($rows)
    {
        foreach ($rows as &$r)
        {
            $p = $this->Photo_model->get((int) $r['photo_id']);
            $r['thumb'] = $p ? base_url($p['path_thumb_webp']) : NULL;
        }
        return $rows;
    }
}
