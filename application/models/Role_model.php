<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Role_model — rôles stockés en base (système extensible).
 */
class Role_model extends CI_Model {

    protected $table = 'roles';

    /** Tous les rôles (id, slug, nom, permissions). */
    public function all()
    {
        return $this->db->order_by('id', 'ASC')->get($this->table)->result_array();
    }

    /** Map slug => nom (pour les listes déroulantes / affichage). */
    public function map()
    {
        $out = array();
        foreach ($this->all() as $r)
        {
            $out[$r['slug']] = $r['nom'];
        }
        return $out;
    }

    public function exists($slug)
    {
        return (bool) $this->db->where('slug', $slug)->count_all_results($this->table);
    }
}
