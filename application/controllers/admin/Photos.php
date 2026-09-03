<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * admin/Photos — gestion (consultation + suppression) des photos importées.
 *
 * Affiche une grille paginée filtrable par album, avec suppression
 * unitaire (AJAX) ou multiple. La suppression efface les 4 dérivés disque
 * et la ligne en base (Photo_model::delete).
 */
class Photos extends Admin_Controller {

    /** Taille de page de la grille de gestion. */
    protected $per_page = 60;

    public function __construct()
    {
        parent::__construct();
        $this->require_permission('photo.upload');
        $this->load->model(array('Event_model', 'Album_model', 'Photo_model'));
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

        $album_q  = $this->input->get('album');
        $album_id = ($album_q === NULL || $album_q === '' || $album_q === 'all') ? NULL : (int) $album_q;
        $album_filter = ($album_id === NULL) ? 'all' : (string) $album_id;

        $page   = max(1, (int) $this->input->get('p'));
        $offset = ($page - 1) * $this->per_page;

        $total  = $event ? $this->Photo_model->count_for_event($event_id, $album_id) : 0;
        $photos = $event
            ? $this->Photo_model->for_event($event_id, $album_id, $this->per_page, $offset, 'ordre')
            : array();

        $this->render('admin/photos/index', array(
            'events'       => $events,
            'event'        => $event,
            'albums'       => $event ? $this->Album_model->for_event($event_id) : array(),
            'photos'       => $photos,
            'total'        => $total,
            'page'         => $page,
            'per_page'     => $this->per_page,
            'album_filter' => $album_filter,
        ));
    }

    /**
     * Suppression d'une photo (AJAX POST). Répond en JSON.
     */
    public function delete($id)
    {
        $this->output->set_content_type('application/json');
        $id = (int) $id;
        $photo = $this->Photo_model->get($id);
        if ( ! $photo)
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Photo introuvable.'), 404);
        }
        if ( ! $this->is_super() && ! $this->Event_model->owned_by($photo['event_id'], $this->tenant_id()))
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Non autorisé.'), 403);
        }
        $this->Photo_model->delete($id);
        return $this->json(array('ok' => TRUE, 'id' => $id));
    }

    /**
     * Suppression multiple (POST ids[]). Redirige avec un flash.
     */
    public function bulk_delete()
    {
        $ids = $this->input->post('ids');
        $event_id = (int) $this->input->post('event_id');
        $this->guard_event($event_id);
        $n = 0;
        if (is_array($ids))
        {
            foreach ($ids as $id)
            {
                // Ne supprime que les photos appartenant bien à cet événement.
                $p = $this->Photo_model->get((int) $id);
                if ($p && (int) $p['event_id'] === $event_id && $this->Photo_model->delete((int) $id)) $n++;
            }
        }
        $this->session->set_flashdata('ok', $n.' photo(s) supprimée(s).');
        redirect('admin/photos/index/'.$event_id);
    }

    /* ----------------------------------------------------------------- */

    protected function json($payload, $status = 200)
    {
        $this->output->set_status_header($status)->set_output(json_encode($payload));
    }

    protected function render($view, $data)
    {
        $data['current_user'] = $this->current_user;
        $this->load->view('admin/layout/header', array('title' => 'Gérer les photos', 'user' => $this->current_user));
        $this->load->view($view, $data);
        $this->load->view('admin/layout/footer');
    }
}
