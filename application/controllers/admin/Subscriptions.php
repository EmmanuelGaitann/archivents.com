<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * admin/Subscriptions — back-office des abonnements (super_admin).
 *
 * Console de l'opérateur : activer un abonnement « en attente » (après
 * paiement OM/MoMo/virement), prolonger, expirer, annuler, et marquer
 * les paiements. C'est le cœur de l'encaissement en activation manuelle.
 */
class Subscriptions extends Admin_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_permission('subscriptions.manage'); // super_admin uniquement
        $this->load->model(array('Subscription_model', 'Plan_model', 'User_model', 'Payment_model'));
    }

    public function index()
    {
        $statut = $this->input->get('statut');
        $valid  = array('en_attente', 'actif', 'expire', 'annule');
        $statut = in_array($statut, $valid, TRUE) ? $statut : NULL;

        // Stockage réellement consommé par photographe (photos + vidéos).
        $this->load->model(array('Photo_model', 'Video_model'));
        $storage = $this->Photo_model->bytes_by_user();
        foreach ($this->Video_model->bytes_by_user() as $uid => $b)
        {
            $storage[$uid] = ($storage[$uid] ?? 0) + $b;
        }

        $this->render('admin/subscriptions/index', array(
            'subs'            => $this->Subscription_model->all_detailed($statut),
            'counts'          => $this->Subscription_model->statut_counts(),
            'filter'          => $statut,
            'payments'        => $this->Payment_model->recent_detailed(60),
            'storage_by_user' => $storage,
        ));
    }

    /**
     * Dérogations de quota accordées par le super admin sur un abonnement :
     * events_quota (nb d'événements) et storage_quota_mo (stockage en Mo).
     * Champ vide = revenir à la valeur du plan ; 0 = illimité.
     */
    public function quota($id)
    {
        $sub = $this->Subscription_model->get((int) $id);
        if ( ! $sub) show_404();

        $fields = array();
        foreach (array('events_quota', 'storage_quota_mo') as $f)
        {
            $v = trim((string) $this->input->post($f, TRUE));
            $fields[$f] = ($v === '') ? NULL : max(0, (int) $v);
        }

        $this->Subscription_model->update((int) $id, $fields);
        $this->session->set_flashdata('ok', 'Dérogations de quota enregistrées ('
            .($fields['events_quota'] === NULL ? 'événements : plan' : 'événements : '.($fields['events_quota'] ?: 'illimité'))
            .' · '
            .($fields['storage_quota_mo'] === NULL ? 'stockage : plan' : 'stockage : '.($fields['storage_quota_mo'] ? $fields['storage_quota_mo'].' Mo' : 'illimité'))
            .').');
        redirect('admin/subscriptions');
    }

    /** Active un abonnement (fixe l'échéance selon le plan) + solde le paiement. */
    public function activate($id)
    {
        $sub = $this->Subscription_model->get((int) $id);
        if ( ! $sub) show_404();

        $plan = $this->Plan_model->get($sub['plan_id']);
        $this->Subscription_model->activate((int) $id, $plan,
            'Activé par '.$this->current_user['nom'].' le '.date('Y-m-d H:i'));

        // Dénormalisation côté utilisateur + solde des paiements en attente.
        $this->User_model->update($sub['user_id'], array(
            'current_plan_id'         => $plan['id'],
            'current_subscription_id' => (int) $id,
        ));
        $this->Payment_model->mark_subscription_paid((int) $id, 'manuel');

        // E-mail de confirmation au photographe (no-op si SMTP non configuré).
        $user = $this->User_model->get($sub['user_id']);
        if ($user)
        {
            $this->load->library('Mailer');
            $this->mailer->activated($user['email'], $user['nom'], $plan['nom']);
        }

        $this->session->set_flashdata('ok', 'Abonnement activé.');
        redirect('admin/subscriptions');
    }

    /** Prolonge un abonnement d'une période de plan (mensuel/annuel). */
    public function extend($id)
    {
        $sub  = $this->Subscription_model->get((int) $id);
        if ( ! $sub) show_404();
        $plan = $this->Plan_model->get($sub['plan_id']);

        $now  = date('Y-m-d H:i:s');
        $base = ($sub['expires_at'] && $sub['expires_at'] > $now) ? $sub['expires_at'] : $now;

        if ($plan['billing_period'] === 'monthly')      $exp = date('Y-m-d H:i:s', strtotime($base.' +1 month'));
        elseif ($plan['billing_period'] === 'yearly')   $exp = date('Y-m-d H:i:s', strtotime($base.' +1 year'));
        else {
            $this->session->set_flashdata('err', 'Prolongation non applicable à ce forfait.');
            redirect('admin/subscriptions');
        }

        $this->Subscription_model->update((int) $id, array(
            'statut'        => 'actif',
            'expires_at'    => $exp,
            // Nouveau cycle : les rappels d'échéance repartiront de zéro.
            'notif_j7'      => 0,
            'notif_j1'      => 0,
            'notif_expired' => 0,
        ));
        $this->session->set_flashdata('ok', 'Abonnement prolongé jusqu\'au '.substr($exp, 0, 10).'.');
        redirect('admin/subscriptions');
    }

    public function expire($id)
    {
        $this->Subscription_model->set_statut((int) $id, 'expire');
        $this->session->set_flashdata('ok', 'Abonnement marqué expiré.');
        redirect('admin/subscriptions');
    }

    public function cancel($id)
    {
        $this->Subscription_model->set_statut((int) $id, 'annule');
        $this->session->set_flashdata('ok', 'Abonnement annulé.');
        redirect('admin/subscriptions');
    }

    /** Marque un paiement précis comme payé. */
    public function pay($payment_id)
    {
        $ref = trim((string) $this->input->post('reference', TRUE)) ?: NULL;
        $this->Payment_model->mark_paid((int) $payment_id, $ref);
        $this->session->set_flashdata('ok', 'Paiement marqué payé.');
        redirect('admin/subscriptions');
    }

    protected function render($view, $data)
    {
        $this->load->view('admin/layout/header', array('title' => 'Abonnements', 'user' => $this->current_user));
        $this->load->view($view, $data);
        $this->load->view('admin/layout/footer');
    }
}
