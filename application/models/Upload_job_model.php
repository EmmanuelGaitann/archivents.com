<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Upload_job_model — file d'attente de traitement des images.
 */
class Upload_job_model extends CI_Model {

    protected $table = 'upload_jobs';

    /**
     * Crée un job "pending". Retourne l'id inséré.
     */
    public function create($event_id, $album_id, $source_path)
    {
        $this->db->insert($this->table, array(
            'event_id'    => $event_id,
            'album_id'    => $album_id ?: NULL,
            'source_path' => $source_path,
            'statut'      => 'pending',
        ));
        return $this->db->insert_id();
    }

    /**
     * IDs des prochains jobs en attente (FIFO).
     * $event_ids = NULL : tous les événements (cron/super_admin).
     * $event_ids = array : restreint à ces événements (cloisonnement admin).
     */
    public function next_pending_ids($limit = 20, $event_ids = NULL)
    {
        if (is_array($event_ids))
        {
            if (empty($event_ids)) return array(); // aucun événement accessible
            $this->db->where_in('event_id', $event_ids);
        }
        $rows = $this->db->select('id')
            ->where('statut', 'pending')
            ->order_by('id', 'ASC')
            ->limit($limit)
            ->get($this->table)->result_array();
        return array_column($rows, 'id');
    }

    /**
     * Réserve un job de façon atomique : passe pending -> processing.
     * Retourne le job réservé, ou FALSE s'il a déjà été pris.
     */
    public function claim($id)
    {
        $this->db->where('id', $id)
            ->where('statut', 'pending')
            ->update($this->table, array('statut' => 'processing'));

        if ($this->db->affected_rows() !== 1)
        {
            return FALSE;
        }
        return $this->db->where('id', $id)->get($this->table)->row_array();
    }

    public function mark_done($id)
    {
        $this->db->where('id', $id)->update($this->table, array(
            'statut'    => 'done',
            'error_msg' => NULL,
        ));
    }

    public function mark_error($id, $msg)
    {
        $this->db->where('id', $id)->update($this->table, array(
            'statut'    => 'error',
            'error_msg' => mb_substr($msg, 0, 1000),
        ));
    }

    /**
     * Compteurs par statut pour un événement (feedback admin temps réel).
     */
    public function counts($event_id)
    {
        $rows = $this->db->select('statut, COUNT(*) AS n')
            ->where('event_id', $event_id)
            ->group_by('statut')
            ->get($this->table)->result_array();

        $out = array('pending' => 0, 'processing' => 0, 'done' => 0, 'error' => 0);
        foreach ($rows as $r)
        {
            $out[$r['statut']] = (int) $r['n'];
        }
        return $out;
    }
}
