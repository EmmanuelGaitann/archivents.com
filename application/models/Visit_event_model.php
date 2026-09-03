<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Visit_event_model — journal des évènements de visite + agrégations stats.
 */
class Visit_event_model extends CI_Model {

    protected $table = 'visit_events';

    /**
     * Enregistre un évènement de visite.
     */
    public function log($event_id, $visitor_id, $type, $album_id = NULL, $photo_id = NULL, $source = 'link')
    {
        $this->db->insert($this->table, array(
            'event_id'   => $event_id,
            'visitor_id' => $visitor_id,
            'type'       => $type,
            'album_id'   => $album_id ?: NULL,
            'photo_id'   => $photo_id ?: NULL,
            'source'     => in_array($source, array('qr', 'link'), TRUE) ? $source : 'link',
        ));
    }

    /**
     * Nombre de connexions (ouvertures) — global ou restreint à des
     * événements (cloisonnement). $event_ids NULL = tous.
     */
    public function count_connections($event_ids = NULL)
    {
        if (is_array($event_ids))
        {
            if (empty($event_ids)) return 0;
            $this->db->where_in('event_id', $event_ids);
        }
        return (int) $this->db->where('type', 'open')->count_all_results($this->table);
    }

    /* =================================================================
     |  Agrégations pour le dashboard
     | ================================================================= */

    /** Compteur par type d'évènement. */
    public function count_by_type($event_id)
    {
        $rows = $this->db->select('type, COUNT(*) AS n')
            ->where('event_id', $event_id)
            ->group_by('type')
            ->get($this->table)->result_array();

        $out = array('open' => 0, 'album_view' => 0, 'photo_view' => 0, 'download' => 0);
        foreach ($rows as $r) { $out[$r['type']] = (int) $r['n']; }
        return $out;
    }

    /** Ouvertures réparties par source (qr / link). */
    public function opens_by_source($event_id)
    {
        $rows = $this->db->select('source, COUNT(*) AS n')
            ->where('event_id', $event_id)
            ->where('type', 'open')
            ->group_by('source')
            ->get($this->table)->result_array();

        $out = array('qr' => 0, 'link' => 0);
        foreach ($rows as $r) { $out[$r['source']] = (int) $r['n']; }
        return $out;
    }

    /**
     * Top albums par type d'évènement (album_view ou download).
     * Retourne [['album_id'=>, 'n'=>], ...].
     */
    public function top_albums($event_id, $type, $limit = 5)
    {
        return $this->db->select('album_id, COUNT(*) AS n')
            ->where('event_id', $event_id)
            ->where('type', $type)
            ->where('album_id IS NOT NULL', NULL, FALSE)
            ->group_by('album_id')
            ->order_by('n', 'DESC')
            ->limit($limit)
            ->get($this->table)->result_array();
    }

    /**
     * Top photos par type d'évènement (photo_view ou download).
     */
    public function top_photos($event_id, $type, $limit = 5)
    {
        return $this->db->select('photo_id, COUNT(*) AS n')
            ->where('event_id', $event_id)
            ->where('type', $type)
            ->where('photo_id IS NOT NULL', NULL, FALSE)
            ->group_by('photo_id')
            ->order_by('n', 'DESC')
            ->limit($limit)
            ->get($this->table)->result_array();
    }

    /**
     * Répartition horaire (0..23) pour un type donné.
     */
    public function hourly($event_id, $type)
    {
        $rows = $this->db->select('HOUR(created_at) AS h, COUNT(*) AS n')
            ->where('event_id', $event_id)
            ->where('type', $type)
            ->group_by('h')
            ->get($this->table)->result_array();

        $out = array_fill(0, 24, 0);
        foreach ($rows as $r) { $out[(int) $r['h']] = (int) $r['n']; }
        return $out;
    }
}
