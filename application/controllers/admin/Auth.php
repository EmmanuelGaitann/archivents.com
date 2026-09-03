<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * admin/Auth — connexion / déconnexion du back-office.
 * N'étend PAS Admin_Controller (sinon boucle de redirection).
 */
class Auth extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('User_model');
        $this->load->library('form_validation');
    }

    public function index()
    {
        redirect('admin/auth/login');
    }

    public function login()
    {
        // Déjà connecté ? -> dashboard.
        if ($this->session->userdata('logged_in'))
        {
            redirect('admin/dashboard');
        }

        $data = array('error' => NULL);

        if ($this->input->method() === 'post')
        {
            $this->form_validation->set_rules('email', 'Email', 'required|valid_email');
            $this->form_validation->set_rules('password', 'Mot de passe', 'required');

            if ($this->form_validation->run() === TRUE)
            {
                $email    = $this->input->post('email', TRUE);
                $password = $this->input->post('password', FALSE);

                $user = $this->User_model->verify_credentials($email, $password);

                if ($user)
                {
                    // Régénère l'ID de session contre la fixation.
                    $this->session->sess_regenerate(TRUE);

                    $this->session->set_userdata(array(
                        'logged_in'   => TRUE,
                        'user_id'     => $user['id'],
                        'user_nom'    => $user['nom'],
                        'user_email'  => $user['email'],
                        'user_role'   => $user['role'],
                        // Collaborateur : id du titulaire (NULL pour un compte direct).
                        'user_parent' => $user['parent_user_id'] ?? NULL,
                    ));

                    $dest = $this->session->userdata('redirect_after_login');
                    $this->session->unset_userdata('redirect_after_login');

                    redirect($dest ?: 'admin/dashboard');
                }

                $data['error'] = 'Identifiants invalides.';
            }
            else
            {
                $data['error'] = validation_errors();
            }
        }

        $this->load->view('admin/auth/login', $data);
    }

    public function logout()
    {
        $this->session->sess_destroy();
        redirect('admin/auth/login');
    }

    /* -----------------------------------------------------------------
     |  Mot de passe oublié
     | ----------------------------------------------------------------- */

    /** Demande de réinitialisation : envoie un lien par e-mail. */
    public function forgot()
    {
        if ($this->session->userdata('logged_in'))
        {
            redirect('admin/dashboard');
        }

        $data = array('sent' => FALSE, 'error' => NULL);

        if ($this->input->method() === 'post')
        {
            $this->form_validation->set_rules('email', 'Email', 'required|valid_email');
            if ($this->form_validation->run() === TRUE)
            {
                $email = strtolower(trim((string) $this->input->post('email', TRUE)));
                $this->load->model(array('User_model', 'Password_reset_model'));

                // On n'indique JAMAIS si l'e-mail existe (anti-énumération).
                if ($this->User_model->get_by_email($email))
                {
                    $raw = bin2hex(random_bytes(32));
                    $this->Password_reset_model->issue($email, hash('sha256', $raw), 60);
                    $this->load->library('Mailer');
                    $this->mailer->reset_link($email, site_url('reset/'.$raw));
                }
                $data['sent'] = TRUE;
            }
            else
            {
                $data['error'] = validation_errors();
            }
        }

        $this->render_site('site/auth/forgot', $data);
    }

    /** Formulaire de nouveau mot de passe (lien reçu par e-mail). */
    public function reset($token = '')
    {
        $this->load->model('Password_reset_model');
        $row = $this->Password_reset_model->find_valid(hash('sha256', (string) $token));

        if ( ! $row)
        {
            return $this->render_site('site/auth/reset', array('invalid' => TRUE, 'token' => $token, 'error' => NULL));
        }

        $data = array('invalid' => FALSE, 'token' => $token, 'error' => NULL);

        if ($this->input->method() === 'post')
        {
            $p  = (string) $this->input->post('password', FALSE);
            $p2 = (string) $this->input->post('password2', FALSE);

            if (strlen($p) < 8)      $data['error'] = 'Au moins 8 caractères.';
            elseif ($p !== $p2)      $data['error'] = 'Les mots de passe ne correspondent pas.';
            else
            {
                $this->load->model('User_model');
                $user = $this->User_model->get_by_email($row['email']);
                if ($user)
                {
                    $this->User_model->update($user['id'], array('password' => $p));
                }
                $this->Password_reset_model->delete_for_email($row['email']);
                $this->session->set_flashdata('ok', 'Mot de passe mis à jour. Vous pouvez vous connecter.');
                redirect('admin/auth/login');
            }
        }

        $this->render_site('site/auth/reset', $data);
    }

    /** Rendu d'une page publique aux couleurs du site vitrine. */
    protected function render_site($view, $data)
    {
        $data['active'] = '';
        $this->load->view('site/layout/header', $data);
        $this->load->view($view, $data);
        $this->load->view('site/layout/footer', $data);
    }
}
