<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * admin/Collaborators — collaborateurs du photographe titulaire.
 *
 * Un collaborateur est un compte `users` (role = collaborateur) rattaché au
 * titulaire par parent_user_id. Il travaille sur les événements du titulaire
 * (photos, albums, paramètres, stats) ; il ne crée pas d'événement et ne
 * touche ni à l'abonnement ni aux collaborateurs. Le plafond est
 * plans.max_collaborators (NULL = illimité, 0 = non inclus au forfait).
 */
class Collaborators extends Admin_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_permission('collab.manage');
        $this->load->model('User_model');

        // Le super admin gère tous les comptes via « Utilisateurs ».
        if ($this->is_super())
        {
            redirect('admin/users');
        }
    }

    public function index()
    {
        $this->render('admin/collaborators/index', array(
            'collabs' => $this->db->where('parent_user_id', $this->tenant_id())
                              ->order_by('created_at', 'ASC')->get('users')->result_array(),
            'max'     => $this->effective_max_collaborators(),   // NULL = illimité, 0 = non inclus
            'used'    => $this->collaborators_count(),
        ));
    }

    /** Création d'un collaborateur (POST). */
    public function create()
    {
        $max = $this->effective_max_collaborators();
        if ($max !== NULL && $this->collaborators_count() >= $max)
        {
            $this->session->set_flashdata('err', $max === 0
                ? 'La collaboration n\'est pas incluse dans votre forfait — passez au forfait Studio.'
                : 'Plafond de collaborateurs de votre forfait atteint ('.$max.').');
            redirect('admin/collaborators');
        }

        $nom   = trim((string) $this->input->post('nom', TRUE));
        $email = strtolower(trim((string) $this->input->post('email', TRUE)));
        $pass  = (string) $this->input->post('password', FALSE);

        $err = NULL;
        if ($nom === '' || $email === '' || $pass === '')      $err = 'Tous les champs sont obligatoires.';
        elseif ( ! filter_var($email, FILTER_VALIDATE_EMAIL))  $err = 'Adresse e-mail invalide.';
        elseif (mb_strlen($pass) < 8)                          $err = 'Mot de passe : 8 caractères minimum.';
        elseif ($this->User_model->email_exists($email))       $err = 'Cette adresse e-mail est déjà utilisée.';

        if ($err)
        {
            $this->session->set_flashdata('err', $err);
            redirect('admin/collaborators');
        }

        $this->User_model->create(array(
            'nom'            => $nom,
            'email'          => $email,
            'password'       => $pass,          // haché par User_model::create
            'role'           => 'collaborateur',
            'parent_user_id' => $this->tenant_id(),
            'actif'          => 1,
        ));

        // Informe le collaborateur (no-op si SMTP non configuré).
        $this->load->library('Mailer');
        $this->mailer->send($email, 'Vous êtes invité sur Archivents',
            '<p>Bonjour '.html_escape($nom).',</p>'
            .'<p><b>'.html_escape($this->current_user['nom']).'</b> vous a ajouté comme collaborateur '
            .'sur son espace <b>Archivents</b>.</p>'
            .'<p>Connectez-vous avec cette adresse e-mail et le mot de passe qui vous a été communiqué :</p>'
            .'<p style="margin:24px 0;"><a href="'.site_url('login').'" '
            .'style="display:inline-block;background:#bd5c33;color:#fff;text-decoration:none;'
            .'padding:12px 22px;border-radius:6px;font-weight:600;">Se connecter</a></p>');

        $this->session->set_flashdata('ok', 'Collaborateur « '.$nom.' » créé. Transmettez-lui son mot de passe.');
        redirect('admin/collaborators');
    }

    /** Active/suspend un collaborateur (POST). Suspendu = connexion refusée. */
    public function toggle($id)
    {
        $collab = $this->own_collab_or_404($id);
        $this->User_model->update($collab['id'], array('actif' => $collab['actif'] ? 0 : 1));
        $this->session->set_flashdata('ok', $collab['actif']
            ? 'Accès de « '.$collab['nom'].' » suspendu.'
            : 'Accès de « '.$collab['nom'].' » réactivé.');
        redirect('admin/collaborators');
    }

    /** Nouveau mot de passe pour un collaborateur (POST). */
    public function password($id)
    {
        $collab = $this->own_collab_or_404($id);
        $pass = (string) $this->input->post('password', FALSE);
        if (mb_strlen($pass) < 8)
        {
            $this->session->set_flashdata('err', 'Mot de passe : 8 caractères minimum.');
        }
        else
        {
            $this->User_model->update($collab['id'], array('password' => $pass));
            $this->session->set_flashdata('ok', 'Mot de passe de « '.$collab['nom'].' » mis à jour.');
        }
        redirect('admin/collaborators');
    }

    /** Supprime un collaborateur (POST). Ne touche à aucune photo/galerie. */
    public function delete($id)
    {
        $collab = $this->own_collab_or_404($id);
        $this->User_model->delete($collab['id']);
        $this->session->set_flashdata('ok', 'Collaborateur « '.$collab['nom'].' » supprimé.');
        redirect('admin/collaborators');
    }

    /** Charge le collaborateur SI ET SEULEMENT S'IL appartient au titulaire. */
    protected function own_collab_or_404($id)
    {
        $collab = $this->db->where('id', (int) $id)
            ->where('parent_user_id', $this->tenant_id())
            ->where('role', 'collaborateur')
            ->get('users')->row_array();
        if ( ! $collab) show_404();
        return $collab;
    }

    protected function render($view, $data)
    {
        $data['current_user'] = $this->current_user;
        $this->load->view('admin/layout/header', array('title' => 'Collaborateurs', 'user' => $this->current_user));
        $this->load->view($view, $data);
        $this->load->view('admin/layout/footer');
    }
}
