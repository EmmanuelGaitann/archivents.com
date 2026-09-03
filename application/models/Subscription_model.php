<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Subscription_model — abonnement d'un photographe à un plan.
 *
 * Source de vérité de l'état d'abonnement (statut, expiration, quota).
 * L'activation peut être manuelle (super_admin) ou automatique (paiement).
 */
class Subscription_model extends CI_Model {

    protected $table = 'subscriptions';

    public function get($id)
    {
        return $this->db->where('id', $id)->get($this->table)->row_array();
    }

    /** Tous les abonnements d'un utilisateur (récents d'abord). */
    public function for_user($user_id)
    {
        return $this->db
            ->where('user_id', $user_id)
            ->order_by('id', 'DESC')
            ->get($this->table)
            ->result_array();
    }

    /**
     * Abonnement actif d'un utilisateur (statut = actif et non expiré),
     * ou NULL. Le plus récent l'emporte.
     */
    public function active_for_user($user_id)
    {
        return $this->db
            ->where('user_id', $user_id)
            ->where('statut', 'actif')
            ->group_start()
                ->where('expires_at IS NULL', NULL, FALSE)
                ->or_where('expires_at >=', date('Y-m-d H:i:s'))
            ->group_end()
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get($this->table)
            ->row_array();
    }

    /**
     * Crée un abonnement. $data doit contenir user_id + plan_id ;
     * les autres champs (statut, dates, quota) sont optionnels.
     * Retourne l'id inséré.
     */
    public function create(array $data)
    {
        $this->db->insert($this->table, $data);
        return (int) $this->db->insert_id();
    }

    public function update($id, array $data)
    {
        return $this->db->where('id', $id)->update($this->table, $data);
    }

    /**
     * Active un abonnement : statut=actif, date de début et d'expiration
     * calculées selon la période du plan (mensuel/annuel ; per_event = sans
     * expiration). Retourne les champs appliqués.
     */
    public function activate($id, array $plan, $note = NULL)
    {
        $now = date('Y-m-d H:i:s');
        $expires = NULL;
        if ($plan['billing_period'] === 'monthly')
        {
            $expires = date('Y-m-d H:i:s', strtotime('+1 month'));
        }
        elseif ($plan['billing_period'] === 'yearly')
        {
            $expires = date('Y-m-d H:i:s', strtotime('+1 year'));
        }

        $fields = array(
            'statut'       => 'actif',
            'started_at'   => $now,
            'expires_at'   => $expires,
            'events_quota' => isset($plan['max_events']) ? $plan['max_events'] : NULL,
            // Nouveau cycle : les rappels d'échéance repartiront de zéro.
            'notif_j7'      => 0,
            'notif_j1'      => 0,
            'notif_expired' => 0,
        );
        if ($note !== NULL)
        {
            $fields['note'] = $note;
        }
        $this->update($id, $fields);
        return $fields;
    }

    public function set_statut($id, $statut)
    {
        return $this->update($id, array('statut' => $statut));
    }

    /* -----------------------------------------------------------------
     |  Back-office (super_admin) — listes détaillées
     | ----------------------------------------------------------------- */

    /**
     * Abonnements enrichis (utilisateur + plan), option filtre par statut.
     * Tri : en_attente d'abord (à traiter), puis récents.
     */
    public function all_detailed($statut = NULL)
    {
        $this->db
            ->select('s.*, u.nom AS user_nom, u.email AS user_email, u.studio_slug,
                      p.nom AS plan_nom, p.slug AS plan_slug, p.billing_period, p.prix AS plan_prix, p.devise')
            ->from($this->table.' s')
            ->join('users u', 'u.id = s.user_id', 'left')
            ->join('plans p', 'p.id = s.plan_id', 'left');
        if ($statut)
        {
            $this->db->where('s.statut', $statut);
        }
        $this->db
            ->order_by("FIELD(s.statut,'en_attente','actif','expire','annule')", 'ASC', FALSE)
            ->order_by('s.id', 'DESC');
        return $this->db->get()->result_array();
    }

    /** Compteurs par statut (pour les onglets de filtre). */
    public function statut_counts()
    {
        $rows = $this->db->select('statut, COUNT(*) AS n')
            ->group_by('statut')->get($this->table)->result_array();
        $out = array('en_attente' => 0, 'actif' => 0, 'expire' => 0, 'annule' => 0);
        foreach ($rows as $r)
        {
            $out[$r['statut']] = (int) $r['n'];
        }
        return $out;
    }
}
