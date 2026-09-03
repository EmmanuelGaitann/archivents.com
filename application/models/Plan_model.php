<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Plan_model — catalogue des forfaits/abonnements SaaS.
 *
 * Un plan porte les quotas qui pilotent les limites d'un photographe :
 * max_events, storage_mo, retention_days, max_collaborators,
 * remove_branding, custom_domain + un JSON `features` extensible.
 */
class Plan_model extends CI_Model {

    protected $table = 'plans';

    /** Décode le JSON `features` d'une ligne plan (tolère NULL/chaîne). */
    protected function decode($row)
    {
        if ($row && isset($row['features']) && is_string($row['features']))
        {
            $row['features'] = json_decode($row['features'], TRUE) ?: array();
        }
        return $row;
    }

    protected function decode_all($rows)
    {
        foreach ($rows as &$r)
        {
            $r = $this->decode($r);
        }
        return $rows;
    }

    /** Tous les plans (option : actifs seulement), triés pour l'affichage. */
    public function all($active_only = FALSE)
    {
        if ($active_only)
        {
            $this->db->where('actif', 1);
        }
        $rows = $this->db->order_by('ordre', 'ASC')->get($this->table)->result_array();
        return $this->decode_all($rows);
    }

    public function get($id)
    {
        return $this->decode($this->db->where('id', $id)->get($this->table)->row_array());
    }

    public function get_by_slug($slug)
    {
        return $this->decode($this->db->where('slug', $slug)->get($this->table)->row_array());
    }

    /** Plans d'un palier (pass|essentiel|studio|signature). */
    public function by_tier($tier, $active_only = TRUE)
    {
        $this->db->where('tier', $tier);
        if ($active_only)
        {
            $this->db->where('actif', 1);
        }
        $rows = $this->db->order_by('ordre', 'ASC')->get($this->table)->result_array();
        return $this->decode_all($rows);
    }

    /**
     * Plans actifs regroupés par palier — pratique pour la page tarifs.
     * Retourne array('essentiel' => [...], 'studio' => [...], …).
     */
    public function grouped_by_tier($active_only = TRUE)
    {
        $out = array();
        foreach ($this->all($active_only) as $p)
        {
            $out[$p['tier']][] = $p;
        }
        return $out;
    }
}
