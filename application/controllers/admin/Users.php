<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * admin/Users — gestion des utilisateurs (réservée au super_admin).
 * CRUD complet avec garde-fous :
 *   - email unique,
 *   - on ne supprime/désactive pas son propre compte,
 *   - on ne supprime/rétrograde pas le dernier super_admin actif.
 */
class Users extends Admin_Controller {

    public function __construct()
    {
        parent::__construct();
        // Permission réservée au super_admin (il possède '*').
        $this->require_permission('users.manage');
        $this->load->model(array('User_model', 'Role_model'));
        $this->load->library('form_validation');
    }

    /* ----------------------------------------------------------------- */

    public function index()
    {
        $this->render('admin/users/index', array(
            'users'     => $this->User_model->all(),
            'role_map'  => $this->Role_model->map(),
        ));
    }

    public function create()
    {
        $data = array(
            'mode'   => 'create',
            'roles'  => $this->Role_model->all(),
            'user'   => array('id' => NULL, 'nom' => '', 'email' => '', 'role' => 'photographe', 'actif' => 1),
            'errors' => NULL,
        );

        if ($this->input->method() === 'post')
        {
            $this->set_rules(TRUE);

            if ($this->form_validation->run())
            {
                $this->User_model->create(array(
                    'nom'      => $this->input->post('nom', TRUE),
                    'email'    => $this->input->post('email', TRUE),
                    'password' => $this->input->post('password', FALSE),
                    'role'     => $this->clean_role($this->input->post('role', TRUE)),
                    'actif'    => $this->input->post('actif') ? 1 : 0,
                ));
                $this->session->set_flashdata('ok', 'Utilisateur créé.');
                redirect('admin/users');
            }

            $data['errors'] = validation_errors();
            $data['user'] = $this->posted_user();
        }

        $this->render('admin/users/form', $data);
    }

    public function edit($id)
    {
        $user = $this->User_model->get((int) $id);
        if ( ! $user) show_404();

        $data = array(
            'mode'   => 'edit',
            'roles'  => $this->Role_model->all(),
            'user'   => $user,
            'errors' => NULL,
        );

        if ($this->input->method() === 'post')
        {
            $this->set_rules(FALSE, (int) $id);

            if ($this->form_validation->run())
            {
                $new_role = $this->clean_role($this->input->post('role', TRUE));
                $new_actif = $this->input->post('actif') ? 1 : 0;

                // Garde-fou : ne pas retirer le dernier super_admin actif.
                if ($this->would_remove_last_super_admin($user, $new_role, $new_actif))
                {
                    $data['errors'] = 'Impossible : il doit rester au moins un super_admin actif.';
                }
                else
                {
                    $this->User_model->update((int) $id, array(
                        'nom'      => $this->input->post('nom', TRUE),
                        'email'    => $this->input->post('email', TRUE),
                        'password' => $this->input->post('password', FALSE), // ignoré si vide
                        'role'     => $new_role,
                        'actif'    => $new_actif,
                    ));
                    $this->session->set_flashdata('ok', 'Utilisateur mis à jour.');
                    redirect('admin/users');
                }
            }
            else
            {
                $data['errors'] = validation_errors();
            }

            $data['user'] = $this->posted_user((int) $id);
        }

        $this->render('admin/users/form', $data);
    }

    public function delete($id)
    {
        $id = (int) $id;
        $user = $this->User_model->get($id);
        if ( ! $user) show_404();

        // POST uniquement (le formulaire envoie en POST).
        if ($this->input->method() !== 'post')
        {
            redirect('admin/users');
        }

        if ($id === (int) $this->current_user['id'])
        {
            $this->session->set_flashdata('err', 'Vous ne pouvez pas supprimer votre propre compte.');
            redirect('admin/users');
        }

        if ($user['role'] === 'super_admin'
            && $this->User_model->count_active_super_admins($id) === 0)
        {
            $this->session->set_flashdata('err', 'Impossible : il doit rester au moins un super_admin actif.');
            redirect('admin/users');
        }

        $this->User_model->delete($id);
        $this->session->set_flashdata('ok', 'Utilisateur supprimé.');
        redirect('admin/users');
    }

    /* -----------------------------------------------------------------
     |  Helpers
     | ----------------------------------------------------------------- */

    protected function set_rules($require_password, $exclude_id = NULL)
    {
        $this->form_validation->set_rules('nom', 'Nom', 'required|max_length[150]');

        $unique = $exclude_id
            ? '|callback_email_unique['.$exclude_id.']'
            : '|callback_email_unique';
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email|max_length[190]'.$unique);

        $pw_rule = $require_password ? 'required|min_length[8]' : 'min_length[8]';
        $this->form_validation->set_rules('password', 'Mot de passe', $pw_rule);
        $this->form_validation->set_rules('role', 'Rôle', 'required');
    }

    /** Callback de validation : email unique. */
    public function email_unique($email, $exclude_id = NULL)
    {
        if ($this->User_model->email_exists($email, $exclude_id ?: NULL))
        {
            $this->form_validation->set_message('email_unique', 'Cet email est déjà utilisé.');
            return FALSE;
        }
        return TRUE;
    }

    /** N'accepte qu'un slug de rôle existant en base. */
    protected function clean_role($role)
    {
        return $this->Role_model->exists($role) ? $role : 'photographe';
    }

    protected function would_remove_last_super_admin($user, $new_role, $new_actif)
    {
        $was_active_super = ($user['role'] === 'super_admin' && (int) $user['actif'] === 1);
        $still_active_super = ($new_role === 'super_admin' && $new_actif === 1);

        return $was_active_super
            && ! $still_active_super
            && $this->User_model->count_active_super_admins($user['id']) === 0;
    }

    protected function posted_user($id = NULL)
    {
        return array(
            'id'    => $id,
            'nom'   => $this->input->post('nom', TRUE),
            'email' => $this->input->post('email', TRUE),
            'role'  => $this->input->post('role', TRUE),
            'actif' => $this->input->post('actif') ? 1 : 0,
        );
    }

    protected function render($view, $data)
    {
        // Rend l'utilisateur courant disponible aux vues de contenu
        // ($this->current_user n'est pas accessible depuis une vue).
        $data['current_user'] = $this->current_user;

        $this->load->view('admin/layout/header', array('title' => 'Utilisateurs', 'user' => $this->current_user));
        $this->load->view($view, $data);
        $this->load->view('admin/layout/footer');
    }
}
