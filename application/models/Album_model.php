<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Album_model — dossiers/albums d'un événement.
 */
class Album_model extends CI_Model {

    protected $table = 'albums';

    public function get($id)
    {
        return $this->db->where('id', $id)->get($this->table)->row_array();
    }

    /**
     * Albums d'un événement (triés par ordre).
     */
    public function for_event($event_id)
    {
        return $this->db->where('event_id', $event_id)
            ->order_by('ordre', 'ASC')
            ->order_by('id', 'ASC')
            ->get($this->table)->result_array();
    }

    /**
     * Vérifie qu'un album appartient bien à l'événement.
     */
    public function belongs_to($album_id, $event_id)
    {
        if (empty($album_id))
        {
            return TRUE; // album_id NULL = "sans dossier" : autorisé
        }
        return (bool) $this->db
            ->where('id', $album_id)
            ->where('event_id', $event_id)
            ->count_all_results($this->table);
    }

    /**
     * Crée un album à la fin de la liste de l'événement. Retourne l'id.
     */
    public function create($event_id, $nom)
    {
        $this->db->insert($this->table, array(
            'event_id' => (int) $event_id,
            'nom'      => $nom,
            'ordre'    => $this->next_ordre($event_id),
        ));
        return $this->db->insert_id();
    }

    /**
     * Renomme un album.
     */
    public function rename($id, $nom)
    {
        $this->db->where('id', (int) $id)->update($this->table, array('nom' => $nom));
        return $this->db->affected_rows() >= 0;
    }

    /**
     * Supprime un album. Les photos rattachées passent "sans dossier"
     * (FK ON DELETE SET NULL) : aucune photo n'est perdue.
     */
    public function delete($id)
    {
        $this->db->where('id', (int) $id)->delete($this->table);
        return $this->db->affected_rows() > 0;
    }

    /**
     * Réordonne : applique un ordre croissant selon une liste d'ids.
     * Ignore les ids n'appartenant pas à l'événement (sécurité).
     */
    public function reorder($event_id, array $ids)
    {
        $ordre = 0;
        foreach ($ids as $id)
        {
            $this->db->where('id', (int) $id)
                ->where('event_id', (int) $event_id)
                ->update($this->table, array('ordre' => $ordre++));
        }
        return TRUE;
    }

    /**
     * Prochaine valeur d'ordre pour un événement.
     */
    public function next_ordre($event_id)
    {
        $row = $this->db->select_max('ordre', 'm')
            ->where('event_id', (int) $event_id)
            ->get($this->table)->row_array();
        return ((int) ($row['m'] ?? -1)) + 1;
    }
}
