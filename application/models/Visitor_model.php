<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Visitor_model — appareils visiteurs (identifiés par visitor_uid).
 *
 * visitor_uid = UUID généré côté client (cookie first-party + localStorage),
 * complété par une empreinte navigateur légère hashée en secours.
 * (La MAC n'est PAS récupérable côté serveur — voir README.)
 */
class Visitor_model extends CI_Model {

    protected $table = 'visitors';

    /**
     * Crée ou met à jour le visiteur, renvoie son id interne.
     */
    public function touch($event_id, $visitor_uid, $ip, $user_agent, $fingerprint_hash)
    {
        $existing = $this->db->select('id')
            ->where('visitor_uid', $visitor_uid)
            ->get($this->table)->row_array();

        $now = date('Y-m-d H:i:s');

        if ($existing)
        {
            $this->db->where('id', $existing['id'])->update($this->table, array(
                'last_seen'  => $now,
                'ip'         => $ip,
                'user_agent' => $user_agent,
            ));
            return (int) $existing['id'];
        }

        $this->db->insert($this->table, array(
            'event_id'         => $event_id,
            'visitor_uid'      => $visitor_uid,
            'first_seen'       => $now,
            'last_seen'        => $now,
            'ip'               => $ip,
            'user_agent'       => $user_agent,
            'fingerprint_hash' => $fingerprint_hash,
        ));
        return (int) $this->db->insert_id();
    }

    /**
     * Appareils uniques pour un événement (COUNT DISTINCT visitor_uid).
     */
    public function unique_devices($event_id)
    {
        return (int) $this->db
            ->where('event_id', $event_id)
            ->count_all_results($this->table);
    }

    /**
     * Nombre total d'appareils (= personnes) — global ou restreint à une
     * liste d'événements (cloisonnement). $event_ids NULL = tous.
     */
    public function count_devices($event_ids = NULL)
    {
        if (is_array($event_ids))
        {
            if (empty($event_ids)) return 0;
            $this->db->where_in('event_id', $event_ids);
        }
        return (int) $this->db->count_all_results($this->table);
    }
}
