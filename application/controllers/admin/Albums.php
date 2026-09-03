<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * admin/Albums — CRUD des dossiers (albums) d'un événement.
 *
 * Supprimer un album ne supprime PAS ses photos : la contrainte FK
 * (ON DELETE SET NULL) les bascule simplement en "sans dossier".
 */
class Albums extends Admin_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_permission('album.crud');
        $this->load->model(array('Event_model', 'Album_model', 'Photo_model'));
    }

    /**
     * Liste des albums d'un événement + formulaire d'ajout.
     */
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

        $albums = $event ? $this->Album_model->for_event($event_id) : array();
        $counts = $event ? $this->Photo_model->counts_by_album($event_id) : array();

        $this->render('admin/albums/index', array(
            'events' => $events,
            'event'  => $event,
            'albums' => $albums,
            'counts' => $counts,
        ));
    }

    /**
     * Création d'un album (POST).
     */
    public function create($event_id)
    {
        $event_id = (int) $event_id;
        $event = $this->Event_model->get($event_id);
        if ( ! $event) show_404();
        $this->guard_event($event_id);

        $nom = trim((string) $this->input->post('nom', TRUE));
        if ($nom === '')
        {
            $this->session->set_flashdata('err', 'Le nom du dossier est obligatoire.');
        }
        else
        {
            $this->Album_model->create($event_id, $nom);
            $this->session->set_flashdata('ok', 'Dossier « '.$nom.' » créé.');
        }
        redirect('admin/albums/index/'.$event_id);
    }

    /**
     * Renommage d'un album (POST).
     */
    public function rename($id)
    {
        $id = (int) $id;
        $album = $this->Album_model->get($id);
        if ( ! $album) show_404();
        $this->guard_event($album['event_id']);

        $nom = trim((string) $this->input->post('nom', TRUE));
        if ($nom === '')
        {
            $this->session->set_flashdata('err', 'Le nom du dossier est obligatoire.');
        }
        else
        {
            $this->Album_model->rename($id, $nom);
            $this->session->set_flashdata('ok', 'Dossier renommé.');
        }
        redirect('admin/albums/index/'.$album['event_id']);
    }

    /**
     * Monte ou descend un album d'un cran (POST, $dir = up|down).
     * L'ordre choisi ici est celui des dossiers sur la galerie publique.
     */
    public function move($id, $dir = 'up')
    {
        $id = (int) $id;
        $album = $this->Album_model->get($id);
        if ( ! $album) show_404();
        $this->guard_event($album['event_id']);

        $event_id = (int) $album['event_id'];
        $ids  = array_map('intval', array_column($this->Album_model->for_event($event_id), 'id'));
        $pos  = array_search($id, $ids, TRUE);
        $swap = ($dir === 'down') ? $pos + 1 : $pos - 1;

        if ($pos !== FALSE && $swap >= 0 && isset($ids[$swap]))
        {
            $ids[$pos]  = $ids[$swap];
            $ids[$swap] = $id;
            $this->Album_model->reorder($event_id, $ids);
        }
        redirect('admin/albums/index/'.$event_id);
    }

    /**
     * Suppression d'un album (POST). Les photos deviennent "sans dossier".
     */
    public function delete($id)
    {
        $id = (int) $id;
        $album = $this->Album_model->get($id);
        if ( ! $album) show_404();
        $this->guard_event($album['event_id']);

        $event_id = (int) $album['event_id'];
        $this->Album_model->delete($id);
        $this->session->set_flashdata('ok', 'Dossier supprimé. Ses photos sont conservées (sans dossier).');
        redirect('admin/albums/index/'.$event_id);
    }

    /* ----------------------------------------------------------------- */

    protected function render($view, $data)
    {
        $data['current_user'] = $this->current_user;
        $this->load->view('admin/layout/header', array('title' => 'Albums', 'user' => $this->current_user));
        $this->load->view($view, $data);
        $this->load->view('admin/layout/footer');
    }
}
