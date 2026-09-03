<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Event_model — événements (futurs tenants).
 */
class Event_model extends CI_Model {

    protected $table = 'events';

    public function get($id)
    {
        return $this->db->where('id', $id)->get($this->table)->row_array();
    }

    public function get_by_slug($slug)
    {
        return $this->db->where('slug', $slug)->get($this->table)->row_array();
    }

    /**
     * Recherche par code public (jeton d'URL non devinable).
     */
    public function get_by_code($code)
    {
        return $this->db->where('public_code', $code)->get($this->table)->row_array();
    }

    /**
     * Génère un code public unique (non devinable) pour l'URL invité.
     */
    public function unique_code()
    {
        do {
            $code = bin2hex(random_bytes(5)); // 10 caractères hex
        } while ($this->db->where('public_code', $code)->count_all_results($this->table) > 0);
        return $code;
    }

    /**
     * Liste des événements (pour les sélecteurs admin).
     */
    public function all($only_active = FALSE)
    {
        if ($only_active)
        {
            $this->db->where('statut', 'actif');
        }
        return $this->db->order_by('date_evt', 'DESC')->get($this->table)->result_array();
    }

    /**
     * Événements appartenant à un utilisateur (cloisonnement admin).
     */
    public function for_user($user_id)
    {
        return $this->db->where('user_id', (int) $user_id)
            ->order_by('date_evt', 'DESC')
            ->get($this->table)->result_array();
    }

    /**
     * Cet événement appartient-il à cet utilisateur ?
     */
    public function owned_by($event_id, $user_id)
    {
        return (bool) $this->db
            ->where('id', (int) $event_id)
            ->where('user_id', (int) $user_id)
            ->count_all_results($this->table);
    }

    /**
     * Crée un événement. Retourne l'id.
     */
    public function create(array $data)
    {
        // Garantit un code public non devinable pour l'URL invité.
        if (empty($data['public_code']))
        {
            $data['public_code'] = $this->unique_code();
        }
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    public function update($id, array $data)
    {
        $this->db->where('id', (int) $id)->update($this->table, $data);
        return $this->db->affected_rows() >= 0;
    }

    /**
     * Supprime un événement (event_settings/albums/photos suivent via FK CASCADE).
     */
    public function delete($id)
    {
        $this->db->where('id', (int) $id)->delete($this->table);
        return $this->db->affected_rows() > 0;
    }

    /**
     * Attribue (ou retire) un événement à un utilisateur.
     */
    public function assign($event_id, $user_id)
    {
        $this->db->where('id', (int) $event_id)
            ->update($this->table, array('user_id' => $user_id ? (int) $user_id : NULL));
    }

    /**
     * Génère un slug unique à partir d'un libellé (a-z0-9 + tirets).
     */
    public function unique_slug($label, $ignore_id = 0)
    {
        $base = strtolower(trim($label));
        $base = preg_replace('/[^a-z0-9]+/', '-', $this->strip_accents($base));
        $base = trim($base, '-');
        if ($base === '') $base = 'event';

        $slug = $base; $i = 2;
        while (TRUE)
        {
            $this->db->where('slug', $slug);
            if ($ignore_id) $this->db->where('id !=', (int) $ignore_id);
            if ( ! $this->db->count_all_results($this->table))
            {
                return $slug;
            }
            $slug = $base.'-'.$i++;
        }
    }

    protected function strip_accents($s)
    {
        $from = array('à','á','â','ä','ã','å','ç','è','é','ê','ë','ì','í','î','ï','ñ','ò','ó','ô','ö','õ','ù','ú','û','ü','ý','œ','æ');
        $to   = array('a','a','a','a','a','a','c','e','e','e','e','i','i','i','i','n','o','o','o','o','o','u','u','u','u','y','oe','ae');
        return str_replace($from, $to, $s);
    }

    /**
     * Paramètres (event_settings) d'un événement.
     */
    public function settings($event_id)
    {
        return $this->db->where('event_id', $event_id)
            ->get('event_settings')->row_array();
    }

    /**
     * Met à jour la date limite de disponibilité des originaux.
     */
    public function set_retention_until($event_id, $datetime)
    {
        $this->db->where('event_id', $event_id)
            ->update('event_settings', array('originals_available_until' => $datetime));
    }

    /**
     * Met à jour les paramètres (event_settings) d'un événement.
     * Crée la ligne si elle n'existe pas encore.
     */
    public function update_settings($event_id, array $data)
    {
        $exists = $this->db->where('event_id', $event_id)
            ->count_all_results('event_settings');

        if ($exists)
        {
            $this->db->where('event_id', $event_id)->update('event_settings', $data);
        }
        else
        {
            $data['event_id'] = $event_id;
            $this->db->insert('event_settings', $data);
        }
    }
}
