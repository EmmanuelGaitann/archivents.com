<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Payment_model — traces des transactions (paiement auto ou activation manuelle).
 */
class Payment_model extends CI_Model {

    protected $table = 'payments';

    public function get($id)
    {
        return $this->db->where('id', $id)->get($this->table)->row_array();
    }

    /** Paiements d'un utilisateur (récents d'abord). */
    public function for_user($user_id)
    {
        return $this->db
            ->where('user_id', $user_id)
            ->order_by('id', 'DESC')
            ->get($this->table)
            ->result_array();
    }

    /** Derniers paiements (back-office abonnements). */
    public function recent($limit = 50)
    {
        return $this->db
            ->order_by('id', 'DESC')
            ->limit($limit)
            ->get($this->table)
            ->result_array();
    }

    /** Crée un paiement ; retourne l'id inséré. */
    public function create(array $data)
    {
        $this->db->insert($this->table, $data);
        return (int) $this->db->insert_id();
    }

    public function update($id, array $data)
    {
        return $this->db->where('id', $id)->update($this->table, $data);
    }

    /** Marque un paiement comme payé (avec date). */
    public function mark_paid($id, $reference = NULL)
    {
        $data = array('statut' => 'paye', 'paid_at' => date('Y-m-d H:i:s'));
        if ($reference !== NULL)
        {
            $data['reference'] = $reference;
        }
        return $this->update($id, $data);
    }

    /** Paiements enrichis (utilisateur + plan) pour le back-office. */
    public function recent_detailed($limit = 100)
    {
        return $this->db
            ->select('pay.*, u.nom AS user_nom, u.email AS user_email, pl.nom AS plan_nom')
            ->from($this->table.' pay')
            ->join('users u', 'u.id = pay.user_id', 'left')
            ->join('plans pl', 'pl.id = pay.plan_id', 'left')
            ->order_by('pay.id', 'DESC')
            ->limit($limit)
            ->get()->result_array();
    }

    /** Solde les paiements « en attente » d'un abonnement (activation manuelle). */
    public function mark_subscription_paid($subscription_id, $methode = NULL)
    {
        $data = array('statut' => 'paye', 'paid_at' => date('Y-m-d H:i:s'));
        if ($methode)
        {
            $data['methode'] = $methode;
        }
        $this->db->where('subscription_id', (int) $subscription_id)
            ->where('statut', 'en_attente')
            ->update($this->table, $data);
    }
}
