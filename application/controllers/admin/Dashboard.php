<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * admin/Dashboard — accueil du back-office (protégé).
 * Démontre le gate d'authentification + permissions de Admin_Controller.
 */
class Dashboard extends Admin_Controller {

    public function index()
    {
        $this->load->model(array('Visitor_model', 'Visit_event_model'));

        // Cloisonnement : super_admin = global (NULL) ; admin = ses événements.
        $ids = $this->is_super()
            ? NULL
            : array_map('intval', array_column($this->accessible_events(), 'id'));

        // Métriques réelles (légères) pour les cartes du tableau de bord.
        $stats = array(
            'visitors'    => $this->Visitor_model->count_devices($ids),       // personnes (appareils)
            'connections' => $this->Visit_event_model->count_connections($ids), // ouvertures
            'photos'      => $this->count_scoped('photos', $ids),
            'events'      => $this->is_super()
                                ? (int) $this->db->where('statut', 'actif')->count_all_results('events')
                                : count($this->accessible_events()),
            'albums'      => $this->count_scoped('albums', $ids),
            'pending'     => $this->count_scoped('upload_jobs', $ids, array('statut' => array('pending', 'processing'))),
        );

        // Adresse e-mail confirmée ? (bannière + bouton de renvoi)
        $this->load->model('User_model');
        $db_user = $this->User_model->get($this->current_user['id']);

        $super = $this->is_super();
        $data = array(
            'user'           => $this->current_user,
            'email_unverified' => ( ! $super && $db_user && empty($db_user['email_verified'])),
            'stats'          => $stats,
            'can_users'      => $this->has_permission('users.manage'),
            'can_delete_evt' => $this->has_permission('event.delete'),
            'can_upload'     => $this->has_permission('photo.upload'),
            'can_stats'      => $this->has_permission('stats.view'),
            'can_retention'  => $this->has_permission('retention.manage'),
            // Abonnement (photographe) : forfait, quota, état d'activation.
            'plan'           => $super ? NULL : $this->current_plan(),
            'sub_pending'    => ( ! $super && ! $this->active_subscription()),
            'events_used'    => $super ? NULL : $this->events_owned_count(),
            // Quota de stockage : consommé (octets) + plafond effectif (Mo, dérogation incluse).
            'storage_used'     => $super ? NULL : $this->storage_used_bytes(),
            'storage_quota_mo' => $super ? NULL : $this->effective_storage_mo(),
            'events_max'       => $super ? NULL : $this->effective_max_events(),
        );

        $this->load->view('admin/layout/header', array(
            'title' => 'Tableau de bord',
            'user'  => $this->current_user,
        ));
        $this->load->view('admin/dashboard', $data);
        $this->load->view('admin/layout/footer');
    }

    /**
     * Renvoie l'e-mail de confirmation d'adresse (POST depuis la bannière).
     */
    public function resend_verification()
    {
        $this->load->model('User_model');
        $this->load->library('Mailer');

        $user = $this->User_model->get($this->current_user['id']);
        if ($user && empty($user['email_verified']))
        {
            $sent = $this->mailer->verification($user['email'], $user['nom'],
                $this->mailer->email_verify_url($user['id'], $user['email']));
            $this->session->set_flashdata($sent ? 'ok' : 'err', $sent
                ? 'E-mail de confirmation renvoyé à '.$user['email'].'.'
                : 'Envoi impossible pour le moment — contactez-nous si le problème persiste.');
        }
        redirect('admin/dashboard');
    }

    /**
     * Compte une table, éventuellement restreinte à une liste d'événements
     * ($ids NULL = global) et à des conditions supplémentaires.
     */
    protected function count_scoped($table, $ids, $where = array())
    {
        if (is_array($ids))
        {
            if (empty($ids)) return 0;
            $this->db->where_in('event_id', $ids);
        }
        foreach ($where as $col => $val)
        {
            is_array($val) ? $this->db->where_in($col, $val) : $this->db->where($col, $val);
        }
        return (int) $this->db->count_all_results($table);
    }
}
