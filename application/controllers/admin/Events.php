<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * admin/Events — CRUD des événements + attribution à un admin.
 * Réservé au super_admin (permission event.create, qu'il est seul à avoir).
 *
 * Supprimer un événement supprime ses albums/photos/paramètres (FK CASCADE)
 * et son dossier physique uploads/{slug}.
 */
class Events extends Admin_Controller {

    protected $types = array('mariage', 'seminaire', 'anniversaire', 'bapteme', 'corporate', 'autre');

    public function __construct()
    {
        parent::__construct();
        $this->require_permission('event.create'); // super_admin uniquement
        $this->load->model(array('Event_model', 'User_model', 'Photo_model', 'Album_model'));
    }

    public function index()
    {
        $super  = $this->is_super();
        $events = $super ? $this->Event_model->all() : $this->Event_model->for_user($this->current_user['id']);
        $users  = $super ? $this->index_users() : array();

        // Décore chaque événement : propriétaire (super) + compteurs.
        foreach ($events as &$e)
        {
            $e['owner']  = ($super && $e['user_id'] && isset($users[$e['user_id']])) ? $users[$e['user_id']]['nom'] : NULL;
            $e['photos'] = $this->Photo_model->count_for_event($e['id']);
            $e['albums'] = count($this->Album_model->for_event($e['id']));
        }
        unset($e);

        $reason = NULL;
        $this->render('admin/events/index', array(
            'events'       => $events,
            'can_assign'   => $super,
            'can_create'   => $this->can_create_event($reason),
            'create_block' => $reason,                       // 'inactive' | 'quota' | NULL
            'plan'         => $super ? NULL : $this->current_plan(),
            'used'         => $super ? NULL : count($events),
        ));
    }

    public function create()
    {
        $reason = NULL;
        if ( ! $this->can_create_event($reason))
        {
            $this->session->set_flashdata('err', $this->quota_message($reason));
            redirect('admin/events');
        }
        $this->form(array(
            'id' => NULL, 'nom' => '', 'type' => 'mariage', 'date_evt' => '',
            'slug' => '', 'statut' => 'actif', 'user_id' => NULL,
        ), 'create');
    }

    public function edit($id)
    {
        $event = $this->Event_model->get((int) $id);
        if ( ! $event) show_404();
        $this->guard_event((int) $id); // photographe : uniquement les siens
        $this->form($event, 'edit');
    }

    /**
     * Enregistre (création ou édition selon la présence d'un id).
     */
    public function save()
    {
        $id    = (int) $this->input->post('id');
        $super = $this->is_super();

        // Gardes : édition => propriété ; création => quota du forfait.
        if ($id)
        {
            $this->guard_event($id);
        }
        else
        {
            $reason = NULL;
            if ( ! $this->can_create_event($reason))
            {
                $this->session->set_flashdata('err', $this->quota_message($reason));
                redirect('admin/events');
            }
        }

        $nom  = trim((string) $this->input->post('nom', TRUE));
        $type = in_array($this->input->post('type'), $this->types, TRUE) ? $this->input->post('type') : 'mariage';
        $date = $this->input->post('date_evt') ?: NULL;
        $statut = ($this->input->post('statut') === 'archive') ? 'archive' : 'actif';
        // Propriétaire : le super choisit ; le photographe est toujours propriétaire.
        $user_id = $super ? ((int) $this->input->post('user_id') ?: NULL) : (int) $this->current_user['id'];
        $slug_in = trim((string) $this->input->post('slug', TRUE));

        if ($nom === '')
        {
            $this->session->set_flashdata('err', 'Le nom de l\'événement est obligatoire.');
            redirect('admin/events/'.($id ? 'edit/'.$id : 'create'));
        }

        // Slug : saisi (nettoyé+unique) sinon généré depuis le nom.
        $slug = $this->Event_model->unique_slug($slug_in !== '' ? $slug_in : $nom, $id);

        $data = array(
            'nom' => $nom, 'type' => $type, 'date_evt' => $date,
            'slug' => $slug, 'statut' => $statut, 'user_id' => $user_id,
        );

        if ($id)
        {
            $this->Event_model->update($id, $data);
            $this->session->set_flashdata('ok', 'Événement mis à jour.');
        }
        else
        {
            $new_id = $this->Event_model->create($data);
            // Rétention des originaux selon le forfait du photographe.
            if ( ! $super)
            {
                $this->apply_plan_retention($new_id, $date);
            }
            $this->session->set_flashdata('ok', 'Événement « '.$nom.' » créé.');
        }
        redirect('admin/events');
    }

    public function delete($id)
    {
        $event = $this->Event_model->get((int) $id);
        if ( ! $event) show_404();
        $this->guard_event((int) $id); // photographe : uniquement les siens

        $slug = $event['slug'];
        $this->Event_model->delete((int) $id); // CASCADE : settings/albums/photos

        // Supprime le dossier physique des médias de l'événement.
        $dir = $this->config->item('upload_root').$slug;
        if (is_dir($dir) && strpos(realpath($dir), realpath($this->config->item('upload_root'))) === 0)
        {
            $this->rrmdir($dir);
        }

        $this->session->set_flashdata('ok', 'Événement supprimé (albums, photos et fichiers compris).');
        redirect('admin/events');
    }

    /* ----------------------------------------------------------------- */

    protected function form($event, $mode)
    {
        $super = $this->is_super();
        $this->render('admin/events/form', array(
            'mode'       => $mode,
            'event'      => $event,
            'types'      => $this->types,
            'users'      => $super ? array_values($this->index_users()) : array(),
            'can_assign' => $super,
        ));
    }

    /** Message d'explication quand la création est bloquée. */
    protected function quota_message($reason)
    {
        if ($reason === 'unverified')
        {
            return "Confirmez d'abord votre adresse e-mail (lien reçu à l'inscription — "
                 . "bouton « Renvoyer » sur le tableau de bord).";
        }
        if ($reason === 'inactive')
        {
            return "Votre abonnement n'est pas encore actif. Réglez votre forfait "
                 . "(ou contactez-nous) pour créer vos événements.";
        }
        $plan = $this->current_plan();
        $max  = $plan ? (int) $plan['max_events'] : 0;
        return "Limite de votre forfait atteinte ($max événement".($max > 1 ? 's' : '')."). "
             . "Passez à un forfait supérieur pour en créer davantage.";
    }

    /**
     * Fixe la date limite de disponibilité des originaux d'un événement
     * selon la rétention du forfait du photographe (crée la ligne settings).
     */
    protected function apply_plan_retention($event_id, $date_evt)
    {
        $plan = $this->current_plan();
        if ( ! $plan || $plan['retention_days'] === NULL)
        {
            return; // rétention illimitée : rien à fixer
        }
        $days  = (int) $plan['retention_days'];
        $base  = $date_evt ?: date('Y-m-d');
        $until = date('Y-m-d 23:59:59', strtotime($base.' +'.$days.' days'));
        $this->Event_model->update_settings($event_id, array('originals_available_until' => $until));
    }

    /** Utilisateurs indexés par id (pour l'attribution). */
    protected function index_users()
    {
        $out = array();
        foreach ($this->User_model->all() as $u)
        {
            $out[$u['id']] = $u;
        }
        return $out;
    }

    /** Suppression récursive d'un dossier (bornée à uploads/). */
    protected function rrmdir($dir)
    {
        foreach (scandir($dir) as $f)
        {
            if ($f === '.' || $f === '..') continue;
            $path = $dir.DIRECTORY_SEPARATOR.$f;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    protected function render($view, $data)
    {
        $data['current_user'] = $this->current_user;
        $this->load->view('admin/layout/header', array('title' => 'Événements', 'user' => $this->current_user));
        $this->load->view($view, $data);
        $this->load->view('admin/layout/footer');
    }
}
